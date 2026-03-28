<?php
include "connessione.php";

if (is_logged()) {
    header("Location: index.php");
    exit;
}

$errore = $_GET['errore'] ?? '';
$successo = $_GET['successo'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gestione Libreria</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>

    <script>
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-white dark:bg-slate-950 text-gray-800 dark:text-gray-200 transition-colors duration-300 font-sans antialiased selection:bg-cyan-500 selection:text-white">

<div class="min-h-screen flex relative">
    
    <button id="themeToggle"
        class="absolute top-4 right-4 z-50 px-4 py-2 rounded-xl bg-gray-200 dark:bg-slate-800 text-gray-800 dark:text-white shadow-md hover:scale-105 transition font-semibold">
        🌙 Dark
    </button>

    <div class="hidden lg:flex lg:w-5/12 bg-gradient-to-br from-cyan-600 via-blue-700 to-indigo-900 relative items-center justify-center p-12 overflow-hidden">
        <div class="absolute top-0 left-0 w-96 h-96 bg-cyan-400 rounded-full mix-blend-multiply filter blur-[128px] opacity-50 animate-pulse"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-50 animate-pulse" style="animation-delay: 2s;"></div>
        
        <div class="relative z-10 text-white max-w-lg">
            <div class="bg-white/10 p-4 rounded-2xl backdrop-blur-md inline-block mb-8 border border-white/20 shadow-xl">
                <img src="assets/logo.png" class="w-16 h-16 object-contain drop-shadow-md" alt="Logo">
            </div>
            <h1 class="text-5xl font-black mb-6 leading-tight tracking-tight">Bentornato <br> <span class="text-cyan-300">a casa.</span></h1>
            <p class="text-lg text-blue-100 mb-10 leading-relaxed">
                Accedi al tuo pannello per gestire le tue letture, scoprire nuovi titoli e controllare i tuoi prestiti attivi.
            </p>
        </div>
    </div>

    <div class="w-full lg:w-7/12 flex items-center justify-center p-6 sm:p-12 xl:p-20 relative">
        <div class="w-full max-w-md">
            
            <div class="lg:hidden text-center mb-10">
                <img src="assets/logo.png" class="w-20 h-20 mx-auto mb-4 drop-shadow-md" alt="Logo">
                <h2 class="text-3xl font-black text-gray-900 dark:text-white">Bentornato!</h2>
            </div>

            <div class="hidden lg:block mb-10">
                <h2 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">Accedi</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2 text-lg">Inserisci le tue credenziali per continuare.</p>
            </div>

            <?php if(isset($_COOKIE['ultima_sede_nome'])): ?>
                <div class="flex justify-center mb-6">
                    <div class="inline-flex items-center gap-2 bg-cyan-50 dark:bg-cyan-900/20 border border-cyan-200 dark:border-cyan-800 text-cyan-700 dark:text-cyan-400 px-4 py-2 rounded-full text-xs font-bold uppercase tracking-widest shadow-sm">
                        <span class="text-base">📍</span> Ultima sede: <?= htmlspecialchars($_COOKIE['ultima_sede_nome']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($errore): ?>
                <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 flex gap-3 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 shadow-sm animate-pulse">
                    <span class="text-xl">⚠️</span><div class="text-sm font-medium"><?php echo htmlspecialchars($errore); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($successo): ?>
                <div class="mb-6 rounded-2xl bg-green-50 border border-green-200 p-4 flex gap-3 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300 shadow-sm">
                    <span class="text-xl">✅</span><div class="text-sm font-medium"><?php echo htmlspecialchars($successo); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login_process.php" class="space-y-6">
                <?php if (function_exists('csrf_input')) echo csrf_input(); ?>
                <div class="space-y-5">
                    
                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Email o Username</label>
                        <input type="text" name="identificativo" required class="w-full px-5 py-3.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 transition-all shadow-sm" placeholder="mario@email.com">
                    </div>

                    <div class="relative">
                        <div class="flex justify-between mb-2">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Password</label>
                            <a href="recupera_password.php" class="text-sm font-semibold text-cyan-600 dark:text-cyan-400 hover:text-cyan-700 hover:underline">Password dimenticata?</a>
                        </div>
                        <div class="relative">
                            <input type="password" id="passwordInput" name="password" required class="w-full px-5 py-3.5 pr-12 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 transition-all shadow-sm" placeholder="••••••••">
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 px-4 text-xl text-gray-500 hover:text-cyan-600 focus:outline-none transition">👁️</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Codice Libreria</label>
                        <input type="text" name="codice_libreria" value="<?= htmlspecialchars($_COOKIE['ultima_sede_codice'] ?? '') ?>" required class="w-full px-5 py-3.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 transition-all shadow-sm uppercase" placeholder="Es. CODICE1">
                        <p class="text-xs text-gray-500 mt-1">Inserisci il codice esatto della tua libreria.</p>
                    </div>

                </div>

                <button type="submit" class="w-full mt-2 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white px-6 py-4 rounded-xl font-bold text-lg shadow-lg hover:-translate-y-0.5 transition-all">
                    Accedi →
                </button>
            </form>

            <div class="mt-8 flex items-center justify-between">
                <span class="w-1/5 border-b border-gray-200 dark:border-gray-700 lg:w-1/4"></span>
                <span class="text-xs text-center text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Oppure</span>
                <span class="w-1/5 border-b border-gray-200 dark:border-gray-700 lg:w-1/4"></span>
            </div>
            
            <div class="flex justify-center mt-6">
                <a href="google_login.php" class="w-full flex items-center justify-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-white px-4 py-3.5 rounded-xl shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700 transition font-bold">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google"> 
                    Accedi con Google
                </a>
            </div>

            <p class="mt-8 text-sm text-gray-600 dark:text-gray-400 text-center font-medium">
                Non hai un account? <a href="register.php" class="text-cyan-600 dark:text-cyan-400 hover:text-cyan-700 font-bold underline transition-colors">Registrati gratis</a>
            </p>
        </div>
    </div>
</div>

<script>
// 👁️ Toggle password
document.addEventListener('DOMContentLoaded', function () {
    const passInput = document.getElementById('passwordInput');
    const toggleBtn = document.getElementById('togglePassword');

    toggleBtn.addEventListener('click', () => {
        if (passInput.type === 'password') {
            passInput.type = 'text'; 
            toggleBtn.textContent = '🙈';
        } else {
            passInput.type = 'password'; 
            toggleBtn.textContent = '👁️';
        }
    });
});

// 🌙 Toggle Dark Mode
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('themeToggle');
    const html = document.documentElement;

    function updateButton() {
        toggleBtn.textContent = html.classList.contains('dark') ? '☀️ Light' : '🌙 Dark';
    }

    toggleBtn.addEventListener('click', () => {
        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            localStorage.setItem('darkMode', 'disabled');
        } else {
            html.classList.add('dark');
            localStorage.setItem('darkMode', 'enabled');
        }
        updateButton();
    });

    updateButton();
});
</script>

</body>
</html>