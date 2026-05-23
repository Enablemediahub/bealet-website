<?php
/**
 * Bealet Website - Admin Site Settings
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

try {
    $db->update("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->update("
        CREATE TABLE IF NOT EXISTS company_branches (
            id INT PRIMARY KEY AUTO_INCREMENT,
            branch_name VARCHAR(255) NOT NULL,
            phone_primary VARCHAR(50) NULL,
            phone_secondary VARCHAR(50) NULL,
            address TEXT NULL,
            google_maps_url TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    $errors[] = 'Unable to prepare site settings tables: ' . $e->getMessage();
}

if (isset($_GET['delete_branch'])) {
    $branchId = (int) $_GET['delete_branch'];
    $db->update("UPDATE company_branches SET is_active = 0 WHERE id = ?", [$branchId]);
    setFlashMessage('success', 'Branch removed successfully.');
    redirect(APP_URL . '/admin/settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'save_company') {
            $companyName = sanitize($_POST['company_name'] ?? '');
            $tagline = sanitize($_POST['tagline'] ?? '');
            $primaryPhone = sanitize($_POST['primary_phone'] ?? '');
            $secondaryPhone = sanitize($_POST['secondary_phone'] ?? '');
            $whatsappPhone = sanitize($_POST['whatsapp_phone'] ?? '');
            $email = sanitize($_POST['company_email'] ?? '');
            $facebookUrl = sanitize($_POST['facebook_url'] ?? '');
            $instagramUrl = sanitize($_POST['instagram_url'] ?? '');
            $twitterUrl = sanitize($_POST['twitter_url'] ?? '');
            $linkedinUrl = sanitize($_POST['linkedin_url'] ?? '');
            $tiktokUrl = sanitize($_POST['tiktok_url'] ?? '');
            $logoPath = sanitize($_POST['existing_logo_path'] ?? '');
            $staffHeroImage = sanitize($_POST['existing_staff_hero_image'] ?? '');
            $contactHeroImage = sanitize($_POST['existing_contact_hero_image'] ?? '');
            $blogHeroImage = sanitize($_POST['existing_blog_hero_image'] ?? '');
            $loginWallpaper = sanitize($_POST['existing_login_wallpaper'] ?? '');
            $introVideo = sanitize($_POST['existing_intro_video'] ?? '');
            $googleClientId = sanitize($_POST['google_client_id'] ?? '');
            $removeLogo = isset($_POST['remove_logo']) ? 1 : 0;
            $removeStaffHeroImage = isset($_POST['remove_staff_hero_image']) ? 1 : 0;
            $removeContactHeroImage = isset($_POST['remove_contact_hero_image']) ? 1 : 0;
            $removeBlogHeroImage = isset($_POST['remove_blog_hero_image']) ? 1 : 0;
            $removeLoginWallpaper = isset($_POST['remove_login_wallpaper']) ? 1 : 0;
            $removeIntroVideo = isset($_POST['remove_intro_video']) ? 1 : 0;

            if ($companyName === '') {
                $errors[] = 'Company name is required.';
            }

            if ($email !== '' && !validateEmail($email)) {
                $errors[] = 'Please enter a valid company email address.';
            }

            if (isset($_FILES['company_logo']) && ($_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['company_logo'], 'branding');
                if (!empty($upload['success'])) {
                    $logoPath = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Logo upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeLogo) {
                $logoPath = '';
            }

            if (isset($_FILES['staff_hero_image']) && ($_FILES['staff_hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['staff_hero_image'], 'branding');
                if (!empty($upload['success'])) {
                    $staffHeroImage = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Staff hero image upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeStaffHeroImage) {
                $staffHeroImage = '';
            }

            if (isset($_FILES['contact_hero_image']) && ($_FILES['contact_hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['contact_hero_image'], 'branding');
                if (!empty($upload['success'])) {
                    $contactHeroImage = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Contact hero image upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeContactHeroImage) {
                $contactHeroImage = '';
            }

            if (isset($_FILES['blog_hero_image']) && ($_FILES['blog_hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['blog_hero_image'], 'branding');
                if (!empty($upload['success'])) {
                    $blogHeroImage = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Blog hero image upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeBlogHeroImage) {
                $blogHeroImage = '';
            }

            if (isset($_FILES['login_wallpaper']) && ($_FILES['login_wallpaper']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['login_wallpaper'], 'branding');
                if (!empty($upload['success'])) {
                    $loginWallpaper = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Login wallpaper upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeLoginWallpaper) {
                $loginWallpaper = '';
            }

            if (isset($_FILES['intro_video']) && ($_FILES['intro_video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['intro_video'], 'branding', ['mp4', 'webm', 'ogg', 'mov'], 25 * 1024 * 1024);
                if (!empty($upload['success'])) {
                    $introVideo = 'assets/uploads/branding/' . $upload['filename'];
                } else {
                    foreach (($upload['errors'] ?? ['Intro video upload failed.']) as $uploadError) {
                        $errors[] = $uploadError;
                    }
                }
            } elseif ($removeIntroVideo) {
                $introVideo = '';
            }

            if (empty($errors)) {
                $settingsToSave = [
                    'company_name' => $companyName,
                    'tagline' => $tagline,
                    'primary_phone' => $primaryPhone,
                    'secondary_phone' => $secondaryPhone,
                    'whatsapp_phone' => $whatsappPhone,
                    'email' => $email,
                    'facebook_url' => $facebookUrl,
                    'instagram_url' => $instagramUrl,
                    'twitter_url' => $twitterUrl,
                    'linkedin_url' => $linkedinUrl,
                    'tiktok_url' => $tiktokUrl,
                    'logo_path' => $logoPath,
                    'login_wallpaper' => $loginWallpaper,
                    'intro_video' => $introVideo,
                    'google_client_id' => $googleClientId,
                    'staff_hero_image' => $staffHeroImage,
                    'contact_hero_image' => $contactHeroImage,
                    'blog_hero_image' => $blogHeroImage,
                ];

                foreach ($settingsToSave as $key => $value) {
                    $db->insert(
                        "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP",
                        [$key, $value]
                    );
                }

                resetSiteSettingsCache();
                setFlashMessage('success', 'Company settings updated successfully.');
                redirect(APP_URL . '/admin/settings.php');
            }
        }

        if ($action === 'save_branch') {
            $branchId = (int) ($_POST['branch_id'] ?? 0);
            $branchName = sanitize($_POST['branch_name'] ?? '');
            $branchPhonePrimary = sanitize($_POST['branch_phone_primary'] ?? '');
            $branchPhoneSecondary = sanitize($_POST['branch_phone_secondary'] ?? '');
            $branchAddress = sanitize($_POST['branch_address'] ?? '');
            $branchMapsUrl = sanitize($_POST['google_maps_url'] ?? '');
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);

            if ($branchName === '') {
                $errors[] = 'Branch name is required.';
            }

            if ($branchId > 0 && empty($errors)) {
                $db->update(
                    "UPDATE company_branches
                     SET branch_name = ?, phone_primary = ?, phone_secondary = ?, address = ?, google_maps_url = ?, sort_order = ?, is_active = 1
                     WHERE id = ?",
                    [$branchName, $branchPhonePrimary, $branchPhoneSecondary, $branchAddress, $branchMapsUrl, $sortOrder, $branchId]
                );
                setFlashMessage('success', 'Branch updated successfully.');
            } elseif (empty($errors)) {
                $db->insert(
                    "INSERT INTO company_branches (branch_name, phone_primary, phone_secondary, address, google_maps_url, sort_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$branchName, $branchPhonePrimary, $branchPhoneSecondary, $branchAddress, $branchMapsUrl, $sortOrder]
                );
                setFlashMessage('success', 'Branch added successfully.');
            }

            if (empty($errors)) {
                redirect(APP_URL . '/admin/settings.php');
            }
        }
    }
}

$siteSettings = getSiteSettings();
$branches = tableExists('company_branches')
    ? $db->fetchAll("SELECT * FROM company_branches WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")
    : [];
$branchCatalog = [];

foreach ($branches as $branch) {
    $branchCatalog[$branch['id']] = [
        'id' => (int) $branch['id'],
        'branch_name' => $branch['branch_name'] ?? '',
        'phone_primary' => $branch['phone_primary'] ?? '',
        'phone_secondary' => $branch['phone_secondary'] ?? '',
        'address' => $branch['address'] ?? '',
        'google_maps_url' => $branch['google_maps_url'] ?? '',
        'sort_order' => (string) ($branch['sort_order'] ?? '0'),
    ];
}

require_once __DIR__ . '/inc/header.php';
?>

        <div class="container-fluid mt-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Site Settings</h2>
                    <p class="text-muted">Manage company branding, contact details, and branch locations.</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                <div><?php echo sanitize($error); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="mb-3">Company Profile</h4>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="save_company">
                                <input type="hidden" name="existing_logo_path" value="<?php echo sanitize((string) ($siteSettings['logo_path'] ?? '')); ?>">
                                <input type="hidden" name="existing_staff_hero_image" value="<?php echo sanitize((string) ($siteSettings['staff_hero_image'] ?? '')); ?>">
                                <input type="hidden" name="existing_contact_hero_image" value="<?php echo sanitize((string) ($siteSettings['contact_hero_image'] ?? '')); ?>">
                                <input type="hidden" name="existing_blog_hero_image" value="<?php echo sanitize((string) ($siteSettings['blog_hero_image'] ?? '')); ?>">
                                <input type="hidden" name="existing_login_wallpaper" value="<?php echo sanitize((string) ($siteSettings['login_wallpaper'] ?? '')); ?>">
                                <input type="hidden" name="existing_intro_video" value="<?php echo sanitize((string) ($siteSettings['intro_video'] ?? '')); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" value="<?php echo sanitize(getCompanyName()); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Tagline</label>
                                    <input type="text" class="form-control" name="tagline" value="<?php echo sanitize((string) ($siteSettings['tagline'] ?? '')); ?>" placeholder="You can add this later">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Primary Phone</label>
                                        <input type="text" class="form-control" name="primary_phone" value="<?php echo sanitize((string) ($siteSettings['primary_phone'] ?? '')); ?>" placeholder="+233 24 000 0000" inputmode="tel">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Secondary Phone</label>
                                        <input type="text" class="form-control" name="secondary_phone" value="<?php echo sanitize((string) ($siteSettings['secondary_phone'] ?? '')); ?>" placeholder="+233 20 000 0000" inputmode="tel">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">WhatsApp Number</label>
                                    <input type="text" class="form-control" name="whatsapp_phone" value="<?php echo sanitize((string) ($siteSettings['whatsapp_phone'] ?? '')); ?>" placeholder="+233 24 000 0000" inputmode="tel">
                                    <small class="text-muted">This powers the floating WhatsApp button on the public website.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Company Email</label>
                                    <input type="email" class="form-control" name="company_email" value="<?php echo sanitize((string) ($siteSettings['email'] ?? '')); ?>">
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-3">
                                    <h5 class="mb-3">Social Media URLs</h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Facebook URL</label>
                                            <input type="url" class="form-control" name="facebook_url" value="<?php echo sanitize((string) ($siteSettings['facebook_url'] ?? '')); ?>" placeholder="https://facebook.com/your-page">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Instagram URL</label>
                                            <input type="url" class="form-control" name="instagram_url" value="<?php echo sanitize((string) ($siteSettings['instagram_url'] ?? '')); ?>" placeholder="https://instagram.com/your-page">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Twitter URL</label>
                                            <input type="url" class="form-control" name="twitter_url" value="<?php echo sanitize((string) ($siteSettings['twitter_url'] ?? '')); ?>" placeholder="https://x.com/your-page">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">LinkedIn URL</label>
                                            <input type="url" class="form-control" name="linkedin_url" value="<?php echo sanitize((string) ($siteSettings['linkedin_url'] ?? '')); ?>" placeholder="https://linkedin.com/company/your-page">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">TikTok URL</label>
                                            <input type="url" class="form-control" name="tiktok_url" value="<?php echo sanitize((string) ($siteSettings['tiktok_url'] ?? '')); ?>" placeholder="https://tiktok.com/@your-page">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Upload Logo</label>
                                    <input type="file" class="form-control" name="company_logo" accept=".jpg,.jpeg,.png,.gif,.webp">
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo getSiteLogoUrl(); ?>" alt="<?php echo sanitize(getCompanyName()); ?>" style="width: 88px; height: 88px; object-fit: contain; border-radius: 16px; background: #fff; border: 1px solid #e2e8f0; padding: 10px;">
                                        <div>
                                            <div class="fw-semibold">Current Logo</div>
                                            <div class="text-muted small"><?php echo sanitize((string) ($siteSettings['logo_path'] ?? LOGO_URL)); ?></div>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_logo" id="removeLogo">
                                                <label class="form-check-label" for="removeLogo">Remove uploaded logo and use fallback</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Staff Page Hero Image</label>
                                    <input type="file" class="form-control" name="staff_hero_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                    <small class="text-muted">Used only on the Staff page hero section.</small>
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-3">
                                    <div class="fw-semibold mb-2">Current Staff Hero Image</div>
                                    <img src="<?php echo getStaffHeroImageUrl(); ?>" alt="Staff page hero" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 18px; border: 1px solid #e2e8f0;">
                                    <div class="text-muted small mt-2"><?php echo sanitize((string) ($siteSettings['staff_hero_image'] ?? 'Fallback image')); ?></div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_staff_hero_image" id="removeStaffHeroImage">
                                        <label class="form-check-label" for="removeStaffHeroImage">Remove custom staff hero image and use fallback</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Login Page Wallpaper</label>
                                    <input type="file" class="form-control" name="login_wallpaper" accept=".jpg,.jpeg,.png,.gif,.webp">
                                    <small class="text-muted">Displayed as the public login page background.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Contact Page Hero Image</label>
                                    <input type="file" class="form-control" name="contact_hero_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                    <small class="text-muted">Used only on the slim Contact page hero section.</small>
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-3">
                                    <div class="fw-semibold mb-2">Current Contact Hero Image</div>
                                    <img src="<?php echo getContactHeroImageUrl(); ?>" alt="Contact page hero" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 18px; border: 1px solid #e2e8f0;">
                                    <div class="text-muted small mt-2"><?php echo sanitize((string) ($siteSettings['contact_hero_image'] ?? 'Fallback image')); ?></div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_contact_hero_image" id="removeContactHeroImage">
                                        <label class="form-check-label" for="removeContactHeroImage">Remove custom contact hero image and use fallback</label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Blog Page Hero Image</label>
                                    <input type="file" class="form-control" name="blog_hero_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                                    <small class="text-muted">Used only on the slim Blog page hero section.</small>
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-3">
                                    <div class="fw-semibold mb-2">Current Blog Hero Image</div>
                                    <img src="<?php echo getBlogHeroImageUrl(); ?>" alt="Blog page hero" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 18px; border: 1px solid #e2e8f0;">
                                    <div class="text-muted small mt-2"><?php echo sanitize((string) ($siteSettings['blog_hero_image'] ?? 'Fallback image')); ?></div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_blog_hero_image" id="removeBlogHeroImage">
                                        <label class="form-check-label" for="removeBlogHeroImage">Remove custom blog hero image and use fallback</label>
                                    </div>
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-3">
                                    <div class="fw-semibold mb-2">Current Login Wallpaper</div>
                                    <img src="<?php echo getLoginWallpaperUrl(); ?>" alt="Login wallpaper" style="width: 100%; max-height: 220px; object-fit: cover; border-radius: 18px; border: 1px solid #e2e8f0;">
                                    <div class="text-muted small mt-2"><?php echo sanitize((string) ($siteSettings['login_wallpaper'] ?? 'Fallback wallpaper')); ?></div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_login_wallpaper" id="removeLoginWallpaper">
                                        <label class="form-check-label" for="removeLoginWallpaper">Remove custom login wallpaper and use fallback</label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Homepage Intro Video</label>
                                    <input type="file" class="form-control" name="intro_video" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov">
                                    <small class="text-muted">This full-screen intro plays when visitors land on the homepage. Short MP4/WebM videos work best.</small>
                                </div>

                                <div class="border rounded-3 p-3 bg-light mb-4">
                                    <div class="fw-semibold mb-2">Current Homepage Intro Video</div>
                                    <?php if (getIntroVideoUrl() !== ''): ?>
                                    <video
                                        src="<?php echo sanitize(getIntroVideoUrl()); ?>"
                                        controls
                                        muted
                                        playsinline
                                        style="width: 100%; max-height: 260px; border-radius: 18px; border: 1px solid #e2e8f0; background: #020617;"
                                    ></video>
                                    <?php else: ?>
                                    <div class="border rounded-4 d-flex align-items-center justify-content-center text-muted" style="min-height: 180px; background: #fff;">
                                        No intro video uploaded yet.
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-2"><?php echo sanitize((string) ($siteSettings['intro_video'] ?? 'No custom intro video')); ?></div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_intro_video" id="removeIntroVideo">
                                        <label class="form-check-label" for="removeIntroVideo">Remove homepage intro video</label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Google Client ID</label>
                                    <input type="text" class="form-control" name="google_client_id" value="<?php echo sanitize((string) ($siteSettings['google_client_id'] ?? '')); ?>" placeholder="Paste your Google OAuth Client ID">
                                    <small class="text-muted">Used to show a Continue with Google button on the public login page.</small>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Company Settings</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="mb-3">Navbar Preview</h4>
                            <div class="border rounded-4 p-3 bg-white">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width: 74px; height: 74px; border-radius: 20px; background: #eff6ff; border: 1px solid #bfdbfe; display: grid; place-items: center;">
                                        <img src="<?php echo getSiteLogoUrl(); ?>" alt="<?php echo sanitize(getCompanyName()); ?>" style="max-width: 52px; max-height: 52px; object-fit: contain;">
                                    </div>
                                    <div>
                                        <div style="font-size: 1.2rem; font-weight: 800; color: #0f172a;"><?php echo sanitize(getCompanyName()); ?></div>
                                        <div style="font-size: 0.82rem; color: #64748b; min-height: 1.25rem;"><?php echo sanitize(getCompanyTagline()); ?></div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-2">Saved Contact Info</h5>
                            <p class="mb-1"><strong>Primary Phone:</strong> <?php echo sanitize((string) ($siteSettings['primary_phone'] ?? 'Not set')); ?></p>
                            <p class="mb-1"><strong>Secondary Phone:</strong> <?php echo sanitize((string) ($siteSettings['secondary_phone'] ?? 'Not set')); ?></p>
                            <p class="mb-1"><strong>WhatsApp:</strong> <?php echo sanitize((string) ($siteSettings['whatsapp_phone'] ?? 'Not set')); ?></p>
                            <p class="mb-3"><strong>Email:</strong> <?php echo sanitize((string) ($siteSettings['email'] ?? 'Not set')); ?></p>

                            <h5 class="mb-2">Working Hours</h5>
                            <p class="mb-1"><strong>Monday - Friday:</strong> 8:00 AM - 5:00 PM</p>
                            <p class="mb-3"><strong>Saturday:</strong> 8:00 AM - 2:00 PM</p>

                            <h5 class="mb-2">Social Links</h5>
                            <p class="mb-1"><strong>Facebook:</strong> <?php echo sanitize((string) ($siteSettings['facebook_url'] ?? 'Not set')); ?></p>
                            <p class="mb-1"><strong>Instagram:</strong> <?php echo sanitize((string) ($siteSettings['instagram_url'] ?? 'Not set')); ?></p>
                            <p class="mb-1"><strong>Twitter:</strong> <?php echo sanitize((string) ($siteSettings['twitter_url'] ?? 'Not set')); ?></p>
                            <p class="mb-1"><strong>LinkedIn:</strong> <?php echo sanitize((string) ($siteSettings['linkedin_url'] ?? 'Not set')); ?></p>
                            <p class="mb-0"><strong>TikTok:</strong> <?php echo sanitize((string) ($siteSettings['tiktok_url'] ?? 'Not set')); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1">Branches</h4>
                            <p class="text-muted mb-0">Add branch addresses, phones, and Google Maps links.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="newBranchButton">
                            <i class="fas fa-plus me-2"></i>New Branch
                        </button>
                    </div>

                    <form method="POST" class="border rounded-4 p-3 bg-light mb-4" id="branchForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="save_branch">
                        <input type="hidden" name="branch_id" id="branchId" value="">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Branch Name</label>
                                <input type="text" class="form-control" name="branch_name" id="branchName" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Primary Phone</label>
                                <input type="text" class="form-control" name="branch_phone_primary" id="branchPhonePrimary" placeholder="+233 24 000 0000" inputmode="tel">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Secondary Phone</label>
                                <input type="text" class="form-control" name="branch_phone_secondary" id="branchPhoneSecondary" placeholder="+233 20 000 0000" inputmode="tel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Branch Address</label>
                                <textarea class="form-control" name="branch_address" id="branchAddress" rows="3"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Google Maps URL</label>
                                <input type="url" class="form-control" name="google_maps_url" id="branchMapsUrl" placeholder="https://maps.google.com/...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" id="branchSortOrder" value="0">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary" id="branchSubmitButton">Save Branch</button>
                            <button type="button" class="btn btn-secondary" id="branchResetButton">Reset</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Branch</th>
                                    <th>Phones</th>
                                    <th>Address</th>
                                    <th>Map</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($branches)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No branches added yet.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo sanitize($branch['branch_name']); ?></strong><br>
                                        <small class="text-muted">Sort: <?php echo (int) ($branch['sort_order'] ?? 0); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo sanitize((string) ($branch['phone_primary'] ?? '')); ?></div>
                                        <small class="text-muted"><?php echo sanitize((string) ($branch['phone_secondary'] ?? '')); ?></small>
                                    </td>
                                    <td><?php echo nl2br(sanitize((string) ($branch['address'] ?? ''))); ?></td>
                                    <td>
                                        <?php if (!empty($branch['google_maps_url'])): ?>
                                        <a href="<?php echo sanitize((string) $branch['google_maps_url']); ?>" target="_blank" rel="noopener noreferrer">Open Map</a>
                                        <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editBranch(<?php echo (int) $branch['id']; ?>)">Edit</button>
                                        <a href="?delete_branch=<?php echo (int) $branch['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmDelete('Remove this branch?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const branchCatalog = <?php echo json_encode($branchCatalog, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function resetBranchForm() {
            document.getElementById('branchForm')?.reset();
            document.getElementById('branchId').value = '';
            document.getElementById('branchSubmitButton').textContent = 'Save Branch';
            document.getElementById('branchSortOrder').value = '0';
        }

        function editBranch(branchId) {
            const branch = branchCatalog[String(branchId)] || branchCatalog[branchId];
            if (!branch) {
                return;
            }

            document.getElementById('branchId').value = branch.id || '';
            document.getElementById('branchName').value = branch.branch_name || '';
            document.getElementById('branchPhonePrimary').value = branch.phone_primary || '';
            document.getElementById('branchPhoneSecondary').value = branch.phone_secondary || '';
            document.getElementById('branchAddress').value = branch.address || '';
            document.getElementById('branchMapsUrl').value = branch.google_maps_url || '';
            document.getElementById('branchSortOrder').value = branch.sort_order || '0';
            document.getElementById('branchSubmitButton').textContent = 'Update Branch';
            window.scrollTo({ top: document.getElementById('branchForm').offsetTop - 100, behavior: 'smooth' });
        }

        document.getElementById('branchResetButton')?.addEventListener('click', resetBranchForm);
        document.getElementById('newBranchButton')?.addEventListener('click', resetBranchForm);
        </script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
