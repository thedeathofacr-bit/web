<?php
include "connessione.php";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea una libreria</title>
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
    <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-2">Crea una nuova libreria</h1>
    <p class="text-gray-500 dark:text-gray-400 mb-6">
        Per creare una nuova libreria e diventarne amministratore, registrati come <strong>Gestore</strong>.
    </p>

    <div class="rounded-2xl bg-blue-100 border border-blue-200 text-blue-800 px-4 py-3 text-sm dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300 mb-6">
        La libreria non si crea più da questa pagina da sola, per evitare librerie senza admin associato.
    </div>

    <div class="flex gap-2">
        <a href="register.php"
           class="w-full inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-2xl font-semibold transition">
            Vai alla registrazione
        </a>

        <a href="login.php"
           class="w-full inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-2xl font-semibold transition">
            Vai al login
        </a>
    </div>
</div>

</body>
</html>