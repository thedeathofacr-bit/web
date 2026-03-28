<?php
include "connessione.php";

$email = $_POST['email'];
$username = $_POST['username'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$codice = rand(100000,999999);
$scadenza = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$stmt = $conn->prepare("
INSERT INTO utenti (email,username,password,codice_verifica,codice_scadenza)
VALUES (?,?,?,?,?)
");

$stmt->bind_param("sssss",$email,$username,$password,$codice,$scadenza);
$stmt->execute();

$utente_id = $stmt->insert_id;

mail($email,"Codice verifica","Il tuo codice è: $codice");

header("Location: verifica_email.php?id=".$utente_id);