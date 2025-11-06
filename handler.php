<?php
/**
 * FIDELITY LAW & DEVELOPMENT - FORM HANDLER
 * Processes contact form submissions and saves data to CSV
 */

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Response function
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Configuration
$config = [
    'csv_file' => 'leads.csv',
    'upload_dir' => 'uploads/',
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
    'timezone' => 'Asia/Jakarta'
];

// Set timezone
date_default_timezone_set($config['timezone']);

// Sanitize input function
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate phone
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

// Get and sanitize form data
$name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
$phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
$consent = isset($_POST['consent']) ? sanitizeInput($_POST['consent']) : '';

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($phone)) {
    $errors[] = 'Phone is required';
} elseif (!validatePhone($phone)) {
    $errors[] = 'Invalid phone number';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!validateEmail($email)) {
    $errors[] = 'Invalid email address';
}

if (empty($message)) {
    $errors[] = 'Message is required';
}

if (empty($consent)) {
    $errors[] = 'Consent is required';
}

// Return validation errors
if (!empty($errors)) {
    sendResponse(false, implode(', ', $errors));
}

// Handle file upload
$uploadedFile = '';
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmpName = $file['tmp_name'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Check file size
    if ($fileSize > $config['max_file_size']) {
        sendResponse(false, 'File size exceeds maximum allowed (10MB)');
    }
    
    // Check file extension
    if (!in_array($fileType, $config['allowed_extensions'])) {
        sendResponse(false, 'Invalid file type. Allowed: ' . implode(', ', $config['allowed_extensions']));
    }
    
    // Create upload directory if not exists
    if (!file_exists($config['upload_dir'])) {
        mkdir($config['upload_dir'], 0755, true);
    }
    
    // Generate unique filename
    $uniqueFileName = date('YmdHis') . '_' . uniqid() . '.' . $fileType;
    $uploadPath = $config['upload_dir'] . $uniqueFileName;
    
    // Move uploaded file
    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        $uploadedFile = $uploadPath;
    } else {
        sendResponse(false, 'Error uploading file');
    }
}

// Prepare data for CSV
$timestamp = date('Y-m-d H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$data = [
    $timestamp,
    $name,
    $phone,
    $email,
    $message,
    $uploadedFile,
    $ip,
    $userAgent
];

// Write to CSV file
$csvFile = $config['csv_file'];
$isNewFile = !file_exists($csvFile);

// Open file for appending
$fp = fopen($csvFile, 'a');

if (!$fp) {
    sendResponse(false, 'Error opening CSV file');
}

// Lock file for writing
if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    sendResponse(false, 'Error locking CSV file');
}

// Write headers if new file
if ($isNewFile) {
    $headers = [
        'Timestamp',
        'Name',
        'Phone',
        'Email',
        'Message',
        'File',
        'IP Address',
        'User Agent'
    ];
    fputcsv($fp, $headers);
}

// Write data
if (!fputcsv($fp, $data)) {
    flock($fp, LOCK_UN);
    fclose($fp);
    sendResponse(false, 'Error writing to CSV file');
}

// Unlock and close file
flock($fp, LOCK_UN);
fclose($fp);

// Optional: Send email notification (uncomment to enable)
/*
$to = 'fidelity.law.general@gmail.com';
$subject = 'New Lead from Website - ' . $name;
$emailBody = "New lead received:\n\n";
$emailBody .= "Name: $name\n";
$emailBody .= "Phone: $phone\n";
$emailBody .= "Email: $email\n";
$emailBody .= "Message: $message\n";
$emailBody .= "Time: $timestamp\n";
if ($uploadedFile) {
    $emailBody .= "File: $uploadedFile\n";
}
$headers = "From: noreply@fidelitylaw.com\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

mail($to, $subject, $emailBody, $headers);
*/

// Optional: Send to Telegram (uncomment and configure)
/*
$telegramBotToken = 'YOUR_BOT_TOKEN';
$telegramChatId = 'YOUR_CHAT_ID';
$telegramMessage = "🆕 Новая заявка с сайта\n\n";
$telegramMessage .= "👤 Имя: $name\n";
$telegramMessage .= "📱 Телефон: $phone\n";
$telegramMessage .= "📧 Email: $email\n";
$telegramMessage .= "💬 Сообщение: $message\n";
$telegramMessage .= "🕐 Время: $timestamp";

$telegramUrl = "https://api.telegram.org/bot{$telegramBotToken}/sendMessage";
$telegramData = [
    'chat_id' => $telegramChatId,
    'text' => $telegramMessage,
    'parse_mode' => 'HTML'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $telegramUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $telegramData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);
*/

// Send success response
sendResponse(true, 'Form submitted successfully', [
    'name' => $name,
    'timestamp' => $timestamp
]);
?>
