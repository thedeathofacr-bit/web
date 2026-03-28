<?php
header('Content-Type: application/json; charset=utf-8');
require 'connessione.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit;
}

$id_utente = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// Recuperiamo l'ID della libreria corrente (necessario per l'insert)
// Uso la funzione che era presente nel tuo file originale
$id_libreria = function_exists('current_library_id') ? current_library_id() : 1; 

// --- CARICA LISTA (GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'lista') {
    $sql = "SELECT l.id AS id_libro, l.titolo, l.autore, l.genere, 0 AS media_voti, 0 AS num_recensioni, l.prezzo, l.immagine 
            FROM lista_desideri ld
            JOIN libri l ON ld.id_libro = l.id
            WHERE ld.id_utente = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_utente);
    $stmt->execute();
    $result = $stmt->get_result();
    $lista = [];
    while ($row = $result->fetch_assoc()) { $lista[] = $row; }
    echo json_encode(['success' => true, 'lista' => $lista]);
    exit;
}

// --- AZIONI POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_libro = $_POST['id_libro'] ?? $_POST['id'] ?? 0;

    if ($action === 'toggle' || $action === 'aggiungi') {
        
        if ($id_libro <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID libro non valido']);
            exit;
        }

        // 1. Verifica se esiste già
        $check = $conn->prepare("SELECT 1 FROM lista_desideri WHERE id_utente = ? AND id_libro = ?");
        $check->bind_param("ii", $id_utente, $id_libro);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            // Se esiste, lo rimuoviamo
            $sql = "DELETE FROM lista_desideri WHERE id_utente = ? AND id_libro = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $id_utente, $id_libro);
            $msg = "Rimosso dai desideri";
            $is_added = false;
        } else {
            // Se non esiste, lo aggiungiamo includendo id_libreria
            // FIX: Aggiunto id_libreria nella query di INSERT
            $sql = "INSERT INTO lista_desideri (id_utente, id_libro, id_libreria) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $id_utente, $id_libro, $id_libreria);
            $msg = "Aggiunto ai desideri! ❤️";
            $is_added = true;
        }

        if ($stmt && $stmt->execute()) {
            echo json_encode(['success' => true, 'message' => $msg, 'added' => $is_added]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore database: ' . ($stmt ? $stmt->error : $conn->error)]);
        }
        exit;
    }

    // RIMUOVI
    if ($action === 'rimuovi') {
        $sql = "DELETE FROM lista_desideri WHERE id_utente = ? AND id_libro = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id_utente, $id_libro);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // SVUOTA TUTTO
    if ($action === 'svuota') {
        $sql = "DELETE FROM lista_desideri WHERE id_utente = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_utente);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Azione non riconosciuta: ' . $action]);
exit;