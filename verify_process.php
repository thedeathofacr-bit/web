<?php
include "connessione.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

verify_csrf_or_die($conn);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$code = clean_string($_POST['code'] ?? '', 10);

if ($id <= 0 || $code === '') {
    header("Location: register.php?errore=" . urlencode("Dati non validi."));
    exit;
}

$stmt = $conn->prepare("
    SELECT id, username, codice_verifica, codice_scadenza, email_verificata
    FROM utenti
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: register.php?errore=" . urlencode("Utente non trovato."));
    exit;
}

if ((int)$user['email_verificata'] === 1) {
    header("Location: login.php?successo=" . urlencode("Email già verificata. Ora puoi accedere."));
    exit;
}

if ($user['codice_verifica'] !== $code) {
    header("Location: verify.php?id=" . $id . "&errore=" . urlencode("Codice errato."));
    exit;
}

if (!empty($user['codice_scadenza']) && strtotime($user['codice_scadenza']) < time()) {
    header("Location: verify.php?id=" . $id . "&errore=" . urlencode("Codice scaduto. Serve generarne uno nuovo."));
    exit;
}

$stmt = $conn->prepare("
    UPDATE utenti
    SET email_verificata = 1,
        codice_verifica = NULL,
        codice_scadenza = NULL
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

log_attivita($conn, "verifica_email", "utente", $id, "Email verificata per utente " . $user['username']);

header("Location: login.php?successo=" . urlencode("Email verificata correttamente. Ora puoi accedere."));
exit;