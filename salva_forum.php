<?php
include "connessione.php";
require_user_page($conn);
$id_utente = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['titolo_discussione'])) {
    $titolo_disc = $conn->real_escape_string($_POST['titolo_discussione']);
    $l_titolo = $conn->real_escape_string($_POST['libro_titolo']);
    $l_autore = $conn->real_escape_string($_POST['libro_autore']);
    $l_genere = $conn->real_escape_string($_POST['libro_genere']);
    $l_prezzo = !empty($_POST['libro_prezzo']) ? floatval($_POST['libro_prezzo']) : 0;
    $messaggio = $conn->real_escape_string($_POST['messaggio']);

    $sql = "INSERT INTO forum_discussioni (id_utente, titolo_discussione, libro_titolo, libro_autore, libro_genere, libro_prezzo, messaggio) 
            VALUES ($id_utente, '$titolo_disc', '$l_titolo', '$l_autore', '$l_genere', $l_prezzo, '$messaggio')";
    
    if ($conn->query($sql)) {
        header("Location: forum_lista.php?status=created");
    } else {
        echo "Errore Database.";
    }
}
exit();