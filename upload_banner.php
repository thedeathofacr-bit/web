<?php
include "connessione.php";
require_user_page($conn);
$id_utente = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_banner'])) {
    $file = $_FILES['file_banner'];
    // Aggiunta 'gif' alle estensioni per lo stile Discord
    $estensioni_permesse = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $estensione = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (in_array($estensione, $estensioni_permesse)) {
        if ($file['size'] <= 8000000) { // Limite 8MB (le GIF pesano di più)
            
            $target_dir = "uploads/banners/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $nuovo_nome = "banner_" . $id_utente . "_" . time() . "." . $estensione;
            $target_file = $target_dir . $nuovo_nome;

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // Aggiorna Database (Assicurati che la colonna 'banner' esista)
                $stmt = $conn->prepare("UPDATE utenti SET banner = ? WHERE id = ?");
                $stmt->bind_param("si", $nuovo_nome, $id_utente);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

header("Location: profilo_view.php?updated=banner");
exit();