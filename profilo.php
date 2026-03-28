<?php
session_start();
require_once "connessione.php"; // connessione DB

// Controllo login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Non autorizzato"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Funzione risposta JSON
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ============================
// 🔹 GET → DATI PROFILO
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $stmt = $conn->prepare("SELECT id, nome, cognome, username, email, foto_profilo, tema, lingua FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    jsonResponse($user);
}


// ============================
// 🔹 POST → UPDATE PROFILO
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Dati base
    $nome = $_POST['nome'] ?? '';
    $cognome = $_POST['cognome'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $tema = $_POST['tema'] ?? 'dark';
    $lingua = $_POST['lingua'] ?? 'it';

    // VALIDAZIONE BASE
    if (empty($nome) || empty($email)) {
        jsonResponse(["error" => "Nome ed email obbligatori"]);
    }

    // ============================
    // 🔹 UPLOAD FOTO PROFILO
    // ============================
    $foto_path = null;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($_FILES['foto']['type'], $allowed)) {
            jsonResponse(["error" => "Formato immagine non valido"]);
        }

        if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            jsonResponse(["error" => "Immagine troppo grande (max 2MB)"]);
        }

        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $filename = "profile_" . $user_id . "_" . time() . "." . $ext;

        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $target = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            $foto_path = $target;
        }
    }

    // ============================
    // 🔹 UPDATE PROFILO
    // ============================
    if ($foto_path) {
        $stmt = $conn->prepare("
            UPDATE utenti 
            SET nome=?, cognome=?, username=?, email=?, tema=?, lingua=?, foto_profilo=? 
            WHERE id=?
        ");
        $stmt->bind_param("sssssssi", $nome, $cognome, $username, $email, $tema, $lingua, $foto_path, $user_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE utenti 
            SET nome=?, cognome=?, username=?, email=?, tema=?, lingua=? 
            WHERE id=?
        ");
        $stmt->bind_param("ssssssi", $nome, $cognome, $username, $email, $tema, $lingua, $user_id);
    }

    $stmt->execute();


    // ============================
    // 🔹 CAMBIO PASSWORD
    // ============================
    if (!empty($_POST['password_attuale']) && !empty($_POST['nuova_password'])) {

        $stmt = $conn->prepare("SELECT password FROM utenti WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!password_verify($_POST['password_attuale'], $user['password'])) {
            jsonResponse(["error" => "Password attuale errata"]);
        }

        $new_password = password_hash($_POST['nuova_password'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE utenti SET password=? WHERE id=?");
        $stmt->bind_param("si", $new_password, $user_id);
        $stmt->execute();
    }


    // ============================
    // 🔹 AGGIORNA SESSIONE
    // ============================
    $_SESSION['username'] = $username;
    $_SESSION['tema'] = $tema;
    if ($foto_path) {
        $_SESSION['foto'] = $foto_path;
    }

    jsonResponse(["success" => true, "message" => "Profilo aggiornato"]);
}