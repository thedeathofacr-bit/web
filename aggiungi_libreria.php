<?php
include "connessione.php";
require_login();

$utente = $_SESSION['utente_id'];
$libro = $_GET['id'];

$stmt = $conn->prepare("
INSERT INTO libreria_utente
(utente_id,libro_id,tipo)
VALUES (?,?, 'acquistato')
");

$stmt->bind_param("ii",$utente,$libro);
$stmt->execute();

header("Location: mia_libreria.php");