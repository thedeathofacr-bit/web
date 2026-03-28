<?php
session_start();
include "connessione.php";

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'msg' => 'Non autorizzato']));
}

$id_utente = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'crea') {
    // Generiamo numeri realistici finti
    $num = sprintf("4%03d %04d %04d %04d", mt_rand(0,999), mt_rand(0,9999), mt_rand(0,9999), mt_rand(0,9999));
    $mese = str_pad(mt_rand(1, 12), 2, '0', STR_PAD_LEFT);
    $anno = mt_rand(26, 32);
    $scadenza = "$mese/$anno";
    $cvc = mt_rand(100, 999);
    $saldo_iniziale = 50.00; // Diamo 50€ di benvenuto!
    
    $stmt = $conn->prepare("INSERT INTO carte_credito (id_utente, numero_carta, scadenza, cvc, saldo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssd", $id_utente, $num, $scadenza, $cvc, $saldo_iniziale);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Errore nella creazione.']);
    }
    $stmt->close();
} 
elseif ($action === 'ricarica') {
    $importo = (float)$_POST['importo'];
    
    if ($importo > 0 && $importo <= 500) {
        $stmt = $conn->prepare("UPDATE carte_credito SET saldo = saldo + ? WHERE id_utente = ?");
        $stmt->bind_param("di", $importo, $id_utente);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Errore server.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'msg' => 'Puoi ricaricare massimo 500€ alla volta.']);
    }
}
?>