<?php
/**
 * Bealet Optical Center Careers
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/includes/send-email.php';

global $db;

$errors = [];
$applicationLookupError = '';
$applicationLookupResult = null;
$applicationLookupData = [
    'code' => '',
    'email' => '',
];
$formData = [
    'role' => trim((string) ($_GET['role'] ?? '')),
    'name' => '',
    'email' => '',
    'phone' => '',
    'cover_letter' => '',
];
$success = $_SESSION['hiring_application_success'] ?? null;
unset($_SESSION['hiring_application_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'lookup_application') {
    $applicationLookupData = [
        'code' => strtoupper(trim((string) ($_POST['application_code'] ?? ''))),
        'email' => strtolower(trim((string) ($_POST['application_email'] ?? ''))),
    ];

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $applicationLookupError = 'Your form session expired. Refresh the page and try again.';
    } elseif (
        $applicationLookupData['code'] === ''
        || strlen($applicationLookupData['code']) > 32
        || !filter_var($applicationLookupData['email'], FILTER_VALIDATE_EMAIL)
        || strlen($applicationLookupData['email']) > 254
    ) {
        $applicationLookupError = 'Enter a valid application code and the email address used when applying.';
    } else {
        $applicationLookupResult = $db->fetch(
            "SELECT application_code, opening_title_snapshot, status, submitted_at, updated_at
             FROM hiring_applications
             WHERE application_code = ? AND LOWER(applicant_email) = ?
             LIMIT 1",
            [
                $applicationLookupData['code'],
                $applicationLookupData['email'],
            ]
        );

        if (!$applicationLookupResult) {
            $applicationLookupError = 'We could not find an application matching those details. Check them and try again.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'role' => trim((string) ($_POST['role'] ?? '')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'cover_letter' => trim((string) ($_POST['cover_letter'] ?? '')),
    ];

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your form session expired. Please refresh the page and try again.';
    }

    if (!empty($_POST['website'] ?? '')) {
        $errors[] = 'We could not accept this submission. Please try again.';
    }

    if (($_POST['consent'] ?? '') !== '1') {
        $errors[] = 'Please confirm your information is accurate and consent to recruitment use.';
    }

    if (strlen($formData['name']) < 2 || strlen($formData['name']) > 180) {
        $errors[] = 'Enter your name (up to 180 characters).';
    }

    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || strlen($formData['email']) > 254) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!validatePhone($formData['phone'])) {
        $errors[] = 'Enter a valid phone number, including a Ghana number if applicable.';
    }

    if (strlen($formData['cover_letter']) > 5000) {
        $errors[] = 'Your additional note must be 5,000 characters or fewer.';
    }

    $cv = $_FILES['cv'] ?? null;
    if (!is_array($cv) || (int) ($cv['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Upload your CV as a PDF.';
    } elseif ((int) ($cv['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'The CV upload did not complete. Please try again with a PDF under 5 MB.';
    } elseif ((int) ($cv['size'] ?? 0) > 5 * 1024 * 1024) {
        $errors[] = 'Your CV must be 5 MB or smaller.';
    } elseif (
        strtolower(pathinfo((string) ($cv['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf'
        || empty($cv['tmp_name'])
        || !is_uploaded_file($cv['tmp_name'])
        || !function_exists('finfo_open')
    ) {
        $errors[] = 'Upload a valid PDF file.';
    } else {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo ? finfo_file($fileInfo, $cv['tmp_name']) : false;
        $pdfHeader = file_get_contents($cv['tmp_name'], false, null, 0, 5);
        if ($mimeType !== 'application/pdf' || $pdfHeader !== '%PDF-') {
            $errors[] = 'The uploaded file is not a valid PDF.';
        }
    }

    if (empty($errors)) {
        $opening = $db->fetch(
            "SELECT id, slug, title
             FROM hiring_openings
             WHERE slug = ? AND is_open = 1
             LIMIT 1",
            [$formData['role']]
        );

        if (!$opening) {
            $errors[] = 'That role is not currently accepting applications.';
        }
    }

    if (empty($errors)) {
        $storedCvName = '';
        $storedCvPath = '';
        $applicationCode = generateHiringApplicationCode();
        $storageDirectory = getHiringCvStorageDirectory();

        try {
            $db->beginTransaction();
            $opening = $db->fetch(
                "SELECT id, slug, title, application_limit, is_open
                 FROM hiring_openings
                 WHERE id = ?
                 FOR UPDATE",
                [(int) $opening['id']]
            );

            if (!$opening || (int) $opening['is_open'] !== 1) {
                $db->rollBack();
                $errors[] = 'That role is no longer accepting applications.';
            } else {
                $countResult = $db->fetch(
                    "SELECT COUNT(*) AS application_count
                     FROM hiring_applications
                     WHERE opening_id = ?",
                    [(int) $opening['id']]
                );
                $applicationCount = (int) ($countResult['application_count'] ?? 0);
                $applicationLimit = $opening['application_limit'] !== null
                    ? (int) $opening['application_limit']
                    : null;

                if ($applicationLimit !== null && $applicationCount >= $applicationLimit) {
                    $db->update("UPDATE hiring_openings SET is_open = 0 WHERE id = ?", [(int) $opening['id']]);
                    $db->commit();
                    $errors[] = 'This opening has reached its application limit and is now closed.';
                } else {
                    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
                        throw new RuntimeException('Unable to create private CV storage.');
                    }
                    if (!is_writable($storageDirectory)) {
                        throw new RuntimeException('Private CV storage is not writable.');
                    }
                    if (!chmod($storageDirectory, 0700)) {
                        throw new RuntimeException('Unable to secure private CV storage permissions.');
                    }

                    $storedCvName = bin2hex(random_bytes(16)) . '.pdf';
                    $storedCvPath = $storageDirectory . DIRECTORY_SEPARATOR . $storedCvName;
                    if (!move_uploaded_file($cv['tmp_name'], $storedCvPath)) {
                        throw new RuntimeException('Unable to store the uploaded CV.');
                    }
                    if (!chmod($storedCvPath, 0600)) {
                        throw new RuntimeException('Unable to secure the uploaded CV permissions.');
                    }

                    $originalCvName = basename(str_replace('\\', '/', (string) $cv['name']));
                    $originalCvName = substr($originalCvName, 0, 255);
                    $coverLetter = $formData['cover_letter'] !== '' ? $formData['cover_letter'] : null;

                    $db->insert(
                        "INSERT INTO hiring_applications
                            (opening_id, opening_title_snapshot, application_code, applicant_name, applicant_email,
                             applicant_phone, cover_letter, cv_storage_name, cv_original_name)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            (int) $opening['id'],
                            (string) $opening['title'],
                            $applicationCode,
                            $formData['name'],
                            $formData['email'],
                            $formData['phone'],
                            $coverLetter,
                            $storedCvName,
                            $originalCvName,
                        ]
                    );

                    if ($applicationLimit !== null && $applicationCount + 1 >= $applicationLimit) {
                        $db->update("UPDATE hiring_openings SET is_open = 0 WHERE id = ?", [(int) $opening['id']]);
                    }

                    $db->commit();
                }
            }
        } catch (PDOException $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollBack();
            }
            if ($storedCvPath !== '' && is_file($storedCvPath)) {
                unlink($storedCvPath);
            }
            createLog('HIRING_APPLICATION_ERROR', 'Database error: ' . $e->getMessage());
            $errors[] = 'We could not save your application. Please try again.';
        } catch (RuntimeException $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollBack();
            }
            if ($storedCvPath !== '' && is_file($storedCvPath)) {
                unlink($storedCvPath);
            }
            createLog('HIRING_APPLICATION_ERROR', $e->getMessage());
            $errors[] = 'We could not safely store your CV. Please try again or contact us.';
        }

        if (empty($errors)) {
            $emailSent = false;
            $emailDiagnosticCode = null;
            try {
                $emailSent = sendEmail(
                    $formData['email'],
                    'Application received - ' . APP_NAME,
                    getHiringApplicationConfirmationTemplate(
                        $formData['name'],
                        (string) $opening['title'],
                        $applicationCode
                    ),
                    true,
                    $emailDiagnosticCode
                );
            } catch (Throwable $e) {
                $emailDiagnosticCode = 'email_processing_error';
                createLog('HIRING_CONFIRMATION_EMAIL_ERROR', $e->getMessage());
            }

            $hiringNotificationEmail = getMailSetting('HIRING_NOTIFICATION_EMAIL');
            $hiringNotificationSent = false;
            $hiringNotificationDiagnosticCode = null;
            if ($hiringNotificationEmail === '' || !filter_var($hiringNotificationEmail, FILTER_VALIDATE_EMAIL)) {
                $hiringNotificationDiagnosticCode = 'hiring_notification_address_invalid';
                createLog('HIRING_NOTIFICATION_EMAIL_ERROR', 'Hiring notification email is not configured.');
            } else {
                try {
                    $hiringNotificationSent = sendEmail(
                        $hiringNotificationEmail,
                        'New hiring application - ' . APP_NAME,
                        getHiringApplicationNotificationTemplate(
                            $formData['name'],
                            (string) $opening['title'],
                            $applicationCode
                        ),
                        true,
                        $hiringNotificationDiagnosticCode
                    );
                    if (!$hiringNotificationSent) {
                        createLog('HIRING_NOTIFICATION_EMAIL_ERROR', 'Hiring notification was not accepted by the email transport.');
                    }
                } catch (Throwable $e) {
                    $hiringNotificationDiagnosticCode = 'email_processing_error';
                    createLog('HIRING_NOTIFICATION_EMAIL_ERROR', $e->getMessage());
                }
            }

            createLog(
                'HIRING_APPLICATION_SUBMITTED',
                'Application ' . $applicationCode . ' for ' . (string) $opening['title']
            );
            $_SESSION['hiring_application_success'] = [
                'name' => $formData['name'],
                'role' => (string) $opening['title'],
                'code' => $applicationCode,
                'email' => $formData['email'],
                'email_sent' => $emailSent,
                'mail_diagnostics' => [
                    'confirmation_code' => $emailDiagnosticCode,
                    'notification_code' => $hiringNotificationDiagnosticCode,
                    'notification_sent' => $hiringNotificationSent,
                    'transport' => getMailTransportDiagnostics(),
                ],
            ];
            redirect(APP_URL . '/hiring.php', 303);
        }
    }
}

$openings = $db->fetchAll(
    "SELECT o.*,
            (SELECT COUNT(*) FROM hiring_applications a WHERE a.opening_id = o.id) AS application_count
     FROM hiring_openings o
     ORDER BY o.sort_order ASC, o.id ASC"
);
$hiringImageRows = $db->fetchAll("SELECT image_key, image_filename FROM hiring_page_images");
$hiringImages = [];
foreach ($hiringImageRows as $imageRow) {
    $hiringImages[$imageRow['image_key']] = $imageRow['image_filename'];
}
$openRoles = array_values(array_filter($openings, static fn ($opening) => (int) $opening['is_open'] === 1));
$selectedRole = $formData['role'];
$validOpenRoleSlugs = array_column($openRoles, 'slug');
if (!in_array($selectedRole, $validOpenRoleSlugs, true)) {
    $selectedRole = !empty($openRoles) ? (string) $openRoles[0]['slug'] : '';
}

$sharedRole = null;
foreach ($openings as $opening) {
    if ((string) $opening['slug'] === trim((string) ($_GET['role'] ?? ''))) {
        $sharedRole = $opening;
        break;
    }
}

if ($sharedRole !== null) {
    $roleImage = $hiringImages['role:' . (int) $sharedRole['id']] ?? '';
    $roleImageIsValid = preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $roleImage)
        && is_file(__DIR__ . '/assets/uploads/hiring/' . $roleImage);
    $shareImage = $roleImageIsValid
        ? APP_URL . '/assets/uploads/hiring/' . rawurlencode($roleImage)
        : (!empty($hiringImages['hero'])
            ? APP_URL . '/assets/uploads/hiring/' . rawurlencode($hiringImages['hero'])
            : getSiteLogoUrl());
    $shareDescription = trim(preg_replace('/\s+/', ' ', strip_tags((string) $sharedRole['description'])));
    if ($shareDescription === '') {
        $shareDescription = 'Explore the ' . $sharedRole['title'] . ' opportunity at ' . getCompanyName() . '.';
    }
    if (strlen($shareDescription) > 180) {
        $shareDescription = substr($shareDescription, 0, 177) . '...';
    }

    $pageMetaTitle = $sharedRole['title'] . ' | Careers at ' . getCompanyName();
    $pageMetaDescription = $shareDescription;
    $pageMetaUrl = APP_URL . '/hiring?role=' . rawurlencode((string) $sharedRole['slug']);
    $pageMetaImage = $shareImage;
}

$mailDiagnosticMessages = [
    'mail_env_invalid_format' => 'The private .env file could not be parsed; check its key=value formatting.',
    'smtp_credentials_missing' => 'SMTP credentials are not configured.',
    'smtp_credentials_incomplete' => 'The SMTP username and App Password are not both configured.',
    'phpmailer_missing' => 'PHPMailer is unavailable; check that the vendor dependencies were uploaded.',
    'smtp_configuration_invalid' => 'The SMTP host, port, or sender address is invalid.',
    'smtp_encryption_invalid' => 'MAIL_ENCRYPTION must be set to tls or ssl.',
    'smtp_authentication_failed' => 'The SMTP server rejected authentication; verify the account and App Password.',
    'smtp_timeout' => 'The SMTP server did not respond before the connection timeout.',
    'smtp_connection_failed' => 'Could not connect to the SMTP server; verify the host, port, and hosting network access.',
    'smtp_send_failed' => 'The SMTP server failed to accept the message; check the PHP error log for details.',
    'php_mail_failed' => 'The PHP mail() transport failed.',
    'hiring_notification_address_invalid' => 'HIRING_NOTIFICATION_EMAIL is missing or invalid.',
    'email_processing_error' => 'An unexpected email processing error occurred; check the PHP error log.',
];
$mailEnvironmentStatusLabels = [
    'loaded' => 'Parsed successfully',
    'file_missing' => 'File not found',
    'file_unreadable' => 'File is not readable by PHP',
    'invalid_format' => 'Invalid dotenv syntax',
    'composer_autoload_missing' => 'Composer autoload file is missing',
    'dotenv_missing' => 'dotenv library is missing',
];
$mailTransportLabels = [
    'smtp' => 'SMTP',
    'php_mail' => 'PHP mail() fallback',
    'incomplete_smtp' => 'Incomplete SMTP configuration',
];

require_once __DIR__ . '/inc/header.php';
?>

<main class="hiring-page">
    <section class="hiring-hero<?php echo !empty($hiringImages['hero']) ? ' has-image' : ''; ?>">
        <?php if (!empty($hiringImages['hero'])): ?>
        <img class="hiring-hero-image"
             src="<?php echo APP_URL; ?>/assets/uploads/hiring/<?php echo rawurlencode($hiringImages['hero']); ?>"
             alt="">
        <?php endif; ?>
        <div class="container-lg">
            <span class="hiring-eyebrow">Careers at Bealet</span>
            <h1>Build a clearer future with us.</h1>
            <p>Explore opportunities to help people see better, feel confident, and experience thoughtful optical care.</p>
        </div>
    </section>

    <section class="container-lg hiring-content">
        <?php if (is_array($success)): ?>
        <div class="hiring-success-card" role="status">
            <div class="hiring-success-icon"><i class="fas fa-check"></i></div>
            <div>
                <p class="hiring-eyebrow">Application received</p>
                <h2>Thank you, <?php echo sanitize($success['name']); ?>.</h2>
                <p>Your application for <strong><?php echo sanitize($success['role']); ?></strong> has been submitted successfully.</p>
                <p class="hiring-reference">Application code: <strong><?php echo sanitize($success['code']); ?></strong></p>
                <p class="mb-0">
                    <?php if (!empty($success['email_sent'])): ?>
                        A confirmation has been sent to <?php echo sanitize($success['email']); ?>.
                    <?php else: ?>
                        We could not send the confirmation email. Please save your application code and contact us if you need help.
                    <?php endif; ?>
                </p>
                <?php
                $mailDiagnostics = $success['mail_diagnostics'] ?? null;
                $mailDiagnosticsHaveErrors = is_array($mailDiagnostics)
                    && (empty($success['email_sent']) || empty($mailDiagnostics['notification_sent']));
                ?>
                <?php if (isSuperAdmin() && $mailDiagnosticsHaveErrors): ?>
                <?php
                $transportDiagnostics = $mailDiagnostics['transport'] ?? [];
                $confirmationCode = $mailDiagnostics['confirmation_code'] ?? '';
                $notificationCode = $mailDiagnostics['notification_code'] ?? '';
                ?>
                <details class="hiring-mail-diagnostics mt-3">
                    <summary>Mail diagnostics (super admin only)</summary>
                    <div class="mt-2">
                        <p class="mb-2"><strong>Confirmation:</strong>
                            <?php echo !empty($success['email_sent']) ? 'accepted by transport' : sanitize($mailDiagnosticMessages[$confirmationCode] ?? 'No specific failure code was captured.'); ?>
                        </p>
                        <p class="mb-2"><strong>Hiring inbox alert:</strong>
                            <?php echo !empty($mailDiagnostics['notification_sent']) ? 'accepted by transport' : sanitize($mailDiagnosticMessages[$notificationCode] ?? 'No specific failure code was captured.'); ?>
                        </p>
                        <ul class="mb-2">
                            <li>Private .env: <?php echo !empty($transportDiagnostics['private_env_readable']) ? 'readable' : 'missing or unreadable'; ?></li>
                            <li>.env parse status: <?php echo sanitize($mailEnvironmentStatusLabels[$transportDiagnostics['environment_status'] ?? ''] ?? 'unknown'); ?></li>
                            <li>Composer autoload: <?php echo !empty($transportDiagnostics['composer_autoload_present']) ? 'available' : 'missing'; ?></li>
                            <li>PHPMailer: <?php echo !empty($transportDiagnostics['phpmailer_available']) ? 'available' : 'missing'; ?></li>
                            <li>SMTP username configured: <?php echo !empty($transportDiagnostics['smtp_username_configured']) ? 'yes' : 'no'; ?></li>
                            <li>SMTP App Password configured: <?php echo !empty($transportDiagnostics['smtp_password_configured']) ? 'yes' : 'no'; ?></li>
                            <li>Sender address valid: <?php echo !empty($transportDiagnostics['sender_address_valid']) ? 'yes' : 'no'; ?></li>
                            <li>Hiring notification address valid: <?php echo !empty($transportDiagnostics['notification_address_valid']) ? 'yes' : 'no'; ?></li>
                            <li>Transport: <?php echo sanitize($mailTransportLabels[$transportDiagnostics['transport'] ?? ''] ?? 'unknown'); ?></li>
                            <li>SMTP target: <?php echo sanitize((string) ($transportDiagnostics['smtp_host'] ?? 'unknown')); ?>:<?php echo (int) ($transportDiagnostics['smtp_port'] ?? 0); ?> / <?php echo sanitize(strtoupper((string) ($transportDiagnostics['smtp_encryption'] ?? 'unknown'))); ?></li>
                        </ul>
                        <small class="text-muted">Credential values and the private file path are never displayed.</small>
                    </div>
                </details>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <section class="hiring-status-lookup" aria-labelledby="hiringStatusLookupTitle">
            <div class="hiring-status-lookup-intro">
                <span class="hiring-eyebrow">Already applied?</span>
                <h2 id="hiringStatusLookupTitle">Check your application status</h2>
                <p>Enter your application code and the email address you used when applying.</p>
            </div>

            <?php if ($applicationLookupError !== ''): ?>
            <div class="alert alert-warning" role="alert"><?php echo sanitize($applicationLookupError); ?></div>
            <?php endif; ?>

            <?php if (is_array($applicationLookupResult)): ?>
            <?php
            $applicationStatusLabels = [
                'submitted' => 'Application received',
                'reviewing' => 'Under review',
                'accepted' => 'Accepted',
                'declined' => 'Not selected',
            ];
            $applicationStatusClasses = [
                'submitted' => 'bg-primary',
                'reviewing' => 'bg-warning text-dark',
                'accepted' => 'bg-success',
                'declined' => 'bg-secondary',
            ];
            $applicationStatus = (string) $applicationLookupResult['status'];
            ?>
            <div class="hiring-status-result" role="status" aria-live="polite">
                <div>
                    <span class="text-muted">Role</span>
                    <strong><?php echo sanitize($applicationLookupResult['opening_title_snapshot']); ?></strong>
                </div>
                <div>
                    <span class="text-muted">Status</span>
                    <strong>
                        <span class="badge <?php echo sanitize($applicationStatusClasses[$applicationStatus] ?? 'bg-secondary'); ?>">
                            <?php echo sanitize($applicationStatusLabels[$applicationStatus] ?? 'Status unavailable'); ?>
                        </span>
                    </strong>
                </div>
                <div>
                    <span class="text-muted">Last updated</span>
                    <strong><?php echo sanitize(date('M j, Y', strtotime((string) $applicationLookupResult['updated_at']))); ?></strong>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo APP_URL; ?>/hiring/" class="hiring-status-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="form_action" value="lookup_application">
                <div>
                    <label class="form-label" for="applicationStatusCode">Application code</label>
                    <input class="form-control" id="applicationStatusCode" name="application_code"
                           type="text" maxlength="32" autocomplete="off" autocapitalize="characters"
                           placeholder="BOC-HR-2026-XXXXXXXXXX"
                           value="<?php echo sanitize($applicationLookupData['code']); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="applicationStatusEmail">Application email</label>
                    <input class="form-control" id="applicationStatusEmail" name="application_email"
                           type="email" maxlength="254" autocomplete="email"
                           value="<?php echo sanitize($applicationLookupData['email']); ?>" required>
                </div>
                <button class="btn btn-primary" type="submit">Check status</button>
            </form>
        </section>

        <div class="hiring-section-heading">
            <div>
                <span class="hiring-eyebrow">Find your place</span>
                <h2>Current opportunities</h2>
            </div>
            <p>Every role is shown below. Open roles are accepting applications; closed roles are listed for visibility only.</p>
        </div>

        <?php if (empty($openings)): ?>
        <div class="alert alert-warning">The careers page is being prepared. Please check back soon.</div>
        <?php else: ?>
        <div class="hiring-role-grid">
            <?php foreach ($openings as $opening): ?>
            <?php $isOpen = (int) $opening['is_open'] === 1; ?>
            <article class="hiring-role-card<?php echo $isOpen ? ' is-open' : ' is-closed'; ?>"
                     id="role-<?php echo sanitize($opening['slug']); ?>">
                <?php $roleImage = $hiringImages['role:' . (int) $opening['id']] ?? ''; ?>
                <div class="hiring-role-image-wrap">
                    <?php if ($roleImage !== ''): ?>
                    <img class="hiring-role-image"
                         src="<?php echo APP_URL; ?>/assets/uploads/hiring/<?php echo rawurlencode($roleImage); ?>"
                         alt="">
                    <?php else: ?>
                    <div class="hiring-role-image-placeholder" aria-hidden="true">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <?php endif; ?>
                    <span class="hiring-status <?php echo $isOpen ? 'is-open' : 'is-closed'; ?>">
                        <?php echo $isOpen ? 'Accepting applications' : 'Applications closed'; ?>
                    </span>
                </div>
                <div class="hiring-role-card-body">
                    <span class="hiring-role-icon"><i class="fas fa-briefcase"></i></span>
                    <h3><?php echo sanitize($opening['title']); ?></h3>
                    <?php if (!empty($opening['description'])): ?>
                    <p class="hiring-role-description"><?php echo nl2br(sanitize($opening['description'])); ?></p>
                    <?php else: ?>
                    <p class="hiring-role-description text-muted">Role details will be shared when this opening is activated.</p>
                    <?php endif; ?>

                    <?php if (!empty($opening['role_advantage'])): ?>
                    <aside class="hiring-role-advantage">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        <div>
                            <strong>Added advantage</strong>
                            <p><?php echo sanitize($opening['role_advantage']); ?></p>
                        </div>
                    </aside>
                    <?php endif; ?>

                    <dl class="hiring-role-details">
                        <?php if (!empty($opening['branch'])): ?>
                        <div><dt>Branch</dt><dd><?php echo sanitize($opening['branch']); ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($opening['schedule'])): ?>
                        <div><dt>Working days</dt><dd><?php echo sanitize($opening['schedule']); ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($opening['candidate_preference'])): ?>
                        <div><dt>Candidate profile</dt><dd><?php echo sanitize($opening['candidate_preference']); ?></dd></div>
                        <?php endif; ?>
                    </dl>

                    <?php if (!empty($opening['requirements'])): ?>
                    <div class="hiring-requirements">
                        <h4>Requirements</h4>
                        <div><?php echo nl2br(sanitize($opening['requirements'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="hiring-card-actions">
                        <?php if ($isOpen): ?>
                        <button class="btn btn-primary hiring-apply-link" type="button"
                                data-bs-toggle="modal" data-bs-target="#hiringApplicationModal"
                                data-role="<?php echo sanitize($opening['slug']); ?>">
                            Apply for this role <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn btn-outline-secondary hiring-apply-link" type="button" disabled>Not accepting applications</button>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary hiring-share-button" type="button"
                                aria-label="Share <?php echo sanitize($opening['title']); ?> role"
                                title="Share <?php echo sanitize($opening['title']); ?> role"
                                data-share-url="<?php echo sanitize(APP_URL . '/hiring?role=' . rawurlencode((string) $opening['slug']) . '#role-' . rawurlencode((string) $opening['slug'])); ?>"
                                data-share-title="<?php echo sanitize($opening['title']); ?>"
                                data-share-text="<?php echo sanitize($opening['description'] ?: 'Explore this career opportunity at Bealet Optical Center.'); ?>">
                            <i class="fas fa-share-alt" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <section class="hiring-application-prompt">
            <div>
                <span class="hiring-eyebrow">Take the next step</span>
                <h2>Submit your application</h2>
                <p>Your CV is stored privately and is only available to the hiring team.</p>
            </div>
            <button class="btn btn-primary btn-lg hiring-submit-button" type="button"
                    data-bs-toggle="modal" data-bs-target="#hiringApplicationModal">
                Open application portal <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </section>

        <div class="modal fade hiring-application-modal" id="hiringApplicationModal" tabindex="-1"
             aria-labelledby="hiringApplicationModalTitle" aria-hidden="true"
             data-show-on-load="<?php echo !empty($errors) ? 'true' : 'false'; ?>">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <div class="hiring-form-intro mb-0">
                            <span class="hiring-eyebrow">Take the next step</span>
                            <h2 id="hiringApplicationModalTitle">Submit your application</h2>
                            <p>Your CV is stored privately and is only available to the hiring team.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close application form"></button>
                    </div>
                    <div class="modal-body pt-3">
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                <div><?php echo sanitize($error); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (empty($openRoles)): ?>
            <div class="hiring-closed-note">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>There are no open applications right now.</strong>
                    <p class="mb-0">You can review the roles above and check back when an opening becomes active.</p>
                </div>
            </div>
            <?php else: ?>
            <form method="POST" action="<?php echo APP_URL; ?>/hiring/" enctype="multipart/form-data" class="hiring-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="hiring-honeypot" aria-hidden="true">
                    <label for="hiringWebsite">Website</label>
                    <input type="text" name="website" id="hiringWebsite" tabindex="-1" autocomplete="off">
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="hiringRole">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="hiringRole" name="role" required>
                            <?php foreach ($openRoles as $openRole): ?>
                            <option value="<?php echo sanitize($openRole['slug']); ?>" <?php echo $selectedRole === $openRole['slug'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($openRole['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="hiringName">Full name <span class="text-danger">*</span></label>
                        <input class="form-control" id="hiringName" name="name" type="text" maxlength="180" autocomplete="name" value="<?php echo sanitize($formData['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="hiringEmail">Email address <span class="text-danger">*</span></label>
                        <input class="form-control" id="hiringEmail" name="email" type="email" maxlength="254" autocomplete="email" value="<?php echo sanitize($formData['email']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="hiringPhone">Phone number <span class="text-danger">*</span></label>
                        <input class="form-control" id="hiringPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?php echo sanitize($formData['phone']); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="hiringCv">CV (PDF, max 5 MB) <span class="text-danger">*</span></label>
                        <input class="form-control" id="hiringCv" name="cv" type="file" accept=".pdf,application/pdf" required>
                        <div class="form-text">PDF files only. Your CV is not publicly accessible.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="hiringCoverLetter">A short note for the hiring team <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control" id="hiringCoverLetter" name="cover_letter" rows="4" maxlength="5000"><?php echo sanitize($formData['cover_letter']); ?></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" id="hiringConsent" name="consent" type="checkbox" value="1" required>
                            <label class="form-check-label" for="hiringConsent">I confirm that the information provided is accurate and consent to its use for recruitment.</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-lg hiring-submit-button" type="submit">
                            Submit application <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="visually-hidden" id="hiringShareStatus" aria-live="polite"></div>
<script>
    document.addEventListener('show.bs.modal', function (event) {
        if (event.target.id !== 'hiringApplicationModal' || !event.relatedTarget) return;
        const role = event.relatedTarget.getAttribute('data-role');
        const roleSelect = document.getElementById('hiringRole');
        if (role && roleSelect && [...roleSelect.options].some(option => option.value === role)) {
            roleSelect.value = role;
        }
    });

    document.addEventListener('click', async function (event) {
        const button = event.target.closest('.hiring-share-button');
        if (!button) return;

        const shareData = {
            title: button.dataset.shareTitle,
            text: button.dataset.shareText,
            url: button.dataset.shareUrl
        };
        const status = document.getElementById('hiringShareStatus');

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                return;
            } catch (error) {
                if (error.name === 'AbortError') return;
            }
        }

        try {
            await navigator.clipboard.writeText(shareData.url);
            status.textContent = 'Role link copied to clipboard.';
            button.setAttribute('aria-label', 'Role link copied');
            button.title = 'Role link copied';
            const icon = button.querySelector('i');
            icon?.classList.replace('fa-share-alt', 'fa-check');
            window.setTimeout(() => {
                button.setAttribute('aria-label', `Share ${shareData.title} role`);
                button.title = `Share ${shareData.title} role`;
                icon?.classList.replace('fa-check', 'fa-share-alt');
            }, 2500);
        } catch (error) {
            window.prompt('Copy this role link to share it:', shareData.url);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('hiringApplicationModal');
        if (modalElement?.dataset.showOnLoad === 'true' && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    });
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
