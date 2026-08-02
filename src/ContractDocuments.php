<?php

declare(strict_types=1);

final class ContractDocuments
{
    public const TYPE_MEMBERSHIP = 'membership';
    public const TYPE_SETTLEMENT = 'settlement';

    /** @var list<string> */
    public const DOC_TYPES = [self::TYPE_MEMBERSHIP, self::TYPE_SETTLEMENT];

    /** @var array<string, string> */
    public const DOC_LABELS = [
        self::TYPE_MEMBERSHIP => 'قرارداد عضویت',
        self::TYPE_SETTLEMENT => 'قرارداد استقرار',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function labelFor(string $docType): string
    {
        return self::DOC_LABELS[$docType] ?? $docType;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filesForTeamYear(int $teamId, string $fiscalYear): array
    {
        if (!Schema::tableExists($this->pdo, 'team_contract_files')) {
            return [];
        }
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $statement = $this->pdo->prepare(
            'SELECT * FROM team_contract_files
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year
             ORDER BY doc_type'
        );
        $statement->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
        $rows = $statement->fetchAll() ?: [];

        return array_map(fn (array $row): array => $this->presentFile($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function proposalForTeamYear(int $teamId, string $fiscalYear): ?array
    {
        if (!Schema::tableExists($this->pdo, 'team_contract_proposals')) {
            return null;
        }
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $statement = $this->pdo->prepare(
            'SELECT * FROM team_contract_proposals
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year
             ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        return $this->presentProposal($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function yearBundle(int $teamId, string $fiscalYear): array
    {
        Access::assertTeamAccess($teamId);
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $files = $this->filesForTeamYear($teamId, $fiscalYear);
        $byType = [];
        foreach (self::DOC_TYPES as $type) {
            $byType[$type] = null;
        }
        foreach ($files as $file) {
            $byType[(string) $file['doc_type']] = $file;
        }

        $proposal = $this->proposalForTeamYear($teamId, $fiscalYear);
        $official = (new TeamContracts($this->pdo))->contractForYear($teamId, $fiscalYear);
        $hasBothFiles = $byType[self::TYPE_MEMBERSHIP] !== null && $byType[self::TYPE_SETTLEMENT] !== null;
        // Team may submit a new package or a correction after rejection — not while pending review.
        $proposalStatus = (string) ($proposal['status'] ?? '');
        // Allow resubmit when rejected, or when an approved proposal was left without an official row.
        $canSubmit = $official === null
            && ($proposal === null || in_array($proposalStatus, ['rejected', 'approved'], true));

        return [
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
            'files' => $byType,
            'proposal' => $proposal,
            'official_contract' => $official ? [
                'id' => (int) ($official['id'] ?? 0),
                'contract_start' => (string) ($official['contract_start'] ?? ''),
                'contract_end' => (string) ($official['contract_end'] ?? ''),
                'formal_contract_amount' => (int) ($official['formal_contract_amount'] ?? 0),
                'notes' => (string) ($official['notes'] ?? ''),
            ] : null,
            'has_both_files' => $hasBothFiles,
            'can_submit' => $canSubmit,
            'is_registered' => $official !== null,
            'doc_labels' => self::DOC_LABELS,
        ];
    }

    public function hasPendingProposal(int $teamId, string $fiscalYear): bool
    {
        $proposal = $this->proposalForTeamYear($teamId, $fiscalYear);

        return $proposal !== null && (string) ($proposal['status'] ?? '') === 'pending';
    }

    /**
     * When admin registers an official contract directly, clear any pending proposal for that year.
     */
    public function syncPendingProposalWithOfficial(int $teamId, string $fiscalYear): void
    {
        if (!Schema::tableExists($this->pdo, 'team_contract_proposals')) {
            return;
        }
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        $proposal = $this->proposalForTeamYear($teamId, $fiscalYear);
        if (!$proposal || (string) ($proposal['status'] ?? '') !== 'pending') {
            return;
        }
        $now = date('c');
        $existingNotes = trim((string) ($proposal['notes'] ?? ''));
        $syncNote = 'هم‌تراز با ثبت مستقیم مرکز';
        $notes = $existingNotes === '' ? $syncNote : ($existingNotes . ' — ' . $syncNote);
        $sync = $this->pdo->prepare(
            "UPDATE team_contract_proposals
             SET status = 'approved',
                 rejection_reason = NULL,
                 notes = :notes,
                 reviewed_at = :reviewed_at,
                 updated_at = :updated_at
             WHERE id = :id AND status = 'pending'"
        );
        $sync->execute([
            'notes' => $notes,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => (int) $proposal['id'],
        ]);
        if ($sync->rowCount() < 1) {
            return;
        }

        if (Schema::tableExists($this->pdo, 'team_contract_files')) {
            $this->pdo->prepare(
                "UPDATE team_contract_files
                 SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
                 WHERE team_id = :team_id AND fiscal_year = :fiscal_year"
            )->execute([
                'reviewed_at' => $now,
                'updated_at' => $now,
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
            ]);
        }
    }

    /**
     * @return array{team_id:int, years:list<array<string, mixed>>, current_year:string, doc_labels:array<string, string>}
     */
    public function teamOverview(int $teamId): array
    {
        Access::assertTeamAccess($teamId);
        $this->assertTeamExists($teamId);
        $currentYear = (string) JalaliDate::todayParts()['year'];
        $years = [$currentYear => true];

        // Membership year through current year (e.g. joined 1403 → 1403,1404,1405).
        $joinedStmt = $this->pdo->prepare('SELECT joined_at FROM teams WHERE id = :id');
        $joinedStmt->execute(['id' => $teamId]);
        $joined = JalaliDate::normalizeDigits((string) ($joinedStmt->fetchColumn() ?: ''));
        if (preg_match('/^(\d{4})/', $joined, $match)) {
            $startYear = (int) $match[1];
            $endYear = (int) $currentYear;
            if ($startYear > 1300 && $startYear <= $endYear) {
                for ($year = $startYear; $year <= $endYear; $year++) {
                    $years[(string) $year] = true;
                }
            }
        }

        foreach (['team_contracts', 'team_contract_proposals', 'team_contract_files'] as $table) {
            if (!Schema::tableExists($this->pdo, $table)) {
                continue;
            }
            $statement = $this->pdo->prepare(
                "SELECT DISTINCT fiscal_year FROM {$table} WHERE team_id = :id AND fiscal_year IS NOT NULL AND fiscal_year <> ''"
            );
            $statement->execute(['id' => $teamId]);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $year = JalaliDate::normalizeDigits((string) ($row['fiscal_year'] ?? ''));
                if (preg_match('/^\d{4}$/', $year)) {
                    $years[$year] = true;
                }
            }
        }

        $sorted = array_keys($years);
        rsort($sorted, SORT_NUMERIC);
        $bundles = [];
        foreach ($sorted as $year) {
            $bundles[] = $this->yearBundle($teamId, (string) $year);
        }

        return [
            'team_id' => $teamId,
            'current_year' => $currentYear,
            'years' => $bundles,
            'doc_labels' => self::DOC_LABELS,
        ];
    }

    /**
     * Admin or team upload/replace one contract file for a year.
     *
     * @param array<string, mixed> $upload $_FILES entry
     * @return array<string, mixed>
     */
    public function upsertFile(int $teamId, string $fiscalYear, string $docType, array $upload, bool $asApproved = false): array
    {
        Access::assertTeamAccess($teamId);
        $fiscalYear = $this->normalizeYear($fiscalYear);
        $docType = $this->normalizeDocType($docType);
        $this->assertTeamExists($teamId);

        if (!Access::canWrite() && !Access::canTeamSubmit()) {
            throw new InvalidArgumentException('دسترسی کافی برای آپلود قرارداد ندارید.');
        }
        // Respect caller flag: admin profile upload may keep pending while a package is under review.
        $status = $asApproved ? 'approved' : 'pending';
        if (!$asApproved && Access::canWrite() && !$this->hasPendingProposal($teamId, $fiscalYear)) {
            $status = 'approved';
        }

        $existing = $this->fileRow($teamId, $fiscalYear, $docType);
        $oldPath = $existing ? (string) ($existing['stored_path'] ?? '') : '';
        $stored = FileStorage::storeUpload($upload, 'contracts');
        $now = date('c');
        $role = Access::role();
        $userId = Access::userId();
        $id = 0;

        try {
            if ($existing) {
                $statement = $this->pdo->prepare(
                    'UPDATE team_contract_files SET
                        original_name = :original_name,
                        stored_path = :stored_path,
                        mime = :mime,
                        size_bytes = :size_bytes,
                        status = :status,
                        rejection_reason = NULL,
                        uploaded_by_role = :uploaded_by_role,
                        uploaded_by_user_id = :uploaded_by_user_id,
                        submitted_at = :submitted_at,
                        reviewed_at = :reviewed_at,
                        updated_at = :updated_at
                     WHERE id = :id'
                );
                $statement->execute([
                    'original_name' => $stored['original_name'],
                    'stored_path' => $stored['relative_path'],
                    'mime' => $stored['mime'],
                    'size_bytes' => $stored['size_bytes'],
                    'status' => $status,
                    'uploaded_by_role' => $role,
                    'uploaded_by_user_id' => $userId > 0 ? $userId : null,
                    'submitted_at' => $now,
                    'reviewed_at' => $status === 'approved' ? $now : null,
                    'updated_at' => $now,
                    'id' => (int) $existing['id'],
                ]);
                $id = (int) $existing['id'];
            } else {
                $statement = $this->pdo->prepare(
                    'INSERT INTO team_contract_files
                        (team_id, fiscal_year, doc_type, original_name, stored_path, mime, size_bytes, status,
                         uploaded_by_role, uploaded_by_user_id, submitted_at, reviewed_at, created_at, updated_at)
                     VALUES
                        (:team_id, :fiscal_year, :doc_type, :original_name, :stored_path, :mime, :size_bytes, :status,
                         :uploaded_by_role, :uploaded_by_user_id, :submitted_at, :reviewed_at, :created_at, :updated_at)'
                );
                $statement->execute([
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                    'doc_type' => $docType,
                    'original_name' => $stored['original_name'],
                    'stored_path' => $stored['relative_path'],
                    'mime' => $stored['mime'],
                    'size_bytes' => $stored['size_bytes'],
                    'status' => $status,
                    'uploaded_by_role' => $role,
                    'uploaded_by_user_id' => $userId > 0 ? $userId : null,
                    'submitted_at' => $now,
                    'reviewed_at' => $status === 'approved' ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $this->pdo->lastInsertId();
            }
        } catch (Throwable $error) {
            FileStorage::deleteRelative((string) ($stored['relative_path'] ?? ''));
            throw $error;
        }

        if ($oldPath !== '' && $oldPath !== $stored['relative_path']) {
            FileStorage::deleteRelative($oldPath);
        }

        return $this->presentFile($this->fileById($id));
    }

    /**
     * Team/admin submits one contract package: metadata + both required attachments.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $membershipUpload
     * @param array<string, mixed> $settlementUpload
     * @return array<string, mixed>
     */
    public function submitPackage(int $teamId, array $payload, array $membershipUpload, array $settlementUpload): array
    {
        if (!Access::canTeamSubmit() && !Access::canWrite()) {
            throw new InvalidArgumentException('ارسال پیشنهاد قرارداد مجاز نیست.');
        }
        Access::assertTeamAccess($teamId);
        $this->assertTeamExists($teamId);

        $fiscalYear = $this->normalizeYear((string) ($payload['fiscal_year'] ?? ''));
        $official = (new TeamContracts($this->pdo))->contractForYear($teamId, $fiscalYear);
        if ($official !== null) {
            throw new InvalidArgumentException('قرارداد این سال قبلاً در سامانه ثبت شده است و ارسال مجدد مجاز نیست.');
        }

        $existingProposal = $this->proposalForTeamYear($teamId, $fiscalYear);
        if ($existingProposal) {
            $proposalStatus = (string) ($existingProposal['status'] ?? '');
            if ($proposalStatus === 'pending') {
                throw new InvalidArgumentException('پیشنهاد این سال در انتظار تأیید مرکز است و ارسال مجدد مجاز نیست.');
            }
        }

        $membershipProvided = is_array($membershipUpload)
            && (int) ($membershipUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $settlementProvided = is_array($settlementUpload)
            && (int) ($settlementUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $existingMembership = $this->fileRow($teamId, $fiscalYear, self::TYPE_MEMBERSHIP);
        $existingSettlement = $this->fileRow($teamId, $fiscalYear, self::TYPE_SETTLEMENT);
        if (!$membershipProvided && !$existingMembership) {
            throw new InvalidArgumentException('آپلود قرارداد عضویت الزامی است.');
        }
        if (!$settlementProvided && !$existingSettlement) {
            throw new InvalidArgumentException('آپلود قرارداد استقرار الزامی است.');
        }

        $contractStart = JalaliDate::normalize($payload['contract_start'] ?? '');
        $contractEnd = JalaliDate::normalize($payload['contract_end'] ?? '');
        if ($contractStart === '' || $contractEnd === '') {
            throw new InvalidArgumentException('تاریخ شروع و پایان قرارداد الزامی است.');
        }
        if ($contractEnd < $contractStart) {
            throw new InvalidArgumentException('تاریخ پایان نباید قبل از شروع باشد.');
        }
        $startYear = substr($contractStart, 0, 4);
        $endYear = substr($contractEnd, 0, 4);
        if ($startYear !== $fiscalYear || $endYear !== $fiscalYear) {
            throw new InvalidArgumentException("تاریخ شروع و پایان باید در سال مالی {$fiscalYear} باشد.");
        }
        $amount = (int) preg_replace('/\D+/', '', (string) ($payload['formal_contract_amount'] ?? '0'));
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ قرارداد باید بیشتر از صفر باشد.');
        }

        $chargeOverride = $this->nullableMoney($payload['charge_rate_override'] ?? null);
        $rentOverride = $this->nullableMoney($payload['informal_rent_rate_override'] ?? null);
        $notes = trim((string) ($payload['notes'] ?? ''));
        $now = date('c');

        // Stage both new files on disk first so a later failure cannot leave half-updated DB.
        $staged = [];
        $oldPaths = [];
        try {
            if ($membershipProvided) {
                $staged[self::TYPE_MEMBERSHIP] = FileStorage::storeUpload($membershipUpload, 'contracts');
                if ($existingMembership) {
                    $oldPaths[] = (string) ($existingMembership['stored_path'] ?? '');
                }
            }
            if ($settlementProvided) {
                $staged[self::TYPE_SETTLEMENT] = FileStorage::storeUpload($settlementUpload, 'contracts');
                if ($existingSettlement) {
                    $oldPaths[] = (string) ($existingSettlement['stored_path'] ?? '');
                }
            }

            $started = false;
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }
            try {
                // Re-check official contract inside the write txn (admin may have registered meanwhile).
                if ((new TeamContracts($this->pdo))->contractForYear($teamId, $fiscalYear) !== null) {
                    throw new InvalidArgumentException('قرارداد این سال قبلاً در سامانه ثبت شده است و ارسال مجدد مجاز نیست.');
                }
                foreach ($staged as $docType => $stored) {
                    $this->persistStoredFile($teamId, $fiscalYear, $docType, $stored, 'pending', $now);
                }

                $this->pdo->prepare(
                    "UPDATE team_contract_files
                     SET status = 'pending', rejection_reason = NULL, submitted_at = :submitted_at, reviewed_at = NULL, updated_at = :updated_at
                     WHERE team_id = :team_id AND fiscal_year = :fiscal_year"
                )->execute([
                    'submitted_at' => $now,
                    'updated_at' => $now,
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                ]);

                if ($existingProposal) {
                    $updateProposal = $this->pdo->prepare(
                        'UPDATE team_contract_proposals SET
                            contract_start = :contract_start,
                            contract_end = :contract_end,
                            formal_contract_amount = :formal_contract_amount,
                            charge_rate_override = :charge_rate_override,
                            informal_rent_rate_override = :informal_rent_rate_override,
                            notes = :notes,
                            status = :status,
                            rejection_reason = NULL,
                            submitted_at = :submitted_at,
                            reviewed_at = NULL,
                            updated_at = :updated_at
                         WHERE id = :id AND status = \'rejected\''
                    );
                    $updateProposal->execute([
                        'contract_start' => $contractStart,
                        'contract_end' => $contractEnd,
                        'formal_contract_amount' => $amount,
                        'charge_rate_override' => $chargeOverride,
                        'informal_rent_rate_override' => $rentOverride,
                        'notes' => $notes !== '' ? $notes : null,
                        'status' => 'pending',
                        'submitted_at' => $now,
                        'updated_at' => $now,
                        'id' => (int) $existingProposal['id'],
                    ]);
                    if ($updateProposal->rowCount() < 1) {
                        throw new InvalidArgumentException('وضعیت پیشنهاد قرارداد تغییر کرده و ارسال مجدد ممکن نیست.');
                    }
                    $id = (int) $existingProposal['id'];
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO team_contract_proposals
                            (team_id, fiscal_year, contract_start, contract_end, formal_contract_amount,
                             charge_rate_override, informal_rent_rate_override, notes, status,
                             submitted_at, created_at, updated_at)
                         VALUES
                            (:team_id, :fiscal_year, :contract_start, :contract_end, :formal_contract_amount,
                             :charge_rate_override, :informal_rent_rate_override, :notes, :status,
                             :submitted_at, :created_at, :updated_at)'
                    )->execute([
                        'team_id' => $teamId,
                        'fiscal_year' => $fiscalYear,
                        'contract_start' => $contractStart,
                        'contract_end' => $contractEnd,
                        'formal_contract_amount' => $amount,
                        'charge_rate_override' => $chargeOverride,
                        'informal_rent_rate_override' => $rentOverride,
                        'notes' => $notes !== '' ? $notes : null,
                        'status' => 'pending',
                        'submitted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $id = (int) $this->pdo->lastInsertId();
                }

                if ($started) {
                    $this->pdo->commit();
                }
            } catch (Throwable $error) {
                if ($started && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $error;
            }
        } catch (Throwable $error) {
            foreach ($staged as $stored) {
                FileStorage::deleteRelative((string) ($stored['relative_path'] ?? ''));
            }
            throw $error;
        }

        foreach ($oldPaths as $oldPath) {
            if ($oldPath !== '') {
                FileStorage::deleteRelative($oldPath);
            }
        }

        return $this->yearBundle($teamId, $fiscalYear) + ['proposal_id' => $id];
    }

    /**
     * @return array<string, mixed>
     */
    public function approveProposal(int $proposalId): array
    {
        Access::requireWriteJson();
        $row = $this->proposalById($proposalId);
        if (!$row) {
            throw new InvalidArgumentException('پیشنهاد قرارداد پیدا نشد.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('فقط پیشنهاد در انتظار قابل تأیید است.');
        }

        $teamId = (int) $row['team_id'];
        $fiscalYear = (string) $row['fiscal_year'];
        $bundle = $this->yearBundle($teamId, $fiscalYear);
        $membership = $bundle['files'][self::TYPE_MEMBERSHIP] ?? null;
        $settlement = $bundle['files'][self::TYPE_SETTLEMENT] ?? null;
        if (!$membership || !$settlement) {
            throw new InvalidArgumentException('تأیید قرارداد بدون هر دو پیوست (عضویت و استقرار) ممکن نیست.');
        }

        $contracts = new TeamContracts($this->pdo);
        if ($contracts->contractForYear($teamId, $fiscalYear)) {
            throw new InvalidArgumentException('برای این سال قبلاً قرارداد رسمی ثبت شده است. ابتدا همان قرارداد را ویرایش یا حذف کنید.');
        }

        $payload = [
            'team_id' => (string) $teamId,
            'fiscal_year' => $fiscalYear,
            'contract_start' => (string) $row['contract_start'],
            'contract_end' => (string) $row['contract_end'],
            'formal_contract_amount' => (string) $row['formal_contract_amount'],
            'charge_rate_override' => $row['charge_rate_override'] !== null ? (string) $row['charge_rate_override'] : '',
            'informal_rent_rate_override' => $row['informal_rent_rate_override'] !== null ? (string) $row['informal_rent_rate_override'] : '',
            'notes' => (string) ($row['notes'] ?? ''),
        ];

        $started = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }
        try {
            $now = date('c');
            // Mark proposal approved before creating the official row so create-time sync is a no-op.
            $proposalUpdate = $this->pdo->prepare(
                "UPDATE team_contract_proposals
                 SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
                 WHERE id = :id AND status = 'pending'"
            );
            $proposalUpdate->execute(['reviewed_at' => $now, 'updated_at' => $now, 'id' => $proposalId]);
            if ($proposalUpdate->rowCount() < 1) {
                throw new InvalidArgumentException('فقط پیشنهاد در انتظار قابل تأیید است.');
            }

            $this->pdo->prepare(
                "UPDATE team_contract_files
                 SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
                 WHERE team_id = :team_id AND fiscal_year = :fiscal_year"
            )->execute([
                'reviewed_at' => $now,
                'updated_at' => $now,
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
            ]);

            (new Crud($this->pdo))->create('team_contracts', $payload);

            if ($started) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        return $this->yearBundle($teamId, $fiscalYear);
    }

    /**
     * @return array<string, mixed>
     */
    public function rejectProposal(int $proposalId, string $reason): array
    {
        Access::requireWriteJson();
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('دلیل رد الزامی است.');
        }
        $row = $this->proposalById($proposalId);
        if (!$row) {
            throw new InvalidArgumentException('پیشنهاد قرارداد پیدا نشد.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('فقط پیشنهاد در انتظار قابل رد است.');
        }

        $now = date('c');
        $teamId = (int) $row['team_id'];
        $fiscalYear = (string) $row['fiscal_year'];
        $started = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }
        try {
            $statement = $this->pdo->prepare(
                "UPDATE team_contract_proposals
                 SET status = 'rejected', rejection_reason = :reason, reviewed_at = :reviewed_at, updated_at = :updated_at
                 WHERE id = :id AND status = 'pending'"
            );
            $statement->execute([
                'reason' => $reason,
                'reviewed_at' => $now,
                'updated_at' => $now,
                'id' => $proposalId,
            ]);
            if ($statement->rowCount() < 1) {
                throw new InvalidArgumentException('فقط پیشنهاد در انتظار قابل رد است.');
            }

            $this->pdo->prepare(
                "UPDATE team_contract_files
                 SET status = 'rejected', rejection_reason = :reason, reviewed_at = :reviewed_at, updated_at = :updated_at
                 WHERE team_id = :team_id AND fiscal_year = :fiscal_year"
            )->execute([
                'reason' => $reason,
                'reviewed_at' => $now,
                'updated_at' => $now,
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
            ]);
            if ($started) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        return $this->yearBundle($teamId, $fiscalYear);
    }

    public function deleteFile(int $fileId): void
    {
        Access::requireWriteJson();
        $row = $this->fileById($fileId);
        if (!$row) {
            throw new InvalidArgumentException('فایل قرارداد پیدا نشد.');
        }
        $teamId = (int) ($row['team_id'] ?? 0);
        $fiscalYear = (string) ($row['fiscal_year'] ?? '');
        if ($this->hasPendingProposal($teamId, $fiscalYear)) {
            throw new InvalidArgumentException('تا وقتی پیشنهاد این سال در صف تأیید است، حذف پیوست مجاز نیست. ابتدا پیشنهاد را رد کنید.');
        }
        $this->pdo->prepare('DELETE FROM team_contract_files WHERE id = :id')->execute(['id' => $fileId]);
        FileStorage::deleteRelative((string) ($row['stored_path'] ?? ''));
    }

    public function downloadFile(int $fileId): never
    {
        $row = $this->fileById($fileId);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'فایل پیدا نشد.';
            exit;
        }
        Access::assertTeamAccess((int) $row['team_id']);
        // Teams may download their own files in any status; admins always can.
        FileStorage::sendDownload(
            (string) $row['stored_path'],
            (string) $row['original_name'],
            (string) $row['mime']
        );
    }

    /**
     * Pending contract packages (metadata + both attachments) for admin review.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingProposals(): array
    {
        return $this->proposalsByStatus('pending');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rejectedProposals(): array
    {
        return $this->proposalsByStatus('rejected');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function proposalsByStatus(string $status): array
    {
        if (!Schema::tableExists($this->pdo, 'team_contract_proposals')) {
            return [];
        }
        $status = trim($status);
        if (!in_array($status, ['pending', 'rejected', 'approved'], true)) {
            throw new InvalidArgumentException('وضعیت پیشنهاد نامعتبر است.');
        }
        $statement = $this->pdo->prepare(
            "SELECT p.*, t.name AS team_name, t.entity_code
             FROM team_contract_proposals p
             INNER JOIN teams t ON t.id = p.team_id
             WHERE p.status = :status
             ORDER BY COALESCE(p.reviewed_at, p.submitted_at) DESC, p.id DESC"
        );
        $statement->execute(['status' => $status]);

        $rows = [];
        foreach ($statement->fetchAll() ?: [] as $row) {
            $presented = $this->presentProposal($row);
            $bundle = $this->yearBundle((int) $presented['team_id'], (string) $presented['fiscal_year']);
            $presented['files'] = $bundle['files'];
            $presented['has_both_files'] = (bool) ($bundle['has_both_files'] ?? false);
            $presented['has_official'] = (bool) ($bundle['is_registered'] ?? false);
            $presented['can_approve'] = $status === 'pending'
                && (bool) ($bundle['has_both_files'] ?? false)
                && !($bundle['is_registered'] ?? false);
            $rows[] = $presented;
        }

        return $rows;
    }

    /**
     * Delete contract files + proposals for a team/year (used when official contract is deleted).
     */
    public function deleteForTeamYear(int $teamId, string $fiscalYear): void
    {
        $fiscalYear = JalaliDate::normalizeDigits($fiscalYear);
        if ($teamId <= 0 || !preg_match('/^\d{4}$/', $fiscalYear)) {
            return;
        }

        $paths = [];
        if (Schema::tableExists($this->pdo, 'team_contract_files')) {
            $statement = $this->pdo->prepare(
                'SELECT stored_path FROM team_contract_files
                 WHERE team_id = :team_id AND fiscal_year = :fiscal_year'
            );
            $statement->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $paths[] = (string) ($row['stored_path'] ?? '');
            }
            $this->pdo->prepare(
                'DELETE FROM team_contract_files WHERE team_id = :team_id AND fiscal_year = :fiscal_year'
            )->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
        }

        if (Schema::tableExists($this->pdo, 'team_contract_proposals')) {
            $this->pdo->prepare(
                'DELETE FROM team_contract_proposals WHERE team_id = :team_id AND fiscal_year = :fiscal_year'
            )->execute(['team_id' => $teamId, 'fiscal_year' => $fiscalYear]);
        }

        // Unlink after DB deletes so a crash mid-way cannot leave approved proposals without an official contract.
        foreach ($paths as $path) {
            if ($path !== '') {
                FileStorage::deleteRelative($path);
            }
        }
    }

    /**
     * @param array{original_name:string, relative_path:string, mime:string, size_bytes:int} $stored
     */
    private function persistStoredFile(
        int $teamId,
        string $fiscalYear,
        string $docType,
        array $stored,
        string $status,
        string $now
    ): void {
        $existing = $this->fileRow($teamId, $fiscalYear, $docType);
        $role = Access::role();
        $userId = Access::userId();
        if ($existing) {
            $this->pdo->prepare(
                'UPDATE team_contract_files SET
                    original_name = :original_name,
                    stored_path = :stored_path,
                    mime = :mime,
                    size_bytes = :size_bytes,
                    status = :status,
                    rejection_reason = NULL,
                    uploaded_by_role = :uploaded_by_role,
                    uploaded_by_user_id = :uploaded_by_user_id,
                    submitted_at = :submitted_at,
                    reviewed_at = :reviewed_at,
                    updated_at = :updated_at
                 WHERE id = :id'
            )->execute([
                'original_name' => $stored['original_name'],
                'stored_path' => $stored['relative_path'],
                'mime' => $stored['mime'],
                'size_bytes' => $stored['size_bytes'],
                'status' => $status,
                'uploaded_by_role' => $role,
                'uploaded_by_user_id' => $userId > 0 ? $userId : null,
                'submitted_at' => $now,
                'reviewed_at' => $status === 'approved' ? $now : null,
                'updated_at' => $now,
                'id' => (int) $existing['id'],
            ]);

            return;
        }

        $this->pdo->prepare(
            'INSERT INTO team_contract_files
                (team_id, fiscal_year, doc_type, original_name, stored_path, mime, size_bytes, status,
                 uploaded_by_role, uploaded_by_user_id, submitted_at, reviewed_at, created_at, updated_at)
             VALUES
                (:team_id, :fiscal_year, :doc_type, :original_name, :stored_path, :mime, :size_bytes, :status,
                 :uploaded_by_role, :uploaded_by_user_id, :submitted_at, :reviewed_at, :created_at, :updated_at)'
        )->execute([
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
            'doc_type' => $docType,
            'original_name' => $stored['original_name'],
            'stored_path' => $stored['relative_path'],
            'mime' => $stored['mime'],
            'size_bytes' => $stored['size_bytes'],
            'status' => $status,
            'uploaded_by_role' => $role,
            'uploaded_by_user_id' => $userId > 0 ? $userId : null,
            'submitted_at' => $now,
            'reviewed_at' => $status === 'approved' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Delete all contract documents for a team (cascade).
     */
    /**
     * @return list<string> stored paths (unlinked when $unlinkFiles is true)
     */
    public function deleteForTeam(int $teamId, bool $unlinkFiles = true): array
    {
        if ($teamId <= 0) {
            return [];
        }
        $paths = [];
        if (Schema::tableExists($this->pdo, 'team_contract_files')) {
            $statement = $this->pdo->prepare(
                'SELECT stored_path FROM team_contract_files WHERE team_id = :team_id'
            );
            $statement->execute(['team_id' => $teamId]);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $path = (string) ($row['stored_path'] ?? '');
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
            $this->pdo->prepare('DELETE FROM team_contract_files WHERE team_id = :team_id')
                ->execute(['team_id' => $teamId]);
        }
        if (Schema::tableExists($this->pdo, 'team_contract_proposals')) {
            $this->pdo->prepare('DELETE FROM team_contract_proposals WHERE team_id = :team_id')
                ->execute(['team_id' => $teamId]);
        }
        if ($unlinkFiles) {
            foreach ($paths as $path) {
                FileStorage::deleteRelative($path);
            }
        }

        return $paths;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingFiles(): array
    {
        // Individual file approval removed — packages are reviewed as a whole.
        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fileRow(int $teamId, string $fiscalYear, string $docType): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM team_contract_files
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND doc_type = :doc_type
             LIMIT 1'
        );
        $statement->execute([
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
            'doc_type' => $docType,
        ]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fileById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM team_contract_files WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function proposalById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM team_contract_proposals WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentFile(array $row): array
    {
        $docType = (string) ($row['doc_type'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'team_id' => (int) ($row['team_id'] ?? 0),
            'team_name' => (string) ($row['team_name'] ?? ''),
            'entity_code' => (string) ($row['entity_code'] ?? ''),
            'fiscal_year' => (string) ($row['fiscal_year'] ?? ''),
            'doc_type' => $docType,
            'doc_label' => self::labelFor($docType),
            'original_name' => (string) ($row['original_name'] ?? ''),
            'mime' => (string) ($row['mime'] ?? ''),
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            'status' => (string) ($row['status'] ?? ''),
            'rejection_reason' => (string) ($row['rejection_reason'] ?? ''),
            'uploaded_by_role' => (string) ($row['uploaded_by_role'] ?? ''),
            'submitted_at' => (string) ($row['submitted_at'] ?? ''),
            'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
            'download_url' => 'download.php?resource=contract-file&id=' . (int) ($row['id'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentProposal(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'team_id' => (int) ($row['team_id'] ?? 0),
            'team_name' => (string) ($row['team_name'] ?? ''),
            'entity_code' => (string) ($row['entity_code'] ?? ''),
            'fiscal_year' => (string) ($row['fiscal_year'] ?? ''),
            'contract_start' => (string) ($row['contract_start'] ?? ''),
            'contract_end' => (string) ($row['contract_end'] ?? ''),
            'formal_contract_amount' => (int) ($row['formal_contract_amount'] ?? 0),
            'charge_rate_override' => $row['charge_rate_override'] !== null ? (int) $row['charge_rate_override'] : null,
            'informal_rent_rate_override' => $row['informal_rent_rate_override'] !== null ? (int) $row['informal_rent_rate_override'] : null,
            'notes' => (string) ($row['notes'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'rejection_reason' => (string) ($row['rejection_reason'] ?? ''),
            'submitted_at' => (string) ($row['submitted_at'] ?? ''),
            'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
        ];
    }

    private function normalizeYear(string $year): string
    {
        $year = JalaliDate::normalizeDigits(trim($year));
        if (!preg_match('/^\d{4}$/', $year)) {
            throw new InvalidArgumentException('سال مالی نامعتبر است.');
        }

        return $year;
    }

    private function normalizeDocType(string $docType): string
    {
        $docType = trim($docType);
        if (!in_array($docType, self::DOC_TYPES, true)) {
            throw new InvalidArgumentException('نوع قرارداد باید عضویت یا استقرار باشد.');
        }

        return $docType;
    }

    private function assertTeamExists(int $teamId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM teams WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $teamId]);
        if (!$statement->fetch()) {
            throw new InvalidArgumentException('نهاد پیدا نشد.');
        }
    }

    private function nullableMoney(mixed $value): ?int
    {
        $text = trim(JalaliDate::normalizeDigits((string) ($value ?? '')));
        if ($text === '') {
            return null;
        }
        if (!preg_match('/^\d+$/', $text)) {
            throw new InvalidArgumentException('مبلغ نرخ اختصاصی معتبر نیست.');
        }

        return (int) $text;
    }
}
