<?php
include "connessione.php";
require_admin_page($conn);

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: prestiti.php");
    exit;
}

$stmt = $conn->prepare("
    UPDATE prestiti
    SET stato = 'restituito', data_restituzione_effettiva = CURDATE()
    WHERE id = ? AND stato = 'in_prestito'
");

if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    log_attivita($conn, "modifica", "prestito", $id, "Prestito segnato come restituito");
}

header("Location: prestiti.php");
exit;