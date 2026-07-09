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
$plainPassword = (string) $pdo->query("SELECT password_plain FROM panel_users WHERE team_id = {$teamId}")->fetchColumn();
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
$assert(count($duplicateAmounts) === 3, 'charges: duplicate desk rows still charge only assigned months');
$assert(($duplicateAmounts[5]['charge_amount'] ?? 0) === 300, 'charges: duplicate rows count one desk per month');
(new Seeder($pdo))->recalculateCharges('1405');
$afterGlobalRecalc = (new Seeder($pdo))->monthlyAmountsForTeam($teamId, '1405');
$assert(($afterGlobalRecalc[5]['charge_amount'] ?? 0) === 300, 'charges: global recalc keeps single-desk amounts');
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

// --- Excel exporter ---
$exporter = new ExcelExporter($pdo);
$assert(method_exists($exporter, 'output'), 'export: ExcelExporter available');

// --- Database backup roundtrip ---
require_once dirname(__DIR__) . '/src/DatabaseBackup.php';
$backupService = new DatabaseBackup($pdo);
$exportPayload = $backupService->export();
$assert(($exportPayload['format'] ?? '') === DatabaseBackup::FORMAT, 'backup: export format');
$assert(($exportPayload['counts']['teams'] ?? 0) >= 1, 'backup: export includes teams');
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
$restoredTeamCount = (int) $roundtripPdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
$assert($restoredTeamCount === (int) ($exportPayload['counts']['teams'] ?? 0), 'backup: restored team count matches export');

// --- Delete team cascades related finance/member data ---
$userBefore = (int) $pdo->query('SELECT COUNT(*) FROM panel_users WHERE team_id = ' . $teamId)->fetchColumn();
$assert($userBefore === 1, 'entity: one portal user per team');
$membersBefore = (int) $pdo->query('SELECT COUNT(*) FROM members WHERE team_id = ' . $teamId)->fetchColumn();
$chargesBefore = (int) $pdo->query('SELECT COUNT(*) FROM charges WHERE team_id = ' . $teamId)->fetchColumn();
$txBefore = (int) $pdo->query('SELECT COUNT(*) FROM transactions WHERE team_id = ' . $teamId)->fetchColumn();
$assert($membersBefore >= 1, 'entity: team has members before cascade delete');
$assert($chargesBefore >= 1, 'entity: team has charges before cascade delete');
$assert($txBefore >= 1, 'entity: team has transactions before cascade delete');
$crud->delete('teams', $teamId);
$userAfter = (int) $pdo->query('SELECT COUNT(*) FROM panel_users WHERE team_id = ' . $teamId)->fetchColumn();
$membersAfter = (int) $pdo->query('SELECT COUNT(*) FROM members WHERE team_id = ' . $teamId)->fetchColumn();
$chargesAfter = (int) $pdo->query('SELECT COUNT(*) FROM charges WHERE team_id = ' . $teamId)->fetchColumn();
$txAfter = (int) $pdo->query('SELECT COUNT(*) FROM transactions WHERE team_id = ' . $teamId)->fetchColumn();
$contractsAfter = (int) $pdo->query('SELECT COUNT(*) FROM team_contracts WHERE team_id = ' . $teamId)->fetchColumn();
$desksAfter = (int) $pdo->query('SELECT COUNT(*) FROM desks WHERE team_id = ' . $teamId)->fetchColumn();
$assert($userAfter === 0, 'entity: portal user deleted with team');
$assert($membersAfter === 0, 'entity: members deleted with team');
$assert($chargesAfter === 0, 'entity: charges deleted with team');
$assert($txAfter === 0, 'entity: transactions deleted with team');
$assert($contractsAfter === 0, 'entity: contracts deleted with team');
$assert($desksAfter === 0, 'entity: desks released when team deleted');

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
