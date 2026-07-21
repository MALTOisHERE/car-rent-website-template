<?php

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/http.php';
require_once __DIR__ . '/../app/validation.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/domain.php';
require_once __DIR__ . '/../app/i18n.php';
require_once __DIR__ . '/../app/vehicle_service.php';

$failures = [];
$assert = function ($condition, $message) use (&$failures) {
    if (!$condition) $failures[] = $message;
};

$assert(validDateValue('2026-02-28') instanceof DateTimeImmutable, 'Valid date rejected');
$assert(validDateValue('2026-02-30') === null, 'Invalid date accepted');
$assert(passwordValidationErrors('weak') !== [], 'Weak password accepted');
$assert(passwordValidationErrors('StrongDemo!2026') === [], 'Strong password rejected');
$assert(in_array('confirmed', reservationTransitions()['pending'], true), 'Pending cannot transition to confirmed');
$assert(!in_array('active', reservationTransitions()['cancelled'], true), 'Cancelled reservation can transition');
$assert(vehicleDetailTab('media') === 'media' && vehicleDetailTab('../finance') === 'overview', 'Vehicle detail tab allowlist failed');
$profileFixture = ['current_mileage'=>50000];
$profileInput = function ($financingType, $monthlyAmount = '') {
    return [
        'category_id'=>'1','registration_number'=>' ab-123 ','vin'=>'vin123','brand'=>'Dacia','model'=>'Duster',
        'model_year'=>(string)date('Y'),'seats'=>'5','doors'=>'5','luggage_capacity'=>'4','current_mileage'=>'50001',
        'mileage_allowance'=>'200','purchase_date'=>date('Y-m-d'),'purchase_price'=>'120000.00','financing_type'=>$financingType,
        'monthly_finance_amount'=>$monthlyAmount,'base_daily_price'=>'450.00','recommended_deposit'=>'1500.00','fuel'=>'diesel','transmission'=>'manual',
    ];
};
$expectInvalidFinancing = function (array $input, $message) use ($profileFixture, $assert) {
    try {
        validatedVehicleProfile($input, $profileFixture);
        $assert(false, $message);
    } catch (InvalidArgumentException $exception) {
        $assert(true, $message);
    }
};

$assert(vehicleFinancingTypeForDisplay(null)==='owned' && vehicleFinancingTypeForDisplay('')==='owned', 'Legacy NULL/empty financing does not render as owned');
$assert(vehicleFinancingTypeForDisplay('loan')==='loan' && vehicleFinancingTypeForDisplay('invalid')==='owned', 'Financing display normalization failed');

try {
    $validatedProfile = validatedVehicleProfile($profileInput('owned','999.00'), $profileFixture);
    $assert($validatedProfile['registration_number']==='AB-123' && $validatedProfile['vin']==='VIN123', 'Vehicle identifiers were not normalized');
    $assert($validatedProfile['monthly_finance_amount']===null, 'Owned vehicle retained a monthly finance amount');
} catch (Throwable $exception) { $assert(false, 'Valid vehicle profile was rejected: '.$exception->getMessage()); }
$expectInvalidFinancing($profileInput(''), 'Empty financing type was accepted');
$expectInvalidFinancing($profileInput('invalid'), 'Invalid financing type was accepted');
$expectInvalidFinancing($profileInput('loan'), 'Loan without a monthly amount was accepted');
$expectInvalidFinancing($profileInput('loan','0'), 'Loan with a zero monthly amount was accepted');
$expectInvalidFinancing($profileInput('loan','-10'), 'Loan with a negative monthly amount was accepted');
$expectInvalidFinancing($profileInput('loan','invalid'), 'Loan with an invalid monthly amount was accepted');
$expectInvalidFinancing($profileInput('lease'), 'Lease without a monthly amount was accepted');
$expectInvalidFinancing($profileInput('lease','0.00'), 'Lease with a zero monthly amount was accepted');
$expectInvalidFinancing($profileInput('lease','-10'), 'Lease with a negative monthly amount was accepted');
$expectInvalidFinancing($profileInput('lease','invalid'), 'Lease with an invalid monthly amount was accepted');
foreach (['loan','lease'] as $financingType) {
    try {
        $valid = validatedVehicleProfile($profileInput($financingType,'625.50'), $profileFixture);
        $assert($valid['financing_type']===$financingType && $valid['monthly_finance_amount']==='625.50', ucfirst($financingType).' with a positive monthly amount failed');
    } catch (Throwable $exception) { $assert(false, ucfirst($financingType).' with a positive monthly amount was rejected'); }
}
$mileageInput=$profileInput('owned');$mileageInput['current_mileage']='49999';
try { validatedVehicleProfile($mileageInput,$profileFixture);$assert(false,'Mileage decrease without a correction reason was accepted'); }
catch (InvalidArgumentException $exception) { $assert(true,'Mileage correction validation active'); }

$price = calculateRentalPrice([
    'pickup_at' => new DateTimeImmutable('2026-01-01 10:00:00'),
    'return_at' => new DateTimeImmutable('2026-01-04 10:00:00'),
    'daily_price' => '200.00',
    'options_total' => '50.00',
    'fees_total' => '0.00',
    'discount_percent' => 10,
    'tax_rate' => 20,
]);
$assert($price['days'] === 3, 'Rental day calculation failed');
$assert($price['total'] === '702.00', 'Price breakdown failed');

$protectedAreas = [
    'dashboard.view', 'agencies.view', 'users.manage', 'customers.manage',
    'vehicles.manage', 'reservations.manage', 'contracts.manage', 'payments.create',
    'payments.manage', 'invoices.manage', 'expenses.manage', 'inspections.manage',
    'maintenance.manage', 'pricing.manage', 'reports.view', 'reports.financial',
    'vehicle_documents.manage', 'vehicles.view',
];

$_SESSION['role'] = ROLE_OWNER;
foreach ($protectedAreas as $permission) {
    $assert(can($permission), "Owner cannot access protected area: $permission");
}
$assert(canCreateAgency(), 'Owner cannot see the agency creation action');
$assert(canViewFinancialDashboard(), 'Owner cannot see financial dashboard metrics');
$assert(canViewFinanceHistory(), 'Owner cannot see payment history and deposits');
$assert(canViewInvoiceSections(), 'Owner cannot see invoice sections');

$_SESSION['role'] = ROLE_AGENCY_MANAGER;
$assert(can('pricing.manage'), 'Agency manager cannot manage pricing rules');
$assert(can('payments.manage'), 'Agency manager cannot manage finance');
$assert(!canCreateAgency(), 'Agency manager can see the owner-only agency creation action');
$assert(canViewFinancialDashboard(), 'Agency manager cannot see financial dashboard metrics');
$assert(canViewFinanceHistory(), 'Agency manager cannot see payment history and deposits');
$assert(canViewInvoiceSections(), 'Agency manager cannot see invoice sections');

$_SESSION['role'] = ROLE_RENTAL_AGENT;
$assert(can('payments.create'), 'Rental agent cannot create payments');
$assert(!can('payments.manage'), 'Rental agent can manage finance');
$assert(!can('pricing.manage'), 'Rental agent can manage pricing rules');
$assert(!canCreateAgency(), 'Rental agent can see the owner-only agency creation action');
$assert(!canViewFinancialDashboard(), 'Rental agent can see financial dashboard metrics');
$assert(!canViewFinanceHistory(), 'Rental agent can see payment history or deposits');
$assert(!canViewInvoiceSections(), 'Rental agent can see invoice sections');

$_SESSION['role'] = ROLE_ACCOUNTANT;
$assert(can('payments.manage'), 'Accountant cannot manage finance');
$assert(can('reports.view'), 'Accountant cannot access reports');
$assert(can('reports.financial'), 'Accountant cannot access financial reports');
$assert(!can('pricing.manage'), 'Accountant can manage pricing rules');
$assert(!canCreateAgency(), 'Accountant can see the owner-only agency creation action');
$assert(canViewFinancialDashboard(), 'Accountant cannot see financial dashboard metrics');
$assert(canViewFinanceHistory(), 'Accountant cannot see payment history and deposits');
$assert(canViewInvoiceSections(), 'Accountant cannot see invoice sections');

$_SESSION['role'] = ROLE_FLEET_AGENT;
$assert(!can('payments.create'), 'Fleet agent can create payments');
$assert(!can('payments.manage'), 'Fleet agent can manage finance');
$assert(!can('pricing.manage'), 'Fleet agent can manage pricing rules');
$assert(!canCreateAgency(), 'Fleet agent can see the owner-only agency creation action');
$assert(!canViewFinancialDashboard(), 'Fleet agent can see financial dashboard metrics');
$assert(!canViewFinanceHistory(), 'Fleet agent can see payment history or deposits');
$assert(!canViewInvoiceSections(), 'Fleet agent can see invoice sections');
unset($_SESSION['role']);

$requiredTranslationKeys = [
    'common.save','common.cancel','common.confirm','common.actions','shell.sign_out',
    'shell.open_navigation','nav.dashboard','nav.reservations','nav.customers',
    'nav.vehicles','nav.finance','nav.reports','role.owner','role.agency_manager',
    'role.rental_agent','role.accountant','role.fleet_agent','role.customer',
    'status.active','status.inactive','status.available','status.reserved','status.rented',
    'status.returned','status.pending','status.completed','status.cancelled','status.approved',
    'status.rejected','status.paid','status.failed','status.draft','status.generated',
    'status.sent','status.signed','status.expired','status.maintenance','status.damaged',
    'status.blocked','status.scheduled','status.in_progress','status.no_show',
    'validation.financing_type',
];
$catalogues = [];
foreach (supportedLanguages() as $languageCode) {
    $catalogues[$languageCode] = translationCatalogue($languageCode);
    foreach ($requiredTranslationKeys as $key) {
        $assert(isset($catalogues[$languageCode][$key]) && is_string($catalogues[$languageCode][$key]) && $catalogues[$languageCode][$key] !== '', "$languageCode translation missing: $key");
    }
    foreach ($catalogues[$languageCode] as $key => $value) {
        $assert(is_string($value), "$languageCode translation is not a string: $key");
    }
}
$assert(array_keys($catalogues['en']) === array_keys($catalogues['fr']), 'French catalogue keys differ from English');
$assert(array_keys($catalogues['en']) === array_keys($catalogues['ar']), 'Arabic catalogue keys differ from English');

$_SESSION['lang'] = 'en';
$assert(t('message.contract_generated', ['id'=>42]) === 'Contract generated: #42', 'Translation placeholder interpolation failed');
$assert(t('missing.deep_key') === 'Deep key', 'Missing translation key did not produce a readable fallback');
$assert(e(t('message.contract_generated', ['id'=>'<script>alert(1)</script>'])) === 'Contract generated: #&lt;script&gt;alert(1)&lt;/script&gt;', 'Rendered translation parameter was not escaped safely');
$assert(translatedRole(ROLE_OWNER) === 'Owner', 'Owner role translation failed');
$assert(translatedStatus('in_progress') === 'In progress', 'Status translation failed');
$assert(localizedMoney('1250', 'MAD', 'en') === 'MAD 1,250.00', 'English money formatting failed');
$assert(localizedMoney('1250', 'MAD', 'fr') === '1 250,00 MAD', 'French money formatting failed');
$assert(localizedMoney('1250', 'MAD', 'ar') === '1,250.00 MAD', 'Arabic money formatting failed');
$assert(localizedDate('2026-07-17', 'en') === '17 Jul 2026', 'English date formatting failed');
$assert(localizedDate('2026-07-17', 'fr') === '17 juil. 2026', 'French date formatting failed');
$assert(localizedDate('2026-07-17', 'ar') === '17 يوليو 2026', 'Arabic date formatting failed');
$assert(localizedDateTime('2026-07-17 10:45:00', 'fr') === '17 juil. 2026 à 10:45', 'Localized date-time formatting failed');
$assert(localizedDate('not-a-date', 'en') === '' && localizedDateTime('', 'en') === '', 'Invalid date values were not handled safely');

$switch = languageSwitchUrl('ar', 'vehicle_detail.php', ['page'=>'2','status'=>'paid','tab'=>'media','search'=>'Client A','date_from'=>'2026-07-01','agency_id'=>'3','vehicle_id'=>'7','_csrf'=>'secret','password'=>'secret','redirect'=>'https://example.test']);
parse_str((string) parse_url($switch, PHP_URL_QUERY), $switchQuery);
$assert(str_starts_with($switch, 'vehicle_detail.php?'), 'Language switch changed the route');
$assert(($switchQuery['lang'] ?? null) === 'ar' && ($switchQuery['page'] ?? null) === '2' && ($switchQuery['agency_id'] ?? null) === '3' && ($switchQuery['vehicle_id'] ?? null)==='7' && ($switchQuery['tab'] ?? null)==='media', 'Language switch lost safe vehicle workspace filters');
$assert(!isset($switchQuery['_csrf'], $switchQuery['password'], $switchQuery['redirect']), 'Language switch retained an unsafe value');
$unsafeSwitch = languageSwitchUrl('fr', '../evil.php', ['page'=>'-1','agency_id'=>'x','date_from'=>'invalid','status'=>'<script>']);
$assert(str_starts_with($unsafeSwitch, 'evil.php?') && !str_contains($unsafeSwitch, 'script'), 'Language switch did not reject invalid filter values');
unset($_SESSION['lang']);

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: $failure\n");
    exit(1);
}
echo "Business rule tests passed: domain rules, role permissions, visibility, translations, localization, and safe language switching.\n";
