   <?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/vendor/autoload.php';

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// Allow JS from same origin (adjust domain in production)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https?://(www\.)?opulentprimeproperties\.com$#', $origin)) {
    header("Access-Control-Allow-Origin: " . $origin);
}
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Recipient: from request or default
$toEmail = trim($_POST['email'] ?? $_GET['email'] ?? 'anandhuvpanicker@gmail.com');
if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit;
}

// Generate 6-digit OTP
$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$mail = new PHPMailer(true);

if ($debugMode) {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function ($str, $level) {
        $GLOBALS['smtp_debug_log'][] = $str;
    };
}

try {
    $mail->isSMTP();
    $mail->Host       = 'mail.opulentinfluencershouse.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'noreplay@opulentinfluencershouse.com';
    $mail->Password   = '5sN[4}HVq^qM';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 15;
    $mail->CharSet    = PHPMailer::CHARSET_UTF8;

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom('noreplay@opulentinfluencershouse.com', 'OTP Service');
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Your OTP Code';
    $mail->Body    = '<p>Your OTP code is: <strong>' . htmlspecialchars($otp) . '</strong></p><p>Valid for 10 minutes.</p>';
    $mail->AltBody = 'Your OTP code is: ' . $otp . '. Valid for 10 minutes.';

    $mail->send();

    $response = [
        "status"  => "success",
        "message" => "OTP sent to " . $toEmail,
        "to"      => $toEmail,
    ];
    if ($debugMode && !empty($GLOBALS['smtp_debug_log'])) {
        $response['debug_log'] = $GLOBALS['smtp_debug_log'];
    }
    echo json_encode($response);

} catch (Exception $e) {
    $errorDetail = [
        "status"          => "error",
        "message"         => $e->getMessage(),
        "phpmailer_error" => $mail->ErrorInfo,
        "file"            => $e->getFile(),
        "line"            => $e->getLine(),
        "code"            => $e->getCode(),
    ];
    if ($debugMode) {
        $errorDetail["smtp_debug_log"] = $GLOBALS['smtp_debug_log'] ?? [];
        if (!empty($errorDetail["smtp_debug_log"])) {
            $errorDetail["smtp_debug_log"] = array_map(function ($line) {
                return preg_replace('/PASS .+/', 'PASS ***', $line);
            }, $errorDetail["smtp_debug_log"]);
        }
    }
    echo json_encode($errorDetail, JSON_PRETTY_PRINT);
}
