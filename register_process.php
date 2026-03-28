<?php
include "connessione.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

verify_csrf_or_die($conn);

$ruoloScelto = (isset($_POST['ruolo']) && $_POST['ruolo'] === 'gestore') ? 'gestore' : 'utente';

$libreriaId = 0;
$email = clean_string($_POST['email'] ?? '', 150);
$username = clean_string($_POST['username'] ?? '', 50);
$password = $_POST['password'] ?? '';

$newLibNome = '';
$newLibCodice = '';
$newLibIndirizzo = '';

function redirect_register_error(string $message): void
{
    $params = [
        'errore' => $message,
        'ruolo' => $_POST['ruolo'] ?? 'utente',
        'libreria_id' => $_POST['libreria_id'] ?? '',
        'email' => $_POST['email'] ?? '',
        'username' => $_POST['username'] ?? '',
        'new_lib_nome' => $_POST['new_lib_nome'] ?? '',
        'new_lib_codice' => $_POST['new_lib_codice'] ?? '',
        'new_lib_indirizzo' => $_POST['new_lib_indirizzo'] ?? ''
    ];

    header('Location: register.php?' . http_build_query($params));
    exit;
}

function secure_upload_profile_image(array $file, string $uploadDir): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Caricamento immagine non valido.'];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['success' => false, 'message' => 'Impossibile creare la cartella delle foto profilo.'];
    }

    $allowedMime = ['image/jpeg' => 'jpg', 'image/png'  => 'png', 'image/webp' => 'webp', 'image/gif'  => 'gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) finfo_close($finfo);

    if (!$mime || !isset($allowedMime[$mime])) return ['success' => false, 'message' => 'Formato foto profilo non supportato.'];
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) return ['success' => false, 'message' => 'La foto profilo supera 5MB.'];

    $ext = $allowedMime[$mime];
    $fileName = 'profile_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Errore durante il salvataggio della foto profilo.'];
    }

    return ['success' => true, 'file_name' => $fileName];
}

// 1. VALIDAZIONE DI BASE
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) redirect_register_error('Email non valida.');
if (mb_strlen($password) < 6) redirect_register_error('La password deve avere almeno 6 caratteri.');

// 2. CONTROLLO: È UN UTENTE GIÀ ESISTENTE?
$isExistingUser = false;
$existingUserId = 0;
$existingUser = null;

$stmtEmail = $conn->prepare("SELECT id, password, username FROM utenti WHERE email = ? LIMIT 1");
if ($stmtEmail) {
    $stmtEmail->bind_param('s', $email);
    $stmtEmail->execute();
    $resEmail = $stmtEmail->get_result();
    if ($resEmail->num_rows > 0) {
        $existingUser = $resEmail->fetch_assoc();
        $existingUserId = (int)$existingUser['id'];
    }
    $stmtEmail->close();
}

if ($existingUserId) {
    // Se esiste, la password deve essere corretta!
    if (!password_verify($password, $existingUser['password'])) {
        redirect_register_error('Questa email è già registrata. Se sei tu, la password inserita è errata.');
    }
    $isExistingUser = true;
} else {
    // Se è nuovo, lo username deve essere valido
    if (!validate_username($username)) redirect_register_error('Username non valido. Usa 3-50 caratteri: lettere, numeri, punto, trattino o underscore.');
}

// 3. LOGICA FOTO PROFILO (Solo per nuovi utenti)
$fotoProfilo = null;
if (!$isExistingUser && isset($_FILES['foto_profilo']) && !empty($_FILES['foto_profilo']['name'])) {
    $upload = secure_upload_profile_image($_FILES['foto_profilo'], __DIR__ . '/uploads/profili');
    if (!$upload['success']) redirect_register_error($upload['message']);
    $fotoProfilo = $upload['file_name'];
}

// 4. LOGICA LIBRERIA
if ($ruoloScelto === 'utente') {
    $libreriaId = isset($_POST['libreria_id']) ? (int)$_POST['libreria_id'] : 0;
    if ($libreriaId <= 0) redirect_register_error('Seleziona una libreria valida.');
} else {
    // Il gestore crea una nuova libreria
    $newLibNome = clean_string($_POST['new_lib_nome'] ?? '', 255);
    $newLibCodice = clean_string($_POST['new_lib_codice'] ?? '', 100);
    $newLibIndirizzo = clean_string($_POST['new_lib_indirizzo'] ?? '', 255);

    if ($newLibNome === '' || $newLibCodice === '' || $newLibIndirizzo === '') {
        redirect_register_error('Compila nome, codice e indirizzo della nuova libreria.');
    }

    $checkLibStmt = $conn->prepare("SELECT id FROM libreria WHERE codice = ? LIMIT 1");
    $checkLibStmt->bind_param('s', $newLibCodice);
    $checkLibStmt->execute();
    if ($checkLibStmt->get_result()->num_rows > 0) {
        $checkLibStmt->close();
        redirect_register_error('Codice libreria già esistente.');
    }
    $checkLibStmt->close();

    $lat = isset($_POST['latitudine']) && is_numeric($_POST['latitudine']) ? (float)$_POST['latitudine'] : null;
    $lon = isset($_POST['longitudine']) && is_numeric($_POST['longitudine']) ? (float)$_POST['longitudine'] : null;
    
    if ($lat === null || $lon === null || $lat == 0) {
        $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($newLibIndirizzo) . "&format=json&limit=1";
        $options = ["http" => ["header" => "User-Agent: GestioneLibreria/1.0\r\n"]];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                $lat = $data[0]['lat'];
                $lon = $data[0]['lon'];
            }
        }
    }

    $stmtLib = $conn->prepare("INSERT INTO libreria (nome, codice, creator_id, indirizzo, latitudine, longitudine) VALUES (?, ?, NULL, ?, ?, ?)");
    if (!$stmtLib) redirect_register_error('Errore interno DB: ' . $conn->error);
    $stmtLib->bind_param('sssss', $newLibNome, $newLibCodice, $newLibIndirizzo, $lat, $lon);
    if (!$stmtLib->execute()) redirect_register_error('Errore durante la creazione della libreria: ' . $stmtLib->error);

    $libreriaId = (int)$stmtLib->insert_id;
    $stmtLib->close();
}

// Controllo che la libreria esista (sicurezza extra)
$libStmt = $conn->prepare("SELECT id, creator_id FROM libreria WHERE id = ? LIMIT 1");
$libStmt->bind_param('i', $libreriaId);
$libStmt->execute();
$libRes = $libStmt->get_result();
if (!$libRes || $libRes->num_rows !== 1) {
    $libStmt->close();
    redirect_register_error('Libreria non valida.');
}
$libData = $libRes->fetch_assoc();
$libStmt->close();

$ruoloFinale = ($ruoloScelto === 'gestore') ? 'admin' : 'utente';

// 5. SALVATAGGIO FINALE: Bivio Utente Esistente vs Nuovo Utente
if ($isExistingUser) {
    
    // Controlliamo se è già membro di QUESTA specifica libreria
    $stmtCheck = $conn->prepare("SELECT * FROM utenti_librerie WHERE id_utente = ? AND id_libreria = ?");
    $stmtCheck->bind_param('ii', $existingUserId, $libreriaId);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        $stmtCheck->close();
        redirect_register_error('Sei già registrato in questa sede! Effettua il login.');
    }
    $stmtCheck->close();

    // Inseriamo il legame nella tabella multi-libreria
    $stmtMulti = $conn->prepare("INSERT INTO utenti_librerie (id_utente, id_libreria, ruolo) VALUES (?, ?, ?)");
    $stmtMulti->bind_param('iis', $existingUserId, $libreriaId, $ruoloFinale);
    $stmtMulti->execute();
    $stmtMulti->close();

    // Aggiorniamo la sua "Libreria Attuale" per fargli trovare subito la nuova
    $conn->query("UPDATE utenti SET libreria_id = $libreriaId WHERE id = $existingUserId");

    // Se sta creando una libreria, lo nominiamo creatore
    if ($ruoloScelto === 'gestore') {
        $conn->query("UPDATE libreria SET creator_id = $existingUserId WHERE id = $libreriaId");
    }

    log_attivita($conn, 'registrazione', 'utente', $existingUserId, "Utente aggiunto alla libreria ID $libreriaId");

    header("Location: login.php?successo=" . urlencode("Ti sei unito alla nuova libreria con successo! Ora puoi accedere."));
    exit;

} else {

    // FLUSSO NUOVO UTENTE
    $stmt = $conn->prepare("SELECT id FROM utenti WHERE username = ? AND libreria_id = ? LIMIT 1");
    $stmt->bind_param('si', $username, $libreriaId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        redirect_register_error('Username già in uso. Scegline un altro.');
    }
    $stmt->close();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $codice = generate_verification_code();
    $scadenza = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stato = 'attivo';

    // Inserisce in `utenti`
    $stmt = $conn->prepare("
        INSERT INTO utenti (email, username, password, ruolo, stato, libreria_id, foto, email_verificata, codice_verifica, codice_scadenza)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");
    $stmt->bind_param('sssssisss', $email, $username, $passwordHash, $ruoloFinale, $stato, $libreriaId, $fotoProfilo, $codice, $scadenza);
    if (!$stmt->execute()) redirect_register_error('Errore DB: ' . $stmt->error);

    $userId = (int)$stmt->insert_id;
    $stmt->close();

    // MAGIA MULTI-TENANT: Inserisce in `utenti_librerie`
    $stmtMulti = $conn->prepare("INSERT INTO utenti_librerie (id_utente, id_libreria, ruolo) VALUES (?, ?, ?)");
    if ($stmtMulti) {
        $stmtMulti->bind_param('iis', $userId, $libreriaId, $ruoloFinale);
        $stmtMulti->execute();
        $stmtMulti->close();
    }

    // Se gestore, salva come creator
    if ($ruoloScelto === 'gestore') {
        $stmtUpdate = $conn->prepare('UPDATE libreria SET creator_id = ? WHERE id = ?');
        $stmtUpdate->bind_param('ii', $userId, $libreriaId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    log_attivita($conn, 'registrazione', 'utente', $userId, 'Nuovo account: ' . $username);

    $sent = send_verification_email($email, $codice);
    $message = 'Registrazione completata. Controlla la tua email e inserisci il codice: ' . $codice;
    header('Location: verify.php?id=' . $userId . '&successo=' . urlencode($message));
    exit;
}