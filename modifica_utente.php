<?php
session_start();

if (!isset($_SESSION["admin"])) {
    header("Location: login_gestioneutenti.php");
    exit;
}

include "connessione.php";

$adminLoggatoId = (int) $_SESSION["admin"];
$id = isset($_GET["id"]) ? $_GET["id"] : "";
$error = isset($_GET["error"]) ? $_GET["error"] : "";

if (!is_numeric($id) || (int)$id <= 0) {
    header("Location: gestione_utenti.php?error=id_non_valido");
    exit;
}

$id = (int) $id;

$stmt = $conn->prepare("SELECT id, username, ruolo, stato, created_at FROM utenti WHERE id = ?");
if (!$stmt) {
    header("Location: gestione_utenti.php?error=errore_modifica");
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header("Location: gestione_utenti.php?error=utente_non_trovato");
    exit;
}

$utente = $result->fetch_assoc();
$staModificandoSeStesso = ((int)$utente["id"] === $adminLoggatoId);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica utente</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <script>
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>

<body class="bg-gray-100 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto px-6 py-8">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Modifica utente</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    Aggiorna username, ruolo e password dell'account selezionato.
                </p>
            </div>

            <a href="gestione_utenti.php"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow transition">
                ← Torna alla gestione utenti
            </a>
        </div>

        <?php if ($error === "campi_vuoti"): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                Compila tutti i campi obbligatori.
            </div>
        <?php elseif ($error === "username_esistente"): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                Esiste già un altro utente con questo username.
            </div>
        <?php elseif ($error === "errore_modifica"): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                Si è verificato un errore durante la modifica dell'utente.
            </div>
        <?php endif; ?>

        <?php if ($staModificandoSeStesso): ?>
            <div class="mb-6 rounded-xl border border-cyan-200 bg-cyan-50 text-cyan-800 px-5 py-4 dark:bg-cyan-900/30 dark:border-cyan-700 dark:text-cyan-300">
                Stai modificando il tuo account admin.
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-6">Dati utente</h2>

                <form action="salva_modifica_utente.php" method="POST" class="space-y-5">
                    <input type="hidden" name="id" value="<?php echo $utente["id"]; ?>">

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Username
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?php echo htmlspecialchars($utente["username"]); ?>"
                            required
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-3"
                        >
                    </div>

                    <div>
                        <label for="ruolo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Ruolo
                        </label>
                        <select
                            id="ruolo"
                            name="ruolo"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-3"
                        >
                            <option value="utente" <?php echo ($utente["ruolo"] === "utente") ? "selected" : ""; ?>>Utente</option>
                            <option value="admin" <?php echo ($utente["ruolo"] === "admin") ? "selected" : ""; ?>>Admin</option>
                        </select>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nuova password
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Lascia vuoto per non cambiarla"
                            class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-3"
                        >
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            La password verrà aggiornata solo se inserisci un nuovo valore.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="gestione_utenti.php"
                           class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-5 py-2.5 rounded-lg transition">
                            Annulla
                        </a>

                        <button
                            type="submit"
                            class="bg-cyan-600 hover:bg-cyan-700 text-white px-5 py-2.5 rounded-lg shadow transition"
                        >
                            Salva modifiche
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-5">Informazioni</h2>

                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">ID utente</p>
                        <p class="text-base font-semibold text-gray-800 dark:text-white">#<?php echo $utente["id"]; ?></p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Username attuale</p>
                        <p class="text-base font-semibold text-gray-800 dark:text-white break-words">
                            <?php echo htmlspecialchars($utente["username"]); ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ruolo attuale</p>
                        <div class="mt-1">
                            <?php if ($utente["ruolo"] === "admin"): ?>
                                <span class="bg-cyan-600 text-white text-xs px-3 py-1 rounded-full">ADMIN</span>
                            <?php else: ?>
                                <span class="bg-gray-300 dark:bg-gray-600 text-xs px-3 py-1 rounded-full text-gray-800 dark:text-white">UTENTE</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Stato attuale</p>
                        <div class="mt-1">
                            <?php if ($utente["stato"] === "attivo"): ?>
                                <span class="bg-green-600 text-white text-xs px-3 py-1 rounded-full">ATTIVO</span>
                            <?php else: ?>
                                <span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">DISATTIVATO</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Data creazione</p>
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            <?php echo date("d/m/Y H:i", strtotime($utente["created_at"])); ?>
                        </p>
                    </div>

                    <?php if ($staModificandoSeStesso): ?>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300">
                            Attenzione: alcune modifiche al tuo account admin possono influire sull'accesso alla gestione utenti.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
