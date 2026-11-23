<?php
/**
 * Bealet Website - Hiring Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

requireSuperAdmin();

global $db;

$errors = [];
$openingOverrides = [];
$applicationStatuses = ['submitted', 'reviewing', 'accepted', 'declined'];

if (isset($_GET['download_cv'])) {
    $applicationId = (int) $_GET['download_cv'];
    $application = $db->fetch(
        "SELECT cv_storage_name, cv_original_name
         FROM hiring_applications
         WHERE id = ?
         LIMIT 1",
        [$applicationId]
    );

    $storedName = (string) ($application['cv_storage_name'] ?? '');
    if (!$application || !preg_match('/\A[a-f0-9]{32}\.pdf\z/', $storedName)) {
        http_response_code(404);
        exit('CV not found.');
    }

    $cvPath = getHiringCvStorageDirectory() . DIRECTORY_SEPARATOR . $storedName;
    if (!is_file($cvPath) || !is_readable($cvPath)) {
        http_response_code(404);
        exit('CV not found.');
    }

    $downloadName = basename(str_replace('\\', '/', (string) $application['cv_original_name']));
    $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
    if ($downloadName === '' || $downloadName === null) {
        $downloadName = 'applicant-cv.pdf';
    }

    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($cvPath));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    readfile($cvPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your form session expired. Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'update_hero_image') {
        $newImageFilename = null;
        try {
            $newImageFilename = saveHiringImageUpload($_FILES['hero_image'] ?? null);
            $removeImage = isset($_POST['remove_hero_image']) && $newImageFilename === null;
            if ($newImageFilename === null && !$removeImage) {
                throw new InvalidArgumentException('Choose a hero image to upload, or select remove current image.');
            }

            $existingImage = $db->fetch(
                "SELECT image_filename FROM hiring_page_images WHERE image_key = 'hero'"
            );
            $db->beginTransaction();
            if ($removeImage) {
                $db->delete("DELETE FROM hiring_page_images WHERE image_key = 'hero'");
            } else {
                $db->insert(
                    "INSERT INTO hiring_page_images (image_key, image_filename)
                     VALUES ('hero', ?)
                     ON DUPLICATE KEY UPDATE image_filename = VALUES(image_filename), updated_at = NOW()",
                    [$newImageFilename]
                );
            }
            $db->commit();

            if (!empty($existingImage['image_filename']) && ($removeImage || $newImageFilename !== null)) {
                if (!deleteHiringImageFile($existingImage['image_filename'])) {
                    createLog('HIRING_IMAGE_CLEANUP_ERROR', 'Could not remove the previous hiring hero image.');
                }
            }
            createLog('HIRING_HERO_IMAGE_UPDATED', $removeImage ? 'Hiring hero image removed' : 'Hiring hero image replaced');
            setFlashMessage('success', $removeImage ? 'Hiring hero image removed.' : 'Hiring hero image updated.');
            redirect(APP_URL . '/admin/hiring.php');
        } catch (PDOException $e) {
            if ($db->getConnection()->inTransaction()) {
                $db->rollBack();
            }
            if ($newImageFilename !== null) {
                deleteHiringImageFile($newImageFilename);
            }
            createLog('HIRING_HERO_IMAGE_ERROR', 'Database error: ' . $e->getMessage());
            $errors[] = 'The hiring hero image could not be saved. Please try again.';
        } catch (InvalidArgumentException | RuntimeException $e) {
            if ($newImageFilename !== null) {
                deleteHiringImageFile($newImageFilename);
            }
            $errors[] = $e->getMessage();
        }
    } elseif (($_POST['action'] ?? '') === 'update_opening') {
        $openingId = (int) ($_POST['opening_id'] ?? 0);
        $openingOverrides[$openingId] = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'requirements' => trim((string) ($_POST['requirements'] ?? '')),
            'branch' => trim((string) ($_POST['branch'] ?? '')),
            'schedule' => trim((string) ($_POST['schedule'] ?? '')),
            'candidate_preference' => trim((string) ($_POST['candidate_preference'] ?? '')),
            'role_advantage' => trim((string) ($_POST['role_advantage'] ?? '')),
            'application_limit' => trim((string) ($_POST['application_limit'] ?? '')),
            'is_open' => isset($_POST['is_open']) ? 1 : 0,
        ];
        $openingForm = $openingOverrides[$openingId];

        if ($openingForm['title'] === '' || strlen($openingForm['title']) > 180) {
            $errors[] = 'Enter a role title of up to 180 characters.';
        }
        if (strlen($openingForm['description']) > 10000 || strlen($openingForm['requirements']) > 10000) {
            $errors[] = 'Role details and requirements must each be 10,000 characters or fewer.';
        }
        if (strlen($openingForm['branch']) > 180 || strlen($openingForm['schedule']) > 180) {
            $errors[] = 'Branch and working days must each be 180 characters or fewer.';
        }
        if (strlen($openingForm['candidate_preference']) > 255) {
            $errors[] = 'Candidate profile must be 255 characters or fewer.';
        }
        if (strlen($openingForm['role_advantage']) > 255) {
            $errors[] = 'Added advantage must be 255 characters or fewer.';
        }

        $applicationLimit = null;
        if ($openingForm['application_limit'] !== '') {
            $parsedLimit = filter_var($openingForm['application_limit'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 2147483647],
            ]);
            if ($parsedLimit === false) {
                $errors[] = 'The application limit must be a positive whole number, or left blank for no limit.';
            } else {
                $applicationLimit = (int) $parsedLimit;
            }
        }

        if ($openingForm['is_open'] && ($openingForm['description'] === '' || $openingForm['requirements'] === '')) {
            $errors[] = 'Add the role description and requirements before opening applications.';
        }

        if (empty($errors)) {
            $newImageFilename = null;
            try {
                $newImageFilename = saveHiringImageUpload($_FILES['opening_image'] ?? null);
                $removeImage = isset($_POST['remove_opening_image']) && $newImageFilename === null;
                $imageKey = 'role:' . $openingId;
                $existingImage = $db->fetch(
                    "SELECT image_filename FROM hiring_page_images WHERE image_key = ?",
                    [$imageKey]
                );

                $db->beginTransaction();
                $openingExists = $db->fetch(
                    "SELECT id FROM hiring_openings WHERE id = ? FOR UPDATE",
                    [$openingId]
                );

                if (!$openingExists) {
                    $db->rollBack();
                    if ($newImageFilename !== null) {
                        deleteHiringImageFile($newImageFilename);
                        $newImageFilename = null;
                    }
                    $errors[] = 'The selected role could not be found.';
                } else {
                    $countResult = $db->fetch(
                        "SELECT COUNT(*) AS application_count
                         FROM hiring_applications
                         WHERE opening_id = ?",
                        [$openingId]
                    );
                    $applicationCount = (int) ($countResult['application_count'] ?? 0);
                    $isOpen = $openingForm['is_open'];
                    $limitReached = $isOpen && $applicationLimit !== null && $applicationCount >= $applicationLimit;

                    if ($limitReached) {
                        $isOpen = 0;
                    }

                    $db->update(
                        "UPDATE hiring_openings
                         SET title = ?, description = ?, requirements = ?, branch = ?, schedule = ?,
                             candidate_preference = ?, role_advantage = ?, application_limit = ?,
                             is_open = ?, updated_at = NOW()
                         WHERE id = ?",
                        [
                            $openingForm['title'],
                            $openingForm['description'] !== '' ? $openingForm['description'] : null,
                            $openingForm['requirements'] !== '' ? $openingForm['requirements'] : null,
                            $openingForm['branch'] !== '' ? $openingForm['branch'] : null,
                            $openingForm['schedule'] !== '' ? $openingForm['schedule'] : null,
                            $openingForm['candidate_preference'] !== '' ? $openingForm['candidate_preference'] : null,
                            $openingForm['role_advantage'] !== '' ? $openingForm['role_advantage'] : null,
                            $applicationLimit,
                            $isOpen,
                            $openingId,
                        ]
                    );
                    if ($removeImage) {
                        $db->delete("DELETE FROM hiring_page_images WHERE image_key = ?", [$imageKey]);
                    } elseif ($newImageFilename !== null) {
                        $db->insert(
                            "INSERT INTO hiring_page_images (image_key, image_filename)
                             VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE image_filename = VALUES(image_filename), updated_at = NOW()",
                            [$imageKey, $newImageFilename]
                        );
                    }
                    $db->commit();

                    if (
                        !empty($existingImage['image_filename'])
                        && ($removeImage || $newImageFilename !== null)
                        && !deleteHiringImageFile($existingImage['image_filename'])
                    ) {
                        createLog('HIRING_IMAGE_CLEANUP_ERROR', 'Could not remove previous image for opening #' . $openingId);
                    }
                    createLog('HIRING_OPENING_UPDATED', 'Hiring opening #' . $openingId . ' updated');
                    setFlashMessage(
                        $limitReached ? 'warning' : 'success',
                        $limitReached
                            ? 'The role was saved as closed because it has already reached its application limit.'
                            : 'Hiring opening updated successfully.'
                    );
                    redirect(APP_URL . '/admin/hiring.php');
                }
            } catch (PDOException $e) {
                if ($db->getConnection()->inTransaction()) {
                    $db->rollBack();
                }
                if ($newImageFilename !== null) {
                    deleteHiringImageFile($newImageFilename);
                }
                createLog('HIRING_OPENING_ERROR', 'Database error: ' . $e->getMessage());
                $errors[] = 'The role could not be saved. Please try again.';
            } catch (InvalidArgumentException | RuntimeException $e) {
                if ($db->getConnection()->inTransaction()) {
                    $db->rollBack();
                }
                if ($newImageFilename !== null) {
                    deleteHiringImageFile($newImageFilename);
                }
                $errors[] = $e->getMessage();
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete_application') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $applicationsPage = max(1, (int) ($_POST['applications_page'] ?? 1));

        if ($applicationId < 1) {
            $errors[] = 'Choose a valid application to delete.';
        } else {
            try {
                $db->beginTransaction();
                $application = $db->fetch(
                    "SELECT cv_storage_name
                     FROM hiring_applications
                     WHERE id = ?
                     FOR UPDATE",
                    [$applicationId]
                );

                if (!$application) {
                    $db->rollBack();
                    $errors[] = 'The selected application could not be found.';
                } elseif (!preg_match('/\A[a-f0-9]{32}\.pdf\z/', (string) $application['cv_storage_name'])) {
                    $db->rollBack();
                    createLog('HIRING_APPLICATION_DELETE_ERROR', 'Invalid private CV filename for application #' . $applicationId);
                    $errors[] = 'The application could not be safely deleted. Please contact support.';
                } else {
                    $db->delete("DELETE FROM hiring_applications WHERE id = ?", [$applicationId]);
                    $db->commit();

                    $cvPath = getHiringCvStorageDirectory() . DIRECTORY_SEPARATOR . $application['cv_storage_name'];
                    $cvCleanupFailed = is_file($cvPath) && !unlink($cvPath);
                    if ($cvCleanupFailed) {
                        createLog('HIRING_APPLICATION_CV_CLEANUP_ERROR', 'Could not remove private CV for deleted application #' . $applicationId);
                    }

                    createLog('HIRING_APPLICATION_DELETED', 'Application #' . $applicationId . ' deleted');
                    setFlashMessage(
                        $cvCleanupFailed ? 'warning' : 'success',
                        $cvCleanupFailed
                            ? 'The application was deleted, but its private CV could not be removed. Please contact support.'
                            : 'Application and its private CV deleted.'
                    );
                    redirect(APP_URL . '/admin/hiring.php?applications_page=' . $applicationsPage . '#applications');
                }
            } catch (PDOException $e) {
                if ($db->getConnection()->inTransaction()) {
                    $db->rollBack();
                }
                createLog('HIRING_APPLICATION_DELETE_ERROR', 'Database error: ' . $e->getMessage());
                $errors[] = 'The application could not be deleted. Please try again.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'update_application_status') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));

        if ($applicationId < 1 || !in_array($status, $applicationStatuses, true)) {
            $errors[] = 'Choose a valid application status.';
        } else {
            try {
                $applicationExists = $db->fetch(
                    "SELECT id FROM hiring_applications WHERE id = ?",
                    [$applicationId]
                );
                if (!$applicationExists) {
                    $errors[] = 'The selected application could not be found.';
                } else {
                    $db->update(
                        "UPDATE hiring_applications SET status = ?, updated_at = NOW() WHERE id = ?",
                        [$status, $applicationId]
                    );
                    createLog('HIRING_APPLICATION_STATUS_UPDATED', 'Application #' . $applicationId . ' set to ' . $status);
                    setFlashMessage('success', 'Application status updated.');
                    $applicationsPage = max(1, (int) ($_POST['applications_page'] ?? 1));
                    redirect(APP_URL . '/admin/hiring.php?applications_page=' . $applicationsPage . '#applications');
                }
            } catch (PDOException $e) {
                createLog('HIRING_APPLICATION_STATUS_ERROR', 'Database error: ' . $e->getMessage());
                $errors[] = 'The application status could not be updated. Please try again.';
            }
        }
    } else {
        $errors[] = 'The requested action is not available.';
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
$totalApplicationsResult = $db->fetch("SELECT COUNT(*) AS total FROM hiring_applications");
$totalApplications = (int) ($totalApplicationsResult['total'] ?? 0);
$applicationsPerPage = 50;
$totalApplicationPages = max(1, (int) ceil($totalApplications / $applicationsPerPage));
$applicationsPage = max(1, min($totalApplicationPages, (int) ($_GET['applications_page'] ?? 1)));
$applicationsOffset = ($applicationsPage - 1) * $applicationsPerPage;
$applications = $db->fetchAll(
    "SELECT id, opening_title_snapshot, application_code, applicant_name, applicant_email, applicant_phone,
            cover_letter, cv_original_name, status, submitted_at
     FROM hiring_applications
     ORDER BY submitted_at DESC, id DESC
     LIMIT {$applicationsPerPage} OFFSET {$applicationsOffset}"
);
$firstApplicationNumber = $totalApplications > 0 ? $applicationsOffset + 1 : 0;
$lastApplicationNumber = min($totalApplications, $applicationsOffset + count($applications));

require_once __DIR__ . '/inc/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="mb-4">
        <h2 class="mb-1">Hiring Portal</h2>
        <p class="text-muted mb-0">Manage role details, application limits, and candidate applications.</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <?php foreach ($errors as $error): ?>
        <div><?php echo sanitize($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <section class="card mb-4">
        <div class="card-header">
            <h3 class="h5 mb-0">Hiring page hero image</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($hiringImages['hero'])): ?>
            <img class="admin-hiring-image-preview admin-hiring-hero-preview mb-3"
                 src="<?php echo APP_URL; ?>/assets/uploads/hiring/<?php echo rawurlencode($hiringImages['hero']); ?>"
                 alt="Current hiring page hero">
            <?php endif; ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_hero_image">
                <div class="row align-items-end g-3">
                    <div class="col-lg-7">
                        <label class="form-label" for="hiringHeroImage">Upload hero image</label>
                        <input class="form-control" id="hiringHeroImage" name="hero_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">JPEG, PNG, or WebP; maximum 5 MB. A dark overlay is applied on the public page.</div>
                    </div>
                    <div class="col-lg-auto">
                        <button class="btn btn-primary" type="submit">Save hero image</button>
                    </div>
                    <?php if (!empty($hiringImages['hero'])): ?>
                    <div class="col-lg-auto">
                        <div class="form-check">
                            <input class="form-check-input" id="removeHiringHeroImage" name="remove_hero_image" type="checkbox" value="1">
                            <label class="form-check-label" for="removeHiringHeroImage">Remove current image</label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>

    <div class="row g-4">
        <?php foreach ($openings as $opening): ?>
        <?php
            $openingId = (int) $opening['id'];
            $roleImage = $hiringImages['role:' . $openingId] ?? '';
            $openingForm = $openingOverrides[$openingId] ?? [
                'title' => $opening['title'],
                'description' => $opening['description'] ?? '',
                'requirements' => $opening['requirements'] ?? '',
                'branch' => $opening['branch'] ?? '',
                'schedule' => $opening['schedule'] ?? '',
                'candidate_preference' => $opening['candidate_preference'] ?? '',
                'role_advantage' => $opening['role_advantage'] ?? '',
                'application_limit' => $opening['application_limit'] ?? '',
                'is_open' => (int) $opening['is_open'],
            ];
            $applicationCount = (int) $opening['application_count'];
            $applicationLimit = $opening['application_limit'] !== null ? (int) $opening['application_limit'] : null;
        ?>
        <div class="col-12 col-xl-6">
            <section class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <h3 class="h5 mb-1"><?php echo sanitize($openingForm['title']); ?></h3>
                        <span class="badge <?php echo (int) $openingForm['is_open'] === 1 ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo (int) $openingForm['is_open'] === 1 ? 'Open' : 'Closed'; ?>
                        </span>
                    </div>
                    <span class="text-muted small">
                        <?php echo $applicationCount; ?>
                        <?php echo $applicationLimit !== null ? ' / ' . $applicationLimit : ''; ?>
                        applications
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_opening">
                        <input type="hidden" name="opening_id" value="<?php echo $openingId; ?>">

                        <?php if ($roleImage !== ''): ?>
                        <img class="admin-hiring-image-preview mb-3"
                             src="<?php echo APP_URL; ?>/assets/uploads/hiring/<?php echo rawurlencode($roleImage); ?>"
                             alt="Current <?php echo sanitize($openingForm['title']); ?> role card image">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label" for="openingImage<?php echo $openingId; ?>">Role card image</label>
                            <input class="form-control" id="openingImage<?php echo $openingId; ?>" name="opening_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text">JPEG, PNG, or WebP; maximum 5 MB.</div>
                            <?php if ($roleImage !== ''): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" id="removeOpeningImage<?php echo $openingId; ?>" name="remove_opening_image" type="checkbox" value="1">
                                <label class="form-check-label" for="removeOpeningImage<?php echo $openingId; ?>">Remove current image</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="openingTitle<?php echo $openingId; ?>">Role title</label>
                            <input class="form-control" id="openingTitle<?php echo $openingId; ?>" name="title" maxlength="180" value="<?php echo sanitize($openingForm['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="openingDescription<?php echo $openingId; ?>">Role information</label>
                            <textarea class="form-control" id="openingDescription<?php echo $openingId; ?>" name="description" rows="3" maxlength="10000"><?php echo sanitize($openingForm['description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="openingRequirements<?php echo $openingId; ?>">Requirements</label>
                            <textarea class="form-control" id="openingRequirements<?php echo $openingId; ?>" name="requirements" rows="4" maxlength="10000"><?php echo sanitize($openingForm['requirements']); ?></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="openingBranch<?php echo $openingId; ?>">Branch</label>
                                <input class="form-control" id="openingBranch<?php echo $openingId; ?>" name="branch" maxlength="180" value="<?php echo sanitize($openingForm['branch']); ?>" placeholder="e.g. East Legon">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="openingSchedule<?php echo $openingId; ?>">Working days</label>
                                <input class="form-control" id="openingSchedule<?php echo $openingId; ?>" name="schedule" maxlength="180" value="<?php echo sanitize($openingForm['schedule']); ?>" placeholder="e.g. Monday, Wednesday, Friday">
                            </div>
                        </div>
                        <div class="mt-3 mb-3">
                            <label class="form-label" for="openingPreference<?php echo $openingId; ?>">Candidate profile / preference</label>
                            <input class="form-control" id="openingPreference<?php echo $openingId; ?>" name="candidate_preference" maxlength="255" value="<?php echo sanitize($openingForm['candidate_preference']); ?>" placeholder="Optional details for applicants">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="openingAdvantage<?php echo $openingId; ?>">Added advantage <span class="text-muted">(optional)</span></label>
                            <input class="form-control" id="openingAdvantage<?php echo $openingId; ?>" name="role_advantage" maxlength="255" value="<?php echo sanitize($openingForm['role_advantage']); ?>" placeholder="e.g. Living near Madina or Adenta is an advantage">
                            <div class="form-text">Displayed as a highlighted note on this role card.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="openingLimit<?php echo $openingId; ?>">Maximum applications</label>
                            <input class="form-control" id="openingLimit<?php echo $openingId; ?>" name="application_limit" type="number" min="1" step="1" value="<?php echo sanitize($openingForm['application_limit']); ?>" placeholder="Leave blank for no limit">
                            <div class="form-text">The opening closes automatically when this many applications have been received.</div>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="openingActive<?php echo $openingId; ?>" name="is_open" value="1" <?php echo (int) $openingForm['is_open'] === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="openingActive<?php echo $openingId; ?>">Accept applications for this role</label>
                        </div>
                        <button class="btn btn-primary" type="submit">Save role</button>
                    </form>
                </div>
            </section>
        </div>
        <?php endforeach; ?>
    </div>

    <section class="card mt-5" id="applications">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="h5 mb-1">Applications</h3>
                <p class="text-muted small mb-0">
                    Showing <?php echo $firstApplicationNumber; ?>-<?php echo $lastApplicationNumber; ?>
                    of <?php echo $totalApplications; ?> applications.
                </p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Applicant</th>
                        <th>Role / reference</th>
                        <th>Submitted</th>
                        <th>CV</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No applications have been submitted yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($applications as $application): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($application['applicant_name']); ?></strong>
                            <div><a href="mailto:<?php echo sanitize($application['applicant_email']); ?>"><?php echo sanitize($application['applicant_email']); ?></a></div>
                            <small class="text-muted"><?php echo sanitize($application['applicant_phone']); ?></small>
                            <?php if (!empty($application['cover_letter'])): ?>
                            <details class="mt-2">
                                <summary>Applicant note</summary>
                                <div class="small mt-1"><?php echo nl2br(sanitize($application['cover_letter'])); ?></div>
                            </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo sanitize($application['opening_title_snapshot']); ?></strong>
                            <div><code><?php echo sanitize($application['application_code']); ?></code></div>
                        </td>
                        <td><?php echo sanitize(date('M j, Y H:i', strtotime($application['submitted_at']))); ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/admin/hiring.php?download_cv=<?php echo (int) $application['id']; ?>">
                                <i class="fas fa-file-pdf me-1"></i> Download CV
                            </a>
                            <div class="small text-muted mt-1"><?php echo sanitize($application['cv_original_name']); ?></div>
                        </td>
                        <td>
                            <form method="POST" action="" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="update_application_status">
                                <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                <input type="hidden" name="applications_page" value="<?php echo $applicationsPage; ?>">
                                <select class="form-select form-select-sm" name="status" aria-label="Application status">
                                    <?php foreach ($applicationStatuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $application['status'] === $status ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($status); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-primary" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" action=""
                                  onsubmit="return confirmDelete('Permanently delete this application and its CV? This cannot be undone.')">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="delete_application">
                                <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                                <input type="hidden" name="applications_page" value="<?php echo $applicationsPage; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="fas fa-trash-alt me-1" aria-hidden="true"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalApplicationPages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Hiring application pages">
                <ul class="pagination justify-content-end mb-0">
                    <?php if ($applicationsPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo APP_URL; ?>/admin/hiring.php?applications_page=<?php echo $applicationsPage - 1; ?>#applications">Previous</a>
                    </li>
                    <?php endif; ?>
                    <li class="page-item disabled"><span class="page-link">Page <?php echo $applicationsPage; ?> of <?php echo $totalApplicationPages; ?></span></li>
                    <?php if ($applicationsPage < $totalApplicationPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo APP_URL; ?>/admin/hiring.php?applications_page=<?php echo $applicationsPage + 1; ?>#applications">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
