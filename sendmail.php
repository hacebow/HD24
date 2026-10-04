<?php
// Oturum başlat (Spam zamanlayıcısı için gerekli)
session_start();

// JSON formatı ve UTF-8 desteği
header('Content-Type: application/json; charset=utf-8');

// Sadece POST isteklerini kabul et (URL'ye yazılarak girilmesini engeller)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- GÜVENLİK 1: ZAMAN SINIRI (RATE LIMITING) ---
    // Aynı kullanıcı 60 saniye içinde sadece 1 mail gönderebilir
    if (isset($_SESSION['last_submit']) && (time() - $_SESSION['last_submit'] < 60)) {
        echo json_encode(["status" => "error", "message" => "Bitte warten Sie eine Minute, bevor Sie eine neue Anfrage senden."]);
        exit;
    }

    // --- GÜVENLİK 2: HONEYPOT (BOT TUZAĞI) ---
    // Eğer gizli alan doldurulmuşsa bu kesinlikle bir spam bottur
    if (!empty($_POST['website_url_check'])) {
        // Bota form başarıyla gönderildi mesajı verip işlemi iptal ediyoruz (Sana mail gelmez)
        echo json_encode(["status" => "success"]); 
        exit;
    }

    // Gelen verileri zararlı kodlardan ve boşluklardan temizle (XSS Koruması)
    $name = strip_tags(trim($_POST["name"] ?? ''));
    $phone = strip_tags(trim($_POST["phone"] ?? ''));
    $email = filter_var(trim($_POST["email"] ?? ''), FILTER_SANITIZE_EMAIL);
    $service = strip_tags(trim($_POST["service"] ?? ''));
    $message = strip_tags(trim($_POST["message"] ?? ''));

    // --- GÜVENLİK 3: EMAIL HEADER INJECTION KORUMASI ---
    // İsim veya e-posta alanına satır atlama karakterleri girerek sistemi hacklemeyi engeller
    if (preg_match("/[\r\n]/", $name) || preg_match("/[\r\n]/", $email)) {
        echo json_encode(["status" => "error", "message" => "Sicherheitswarnung: Ungültige Zeichen erkannt."]);
        exit;
    }

    // Gerekli alanların boş olup olmadığını ve e-posta formatını doğrula
    if (empty($name) || empty($phone) || empty($email) || empty($service) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Bitte füllen Sie alle Pflichtfelder korrekt aus."]);
        exit;
    }

    // Güvenlik duvarları aşıldıysa, son gönderim zamanını kaydet
    $_SESSION['last_submit'] = time();

    // --- SANA (FİRMAYA) GELECEK BİLDİRİM E-POSTASI ---
    $admin_email = "office@hd-24.at"; 
    
    // Almanca karakterlerin (Ö, Ä, Ü) bozulmaması için Base64 kodlaması
    $admin_subject_text = "Neue Web-Anfrage: " . $name . " (" . $service . ")";
    $admin_subject = "=?UTF-8?B?" . base64_encode($admin_subject_text) . "?=";
    
    $admin_body = "Neue Anfrage über die Website (elektrotechnik-hd24.at):\n\n";
    $admin_body .= "Name / Firma: $name\n";
    $admin_body .= "Telefon: $phone\n";
    $admin_body .= "E-Mail: $email\n";
    $admin_body .= "Gewünschte Leistung: $service\n\n";
    $admin_body .= "Nachricht:\n$message\n";

    $admin_headers = "From: Webmaster HD 24 <office@hd-24.at>\r\n";
    $admin_headers .= "Reply-To: $email\r\n";
    $admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $admin_headers .= "X-Mailer: PHP/" . phpversion();

    $mail_to_admin = mail($admin_email, $admin_subject, $admin_body, $admin_headers);


    // --- MÜŞTERİYE GİDECEK OTOMATİK YANIT (ŞIK HTML E-POSTA) ---
    $client_subject_text = "Ihre Anfrage bei HD 24 Elektrotechnik e.U. - Eingangsbestätigung";
    $client_subject = "=?UTF-8?B?" . base64_encode($client_subject_text) . "?=";
    
    $client_body = '
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Eingangsbestätigung HD 24</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f8f9fa; font-family: Tahoma, Arial, sans-serif;">
        <div style="max-width: 600px; margin: 30px auto; background-color: #ffffff; border: 1px solid #dee2e6; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            
            <div style="background-color: #ffffff; text-align: center; padding: 25px 20px; border-bottom: 4px solid #d90429;">
                <img src="https://elektrotechnik-hd24.at/logo.png" alt="HD 24 Elektrotechnik Logo" style="max-height: 60px; height: auto;">
            </div>
            
            <div style="padding: 30px 40px;">
                <h2 style="color: #0b090a; font-size: 20px; margin-top: 0;">Vielen Dank für Ihre Anfrage!</h2>
                
                <p style="color: #495057; font-size: 15px; line-height: 1.6;"><strong>Sehr geehrte/r ' . htmlspecialchars($name) . ',</strong></p>
                
                <p style="color: #495057; font-size: 15px; line-height: 1.6;">
                    wir haben Ihre Nachricht erfolgreich erhalten. Vielen Dank für Ihr Interesse an unseren Dienstleistungen. 
                    Unser Team wird sich schnellstmöglich mit Ihnen in Verbindung setzen, um Ihr Anliegen zu besprechen und Ihnen verlässlich weiterzuhelfen.
                </p>

                <div style="background-color: #f8f9fa; padding: 18px 20px; border-left: 4px solid #ffb703; margin: 25px 0; border-radius: 4px;">
                    <p style="margin: 0 0 8px 0; color: #212529; font-size: 14px;"><strong>Gewünschte Leistung:</strong><br>' . htmlspecialchars($service) . '</p>
                    <p style="margin: 0; color: #212529; font-size: 14px;"><strong>Ihre Nachricht:</strong><br>' . nl2br(htmlspecialchars($message)) . '</p>
                </div>

                <p style="color: #495057; font-size: 15px; line-height: 1.6;">
                    Sollten Sie in der Zwischenzeit dringende Fragen oder einen elektrotechnischen Notfall haben, erreichen Sie uns jederzeit telefonisch unter unserer Service-Hotline.
                </p>

                <p style="color: #495057; font-size: 15px; line-height: 1.6; margin-bottom: 0;">
                    Mit freundlichen Grüßen,<br>
                    <strong>Ihr Team von HD 24 Elektrotechnik</strong>
                </p>
            </div>

            <div style="background-color: #111622; padding: 25px 30px; text-align: center; color: #ced4da; font-size: 12px; line-height: 1.6;">
                <strong style="color: #ffffff; font-size: 14px;">HD 24 Elektrotechnik e.U.</strong><br>
                Inhaber: Yunus Nazli | FN: 608957 a | HG Wien | UID: ATU79865148<br>
                Marischkapromenade 11/2/6, 1210 Wien, Österreich<br><br>
                
                <span style="color: #ffb703;">📞</span> <a href="tel:+436645277706" style="color: #ffffff; text-decoration: none;">+43 664 5277706</a> &nbsp;|&nbsp; 
                <span style="color: #ffb703;">✉️</span> <a href="mailto:office@hd-24.at" style="color: #ffffff; text-decoration: none;">office@hd-24.at</a><br>
                <span style="color: #ffb703;">🌐</span> <a href="https://elektrotechnik-hd24.at" style="color: #ffffff; text-decoration: none;">www.elektrotechnik-hd24.at</a><br><br>
                
                <em style="color: #adb5bd;">Meisterbetrieb | Mitglied der WKO Wien | Landesinnung Elektrotechnik</em>
            </div>

        </div>
    </body>
    </html>
    ';

    $client_headers = "MIME-Version: 1.0\r\n";
    $client_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $client_headers .= "From: HD 24 Elektrotechnik <office@hd-24.at>\r\n";
    $client_headers .= "Reply-To: office@hd-24.at\r\n";
    $client_headers .= "X-Mailer: PHP/" . phpversion();

    $mail_to_client = mail($email, $client_subject, $client_body, $client_headers);

    if ($mail_to_admin && $mail_to_client) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "E-Mail konnte nicht versendet werden."]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Ungültige Anfrage."]);
}
?>