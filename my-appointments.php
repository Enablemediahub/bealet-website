<?php
/**
 * Bealet Website - My Appointments
 */

session_start();

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

if (!isLoggedIn()) {
    redirect(APP_URL . '/login.php');
}

$errors = [];
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $appointmentId = (int)$_POST['appointment_id'];
        $appointment = $db->fetch("SELECT user_id, status FROM appointments WHERE id = ?", [$appointmentId]);
        if ($appointment && $appointment['user_id'] === $user['id'] && $appointment['status'] === 'pending') {
            $db->update("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [$appointmentId]);
            setFlashMessage('success', 'Appointment cancelled successfully.');
        } else {
            $errors[] = 'Unable to cancel this appointment.';
        }
    }
}

$appointments = $db->fetchAll(
    "SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC",
    [$user['id']]
);

require_once __DIR__ . '/inc/header.php';
?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>My Appointments</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active">Appointments</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container my-5">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?php echo sanitize($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (empty($appointments)): ?>
        <div class="text-center py-5">
            <i class="fas fa-calendar-alt" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
            <h3>No appointments yet</h3>
            <p class="text-muted mb-4">Book an appointment to track your consultation schedule.</p>
            <a href="<?php echo APP_URL; ?>/appointment.php" class="btn btn-primary">
                <i class="fas fa-calendar-plus me-2"></i> Book Appointment
            </a>
        </div>
        <?php else: ?>
        <div class="row gy-4">
            <?php foreach ($appointments as $appointment): ?>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1"><?php echo formatDate($appointment['appointment_date']); ?></h5>
                                <p class="text-muted mb-1"><?php echo sanitize($appointment['appointment_time']); ?></p>
                            </div>
                            <span class="badge bg-<?php echo $appointment['status'] === 'pending' ? 'warning' : ($appointment['status'] === 'confirmed' ? 'info' : ($appointment['status'] === 'completed' ? 'success' : 'danger')); ?> text-dark">
                                <?php echo ucfirst($appointment['status']); ?>
                            </span>
                        </div>
                        <p><strong>Phone:</strong> <?php echo sanitize($appointment['phone']); ?></p>
                        <p><strong>Notes:</strong> <?php echo nl2br(sanitize($appointment['notes'])); ?></p>
                        <?php if ($appointment['status'] === 'pending'): ?>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="cancel_appointment" value="1">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Appointment</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
