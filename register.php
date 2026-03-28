<?php
include "connessione.php";

if (is_logged()) {
    header("Location: index.php");
    exit;
}

// Recupera le librerie disponibili
$libraries = [];
$libRes = $conn->query("SELECT id, nome, codice FROM libreria ORDER BY nome ASC");

if ($libRes && $libRes->num_rows > 0) {
    while ($row = $libRes->fetch_assoc()) {
        $libraries[] = $row;
    }
}

$errore = $_GET['errore'] ?? '';
$successo = $_GET['successo'] ?? '';

// Manteniamo i valori se la registrazione fallisce
$oldRuolo = $_GET['ruolo'] ?? 'utente';
$oldLibreriaId = $_GET['libreria_id'] ?? '';
$oldEmail = $_GET['email'] ?? '';
$oldUsername = $_GET['username'] ?? '';
$oldNewLibNome = $_GET['new_lib_nome'] ?? '';
$oldNewLibCodice = $_GET['new_lib_codice'] ?? '';
$oldNewLibIndirizzo = $_GET['new_lib_indirizzo'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - Gestione Libreria</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script>if (localStorage.getItem('darkMode') === 'enabled') { document.documentElement.classList.add('dark'); }</script>
</head>
<body class="bg-white dark:bg-slate-950 text-gray-800 dark:text-gray-200 transition-colors duration-300 font-sans antialiased selection:bg-cyan-500 selection:text-white">

<div class="min-h-screen flex">
    
    <div class="hidden lg:flex lg:w-5/12 bg-gradient-to-br from-cyan-600 via-blue-700 to-indigo-900 relative items-center justify-center p-12 overflow-hidden">
        <div class="absolute top-0 left-0 w-96 h-96 bg-cyan-400 rounded-full mix-blend-multiply filter blur-[128px] opacity-50 animate-pulse"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-indigo-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-50 animate-pulse" style="animation-delay: 2s;"></div>
        
        <div class="relative z-10 text-white max-w-lg">
            <div class="bg-white/10 p-4 rounded-2xl backdrop-blur-md inline-block mb-8 border border-white/20 shadow-xl">
                <img src="assets/logo.png" class="w-16 h-16 object-contain drop-shadow-md" alt="Logo">
            </div>
            <h1 class="text-5xl font-black mb-6 leading-tight tracking-tight">
                Unisciti alla nostra <br> <span class="text-cyan-300">Community.</span>
            </h1>
            <p class="text-lg text-blue-100 mb-10 leading-relaxed">
                Esplora migliaia di libri, condividi le tue letture e gestisci i tuoi prestiti in un unico spazio digitale.
            </p>
            <div class="flex items-center gap-4 text-sm font-medium text-cyan-100 bg-white/5 py-3 px-5 rounded-full border border-white/10 backdrop-blur-sm w-max">
                <span class="flex h-3 w-3 relative">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-cyan-500"></span>
                </span>
                Oltre <?php echo count($libraries); ?> librerie già attive
            </div>
        </div>
    </div>

    <div class="w-full lg:w-7/12 flex items-center justify-center p-6 sm:p-12 xl:p-20 relative">
        <div class="absolute top-6 right-6 flex gap-4">
            <a href="index.php" class="text-sm font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white transition">Torna alla Home</a>
        </div>

        <div class="w-full max-w-xl">
            <div class="lg:hidden text-center mb-10">
                <img src="assets/logo.png" class="w-20 h-20 mx-auto mb-4 drop-shadow-md" alt="Logo">
                <h2 class="text-3xl font-black text-gray-900 dark:text-white">Crea Account</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2">Registrati per iniziare a leggere.</p>
            </div>

            <div class="hidden lg:block mb-10">
                <h2 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">Inizia ora.</h2>
                <p class="text-gray-500 dark:text-gray-400 mt-2 text-lg">Crea il tuo account o unisciti a una nuova sede in pochi secondi.</p>
            </div>

            <?php if ($errore): ?>
                <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 flex gap-3 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300 shadow-sm">
                    <span class="text-xl">⚠️</span><div class="text-sm font-medium"><?php echo htmlspecialchars($errore); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($successo): ?>
                <div class="mb-6 rounded-2xl bg-green-50 border border-green-200 p-4 flex gap-3 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300 shadow-sm">
                    <span class="text-xl">✅</span><div class="text-sm font-medium"><?php echo htmlspecialchars($successo); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="register_process.php" enctype="multipart/form-data" class="space-y-6">
                <?php echo csrf_input(); ?>

                <div class="space-y-3">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Come vuoi registrarti?</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer relative">
                            <input type="radio" name="ruolo" value="utente" class="peer sr-only" <?php echo ($oldRuolo !== 'gestore') ? 'checked' : ''; ?>>
                            <div class="p-5 rounded-2xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-cyan-300 dark:hover:border-cyan-700 peer-checked:border-cyan-500 peer-checked:bg-cyan-50 dark:peer-checked:border-cyan-500 dark:peer-checked:bg-cyan-900/20 transition-all text-center group shadow-sm">
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform">👤</div>
                                <div class="font-bold text-gray-900 dark:text-white">Lettore</div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-tight">Mi unisco a una libreria esistente</div>
                            </div>
                            <div class="absolute top-3 right-3 text-cyan-500 opacity-0 peer-checked:opacity-100 transition-opacity">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </div>
                        </label>

                        <label class="cursor-pointer relative">
                            <input type="radio" name="ruolo" value="gestore" class="peer sr-only" <?php echo ($oldRuolo === 'gestore') ? 'checked' : ''; ?>>
                            <div class="p-5 rounded-2xl border-2 border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-indigo-300 dark:hover:border-indigo-700 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:border-indigo-500 dark:peer-checked:bg-indigo-900/20 transition-all text-center group shadow-sm">
                                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform">🏢</div>
                                <div class="font-bold text-gray-900 dark:text-white">Gestore</div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-tight">Voglio creare la mia libreria</div>
                            </div>
                            <div class="absolute top-3 right-3 text-indigo-500 opacity-0 peer-checked:opacity-100 transition-opacity">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="existing-library-section" class="space-y-2 animate-fade-in-up">
                    <?php if (empty($libraries)): ?>
                        <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm dark:bg-red-900/20 dark:border-red-800">
                            Nessuna libreria disponibile. Crea una libreria prima di registrarti.
                        </div>
                    <?php else: ?>
                        <div>
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Scegli la Libreria</label>
                            <select id="libreria_select" name="libreria_id" class="w-full px-5 py-3.5 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white transition-all shadow-sm appearance-none">
                                <option value="">Seleziona dall'elenco...</option>
                                <?php foreach ($libraries as $lib): ?>
                                    <option value="<?php echo (int)$lib['id']; ?>" <?php echo ((string)$oldLibreriaId === (string)$lib['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lib['nome'] . ' (' . $lib['codice'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="new-library-section" class="hidden space-y-5 bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-slate-700 rounded-2xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Dettagli Nuova Libreria</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Compila questi campi per fondare la tua libreria.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold mb-1.5 text-gray-700 dark:text-gray-300 uppercase tracking-wide">Nome Struttura</label>
                            <input type="text" id="new_lib_nome" name="new_lib_nome" value="<?php echo htmlspecialchars($oldNewLibNome); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1.5 text-gray-700 dark:text-gray-300 uppercase tracking-wide">Codice Identificativo</label>
                            <input type="text" id="new_lib_codice" name="new_lib_codice" value="<?php echo htmlspecialchars($oldNewLibCodice); ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-sm" placeholder="Es: LIB001">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1.5 text-gray-700 dark:text-gray-300 uppercase tracking-wide">Indirizzo Fisico</label>
                            <div class="flex gap-2">
                                <input type="text" id="new_lib_indirizzo" name="new_lib_indirizzo" value="<?php echo htmlspecialchars($oldNewLibIndirizzo); ?>" class="flex-1 px-4 py-3 rounded-xl border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all shadow-sm" placeholder="Es: Via Roma 10, Milano">
                                <button type="button" id="btnGetLocation" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl font-bold transition flex items-center justify-center shadow-md shadow-indigo-500/30 shrink-0" title="Rileva posizione esatta">📍 Trova</button>
                            </div>
                            <p id="locationStatus" class="mt-2 text-xs text-indigo-600 dark:text-indigo-400 font-medium">Usa il GPS per apparire sulla mappa pubblica.</p>
                            <input type="hidden" id="latitudine" name="latitudine" value="">
                            <input type="hidden" id="longitudine" name="longitudine" value="">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800">
                    
                    <div class="relative">
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Email</label>
                        <div class="relative">
                            <input type="email" id="emailInput" name="email" required value="<?php echo htmlspecialchars($oldEmail); ?>" class="w-full px-5 py-3.5 pr-12 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white dark:focus:bg-slate-900 transition-all shadow-sm" placeholder="mario@email.com">
                            <span id="emailStatus" class="absolute inset-y-0 right-4 flex items-center text-xl pointer-events-none transition-all"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        
                        <div class="relative">
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Username</label>
                            <div class="relative">
                                <input type="text" id="usernameInput" name="username" required value="<?php echo htmlspecialchars($oldUsername); ?>" class="w-full px-5 py-3.5 pr-12 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white dark:focus:bg-slate-900 transition-all shadow-sm" placeholder="mario_rossi">
                                <span id="usernameStatus" class="absolute inset-y-0 right-4 flex items-center text-xl pointer-events-none transition-all"></span>
                            </div>
                        </div>

                        <div class="relative">
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Password</label>
                            <div class="relative">
                                <input type="password" id="passwordInput" name="password" required class="w-full px-5 py-3.5 pr-12 rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 text-gray-800 dark:text-white outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white dark:focus:bg-slate-900 transition-all shadow-sm" placeholder="••••••••">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 px-4 text-xl text-gray-500 hover:text-cyan-600 focus:outline-none transition">👁️</button>
                            </div>
                            
                            <div class="h-1.5 w-full bg-gray-200 dark:bg-slate-700 rounded-full mt-2 overflow-hidden shadow-inner">
                                <div id="passwordStrengthBar" class="h-full w-0 bg-red-500 transition-all duration-500"></div>
                            </div>
                            <p id="passwordStrengthText" class="text-[10px] mt-1 font-bold text-right uppercase tracking-wider text-gray-400"></p>
                        </div>

                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-gray-300">Foto Profilo <span class="text-xs font-normal text-gray-400">(Opzionale)</span></label>
                    <div id="dropzone" class="rounded-2xl border-2 border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800/50 hover:bg-cyan-50 dark:hover:bg-cyan-900/10 hover:border-cyan-400 px-6 py-6 text-center cursor-pointer transition-all duration-300 group">
                        <input type="file" id="foto_profilo" name="foto_profilo" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden">

                        <div class="flex flex-col sm:flex-row items-center gap-5 justify-center">
                            <div class="relative shrink-0">
                                <img id="previewFoto" src="https://placehold.co/160x160/e2e8f0/475569?text=Avatar" alt="Anteprima foto" class="w-20 h-20 rounded-full object-cover border-4 border-white dark:border-slate-700 shadow-md group-hover:scale-105 transition-transform duration-300">
                                <button type="button" id="removePhotoBtn" class="hidden absolute -bottom-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1.5 shadow-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <div class="text-center sm:text-left">
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">Trascina o clicca qui</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">PNG, JPG, WEBP max 5MB</p>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full mt-4 bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white px-6 py-4 rounded-xl font-bold text-lg shadow-[0_10px_20px_-10px_rgba(8,145,178,0.5)] hover:-translate-y-0.5 transition-all duration-300">
                    Conferma →
                </button>
            </form>

            <div class="mt-8 flex items-center justify-between">
                <span class="w-1/5 border-b border-gray-200 dark:border-gray-700 lg:w-1/4"></span>
                <span class="text-xs text-center text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider">Oppure accedi con</span>
                <span class="w-1/5 border-b border-gray-200 dark:border-gray-700 lg:w-1/4"></span>
            </div>
            
            <div class="flex justify-center mt-6">
                <a href="google_callback.php" class="w-full sm:w-3/4 flex items-center justify-center gap-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-white px-4 py-3.5 rounded-xl shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700 hover:shadow-md transition font-bold">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="w-5 h-5" alt="Google"> 
                    Continua con Google
                </a>
            </div>

            <p class="mt-8 text-sm text-gray-600 dark:text-gray-400 text-center font-medium">
                Hai già un account? <a href="login.php" class="text-cyan-600 dark:text-cyan-400 hover:text-cyan-700 underline decoration-cyan-300/50 hover:decoration-cyan-600 transition-colors">Accedi qui</a>
            </p>
        </div>
    </div>
</div>

<style>
/* Piccola animazione fluida per il cambio sezioni */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up { animation: fadeInUp 0.3s ease-out forwards; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- 1. GESTIONE SEZIONI RUOLO ----
    const ruoloRadios = document.querySelectorAll('input[name="ruolo"]');
    const existingLibrarySection = document.getElementById('existing-library-section');
    const newLibrarySection = document.getElementById('new-library-section');
    const libreriaSelect = document.getElementById('libreria_select');
    
    function updateSections() {
        const selectedRole = document.querySelector('input[name="ruolo"]:checked').value;
        if (selectedRole === 'gestore') {
            existingLibrarySection.classList.add('hidden');
            newLibrarySection.classList.remove('hidden');
            newLibrarySection.classList.add('animate-fade-in-up');
            if (libreriaSelect) { libreriaSelect.required = false; libreriaSelect.disabled = true; }
        } else {
            existingLibrarySection.classList.remove('hidden');
            newLibrarySection.classList.add('hidden');
            existingLibrarySection.classList.add('animate-fade-in-up');
            if (libreriaSelect) { libreriaSelect.required = true; libreriaSelect.disabled = false; }
        }
    }
    ruoloRadios.forEach(radio => radio.addEventListener('change', updateSections));
    updateSections();

    // ---- 2. OCCHIO PASSWORD ----
    const passInput = document.getElementById('passwordInput');
    const toggleBtn = document.getElementById('togglePassword');
    toggleBtn.addEventListener('click', () => {
        if (passInput.type === 'password') {
            passInput.type = 'text'; toggleBtn.textContent = '🙈';
        } else {
            passInput.type = 'password'; toggleBtn.textContent = '👁️';
        }
    });

    // ---- 3. BARRA SICUREZZA PASSWORD ----
    const passBar = document.getElementById('passwordStrengthBar');
    const passText = document.getElementById('passwordStrengthText');
    
    passInput.addEventListener('input', () => {
        let val = passInput.value;
        let strength = 0;
        if (val.length >= 6) strength += 1; 
        if (val.length >= 10) strength += 1; 
        if (/[A-Z]/.test(val)) strength += 1; 
        if (/[0-9]/.test(val)) strength += 1; 
        if (/[^A-Za-z0-9]/.test(val)) strength += 1; 

        if (val.length === 0) {
            passBar.style.width = '0%'; passText.textContent = '';
        } else if (strength <= 2) {
            passBar.style.width = '33%'; passBar.className = 'h-full bg-red-500 transition-all duration-300';
            passText.textContent = 'Debole'; passText.className = 'text-[10px] mt-1 font-bold text-right uppercase tracking-wider text-red-500';
        } else if (strength === 3 || strength === 4) {
            passBar.style.width = '66%'; passBar.className = 'h-full bg-yellow-400 transition-all duration-300';
            passText.textContent = 'Media'; passText.className = 'text-[10px] mt-1 font-bold text-right uppercase tracking-wider text-yellow-500';
        } else {
            passBar.style.width = '100%'; passBar.className = 'h-full bg-green-500 transition-all duration-300 shadow-[0_0_10px_rgba(34,197,94,0.6)]';
            passText.textContent = 'Forte!'; passText.className = 'text-[10px] mt-1 font-bold text-right uppercase tracking-wider text-green-500';
        }
    });

    // ---- 4. CONTROLLO AJAX TEMPO REALE ----
    async function checkAvailability(type, value, statusEl) {
        if(value.trim() === '') { statusEl.innerHTML = ''; return; }
        
        statusEl.innerHTML = '<span class="animate-spin text-cyan-500">⏳</span>';
        statusEl.classList.remove('scale-110');
        
        let libId = libreriaSelect && !libreriaSelect.disabled ? libreriaSelect.value : 0;
        
        try {
            const res = await fetch(`api_check_user.php?type=${type}&value=${encodeURIComponent(value)}&lib_id=${libId}`);
            const data = await res.json();
            
            statusEl.classList.add('scale-110'); 
            if(data.available) {
                statusEl.innerHTML = '✅';
                statusEl.title = "Disponibile!";
            } else {
                if (type === 'email') {
                    statusEl.innerHTML = '💡';
                    statusEl.title = "Account esistente: inserisci la tua password in basso per unirti a questa libreria!";
                } else {
                    statusEl.innerHTML = '❌';
                    statusEl.title = "Già in uso!";
                }
            }
        } catch(e) {
            statusEl.innerHTML = '⚠️';
        }
    }

    let emailTimeout, userTimeout;
    const emailInput = document.getElementById('emailInput');
    const emailStatus = document.getElementById('emailStatus');
    const userInput = document.getElementById('usernameInput');
    const userStatus = document.getElementById('usernameStatus');

    emailInput.addEventListener('input', () => {
        clearTimeout(emailTimeout);
        emailTimeout = setTimeout(() => checkAvailability('email', emailInput.value, emailStatus), 600);
    });

    userInput.addEventListener('input', () => {
        clearTimeout(userTimeout);
        userTimeout = setTimeout(() => checkAvailability('username', userInput.value, userStatus), 600);
    });

    if (libreriaSelect) {
        libreriaSelect.addEventListener('change', () => {
            if (userInput.value.trim() !== '') checkAvailability('username', userInput.value, userStatus);
        });
    }

    // ---- 5. DRAG & DROP FOTO E GPS ----
    const dropzone = document.getElementById('dropzone');
    const fotoInput = document.getElementById('foto_profilo');
    const previewFoto = document.getElementById('previewFoto');
    const removePhotoBtn = document.getElementById('removePhotoBtn');
    
    function showPreview(file) {
        if (!file) {
            previewFoto.src = 'https://placehold.co/160x160/e2e8f0/475569?text=Avatar';
            removePhotoBtn.classList.add('hidden');
            return;
        }
        const reader = new FileReader();
        reader.onload = e => { previewFoto.src = e.target.result; removePhotoBtn.classList.remove('hidden'); };
        reader.readAsDataURL(file);
    }

    if (dropzone && fotoInput) {
        dropzone.addEventListener('click', (e) => { if(e.target !== removePhotoBtn) fotoInput.click(); });
        fotoInput.addEventListener('change', function () { showPreview(this.files[0] || null); });
        dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('border-cyan-400', 'bg-cyan-50/50'); });
        dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('border-cyan-400', 'bg-cyan-50/50'); });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault(); dropzone.classList.remove('border-cyan-400', 'bg-cyan-50/50');
            if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
            fotoInput.files = e.dataTransfer.files; showPreview(e.dataTransfer.files[0]);
        });
    }

    if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', function (e) {
            e.stopPropagation(); fotoInput.value = ''; showPreview(null);
        });
    }

    const btnGetLocation = document.getElementById('btnGetLocation');
    if (btnGetLocation) {
        const locationStatus = document.getElementById('locationStatus');
        btnGetLocation.addEventListener('click', function() {
            if (!navigator.geolocation) { locationStatus.textContent = "GPS non supportato."; locationStatus.classList.add('text-red-500'); return; }
            btnGetLocation.disabled = true; btnGetLocation.innerHTML = "⏳..."; locationStatus.textContent = "Acquisizione posizione in corso...";
            navigator.geolocation.getCurrentPosition(async function(position) {
                document.getElementById('latitudine').value = position.coords.latitude;
                document.getElementById('longitudine').value = position.coords.longitude;
                try {
                    const url = `https://nominatim.openstreetmap.org/reverse?lat=${position.coords.latitude}&lon=${position.coords.longitude}&format=json`;
                    const response = await fetch(url, { headers: { 'Accept-Language': 'it' }});
                    const data = await response.json();
                    if (data && data.display_name) {
                        document.getElementById('new_lib_indirizzo').value = data.display_name;
                        locationStatus.innerHTML = "<b>Posizione trovata!</b> ✅";
                        locationStatus.className = "mt-2 text-xs text-green-600 dark:text-green-400 font-bold";
                    }
                } catch(e) { locationStatus.textContent = "Coordinate salvate, sistemare il testo a mano."; }
                finally { btnGetLocation.disabled = false; btnGetLocation.innerHTML = "📍 Trova"; }
            }, function(error) {
                btnGetLocation.disabled = false; btnGetLocation.innerHTML = "📍 Riprova";
                locationStatus.className = "mt-2 text-xs text-red-500 font-bold";
                locationStatus.textContent = error.code === error.PERMISSION_DENIED ? "Permesso negato. Scrivi a mano." : "Errore GPS.";
            }, { enableHighAccuracy: true });
        });
    }
});
</script>
</body>
</html>