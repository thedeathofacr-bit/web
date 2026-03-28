<?php
include "connessione.php";
require_admin_page($conn, "login_gestioneutenti.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: gestione_utenti.php");
    exit;
}

verify_csrf_or_die($conn);

$id = $_POST["id"] ?? "";
$nuovoStato = clean_string($_POST["nuovo_stato"] ?? "", 20);

if (!is_numeric($id) || (int)$id <= 0) {
    header("Location: gestione_utenti.php?error=id_non_valido");
    exit;
}

$id = (int)$id;
$adminLoggatoId = current_user_id();

if ($nuovoStato !== "attivo" && $nuovoStato !== "disattivato") {
    header("Location: gestione_utenti.php?error=errore_stato");
    exit;
}

if ($id === $adminLoggatoId && $nuovoStato === "disattivato") {
    header("Location: gestione_utenti.php?error=non_puoi_disattivare_te_stesso");
    exit;
}

$stmtUtente = $conn->prepare("SELECT id, username, ruolo, stato FROM utenti WHERE id = ?");
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

if ($utente["ruolo"] === "admin" && $nuovoStato === "disattivato") {
    $resultAdminAttivi = $conn->query("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'admin' AND stato = 'attivo'");
    $totaleAdminAttivi = 0;

    if ($resultAdminAttivi && $rowAdmin = $resultAdminAttivi->fetch_assoc()) {
        $totaleAdminAttivi = (int)$rowAdmin["totale"];
    }

    if ($totaleAdminAttivi <= 1) {
        header("Location: gestione_utenti.php?error=ultimo_admin_non_disattivabile");
        exit;
    }
}

$stmtUpdate = $conn->prepare("UPDATE utenti SET stato = ? WHERE id = ?");
if (!$stmtUpdate) {
    header("Location: gestione_utenti.php?error=errore_stato");
    exit;
}

$stmtUpdate->bind_param("si", $nuovoStato, $id);

if ($stmtUpdate->execute()) {
    log_attivita($conn, "stato", "utente", $id, "Cambio stato utente: " . $utente["username"] . " -> " . $nuovoStato);
    header("Location: gestione_utenti.php?success=stato_aggiornato");
    exit;
}

header("Location: gestione_utenti.php?error=errore_stato");
exit;