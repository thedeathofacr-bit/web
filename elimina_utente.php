<?php
include "connessione.php";
require_admin_page($conn, "login_gestioneutenti.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: gestione_utenti.php");
    exit;
}

verify_csrf_or_die($conn);

$id = $_POST["id"] ?? "";

if (!is_numeric($id) || (int)$id <= 0) {
    header("Location: gestione_utenti.php?error=id_non_valido");
    exit;
}

$id = (int)$id;
$adminLoggatoId = current_user_id();

if ($id === $adminLoggatoId) {
    header("Location: gestione_utenti.php?error=non_puoi_eliminare_te_stesso");
    exit;
}

$stmtUtente = $conn->prepare("SELECT id, username, ruolo FROM utenti WHERE id = ?");
if (!$stmtUtente) {
    header("Location: gestione_utenti.php?error=utente_non_trovato");
    exit;
}

$stmtUtente->bind_param("i", $id);
$stmtUtente->execute();
$resultUtente = $stmtUtente->get_result();

if (!$resultUtente || $resultUtente->num_rows === 0) {
    header("Location: gestione_utenti.php?error=utente_non_trovato");
    exit;
}

$utente = $resultUtente->fetch_assoc();

if ($utente["ruolo"] === "admin") {
    $resultAdmin = $conn->query("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'admin'");
    $totaleAdmin = 0;

    if ($resultAdmin && $rowAdmin = $resultAdmin->fetch_assoc()) {
        $totaleAdmin = (int)$rowAdmin["totale"];
    }

    if ($totaleAdmin <= 1) {
        header("Location: gestione_utenti.php?error=ultimo_admin_non_eliminabile");
        exit;
    }
}

$stmtDelete = $conn->prepare("DELETE FROM utenti WHERE id = ?");
if (!$stmtDelete) {
    header("Location: gestione_utenti.php?error=errore_eliminazione");
    exit;
}

$stmtDelete->bind_param("i", $id);

if ($stmtDelete->execute()) {
    log_attivita($conn, "eliminazione", "utente", $id, "Eliminato utente: " . $utente["username"]);
    header("Location: gestione_utenti.php?success=utente_eliminato");
    exit;
}

header("Location: gestione_utenti.php?error=errore_eliminazione");
exit;