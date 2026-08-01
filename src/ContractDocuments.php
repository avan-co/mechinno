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

        return [
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
            'files' => $byType,
            'proposal' => $this->proposalForTeamYear($teamId, $fiscalYear),
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

        $stored = FileStorage::storeUpload($upload, 'contracts');
        $now = date('c');
        $role = Access::role();
        $userId = Access::userId();
        $status = ($asApproved || Access::canWrite()) ? 'approved' : 'pending';
        if (!Access::canWrite() && !Access::canTeamSubmit()) {
            throw new InvalidArgumentException('دسترسی کافی برای آپلود قرارداد ندارید.');
        }

        $existing = $this->fileRow($teamId, $fiscalYear, $docType);
        $oldPath = $existing ? (string) ($existing['stored_path'] ?? '') : '';

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

        if ($oldPath !== '' && $oldPath !== $stored['relative_path']) {
            FileStorage::deleteRelative($oldPath);
        }

        return $this->presentFile($this->fileById($id));
    }

    /**
     * Team submits contract metadata + optional already-uploaded pending files.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitProposal(int $teamId, array $payload): array
    {
        if (!Access::canTeamSubmit() && !Access::canWrite()) {
            throw new InvalidArgumentException('ارسال پیشنهاد قرارداد مجاز نیست.');
        }
        Access::assertTeamAccess($teamId);
        $this->assertTeamExists($teamId);

        $fiscalYear = $this->normalizeYear((string) ($payload['fiscal_year'] ?? ''));
        $contractStart = JalaliDate::normalize($payload['contract_start'] ?? '');
        $contractEnd = JalaliDate::normalize($payload['contract_end'] ?? '');
        if ($contractStart === '' || $contractEnd === '') {
            throw new InvalidArgumentException('تاریخ شروع و پایان قرارداد الزامی است.');
        }
        if ($contractEnd < $contractStart) {
            throw new InvalidArgumentException('تاریخ پایان نباید قبل از شروع باشد.');
        }
        $amount = (int) preg_replace('/\D+/', '', (string) ($payload['formal_contract_amount'] ?? '0'));
        if ($amount < 0) {
            throw new InvalidArgumentException('مبلغ قرارداد نامعتبر است.');
        }

        $chargeOverride = $this->nullableMoney($payload['charge_rate_override'] ?? null);
        $rentOverride = $this->nullableMoney($payload['informal_rent_rate_override'] ?? null);
        $notes = trim((string) ($payload['notes'] ?? ''));
        $now = date('c');

        $existing = $this->proposalForTeamYear($teamId, $fiscalYear);
        if ($existing && (string) ($existing['status'] ?? '') === 'pending' && !Access::canWrite()) {
            // replace pending proposal
        }

        if ($existing) {
            $statement = $this->pdo->prepare(
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
                 WHERE id = :id'
            );
            $statement->execute([
                'contract_start' => $contractStart,
                'contract_end' => $contractEnd,
                'formal_contract_amount' => $amount,
                'charge_rate_override' => $chargeOverride,
                'informal_rent_rate_override' => $rentOverride,
                'notes' => $notes !== '' ? $notes : null,
                'status' => 'pending',
                'submitted_at' => $now,
                'updated_at' => $now,
                'id' => (int) $existing['id'],
            ]);
            $id = (int) $existing['id'];
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO team_contract_proposals
                    (team_id, fiscal_year, contract_start, contract_end, formal_contract_amount,
                     charge_rate_override, informal_rent_rate_override, notes, status,
                     submitted_at, created_at, updated_at)
                 VALUES
                    (:team_id, :fiscal_year, :contract_start, :contract_end, :formal_contract_amount,
                     :charge_rate_override, :informal_rent_rate_override, :notes, :status,
                     :submitted_at, :created_at, :updated_at)'
            );
            $statement->execute([
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

        // Re-queue only non-approved files when team resubmits metadata; approved files stay.
        if (Schema::tableExists($this->pdo, 'team_contract_files')) {
            $this->pdo->prepare(
                "UPDATE team_contract_files
                 SET status = 'pending', rejection_reason = NULL, submitted_at = :submitted_at, reviewed_at = NULL, updated_at = :updated_at
                 WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND status IN ('pending', 'rejected')"
            )->execute([
                'submitted_at' => $now,
                'updated_at' => $now,
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
            ]);
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

        $contracts = new TeamContracts($this->pdo);
        $existingContract = $contracts->contractForYear($teamId, $fiscalYear);
        $crud = new Crud($this->pdo);
        if ($existingContract) {
            $crud->update('team_contracts', (int) $existingContract['id'], $payload);
        } else {
            $crud->create('team_contracts', $payload);
        }

        $now = date('c');
        $this->pdo->prepare(
            "UPDATE team_contract_proposals
             SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id"
        )->execute(['reviewed_at' => $now, 'updated_at' => $now, 'id' => $proposalId]);

        $this->pdo->prepare(
            "UPDATE team_contract_files
             SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND status = 'pending'"
        )->execute([
            'reviewed_at' => $now,
            'updated_at' => $now,
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
        ]);

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
        $this->pdo->prepare(
            "UPDATE team_contract_proposals
             SET status = 'rejected', rejection_reason = :reason, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id"
        )->execute([
            'reason' => $reason,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $proposalId,
        ]);

        $this->pdo->prepare(
            "UPDATE team_contract_files
             SET status = 'rejected', rejection_reason = :reason, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE team_id = :team_id AND fiscal_year = :fiscal_year AND status = 'pending'"
        )->execute([
            'reason' => $reason,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'team_id' => $teamId,
            'fiscal_year' => $fiscalYear,
        ]);

        return $this->yearBundle($teamId, $fiscalYear);
    }

    /**
     * @return array<string, mixed>
     */
    public function approveFile(int $fileId): array
    {
        Access::requireWriteJson();
        $row = $this->fileById($fileId);
        if (!$row) {
            throw new InvalidArgumentException('فایل قرارداد پیدا نشد.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('فقط فایل در انتظار قابل تأیید است.');
        }
        $now = date('c');
        $this->pdo->prepare(
            "UPDATE team_contract_files
             SET status = 'approved', rejection_reason = NULL, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id"
        )->execute(['reviewed_at' => $now, 'updated_at' => $now, 'id' => $fileId]);

        return $this->presentFile($this->fileById($fileId));
    }

    /**
     * @return array<string, mixed>
     */
    public function rejectFile(int $fileId, string $reason): array
    {
        Access::requireWriteJson();
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('دلیل رد الزامی است.');
        }
        $row = $this->fileById($fileId);
        if (!$row) {
            throw new InvalidArgumentException('فایل قرارداد پیدا نشد.');
        }
        if ((string) ($row['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('فقط فایل در انتظار قابل رد است.');
        }
        $now = date('c');
        $this->pdo->prepare(
            "UPDATE team_contract_files
             SET status = 'rejected', rejection_reason = :reason, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id"
        )->execute([
            'reason' => $reason,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $fileId,
        ]);

        return $this->presentFile($this->fileById($fileId));
    }

    public function deleteFile(int $fileId): void
    {
        Access::requireWriteJson();
        $row = $this->fileById($fileId);
        if (!$row) {
            throw new InvalidArgumentException('فایل قرارداد پیدا نشد.');
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
     * @return list<array<string, mixed>>
     */
    public function pendingProposals(): array
    {
        if (!Schema::tableExists($this->pdo, 'team_contract_proposals')) {
            return [];
        }
        $statement = $this->pdo->query(
            "SELECT p.*, t.name AS team_name, t.entity_code
             FROM team_contract_proposals p
             INNER JOIN teams t ON t.id = p.team_id
             WHERE p.status = 'pending'
             ORDER BY p.submitted_at DESC, p.id DESC"
        );

        return array_map(fn (array $row): array => $this->presentProposal($row), $statement->fetchAll() ?: []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingFiles(): array
    {
        if (!Schema::tableExists($this->pdo, 'team_contract_files')) {
            return [];
        }
        $statement = $this->pdo->query(
            "SELECT f.*, t.name AS team_name, t.entity_code
             FROM team_contract_files f
             INNER JOIN teams t ON t.id = f.team_id
             WHERE f.status = 'pending'
             ORDER BY f.submitted_at DESC, f.id DESC"
        );

        return array_map(fn (array $row): array => $this->presentFile($row), $statement->fetchAll() ?: []);
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
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return (int) preg_replace('/\D+/', '', $text);
    }
}
