<?php
include "connessione.php";

$sql = "SELECT * FROM libri";
$result = $conn->query($sql);

if ($result->num_rows > 0) {

    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row["id"] .
             " | Titolo: " . $row["titolo"] .
             " | Autore: " . $row["autore"] .
             " | Anno: " . $row["anno_pubblicazione"] .
             "<br>";
    }

} else {
    echo "Nessun libro trovato";
}

$conn->close();
?>