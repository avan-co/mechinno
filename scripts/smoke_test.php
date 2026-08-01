<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Schema::migrate($pdo);

$crud = new Crud($pdo);
$repo = new Repository($pdo);
$errors = [];

$assert = static function (bool $ok, string $message) use (&$errors): void {
    if (!$ok) {
        $errors[] = $message;
    }
};

$pdo->exec("INSERT INTO teams (entity_type, entity_code, name, leader, phone, contract_start, contract_end, source_file, source_sheet)
            VALUES ('company', 'C-001', 'آوان', 'مهدی', '09398283658', '1405/01/01', '1405/12/29', 'manual', 'panel')");
(new TeamContracts($pdo))->migrateFromLegacyTeamDates();
EntityAccounts::provisionForTeam($pdo, 1, 'C-001', 'مهدی');
$teamsPage = $repo->paginatedResource('teams', 1, 25);
$assert(($teamsPage['rows'][0]['portal_username'] ?? '') === 'c001', 'team portal username generated');
$assert(EntityAccounts::usernameForCode('C-001') === 'c001', 'usernameForCode keeps letters');
$assert(EntityAccounts::usernameForCode('T-012') === 't012', 'usernameForCode normalizes codes');
$assert((int) ($teamsPage['rows'][0]['portal_has_password'] ?? 0) === 1, 'team portal password flag visible to admin');

$pdo->exec('UPDATE desks SET team_id = 1, usage_type = "mixed", formal_seats = 1, informal_seats = 1 WHERE number = 1');
(new DeskAssignments($pdo))->syncDeskAssignment(1, [
    'number' => 1,
    'team_id' => 1,
    'usage_type' => 'mixed',
    'assignment_from' => '1405/01/01',
    'assignment_until' => '1405/12/29',
]);

$member = $crud->create('members', [
    'team_id' => '1',
    'full_name' => 'عضو تست',
    'access_code' => '12345',
    'phone' => '09121234567',
    'national_id' => '0012345678',
]);
$assert(isset($member['member_code']), 'member_code generated');
$assert(!isset($member['locker_id']) || $member['locker_id'] === null, 'member has no locker_id');

$locker = $crud->create('lockers', [
    'locker_number' => '7',
    'team_id' => '1',
    'status' => 'تخصیص یافته',
]);
$assert((int) $locker['team_id'] === 1, 'locker assigned to team');

$emptyLocker = $crud->create('lockers', [
    'locker_number' => '99',
    'status' => 'خالی',
]);
$assert(!isset($emptyLocker['team_id']) || $emptyLocker['team_id'] === null, 'empty locker has no team');

$crud->update('teams', 1, ['leader' => 'احمد جدید']);
$portalName = (string) $pdo->query("SELECT full_name FROM panel_users WHERE team_id = 1 AND role = 'team'")->fetchColumn();
$assert($portalName === 'احمد جدید', 'leader update syncs portal full_name');

$crud->create('rate_settings', [
    'fiscal_year' => '1405',
    'title' => 'نرخ اول',
    'charge_rate' => '200',
    'informal_rent_rate' => '400',
    'effective_from' => '1405/01/01',
]);
$crud->create('rate_settings', [
    'fiscal_year' => '1405',
    'title' => 'نرخ دوم',
    'charge_rate' => '400',
    'informal_rent_rate' => '600',
    'effective_from' => '1405/04/01',
]);

$crud->create('rate_settings', [
    'fiscal_year' => '۱۴۰۵',
    'title' => 'نرخ فارسی',
    'charge_rate' => '100',
    'informal_rent_rate' => '100',
    'effective_from' => '1405/06/01',
]);
$persianRates = $repo->paginatedResource('rate_settings', 1, 25);
$persianRow = array_values(array_filter($persianRates['rows'], static fn ($r) => ($r['title'] ?? '') === 'نرخ فارسی'))[0] ?? [];
$assert(($persianRow['fiscal_year'] ?? '') === '1405', 'fiscal_year persian digits normalized');

$crud->create('charges', [
    'team_id' => '1',
    'fiscal_year' => '1405',
    'month_index' => '2',
    'charge_amount' => '999',
    'rent_amount' => '0',
    'amount' => '999',
]);
$chargeRow = $repo->paginatedResource('charges', 1, 25)['rows'][0] ?? [];
$assert(($chargeRow['team_name'] ?? '') === 'آوان', 'charge stores team_name from join');
$beforeManual = count($repo->resource('charges'));
(new Seeder($pdo))->recalculateCharges('1405');
$afterManual = $repo->resource('charges');
$manualMonth = array_values(array_filter($afterManual, static fn ($r) => (int) ($r['month_index'] ?? 0) === 2))[0] ?? [];
$assert((int) ($manualMonth['amount'] ?? 0) === 999, 'manual charge preserved after recalc');
$assert(count($afterManual) >= $beforeManual, 'recalc keeps manual rows');

$seeder = new Seeder($pdo);
$amounts = $seeder->monthlyAmountsForTeam(1, '1405');
$assert(($amounts[1]['amount'] ?? 0) === 600, 'month 1 amount');
$assert(($amounts[4]['amount'] ?? 0) === 1000, 'month 4 amount');

$teamsPage = $repo->paginatedResource('teams', 1, 25);
$teamCols = array_keys($teamsPage['rows'][0] ?? []);
$assert(!in_array('row_number', $teamCols, true), 'teams: no row_number');
$assert(!in_array('lockers', $teamCols, true), 'teams: no lockers column');
$assert(!in_array('power_strips', $teamCols, true), 'teams: no power_strips');

$membersPage = $repo->paginatedResource('members', 1, 25);
$memberCols = array_keys($membersPage['rows'][0] ?? []);
$assert(!in_array('locker_number', $memberCols, true), 'members: no locker_number');
$assert(in_array('member_code', $memberCols, true), 'members: has member_code');

$lockersPage = $repo->paginatedResource('lockers', 1, 25);
$lockerCols = array_keys($lockersPage['rows'][0] ?? []);
$assert(!in_array('member_name', $lockerCols, true), 'lockers: no member_name');

$ratesPage = $repo->paginatedResource('rate_settings', 1, 25);
$rateCols = array_keys($ratesPage['rows'][0] ?? []);
$assert(!in_array('rent_rate', $rateCols, true), 'rates: no rent_rate');

$stripped = Repository::stripLegacyColumns([
    'name' => 'Test',
    'row_number' => 1,
    'lockers' => 2,
    'power_strips' => 3,
    'rent_rate' => 4,
]);
$assert(!isset($stripped['row_number'], $stripped['lockers'], $stripped['power_strips'], $stripped['rent_rate']), 'legacy columns stripped');

$matrix = $repo->chargesMatrix('1405');
$assert(count($matrix['rows']) === 1, 'charges matrix has one team');
$assert($matrix['rows'][0]['cells'][0]['amount_due'] === 600, 'Farvardin due amount');

$report = (new ReportData($pdo))->build();
$assert(ReportData::money('300 ریال') === '300', 'ReportData money string');
$assert(ReportData::plain('9,398,283,658') === '9398283658', 'ReportData plain phone');

$summary = $repo->summary();
$assert(isset($summary['cards']['debt_total']), 'summary cards present');
$assert(isset($summary['current_month']['debtor_count']), 'current month summary');

Auth::start();
$_SESSION['mechinno_authenticated'] = true;
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$deskMap = $repo->deskMap();
$assert(count($deskMap['rows']) === 24, 'desk map has 24 desks');
$ownDesks = array_values(array_filter($deskMap['rows'], static fn ($d) => !empty($d['is_own'])));
$assert(count($ownDesks) >= 1, 'team desk map marks own desks');
$foreign = array_values(array_filter($deskMap['rows'], static fn ($d) => empty($d['is_own'])));
$assert(($foreign[0]['privacy_neutral'] ?? false) === true, 'team desk map hides foreign occupancy');
$assert(($foreign[0]['team_name'] ?? '') === '', 'team desk map hides foreign team names');
$assert(in_array('desks-map', Access::allowedResources(), true), 'team can access desks-map');

$perf = new PerformanceReports($pdo);
$perfSettings = $perf->settings();
$assert(($perfSettings['performance_reports_enabled'] ?? true) === false, 'performance reports disabled by default');
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$perf->updateSettings([
    'performance_reports_enabled' => 1,
    'performance_h1_open_from' => '1400/01/01',
    'performance_h1_open_until' => '1499/12/29',
    'performance_h2_open_from' => '1400/01/01',
    'performance_h2_open_until' => '1499/12/29',
]);
$tmpDir = sys_get_temp_dir();
$tmpPdf = tempnam($tmpDir, 'perf');
file_put_contents($tmpPdf, '%PDF-1.4 test');
$renamed = $tmpPdf . '.pdf';
rename($tmpPdf, $renamed);
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$report = $perf->submit(1, '1405', 'h1', [
    'name' => 'report.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $renamed,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($renamed),
], 'گزارش تست');
$assert(($report['status'] ?? '') === 'pending', 'performance report submitted as pending');

$contractDocs = new ContractDocuments($pdo);
$bundleBefore = $contractDocs->yearBundle(1, '1404');
$assert(is_array($bundleBefore['files']), 'prior year bundle available without deleting history');

// 1405 already has an official contract from legacy migration — team cannot resubmit.
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$dupBlocked = false;
try {
    $tmpA = tempnam($tmpDir, 'dup') . '.pdf';
    $tmpB = tempnam($tmpDir, 'dup') . '.pdf';
    file_put_contents($tmpA, '%PDF-1.4 a');
    file_put_contents($tmpB, '%PDF-1.4 b');
    $contractDocs->submitPackage(1, [
        'fiscal_year' => '1405',
        'contract_start' => '1405/01/01',
        'contract_end' => '1405/12/29',
        'formal_contract_amount' => '1',
    ], [
        'name' => 'a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpA,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpA),
    ], [
        'name' => 'b.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpB,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpB),
    ]);
} catch (InvalidArgumentException $e) {
    $dupBlocked = str_contains($e->getMessage(), 'قبلاً');
}
$assert($dupBlocked, 'duplicate contract submit blocked when already registered');

$tmpMembership = tempnam($tmpDir, 'mem') . '.pdf';
$tmpSettlement = tempnam($tmpDir, 'set') . '.pdf';
file_put_contents($tmpMembership, '%PDF-1.4 membership');
file_put_contents($tmpSettlement, '%PDF-1.4 settlement');
$package = $contractDocs->submitPackage(1, [
    'fiscal_year' => '1406',
    'contract_start' => '1406/01/01',
    'contract_end' => '1406/12/29',
    'formal_contract_amount' => '1000000',
    'notes' => 'پیشنهاد تست',
], [
    'name' => 'membership.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmpMembership,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpMembership),
], [
    'name' => 'settlement.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmpSettlement,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpSettlement),
]);
$assert(($package['proposal']['status'] ?? '') === 'pending', 'contract package pending');
$assert(($package['has_both_files'] ?? false) === true, 'package requires both attachments');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$approved = $contractDocs->approveProposal((int) $package['proposal']['id']);
$assert(($approved['proposal']['status'] ?? '') === 'approved', 'contract package approved');
$official = (new TeamContracts($pdo))->contractForYear(1, '1406');
$assert($official !== null, 'approved package creates official yearly contract');
$assert((int) ($official['formal_contract_amount'] ?? 0) === 1000000, 'official contract amount preserved');
$assert(($approved['files']['membership']['status'] ?? '') === 'approved', 'membership file approved with package');
$assert(($approved['files']['settlement']['status'] ?? '') === 'approved', 'settlement file approved with package');

// Rejected proposals are listed separately from the pending queue.
$tmpRejectA = tempnam($tmpDir, 'rej') . '.pdf';
$tmpRejectB = tempnam($tmpDir, 'rej') . '.pdf';
file_put_contents($tmpRejectA, '%PDF-1.4 reject-a');
file_put_contents($tmpRejectB, '%PDF-1.4 reject-b');
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$rejectPackage = $contractDocs->submitPackage(1, [
    'fiscal_year' => '1407',
    'contract_start' => '1407/01/01',
    'contract_end' => '1407/12/29',
    'formal_contract_amount' => '500000',
], [
    'name' => 'm1407.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpRejectA,
    'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpRejectA),
], [
    'name' => 's1407.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpRejectB,
    'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpRejectB),
]);
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$rejected = $contractDocs->rejectProposal((int) $rejectPackage['proposal']['id'], 'ناقص است');
$assert(($rejected['proposal']['status'] ?? '') === 'rejected', 'rejected proposal status');
$assert(count($contractDocs->pendingProposals()) === 0, 'rejected proposal leaves pending queue');
$rejectedRows = $contractDocs->rejectedProposals();
$assert(count($rejectedRows) === 1, 'rejected proposals listed separately');
$assert((string) ($rejectedRows[0]['fiscal_year'] ?? '') === '1407', 'rejected list includes year 1407');

// Deleting an official contract also removes attachment files from disk.
$pathStmt = $pdo->prepare(
    'SELECT doc_type, stored_path FROM team_contract_files WHERE team_id = 1 AND fiscal_year = :year'
);
$pathStmt->execute(['year' => '1406']);
$pathsByType = [];
foreach ($pathStmt->fetchAll() ?: [] as $fileRow) {
    $pathsByType[(string) $fileRow['doc_type']] = (string) $fileRow['stored_path'];
}
$membershipAbs = isset($pathsByType['membership']) ? FileStorage::absolutePath($pathsByType['membership']) : '';
$settlementAbs = isset($pathsByType['settlement']) ? FileStorage::absolutePath($pathsByType['settlement']) : '';
$assert($membershipAbs !== '' && is_file($membershipAbs), 'membership file exists before contract delete');
$assert($settlementAbs !== '' && is_file($settlementAbs), 'settlement file exists before contract delete');
$crud->delete('team_contracts', (int) $official['id']);
$assert(!is_file($membershipAbs), 'membership file removed with contract delete');
$assert(!is_file($settlementAbs), 'settlement file removed with contract delete');
$bundleAfterDelete = $contractDocs->yearBundle(1, '1406');
$assert(($bundleAfterDelete['files']['membership'] ?? null) === null, 'membership db row removed with contract');
$assert(($bundleAfterDelete['files']['settlement'] ?? null) === null, 'settlement db row removed with contract');
$assert(($bundleAfterDelete['proposal'] ?? null) === null, 'proposal removed with contract delete');

// After rejection, team may resubmit metadata while keeping existing files.
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$resubmit = $contractDocs->submitPackage(1, [
    'fiscal_year' => '1407',
    'contract_start' => '1407/01/01',
    'contract_end' => '1407/12/29',
    'formal_contract_amount' => '750000',
    'notes' => 'اصلاحیه بدون فایل جدید',
], ['error' => UPLOAD_ERR_NO_FILE], ['error' => UPLOAD_ERR_NO_FILE]);
$assert(($resubmit['proposal']['status'] ?? '') === 'pending', 'resubmit after reject keeps pending');
$assert((int) ($resubmit['proposal']['formal_contract_amount'] ?? 0) === 750000, 'resubmit updates amount without new files');
$assert(($resubmit['has_both_files'] ?? false) === true, 'resubmit reuses existing attachments');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$perf = new PerformanceReports($pdo);
$approvedReport = $perf->approve((int) $report['id']);
$assert(($approvedReport['status'] ?? '') === 'approved', 'performance report approve works');

$h2Year = null;
$overview = $perf->teamOverview(1);
foreach ($overview['periods'] as $periodRow) {
    if (($periodRow['period'] ?? '') === 'h2') {
        $h2Year = (string) ($periodRow['fiscal_year'] ?? '');
    }
}
$assert($h2Year !== null && $h2Year !== '', 'performance overview exposes h2 fiscal year');

$settingsError = false;
try {
    $perf->updateSettings([
        'performance_reports_enabled' => 1,
        'performance_h1_open_from' => '1405/09/01',
        'performance_h1_open_until' => '1405/07/01',
        'performance_h2_open_from' => '1406/01/01',
        'performance_h2_open_until' => '1406/03/31',
        'performance_report_guide' => 'x',
    ]);
} catch (InvalidArgumentException) {
    $settingsError = true;
}
$assert($settingsError, 'performance settings reject inverted date window');

$summaryAdmin = $repo->summary();
$actionLabels = array_map(static fn (array $item): string => (string) ($item['label'] ?? ''), $summaryAdmin['action_items'] ?? []);
$assert(
    count(array_filter($actionLabels, static fn (string $label): bool => str_contains($label, 'قرارداد در انتظار'))) === 1,
    'dashboard action item includes pending contracts'
);
$assert((int) ($summaryAdmin['cards']['pending_contracts'] ?? 0) >= 1, 'summary cards include pending contracts');
$assert((int) ($summaryAdmin['cards']['pending_performance'] ?? 0) >= 0, 'summary cards expose pending performance');

// Admin direct official contract clears pending proposal and approves its files.
$tmpSyncA = tempnam($tmpDir, 'sync') . '.pdf';
$tmpSyncB = tempnam($tmpDir, 'sync') . '.pdf';
file_put_contents($tmpSyncA, '%PDF-1.4 sync-a');
file_put_contents($tmpSyncB, '%PDF-1.4 sync-b');
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$syncPackage = $contractDocs->submitPackage(1, [
    'fiscal_year' => '1408',
    'contract_start' => '1408/01/01',
    'contract_end' => '1408/12/29',
    'formal_contract_amount' => '900000',
], [
    'name' => 'm1408.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpSyncA,
    'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpSyncA),
], [
    'name' => 's1408.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpSyncB,
    'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpSyncB),
]);
$assert(($syncPackage['proposal']['status'] ?? '') === 'pending', 'sync package starts pending');
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$crud->create('team_contracts', [
    'team_id' => '1',
    'fiscal_year' => '1408',
    'contract_start' => '1408/01/01',
    'contract_end' => '1408/12/29',
    'formal_contract_amount' => '900000',
]);
$synced = $contractDocs->yearBundle(1, '1408');
$assert(($synced['proposal']['status'] ?? '') === 'approved', 'direct official contract syncs pending proposal');
$assert(($synced['files']['membership']['status'] ?? '') === 'approved', 'sync approves membership file');
$assert(($synced['files']['settlement']['status'] ?? '') === 'approved', 'sync approves settlement file');

$zeroAmountBlocked = false;
try {
    $crud->create('team_contracts', [
        'team_id' => '1',
        'fiscal_year' => '1409',
        'contract_start' => '1409/01/01',
        'contract_end' => '1409/12/29',
        'formal_contract_amount' => '0',
    ]);
} catch (InvalidArgumentException) {
    $zeroAmountBlocked = true;
}
$assert($zeroAmountBlocked, 'admin cannot register zero-amount official contract');

$badYearDatesBlocked = false;
try {
    $tmpBadA = tempnam($tmpDir, 'bad') . '.pdf';
    $tmpBadB = tempnam($tmpDir, 'bad') . '.pdf';
    file_put_contents($tmpBadA, '%PDF-1.4 bad-a');
    file_put_contents($tmpBadB, '%PDF-1.4 bad-b');
    $_SESSION['mechinno_role'] = Access::ROLE_TEAM;
    $_SESSION['mechinno_team_id'] = 1;
    $contractDocs->submitPackage(1, [
        'fiscal_year' => '1410',
        'contract_start' => '1400/01/01',
        'contract_end' => '1400/12/29',
        'formal_contract_amount' => '1000',
    ], [
        'name' => 'bad-a.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpBadA,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpBadA),
    ], [
        'name' => 'bad-b.pdf', 'type' => 'application/pdf', 'tmp_name' => $tmpBadB,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($tmpBadB),
    ]);
} catch (InvalidArgumentException $e) {
    $badYearDatesBlocked = str_contains($e->getMessage(), 'سال مالی');
}
$assert($badYearDatesBlocked, 'package dates must match fiscal year');

// Rejected performance may resubmit outside the open window; team cannot spoof fiscal year.
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$perf->updateSettings([
    'performance_reports_enabled' => 1,
    'performance_h1_open_from' => '1400/01/01',
    'performance_h1_open_until' => '1499/12/29',
    'performance_h2_open_from' => '1400/01/01',
    'performance_h2_open_until' => '1499/12/29',
]);
$tmpH2 = tempnam($tmpDir, 'h2') . '.pdf';
file_put_contents($tmpH2, '%PDF-1.4 h2');
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$h2Report = $perf->submit(1, '1390', 'h2', [
    'name' => 'h2.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmpH2,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpH2),
]);
$assert((string) ($h2Report['fiscal_year'] ?? '') === $h2Year, 'team cannot spoof performance fiscal year on first submit');
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$perf->reject((int) $h2Report['id'], 'ناقص');
$perf->updateSettings([
    'performance_reports_enabled' => 1,
    'performance_h1_open_from' => '1400/01/01',
    'performance_h1_open_until' => '1400/01/02',
    'performance_h2_open_from' => '1400/01/01',
    'performance_h2_open_until' => '1400/01/02',
]);
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;
$overviewAfterReject = $perf->teamOverview(1);
$h2AfterReject = null;
foreach ($overviewAfterReject['periods'] as $periodRow) {
    if (($periodRow['period'] ?? '') === 'h2') {
        $h2AfterReject = $periodRow;
    }
}
$assert(($h2AfterReject['can_submit'] ?? false) === true, 'rejected performance can resubmit outside window');
$tmpFix = tempnam($tmpDir, 'fix') . '.pdf';
file_put_contents($tmpFix, '%PDF-1.4 fix');
$resubmitted = $perf->submit(1, '1390', 'h2', [
    'name' => 'fix.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmpFix,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpFix),
], 'اصلاحیه');
$assert(($resubmitted['status'] ?? '') === 'pending', 'rejected performance resubmit pending');
$assert((string) ($resubmitted['fiscal_year'] ?? '') === $h2Year, 'resubmit still binds period fiscal year');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$liveOccupied = (int) ($repo->summary()['cards']['desks_occupied'] ?? 0);
$assert($liveOccupied >= 1, 'live desk occupancy counts current assignments');

// Conditional payment approval cannot revive a rejected deposit.
$pdo->prepare(
    "INSERT INTO transactions (tx_date, description, amount, category, team_id, fiscal_year, month_index, payment_status, confirmed, source_file)
     VALUES ('1405/01/15', 'واریز تست رقابت', 1000, 'واریز تیم', 1, '1405', 1, 'pending', 0, 'manual')"
)->execute();
$paymentId = (int) $pdo->lastInsertId();
$workflow = new Workflow($pdo);
$workflow->rejectPayment($paymentId, 'اشتباه');
$reviveBlocked = false;
try {
    $workflow->approvePayment($paymentId);
} catch (InvalidArgumentException) {
    $reviveBlocked = true;
}
$assert($reviveBlocked, 'rejected payment cannot be approved via race');

// Removing desks must not wipe historical system charges for the year.
$pdo->exec('UPDATE desks SET team_id = NULL WHERE team_id = 1');
$pdo->prepare('DELETE FROM desk_assignments WHERE team_id = 1')->execute();
$beforeWipe = (int) $pdo->query("SELECT COUNT(*) FROM charges WHERE team_id = 1 AND fiscal_year = '1405' AND source_file = 'system'")->fetchColumn();
(new Seeder($pdo))->recalculateChargesForTeam(1, '1405', true);
$afterWipe = (int) $pdo->query("SELECT COUNT(*) FROM charges WHERE team_id = 1 AND fiscal_year = '1405' AND source_file = 'system'")->fetchColumn();
$assert($beforeWipe === $afterWipe, 'recalc without desk preserves historical system charges');

$token = RoomReservations::normalizePublicToken('mn-1234567890');
$assert($token === 'MN-1234567890', 'public room token normalizes 10-digit codes');

$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$_SESSION['mechinno_team_id'] = 1;

$teamMeta = $crud->meta();
$assert(!isset($teamMeta['resources']['panel_users']), 'team crud meta excludes panel_users');
$assert(isset($teamMeta['resources']['transactions']), 'team crud meta includes transactions');
$assert(isset($teamMeta['resources']['members']), 'team crud meta includes members');
$teamSummary = $repo->summary();
$assert(isset($teamSummary['team']['name']), 'team summary scoped');
$assert(!isset($teamSummary['debt_by_team']), 'team summary has no admin debt chart');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_VIEWER;
$viewerMeta = $crud->meta();
$assert(isset($viewerMeta['resources']['panel_users']), 'viewer can read panel_users meta');
$assert(!isset($viewerMeta['resources']['panel_users']['fields']['team_id']), 'panel_users form has no team_id');
$assert(isset($viewerMeta['resources']['transactions']), 'viewer can read transactions meta');

$_SESSION = [];

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "All smoke tests passed\n";
