<?php
require_once "security.php";

if (!isset($_SESSION["utente"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["ruolo"]) || $_SESSION["ruolo"] !== "admin") {
    http_response_code(403);
    die("Accesso negato");
}