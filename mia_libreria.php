<?php
include "connessione.php";
require_login();

$utente_id = $_SESSION['utente_id'];

$stmt = $conn->prepare("
SELECT libri.*
FROM libreria_utente
JOIN libri ON libri.id=libreria_utente.libro_id
WHERE libreria_utente.utente_id=?
");

$stmt->bind_param("i",$utente_id);
$stmt->execute();

$result = $stmt->get_result();

while($row = $result->fetch_assoc()){

echo "<h3>".$row['titolo']."</h3>";

}