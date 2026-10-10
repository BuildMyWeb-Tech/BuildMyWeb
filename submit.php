<?php
require __DIR__ . '/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// ✅ Set Indian timezone
date_default_timezone_set('Asia/Kolkata');

// 🧰 1️⃣ Honeypot spam trap
if (!empty($_POST['website'])) {
    exit; // hidden field filled = bot
}

// ⏱ 2️⃣ Time check spam trap
$formLoadTime = isset($_POST['form_load_time']) ? intval($_POST['form_load_time']) : 0;
$now = round(microtime(true) * 1000);
if ($now - $formLoadTime < 3000) { // less than 3 sec = suspicious
    exit;
}

// 📝 3️⃣ Sanitize & validate fields
function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$name = clean_input($_POST['name'] ?? '');
$phone = clean_input($_POST['phone'] ?? '');
$business_category = clean_input($_POST['business_category'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$message = clean_input($_POST['message'] ?? '');

// Required field checks
if ($name == '' || $phone == '' || $business_category == '' || $email == '') {
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit;
}

// Validate phone (Indian mobile)
if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    exit;
}

// 🌐 4️⃣ Block spam keywords & URLs
$spam_keywords = [
    'http://', 'https://', 'www.', '.ru', '.xyz', '.top', 'proposal for your website',
    'special offer', 'Try tactic', 'youtube.com', 'GhostPages', 'seo traffic'
];

foreach ($spam_keywords as $word) {
    if (stripos($message, $word) !== false || stripos($name, $word) !== false) {
        exit;
    }
}

// ✅ Insert into DB securely
$sql = "INSERT INTO business_inquiries (name, email, phone, business_category, message) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $name, $email, $phone, $business_category, $message);
$stmt->execute();

// 🔐 Email credentials for Hostinger
$fromEmail = 'contact@horizonsparktech.com';
$fromName  = 'Horizon Spark Technologies';
$emailPass = 'Harish$1990'; // ⚠ In production, store securely

// ✅ Admin notification email
try {
    $adminMail = new PHPMailer(true);
    $adminMail->isSMTP();
    $adminMail->Host = 'smtp.hostinger.com';
    $adminMail->SMTPAuth = true;
    $adminMail->Username = $fromEmail;
    $adminMail->Password = $emailPass;
    $adminMail->SMTPSecure = 'ssl';
    $adminMail->Port = 465;

    $adminMail->setFrom($fromEmail, $fromName);
    $adminMail->addAddress($fromEmail);

    $adminMail->isHTML(true);
    $adminMail->Subject = "New Business Inquiry via Horizon Spark Website";
    $adminMail->Body = "
    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f9f9f9; border-radius: 8px;'>
        <h2 style='color: #003366;'>✨ New Business Inquiry - Horizon Spark Technologies</h2>
        <p>You have received a new business inquiry:</p>
        <table style='width: 100%; border-collapse: collapse;'>
            <tr><td><strong>👤 Name:</strong></td><td>$name</td></tr>
            <tr><td><strong>📞 Phone:</strong></td><td>$phone</td></tr>
            <tr><td><strong>📧 Email:</strong></td><td>$email</td></tr>
            <tr><td><strong>💼 Business Category:</strong></td><td>$business_category</td></tr>
            <tr><td><strong>💬 Message:</strong></td><td>$message</td></tr>
        </table>
        <p style='margin-top: 15px;'>📅 Submitted on: <strong>" . date('d M Y, h:i A') . "</strong></p>
    </div>";
    $adminMail->send();
} catch (Exception $e) { /* log if needed */ }

// ✅ Confirmation email to user
try {
    $userMail = new PHPMailer(true);
    $userMail->isSMTP();
    $userMail->Host = 'smtp.hostinger.com';
    $userMail->SMTPAuth = true;
    $userMail->Username = $fromEmail;
    $userMail->Password = $emailPass;
    $userMail->SMTPSecure = 'ssl';
    $userMail->Port = 465;

    $userMail->setFrom($fromEmail, $fromName);
    $userMail->addAddress($email, $name);

    $userMail->isHTML(true);
    $userMail->Subject = "Thank You for Contacting Horizon Spark Technologies";
    $userMail->Body = "
    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #ffffff; border-radius: 8px;'>
        <h2 style='color: #4CAF50;'>Hi $name! 🎉</h2>
        <p>Thank you for your interest in our services: <strong>$business_category</strong>.</p>
        <p>We've received your inquiry and our team will get back to you soon.</p>
        <p>📞 Phone: <strong>$phone</strong><br>📧 Email: <strong>$email</strong></p>
        <p style='margin-top: 15px;'>📅 Submitted on: <strong>" . date('d M Y, h:i A') . "</strong></p>
        <hr>
        <p style='font-size: 14px; color: #777;'>Regards,<br><strong>Horizon Spark Technologies</strong></p>
    </div>";
    $userMail->send();
} catch (Exception $e) { /* log if needed */ }

$stmt->close();
$conn->close();

// ✅ Final success message
echo '✅ Thank you! Your inquiry has been submitted.';
?>
