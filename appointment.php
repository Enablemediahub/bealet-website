<?php
/**
 * Bealet Website - Appointment Booking Page
 */

session_start();

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

global $db;

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'appointment_date' => '',
    'appointment_time' => '',
    'notes' => ''
];

// Pre-fill if logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    $formData['name'] = $user['name'];
    $formData['email'] = $user['email'];
    $formData['phone'] = $user['phone'] ?: '+233XXXXXXXXX';
}

if ($formData['phone'] === '') {
    $formData['phone'] = '+233XXXXXXXXX';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $appointmentDate = sanitize($_POST['appointment_date'] ?? '');
        $appointmentTime = sanitize($_POST['appointment_time'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        $formData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
            'notes' => $notes
        ];
        
        // Validate inputs
        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'Please enter a valid full name';
        }
        
        if (empty($email) || !validateEmail($email)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($phone) || $phone === '+233XXXXXXXXX' || !validatePhone($phone)) {
            $errors[] = 'Please enter a valid Ghana phone number';
        }
        
        if (empty($appointmentDate)) {
            $errors[] = 'Please select an appointment date';
        } else {
            $selectedDate = strtotime($appointmentDate);
            $today = strtotime(date('Y-m-d'));
            
            if ($selectedDate < $today) {
                $errors[] = 'Please select a future date';
            }
            
            // Check if date is Sunday
            if (date('w', $selectedDate) == 0) {
                $errors[] = 'We are closed on Sundays. Please choose another date.';
            }
        }
        
        if (empty($appointmentTime)) {
            $errors[] = 'Please select a time slot';
        }
        
        // If no errors, save appointment
        if (empty($errors)) {
            try {
                $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
                
                $appointmentId = $db->insert(
                    "INSERT INTO appointments (user_id, name, email, phone, appointment_date, appointment_time, notes, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                    [$userId, $name, $email, $phone, $appointmentDate, $appointmentTime, $notes]
                );
                
                if ($appointmentId) {
                    // Send confirmation email
                    sendAppointmentConfirmationEmail($formData);
                    
                    // Send admin notification
                    $adminEmail = 'admin@bealet.com';
                    $subject = 'New Appointment Booking - ' . APP_NAME;
                    $body = "
                    <h2>New Appointment Booking</h2>
                    <p><strong>Name:</strong> $name</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Phone:</strong> $phone</p>
                    <p><strong>Date:</strong> " . date('F d, Y', strtotime($appointmentDate)) . "</p>
                    <p><strong>Time:</strong> $appointmentTime</p>
                    <p><strong>Notes:</strong> $notes</p>
                    <p><a href='" . APP_URL . "/admin/appointments.php'>View in Admin Panel</a></p>
                    ";
                    
                    sendEmail($adminEmail, $subject, $body);
                    
                    createLog('APPOINTMENT_BOOKED', 'Appointment ID: ' . $appointmentId, $userId);
                    setFlashMessage('success', 'Appointment booked successfully! Check your email for confirmation.');
                    redirect(APP_URL . '/appointment.php');
                }
            } catch (Exception $e) {
                createLog('APPOINTMENT_ERROR', 'Error booking appointment: ' . $e->getMessage());
                $errors[] = 'An error occurred while booking your appointment. Please try again.';
            }
        }
    }
}

// Get available dates (next 30 days, excluding Sundays)
$availableDates = [];
$today = new DateTime();
for ($i = 1; $i <= 30; $i++) {
    $date = clone $today;
    $date->modify("+$i days");
    
    // Skip Sundays
    if ($date->format('w') != 0) {
        $availableDates[] = $date->format('Y-m-d');
    }
}

require_once __DIR__ . '/inc/header.php';

?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mt-4 mb-4">
        <div class="container-lg">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/">Home</a></li>
                <li class="breadcrumb-item active">Book Appointment</li>
            </ol>
        </div>
    </nav>
    
    <!-- Page Header -->
    <section class="mb-5">
        <div class="container-lg">
            <h1 class="mb-2">Book an Appointment</h1>
            <p class="text-muted">Schedule a consultation with our eye care specialists</p>
        </div>
    </section>
    
    <!-- Booking Section -->
    <section class="section-spacing">
        <div class="container-lg">
            <div class="row">
                <!-- Info Sidebar -->
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> About Our Services</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="mb-3">What to expect:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <strong>Comprehensive Eye Test</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <strong>Frame Consultation</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <strong>Prescription Verification</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <strong>Styling Advice</strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-clock me-2"></i> Service Hours</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Monday - Friday:</strong><br>
                                8:00 AM - 5:00 PM
                            </p>
                            <p class="mb-2">
                                <strong>Saturday:</strong><br>
                                8:00 AM - 2:00 PM
                            </p>
                            <p class="mb-0">
                                <strong>Sunday:</strong><br>
                                Closed
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Booking Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <!-- Full Name -->
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo sanitize($formData['name']); ?>">
                                    </div>
                                </div>
                                
                                <!-- Email -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo sanitize($formData['email']); ?>">
                                    </div>
                                </div>
                                
                                <!-- Phone -->
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="phone" name="phone" required value="<?php echo sanitize($formData['phone']); ?>" placeholder="+233XXXXXXXXX" inputmode="tel">
                                    </div>
                                    <small class="text-muted">Use a Ghana number like +233 24 000 0000 or 024 000 0000.</small>
                                </div>
                                
                                <!-- Date -->
                                <div class="mb-3">
                                    <label for="appointment_date" class="form-label">Preferred Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" required value="<?php echo sanitize($formData['appointment_date']); ?>" min="<?php echo date('Y-m-d'); ?>">
                                    <small class="text-muted">Available: Monday - Saturday</small>
                                </div>
                                
                                <!-- Time -->
                                <div class="mb-3">
                                    <label for="appointment_time" class="form-label">Preferred Time <span class="text-danger">*</span></label>
                                    <select class="form-select" id="appointment_time" name="appointment_time" required>
                                        <option value="">Select a time slot...</option>
                                        <option value="09:00" <?php echo $formData['appointment_time'] === '09:00' ? 'selected' : ''; ?>>9:00 AM</option>
                                        <option value="10:00" <?php echo $formData['appointment_time'] === '10:00' ? 'selected' : ''; ?>>10:00 AM</option>
                                        <option value="11:00" <?php echo $formData['appointment_time'] === '11:00' ? 'selected' : ''; ?>>11:00 AM</option>
                                        <option value="12:00" <?php echo $formData['appointment_time'] === '12:00' ? 'selected' : ''; ?>>12:00 PM</option>
                                        <option value="13:00" <?php echo $formData['appointment_time'] === '13:00' ? 'selected' : ''; ?>>1:00 PM</option>
                                        <option value="14:00" <?php echo $formData['appointment_time'] === '14:00' ? 'selected' : ''; ?>>2:00 PM</option>
                                        <option value="15:00" <?php echo $formData['appointment_time'] === '15:00' ? 'selected' : ''; ?>>3:00 PM</option>
                                        <option value="16:00" <?php echo $formData['appointment_time'] === '16:00' ? 'selected' : ''; ?>>4:00 PM</option>
                                    </select>
                                </div>
                                
                                <!-- Notes -->
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Additional Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Tell us about your appointment needs..."><?php echo sanitize($formData['notes']); ?></textarea>
                                </div>
                                
                                <!-- Submit -->
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-calendar-check me-2"></i> Book Appointment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
