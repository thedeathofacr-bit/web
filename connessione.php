<?php

/*
|--------------------------------------------------------------------------
| CONFIG DATABASE
|--------------------------------------------------------------------------
*/
$host = "localhost";
$user = "my_cristianagrillovt";
$password = "";
$database = "my_cristianagrillovt";

/*
|--------------------------------------------------------------------------
| SESSIONE SICURA
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_start();
}

/*
|--------------------------------------------------------------------------
| TIMEOUT INATTIVITÀ
|--------------------------------------------------------------------------
*/
define('SESSION_TIMEOUT_SECONDS', 1800);

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - (int)$_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT_SECONDS) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['LAST_ACTIVITY'] = time();

/*
|--------------------------------------------------------------------------
| HEADER DI SICUREZZA
|--------------------------------------------------------------------------
*/
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/*
|--------------------------------------------------------------------------
| CONNESSIONE DATABASE
|--------------------------------------------------------------------------
*/
$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    die("Connessione fallita al database.");
}

$conn->set_charset("utf8mb4");

/*
|--------------------------------------------------------------------------
| TABELLA LOG ATTIVITÀ
|--------------------------------------------------------------------------
*/
function ensureLogTable(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS log_attivita (
            id INT AUTO_INCREMENT PRIMARY KEY,
            utente VARCHAR(100) NOT NULL,
            azione VARCHAR(50) NOT NULL,
            oggetto VARCHAR(50) NOT NULL,
            oggetto_id INT DEFAULT NULL,
            descrizione TEXT,
            data_operazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

ensureLogTable($conn);
ensureSchema($conn);

/*
|--------------------------------------------------------------------------
| SCHEMA E TABELLE
|--------------------------------------------------------------------------
*/
function ensureSchema(mysqli $conn): void
{
    // Tabella delle librerie (multi-tenant)
    $conn->query("
        CREATE TABLE IF NOT EXISTS libreria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            codice VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // In alcune versioni/stili si usa il nome "librerie"; manteniamo anche quella tabella compatibile
    $conn->query("CREATE TABLE IF NOT EXISTS librerie LIKE libreria");

    // Aggiungiamo creator_id per sapere chi può modificare la libreria
    ensureColumnExists($conn, 'libreria', 'creator_id', 'INT DEFAULT NULL');

    // Associa una libreria a utenti e libri
    ensureColumnExists($conn, 'utenti', 'libreria_id', 'INT DEFAULT NULL');
    ensureColumnExists($conn, 'utenti', 'foto_profilo', 'VARCHAR(255) DEFAULT NULL');
    ensureColumnExists($conn, 'libri', 'libreria_id', 'INT DEFAULT NULL');

    // Assicuriamoci che il campo ruolo sia abbastanza lungo per contenere valori come "utente" / "gestore"
    $conn->query("ALTER TABLE utenti MODIFY COLUMN ruolo VARCHAR(50) NOT NULL DEFAULT 'utente'");

    // Forziamo l'unicità del nome utente solo all'interno della stessa libreria
    // (permette lo stesso username in librerie diverse e lo stesso email ovunque)
    ensureUniqueIndex($conn, 'utenti', 'ux_utenti_libreria_username', ['libreria_id', 'username']);

    // Rimuoviamo eventuali indici legacy su email che impediscono registrazioni con la stessa email in librerie diverse
    dropIndexIfExists($conn, 'utenti', 'ux_utenti_libreria_email');
    dropIndexIfExists($conn, 'utenti', 'email');
}

function ensureIndexExists(mysqli $conn, string $table, string $indexName): bool
{
    $res = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
    return $res && $res->num_rows > 0;
}

function dropIndexIfExists(mysqli $conn, string $table, string $indexName): void
{
    if (ensureIndexExists($conn, $table, $indexName)) {
        $conn->query("ALTER TABLE `$table` DROP INDEX `$indexName`");
    }
}

function ensureUniqueIndex(mysqli $conn, string $table, string $indexName, array $columns): void
{
    // Assicuriamoci che l'indice esista con le colonne corrette; se esiste ma con colonne diverse, lo ricreiamo.
    $res = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");

    $expected = $columns;
    $existing = [];

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $existing[] = $row['Column_name'];
        }
    }

    if ($existing === $expected) {
        return;
    }

    // Rimuoviamo l'indice obsoleto (se esiste)
    if (!empty($existing)) {
        $conn->query("ALTER TABLE `$table` DROP INDEX `$indexName`");
    }

    // Rimuoviamo eventuali indici globali legacy su email/username per evitare conflitti
    if ($indexName === 'ux_utenti_libreria_email') {
        dropIndexIfExists($conn, $table, 'email');
    }

    if ($indexName === 'ux_utenti_libreria_username') {
        dropIndexIfExists($conn, $table, 'username');
    }

    $colsSql = implode(', ', array_map(function ($c) {
        return "`$c`";
    }, $columns));
    $conn->query("CREATE UNIQUE INDEX `$indexName` ON `$table` ($colsSql)");
}

function ensureColumnExists(mysqli $conn, string $table, string $column, string $definition): void
{
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

/*
|--------------------------------------------------------------------------
| HELPER UTENTE
|--------------------------------------------------------------------------
*/
function current_username(): string
{
    return $_SESSION["username"] ?? $_SESSION["utente"] ?? "sconosciuto";
}

function current_user_id(): int
{
    return $_SESSION["user_id"] ?? $_SESSION["utente_id"] ?? 0;
}

function current_library_id(): ?int
{
    return isset($_SESSION["libreria_id"]) ? (int)$_SESSION["libreria_id"] : null;
}

function current_role(): string
{
    return $_SESSION["ruolo"] ?? "utente";
}

function is_admin(): bool
{
    return in_array(current_role(), ["admin", "gestore", "superadmin"], true);
}

function is_logged(): bool
{
    return isset($_SESSION["user_id"]);
}

/**
 * Assicura che la sessione contenga libreria_id, username, e ruolo corretti per l'utente loggato.
 * Questo permette di visualizzare sempre la libreria corretta anche dopo modifiche al database.
 */
function ensure_session_user_data(mysqli $conn): void
{
    if (!is_logged()) {
        return;
    }

    if (isset($_SESSION["libreria_id"]) && isset($_SESSION["username"]) && isset($_SESSION["ruolo"])) {
        return;
    }

    $userId = current_user_id();

    $stmt = $conn->prepare("SELECT username, ruolo, libreria_id FROM utenti WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows !== 1) {
        $stmt->close();
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!isset($_SESSION["username"]) && isset($user["username"])) {
        $_SESSION["username"] = $user["username"];
    }

    if (!isset($_SESSION["ruolo"]) && isset($user["ruolo"])) {
        $_SESSION["ruolo"] = $user["ruolo"];
    }

    if (!isset($_SESSION["libreria_id"]) && isset($user["libreria_id"])) {
        $_SESSION["libreria_id"] = (int)$user["libreria_id"];
    }
}

ensure_session_user_data($conn);

/*
|--------------------------------------------------------------------------
| LOG ATTIVITÀ
|--------------------------------------------------------------------------
*/
function log_attivita(mysqli $conn, string $azione, string $oggetto, ?int $oggettoId, string $descrizione): void
{
    $utente = current_username();

    $stmt = $conn->prepare("
        INSERT INTO log_attivita (utente, azione, oggetto, oggetto_id, descrizione)
        VALUES (?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param("sssis", $utente, $azione, $oggetto, $oggettoId, $descrizione);
        $stmt->execute();
        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| CONTROLLO ACCESSI
|--------------------------------------------------------------------------
*/
function require_admin_page(mysqli $conn, string $redirect = "index.php"): void
{
    if (!is_admin()) {
        log_attivita($conn, "accesso_negato", "pagina_admin", null, "Accesso negato admin.");
        header("Location: " . $redirect);
        exit;
    }
}

function require_user_page(mysqli $conn, string $redirect = "login.php"): void
{
    if (!is_logged()) {
        log_attivita($conn, "accesso_negato", "pagina_protetta", null, "Accesso negato utente.");
        header("Location: " . $redirect);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_die(mysqli $conn): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        empty($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        log_attivita($conn, "csrf_bloccato", "sicurezza", null, "Token CSRF non valido.");
        die("Richiesta non valida.");
    }
}

/*
|--------------------------------------------------------------------------
| VALIDAZIONE INPUT
|--------------------------------------------------------------------------
*/
function clean_string(?string $value, int $maxLength = 255): string
{
    $value = trim((string)$value);

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function validate_username($value): bool
{
    return preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $value);
}

/*
|--------------------------------------------------------------------------
| AUTENTICAZIONE
|--------------------------------------------------------------------------
*/
function login_success(mysqli $conn, int $userId, string $username, string $ruolo, ?int $libreriaId = null): void
{
    session_regenerate_id(true);

    $_SESSION["user_id"] = $userId;
    $_SESSION["utente_id"] = $userId;
    $_SESSION["username"] = $username;
    $_SESSION["utente"] = $username;
    $_SESSION["ruolo"] = $ruolo;

    if ($libreriaId !== null) {
        $_SESSION["libreria_id"] = $libreriaId;
    }

    $_SESSION["LAST_ACTIVITY"] = time();

    log_attivita($conn, "login", "utente", $userId, "Login effettuato da " . $username);
}

function logout_user(mysqli $conn): void
{
    $userId = current_user_id();
    $username = current_username();

    log_attivita($conn, "logout", "utente", $userId, "Logout effettuato");

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/*
|--------------------------------------------------------------------------
| VERIFICA EMAIL
|--------------------------------------------------------------------------
*/
function generate_verification_code(): string
{
    return (string)random_int(100000, 999999);
}

function send_verification_email(string $email, string $code): bool
{
    $subject = "Codice verifica account";
    $message = "Il tuo codice di verifica è: " . $code;
    $headers = "From: noreply@libreria.it";

    // Proviamo a inviare la mail (potrebbe non funzionare su alcuni hosting).
    $sent = mail($email, $subject, $message, $headers);

    // Log di debug: salviamo le email inviate in un file locale.
    $logPath = __DIR__ . DIRECTORY_SEPARATOR . 'email_debug.log';
    $logEntry = sprintf("[%s] To: %s | Sent: %s | Subj: %s | Msg: %s\n", date('Y-m-d H:i:s'), $email, $sent ? 'OK' : 'FAIL', $subject, str_replace("\n", " ", $message));
    @file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);

    return $sent;
}
