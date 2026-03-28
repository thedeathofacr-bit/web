<?php
session_start();
include "connessione.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificativo = trim($_POST['identificativo'] ?? '');
    $password = $_POST['password'] ?? '';
    $codice_input = trim($_POST['codice_libreria'] ?? '');

    // 1. Controllo campi vuoti
    if (empty($identificativo) || empty($password) || $codice_input === '') {
        header("Location: login.php?errore=" . urlencode("Tutti i campi sono obbligatori, incluso il codice libreria."));
        exit;
    }

    // 2. Ricerca Utente
    $stmt = $conn->prepare("SELECT * FROM utenti WHERE email = ? OR username = ? LIMIT 1");
    $stmt->bind_param("ss", $identificativo, $identificativo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // 3. Controllo Password
        if (password_verify($password, $user['password'])) {
            
            // 4. Verifica Stato Account
            if ($user['stato'] === 'sospeso') {
                header("Location: login.php?errore=" . urlencode("Il tuo account è stato sospeso. Contatta l'amministratore."));
                exit;
            }

            // --- 5. RICERCA LIBRERIA (Estraggo anche nome e codice per il Cookie) ---
            $codice_as_int = (int)$codice_input; 
            
            $check_lib = $conn->prepare("SELECT id, nome, codice FROM libreria WHERE id = ? OR codice = ? LIMIT 1");
            $check_lib->bind_param("is", $codice_as_int, $codice_input);
            $check_lib->execute();
            $res_lib = $check_lib->get_result();
            
            if ($res_lib->num_rows === 0) {
                header("Location: login.php?errore=" . urlencode("Errore: La libreria '$codice_input' non esiste."));
                exit;
            }

            $row_lib = $res_lib->fetch_assoc();
            $libreria_id_reale = (int)$row_lib['id'];
            $libreria_nome = $row_lib['nome'];
            $libreria_codice = $row_lib['codice'];

            // 6. Verifica Assegnazione Libreria (Solo per utenti normali)
            if ($user['ruolo'] !== 'admin' && $user['ruolo'] !== 'gestore') {
                if ((int)$user['libreria_id'] !== $libreria_id_reale) {
                    header("Location: login.php?errore=" . urlencode("Accesso negato: Il codice inserito non corrisponde alla tua libreria."));
                    exit;
                }
            }

            // 7. Tutto OK - Avvio Sessione
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['ruolo'] = $user['ruolo'];
            $_SESSION['libreria_id'] = $libreria_id_reale; 
            $_SESSION['nome_libreria'] = $libreria_nome;

            // 8. Aggiorna i Cookie ad ogni login andato a buon fine
            setcookie('ultima_sede_nome', $libreria_nome, time() + (86400 * 30), "/");
            setcookie('ultima_sede_codice', $libreria_codice, time() + (86400 * 30), "/");

            // 9. Aggiorniamo ultimo login E salviamo l'ultima libreria scelta nel profilo
            $conn->query("UPDATE utenti SET ultimo_login = NOW(), libreria_id = $libreria_id_reale WHERE id = " . $user['id']);

            header("Location: index.php");
            exit;
        } else {
            // Password Errata
            header("Location: login.php?errore=" . urlencode("La password inserita non è corretta."));
            exit;
        }
    } else {
        // Utente Non Trovato
        header("Location: login.php?errore=" . urlencode("Nessun utente trovato con questa email/username."));
        exit;
    }
} else {
    // Accesso diretto alla pagina bloccato
    header("Location: login.php");
    exit;
}