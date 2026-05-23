<?php
/**
 * Bealet Website - Admin Appointments Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

require_once __DIR__ . '/inc/header.php';

global $db;

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_appointment'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $appointmentId = (int)$_POST['appointment_id'];
        $status = sanitize($_POST['status']);
        
        if (in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            $db->update(
                "UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?",
                [$status, $appointmentId]
            );
            
            createLog('APPOINTMENT_STATUS_UPDATED', "Appointment #$appointmentId status changed to $status");
            setFlashMessage('success', 'Appointment status updated');
        }
    }
}

// Get appointments
$appointments = $db->fetchAll(
    "SELECT a.*,
            COALESCE(NULLIF(a.name, ''), u.name, '') AS customer_name,
            COALESCE(NULLIF(a.email, ''), u.email, '') AS customer_email,
            COALESCE(NULLIF(a.phone, ''), u.phone, '') AS customer_phone
     FROM appointments a
     LEFT JOIN users u ON a.user_id = u.id
     WHERE a.status != 'completed'
     ORDER BY a.appointment_date ASC, a.appointment_time ASC"
);
$appointmentCatalog = [];

foreach ($appointments as $appointment) {
    $customerName = isset($appointment['customer_name']) ? $appointment['customer_name'] : '';
    $customerEmail = isset($appointment['customer_email']) ? $appointment['customer_email'] : '';
    $customerPhone = isset($appointment['customer_phone']) ? $appointment['customer_phone'] : '';

    $appointmentCatalog[(int) $appointment['id']] = [
        'id' => (int) $appointment['id'],
        'name' => (string) $customerName,
        'email' => (string) $customerEmail,
        'phone' => (string) $customerPhone,
        'date' => formatDate($appointment['appointment_date']),
        'time' => (string) ($appointment['appointment_time'] ?? ''),
        'status' => ucfirst((string) ($appointment['status'] ?? 'pending')),
        'notes' => trim((string) ($appointment['notes'] ?? '')),
        'created_at' => !empty($appointment['created_at']) ? formatDate($appointment['created_at']) : '',
    ];
}

?>

        <!-- Appointments Management -->
        <div class="container-fluid mt-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Appointments Management</h2>
                    <p class="text-muted">View and manage customer appointments</p>
                </div>
                <span class="badge bg-primary" style="font-size: 1rem;"><?php echo count($appointments); ?> Total</span>
            </div>
            
            <!-- Appointments Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                            <?php
                                $customerName = isset($appointment['customer_name']) ? $appointment['customer_name'] : '';
                                $customerEmail = isset($appointment['customer_email']) ? $appointment['customer_email'] : '';
                                $customerPhone = isset($appointment['customer_phone']) ? $appointment['customer_phone'] : '';
                            ?>
                            <tr>
                                <td><strong><?php echo sanitize($customerName); ?></strong></td>
                                <td><?php echo sanitize($customerEmail); ?></td>
                                <td><?php echo sanitize($customerPhone); ?></td>
                                <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                <td><?php echo sanitize($appointment['appointment_time']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="update_appointment" value="1">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $appointment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $appointment['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewAppointmentDetails(<?php echo $appointment['id']; ?>)">
                                        View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="appointmentDetailsModal" tabindex="-1" aria-labelledby="appointmentDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="appointmentDetailsModalLabel">Appointment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Customer</small>
                            <strong id="appointmentDetailName">Not available</strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Email</small>
                            <span id="appointmentDetailEmail">Not available</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Phone</small>
                            <span id="appointmentDetailPhone">Not available</span>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Date</small>
                                <span id="appointmentDetailDate">Not available</span>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted d-block">Time</small>
                                <span id="appointmentDetailTime">Not available</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Status</small>
                            <span id="appointmentDetailStatus" class="badge bg-secondary">Pending</span>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted d-block">Notes</small>
                            <div id="appointmentDetailNotes" class="border rounded-3 p-3 bg-light">No notes added.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        const appointmentCatalog = <?php echo json_encode($appointmentCatalog, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function viewAppointmentDetails(appointmentId) {
            const appointment = appointmentCatalog[String(appointmentId)] || appointmentCatalog[appointmentId];
            const modalElement = document.getElementById('appointmentDetailsModal');

            if (!appointment || !modalElement || typeof bootstrap === 'undefined') {
                return;
            }

            document.getElementById('appointmentDetailName').textContent = appointment.name || 'Not available';
            document.getElementById('appointmentDetailEmail').textContent = appointment.email || 'Not available';
            document.getElementById('appointmentDetailPhone').textContent = appointment.phone || 'Not available';
            document.getElementById('appointmentDetailDate').textContent = appointment.date || 'Not available';
            document.getElementById('appointmentDetailTime').textContent = appointment.time || 'Not available';

            const statusElement = document.getElementById('appointmentDetailStatus');
            statusElement.textContent = appointment.status || 'Pending';
            statusElement.className = 'badge ' + (
                appointment.status === 'Confirmed' ? 'bg-primary' :
                appointment.status === 'Completed' ? 'bg-success' :
                appointment.status === 'Cancelled' ? 'bg-danger' :
                'bg-secondary'
            );

            document.getElementById('appointmentDetailNotes').textContent = appointment.notes || 'No notes added.';

            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
        }
        </script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
