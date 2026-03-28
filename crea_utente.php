<?php
include "connessione.php";
require_admin_page($conn, "login_gestioneutenti.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: gestione_utenti.php");
    exit;
}

verify_csrf_or_die($conn);

$username = clean_string($_POST["username"] ?? "", 50);
$password = (string)($_POST["password"] ?? "");
$ruolo = clean_string($_POST["ruolo"] ?? "utente", 20);

if (!validate_username($username) || $password === "") {
    header("Location: gestione_utenti.php?error=campi_vuoti");
    exit;
}

if ($ruolo !== "admin" && $ruolo !== "utente") {
    $ruolo = "utente";
}

$stmtCheck = $conn->prepare("SELECT id FROM utenti WHERE username = ?");
if (!$stmtCheck) {
    header("Location: gestione_utenti.php?error=errore_creazione");
    exit;
}

$stmtCheck->bind_param("s", $username);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck && $resultCheck->num_rows > 0) {
    header("Location: gestione_utenti.php?error=username_esistente");
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stato = "attivo";

$stmtInsert = $conn->prepare("
    INSERT INTO utenti (username, password, ruolo, stato, created_at)
    VALUES (?, ?, ?, ?, NOW())
");

if (!$stmtInsert) {
    header("Location: gestione_utenti.php?error=errore_creazione");
    exit;
}

$stmtInsert->bind_param("ssss", $username, $passwordHash, $ruolo, $stato);

if ($stmtInsert->execute()) {
    $nuovoId = $conn->insert_id;
    log_attivita($conn, "creazione", "utente", $nuovoId, "Creato utente: " . $username . " (" . $ruolo . ")");
    header("Location: gestione_utenti.php?success=utente_creato");
    exit;
}

header("Location: gestione_utenti.php?error=errore_creazione");
exit;