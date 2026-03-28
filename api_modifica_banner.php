<?php
session_start();
include "connessione.php";

// Verifica sicurezza base
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $id_utente = $_SESSION['user_id'];
    $file = $_FILES['profile_image'];
    
    // Controlliamo che l'upload sia partito senza errori
    if ($file['error'] === UPLOAD_ERR_OK) {
        $nome_file = $file['name'];
        $tmp_name = $file['tmp_name'];
        $dimensione = $file['size'];
        
        $estensioni_permesse = ['jpg', 'jpeg', 'png', 'webp'];
        $estensione = strtolower(pathinfo($nome_file, PATHINFO_EXTENSION));

        // Filtro formati
        if (!in_array($estensione, $estensioni_permesse)) {
            header("Location: profilo_view.php?errore=" . urlencode("Formato foto non valido. Usa JPG, PNG o WEBP."));
            exit;
        }

        // Filtro peso (Max 3 MB per le foto profilo)
        if ($dimensione > 3145728) {
            header("Location: profilo_view.php?errore=" . urlencode("Immagine troppo pesante. Max 3MB."));
            exit;
        }

        // Creiamo la cartella se non esiste
        $cartella_destinazione = "uploads/profili/";
        if (!is_dir($cartella_destinazione)) {
            mkdir($cartella_destinazione, 0777, true);
        }

        // Nome pulito e anti-conflitto per il file
        $nuovo_nome = "avatar_usr" . $id_utente . "_" . time() . "." . $estensione;
        $percorso_completo = $cartella_destinazione . $nuovo_nome;

        if (move_uploaded_file($tmp_name, $percorso_completo)) {
            // Aggiorniamo il database: colonna 'foto'
            $stmt = $conn->prepare("UPDATE utenti SET foto = ? WHERE id = ?");
            $stmt->bind_param("si", $nuovo_nome, $id_utente);
            
            if ($stmt->execute()) {
                header("Location: profilo_view.php?successo=" . urlencode("Avatar aggiornato!"));
            } else {
                header("Location: profilo_view.php?errore=" . urlencode("Errore DB durante l'aggiornamento foto."));
            }
            $stmt->close();
        } else {
            header("Location: profilo_view.php?errore=" . urlencode("Impossibile caricare il file."));
        }
    } else {
        header("Location: profilo_view.php?errore=" . urlencode("Errore nell'upload. Riprova."));
    }
} else {
    header("Location: profilo_view.php");
}
exit;