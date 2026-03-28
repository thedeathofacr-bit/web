<?php
include "connessione.php";

$id = $_POST['id'];
$codice = $_POST['codice'];

$stmt = $conn->prepare("
SELECT codice_verifica,codice_scadenza
FROM utenti
WHERE id=?
");

$stmt->bind_param("i",$id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if($user['codice_verifica'] == $codice){

$conn->query("
UPDATE utenti
SET email_verificata=1,
codice_verifica=NULL
WHERE id=$id
");

header("Location: login.php");

}else{

echo "Codice errato";

}