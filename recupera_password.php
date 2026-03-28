<?php
include "connessione.php";

if (is_logged()) {
    header("Location: index.php");
    exit;
}

$errore = '';
$successo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Inserisci un indirizzo email valido.";
    } else {
        $stmt = $conn->prepare("SELECT id, username FROM utenti WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows > 0) {
                $user = $res->fetch_assoc();
                
                $token = bin2hex(random_bytes(32));
                $scadenza = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $update = $conn->prepare("UPDATE utenti SET codice_verifica = ?, codice_scadenza = ? WHERE email = ?");
                $update->bind_param('sss', $token, $scadenza, $email);
                $update->execute();
                $update->close();
                
                $protocollo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $cartella = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $reset_link = $protocollo . $_SERVER['HTTP_HOST'] . $cartella . "/reset_password.php?token=" . $token;
                
                $to = $email;
                $subject = "Recupero Password - Gestione Libreria";
                
                // ── MAGIA: CREIAMO L'EMAIL IN HTML ──
                $message = "
                <html>
                <body style='font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 20px; margin: 0;'>
                    <div style='max-w-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                        
                        <div style='background-color: #0891b2; padding: 30px 20px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Gestione Libreria</h1>
                        </div>
                        
                        <div style='padding: 30px 20px; color: #334155;'>
                            <h2 style='color: #0f172a; margin-top: 0;'>Ciao {$user['username']},</h2>
                            <p style='font-size: 16px; line-height: 1.5; color: #475569;'>
                                Abbiamo ricevuto una richiesta per reimpostare la password del tuo account. 
                                Se sei stato tu, clicca sul bottone qui sotto per sceglierne una nuova:
                            </p>
                            
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{$reset_link}' style='background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 16px;'>
                                    Reimposta la tua Password
                                </a>
                            </div>
                            
                            <p style='font-size: 14px; color: #64748b; background-color: #f8fafc; padding: 15px; border-radius: 8px;'>
                                <strong>Attenzione:</strong> Questo link è valido solo per 15 minuti. Se non hai richiesto tu il cambio password, ignora tranquillamente questa email.
                            </p>
                        </div>
                        
                        <div style='background-color: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;'>
                            © " . date('Y') . " Gestione Libreria. Tutti i diritti riservati.<br>
                            Questo è un messaggio automatico, non rispondere a questa email.
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // ── MAGIA 2: DICIAMO A PHP CHE È UN'EMAIL HTML ──
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=utf-8\r\n";
                // Personalizziamo anche il MITTENTE (es. apparirà "La Tua Libreria" invece di "noreply@...")
                $headers .= "From: La Tua Libreria <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                
                // Inviamo!
                mail($to, $subject, $message, $headers);
            }
            $stmt->close();
        }
        $successo = "Se l'indirizzo email è registrato nei nostri sistemi, riceverai a breve un link per resettare la password. (Controlla anche la cartella Spam)";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupera Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script>if (localStorage.getItem('darkMode') === 'enabled') { document.documentElement.classList.add('dark'); }</script>
</head>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen flex items-center justify-center p-4 transition-colors duration-300">

    <div class="max-w-md w-full bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-gray-100 dark:border-slate-800 p-8 sm:p-10 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-cyan-500 to-indigo-500"></div>

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-cyan-100 dark:bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-inner">
                🔐
            </div>
            <h2 class="text-3xl font-black text-gray-900 dark:text-white">Password persa?</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm leading-relaxed">
                Nessun problema! Inserisci l'indirizzo email associato al tuo account e ti invieremo un link per crearne una nuova.
            </p>
        </div>

        <?php if ($errore): ?>
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 text-sm dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 shadow-sm text-center">
                <?php echo htmlspecialchars($errore); ?>
            </div>
        <?php endif; ?>

        <?php if ($successo): ?>
            <div class="mb-6 rounded-xl bg-green-50 border border-green-200 p-5 text-green-800 text-sm dark:bg-green-900/20 dark:border-green-800 dark:text-green-300 shadow-sm text-center font-medium leading-relaxed">
                <?php echo htmlspecialchars($successo); ?>
            </div>
            <a href="login.php" class="block w-full text-center mt-4 bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-gray-800 dark:text-white px-6 py-3.5 rounded-xl font-bold transition">
                Torna al Login
            </a>
        <?php else: ?>
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Indirizzo Email</label>
                    <input type="email" name="email" required class="w-full px-5 py-3.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 transition-all shadow-sm" placeholder="mario@email.com">
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white px-6 py-3.5 rounded-xl font-bold shadow-lg shadow-cyan-500/30 hover:-translate-y-0.5 transition-all">
                    Invia link di recupero
                </button>
            </form>

            <p class="mt-6 text-sm text-center font-medium">
                <a href="login.php" class="text-gray-500 hover:text-cyan-600 dark:text-gray-400 dark:hover:text-cyan-400 transition flex items-center justify-center gap-1">
                    ← Torna al Login
                </a>
            </p>
        <?php endif; ?>
    </div>

</body>
</html>