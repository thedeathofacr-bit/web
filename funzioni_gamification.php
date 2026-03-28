<?php
// funzioni_gamification.php

function aggiungiPunti($conn, $id_utente, $punti) {
    // 1. Aggiunge i punti
    $stmt = $conn->prepare("UPDATE utenti SET punti_esperienza = punti_esperienza + ? WHERE id = ?");
    $stmt->bind_param("ii", $punti, $id_utente);
    $stmt->execute();

    // 2. Calcola il nuovo livello (ogni 100 XP si sale di livello)
    $stmt = $conn->prepare("SELECT punti_esperienza FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $id_utente);
    $stmt->execute();
    $xp = $stmt->get_result()->fetch_assoc()['punti_esperienza'];
    
    $nuovo_livello = floor($xp / 100) + 1;

    // 3. Aggiorna il livello nel DB
    $stmt = $conn->prepare("UPDATE utenti SET livello = ? WHERE id = ?");
    $stmt->bind_param("ii", $nuovo_livello, $id_utente);
    $stmt->execute();
    
    return ["xp" => $xp, "livello" => $nuovo_livello];
}

function assegnaMedaglia($conn, $id_utente, $tipo) {
    // Controlla se l'ha già
    $stmt = $conn->prepare("SELECT id FROM medaglie_utenti WHERE id_utente = ? AND tipo_medaglia = ?");
    $stmt->bind_param("is", $id_utente, $tipo);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows == 0) {
        $ins = $conn->prepare("INSERT INTO medaglie_utenti (id_utente, tipo_medaglia) VALUES (?, ?)");
        $ins->bind_param("is", $id_utente, $tipo);
        $ins->execute();
        return true; // Nuova medaglia sbloccata!
    }
    return false;
}