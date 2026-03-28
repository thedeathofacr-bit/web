<?php
session_start();

if (!isset($_SESSION["admin"])) {
    header("Location: login_gestioneutenti.php");
    exit;
}

include "connessione.php";

$id = isset($_POST["id"]) ? $_POST["id"] : "";
$username = isset($_POST["username"]) ? trim($_POST["username"]) : "";
$password = isset($_POST["password"]) ? trim($_POST["password"]) : "";
$ruolo = isset($_POST["ruolo"]) ? trim($_POST["ruolo"]) : "";

if (!is_numeric($id) || (int)$id <= 0) {
    header("Location: gestione_utenti.php?error=id_non_valido");
    exit;
}

$id = (int) $id;

if ($username === "" || $ruolo === "") {
    header("Location: modifica_utente.php?id=" . $id . "&error=campi_vuoti");
    exit;
}

if ($ruolo !== "utente" && $ruolo !== "admin") {
    header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
    exit;
}

$stmtCheckUser = $conn->prepare("SELECT id, ruolo FROM utenti WHERE id = ?");
if (!$stmtCheckUser) {
    header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
    exit;
}

$stmtCheckUser->bind_param("i", $id);
$stmtCheckUser->execute();
$resultCheckUser = $stmtCheckUser->get_result();

if (!$resultCheckUser || $resultCheckUser->num_rows === 0) {
    header("Location: gestione_utenti.php?error=utente_non_trovato");
    exit;
}

$utenteAttuale = $resultCheckUser->fetch_assoc();

$stmtCheckUsername = $conn->prepare("SELECT id FROM utenti WHERE username = ? AND id != ?");
if (!$stmtCheckUsername) {
    header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
    exit;
}

$stmtCheckUsername->bind_param("si", $username, $id);
$stmtCheckUsername->execute();
$resultCheckUsername = $stmtCheckUsername->get_result();

if ($resultCheckUsername && $resultCheckUsername->num_rows > 0) {
    header("Location: modifica_utente.php?id=" . $id . "&error=username_esistente");
    exit;
}

if ($utenteAttuale["ruolo"] === "admin" && $ruolo !== "admin") {
    $resultAdmin = $conn->query("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'admin'");

    if (!$resultAdmin) {
        header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
        exit;
    }

    $rowAdmin = $resultAdmin->fetch_assoc();
    $totaleAdmin = (int) $rowAdmin["totale"];

    if ($totaleAdmin <= 1) {
        header("Location: gestione_utenti.php?error=ultimo_admin_non_modificabile");
        exit;
    }
}

if ($password !== "") {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmtUpdate = $conn->prepare("UPDATE utenti SET username = ?, password = ?, ruolo = ? WHERE id = ?");
    if (!$stmtUpdate) {
        header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
        exit;
    }

    $stmtUpdate->bind_param("sssi", $username, $passwordHash, $ruolo, $id);
} else {
    $stmtUpdate = $conn->prepare("UPDATE utenti SET username = ?, ruolo = ? WHERE id = ?");
    if (!$stmtUpdate) {
        header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
        exit;
    }

    $stmtUpdate->bind_param("ssi", $username, $ruolo, $id);
}

if ($stmtUpdate->execute()) {
    header("Location: gestione_utenti.php?success=utente_modificato");
    exit;
} else {
    header("Location: modifica_utente.php?id=" . $id . "&error=errore_modifica");
    exit;
}