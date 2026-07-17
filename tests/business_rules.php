<?php

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/validation.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/domain.php';

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
    'vehicle_documents.manage',
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

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: $failure\n");
    exit(1);
}
echo "Business rule tests passed: domain rules, role permissions, and visibility helpers.\n";
