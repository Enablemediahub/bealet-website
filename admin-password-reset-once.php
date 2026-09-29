<?php
$expectedHost = ['bealet.com', 'www.bealet.com'];
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\d+$/', '', $requestHost);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$expectedToken = '432b8a68aabee50518bc0acbaf2a6bc3be56ce0f9f75be8a060b05b3c936cea3';
$providedToken = (string) ($_POST['key'] ?? $_GET['key'] ?? '');

if (!in_array($requestHost, $expectedHost, true) || !$isHttps) {
    http_response_code(404);
    exit('Not found.');
}

if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(404);
    exit('Not found.');
}

$usedMarker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bealet-admin-reset-' . hash('sha256', __FILE__) . '.used';
if (is_file($usedMarker)) {
    http_response_code(410);
    exit('This reset link has already been used. Remove this file from the server.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $formAction = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin-password-reset-once'), PHP_URL_PATH);
    if (!is_string($formAction) || $formAction === '') {
        $formAction = '/admin-password-reset-once';
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Confirm Admin Password Reset</title>
    </head>
    <body>
        <main>
            <h1>Confirm admin password reset</h1>
            <p>This will set the password for crepindale@gmail.com to the temporary password you requested.</p>
            <form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="key" value="<?php echo htmlspecialchars($expectedToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">Reset admin password</button>
            </form>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$markerHandle = @fopen($usedMarker, 'x');
if ($markerHandle === false) {
    http_response_code(410);
    exit('This reset link has already been used. Remove this file from the server.');
}
fclose($markerHandle);

try {
    require_once __DIR__ . '/inc/config.php';
    require_once __DIR__ . '/inc/db.php';
    require_once __DIR__ . '/inc/functions.php';

    $user = $db->fetch(
        'SELECT id FROM users WHERE email = ? AND is_admin = 1 LIMIT 1',
        ['crepindale@gmail.com']
    );

    if (!$user) {
        throw new RuntimeException('The requested admin account was not found.');
    }

    $db->beginTransaction();
    $statement = $db->update(
        'UPDATE users SET password_hash = ?, login_attempts = 0, locked_until = NULL WHERE id = ? AND is_admin = 1',
        [hashPassword('password'), $user['id']]
    );

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('The admin password reset did not update a row.');
    }

    $db->commit();
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    @unlink($usedMarker);
    error_log('One-time admin password reset failed: ' . $error->getMessage());
    http_response_code(500);
    exit('The reset failed. Check the server error log and remove this file when finished.');
}

$deleted = @unlink(__FILE__);
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo "Admin password reset for crepindale@gmail.com.\n";
echo "Temporary password: password\n";
echo "Login at https://bealet.com/admin/login and change the password immediately.\n";
if (!$deleted) {
    echo "The reset file could not delete itself. Remove admin-password-reset-once.php from the server now.\n";
}
