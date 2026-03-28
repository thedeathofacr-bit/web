<?php
include "connessione.php";
require_user_page($conn);
$id_utente = $_SESSION['user_id'];

header('Content-Type: application/json');

if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    
    // Controlla se il like esiste già
    $check = $conn->query("SELECT id FROM forum_likes WHERE id_utente = $id_utente AND id_discussione = $post_id");
    
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM forum_likes WHERE id_utente = $id_utente AND id_discussione = $post_id");
        $status = 'unliked';
    } else {
        $conn->query("INSERT INTO forum_likes (id_utente, id_discussione) VALUES ($id_utente, $post_id)");
        $status = 'liked';
    }

    // Conta i like totali aggiornati
    $res_count = $conn->query("SELECT COUNT(*) as tot FROM forum_likes WHERE id_discussione = $post_id");
    $count = $res_count->fetch_assoc()['tot'];

    echo json_encode([
        'status' => 'success',
        'action' => $status,
        'count' => $count
    ]);
} else {
    echo json_encode(['status' => 'error']);
}
exit();