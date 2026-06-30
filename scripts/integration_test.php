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
$crud->create('members', ['team_id' => (string) $teamId, 'full_name' => 'عضو یک']);
$membersAfter = $repo->paginatedResource('members', 1, 25);
$assert(count($membersAfter['rows']) === count($members['rows']) + 1, 'crud: member created');

$allowed = Access::allowedResources();
$assert(!in_array('transactions', $allowed, true), 'access: team cannot access transactions');
$assert(in_array('desks-map', $allowed, true), 'access: team can access desks-map');

$deskMap = $repo->deskMap();
$assert(count($deskMap['rows']) === 24, 'api: desk map has 24 desks');
$ownCount = count(array_filter($deskMap['rows'], static fn ($d) => !empty($d['is_own'])));
$assert($ownCount === 0, 'api: desk map own count before desk assign'); // no desk assigned yet

$pdo->exec('UPDATE desks SET team_id = ' . $teamId . ', usage_type = "formal", formal_seats = 2 WHERE number = 1');
$deskMap2 = $repo->deskMap();
$ownCount2 = count(array_filter($deskMap2['rows'], static fn ($d) => !empty($d['is_own'])));
$assert($ownCount2 === 1, 'api: desk map marks assigned desk');

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

// --- Password reset ---
$credentials = EntityAccounts::resetPassword($pdo, $teamId);
$assert(strlen($credentials['password'] ?? '') === 8, 'entity: password reset generates 8 chars');
$assert(Auth::attempt($pdo, ['auth' => ['enabled' => true]], $credentials['username'], $credentials['password']), 'entity: login with reset password');

// --- Report data ---
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
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
