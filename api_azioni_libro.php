<?php
session_start();
include "connessione.php";
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Devi effettuare il login per compiere questa azione."]);
    exit;
}

$id_utente = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$id_libro = isset($_POST['id_libro']) ? (int)$_POST['id_libro'] : 0;

if ($id_libro <= 0) {
    echo json_encode(["success" => false, "message" => "Libro non valido."]);
    exit;
}

// AZIONE: RICHIEDI PRESTITO
if ($action === 'prestito') {
    // Controlliamo se ha già questo libro in prestito (e non lo ha ancora restituito)
    $check = $conn->prepare("SELECT id FROM prestiti WHERE id_utente = ? AND id_libro = ? AND data_restituzione_effettiva IS NULL");
    $check->bind_param("ii", $id_utente, $id_libro);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Hai già questo libro in prestito!"]);
        exit;
    }
    
    // Inseriamo il prestito (Durata standard: 30 giorni)
    $stmt = $conn->prepare("INSERT INTO prestiti (id_utente, id_libro, data_restituzione_prevista) VALUES (?, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY))");
    if ($stmt) {
        $stmt->bind_param("ii", $id_utente, $id_libro);
        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Errore nel salvataggio del prestito."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Errore SQL prestiti."]);
    }
    exit;
}

// AZIONE: SALVA NEI DESIDERI
if ($action === 'desideri') {
    // Controlliamo se è già nei desideri
    $check = $conn->prepare("SELECT * FROM lista_desideri WHERE id_utente = ? AND id_libro = ?");
    $check->bind_param("ii", $id_utente, $id_libro);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Questo libro è già nella tua lista!"]);
        exit;
    }
    
    // Inseriamo nei desideri
    $stmt = $conn->prepare("INSERT INTO lista_desideri (id_utente, id_libro) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $id_utente, $id_libro);
        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Errore nell'aggiunta ai desideri."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Errore SQL desideri."]);
    }
    exit;
}

echo json_encode(["success" => false, "message" => "Azione non riconosciuta."]);
?>