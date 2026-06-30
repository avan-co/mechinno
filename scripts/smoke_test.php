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

$pdo->exec("INSERT INTO teams (entity_type, entity_code, name, leader, phone, source_file, source_sheet)
            VALUES ('company', 'C-001', 'آوان', 'مهدی', '09398283658', 'manual', 'panel')");
EntityAccounts::provisionForTeam($pdo, 1, 'C-001', 'مهدی');
$teamsPage = $repo->paginatedResource('teams', 1, 25);
$assert(($teamsPage['rows'][0]['portal_username'] ?? '') === 'c001', 'team portal username generated');
$assert(EntityAccounts::usernameForCode('C-001') === 'c001', 'usernameForCode keeps letters');
$assert(EntityAccounts::usernameForCode('T-012') === 't012', 'usernameForCode normalizes codes');
$assert(($teamsPage['rows'][0]['portal_password'] ?? '') !== '', 'team portal password visible to admin');

$pdo->exec('UPDATE desks SET team_id = 1, usage_type = "mixed", formal_seats = 1, informal_seats = 1 WHERE number = 1');

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

$teamMeta = $crud->meta();
$assert(!isset($teamMeta['resources']['panel_users']), 'team crud meta excludes panel_users');
$assert(!isset($teamMeta['resources']['transactions']), 'team crud meta excludes transactions');
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
