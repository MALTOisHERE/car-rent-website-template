<?php

function loadUserAgencies($userId)
{
    return array_map('intval', array_column(
        dbFetchAll('SELECT agency_id FROM user_agencies WHERE user_id = :user_id', ['user_id' => $userId]),
        'agency_id'
    ));
}

function authenticateCredentials($email, $password)
{
    $email = normalizedEmail($email);
    $user = dbFetchOne('SELECT * FROM users WHERE email_normalized = :email LIMIT 1', ['email' => $email]);
    $dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $hash = $user['password_hash'] ?? $dummyHash;
    $valid = password_verify((string) $password, $hash);

    if (!$user || $user['status'] !== 'active' || !empty($user['archived_at'])) {
        return null;
    }

    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return null;
    }

    if (!$valid) {
        $attempts = (int) $user['failed_login_attempts'] + 1;
        $lockedUntil = $attempts >= appConfig('login_max_attempts')
            ? date('Y-m-d H:i:s', time() + appConfig('login_window_seconds'))
            : null;
        dbExecute(
            'UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked_until WHERE id = :id',
            ['attempts' => $attempts, 'locked_until' => $lockedUntil, 'id' => $user['id']]
        );
        auditLog('auth.login_failed', 'user', $user['id']);
        return null;
    }

    dbExecute(
        'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id',
        ['id' => $user['id']]
    );
    $user['iduser'] = $user['id'];
    $user['agency_ids'] = loadUserAgencies($user['id']);
    loginUser($user);
    auditLog('auth.login_succeeded', 'user', $user['id']);
    return $user;
}

function ensureCurrentSessionValid()
{
    if (!isAuthenticated() || !function_exists('dbFetchOne') || !tableExists('users')) {
        return;
    }

    $user = dbFetchOne(
        'SELECT status, archived_at, sessions_invalid_before FROM users WHERE id = :id',
        ['id' => currentUserId()]
    );
    $authenticatedAt = (int) ($_SESSION['_authenticated_at'] ?? 0);
    $invalidBefore = !empty($user['sessions_invalid_before']) ? strtotime($user['sessions_invalid_before']) : 0;
    if (!$user || $user['status'] !== 'active' || !empty($user['archived_at']) || $authenticatedAt < $invalidBefore) {
        logoutUser();
        safeRedirect('../account/login.php?reason=session');
    }
}

function updateOwnProfile($userId, $fullname, $phone)
{
    $fullname = trim((string) $fullname);
    $phone = trim((string) $phone);
    if ($fullname === '') {
        throw new InvalidArgumentException(t('validation.name_required'));
    }
    $before = dbFetchOne('SELECT fullname, phone FROM users WHERE id = :id', ['id' => $userId]);
    dbExecute('UPDATE users SET fullname = :fullname, phone = :phone WHERE id = :id', [
        'fullname' => $fullname, 'phone' => $phone, 'id' => $userId,
    ]);
    auditLog('user.profile_updated', 'user', $userId, $before, ['fullname' => $fullname, 'phone' => $phone]);
    $_SESSION['username'] = $fullname;
    return $fullname;
}

function changeOwnPassword($userId, $currentPassword, $newPassword)
{
    $user = dbFetchOne('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]);
    if (!$user || !password_verify((string) $currentPassword, $user['password_hash'])) {
        throw new InvalidArgumentException(t('validation.current_password_incorrect'));
    }
    $errors = passwordValidationErrors($newPassword);
    if ($errors !== []) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    dbExecute(
        'UPDATE users SET password_hash = :password_hash, password_changed_at = NOW(), sessions_invalid_before = NOW() WHERE id = :id',
        ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]
    );
    auditLog('user.password_changed', 'user', $userId);
    session_regenerate_id(true);
    $_SESSION['_authenticated_at'] = time();
    $_SESSION['_regenerated_at'] = time();
}

function createPasswordResetRequest($email, $languageCode = 'en')
{
    $user = dbFetchOne(
        'SELECT id, email FROM users WHERE email_normalized = :email AND status = :status AND archived_at IS NULL',
        ['email' => normalizedEmail($email), 'status' => 'active']
    );
    if (!$user) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    dbExecute(
        'UPDATE users SET password_reset_token_hash = :token_hash, password_reset_expires_at = :expires_at WHERE id = :id',
        ['token_hash' => $tokenHash, 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'id' => $user['id']]
    );

    $languageCode = in_array($languageCode, supportedLanguages(), true) ? $languageCode : 'en';
    $baseUrl = appConfig('base_url');
    $path = '/account/reset_password.php?lang=' . rawurlencode($languageCode) . '&token=' . rawurlencode($token);
    $link = ($baseUrl !== '' ? $baseUrl : '') . $path;
    $subject = 'Password reset request';
    $message = "A password reset was requested for your account.\n\n" . $link . "\n\nThis link expires in one hour.";
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($user['email'], $subject, $message, $headers);
    auditLog('auth.password_reset_requested', 'user', $user['id']);
}

function resetPasswordWithToken($token, $password)
{
    $errors = passwordValidationErrors($password);
    if ($errors !== []) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $user = dbFetchOne(
        'SELECT id FROM users WHERE password_reset_token_hash = :token_hash
         AND password_reset_expires_at > NOW() AND status = :status AND archived_at IS NULL',
        ['token_hash' => hash('sha256', (string) $token), 'status' => 'active']
    );
    if (!$user) {
        return false;
    }

    withTransaction(function () use ($user, $password) {
        dbExecute(
            'UPDATE users SET password_hash = :password_hash, password_reset_token_hash = NULL,
             password_reset_expires_at = NULL, password_changed_at = NOW(), sessions_invalid_before = NOW()
             WHERE id = :id',
            ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]
        );
        auditLog('auth.password_reset_completed', 'user', $user['id']);
    });
    return true;
}

