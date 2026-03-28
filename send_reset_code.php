<?php
require 'connessione.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$email = $_POST['email'];

// Controlla utente
$stmt = $conn->prepare("SELECT id FROM utenti WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["error" => "Email non trovata"]);
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

// Genera codice
$codice = rand(100000, 999999);
$scadenza = date("Y-m-d H:i:s", strtotime("+10 minutes"));

// Salva codice
$stmt = $conn->prepare("INSERT INTO password_reset (user_id, codice, scadenza) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $codice, $scadenza);
$stmt->execute();

// INVIO EMAIL
$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'TUA_EMAIL@gmail.com';
$mail->Password = 'PASSWORD_APP';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('TUA_EMAIL@gmail.com', 'Libreria');
$mail->addAddress($email);

$mail->Subject = "Codice reset password";
$mail->Body = "Il tuo codice è: $codice";

$mail->send();

echo json_encode(["success" => true]);