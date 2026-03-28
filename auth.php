<?php
require_once "security.php";
require_once "connessione.php";

if (!is_logged()) {
    header("Location: login.php");
    exit;
}

$userId = current_user_id();

$stmt = $conn->prepare("SELECT ruolo, libreria_id FROM utenti WHERE id = ? LIMIT 1");

if (!$stmt) {
    die("Errore interno autenticazione.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

$user = $result->fetch_assoc();
$_SESSION["ruolo"] = $user["ruolo"] ?? "utente";
$_SESSION["libreria_id"] = isset($user["libreria_id"]) ? (int)$user["libreria_id"] : null;

$stmt->close();
$conn->close();