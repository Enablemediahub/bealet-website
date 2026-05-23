<?php
/**
 * Bealet Website - Process Appointment Emails
 */

require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/functions.php';

function sendAppointmentConfirmationEmail($appointment, $user = null) {
    $appointmentDate = formatDate($appointment['appointment_date']);
    $appointmentTime = sanitize($appointment['appointment_time']);
    
    if (!$user) {
        $user = [
            'name' => $appointment['customer_name'],
            'email' => $appointment['customer_email']
        ];
    }
    
    // Email to customer
    $customerSubject = "Appointment Confirmation - " . APP_NAME;
    $customerMessage = "
        <h2>Appointment Confirmation</h2>
        <p>Dear {$user['name']},</p>
        <p>Thank you for booking an appointment with " . APP_NAME . ".</p>
        <p><strong>Appointment Details:</strong></p>
        <ul>
            <li>Date: $appointmentDate</li>
            <li>Time: $appointmentTime</li>
            <li>Status: Pending Confirmation</li>
        </ul>
        <p>Our team will contact you soon to confirm your appointment.</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    ";
    
    sendEmail($user['email'], $customerSubject, $customerMessage);
    
    // Email to admin
    $adminSubject = "New Appointment - " . APP_NAME;
    $adminMessage = "
        <h2>New Appointment Received</h2>
        <p>A new appointment has been booked:</p>
        <ul>
            <li>Customer: {$user['name']}</li>
            <li>Email: {$user['email']}</li>
            <li>Date: $appointmentDate</li>
            <li>Time: $appointmentTime</li>
        </ul>
        <p><a href='" . APP_URL . "/admin/appointments.php'>View in Admin Panel</a></p>
    ";
    
    sendEmail(ADMIN_EMAIL, $adminSubject, $adminMessage);
}
