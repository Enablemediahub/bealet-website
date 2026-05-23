<?php
/**
 * Bealet Website - Admin Contact Messages Management
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect(APP_URL . '/admin/login.php');
}

require_once __DIR__ . '/inc/header.php';

global $db;

// Handle message deletion
if (isset($_GET['delete'])) {
    $messageId = (int)$_GET['delete'];
    $db->update("DELETE FROM contacts WHERE id = ?", [$messageId]);
    createLog('CONTACT_DELETED', "Contact message #$messageId deleted");
    setFlashMessage('success', 'Message deleted successfully');
    header('Location: ' . APP_URL . '/admin/contacts.php');
    exit;
}

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $messageId = (int)$_GET['mark_read'];
    $db->update("UPDATE contacts SET is_read = 1 WHERE id = ?", [$messageId]);
}

// Get contact messages
$contacts = $db->fetchAll(
    "SELECT * FROM contacts ORDER BY is_read ASC, created_at DESC"
);

$unreadCount = $db->fetch("SELECT COUNT(*) as total FROM contacts WHERE is_read = 0");

?>

        <!-- Contact Messages Management -->
        <div class="container-fluid mt-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Contact Messages</h2>
                    <p class="text-muted">Manage customer inquiries and messages</p>
                </div>
                <span class="badge bg-warning text-dark" style="font-size: 1rem;">
                    <i class="fas fa-envelope me-2"></i> <?php echo $unreadCount['total']; ?> Unread
                </span>
            </div>
            
            <!-- Messages Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Message Preview</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                            <tr class="<?php echo !$contact['is_read'] ? 'fw-bold' : ''; ?>">
                                <td><?php echo sanitize($contact['name']); ?></td>
                                <td><?php echo sanitize($contact['email']); ?></td>
                                <td><?php echo sanitize($contact['subject']); ?></td>
                                <td>
                                    <small><?php echo substr(sanitize($contact['message']), 0, 50); ?>...</small>
                                </td>
                                <td><?php echo formatDate($contact['created_at']); ?></td>
                                <td>
                                    <?php if (!$contact['is_read']): ?>
                                    <span class="badge bg-warning text-dark">New</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Read</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#messageModal<?php echo $contact['id']; ?>">
                                        View
                                    </button>
                                    <a href="?delete=<?php echo $contact['id']; ?>" onclick="return confirmDelete('Delete this message?')" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Message Detail Modal -->
                            <div class="modal fade" id="messageModal<?php echo $contact['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo sanitize($contact['subject']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <p class="text-muted mb-1">From</p>
                                                <p class="mb-0"><strong><?php echo sanitize($contact['name']); ?></strong></p>
                                                <small><?php echo sanitize($contact['email']); ?></small>
                                            </div>
                                            <div class="mb-3">
                                                <p class="text-muted mb-1">Date</p>
                                                <small><?php echo formatDate($contact['created_at']); ?></small>
                                            </div>
                                            <hr>
                                            <div class="mb-3">
                                                <p class="text-muted mb-2">Message</p>
                                                <p><?php echo nl2br(sanitize($contact['message'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="?mark_read=<?php echo $contact['id']; ?>" class="btn btn-sm btn-primary">
                                                Mark as Read
                                            </a>
                                            <a href="mailto:<?php echo sanitize($contact['email']); ?>" class="btn btn-sm btn-outline-primary">
                                                Reply
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
