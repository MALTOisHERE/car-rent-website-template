<?php

const ROLE_OWNER = 'OWNER';
const ROLE_AGENCY_MANAGER = 'AGENCY_MANAGER';
const ROLE_RENTAL_AGENT = 'RENTAL_AGENT';
const ROLE_ACCOUNTANT = 'ACCOUNTANT';
const ROLE_FLEET_AGENT = 'FLEET_AGENT';
const ROLE_CUSTOMER = 'CUSTOMER';

function normalizeRole($role)
{
    $role = strtoupper(trim((string) $role));
    $legacy = ['ADMIN' => ROLE_OWNER, '1' => ROLE_OWNER, '0' => ROLE_CUSTOMER, 'USER' => ROLE_CUSTOMER];
    return $legacy[$role] ?? $role;
}

function currentUserId()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUserRole()
{
    return normalizeRole($_SESSION['role'] ?? ROLE_CUSTOMER);
}

function isAuthenticated()
{
    return currentUserId() !== null;
}

function loginUser(array $user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['iduser'];
    $_SESSION['username'] = (string) ($user['fullname'] ?? $user['email']);
    $_SESSION['email'] = (string) $user['email'];
    $_SESSION['role'] = normalizeRole($user['role'] ?? ROLE_CUSTOMER);
    $_SESSION['agency_ids'] = array_values(array_map('intval', $user['agency_ids'] ?? []));
    $_SESSION['_created_at'] = time();
    $_SESSION['_authenticated_at'] = time();
    $_SESSION['_last_activity'] = time();
    $_SESSION['_regenerated_at'] = time();
    unset($_SESSION['_csrf_token']);
}

function logoutUser()
{
    clearSession();
}

function requireAuthentication($loginPath = '../en/login.php')
{
    if (!isAuthenticated()) {
        safeRedirect($loginPath);
    }
    if (function_exists('ensureCurrentSessionValid')) {
        ensureCurrentSessionValid();
    }
}

function rolePermissions()
{
    return [
        ROLE_OWNER => ['*'],
        ROLE_AGENCY_MANAGER => ['dashboard.view', 'agencies.view', 'users.manage', 'customers.manage', 'vehicles.manage', 'reservations.manage', 'contracts.manage', 'payments.manage', 'invoices.manage', 'expenses.manage', 'inspections.manage', 'maintenance.manage', 'reports.view'],
        ROLE_RENTAL_AGENT => ['dashboard.view', 'customers.manage', 'vehicles.view', 'reservations.manage', 'contracts.manage', 'payments.create', 'inspections.manage'],
        ROLE_ACCOUNTANT => ['dashboard.view', 'payments.manage', 'invoices.manage', 'expenses.manage', 'reports.financial', 'reports.view'],
        ROLE_FLEET_AGENT => ['dashboard.view', 'vehicles.manage', 'inspections.manage', 'maintenance.manage', 'vehicle_documents.manage'],
        ROLE_CUSTOMER => ['portal.use'],
    ];
}

function can($permission)
{
    $permissions = rolePermissions()[currentUserRole()] ?? [];
    if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
        return true;
    }
    if (str_ends_with($permission, '.view')) {
        return in_array(substr($permission, 0, -5) . '.manage', $permissions, true);
    }
    if (str_ends_with($permission, '.create')) {
        return in_array(substr($permission, 0, -7) . '.manage', $permissions, true);
    }
    return false;
}

function requirePermission($permission)
{
    requireAuthentication();
    if (!can($permission)) {
        http_response_code(403);
        exit('You are not authorized to perform this action.');
    }
}

function passwordValidationErrors($password)
{
    $errors = [];
    if (strlen((string) $password) < 12) $errors[] = 'Password must contain at least 12 characters.';
    if (!preg_match('/[A-Z]/', (string) $password)) $errors[] = 'Password must contain an uppercase letter.';
    if (!preg_match('/[a-z]/', (string) $password)) $errors[] = 'Password must contain a lowercase letter.';
    if (!preg_match('/\d/', (string) $password)) $errors[] = 'Password must contain a number.';
    if (!preg_match('/[^A-Za-z0-9]/', (string) $password)) $errors[] = 'Password must contain a symbol.';
    return $errors;
}
