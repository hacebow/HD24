<?php
// UTF-8 ve JSON Yanıt Ayarları
header('Content-Type: application/json; charset=utf-8');

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Ungültige Anfragemethode.']);
    exit;
}

// Güvenlik: Girdileri temizleme fonksiyonu
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Form verilerini al
$name    = isset($_POST['name']) ? clean_input($_POST['name']) : '';
$phone   = isset($_POST['phone']) ? clean_input($_POST['phone']) : '';
$email   = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$service = isset($_POST['service']) ? clean_input($_POST['service']) : 'Allgemeine Anfrage';
$message = isset($_POST['message']) ? clean_input($_POST['message']) : '';

// Zorunlu alan kontrolü
if (empty($name) || empty($phone) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bitte alle Pflichtfelder ausfüllen.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ungültige E-Mail-Adresse.']);
    exit;
}

// E-posta Hedefi ve Başlığı
$to = 'office@hd-24.at';
$subject = "=?UTF-8?B?" . base64_encode("Neue Anfrage: {$service} - {$name}") . "?=";

// E-posta İçeriği (Düzgün ve okunabilir formatta)
$body = "Sie haben eine neue Anfrage über das Web-Kontaktformular erhalten:\n\n";
$body .= "--------------------------------------------------\n";
$body .= "Kunde / Firma:      " . $name . "\n";
$body .= "Telefonnummer:      " . $phone . "\n";
$body .= "E-Mail-Adresse:     " . $email . "\n";
$body .= "Gewählte Leistung:  " . $service . "\n";
$body .= "--------------------------------------------------\n\n";
$body .= "Nachricht des Kunden:\n";
$body .= $message . "\n\n";
$body .= "--------------------------------------------------\n";
$body .= "Gesendet am: " . date('d.m.Y H:i:s') . "\n";
$body .= "Absender IP: " . $_SERVER['REMOTE_ADDR'] . "\n";

// E-posta Başlıkları (Doğrudan müşteriye yanıt verebilmek için Reply-To ayarlı)
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'Content-Transfer-Encoding: 8bit';
$headers[] = 'From: HD 24 Website <office@hd-24.at>';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers[] = 'X-Mailer: PHP/' . phpversion();

// Postala
if (mail($to, $subject, $body, implode("\r\n", $headers))) {
    echo json_encode(['status' => 'success', 'message' => 'Ihre Anfrage wurde erfolgreich übermittelt.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'E-Mail konnte nicht gesendet werden.']);
}