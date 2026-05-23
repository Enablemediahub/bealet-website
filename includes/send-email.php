<?php
/**
 * Bealet Website - Email Templates and Utilities
 */

require_once __DIR__ . '/../inc/config.php';

function getEmailHeader($title) {
    return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; }
                .header { background: linear-gradient(135deg, #2563EB 0%, #1E40AF 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; }
                .button { display: inline-block; background: #2563EB; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .alert { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f5f5f5; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$title</h1>
                </div>
                <div class='content'>
    ";
}

function getEmailFooter() {
    return "
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                    <p><a href='" . APP_URL . "'>Visit Our Website</a> | <a href='" . APP_URL . "/contact.php'>Contact Us</a></p>
                </div>
            </div>
        </body>
        </html>
    ";
}

function getWelcomeEmailTemplate($userName) {
    return getEmailHeader('Welcome to ' . APP_NAME) . "
        <p>Dear $userName,</p>
        <p>Welcome to " . APP_NAME . "! We're thrilled to have you as part of our community.</p>
        <p>Your account has been successfully created. You can now:</p>
        <ul>
            <li>Shop our premium eyewear collection</li>
            <li>Book appointments with our specialists</li>
            <li>Try on frames using our AR feature</li>
            <li>Track your orders in real-time</li>
        </ul>
        <p><a href='" . APP_URL . "/shop.php' class='button'>Start Shopping</a></p>
        <p>If you have any questions, feel free to contact our support team.</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    " . getEmailFooter();
}

function getPasswordResetEmailTemplate($userName, $resetLink) {
    return getEmailHeader('Reset Your Password') . "
        <p>Dear $userName,</p>
        <p>We received a request to reset your password. Click the link below to create a new password:</p>
        <p><a href='$resetLink' class='button'>Reset Password</a></p>
        <p>This link will expire in 24 hours.</p>
        <p>If you didn't request this, please ignore this email.</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    " . getEmailFooter();
}

function getAppointmentConfirmationTemplate($customerName, $appointmentDate, $appointmentTime, $adminContact) {
    return getEmailHeader('Appointment Confirmation') . "
        <p>Dear $customerName,</p>
        <p>Thank you for booking an appointment with " . APP_NAME . ".</p>
        <p><strong>Appointment Details:</strong></p>
        <ul>
            <li>Date: $appointmentDate</li>
            <li>Time: $appointmentTime</li>
            <li>Status: Pending Confirmation</li>
        </ul>
        <p>Our team will contact you soon to confirm your appointment.</p>
        <p>Questions? Contact us at: $adminContact</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    " . getEmailFooter();
}

function getOrderConfirmationTemplate($customerName, $trackingCode, $orderTotal, $trackingLink) {
    return getEmailHeader('Order Confirmation') . "
        <p>Dear $customerName,</p>
        <p>Thank you for your order! We're excited to get your items to you.</p>
        <p><strong>Order Details:</strong></p>
        <p>Tracking Code: <strong>$trackingCode</strong></p>
        <p>Total Amount: <strong>$orderTotal</strong></p>
        <p><a href='$trackingLink' class='button'>Track Your Order</a></p>
        <p>You'll receive shipping updates via email. If you have any questions, please contact our support team.</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    " . getEmailFooter();
}

function getContactFormReplyTemplate($name, $message) {
    return getEmailHeader('We Received Your Message') . "
        <p>Dear $name,</p>
        <p>Thank you for reaching out to " . APP_NAME . "!</p>
        <p>We've received your inquiry and will get back to you as soon as possible.</p>
        <p><strong>Your Message:</strong></p>
        <p style='background: #f5f5f5; padding: 10px; border-left: 4px solid #2563EB;'>$message</p>
        <p>Our team typically responds within 24 hours.</p>
        <p>Best regards,<br>" . APP_NAME . " Team</p>
    " . getEmailFooter();
}
