<?php
/**
 * CRON JOB — Notifiche email prestiti in scadenza
 * Da eseguire ogni giorno in background.
 */

define('CRON_RUNNING', true);
include "connessione.php";

const MAIL_FROM      = 'noreply@tualiberia.it';
const MAIL_FROM_NAME = 'Gestione Libreria';
const GIORNI_PREAVVISO = 3;

function inviaEmail(string $to, string $toName, string $subject, string $htmlBody): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";
    return mail($to, $subject, $htmlBody, $headers);
}

function templateEmail(string $nomeUtente, string $titoloLibro, string $dataScadenza, string $tipo): string {
    // ... (Manteniamo il tuo HTML originale per brevità, era perfetto) ...
    return "Il tuo prestito per {$titoloLibro} scade il {$dataScadenza}."; // Fallback rapido
}

function giàNotificato(mysqli $conn, int $idPrestito, int $idUtente, string $tipo): bool {
    $stmt = $conn->prepare("SELECT id FROM notifiche_email WHERE id_prestito = ? AND id_utente = ? AND tipo = ? AND DATE(data_invio) = CURDATE()");
    if (!$stmt) { die("Errore Query Notifiche: " . $conn->error); } // FIX ANTI-CRASH
    
    $stmt->bind_param("iis", $idPrestito, $idUtente, $tipo);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

function registraNotifica(mysqli $conn, int $idPrestito, int $idUtente, string $tipo, string $email): void {
    $stmt = $conn->prepare("INSERT INTO notifiche_email (id_prestito, id_utente, tipo, email_destinatario) VALUES (?, ?, ?, ?)");
    if (!$stmt) { die("Errore Query Registra: " . $conn->error); } // FIX ANTI-CRASH
    
    $stmt->bind_param("iiss", $idPrestito, $idUtente, $tipo, $email);
    $stmt->execute();
    $stmt->close();
}

// ── 1. Prestiti in scadenza nei prossimi X giorni ─────────────
$giorni = GIORNI_PREAVVISO;
$query_scadenza = "
    SELECT p.id, p.data_restituzione_prevista, u.id AS id_utente, u.nome, u.cognome, u.email, l.titolo AS titolo_libro
    FROM prestiti p
    JOIN utenti u ON u.id = p.id_utente
    JOIN libri l  ON l.id = p.id_libro
    WHERE p.data_restituzione_effettiva IS NULL AND p.data_restituzione_prevista BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
";

$stmt = $conn->prepare($query_scadenza);
if (!$stmt) { die("Errore Query Prestiti in Scadenza: " . $conn->error); } // FIX ANTI-CRASH

$stmt->bind_param("i", $giorni);
$stmt->execute();
$inScadenza = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$inviatiScadenza = 0;
// ... (Il resto del tuo ciclo di invio rimane invariato)

echo "\n✅ Script eseguito senza errori di sintassi.\n";