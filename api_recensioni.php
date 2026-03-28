<?php
include "connessione.php";
require_user_page($conn);

header('Content-Type: application/json; charset=utf-8');

// ── AUTO-CREAZIONE TABELLE (Commenti e Likes) ──
$conn->query("
    CREATE TABLE IF NOT EXISTS commenti_recensioni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_recensione INT NOT NULL,
        id_utente INT NOT NULL,
        testo TEXT NOT NULL,
        data_commento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS likes_recensioni (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_recensione INT NOT NULL,
        id_utente INT NOT NULL,
        data_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY(id_recensione, id_utente)
    )
");

$id_utente = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── GET: Leggi le recensioni (con commenti e LIKES) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'leggi') {
    $id_libro = (int)($_GET['id_libro'] ?? 0);
    
    // 1. Peschiamo le recensioni, badge e likes
    $stmt = $conn->prepare("
        SELECT r.id AS id_recensione, r.voto, r.testo, r.data_recensione, 
               u.username, u.foto, u.ruolo,
               (SELECT COUNT(id) FROM recensioni WHERE id_utente = u.id) AS tot_recensioni,
               (SELECT COUNT(id) FROM prestiti WHERE id_utente = u.id) AS tot_prestiti,
               (SELECT COUNT(id) FROM likes_recensioni WHERE id_recensione = r.id) AS tot_likes,
               (SELECT COUNT(id) FROM likes_recensioni WHERE id_recensione = r.id AND id_utente = ?) AS user_liked
        FROM recensioni r 
        JOIN utenti u ON r.id_utente = u.id 
        WHERE r.id_libro = ? 
        ORDER BY r.data_recensione DESC
    ");
    
    $recensioni = [];
    if ($stmt) {
        $stmt->bind_param("ii", $id_utente, $id_libro);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $row['commenti'] = []; // Prepariamo l'array per le risposte
            $row['user_liked'] = (int)$row['user_liked'] > 0; // Booleano
            $recensioni[$row['id_recensione']] = $row;
        }
        $stmt->close();
    }
    
    // 2. Peschiamo i commenti (risposte) collegati
    if (!empty($recensioni)) {
        $stmtC = $conn->prepare("
            SELECT c.id AS id_commento, c.id_recensione, c.testo, c.data_commento,
                   u.username, u.foto, u.ruolo
            FROM commenti_recensioni c
            JOIN utenti u ON c.id_utente = u.id
            JOIN recensioni r ON c.id_recensione = r.id
            WHERE r.id_libro = ?
            ORDER BY c.data_commento ASC
        ");
        if ($stmtC) {
            $stmtC->bind_param("i", $id_libro);
            $stmtC->execute();
            $resC = $stmtC->get_result();
            while ($rowC = $resC->fetch_assoc()) {
                if (isset($recensioni[$rowC['id_recensione']])) {
                    $recensioni[$rowC['id_recensione']]['commenti'][] = $rowC;
                }
            }
            $stmtC->close();
        }
    }
    
    $recensioni_list = array_values($recensioni);
    
    // 3. Verifica se l'utente attuale ha già recensito questo libro
    $ha_recensito = false;
    $stmt2 = $conn->prepare("SELECT id FROM recensioni WHERE id_libro = ? AND id_utente = ?");
    if ($stmt2) {
        $stmt2->bind_param("ii", $id_libro, $id_utente);
        $stmt2->execute();
        if ($stmt2->get_result()->num_rows > 0) {
            $ha_recensito = true;
        }
        $stmt2->close();
    }

    echo json_encode(['success' => true, 'recensioni' => $recensioni_list, 'ha_recensito' => $ha_recensito]);
    exit;
}

// ── POST: Metti/Togli LIKE a una recensione ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_like') {
    $id_recensione = (int)($_POST['id_recensione'] ?? 0);
    
    if ($id_recensione <= 0) {
        echo json_encode(['success' => false, 'message' => 'Recensione non valida.']);
        exit;
    }

    // Controlliamo se ha già messo like
    $stmtCheck = $conn->prepare("SELECT id FROM likes_recensioni WHERE id_recensione = ? AND id_utente = ?");
    $stmtCheck->bind_param("ii", $id_recensione, $id_utente);
    $stmtCheck->execute();
    $esiste = $stmtCheck->get_result()->num_rows > 0;
    $stmtCheck->close();

    if ($esiste) {
        // Rimuovi il like
        $stmt = $conn->prepare("DELETE FROM likes_recensioni WHERE id_recensione = ? AND id_utente = ?");
        $stmt->bind_param("ii", $id_recensione, $id_utente);
        $stmt->execute();
        $is_liked = false;
    } else {
        // Aggiungi il like
        $stmt = $conn->prepare("INSERT INTO likes_recensioni (id_recensione, id_utente) VALUES (?, ?)");
        $stmt->bind_param("ii", $id_recensione, $id_utente);
        $stmt->execute();
        $is_liked = true;
    }
    
    // Contiamo i nuovi like totali per restituirli al frontend
    $stmtTot = $conn->prepare("SELECT COUNT(id) AS tot FROM likes_recensioni WHERE id_recensione = ?");
    $stmtTot->bind_param("i", $id_recensione);
    $stmtTot->execute();
    $tot_likes = $stmtTot->get_result()->fetch_assoc()['tot'];
    
    echo json_encode(['success' => true, 'is_liked' => $is_liked, 'tot_likes' => $tot_likes]);
    exit;
}

// ── POST: Aggiungi una nuova recensione (con filtri) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'aggiungi') {
    $id_libro = (int)($_POST['id_libro'] ?? 0);
    $voto = (int)($_POST['voto'] ?? 0);
    $testo = trim($_POST['testo'] ?? '');

    if ($id_libro <= 0 || $voto < 1 || $voto > 5) {
        echo json_encode(['success' => false, 'message' => 'Seleziona un voto valido.']);
        exit;
    }

    if (!empty($testo)) {
        $testo = strip_tags($testo);
        if (mb_strlen($testo, 'UTF-8') < 5) {
            echo json_encode(['success' => false, 'message' => 'Recensione troppo corta.']);
            exit;
        }
        
        $bad_words = ['cazzo', 'merda', 'stronzo', 'puttana', 'troia', 'vaffanculo', 'bastardo', 'coglione', 'zoccola', 'minchia', 'porco'];
        $testo_lower = mb_strtolower($testo, 'UTF-8');
        foreach ($bad_words as $word) {
            if (strpos($testo_lower, $word) !== false) {
                echo json_encode(['success' => false, 'message' => 'Linguaggio non consentito.']);
                exit;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO recensioni (id_libro, id_utente, voto, testo) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiis", $id_libro, $id_utente, $voto, $testo);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Recensione pubblicata!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore salvataggio.']);
        }
        $stmt->close();
    }
    exit;
}

// ── POST: Aggiungi un Commento/Risposta ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'aggiungi_commento') {
    $id_recensione = (int)($_POST['id_recensione'] ?? 0);
    $testo = trim($_POST['testo'] ?? '');

    if ($id_recensione <= 0 || empty($testo)) {
        echo json_encode(['success' => false, 'message' => 'Scrivi un commento.']);
        exit;
    }

    $testo = strip_tags($testo);
    $bad_words = ['cazzo', 'merda', 'stronzo', 'puttana', 'troia', 'vaffanculo', 'bastardo', 'coglione', 'zoccola', 'minchia', 'porco'];
    $testo_lower = mb_strtolower($testo, 'UTF-8');
    foreach ($bad_words as $word) {
        if (strpos($testo_lower, $word) !== false) {
            echo json_encode(['success' => false, 'message' => 'Linguaggio non consentito nel commento.']);
            exit;
        }
    }

    $stmt = $conn->prepare("INSERT INTO commenti_recensioni (id_recensione, id_utente, testo) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $id_recensione, $id_utente, $testo);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore database.']);
        }
        $stmt->close();
    }
    exit;
}

// ── POST: Elimina una recensione (SOLO ADMIN) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'elimina') {
    if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non autorizzato.']);
        exit;
    }

    $id_recensione = (int)($_POST['id_recensione'] ?? 0);
    
    // Pulizia a cascata
    $stmtC = $conn->prepare("DELETE FROM commenti_recensioni WHERE id_recensione = ?");
    if($stmtC) { $stmtC->bind_param("i", $id_recensione); $stmtC->execute(); $stmtC->close(); }
    
    $stmtL = $conn->prepare("DELETE FROM likes_recensioni WHERE id_recensione = ?");
    if($stmtL) { $stmtL->bind_param("i", $id_recensione); $stmtL->execute(); $stmtL->close(); }

    $stmt = $conn->prepare("DELETE FROM recensioni WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_recensione);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        $stmt->close();
    }
    exit;
}

// ── POST: Elimina un singolo commento (SOLO ADMIN) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'elimina_commento') {
    if (!isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Non autorizzato.']);
        exit;
    }

    $id_commento = (int)($_POST['id_commento'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM commenti_recensioni WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_commento);
        $stmt->execute();
        echo json_encode(['success' => true]);
        $stmt->close();
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Azione non valida']);