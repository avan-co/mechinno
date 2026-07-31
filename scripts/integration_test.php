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
$pdo->exec("INSERT INTO team_contracts (team_id, fiscal_year, contract_start, contract_end, notes)
            VALUES (99, '1403', '1403/01/01', '1403/12/29', 'orphan')");
$installer->installFresh();
$orphanContracts = (int) $pdo->query('SELECT COUNT(*) FROM team_contracts')->fetchColumn();
$assert($orphanContracts === 0, 'install: reset clears team_contracts');

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

$syncTeam = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد همگام‌سازی',
    'leader' => 'تست',
    'phone' => '09123334444',
    'joined_at' => '1405/01/01',
]);
$syncTeamId = (int) $syncTeam['id'];
$crud->create('team_contracts', [
    'team_id' => (string) $syncTeamId,
    'fiscal_year' => '1405',
    'contract_start' => '1405/03/01',
    'contract_end' => '1405/08/29',
    'formal_contract_amount' => '1000000',
]);
$pdo->exec('UPDATE desks SET team_id = ' . $syncTeamId . ' WHERE number = 2');
$pdo->prepare(
    'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until)
     VALUES (2, 2, :team_id, :usage_type, :assigned_from, NULL)'
)->execute([
    'team_id' => $syncTeamId,
    'usage_type' => 'formal',
    'assigned_from' => '1405/01/01',
]);
$pdo->prepare(
    'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until)
     VALUES (2, 2, :team_id, :usage_type, :assigned_from, :assigned_until)'
)->execute([
    'team_id' => $syncTeamId,
    'usage_type' => 'formal',
    'assigned_from' => '1405/04/01',
    'assigned_until' => '1405/06/29',
]);
$installer->syncDatabase();
$syncRow = $pdo->query('SELECT assigned_until FROM desk_assignments WHERE desk_id = 2 AND team_id = ' . $syncTeamId . ' ORDER BY id DESC LIMIT 1')->fetch();
$assert(($syncRow['assigned_until'] ?? '') === '1405/06/29', 'install: sync keeps bounded assignment end date');
$syncDuplicates = (int) $pdo->query('SELECT COUNT(*) FROM desk_assignments WHERE desk_id = 2 AND team_id = ' . $syncTeamId)->fetchColumn();
$assert($syncDuplicates === 1, 'install: sync dedupes desk-year assignment rows');

$team = $crud->create('teams', [
    'entity_type' => 'company',
    'name' => 'شرکت آزمایشی',
    'leader' => 'علی رضایی',
    'phone' => '09121234567',
    'joined_at' => '1404/01/01',
]);
$teamId = (int) $team['id'];
$crud->create('team_contracts', [
    'team_id' => (string) $teamId,
    'fiscal_year' => '1405',
    'contract_start' => '1405/01/01',
    'contract_end' => '1405/12/29',
    'formal_contract_amount' => '5000000',
]);
$joinedOnlyTeam = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد بدون قرارداد خودکار',
    'leader' => 'تست خودکار',
    'phone' => '09120000099',
    'joined_at' => '1403/01/01',
]);
$joinedOnlyTeamId = (int) $joinedOnlyTeam['id'];
Schema::migrate($pdo);
$autoContractCount = (int) $pdo->query("SELECT COUNT(*) FROM team_contracts WHERE team_id = {$joinedOnlyTeamId}")->fetchColumn();
$assert($autoContractCount === 0, 'contracts: joined_at alone does not auto-create contracts on migrate');
$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $joinedOnlyTeamId,
    'mechinno_user' => 'joined-only-team',
    'mechinno_user_id' => 2,
];
$joinedOnlyProfile = $repo->teamProfile($joinedOnlyTeamId);
$assert(isset($joinedOnlyProfile['team']['name']), 'api: team profile without current-year contract');
$assert(is_array($joinedOnlyProfile['year_summaries'] ?? null), 'api: team profile year summaries without contract');
$assert(isset($joinedOnlyProfile['lockers']) && is_array($joinedOnlyProfile['charges']), 'api: team profile loads lockers and charges');
$joinedOnlySummary = $repo->summary();
$assert(isset($joinedOnlySummary['team']['name']), 'api: team summary without current-year contract');
$_SESSION = [];
$assert($teamId > 0 && ($team['entity_code'] ?? '') !== '', 'crud: team created with entity_code');
$leaderMember = $pdo->query("SELECT id, is_leader, full_name FROM members WHERE team_id = {$teamId} AND is_leader = 1")->fetch();
$assert($leaderMember !== false, 'teams: leader member auto-created');
$assert(($leaderMember['full_name'] ?? '') === 'علی رضایی', 'teams: leader member name matches team leader');

$teamsList = $repo->paginatedResource('teams', 1, 25);
$row = $teamsList['rows'][0] ?? [];
$assert(($row['portal_username'] ?? '') === strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $team['entity_code'])), 'entity: portal username from entity_code');
$assert((int) ($row['portal_has_password'] ?? 0) === 1, 'entity: portal password set');

// --- Auth: database login for entity ---
$credentials = EntityAccounts::resetPassword($pdo, $teamId);
$plainPassword = (string) ($credentials['password'] ?? '');
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

$pdo->exec('UPDATE desks SET team_id = ' . $teamId . ', usage_type = "formal", formal_seats = 2 WHERE number = 1');
$partialUntil = JalaliDate::monthEnd('1405', 7);
(new DeskAssignments($pdo))->syncDeskAssignment(1, [
    'number' => 1,
    'team_id' => $teamId,
    'usage_type' => 'formal',
    'assignment_from' => '1405/01/01',
    'assignment_until' => $partialUntil,
]);

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_ADMIN_EDITOR,
    'mechinno_user' => 'admin',
    'mechinno_user_id' => 0,
];
$deskAfterAssign = $crud->find('desks', 1);
$assert((int) ($deskAfterAssign['team_id'] ?? 0) === $teamId, 'desks: team remains assigned when end date is set');
$assert(($deskAfterAssign['assignment_until'] ?? '') === $partialUntil, 'desks: assignment_until is shown on desk form');
$deskAfterUpdate = $crud->update('desks', 1, [
    'team_id' => (string) $teamId,
    'usage_type' => 'formal',
    'assignment_from_month' => '1',
    'assignment_until_month' => '7',
]);
$assert(($deskAfterUpdate['assignment_until'] ?? '') === $partialUntil, 'desks: assignment_until persists after save');
$deskListRow = null;
foreach ($repo->paginatedResource('desks', 1, 100)['rows'] as $deskRow) {
    if ((int) ($deskRow['number'] ?? 0) === 1) {
        $deskListRow = $deskRow;
        break;
    }
}
$assert($deskListRow !== null, 'desks: list includes assigned desk');
$assert(($deskListRow['assignment_until'] ?? '') === $partialUntil, 'desks: list shows assignment_until');
$crud->create('rate_settings', [
    'fiscal_year' => '1405',
    'title' => 'نرخ تست تیم',
    'charge_rate' => '300',
    'informal_rent_rate' => '500',
    'effective_from' => '1405/01/01',
]);
(new Seeder($pdo))->recalculateCharges('1405');
$partialMatrix = $repo->chargesMatrix('1405');
$chargedMonths = 0;
$emptyAfterDesk = 0;
foreach ($partialMatrix['rows'] ?? [] as $matrixRow) {
    if ((int) ($matrixRow['team']['id'] ?? 0) !== $teamId) {
        continue;
    }
    foreach ($matrixRow['cells'] ?? [] as $cell) {
        $monthIndex = (int) ($cell['month_index'] ?? 0);
        $amount = (int) ($cell['amount_due'] ?? 0);
        if ($amount > 0) {
            $chargedMonths++;
        }
        if ($monthIndex > 7 && $amount > 0) {
            $emptyAfterDesk++;
        }
    }
}
$assert($chargedMonths === 7, 'charges: partial desk assignment charges only assigned months');
$assert($emptyAfterDesk === 0, 'charges: no charges after desk assignment end month');

$assignmentRow = $pdo->query('SELECT id FROM desk_assignments WHERE desk_id = 1 AND team_id = ' . $teamId . ' ORDER BY id DESC LIMIT 1')->fetch();
$assert($assignmentRow !== false, 'desk-assignments: has row for desk 1');
$assignmentId = (int) $assignmentRow['id'];
$fromMonth5 = JalaliDate::monthStart('1405', 5);
$untilMonth7 = JalaliDate::monthEnd('1405', 7);
$updatedAssign = $crud->update('desk_assignments', $assignmentId, [
    'team_id' => (string) $teamId,
    'desk_id' => '1',
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '5',
    'assigned_until_month' => '7',
    'notes' => '',
]);
$assert(($updatedAssign['assigned_from'] ?? '') === $fromMonth5, 'desk-assignments: update persists from month');
$assert(($updatedAssign['assigned_until'] ?? '') === $untilMonth7, 'desk-assignments: update persists until month');
$deskListAfterAssign = null;
foreach ($repo->paginatedResource('desks', 1, 100)['rows'] as $deskRow) {
    if ((int) ($deskRow['number'] ?? 0) === 1) {
        $deskListAfterAssign = $deskRow;
        break;
    }
}
$assert($deskListAfterAssign !== null, 'desks: list after desk-assignments update');
$assert(($deskListAfterAssign['assignment_from'] ?? '') === $fromMonth5, 'desks: list shows updated assignment_from');
$assert(($deskListAfterAssign['assignment_until'] ?? '') === $untilMonth7, 'desks: list shows updated assignment_until');

$upsertFrom = JalaliDate::monthStart('1405', 3);
$upsertUntil = JalaliDate::monthEnd('1405', 6);
$upserted = $crud->create('desk_assignments', [
    'team_id' => (string) $teamId,
    'desk_id' => '1',
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '3',
    'assigned_until_month' => '6',
    'notes' => 'upsert',
]);
$assert((int) ($upserted['id'] ?? 0) === $assignmentId, 'desk-assignments: create upserts existing desk-year row');
$assert(($upserted['assigned_from'] ?? '') === $upsertFrom, 'desk-assignments: upsert saves from month');
$assert(($upserted['assigned_until'] ?? '') === $upsertUntil, 'desk-assignments: upsert saves until month');
$duplicateCount = (int) $pdo->query('SELECT COUNT(*) FROM desk_assignments WHERE desk_id = 1 AND team_id = ' . $teamId)->fetchColumn();
$assert($duplicateCount === 1, 'desk-assignments: reconcile removes duplicate rows for desk-year');

$pdo->prepare(
    'INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until)
     VALUES (1, 1, :team_id, :usage_type, :assigned_from, :assigned_until)'
)->execute([
    'team_id' => $teamId,
    'usage_type' => 'formal',
    'assigned_from' => $fromMonth5,
    'assigned_until' => $untilMonth7,
]);
$duplicateAmounts = (new Seeder($pdo))->monthlyAmountsForTeam($teamId, '1405');
// Overlapping duplicate rows: bill the union of months, still one desk per month.
$assert(count($duplicateAmounts) === 5, 'charges: overlapping desk rows bill union of months');
$assert(($duplicateAmounts[5]['charge_amount'] ?? 0) === 300, 'charges: duplicate rows count one desk per month');
(new Seeder($pdo))->recalculateCharges('1405');
$afterGlobalRecalc = (new Seeder($pdo))->monthlyAmountsForTeam($teamId, '1405');
$assert(($afterGlobalRecalc[5]['charge_amount'] ?? 0) === 300, 'charges: global recalc keeps single-desk amounts');
$assert(($afterGlobalRecalc[3]['charge_amount'] ?? 0) === 300, 'charges: early uncovered segment still billed');
$assert(($afterGlobalRecalc[7]['charge_amount'] ?? 0) === 300, 'charges: late uncovered segment still billed');
// Remove intentional duplicate so later assignment updates are not blocked by overlap checks.
$pdo->exec('DELETE FROM desk_assignments WHERE desk_id = 1 AND id <> ' . (int) $assignmentId);
Schema::reconcileDeskAssignments($pdo);

$crud->update('desk_assignments', $assignmentId, [
    'team_id' => (string) $teamId,
    'desk_id' => '1',
    'usage_type' => 'informal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '5',
    'assigned_until_month' => '7',
    'charge_exempt' => '1',
    'rent_exempt' => '0',
    'notes' => '',
]);
$chargeExemptAmounts = (new Seeder($pdo))->monthlyAmountsForTeam($teamId, '1405');
$assert(($chargeExemptAmounts[5]['charge_amount'] ?? -1) === 0, 'billing: charge-exempt desk skips charge amount');
$assert(($chargeExemptAmounts[5]['rent_amount'] ?? 0) === 500, 'billing: charge-exempt desk still pays informal rent');

$crud->update('desk_assignments', $assignmentId, [
    'team_id' => (string) $teamId,
    'desk_id' => '1',
    'usage_type' => 'informal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '5',
    'assigned_until_month' => '7',
    'charge_exempt' => '0',
    'rent_exempt' => '1',
    'notes' => '',
]);
$rentExemptAmounts = (new Seeder($pdo))->monthlyAmountsForTeam($teamId, '1405');
$assert(($rentExemptAmounts[5]['charge_amount'] ?? 0) === 300, 'billing: rent-exempt desk still pays charge');
$assert(($rentExemptAmounts[5]['rent_amount'] ?? -1) === 0, 'billing: rent-exempt desk skips informal rent');
$rentExemptProfile = $repo->teamProfile($teamId);
$rentProfileBilling = $rentExemptProfile['billing_summaries']['1405'] ?? [];
$assert(($rentProfileBilling['has_exemptions'] ?? false) === true, 'api: team profile billing summaries include desk exemptions');
$assert(is_array($rentProfileBilling['exempt_desks'] ?? null) && $rentProfileBilling['exempt_desks'] !== [], 'api: team profile exposes exempt desk list');

$contractRow = $pdo->query("SELECT id FROM team_contracts WHERE team_id = {$teamId} AND fiscal_year = '1405'")->fetch();
$assert($contractRow !== false, 'billing: team contract exists for test year');
$crud->update('team_contracts', (int) $contractRow['id'], [
    'team_id' => (string) $teamId,
    'fiscal_year' => '1405',
    'contract_start' => '1405/01/01',
    'contract_end' => '1405/12/29',
    'formal_contract_amount' => '5000000',
    'charge_rate_override' => '200',
    'informal_rent_rate_override' => '',
    'notes' => '',
]);
$crud->update('desk_assignments', $assignmentId, [
    'team_id' => (string) $teamId,
    'desk_id' => '1',
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '5',
    'assigned_until_month' => '7',
    'charge_exempt' => '0',
    'rent_exempt' => '0',
    'notes' => '',
]);
$customRateAmounts = (new Seeder($pdo))->monthlyAmountsForTeam($teamId, '1405');
$assert(($customRateAmounts[5]['charge_amount'] ?? 0) === 200, 'billing: contract charge override applies');
$billingSummary = (new TeamContracts($pdo))->billingSummaryForTeamInYear($teamId, '1405');
$assert(($billingSummary['has_custom_rates'] ?? false) === true, 'billing: summary flags custom contract rates');
$assert(($billingSummary['has_billing_adjustments'] ?? false) === true, 'billing: summary reports billing adjustments');
(new Seeder($pdo))->recalculateChargesForTeam($teamId, '1405');
$chargeNote = (string) ($pdo->query(
    "SELECT note FROM charges WHERE team_id = {$teamId} AND fiscal_year = '1405' AND month_index = 5 AND source_file = 'system'"
)->fetchColumn() ?: '');
$assert(str_contains($chargeNote, 'خودکار'), 'billing: system charge stores auto-calculation note');

$pdo->exec('DELETE FROM desk_assignments WHERE desk_id = 1 AND id <> ' . $assignmentId);

$expiredDeskId = (int) ($pdo->query('SELECT id FROM desks WHERE number = 10')->fetchColumn() ?: 0);
if ($expiredDeskId > 0) {
    $pdo->exec('DELETE FROM desk_assignments WHERE desk_id = ' . $expiredDeskId);
    (new DeskAssignments($pdo))->syncDeskAssignment($expiredDeskId, [
        'number' => 10,
        'team_id' => $teamId,
        'usage_type' => 'formal',
        'assignment_from' => '1405/01/01',
        'assignment_until' => JalaliDate::monthEnd('1405', 3),
    ]);
    $_SESSION = [
        'mechinno_authenticated' => true,
        'mechinno_role' => Access::ROLE_ADMIN_EDITOR,
        'mechinno_user' => 'admin',
        'mechinno_user_id' => 0,
    ];
    $extendedUntil = JalaliDate::monthEnd('1405', 8);
    $expiredUpdate = $crud->update('desks', $expiredDeskId, [
        'team_id' => (string) $teamId,
        'usage_type' => 'formal',
        'assignment_from_month' => '1',
        'assignment_until_month' => '8',
    ]);
    $assert(($expiredUpdate['assignment_until'] ?? '') === $extendedUntil, 'desks: expired year assignment extends end month in place');
    $assignmentCount = (int) $pdo->query('SELECT COUNT(*) FROM desk_assignments WHERE desk_id = ' . $expiredDeskId)->fetchColumn();
    $assert($assignmentCount === 1, 'desks: extending expired assignment does not create duplicate row');
}

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'],
    'mechinno_user_id' => 1,
];
$payable = $repo->teamPayableMonths($teamId);
$assert($payable !== [], 'charges: team has payable months');
$firstPayable = $payable[0];
$payment = $crud->create('transactions', [
    'tx_date' => '1405/02/10',
    'description' => 'اعلام واریز تست',
    'payment_reference' => 'REF-001',
    'payment_plan' => [[
        'fiscal_year' => $firstPayable['fiscal_year'],
        'month_index' => $firstPayable['month_index'],
        'amount' => $firstPayable['amount_remaining'],
    ]],
]);
$assert((int) ($payment['amount'] ?? 0) === (int) $firstPayable['amount_remaining'], 'workflow: team payment exact amount');
$assert(($payment['payment_status'] ?? '') === 'pending', 'workflow: team payment pending');
$assert((int) ($payment['confirmed'] ?? 1) === 0, 'workflow: team payment not confirmed yet');

$pendingTeamTx = $repo->paginatedResource('transactions', 1, 25, ['payment_status' => 'pending']);
$pendingIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $pendingTeamTx['rows']);
$assert(in_array((int) $payment['id'], $pendingIds, true), 'transactions: pending filter works for team');

$deskMap = $repo->paginatedResource('desks', 1, 100);
$assert(count($deskMap['rows']) >= 1, 'api: team desks list after assign');

// --- Admin CRUD flow ---
$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_ADMIN_EDITOR,
    'mechinno_user' => 'admin',
    'mechinno_user_id' => 0,
];
$matrix = $repo->chargesMatrix('1405');
$assert(count($matrix['rows']) >= 1, 'charges: matrix has teams with contract and desk');
$assert(($matrix['rows'][0]['cells'][0]['amount_due'] ?? 0) > 0, 'charges: auto-calculated amount');
$matrixEmptyYear = $repo->chargesMatrix('1404');
$teamIn1404 = array_filter($matrixEmptyYear['rows'] ?? [], static fn (array $r): bool => (int) ($r['team']['id'] ?? 0) === $teamId);
$assert($teamIn1404 === [], 'charges: team without 1404 contract hidden from collage');
$ledger = (new CenterLedger($pdo))->snapshot();
$assert(array_key_exists('balance', $ledger), 'ledger: snapshot has balance');
$assert(array_key_exists('page', $ledger) && array_key_exists('pages', $ledger), 'ledger: snapshot is paginated');
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
$payableAfterFirst = $repo->teamPayableMonths($teamId);
$assert($payableAfterFirst !== [], 'payments: remaining months after first approval');
$nextPayable = $payableAfterFirst[0];
$doublePayment = $crud->create('transactions', [
    'tx_date' => '1405/03/15',
    'description' => 'واریز ماه بعد',
    'payment_reference' => 'REF-002',
    'payment_plan' => [[
        'fiscal_year' => $nextPayable['fiscal_year'],
        'month_index' => $nextPayable['month_index'],
        'amount' => $nextPayable['amount_remaining'],
    ]],
]);
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$workflow->approvePayment((int) $doublePayment['id']);
$_SESSION['mechinno_role'] = Access::ROLE_TEAM;
$teamCards = $repo->summary()['cards'] ?? [];
$assert((int) ($teamCards['paid_total'] ?? 0) >= (int) $nextPayable['amount_remaining'], 'payments: approved amount counts toward paid_total');
$assert(isset($teamCards['charge_total']), 'dashboard: team cards include charge_total');

$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$devPlan = $crud->create('development_plans', [
    'title' => 'ایده تست',
    'category' => 'idea',
    'priority' => 'high',
    'status' => 'open',
]);
$assert(($devPlan['title'] ?? '') === 'ایده تست', 'crud: development plan created');

$expense = $crud->create('transactions', [
    'tx_date' => '1405/03/01',
    'category' => 'هزینه',
    'finance_subtype' => 'لوازم مصرفی',
    'description' => 'خرید لوازم تست',
    'amount' => '50000',
    'confirmed' => '1',
]);
$assert((int) ($expense['amount'] ?? 0) === -50000, 'finance: expense stored as negative');
$income = $crud->create('transactions', [
    'tx_date' => '1405/03/02',
    'category' => 'درآمد',
    'finance_subtype' => 'دوره آموزشی',
    'description' => 'کارگاه تست',
    'amount' => '100000',
    'confirmed' => '1',
]);
$assert((int) ($income['amount'] ?? 0) === 100000, 'finance: income stored as positive');

$crud->create('team_contracts', [
    'team_id' => (string) $teamId,
    'fiscal_year' => '1404',
    'contract_start' => '1404/01/01',
    'contract_end' => '1404/12/29',
    'formal_contract_amount' => '3000000',
]);

$deskAssign = $crud->create('desk_assignments', [
    'desk_id' => '1',
    'team_id' => (string) $teamId,
    'usage_type' => 'formal',
    'fiscal_year' => '1404',
    'assigned_from_month' => '1',
    'assigned_until_month' => '12',
    'notes' => 'سال قبل',
]);
$assert((int) ($deskAssign['desk_number'] ?? 0) === 1, 'desk_assignments: historical record created');

$activeAssign = $crud->create('desk_assignments', [
    'desk_id' => '3',
    'team_id' => (string) $teamId,
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '1',
    'assigned_until_month' => '12',
    'notes' => 'فعال بدون تحویل',
]);
$deskThree = $crud->find('desks', 3);
$assert((int) ($deskThree['team_id'] ?? 0) === $teamId, 'desk_assignments: open assignment syncs desks table');

$crud->update('desk_assignments', (int) $activeAssign['id'], [
    'assigned_until_month' => '12',
]);

(new DeskAssignments($pdo))->syncDeskAssignment(3, [
    'number' => 3,
    'team_id' => $teamId,
    'usage_type' => 'formal',
    'assignment_from' => '1406/01/01',
]);
$splitCount = (int) $pdo->query('SELECT COUNT(*) FROM desk_assignments WHERE desk_id = 3')->fetchColumn();
$assert($splitCount >= 2, 'desks: fiscal-year change keeps history');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'] ?? 'team',
    'mechinno_user_id' => 1,
];
$teamDeskHistory = $repo->paginatedResource('desk-assignments', 1, 100);
$assert(count($teamDeskHistory['rows']) >= 2, 'desk-assignments: team panel shows full history');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_ADMIN_EDITOR,
    'mechinno_user' => 'admin',
    'mechinno_user_id' => 0,
];
$smsStats = (new SmsService($pdo))->stats();
$assert(isset($smsStats['daily_limit']), 'sms: stats endpoint data');
$assert(array_key_exists('panel_credit', $smsStats), 'sms: stats include panel credit');
$assert(array_key_exists('sms_configured', $smsStats), 'sms: stats include configured flag');
$assert((new SmsService($pdo))->isApiConfigured() === false, 'sms: not configured without credentials');
$smsRecipients = $repo->paginatedResource('sms-recipients', 1, 50, ['is_leader' => '1']);
$assert(count($smsRecipients['rows']) >= 1, 'sms: leader recipients listed');
$allMembers = $repo->paginatedResource('members', 1, 100, []);
$leaderMembers = $repo->paginatedResource('members', 1, 100, ['is_leader' => '1']);
$assert($leaderMembers['total'] <= $allMembers['total'], 'members: leader filter reduces result set');
$assert($leaderMembers['total'] >= 1, 'members: leader filter returns leaders');
$assert(MelliPayamak::deliveryLabel(4) === 'رسیده به گوشی', 'sms: delivery label mapping');
$assert(MelliPayamak::deliveryLabel(0) === 'ارسال شده به مخابرات', 'sms: delivery code 0 label');
$assert(MelliPayamak::deliveryLabel(-2) === 'شناسه پیامک نامعتبر یا هنوز ثبت نشده', 'sms: delivery error code label');
$patternMessage = MelliPayamak::parsePatternMessage('456@علی;مرکز نوآوری##shared');
$assert(($patternMessage['body_id'] ?? null) === 456, 'sms: pattern notation parses body id');
$assert(($patternMessage['variables'] ?? null) === 'علی;مرکز نوآوری', 'sms: pattern notation preserves variables');
$assert(MelliPayamak::parsePatternMessage('متن عادی حاوی @') === null, 'sms: ordinary text is not treated as pattern');
$assert(MelliPayamak::parsePatternMessage('0@متغیر##shared') === null, 'sms: pattern rejects invalid body id');
$patternClient = new MelliPayamak();
$invalidPattern = $patternClient->send('', '', '', '09121111111', 'bad@متغیر##shared');
$assert(($invalidPattern['ok'] ?? true) === false, 'sms: malformed pattern is rejected before API request');
$assert(
    str_contains((string) ($invalidPattern['error'] ?? ''), 'قالب صحیح'),
    'sms: malformed pattern returns actionable error'
);
$emptyPatternVariables = $patternClient->sendPattern('', '', '09121111111', 456, '');
$assert(($emptyPatternVariables['ok'] ?? true) === false, 'sms: empty pattern variables are rejected');
$adminAllowed = Access::allowedResources();
$assert(in_array('sms-send', $adminAllowed, true), 'access: admin can send sms announcements');

$smsCenter = new CenterSettings($pdo);
$smsCenter->updateSms([
    'sms_username' => 'testuser',
    'sms_password' => 'testpass',
    'sms_from_number' => '30001234',
]);
$partial = $smsCenter->smsSettings();
$assert(($partial['sms_username'] ?? '') === 'testuser', 'sms: partial update preserves username');
$sendSettings = $smsCenter->smsSettingsForSend();
$assert(($sendSettings['sms_password'] ?? '') === 'testpass', 'sms: partial update preserves password');

$debtors = $repo->debtorTeamsForSms();
$teamDebtor = null;
foreach ($debtors as $debtorRow) {
    if ((int) ($debtorRow['team_id'] ?? 0) === $teamId) {
        $teamDebtor = $debtorRow;
        break;
    }
}
$assert($teamDebtor !== null, 'charges: debtor list still includes team with unpaid charges');
$matrixAfterPay = $repo->chargesMatrix('1405');
$matrixDebt = 0;
foreach ($matrixAfterPay['rows'] ?? [] as $matrixRow) {
    if ((int) ($matrixRow['team']['id'] ?? 0) !== $teamId) {
        continue;
    }
    foreach ($matrixRow['cells'] ?? [] as $cell) {
        $status = (string) ($cell['status'] ?? '');
        if ($status === 'بدهکار به مرکز' || $status === 'ناقص') {
            $matrixDebt += max(0, (int) ($cell['amount_due'] ?? 0) - (int) ($cell['amount_paid'] ?? 0));
        }
    }
}
$assert((int) ($teamDebtor['debt_total'] ?? 0) === $matrixDebt, 'charges: debtor total matches charges collage');

$manualDebtTeam = $crud->create('teams', [
    'entity_type' => 'company',
    'name' => 'نهاد بدهی دستی',
    'leader' => 'مسئول دستی',
    'phone' => '09123334444',
    'joined_at' => '1405/01/01',
]);
$manualDebtTeamId = (int) $manualDebtTeam['id'];
$crud->create('team_contracts', [
    'team_id' => (string) $manualDebtTeamId,
    'fiscal_year' => '1405',
    'contract_start' => '1405/01/01',
    'contract_end' => '1405/12/29',
    'formal_contract_amount' => '2000000',
]);
$crud->create('charges', [
    'team_id' => (string) $manualDebtTeamId,
    'fiscal_year' => '1405',
    'month_index' => '6',
    'month_name' => 'شهریور',
    'charge_amount' => '450000',
    'rent_amount' => '0',
    'amount' => '450000',
    'note' => 'شارژ دستی بدون میز',
]);
$manualDebtors = $repo->debtorTeamsForSms();
$manualDebtor = null;
foreach ($manualDebtors as $debtorRow) {
    if ((int) ($debtorRow['team_id'] ?? 0) === $manualDebtTeamId) {
        $manualDebtor = $debtorRow;
        break;
    }
}
$assert($manualDebtor !== null, 'charges: manual charge without desk assignment appears in debtor list');
$assert((int) ($manualDebtor['debt_total'] ?? 0) === 450000, 'charges: manual charge debt total preserved');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_ADMIN_EDITOR,
    'mechinno_user' => 'admin',
    'mechinno_user_id' => 0,
];

$inactiveTeam = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد غیرفعال تست',
    'leader' => 'تست',
    'phone' => '09120000002',
]);
$contracts = new TeamContracts($pdo);
$contracts->syncTeamActiveStatus((int) $inactiveTeam['id']);
$inactiveTeam = $crud->find('teams', (int) $inactiveTeam['id']);
$assert((int) ($inactiveTeam['is_active'] ?? 1) === 0, 'teams: inactive when no current-year contract');
$teamsSorted = $repo->paginatedResource('teams', 1, 50);
$assert((int) ($teamsSorted['rows'][0]['is_active'] ?? 0) === 1, 'teams: active entities listed first');

$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $teamId,
    'mechinno_user' => $row['portal_username'] ?? 'team',
    'mechinno_user_id' => 1,
];
$memberRequest = $crud->create('member_requests', [
    'member_id' => (string) ($member['id'] ?? 0),
    'request_type' => 'update',
    'full_name' => 'عضو یک ویرایش‌شده',
    'phone' => '09121111111',
    'national_id' => '1234567890',
    'wants_access' => '1',
    'notes' => 'درخواست تست',
]);
$assert(($memberRequest['status'] ?? '') === 'pending', 'member_requests: team update request pending');
$pdo->prepare("UPDATE member_requests SET status = 'rejected', reviewed_at = '1405/01/02' WHERE id = :id")
    ->execute(['id' => (int) $memberRequest['id']]);
$deleteRequest = $crud->create('member_requests', [
    'member_id' => (string) ($member['id'] ?? 0),
    'request_type' => 'delete',
    'notes' => 'درخواست حذف تست',
]);
$assert(($deleteRequest['request_type'] ?? '') === 'delete', 'member_requests: team delete request accepted');
$assert(($deleteRequest['status'] ?? '') === 'pending', 'member_requests: team delete request pending');
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;

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
$payableForReject = $repo->teamPayableMonths($teamId);
$rejectPayable = $payableForReject[0] ?? null;
$assert($rejectPayable !== null, 'payments: month available for reject test');
$pendingReject = $crud->create('transactions', [
    'tx_date' => '1405/06/01',
    'description' => 'واریز برای رد',
    'payment_reference' => 'REJ-001',
    'payment_plan' => [[
        'fiscal_year' => $rejectPayable['fiscal_year'],
        'month_index' => $rejectPayable['month_index'],
        'amount' => $rejectPayable['amount_remaining'],
    ]],
]);
$assert(($pendingReject['payment_status'] ?? '') === 'pending', 'workflow: payment pending before reject');
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$rejectedPayment = $workflow->rejectPayment((int) $pendingReject['id'], 'مبلغ نادرست');
$assert(($rejectedPayment['payment_status'] ?? '') === 'rejected', 'workflow: payment rejected');
$pendingAfterReject = $repo->paginatedResource('pending-payments', 1, 25);
$pendingRejectIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $pendingAfterReject['rows']);
$assert(!in_array((int) $pendingReject['id'], $pendingRejectIds, true), 'workflow: rejected payment removed from pending');
$historyAfterReject = $repo->paginatedResource('payment-history', 1, 25);
$historyRejectIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $historyAfterReject['rows']);
$assert(in_array((int) $pendingReject['id'], $historyRejectIds, true), 'payment-history: rejected payment listed');
$pendingAfterApprove = $repo->paginatedResource('transactions', 1, 25, ['payment_status' => 'pending']);
$pendingAfterIds = array_map(static fn ($r) => (int) ($r['id'] ?? 0), $pendingAfterApprove['rows']);
$assert(!in_array((int) $payment['id'], $pendingAfterIds, true), 'payment-history: pending payment excluded after approve');

// --- Password reset ---
$credentials = EntityAccounts::resetPassword($pdo, $teamId);
$assert(strlen($credentials['password'] ?? '') === 8, 'entity: password reset generates 8 chars');
$assert(Auth::attempt($pdo, ['auth' => ['enabled' => true]], $credentials['username'], $credentials['password']), 'entity: login with reset password');
$customCredentials = EntityAccounts::resetPassword($pdo, $teamId, 'secret12');
$assert(($customCredentials['password'] ?? '') === 'secret12', 'entity: custom password reset');
$assert(Auth::attempt($pdo, ['auth' => ['enabled' => true]], $customCredentials['username'], 'secret12'), 'entity: login with custom password');

// --- Report data ---
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$deskMapAdmin = $repo->deskMap();
$assert(count($deskMapAdmin['rows']) === 24, 'api: admin desks-map has 24 desks');
$report = (new ReportData($pdo))->build();
$assert(isset($report['teams'], $report['members'], $report['desks']), 'report: build succeeds');
$assert(count($report['teams']) >= 1, 'report: includes teams');

// --- Professional report builder ---
$builder = new ReportBuilder($pdo);
$catalog = $builder->catalog();
$assert(isset($catalog['types'], $catalog['periods'], $catalog['defaults']), 'reports: catalog shape');
$assert(count($catalog['types']) >= 8, 'reports: catalog has report types');
$monthly = $builder->build([
    'type' => 'finance',
    'period' => 'monthly',
    'fiscal_year' => '1405',
    'month' => 5,
    'team_id' => $teamId,
]);
$assert(($monthly['meta']['type'] ?? '') === 'finance', 'reports: finance monthly type');
$assert(($monthly['meta']['month_from'] ?? 0) === 5 && ($monthly['meta']['month_to'] ?? 0) === 5, 'reports: monthly range');
$assert(isset($monthly['finance_summary']['income_total'], $monthly['monthly_breakdown']), 'reports: finance sections');
$assert(!array_key_exists('formal_contract_total', $monthly['finance_summary']), 'reports: monthly omits formal contract total');
$assert((int) ($monthly['finance_summary']['manual_income'] ?? -1) === 0, 'reports: team filter zeros center manual income');
$assert((int) ($monthly['finance_summary']['expense_total'] ?? -1) === 0, 'reports: team filter zeros center expense');
$pdo->exec("INSERT INTO transactions (tx_date, description, amount, category, confirmed, notes)
            VALUES ('1405/05/10', 'درآمد مرکز تست گزارش', 777000, 'درآمد', 1, 'report-audit')");
$centerFinance = $builder->build([
    'type' => 'finance',
    'period' => 'monthly',
    'fiscal_year' => '1405',
    'month' => 5,
    'team_id' => 0,
]);
$assert((int) ($centerFinance['finance_summary']['manual_income'] ?? 0) >= 777000, 'reports: center report includes manual income');
$teamFinanceAgain = $builder->build([
    'type' => 'finance',
    'period' => 'monthly',
    'fiscal_year' => '1405',
    'month' => 5,
    'team_id' => $teamId,
]);
$assert((int) ($teamFinanceAgain['finance_summary']['manual_income'] ?? -1) === 0, 'reports: team filter still excludes center income');
$pdo->exec("INSERT INTO members (member_code, team_id, full_name, phone, approval_status, notes)
            VALUES ('REJ-1', {$teamId}, 'عضو رد شده گزارش', '09121112233', 'rejected', 'report-audit')");
$membersReport = $builder->build([
    'type' => 'members',
    'period' => 'annual',
    'fiscal_year' => '1405',
    'team_id' => $teamId,
]);
foreach ($membersReport['members'] as $memberRow) {
    $assert(($memberRow['approval_status'] ?? 'approved') === 'approved'
        || ($memberRow['approval_status'] ?? '') === '', 'reports: members export is approved-only');
    $assert(($memberRow['full_name'] ?? '') !== 'عضو رد شده گزارش', 'reports: rejected member excluded');
}
$quarterly = $builder->build([
    'type' => 'debts',
    'period' => 'quarterly',
    'fiscal_year' => '1405',
    'quarter' => 2,
]);
$assert(($quarterly['meta']['month_from'] ?? 0) === 4 && ($quarterly['meta']['month_to'] ?? 0) === 6, 'reports: quarterly summer months');
$assert(is_array($quarterly['debts'] ?? null), 'reports: debts section present');
$annual = $builder->build([
    'type' => 'full',
    'period' => 'annual',
    'fiscal_year' => '1405',
]);
$assert(in_array('teams', $annual['meta']['sections'] ?? [], true), 'reports: full includes teams');
$assert(isset($annual['transactions'], $annual['charges'], $annual['kpis']), 'reports: full payload');
$assert(array_key_exists('formal_contract_total', $annual['finance_summary'] ?? []), 'reports: annual includes formal contract total');
$assert((int) ($annual['finance_summary']['formal_contract_total'] ?? 0) >= 5000000, 'reports: annual formal contract sum');
$txKpi = null;
foreach ($annual['kpis'] as $kpi) {
    if (($kpi['label'] ?? '') === 'تعداد تراکنش') {
        $txKpi = $kpi;
        break;
    }
}
$assert(is_array($txKpi) && ($txKpi['format'] ?? '') === 'count', 'reports: transaction count KPI is count format');
$assert(ReportData::kpiValue(['value' => 12, 'format' => 'count']) === '12', 'reports: count KPI formats without money suffix');
$assert(ReportData::kpiValue(['value' => 1000, 'format' => 'money']) === '1,000', 'reports: money KPI formats number');
$assert(ReportData::kpiValue(['value' => 'سال مالی 1405', 'format' => 'text']) === 'سال مالی 1405', 'reports: text KPI stays plain');
$adminSummary = $repo->summary();
$assert(array_key_exists('formal_contract_year', $adminSummary['cards'] ?? []), 'summary: formal contract year card');
$assert((int) ($adminSummary['cards']['formal_contract_year'] ?? 0) >= 5000000, 'summary: formal contract year total');
$contractAmount = (int) $pdo->query("SELECT formal_contract_amount FROM team_contracts WHERE team_id = {$teamId} AND fiscal_year = '1405'")->fetchColumn();
$assert($contractAmount === 5000000, 'contracts: formal amount persisted');
$missingAmountFailed = false;
try {
    $crud->create('team_contracts', [
        'team_id' => (string) $teamId,
        'fiscal_year' => '1403',
        'contract_start' => '1403/01/01',
        'contract_end' => '1403/12/29',
    ]);
} catch (InvalidArgumentException) {
    $missingAmountFailed = true;
}
$assert($missingAmountFailed, 'contracts: formal amount required on create');

// --- Excel exporter ---
$exporter = new ExcelExporter($pdo);
$assert(method_exists($exporter, 'output'), 'export: ExcelExporter available');
$excelRef = new ReflectionClass($exporter);
$normalizeMethod = $excelRef->getMethod('normalizeFilters');
$normalizeMethod->setAccessible(true);
$workbookMethod = $excelRef->getMethod('workbookXml');
$workbookMethod->setAccessible(true);
$filtersProp = $excelRef->getProperty('filters');
$filtersProp->setAccessible(true);
$filtersProp->setValue($exporter, $normalizeMethod->invoke($exporter, [
    'fiscal_year' => '1405',
    'month_from' => 5,
    'month_to' => 5,
    'team_id' => $teamId,
]));
$excelXml = (string) $workbookMethod->invoke($exporter, ['summary', 'charges', 'transactions', 'members'], '1405/05/09');
$assert(str_contains($excelXml, 'بازه گزارش'), 'export: filtered summary includes period label');
$assert(str_contains($excelXml, 'Worksheet ss:Name="شارژ ماهانه"'), 'export: charges sheet present');
$assert(!str_contains($excelXml, 'عضو رد شده گزارش'), 'export: rejected members excluded from excel');
$isNumericMethod = $excelRef->getMethod('isNumericCell');
$isNumericMethod->setAccessible(true);
$assert($isNumericMethod->invoke($exporter, '09121112233', 'تماس') === false, 'export: phone header forces text cell');
$assert($isNumericMethod->invoke($exporter, '1234567890', '') === false, 'export: long digit strings stay text');
$assert($isNumericMethod->invoke($exporter, 1500000, 'مبلغ') === true, 'export: money ints stay numeric');
$assert($isNumericMethod->invoke($exporter, '12', 'شماره ماه') === true, 'export: short digit strings stay numeric');

// --- Database backup roundtrip ---
require_once dirname(__DIR__) . '/src/DatabaseBackup.php';
$backupRoomId = (int) ($pdo->query('SELECT id FROM meeting_rooms ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
$assert($backupRoomId > 0, 'backup: seeded meeting room exists');
$pdo->exec("INSERT INTO room_closed_days (closed_date, note, created_at)
            VALUES ('1405/05/20', 'تعطیل تست بک‌آپ', '1405/05/09')");
$pdo->exec("INSERT INTO room_reservations (
                room_id, reserved_date, start_time, end_time, duration_minutes,
                team_id, booker_name, booker_phone, status, source, public_token, submitted_at, created_at, updated_at
            ) VALUES (
                {$backupRoomId}, '1405/05/21', '10:00', '11:00', 60,
                {$teamId}, 'بک‌آپ', '09120009999', 'approved', 'admin', 'MN-BACKUP1', '1405/05/09', '1405/05/09', '1405/05/09'
            )");
$backupService = new DatabaseBackup($pdo);
$exportPayload = $backupService->export();
$assert(($exportPayload['format'] ?? '') === DatabaseBackup::FORMAT, 'backup: export format');
$assert(($exportPayload['counts']['teams'] ?? 0) >= 1, 'backup: export includes teams');
$assert((int) ($exportPayload['counts']['meeting_rooms'] ?? 0) >= 1, 'backup: export includes meeting_rooms');
$assert((int) ($exportPayload['counts']['room_reservations'] ?? 0) >= 1, 'backup: export includes room_reservations');
$assert((int) ($exportPayload['counts']['room_closed_days'] ?? 0) >= 1, 'backup: export includes room_closed_days');
$roundtripDb = dirname(__DIR__) . '/data/integration_backup_roundtrip.sqlite3';
if (is_file($roundtripDb)) {
    unlink($roundtripDb);
}
$roundtripPdo = new PDO('sqlite:' . $roundtripDb);
$roundtripPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Schema::migrate($roundtripPdo);
$roundtripBackup = new DatabaseBackup($roundtripPdo);
$imported = $roundtripBackup->import($exportPayload);
$assert(($imported['teams'] ?? 0) >= 1, 'backup: restore imports teams');
$assert(($imported['room_reservations'] ?? 0) >= 1, 'backup: restore imports room_reservations');
$assert(($imported['room_closed_days'] ?? 0) >= 1, 'backup: restore imports room_closed_days');
$restoredTeamCount = (int) $roundtripPdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
$assert($restoredTeamCount === (int) ($exportPayload['counts']['teams'] ?? 0), 'backup: restored team count matches export');
$restoredRoomReservations = (int) $roundtripPdo->query('SELECT COUNT(*) FROM room_reservations')->fetchColumn();
$assert($restoredRoomReservations === (int) ($exportPayload['counts']['room_reservations'] ?? 0), 'backup: restored room reservations match export');
$schemaVersion = (int) $roundtripPdo->query('SELECT schema_version FROM center_settings WHERE id = 1')->fetchColumn();
$assert($schemaVersion === Schema::VERSION, 'backup: restore pins schema_version to current');

// Old backup without room tables must not wipe live rooms on restore.
$legacyPayload = $exportPayload;
unset($legacyPayload['tables']['meeting_rooms'], $legacyPayload['tables']['room_closed_days'], $legacyPayload['tables']['room_reservations'], $legacyPayload['counts']['meeting_rooms'], $legacyPayload['counts']['room_closed_days'], $legacyPayload['counts']['room_reservations']);
$legacyDb = dirname(__DIR__) . '/data/integration_backup_legacy.sqlite3';
if (is_file($legacyDb)) {
    unlink($legacyDb);
}
$legacyPdo = new PDO('sqlite:' . $legacyDb);
$legacyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
Schema::migrate($legacyPdo);
$legacyPdo->exec("INSERT INTO meeting_rooms (name, code, capacity, open_time, close_time, slot_minutes, is_active, created_at, updated_at)
                  VALUES ('اتاق زنده', 'LIVE-1', 4, '08:00', '20:00', 60, 1, '1405/01/01', '1405/01/01')");
$liveRoomBefore = (int) $legacyPdo->query('SELECT COUNT(*) FROM meeting_rooms')->fetchColumn();
$assert($liveRoomBefore >= 1, 'backup: legacy target has live rooms before restore');
(new DatabaseBackup($legacyPdo))->import($legacyPayload);
$liveRoomAfter = (int) $legacyPdo->query('SELECT COUNT(*) FROM meeting_rooms')->fetchColumn();
$assert($liveRoomAfter === $liveRoomBefore, 'backup: legacy restore preserves missing room tables');
$legacyTeams = (int) $legacyPdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
$assert($legacyTeams === (int) ($exportPayload['counts']['teams'] ?? 0), 'backup: legacy restore still replaces teams');
// --- Delete team cascades related finance/member data ---
$userBefore = (int) $pdo->query('SELECT COUNT(*) FROM panel_users WHERE team_id = ' . $teamId)->fetchColumn();
$assert($userBefore === 1, 'entity: one portal user per team');
$membersBefore = (int) $pdo->query('SELECT COUNT(*) FROM members WHERE team_id = ' . $teamId)->fetchColumn();
$chargesBefore = (int) $pdo->query('SELECT COUNT(*) FROM charges WHERE team_id = ' . $teamId)->fetchColumn();
$txBefore = (int) $pdo->query('SELECT COUNT(*) FROM transactions WHERE team_id = ' . $teamId)->fetchColumn();
$assert($membersBefore >= 1, 'entity: team has members before cascade delete');
$assert($chargesBefore >= 1, 'entity: team has charges before cascade delete');
$assert($txBefore >= 1, 'entity: team has transactions before cascade delete');
$pdo->exec("INSERT INTO meeting_rooms (name, code, capacity, open_time, close_time, slot_minutes, is_active, created_at, updated_at)
            VALUES ('اتاق تست', 'R-TEST', 6, '08:00', '20:00', 60, 1, '1405/01/01', '1405/01/01')");
$roomId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO room_reservations (
                room_id, reserved_date, start_time, end_time, duration_minutes,
                team_id, booker_name, booker_phone, status, source, public_token, submitted_at, created_at, updated_at
            ) VALUES (
                {$roomId}, '1405/08/01', '10:00', '11:00', 60,
                {$teamId}, 'تست', '09120000000', 'pending', 'team', 'MN-TEST01', '1405/01/01', '1405/01/01', '1405/01/01'
            )");
$reservationsBefore = (int) $pdo->query('SELECT COUNT(*) FROM room_reservations WHERE team_id = ' . $teamId)->fetchColumn();
$assert($reservationsBefore >= 1, 'entity: team has room reservation before cascade delete');
$crud->delete('teams', $teamId);
$userAfter = (int) $pdo->query('SELECT COUNT(*) FROM panel_users WHERE team_id = ' . $teamId)->fetchColumn();
$membersAfter = (int) $pdo->query('SELECT COUNT(*) FROM members WHERE team_id = ' . $teamId)->fetchColumn();
$chargesAfter = (int) $pdo->query('SELECT COUNT(*) FROM charges WHERE team_id = ' . $teamId)->fetchColumn();
$txAfter = (int) $pdo->query('SELECT COUNT(*) FROM transactions WHERE team_id = ' . $teamId)->fetchColumn();
$contractsAfter = (int) $pdo->query('SELECT COUNT(*) FROM team_contracts WHERE team_id = ' . $teamId)->fetchColumn();
$desksAfter = (int) $pdo->query('SELECT COUNT(*) FROM desks WHERE team_id = ' . $teamId)->fetchColumn();
$reservationsAfter = (int) $pdo->query('SELECT COUNT(*) FROM room_reservations WHERE team_id = ' . $teamId)->fetchColumn();
$assert($userAfter === 0, 'entity: portal user deleted with team');
$assert($membersAfter === 0, 'entity: members deleted with team');
$assert($chargesAfter === 0, 'entity: charges deleted with team');
$assert($txAfter === 0, 'entity: transactions deleted with team');
$assert($contractsAfter === 0, 'entity: contracts deleted with team');
$assert($desksAfter === 0, 'entity: desks released when team deleted');
$assert($reservationsAfter === 0, 'entity: room reservations deleted with team');

// --- Charge uniqueness / dedupe ---
$uniqTeam = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد یکتایی شارژ',
    'leader' => 'تست',
    'phone' => '09120001111',
    'joined_at' => '1405/01/01',
]);
$uniqTeamId = (int) $uniqTeam['id'];
$crud->create('charges', [
    'team_id' => (string) $uniqTeamId,
    'fiscal_year' => '1405',
    'month_index' => '3',
    'charge_amount' => '1000',
    'rent_amount' => '0',
    'amount' => '1000',
]);
$crud->create('charges', [
    'team_id' => (string) $uniqTeamId,
    'fiscal_year' => '1405',
    'month_index' => '3',
    'charge_amount' => '1500',
    'rent_amount' => '0',
    'amount' => '1500',
]);
$chargeCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM charges WHERE team_id = {$uniqTeamId} AND fiscal_year = '1405' AND month_index = 3"
)->fetchColumn();
$chargeAmount = (int) $pdo->query(
    "SELECT amount FROM charges WHERE team_id = {$uniqTeamId} AND fiscal_year = '1405' AND month_index = 3"
)->fetchColumn();
$assert($chargeCount === 1, 'charges: upsert keeps one row per team/month');
$assert($chargeAmount === 1500, 'charges: upsert updates amount instead of duplicating');
$indexExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'uniq_charges_team_year_month'"
)->fetchColumn();
$assert($indexExists === 1, 'schema: unique charge index exists');

// Partial charge update must keep rent when only charge_amount changes.
$crud->update('charges', (int) $pdo->query(
    "SELECT id FROM charges WHERE team_id = {$uniqTeamId} AND fiscal_year = '1405' AND month_index = 3"
)->fetchColumn(), [
    'charge_amount' => '2000',
    'rent_amount' => '500',
    'amount' => '2500',
]);
$partialId = (int) $pdo->query(
    "SELECT id FROM charges WHERE team_id = {$uniqTeamId} AND fiscal_year = '1405' AND month_index = 3"
)->fetchColumn();
$crud->update('charges', $partialId, ['charge_amount' => '2200']);
$partial = $pdo->query("SELECT charge_amount, rent_amount, amount FROM charges WHERE id = {$partialId}")->fetch();
$assert((int) ($partial['charge_amount'] ?? 0) === 2200, 'charges: partial update keeps new charge');
$assert((int) ($partial['rent_amount'] ?? 0) === 500, 'charges: partial update preserves rent');
$assert((int) ($partial['amount'] ?? 0) === 2700, 'charges: partial update recomputes amount from charge+rent');

// Admin deposit pins allocation to selected month via payment_plan.
$crud->create('team_contracts', [
    'team_id' => (string) $uniqTeamId,
    'fiscal_year' => '1405',
    'contract_start' => '1405/01/01',
    'contract_end' => '1405/12/29',
    'formal_contract_amount' => '1000000',
]);
$adminDeposit = $crud->create('transactions', [
    'tx_date' => '1405/03/15',
    'category' => 'واریز تیم',
    'team_id' => (string) $uniqTeamId,
    'fiscal_year' => '1405',
    'month_index' => '3',
    'amount' => '2700',
    'description' => 'دریافت شارژ خرداد',
    'confirmed' => '1',
]);
$assert(($adminDeposit['payment_status'] ?? '') === 'approved', 'admin deposit: auto-approved');
$planRaw = (string) ($pdo->query('SELECT payment_plan FROM transactions WHERE id = ' . (int) $adminDeposit['id'])->fetchColumn() ?: '');
$planDecoded = json_decode($planRaw, true);
$assert(is_array($planDecoded) && (int) ($planDecoded[0]['month_index'] ?? 0) === 3, 'admin deposit: payment_plan targets selected month');

// Sequential desk segments in one year must both bill (legacy/multi-row data).
$segTeam = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد سگمنت میز',
    'leader' => 'تست',
    'phone' => '09120002222',
    'joined_at' => '1405/01/01',
]);
$segTeamId = (int) $segTeam['id'];
$crud->create('team_contracts', [
    'team_id' => (string) $segTeamId,
    'fiscal_year' => '1405',
    'contract_start' => '1405/01/01',
    'contract_end' => '1405/12/29',
    'formal_contract_amount' => '2000000',
]);
$segDeskId = (int) ($pdo->query('SELECT id FROM desks WHERE number = 5')->fetchColumn() ?: 0);
$pdo->exec('DELETE FROM desk_assignments WHERE desk_id = ' . $segDeskId);
$pdo->exec(
    "INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until)
     VALUES ({$segDeskId}, 5, {$segTeamId}, 'formal', '1405/01/01', '" . JalaliDate::monthEnd('1405', 6) . "')"
);
$pdo->exec(
    "INSERT INTO desk_assignments (desk_id, desk_number, team_id, usage_type, assigned_from, assigned_until)
     VALUES ({$segDeskId}, 5, {$segTeamId}, 'formal', '" . JalaliDate::monthStart('1405', 7) . "', '" . JalaliDate::monthEnd('1405', 12) . "')"
);
$segMonths = (new Seeder($pdo))->monthlyAmountsForTeam($segTeamId, '1405');
$assert(isset($segMonths[1], $segMonths[7]), 'billing: sequential desk segments bill early and late months');
$assert((int) ($segMonths[1]['amount'] ?? 0) > 0, 'billing: early segment charged');
$assert((int) ($segMonths[7]['amount'] ?? 0) > 0, 'billing: late segment charged');

// Bulk CSV desk_numbers path creates assignments.
$bulkTeam = $crud->create('teams', [
    'entity_type' => 'company',
    'name' => 'نهاد CSV میز',
    'leader' => 'تست',
    'phone' => '09120003333',
    'joined_at' => '1404/01/01',
]);
$bulk = (new YearBackfill($pdo, $crud))->import([
    'fiscal_year' => '1404',
    'recalculate' => false,
    'rows' => [[
        'team_name' => 'نهاد CSV میز',
        'contract_start' => '1404/01/01',
        'contract_end' => '1404/12/29',
        'formal_contract_amount' => '9000000',
        'desk_numbers' => '6,7',
    ]],
]);
$assert((int) ($bulk['imported'] ?? 0) === 1, 'bulk import: team imported');
$assert((int) ($bulk['results'][0]['desk_assignments'] ?? 0) === 2, 'bulk import: desk_numbers parsed into assignments');
$bulkAssignCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM desk_assignments WHERE team_id = ' . (int) $bulkTeam['id']
)->fetchColumn();
$assert($bulkAssignCount === 2, 'bulk import: two desk assignments persisted');

// Mid-year desk handoff must keep prior team segment and clear its ghost charges after reassign.
$handA = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد واگذاری الف',
    'leader' => 'الف',
    'phone' => '09120004444',
    'joined_at' => '1405/01/01',
]);
$handB = $crud->create('teams', [
    'entity_type' => 'team',
    'name' => 'نهاد واگذاری ب',
    'leader' => 'ب',
    'phone' => '09120005555',
    'joined_at' => '1405/01/01',
]);
$handAId = (int) $handA['id'];
$handBId = (int) $handB['id'];
foreach ([$handAId, $handBId] as $hid) {
    $crud->create('team_contracts', [
        'team_id' => (string) $hid,
        'fiscal_year' => '1405',
        'contract_start' => '1405/01/01',
        'contract_end' => '1405/12/29',
        'formal_contract_amount' => '3000000',
    ]);
}
$handDeskId = (int) ($pdo->query('SELECT id FROM desks WHERE number = 8')->fetchColumn() ?: 0);
$pdo->exec('DELETE FROM desk_assignments WHERE desk_id = ' . $handDeskId);
$handFirst = $crud->create('desk_assignments', [
    'desk_id' => (string) $handDeskId,
    'team_id' => (string) $handAId,
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '1',
    'assigned_until_month' => '6',
]);
$handSecond = $crud->create('desk_assignments', [
    'desk_id' => (string) $handDeskId,
    'team_id' => (string) $handBId,
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '7',
    'assigned_until_month' => '12',
]);
$assert((int) ($handFirst['id'] ?? 0) > 0 && (int) ($handSecond['id'] ?? 0) > 0, 'handoff: both segments created');
$assert((int) ($handFirst['id'] ?? 0) !== (int) ($handSecond['id'] ?? 0), 'handoff: second segment is a new row');
$handCount = (int) $pdo->query('SELECT COUNT(*) FROM desk_assignments WHERE desk_id = ' . $handDeskId)->fetchColumn();
$assert($handCount === 2, 'handoff: non-overlapping segments kept after reconcile');
(new Seeder($pdo))->recalculateChargesForTeam($handAId, '1405');
(new Seeder($pdo))->recalculateChargesForTeam($handBId, '1405');
$handAMonths = (new Seeder($pdo))->monthlyAmountsForTeam($handAId, '1405');
$handBMonths = (new Seeder($pdo))->monthlyAmountsForTeam($handBId, '1405');
$assert(isset($handAMonths[1], $handAMonths[6]) && !isset($handAMonths[7]), 'handoff: team A bills early months only');
$assert(isset($handBMonths[7], $handBMonths[12]) && !isset($handBMonths[1]), 'handoff: team B bills late months only');

// Deleting a dated desk assignment must free desks.team_id when no other current row remains.
$freeDeskId = (int) ($pdo->query('SELECT id FROM desks WHERE number = 9')->fetchColumn() ?: 0);
$pdo->exec('DELETE FROM desk_assignments WHERE desk_id = ' . $freeDeskId);
$freeAssign = $crud->create('desk_assignments', [
    'desk_id' => (string) $freeDeskId,
    'team_id' => (string) $handAId,
    'usage_type' => 'formal',
    'fiscal_year' => '1405',
    'assigned_from_month' => '1',
    'assigned_until_month' => '12',
]);
$deskBeforeDelete = $crud->find('desks', $freeDeskId);
$assert((int) ($deskBeforeDelete['team_id'] ?? 0) === $handAId, 'desk delete: desk owned before assignment delete');
$crud->delete('desk_assignments', (int) $freeAssign['id']);
$deskAfterDelete = $crud->find('desks', $freeDeskId);
$assert((int) ($deskAfterDelete['team_id'] ?? 0) === 0, 'desk delete: dated assignment delete frees desk map');

// Admin update of team deposit must not collapse multi-month payment_plan or re-approve.
$multiPlanTx = $crud->create('transactions', [
    'tx_date' => '1405/04/01',
    'category' => 'واریز تیم',
    'team_id' => (string) $handAId,
    'fiscal_year' => '1405',
    'month_index' => '1',
    'amount' => '600',
    'description' => 'دریافت تست چندماهه',
    'confirmed' => '1',
]);
$pdo->prepare('UPDATE transactions SET payment_plan = :plan WHERE id = :id')->execute([
    'plan' => json_encode([
        ['fiscal_year' => '1405', 'month_index' => 1, 'amount' => 300],
        ['fiscal_year' => '1405', 'month_index' => 3, 'amount' => 300],
    ], JSON_UNESCAPED_UNICODE),
    'id' => (int) $multiPlanTx['id'],
]);
$pdo->prepare("UPDATE transactions SET payment_status = 'rejected', confirmed = 0 WHERE id = :id")
    ->execute(['id' => (int) $multiPlanTx['id']]);
$crud->update('transactions', (int) $multiPlanTx['id'], [
    'notes' => 'ویرایش یادداشت',
    'amount' => '600',
    'fiscal_year' => '1405',
    'month_index' => '2',
    'category' => 'واریز تیم',
    'team_id' => (string) $handAId,
    'tx_date' => '1405/04/01',
    'description' => 'دریافت تست چندماهه',
]);
$afterEdit = $pdo->query('SELECT payment_status, confirmed, payment_plan, notes FROM transactions WHERE id = ' . (int) $multiPlanTx['id'])->fetch();
$assert(($afterEdit['payment_status'] ?? '') === 'rejected', 'admin tx update: does not re-approve rejected deposit');
$assert((int) ($afterEdit['confirmed'] ?? 1) === 0, 'admin tx update: keeps confirmed=0 on rejected');
$planAfter = json_decode((string) ($afterEdit['payment_plan'] ?? ''), true);
$assert(is_array($planAfter) && count($planAfter) === 2, 'admin tx update: keeps multi-month payment_plan');
$assert((int) ($planAfter[0]['month_index'] ?? 0) === 1 && (int) ($planAfter[1]['month_index'] ?? 0) === 3, 'admin tx update: plan months unchanged');
$assert(($afterEdit['notes'] ?? '') === 'ویرایش یادداشت', 'admin tx update: notes still save');

// Public booking must ignore client-supplied team_id.
$rooms = new RoomReservations($pdo);
$publicRoomId = (int) ($pdo->query('SELECT id FROM meeting_rooms ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
$publicDate = JalaliDate::addDays(JalaliDate::todayParts()['formatted'], 2);
$publicBooking = $rooms->createFromPayload([
    'room_id' => $publicRoomId,
    'reserved_date' => $publicDate,
    'start_time' => '10:00',
    'end_time' => '11:00',
    'booker_name' => 'مهمان',
    'booker_phone' => '09121230000',
    'team_id' => (string) $handAId,
    'member_id' => '999',
], 'public');
$assert(($publicBooking['team_id'] ?? null) === null || (int) ($publicBooking['team_id'] ?? 0) === 0, 'public booking: team_id ignored');
$assert(($publicBooking['member_id'] ?? null) === null || (int) ($publicBooking['member_id'] ?? 0) === 0, 'public booking: member_id ignored');

// Second locker request cannot reuse an already assigned locker.
$_SESSION = [
    'mechinno_authenticated' => true,
    'mechinno_role' => Access::ROLE_TEAM,
    'mechinno_team_id' => $handAId,
    'mechinno_user' => 'handa',
    'mechinno_user_id' => 1,
];
$firstLockerReq = $crud->create('locker_requests', ['notes' => 'کمد اول']);
$_SESSION['mechinno_team_id'] = $handBId;
$_SESSION['mechinno_user'] = 'handb';
$secondLockerReq = $crud->create('locker_requests', ['notes' => 'کمد تکراری']);
$_SESSION['mechinno_role'] = Access::ROLE_ADMIN_EDITOR;
$workflowLocker = new Workflow($pdo);
$approvedFirstLocker = $workflowLocker->approveLockerRequest((int) $firstLockerReq['id'], 15);
$assert(($approvedFirstLocker['status'] ?? '') === 'approved', 'workflow: first locker approve ok');
$lockerDupFailed = false;
try {
    $workflowLocker->approveLockerRequest((int) $secondLockerReq['id'], 15);
} catch (InvalidArgumentException) {
    $lockerDupFailed = true;
}
$assert($lockerDupFailed, 'workflow: cannot approve second request onto occupied locker');
$pdo->exec("UPDATE locker_requests SET status = 'rejected', reviewed_at = '1405/05/09' WHERE team_id IN ({$handAId}, {$handBId}) AND status = 'pending'");
$pdo->exec("UPDATE transactions SET payment_status = 'rejected', confirmed = 0 WHERE team_id IN ({$handAId}, {$handBId}) AND payment_status = 'pending'");

$crud->delete('teams', $segTeamId);
$crud->delete('teams', (int) $bulkTeam['id']);
$crud->delete('teams', $handAId);
$crud->delete('teams', $handBId);
$crud->delete('teams', $uniqTeamId);

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
