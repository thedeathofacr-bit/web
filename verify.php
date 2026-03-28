<?php
include "connessione.php";

if (is_logged()) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errore = $_GET['errore'] ?? '';
$successo = $_GET['successo'] ?? '';

if ($id <= 0) {
    header("Location: register.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica email</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-slate-950 min-h-screen flex items-center justify-center px-4">

<div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-xl border border-gray-200 dark:border-slate-800 p-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-2">Verifica email</h1>
    <p class="text-gray-500 dark:text-gray-400 mb-6">Inserisci il codice ricevuto via email.</p>

    <?php if ($errore): ?>
        <div class="mb-4 rounded-2xl bg-red-100 border border-red-200 text-red-700 px-4 py-3 text-sm dark:bg-red-900/30 dark:border-red-800 dark:text-red-300">
            <?php echo htmlspecialchars($errore); ?>
        </div>
    <?php endif; ?>

    <?php if ($successo): ?>
        <div class="mb-4 rounded-2xl bg-green-100 border border-green-200 text-green-700 px-4 py-3 text-sm dark:bg-green-900/30 dark:border-green-800 dark:text-green-300">
            <?php echo htmlspecialchars($successo); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="verify_process.php" class="space-y-4">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div>
            <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Codice di verifica</label>
            <input type="text" name="code" required maxlength="6"
                class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <button type="submit"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-2xl font-semibold transition">
            Verifica account
        </button>
    </form>

    <p class="mt-6 text-sm text-gray-600 dark:text-gray-400">
        Hai già verificato?
        <a href="login.php" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Vai al login</a>
    </p>
</div>

</body>
</html>