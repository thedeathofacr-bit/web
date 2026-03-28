<?php
include "connessione.php";

if (is_logged()) {
    header("Location: index.php");
    exit;
}

$errore = '';
$successo = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Accesso negato. Token di sicurezza mancante.");
}

// Verifichiamo se il token è valido e non è scaduto (scadenza > NOW())
$stmt = $conn->prepare("SELECT id, email FROM utenti WHERE codice_verifica = ? AND codice_scadenza > NOW() LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
} else {
    $user = null;
}

if (!$user) {
    $errore = "Il link di recupero non è valido o è scaduto (Validità massima: 15 minuti). Per favore, effettua una nuova richiesta.";
}

// Se il form viene inviato con le nuove password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (mb_strlen($password) < 6) {
        $errore = "La password deve contenere almeno 6 caratteri.";
    } elseif ($password !== $confirm) {
        $errore = "Le password non coincidono. Riprova.";
    } else {
        // Hash della nuova password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Aggiorniamo la password e ANNULLIAMO il token usato per sicurezza
        $update = $conn->prepare("UPDATE utenti SET password = ?, codice_verifica = NULL, codice_scadenza = NULL WHERE id = ?");
        $update->bind_param('si', $hash, $user['id']);
        $update->execute();
        $update->close();
        
        $successo = "Fantastico! La tua password è stata aggiornata con successo.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Nuova Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script>if (localStorage.getItem('darkMode') === 'enabled') { document.documentElement.classList.add('dark'); }</script>
</head>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen flex items-center justify-center p-4 transition-colors duration-300">

    <div class="max-w-md w-full bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-gray-100 dark:border-slate-800 p-8 sm:p-10 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-emerald-400 to-teal-500"></div>

        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-emerald-100 dark:bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl shadow-inner">
                ✨
            </div>
            <h2 class="text-3xl font-black text-gray-900 dark:text-white">Nuova Password</h2>
            <?php if ($user && !$successo): ?>
                <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm leading-relaxed">
                    Stai reimpostando la password per <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($errore): ?>
            <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-red-800 text-sm dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 shadow-sm text-center">
                <?php echo htmlspecialchars($errore); ?>
            </div>
            <?php if (!$user): ?>
                <a href="recupera_password.php" class="block w-full text-center bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-3.5 rounded-xl font-bold transition">
                    Richiedi nuovo link
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($successo): ?>
            <div class="mb-6 rounded-xl bg-green-50 border border-green-200 p-5 text-green-800 text-sm dark:bg-green-900/20 dark:border-green-800 dark:text-green-300 shadow-sm text-center font-medium leading-relaxed">
                <?php echo htmlspecialchars($successo); ?>
            </div>
            <a href="login.php" class="block w-full text-center mt-4 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white px-6 py-3.5 rounded-xl font-bold transition shadow-lg shadow-emerald-500/30">
                Vai al Login
            </a>
        <?php elseif ($user): ?>
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Nuova Password</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-5 py-3.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition-all shadow-sm" placeholder="••••••••">
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Conferma Nuova Password</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full px-5 py-3.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition-all shadow-sm" placeholder="••••••••">
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white px-6 py-3.5 rounded-xl font-bold shadow-lg shadow-emerald-500/30 hover:-translate-y-0.5 transition-all">
                    Salva Password
                </button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>