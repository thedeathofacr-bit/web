<?php
// Impedisce a eventuali warning di rompere il JSON
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

include "connessione.php";
// Verificata la corrispondenza con la protezione del frontend
require_admin_page($conn); 

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // --- AZIONE: RESTITUISCI ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restituisci') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception("Token CSRF non valido.");
        }

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) throw new Exception("ID prestito mancante.");

        $oggi = date('Y-m-d');
        $stmt = $conn->prepare("UPDATE prestiti SET stato = 'restituito', data_restituzione_effettiva = ? WHERE id = ?");
        $stmt->bind_param("si", $oggi, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) throw new Exception("Impossibile aggiornare il prestito.");
        
        echo json_encode(['success' => true]);
        exit;
    }

    // --- AZIONE: LISTA (Filtri, Ricerca, Stats) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $search = $_GET['q'] ?? '';
        $stato = $_GET['stato'] ?? '';
        $sort = $_GET['sort'] ?? 'recenti';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $oggi = date('Y-m-d');

        // 1. Statistiche
        $statSql = "SELECT 
            COUNT(*) as totale,
            SUM(CASE WHEN stato = 'in_prestito' THEN 1 ELSE 0 END) as attivi,
            SUM(CASE WHEN stato = 'restituito' THEN 1 ELSE 0 END) as restituiti,
            SUM(CASE WHEN stato = 'in_prestito' AND data_restituzione_prevista < '$oggi' THEN 1 ELSE 0 END) as ritardo
            FROM prestiti";
        $stats = $conn->query($statSql)->fetch_assoc();

        // 2. Costruzione Query
        $where = ["1=1"];
        $params = [];
        $types = "";

        if ($search) {
            $where[] = "(l.titolo LIKE ? OR u.nome LIKE ? OR u.cognome LIKE ?)";
            $s = "%$search%";
            array_push($params, $s, $s, $s);
            $types .= "sss";
        }

        if ($stato === 'in_prestito') $where[] = "p.stato = 'in_prestito'";
        if ($stato === 'restituito') $where[] = "p.stato = 'restituito'";
        if ($stato === 'ritardo') $where[] = "p.stato = 'in_prestito' AND p.data_restituzione_prevista < '$oggi'";

        $whereClause = "WHERE " . implode(" AND ", $where);
        
        // 3. Conteggio per paginazione
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM prestiti p JOIN libri l ON p.id_libro = l.id JOIN utenti u ON p.id_utente = u.id $whereClause");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $totalItems = $countStmt->get_result()->fetch_assoc()['total'];
        $totalPages = ceil($totalItems / $limit);

        // 4. Dati finali
        $order = "ORDER BY p.data_prestito DESC";
        if ($sort === 'scadenza_asc') $order = "ORDER BY p.data_restituzione_prevista ASC";
        if ($sort === 'cliente_asc') $order = "ORDER BY u.cognome ASC";

        $sql = "SELECT p.*, l.titolo, l.autore, CONCAT(u.nome, ' ', u.cognome) as nome_cliente, 
                u.email as email_cliente, 
                IF(p.stato = 'in_prestito' AND p.data_restituzione_prevista < '$oggi', 1, 0) as in_ritardo
                FROM prestiti p 
                JOIN libri l ON p.id_libro = l.id 
                JOIN utenti u ON p.id_utente = u.id 
                $whereClause $order LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $params[] = $limit; $params[] = $offset;
        $types .= "ii";
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'items' => $items,
            'totalPages' => $totalPages
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}