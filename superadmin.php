<?php
require_once "security.php";

if (!isset($_SESSION["utente"])) {

    $_SESSION["redirect_after_login"] = $_SERVER["REQUEST_URI"];

    header("Location: login.php");
    exit;
}

if ($_SESSION["ruolo"] !== "superadmin") {

    die("Accesso negato");

}