<?php
/**
 * Bealet Website - Contact Page
 */

require_once __DIR__ . '/inc/header.php';

global $db;

$siteSettings = getSiteSettings();
$branches = getCompanyBranches();
$companyEmail = trim((string) ($siteSettings['email'] ?? ''));
$primaryPhone = trim((string) ($siteSettings['primary_phone'] ?? ''));
$secondaryPhone = trim((string) ($siteSettings['secondary_phone'] ?? ''));
$businessHours = getBusinessHours();
$contactHeroImage = getContactHeroImageUrl();

if (!function_exists('buildBranchMapEmbedUrl')) {
    function buildBranchMapEmbedUrl($branch) {
        $mapsUrl = trim((string) ($branch['google_maps_url'] ?? ''));
        $address = trim((string) ($branch['address'] ?? ''));
        $branchName = trim((string) ($branch['branch_name'] ?? ''));
        $query = $address !== '' ? $address : $branchName;

        if ($mapsUrl !== '') {
            if (stripos($mapsUrl, 'output=embed') !== false || stripos($mapsUrl, '/maps/embed') !== false) {
                return $mapsUrl;
            }

            if ($query !== '') {
                return 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
            }

            return $mapsUrl;
        }

        if ($query !== '') {
            return 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
        }

        return '';
    }
}

if (!function_exists('buildBranchMapUrl')) {
    function buildBranchMapUrl($branch) {
        $mapsUrl = trim((string) ($branch['google_maps_url'] ?? ''));
        $address = trim((string) ($branch['address'] ?? ''));
        $branchName = trim((string) ($branch['branch_name'] ?? ''));
        $query = $address !== '' ? $address : $branchName;

        if ($mapsUrl !== '') {
            return $mapsUrl;
        }

        if ($query !== '') {
            return 'https://www.google.com/maps?q=' . rawurlencode($query);
        }

        return '';
    }
}

$errors = [];
$success = false;
$formData = [
    'name' => '',
    'email' => '',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        
        $formData = ['name' => $name, 'email' => $email, 'message' => $message];
        
        // Validate
        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'Please enter a valid name';
        }
        
        if (empty($email) || !validateEmail($email)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($message) || strlen($message) < 10) {
            $errors[] = 'Please enter a message (at least 10 characters)';
        }
        
        // Save and send
        if (empty($errors)) {
            try {
                $db->insert(
                    "INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)",
                    [$name, $email, $message]
                );
                
                // Send email to admin
                $adminEmail = 'admin@bealet.com';
                $subject = 'New Contact Form Submission - ' . APP_NAME;
                $body = "
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> $name</p>
                <p><strong>Email:</strong> $email</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br($message) . "</p>
                <p><a href='" . APP_URL . "/admin/contacts.php'>View in Admin Panel</a></p>
                ";
                
                sendEmail($adminEmail, $subject, $body);
                
                // Send confirmation to user
                $userSubject = 'We received your message - ' . APP_NAME;
                $userBody = "
                <h2>Thank you for contacting us!</h2>
                <p>Dear $name,</p>
                <p>We have received your message and will get back to you as soon as possible.</p>
                <p>Best regards,<br>" . APP_NAME . " Team</p>
                ";
                
                sendEmail($email, $userSubject, $userBody);
                
                $success = true;
                $formData = ['name' => '', 'email' => '', 'message' => ''];
                createLog('CONTACT_FORM_SUBMITTED', 'From: ' . $email);
                setFlashMessage('success', 'Thank you for your message! We will get back to you soon.');
            } catch (Exception $e) {
                createLog('CONTACT_FORM_ERROR', 'Error: ' . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }
    }
}

?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item active">Contact</li>
            </ol>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="contact-hero mb-5">
        <div class="container-lg">
            <div class="contact-hero-panel contact-hero-panel--image text-center" style="--page-hero-image: url('<?php echo sanitize($contactHeroImage); ?>');">
                <h1 class="mb-3">Get in Touch</h1>
                <p class="lead mb-0">We'd love to hear from you. Send us a message!</p>
            </div>
        </div>
    </section>
    
    <!-- Contact Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="row g-5">
                <!-- Contact Information -->
                <div class="col-lg-4">
                    <!-- Phone -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <i class="fas fa-phone" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h5>Phone</h5>
                            <p class="text-muted mb-2">Call us during business hours</p>
                            <?php if ($primaryPhone !== ''): ?>
                            <a href="tel:<?php echo sanitize(formatPhoneForTel($primaryPhone)); ?>" class="text-primary fw-bold d-block"><?php echo sanitize($primaryPhone); ?></a>
                            <?php else: ?>
                            <span class="text-muted">Primary phone not set</span>
                            <?php endif; ?>
                            <?php if ($secondaryPhone !== ''): ?>
                            <a href="tel:<?php echo sanitize(formatPhoneForTel($secondaryPhone)); ?>" class="text-primary fw-bold d-block mt-2"><?php echo sanitize($secondaryPhone); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h5>Email</h5>
                            <p class="text-muted mb-2">Send us an email anytime</p>
                            <?php if ($companyEmail !== ''): ?>
                            <a href="mailto:<?php echo sanitize($companyEmail); ?>" class="text-primary fw-bold"><?php echo sanitize($companyEmail); ?></a>
                            <?php else: ?>
                            <span class="text-muted">Company email not set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-map-pin" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h5>Branches</h5>
                            <?php if (!empty($branches)): ?>
                                <?php foreach ($branches as $branch): ?>
                                <div class="mb-3 text-start">
                                    <div class="fw-semibold"><?php echo sanitize($branch['branch_name'] ?? 'Branch'); ?></div>
                                    <?php if (!empty($branch['address'])): ?>
                                    <div class="text-muted small"><?php echo nl2br(sanitize((string) $branch['address'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted mb-0">Branch locations have not been added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Thank you!</strong> Your message has been sent successfully. We'll get back to you soon.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <!-- Name -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($formData['name']); ?>" required>
                                    </div>
                                </div>
                                
                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Your Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize($formData['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <!-- Subject -->
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" placeholder="How can we help?" disabled>
                                </div>
                                
                                <!-- Message -->
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="6" placeholder="Your message..." required><?php echo sanitize($formData['message']); ?></textarea>
                                    <small class="text-muted">Minimum 10 characters</small>
                                </div>
                                
                                <!-- Submit -->
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane me-2"></i> Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-spacing pt-0">
        <div class="container-lg">
            <div class="section-title">
                <h2>Our Branch Locations</h2>
                <p>Find each branch, view contact numbers, and open directions.</p>
            </div>

            <?php if (!empty($branches)): ?>
            <div class="row g-4">
                <?php foreach ($branches as $branch): ?>
                <?php
                    $branchMapEmbed = buildBranchMapEmbedUrl($branch);
                    $branchMapsUrl = buildBranchMapUrl($branch);
                    $branchPhonePrimary = trim((string) ($branch['phone_primary'] ?? ''));
                    $branchPhoneSecondary = trim((string) ($branch['phone_secondary'] ?? ''));
                ?>
                <div class="col-lg-6">
                    <div class="card h-100 overflow-hidden">
                        <?php if ($branchMapEmbed !== ''): ?>
                        <iframe
                            src="<?php echo sanitize($branchMapEmbed); ?>"
                            title="<?php echo sanitize(($branch['branch_name'] ?? 'Branch') . ' map'); ?>"
                            style="width:100%; height:280px; border:0;"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                        ></iframe>
                        <?php endif; ?>

                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                                <div>
                                    <h4 class="mb-1"><?php echo sanitize($branch['branch_name'] ?? 'Branch'); ?></h4>
                                    <p class="text-muted mb-0">Visit this branch or contact the team directly.</p>
                                </div>
                                <?php if ($branchMapsUrl !== ''): ?>
                                <a href="<?php echo sanitize($branchMapsUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">
                                    <i class="fas fa-location-arrow me-2"></i> Open Map
                                </a>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($branch['address'])): ?>
                            <p class="mb-3">
                                <i class="fas fa-map-pin text-primary me-2"></i>
                                <?php echo nl2br(sanitize((string) $branch['address'])); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ($branchPhonePrimary !== '' || $branchPhoneSecondary !== ''): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php if ($branchPhonePrimary !== ''): ?>
                                <a href="tel:<?php echo sanitize(formatPhoneForTel($branchPhonePrimary)); ?>" class="text-decoration-none">
                                    <i class="fas fa-phone text-primary me-2"></i><?php echo sanitize($branchPhonePrimary); ?>
                                </a>
                                <?php endif; ?>
                                <?php if ($branchPhoneSecondary !== ''): ?>
                                <a href="tel:<?php echo sanitize(formatPhoneForTel($branchPhoneSecondary)); ?>" class="text-decoration-none">
                                    <i class="fas fa-phone-volume text-primary me-2"></i><?php echo sanitize($branchPhoneSecondary); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted mb-0">Phone number not added for this branch yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-map-location-dot" style="font-size: 2.75rem; color: var(--primary); margin-bottom: 1rem;"></i>
                    <h3 class="h4">No branch locations yet</h3>
                    <p class="text-muted mb-0">Add branch phone numbers, addresses, and Google Maps links from the admin settings page.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Business Hours -->
    <section class="section-spacing bg-light">
        <div class="container-lg">
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="text-center">
                        <i class="fas fa-clock" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <h5><?php echo sanitize($businessHours[0]['label']); ?></h5>
                        <p class="text-muted"><?php echo sanitize($businessHours[0]['hours']); ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="text-center">
                        <i class="fas fa-clock" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <h5><?php echo sanitize($businessHours[1]['label']); ?></h5>
                        <p class="text-muted"><?php echo sanitize($businessHours[1]['hours']); ?></p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="text-center">
                        <i class="fas fa-clock" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <h5><?php echo sanitize($businessHours[2]['label']); ?></h5>
                        <p class="text-muted"><?php echo sanitize($businessHours[2]['hours']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
