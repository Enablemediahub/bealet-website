<?php
/**
 * Bealet Website - Staff Page
 */

require_once __DIR__ . '/inc/header.php';

$staffMembers = getActiveStaffMembers();
$staffHeroImage = getStaffHeroImageUrl();
?>

<section class="section-spacing">
    <div class="container-lg">
        <div class="team-hero" style="--staff-hero-image: url('<?php echo sanitize($staffHeroImage); ?>');">
            <div class="team-hero-layout">
                <div class="team-hero-copy">
                    <span class="hero-kicker team-hero-kicker"><i class="fas fa-people-group"></i> Bealet Optical Staff</span>
                    <h1 class="mb-3">Meet the staff behind every BEALET experience.</h1>
                    <p class="lead mb-0">
                        Our staff supports every visit with expert care, warm guidance, and quick help for appointments, eyewear, and follow-up questions.
                    </p>
                    <div class="team-hero-actions">
                        <a href="<?php echo APP_URL; ?>/appointment.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-check me-2"></i> Book a Visit
                        </a>
                        <a href="<?php echo APP_URL; ?>/contact.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-phone me-2"></i> Contact Our Staff
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-spacing pt-0">
    <div class="container-lg">
        <div class="section-title">
            <h2>Our Staff</h2>
            <p>Meet the people our admin team has published for the website.</p>
        </div>

        <?php if (!empty($staffMembers)): ?>
        <div class="row g-4">
            <?php foreach ($staffMembers as $member): ?>
            <div class="col-md-6 col-xl-3">
                <div class="card team-staff-card">
                    <div class="team-card-photo-shell">
                        <img
                            src="<?php echo getStaffImageUrl($member['thumbnail'] ?? '', $member['name'] ?? 'Staff Member'); ?>"
                            alt="<?php echo sanitize($member['name'] ?? 'Staff Member'); ?>"
                            class="team-avatar-photo"
                        >
                        <div class="team-card-photo-glow"></div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title mb-2"><?php echo sanitize($member['name'] ?? ''); ?></h5>
                        <p class="text-primary fw-semibold mb-3"><?php echo sanitize($member['designation'] ?? ''); ?></p>

                        <?php if (!empty($member['branch_name'])): ?>
                        <p class="card-text mb-2">
                            <i class="fas fa-location-dot me-2 text-primary"></i>
                            <?php echo sanitize($member['branch_name']); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($member['email'])): ?>
                        <p class="card-text mb-2">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            <a href="mailto:<?php echo sanitize($member['email']); ?>"><?php echo sanitize($member['email']); ?></a>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($member['contact'])): ?>
                        <p class="card-text mb-2">
                            <i class="fas fa-phone me-2 text-primary"></i>
                            <a href="tel:<?php echo sanitize($member['contact']); ?>"><?php echo sanitize($member['contact']); ?></a>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($member['bio'])): ?>
                        <p class="card-text mb-0"><?php echo sanitize($member['bio']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card team-empty-state">
            <div class="card-body text-center py-5">
                <span class="team-avatar mx-auto d-inline-flex mb-3">
                    <i class="fas fa-user-group"></i>
                </span>
                <h3 class="h4">Staff profiles will appear here soon</h3>
                <p class="text-muted mb-0">Add staff members from the admin panel with a thumbnail, designation, email, and contact details.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
