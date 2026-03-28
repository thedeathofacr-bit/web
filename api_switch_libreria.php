<?php
// Usiamo un trucco per catturare gli errori fatali senza rompere il JSON
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include "connessione.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato.']);
    exit;
}

$nuova_libreria_id = (int)($_POST['id_libreria'] ?? 0);
$mio_id = $_SESSION['user_id'];

if (!$nuova_libreria_id) {
    echo json_encode(['success' => false, 'message' => 'ID libreria mancante.']);
    exit;
}

try {
    // Estraiamo anche il "codice" della libreria
    $sql = "SELECT l.id, l.nome, l.codice, ul.ruolo 
            FROM libreria l 
            JOIN utenti_librerie ul ON l.id = ul.id_libreria 
            WHERE l.id = ? AND ul.id_utente = ?";
            
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Errore DB (Verifica Sede): " . $conn->error);
    }
    
    $stmt->bind_param("ii", $nuova_libreria_id, $mio_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Accesso negato: non sei registrato in questa sede.']);
        exit;
    }

    $dati_sede = $result->fetch_assoc();
    $stmt->close();

    // Aggiorniamo il database in modo PERMANENTE
    $updateSql = "UPDATE utenti SET libreria_id = ? WHERE id = ?";
    $stmtUpdate = $conn->prepare($updateSql);
    
    if (!$stmtUpdate) {
        throw new Exception("Errore DB (Aggiornamento Profilo): " . $conn->error);
    }
    
    $stmtUpdate->bind_param("ii", $dati_sede['id'], $mio_id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    $_SESSION['id_libreria'] = $dati_sede['id'];
    $_SESSION['nome_libreria'] = $dati_sede['nome']; 
    $_SESSION['ruolo_libreria'] = $dati_sede['ruolo'];

    // CHICCA: Salviamo il NOME e il CODICE nel cookie (durata 30 giorni)
    setcookie('ultima_sede_nome', $dati_sede['nome'], time() + (86400 * 30), "/");
    setcookie('ultima_sede_codice', $dati_sede['codice'], time() + (86400 * 30), "/");

    echo json_encode([
        'success' => true, 
        'message' => 'Sede cambiata con successo!',
        'nuova_libreria' => $dati_sede['nome']
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'CRASH SERVER: ' . $e->getMessage()]);
}