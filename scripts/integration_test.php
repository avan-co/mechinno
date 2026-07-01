<?php

declare(strict_types=1);

/**
 * Full integration test: install, auth, roles, API, CRUD, reports.
 * Run: php scripts/integration_test.php
 */

require __DIR__ . '/../src/bootstrap.php';

$errors = [];
$assert = static function (bool $ok, string $message) use (&$errors): void {
    if (!$ok) {
        $errors[] = $message;
    }
};

$testDb = dirname(__DIR__) . '/data/integration_test.sqlite3';
if (is_file($testDb)) {
    unlink($testDb);
}
if (!is_dir(dirname($testDb))) {
    mkdir(dirname($testDb), 0775, true);
}

$pdo = new PDO('sqlite:' . $testDb);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- Install ---
Schema::migrate($pdo);
$assert((int) $pdo->query('SELECT COUNT(*) FROM desks')->fetchColumn() === 24, 'install: 24 desks seeded');

$installer = new Installer($pdo);
$result = $installer->installFresh();
$assert(($result['desks'] ?? 0) === 24 && ($result['teams'] ?? -1) === 0, 'install: fresh reset works');

// --- Bootstrap users from config (if config.php exists) ---
$configPath = Database::configPath();
if (is_file($configPath)) {
    $config = require $configPath;
    UserAccounts::ensureBootstrapUsers($pdo, $config);
    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM panel_users WHERE role = 'admin_editor'")->fetchColumn();
    $assert($adminCount >= 1, 'install: bootstrap admin user created');
}

// --- Entity auto-provision via Crud ---
$crud = new Crud($pdo);
$repo = new Repository($pdo);

$team = $crud->create('teams', [
    'entity_type' => 'company',
    'name' => 'شرکت آزمایشی',
    'leader' => 'علی رضایی',
    'phone' => '09121234567',
    'contract_start' => '1405/01/01',
    'contract_end' => '1405/12/29',
]);
$teamId = (int) $team['id'];
$assert($teamId > 0 && ($team['entity_code'] ?? '') !== '', 'crud: team created with entity_code');

$teamsList = $repo->paginatedResource('teams', 1, 25);
$row = $teamsList['rows'][0] ?? [];
$assert(($row['portal_username'] ?? '') === strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $team['entity_code'])), 'entity: portal username from entity_code');
$assert(($row['portal_password'] ?? '') !== '', 'entity: portal password set');

// --- Auth: database login for entity ---
$plainPassword = (string) ($row['portal_password'] ?? '');
$_SESSION = [];
$assert(Auth::attempt($pdo, ['auth' => ['enabled' => true]], (string) $row['portal_username'], $plainPassword), 'auth: entity login works');
$assert(Access::isTeam() && Access::scopedTeamId() === $teamId, 'auth: entity session scoped to team');

// --- Auth: config admin ---
$_SESSION = [];
if (is_file($configPath)) {
    $config = require $configPath;
    $auth = $config['auth'] ?? [];
    $assert(
        Auth::attempt($pdo, $config, (string) ($auth['username'] ?? ''), (string) ($auth['password'] ?? '')),
        'auth: admin config login works'
    );
    $assert(Access::canWrite(), 'auth: admin has write access');
}

// --- Auth: config viewer ---
$_SESSION = [];
if (is_file($configPath)) {
    $config = require $configPath;
    $auth = $config['auth'] ?? [];
    $assert(
        Auth::attempt($pdo, $config, (string) ($auth['viewer_username'] ?? ''), (string) ($auth['viewer_password'] ?? '')),
        'auth: viewer config login works'
    );
    $assert(Access::isAdmin() && !Access::canWrite(), 'auth: viewer is read-only admin');
}

// --- Team scoped API ---
$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'],
    'mechinno_user_id' => 1,
];
$teamSummary = $repo->summary();
$assert(isset($teamSummary['team']['name']), 'api: team summary scoped');
$assert(!isset($teamSummary['cards']['teams']), 'api: team summary has no admin teams count');

$members = $repo->paginatedResource('members', 1, 25);
$member = $crud->create('members', [
    'team_id' => (string) $teamId,
    'full_name' => 'عضو یک',
    'phone' => '09121111111',
    'national_id' => '1234567890',
    'wants_access' => '1',
]);
$membersAfter = $repo->paginatedResource('members', 1, 25);
$assert(count($membersAfter['rows']) === count($members['rows']) + 1, 'crud: team member submitted');
$assert(($member['approval_status'] ?? '') === 'pending', 'workflow: team member pending approval');

$allowed = Access::allowedResources();
$assert(in_array('transactions', $allowed, true), 'access: team can access transactions');
$assert(in_array('payment-history', $allowed, true), 'access: team can access payment-history');
$assert(!in_array('pending-members', $allowed, true), 'access: team cannot access pending-members');
$assert(!in_array('pending-payments', $allowed, true), 'access: team cannot access pending-payments');
$assert(in_array('desks', $allowed, true), 'access: team can access desks');

$payment = $crud->create('transactions', [
    'tx_date' => '1405/02/10',
    'description' => 'اعلام واریز تست',
    'amount' => '500000',
    'fiscal_year' => '1405',
    'month_index' => '2',
    'payment_reference' => 'REF-001',
]);
$assert(($payment['payment_status'] ?? '') === 'pending', 'workflow: team payment pending');
$assert((int) ($payment['confirmed'] ?? 1) === 0, 'workflow: team payment not confirmed yet');

$pendingTeamTx = $repo->paginatedResource('transactions', 1, 25, ['payment_status' => 'pending']);
$pendingIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $pendingTeamTx['rows']);
$assert(in_array((int) $payment['id'], $pendingIds, true), 'transactions: pending filter works for team');

$pdo->exec('UPDATE desks SET team_id = ' . $teamId . ', usage_type = "formal", formal_seats = 2 WHERE number = 1');
$deskMap = $repo->paginatedResource('desks', 1, 100);
$assert(count($deskMap['rows']) >= 1, 'api: team desks list after assign');

// --- Admin CRUD flow ---
$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_ADMIN_EDITOR,
    'mechinno_user' => 'admin',
    'mechinno_user_id' => 0,
];
$crud->create('rate_settings', [
    'fiscal_year' => '1405',
    'title' => 'نرخ تست',
    'charge_rate' => '300',
    'informal_rent_rate' => '500',
    'effective_from' => '1405/01/01',
]);
(new Seeder($pdo))->recalculateCharges('1405');
$matrix = $repo->chargesMatrix('1405');
$assert(count($matrix['rows']) >= 1, 'charges: matrix has teams');
$assert(($matrix['rows'][0]['cells'][0]['amount_due'] ?? 0) > 0, 'charges: auto-calculated amount');
$ledger = (new CenterLedger($pdo))->snapshot();
$assert(array_key_exists('balance', $ledger), 'ledger: snapshot has balance');
$systemRows = array_filter($ledger['rows'] ?? [], static fn (array $r): bool => str_starts_with((string) ($r['source_file'] ?? ''), 'system:'));
$assert(count($systemRows) === 0, 'ledger: no duplicate accrual rows');
$assert(($ledger['totals']['balance'] ?? -1) === ($ledger['totals']['income_total'] ?? 0) - ($ledger['totals']['expense_total'] ?? 0), 'ledger: balance equals income minus expense');

$crud->create('lockers', ['locker_number' => '10', 'team_id' => (string) $teamId, 'status' => 'تخصیص یافته']);
$lockers = $repo->paginatedResource('lockers', 1, 25);
$assert(count($lockers['rows']) >= 1, 'crud: locker created');

$tx = $crud->create('transactions', [
    'tx_date' => '1405/01/15',
    'description' => 'واریز تست',
    'amount' => '600',
    'category' => 'واریز تیم',
    'team_id' => (string) $teamId,
    'fiscal_year' => '1405',
    'month_index' => '1',
    'confirmed' => '1',
]);
$assert((int) ($tx['amount'] ?? 0) === 600, 'crud: team deposit transaction');

$workflow = new Workflow($pdo);
$approvedMember = $workflow->approveMember((int) $member['id']);
$assert(($approvedMember['approval_status'] ?? '') === 'approved', 'workflow: member approved');
$approvedPayment = $workflow->approvePayment((int) $payment['id']);
$assert(($approvedPayment['payment_status'] ?? '') === 'approved', 'workflow: payment approved');
$assert((int) ($approvedPayment['confirmed'] ?? 0) === 1, 'workflow: payment confirmed in income');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'] ?? 'team',
    'mechinno_user_id' => 1,
];
$lockerRequest = $crud->create('locker_requests', ['notes' => 'درخواست کمد تست']);
$assert(($lockerRequest['status'] ?? '') === 'pending', 'workflow: locker request pending');
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$approvedLocker = $workflow->approveLockerRequest((int) $lockerRequest['id'], 11);
$assert(($approvedLocker['status'] ?? '') === 'approved', 'workflow: locker request approved');
$assert((int) ($approvedLocker['locker_number'] ?? 0) === 11, 'workflow: locker number assigned');

$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$summaryAfterApprove = $repo->summary();
$assert(is_array($summaryAfterApprove['recent_approvals'] ?? null), 'api: team summary has recent approvals');
$approvalTypes = array_column($summaryAfterApprove['recent_approvals'], 'type');
$assert(in_array('member', $approvalTypes, true), 'api: recent approvals include member');
$assert(in_array('payment', $approvalTypes, true), 'api: recent approvals include payment');
$assert(in_array('locker', $approvalTypes, true), 'api: recent approvals include locker');
$assert((int) ($member['wants_access'] ?? 0) === 1, 'workflow: member wants_access stored');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$memberWithCode = $crud->update('members', (int) $member['id'], ['access_code' => 'A-12345']);
$assert(trim((string) ($memberWithCode['access_code'] ?? '')) === 'A-12345', 'members: admin can assign access code');
$assert((int) ($memberWithCode['wants_access'] ?? 0) === 1, 'members: access code keeps wants_access');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'] ?? 'team',
    'mechinno_user_id' => 1,
];
$doublePayment = $crud->create('transactions', [
    'tx_date' => '1405/03/15',
    'description' => 'واریز دو ماه',
    'amount' => '1200',
    'fiscal_year' => '1405',
    'month_index' => '3',
    'payment_reference' => 'REF-002',
]);
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$workflow->approvePayment((int) $doublePayment['id']);
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$teamCards = $repo->summary()['cards'] ?? [];
$assert((int) ($teamCards['paid_total'] ?? 0) >= 1200, 'payments: approved amount counts toward paid_total');
$assert(isset($teamCards['charge_total']), 'dashboard: team cards include charge_total');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$devPlan = $crud->create('development_plans', [
    'title' => 'ایده تست',
    'category' => 'idea',
    'priority' => 'high',
    'status' => 'open',
]);
$assert(($devPlan['title'] ?? '') === 'ایده تست', 'crud: development plan created');

$settings = new CenterSettings($pdo);
$updated = $settings->update([
    'bank_name' => 'بانک تست',
    'account_holder' => 'مرکز نوآوری',
    'account_number' => '1234567890',
    'card_number' => '6037-9912-3456-7890',
    'sheba' => 'IR120123456789012345678901',
    'payment_guide' => 'راهنمای تست',
]);
$assert(($updated['bank_name'] ?? '') === 'بانک تست', 'settings: payment info saved');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'] ?? 'team',
    'mechinno_user_id' => 1,
];
$teamSummarySettings = $repo->summary()['payment_settings'] ?? [];
$assert(($teamSummarySettings['sheba'] ?? '') === 'IR120123456789012345678901', 'settings: team can read payment info');

$history = $repo->paginatedResource('payment-history', 1, 25);
$historyIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $history['rows']);
$assert(in_array((int) $approvedPayment['id'], $historyIds, true), 'payment-history: approved payment listed');
$pendingAfterApprove = $repo->paginatedResource('transactions', 1, 25, ['payment_status' => 'pending']);
$pendingAfterIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $pendingAfterApprove['rows']);
$assert(!in_array((int) $payment['id'], $pendingAfterIds, true), 'payment-history: pending payment excluded after approve');

// --- Password reset ---
$credentials = EntityAccounts::resetPassword($pdo, $teamId);
$assert(strlen($credentials['password'] ?? '') === 8, 'entity: password reset generates 8 chars');
$assert(Auth::attempt($pdo, ['auth' => ['enabled' => true]], $credentials['username'], $credentials['password']), 'entity: login with reset password');

// --- Report data ---
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$deskMapAdmin = $repo->deskMap();
$assert(count($deskMapAdmin['rows']) === 24, 'api: admin desks-map has 24 desks');
$report = (new ReportData($pdo))->build();
$assert(isset($report['teams'], $report['members'], $report['desks']), 'report: build succeeds');
$assert(count($report['teams']) >= 1, 'report: includes teams');

// --- Excel exporter ---
$exporter = new ExcelExporter($pdo);
$assert(method_exists($exporter, 'output'), 'export: ExcelExporter available');

// --- Delete team cascades portal user ---
$userBefore = (int) $pdo->query('SELECT COUNT(*) FROM panel_users WHERE team_id = ' . $teamId)->fetchColumn();
$assert($userBefore === 1, 'entity: one portal user per team');
$crud->delete('teams', $teamId);
$userAfter = (int) $pdo->query('SELECT COUNT(*) FROM panel_users WHERE team_id = ' . $teamId)->fetchColumn();
$assert($userAfter === 0, 'entity: portal user deleted with team');

// --- Unused tables dropped ---
foreach (['plans', 'team_rates', 'member_desks', 'import_runs'] as $table) {
    $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
    $assert($exists === false, "schema: dropped table {$table}");
}

// --- Password sync ---
if (is_file($configPath)) {
    $config = require $configPath;
    UserAccounts::ensureBootstrapUsers($pdo, $config);
    $hash1 = $pdo->query("SELECT password_hash FROM panel_users WHERE username = 'admin'")->fetchColumn();
    UserAccounts::ensureBootstrapUsers($pdo, $config);
    $hash2 = $pdo->query("SELECT password_hash FROM panel_users WHERE username = 'admin'")->fetchColumn();
    $assert($hash1 === $hash2, 'auth: password sync idempotent');
}

// --- Viewer cannot write (simulated) ---
$_SESSION = ['mechinno_authenticated' => true, 'mechinno_role' => Access::ROLE_ADMIN_VIEWER, 'mechinno_user' => 'viewer', 'mechinno_user_id' => 0];
$assert(!Access::canWrite(), 'access: viewer cannot write');
$viewerMeta = $crud->meta();
$assert(isset($viewerMeta['resources']['panel_users']), 'access: viewer sees panel_users meta');
$assert(isset($viewerMeta['resources']['teams']), 'access: viewer sees teams meta');

$_SESSION = [];

if ($errors !== []) {
    fwrite(STDERR, "FAILED (" . count($errors) . " errors):\n" . implode("\n", $errors) . "\n");
    exit(1);
}

echo "All integration tests passed (" . (count($errors)) . " errors)\n";
