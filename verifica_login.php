<?php
session_start();
include "connessione.php";

$username = isset($_POST["username"]) ? trim($_POST["username"]) : "";
$password = isset($_POST["password"]) ? $_POST["password"] : "";

$stmt = $conn->prepare("SELECT id, username, password, stato, ruolo FROM utenti WHERE username = ? AND ruolo = 'admin' LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row["stato"] !== "attivo") {
        header("Location: login_gestioneutenti.php?error=account_disattivato");
        exit;
    }

    if (password_verify($password, $row["password"])) {
        $_SESSION["admin"] = $row["id"];
        $_SESSION["user_id"] = $row["id"];
        $_SESSION["username"] = $row["username"];
        $_SESSION["ruolo"] = $row["ruolo"];

        header("Location: gestione_utenti.php");
        exit;
    }
}

header("Location: login_gestioneutenti.php?error=credenziali_non_valide");
exit;
