<?php
include "connessione.php";
require_user_page($conn);

header('Content-Type: application/json');

$id_utente   = $_SESSION['user_id'];
$id_libreria = current_library_id();
$action      = $_POST['action'] ?? $_GET['action'] ?? '';

// ── GET: carica recensioni di un libro ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    $id_libro = (int)($_GET['id_libro'] ?? 0);
    if (!$id_libro) { echo json_encode(['success' => false, 'message' => 'ID libro mancante']); exit; }

    // Media voti
    $stmt = $conn->prepare("
        SELECT AVG(voto) AS media, COUNT(*) AS totale
        FROM recensioni
        WHERE id_libro = ? AND id_libreria = ?
    ");
    $stmt->bind_param("ii", $id_libro, $id_libreria);
    $stmt->execute();
    $meta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Lista recensioni
    $stmt = $conn->prepare("
        SELECT r.id, r.voto, r.titolo, r.testo, r.data_recensione,
               u.nome, u.cognome, u.foto,
               (r.id_utente = ?) AS mia
        FROM recensioni r
        JOIN utenti u ON u.id = r.id_utente
        WHERE r.id_libro = ? AND r.id_libreria = ?
        ORDER BY r.data_recensione DESC
    ");
    $stmt->bind_param("iii", $id_utente, $id_libro, $id_libreria);
    $stmt->execute();
    $result = $stmt->get_result();
    $recensioni = [];
    while ($row = $result->fetch_assoc()) $recensioni[] = $row;
    $stmt->close();

    echo json_encode([
        'success'    => true,
        'media'      => round((float)($meta['media'] ?? 0), 1),
        'totale'     => (int)($meta['totale'] ?? 0),
        'recensioni' => $recensioni
    ]);
    exit;
}

// ── POST: salva / aggiorna recensione ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'salva') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $id_libro = (int)($_POST['id_libro'] ?? 0);
    $voto     = (int)($_POST['voto']     ?? 0);
    $titolo   = trim($_POST['titolo']    ?? '');
    $testo    = trim($_POST['testo']     ?? '');

    if (!$id_libro || $voto < 1 || $voto > 5) {
        echo json_encode(['success' => false, 'message' => 'Dati non validi']); exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO recensioni (id_libro, id_utente, id_libreria, voto, titolo, testo)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE voto = VALUES(voto), titolo = VALUES(titolo), testo = VALUES(testo), data_recensione = NOW()
    ");
    $stmt->bind_param("iiiiss", $id_libro, $id_utente, $id_libreria, $voto, $titolo, $testo);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Recensione salvata!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio']);
    }
    $stmt->close();
    exit;
}

// ── POST: elimina recensione ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'elimina') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $id_recensione = (int)($_POST['id_recensione'] ?? 0);
    if (!$id_recensione) { echo json_encode(['success' => false, 'message' => 'ID mancante']); exit; }

    // Solo l'autore o un admin può eliminare
    if (is_admin()) {
        $stmt = $conn->prepare("DELETE FROM recensioni WHERE id = ? AND id_libreria = ?");
        $stmt->bind_param("ii", $id_recensione, $id_libreria);
    } else {
        $stmt = $conn->prepare("DELETE FROM recensioni WHERE id = ? AND id_utente = ? AND id_libreria = ?");
        $stmt->bind_param("iii", $id_recensione, $id_utente, $id_libreria);
    }

    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => $deleted > 0, 'message' => $deleted > 0 ? 'Eliminata' : 'Non trovata']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Azione non valida']);
