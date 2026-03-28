<?php
include "connessione.php";
header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';
$value = trim($_GET['value'] ?? '');
$lib_id = (int)($_GET['lib_id'] ?? 0);

if ($value === '') {
    echo json_encode(['available' => false]);
    exit;
}

if ($type === 'email') {
    // Controlla se l'email esiste in tutto il DB
    $stmt = $conn->prepare("SELECT id FROM utenti WHERE email = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        echo json_encode(['available' => !$exists]);
    }
    exit;
}

if ($type === 'username') {
    // Controlla se lo username esiste NELLA STESSA libreria selezionata
    $stmt = $conn->prepare("SELECT id FROM utenti WHERE username = ? AND libreria_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('si', $value, $lib_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        echo json_encode(['available' => !$exists]);
    }
    exit;
}

echo json_encode(['available' => false]);