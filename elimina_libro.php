<?php
include "connessione.php";

if (!is_logged() || !is_admin()) {
    http_response_code(403);
    exit("Accesso negato.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metodo non consentito.");
}

verify_csrf_or_die($conn);

$bookId = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
$libraryId = (int) current_library_id();

if ($bookId <= 0 || $libraryId <= 0) {
    http_response_code(400);
    exit("Dati non validi.");
}

/* Recupera il libro solo della libreria corrente */
$stmt = $conn->prepare("
    SELECT id, titolo, immagine
    FROM libri
    WHERE id = ? AND id_libreria = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    exit("Errore interno: " . $conn->error);
}

$stmt->bind_param("ii", $bookId, $libraryId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows !== 1) {
    $stmt->close();
    http_response_code(404);
    exit("Libro non trovato.");
}

$libro = $result->fetch_assoc();
$stmt->close();

/* Elimina il libro solo dalla libreria corrente */
$deleteStmt = $conn->prepare("
    DELETE FROM libri
    WHERE id = ? AND id_libreria = ?
    LIMIT 1
");

if (!$deleteStmt) {
    http_response_code(500);
    exit("Errore interno: " . $conn->error);
}

$deleteStmt->bind_param("ii", $bookId, $libraryId);

if (!$deleteStmt->execute()) {
    $deleteStmt->close();
    http_response_code(500);
    exit("Errore durante l'eliminazione: " . $deleteStmt->error);
}

$deleteStmt->close();

/* Elimina immagine se esiste */
if (!empty($libro["immagine"])) {
    $relativePath = ltrim((string)$libro["immagine"], "/\\");
    $imagePath = __DIR__ . "/" . $relativePath;

    if (is_file($imagePath)) {
        @unlink($imagePath);
    }
}

/* Log attività */
if (function_exists("log_attivita")) {
    log_attivita(
        $conn,
        "eliminazione",
        "libro",
        $bookId,
        "Eliminato libro: " . ($libro["titolo"] ?? "Sconosciuto")
    );
}

echo "OK";
exit;
