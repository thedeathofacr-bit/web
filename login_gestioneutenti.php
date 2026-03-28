<?php
include "connessione.php";

if (is_admin()) {
    header("Location: gestione_utenti.php");
    exit;
}

$errore = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf_or_die($conn);

    $username = clean_string($_POST["username"] ?? "", 50);
    $password = (string)($_POST["password"] ?? "");

    if (!validate_username($username) || $password === "") {
        $errore = "Credenziali non valide.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, ruolo, stato FROM utenti WHERE username = ? LIMIT 1");

        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $utente = $result->fetch_assoc();

                if ($utente["stato"] !== "attivo") {
                    $errore = "Account disattivato.";
                    log_attivita($conn, "login_fallito", "utente", (int)$utente["id"], "Tentativo login su account disattivato: " . $username);
                } elseif (!password_verify($password, $utente["password"])) {
                    $errore = "Credenziali non valide.";
                    log_attivita($conn, "login_fallito", "utente", (int)$utente["id"], "Password errata per utente: " . $username);
                } else {
                    login_success($conn, (int)$utente["id"], $utente["username"], $utente["ruolo"]);

                    if ($utente["ruolo"] === "admin") {
                        header("Location: gestione_utenti.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                }
            } else {
                $errore = "Credenziali non valide.";
                log_attivita($conn, "login_fallito", "utente", null, "Tentativo login con username inesistente: " . $username);
            }
        } else {
            $errore = "Errore interno.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login gestione utenti</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8">
        <div class="mb-6 text-center">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Login</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Accedi al pannello di gestione</p>
        </div>

        <?php if ($errore !== ""): ?>
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                <?php echo htmlspecialchars($errore); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <?php echo csrf_input(); ?>

            <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                <input
                    type="text"
                    name="username"
                    required
                    maxlength="50"
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-white"
                >
            </div>

            <div>
                <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                <input
                    type="password"
                    name="password"
                    required
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-white"
                >
            </div>

            <button
                type="submit"
                class="w-full bg-cyan-600 hover:bg-cyan-700 text-white font-medium py-3 rounded-xl shadow transition"
            >
                Accedi
            </button>
        </form>
    </div>

</body>
</html>