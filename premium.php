<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id_utente = $_SESSION['user_id'];
$id_libro = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id_libro']) ? (int)$_POST['id_libro'] : 0);

// FIX DATABASE ALTERVISTA
$check_premium = $conn->query("SHOW COLUMNS FROM utenti LIKE 'is_premium'");
if ($check_premium && $check_premium->num_rows == 0) { $conn->query("ALTER TABLE utenti ADD COLUMN is_premium INT DEFAULT 0"); }

$is_premium = false;
$stmt_prem = $conn->prepare("SELECT is_premium FROM utenti WHERE id = ?");
if($stmt_prem) {
    $stmt_prem->bind_param("i", $id_utente); $stmt_prem->execute();
    if($row = $stmt_prem->get_result()->fetch_assoc()) { $is_premium = ($row['is_premium'] == 1); }
    $stmt_prem->close();
}

// API BACKEND (Cloud Sync & Gamification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['api_action'];

    if ($action === 'sync_progress' && $is_premium) {
        $conn->query("CREATE TABLE IF NOT EXISTS progresso_lettura (id INT AUTO_INCREMENT PRIMARY KEY, id_utente INT NOT NULL, id_libro INT NOT NULL, pagina INT DEFAULT 1, dati_json LONGTEXT, UNIQUE KEY(id_utente, id_libro))");
        $pagina = (int)$_POST['pagina']; $dati = $_POST['dati_json'] ?? '{}';
        $stmt = $conn->prepare("INSERT INTO progresso_lettura (id_utente, id_libro, pagina, dati_json) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE pagina = ?, dati_json = ?");
        $stmt->bind_param("iiisis", $id_utente, $id_libro, $pagina, $dati, $pagina, $dati); $stmt->execute();
        echo json_encode(['status' => 'ok']); exit;
    }
    
    if ($action === 'add_xp') {
        $xp = (int)$_POST['xp'];
        $check_xp = $conn->query("SHOW COLUMNS FROM utenti LIKE 'punti_esperienza'");
        if ($check_xp && $check_xp->num_rows == 0) { $conn->query("ALTER TABLE utenti ADD COLUMN punti_esperienza INT DEFAULT 0"); }
        
        $stmt = $conn->prepare("UPDATE utenti SET punti_esperienza = punti_esperienza + ? WHERE id = ?");
        $stmt->bind_param("ii", $xp, $id_utente); $stmt->execute();
        echo json_encode(['status' => 'ok', 'xp_added' => $xp]); exit;
    }
    echo json_encode(['status' => 'error']); exit;
}

$email_utente = $_SESSION['email'] ?? '';
$possiede = false;
$stmt1 = $conn->prepare("SELECT id FROM acquisti WHERE id_utente = ? AND id_libro = ?");
if ($stmt1) { $stmt1->bind_param("ii", $id_utente, $id_libro); $stmt1->execute(); if ($stmt1->get_result()->num_rows > 0) $possiede = true; $stmt1->close(); }
$stmt2 = $conn->prepare("SELECT id FROM prestiti WHERE email_cliente = ? AND libro_id = ?");
if ($stmt2) { $stmt2->bind_param("si", $email_utente, $id_libro); $stmt2->execute(); if ($stmt2->get_result()->num_rows > 0) $possiede = true; $stmt2->close(); }

if (!$possiede) die("<div style='background:#020617; color:white; height:100vh; display:flex; justify-content:center; align-items:center;'><h1 style='font-size:2rem;'>Accesso Negato 🔒</h1></div>");

$stmt = $conn->prepare("SELECT titolo, autore FROM libri WHERE id = ?");
if ($stmt) { $stmt->bind_param("i", $id_libro); $stmt->execute(); $libro = $stmt->get_result()->fetch_assoc(); $stmt->close(); } else { $libro = ['titolo' => 'Libro', 'autore' => '']; }

$percorso_pdf = "libri/libro_" . $id_libro . ".pdf";
$esiste_pdf = file_exists($percorso_pdf);

$cloud_page = 1; $cloud_data = '{"notes":[], "bookmarks":[], "highlights":[]}';
if ($is_premium) {
    $stmt_cloud = $conn->prepare("SELECT pagina, dati_json FROM progresso_lettura WHERE id_utente = ? AND id_libro = ?");
    if($stmt_cloud) {
        $stmt_cloud->bind_param("ii", $id_utente, $id_libro); $stmt_cloud->execute();
        if($row = $stmt_cloud->get_result()->fetch_assoc()) { $cloud_page = $row['pagina'] > 0 ? $row['pagina'] : 1; if(!empty($row['dati_json'])) $cloud_data = $row['dati_json']; }
        $stmt_cloud->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($libro['titolo']) ?> | Nexus Reader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden; transition: background-color 0.5s; }
        .glass-panel { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        #pdf-container { overflow: auto; height: calc(100vh - 64px); display: flex; justify-content: center; align-items: flex-start; padding: 40px 10px 150px 10px; transition: background-color 0.5s; scroll-behavior: smooth; }
        #pdf-container::-webkit-scrollbar { width: 6px; height: 6px; } #pdf-container::-webkit-scrollbar-track { background: transparent; } #pdf-container::-webkit-scrollbar-thumb { background: rgba(150,150,150,0.3); border-radius: 4px; }
        #canvas-wrapper { position: relative; transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); width: fit-content; margin: 0 auto; }
        .page-changing { opacity: 0; transform: scale(0.98) translateY(10px); }
        #the-canvas { position: relative; z-index: 2; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); transition: filter 0.5s ease; border-radius: 4px; max-width: 100%; height: auto; }
        #ambilight-canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; transition: filter 0.5s ease, opacity 0.5s ease; border-radius: 4px; transform: scale(1.02); }
        body.theme-light { background-color: #e2e8f0; } body.theme-light #the-canvas { filter: none; } body.theme-light #ambilight-canvas { filter: blur(40px) saturate(150%); opacity: 0.4; }
        body.theme-sepia { background-color: #d4c5b0; } body.theme-sepia #the-canvas { filter: sepia(0.8) contrast(0.9) brightness(0.9); } body.theme-sepia #ambilight-canvas { filter: sepia(0.8) contrast(0.9) brightness(0.9) blur(40px) saturate(120%); opacity: 0.4; }
        body.theme-dark { background-color: #020617; } body.theme-dark #the-canvas { filter: invert(0.9) hue-rotate(180deg) contrast(1.1); } body.theme-dark #ambilight-canvas { filter: invert(0.9) hue-rotate(180deg) contrast(1.1) blur(50px) saturate(200%); opacity: 0.6; }
        #dog-ear { position: absolute; top: 0; right: 0; width: 50px; height: 50px; background: linear-gradient(225deg, transparent 50%, rgba(255,255,255,0.7) 50%); z-index: 10; cursor: pointer; opacity: 0.3; transition: all 0.3s; border-radius: 0 4px 0 0; }
        #dog-ear:hover { opacity: 0.8; transform: scale(1.1) translate(-2px, 2px); } #dog-ear.bookmarked { background: linear-gradient(225deg, transparent 50%, #06b6d4 50%); opacity: 1; filter: drop-shadow(-2px 2px 5px rgba(0,0,0,0.5)); }
        .btn-control { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.1); color: white; transition: all 0.2s; font-weight: bold; border: 1px solid rgba(255,255,255,0.05); cursor: pointer; position: relative;}
        @media (min-width: 768px) { .btn-control { width: 40px; height: 40px; border-radius: 12px; } }
        .btn-control:hover:not(:disabled) { background: rgba(6,182,212,0.8); transform: scale(1.05); } .btn-control:disabled { opacity: 0.3; cursor: not-allowed; }
        .crown-icon { position: absolute; top: -5px; right: -5px; font-size: 10px; background: #fbbf24; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        #focus-ruler { position: fixed; left: 0; width: 100vw; height: 80px; background: rgba(6, 182, 212, 0.15); border-top: 2px solid rgba(6, 182, 212, 0.5); border-bottom: 2px solid rgba(6, 182, 212, 0.5); pointer-events: none; z-index: 40; display: none; transform: translateY(-50%); box-shadow: 0 -1000px 0 1000px rgba(0,0,0,0.5), 0 1000px 0 1000px rgba(0,0,0,0.5); backdrop-filter: contrast(1.1); }
        .loader-ring { border: 4px solid rgba(255,255,255,0.1); border-left-color: #06b6d4; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #subtitles-container { max-height: 150px; overflow-y: auto; scroll-behavior: smooth; } #subtitles-container::-webkit-scrollbar { width: 4px; } #subtitles-container::-webkit-scrollbar-thumb { background: rgba(6,182,212,0.5); border-radius: 4px; }
        .tts-word { border-radius: 4px; transition: all 0.2s; padding: 0 2px; } .tts-word:hover { background: rgba(6, 182, 212, 0.3); color: #fff; } .tts-word.active { background: #06b6d4; color: white; font-weight: 900; box-shadow: 0 0 10px rgba(6,182,212,0.8); }
        .no-scrollbar::-webkit-scrollbar { display: none; } .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .dict-hidden { opacity: 0; transform: scale(0.95) translateY(10px); pointer-events: none; visibility: hidden; }
        #dict-popup, #stats-modal, #paywall-modal { transition: opacity 0.2s, transform 0.2s, visibility 0.2s; }
        #notes-sidebar, #xray-sidebar { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        #highlight-menu { position: absolute; z-index: 100; display: none; transform: translate(-50%, -100%); margin-top: -10px; }
        .highlight-color { width: 24px; height: 24px; border-radius: 50%; cursor: pointer; border: 2px solid white; transition: transform 0.2s; } .highlight-color:hover { transform: scale(1.2); }
        #xp-toast { position: fixed; bottom: 100px; right: -300px; transition: right 0.5s cubic-bezier(0.4, 0, 0.2, 1); z-index: 100; } #xp-toast.show { right: 20px; }
    </style>
</head>
<body class="theme-dark h-screen flex flex-col relative selection:bg-cyan-500 selection:text-white" id="readerBody">

    <audio id="audio-rain" src="https://assets.mixkit.co/active_storage/sfx/2393/2393-preview.mp3" loop preload="none"></audio>
    <audio id="audio-fire" src="https://assets.mixkit.co/active_storage/sfx/1330/1330-preview.mp3" loop preload="none"></audio>
    <audio id="audio-forest" src="https://assets.mixkit.co/active_storage/sfx/1210/1210-preview.mp3" loop preload="none"></audio>
    <audio id="audio-bell" src="https://assets.mixkit.co/active_storage/sfx/2218/2218-preview.mp3" preload="auto"></audio>

    <div id="focus-ruler"></div>

    <div id="xp-toast" class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4 rounded-2xl shadow-[0_10px_30px_rgba(79,70,229,0.5)] border border-white/20 flex items-center gap-4">
        <div class="text-3xl animate-bounce">⭐</div>
        <div><p class="text-white font-black text-lg leading-tight" id="xp-toast-val">+10 XP</p><p class="text-indigo-200 text-xs font-bold uppercase tracking-widest" id="xp-toast-msg">Lettore Assiduo!</p></div>
    </div>

    <nav class="h-14 md:h-16 border-b border-white/10 flex items-center justify-between px-4 md:px-6 shrink-0 bg-[#020617] z-50 relative" id="topNav">
        <div class="absolute bottom-0 left-0 h-0.5 bg-white/5 w-full"><div id="readProgress" class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 transition-all duration-500 shadow-[0_0_15px_#06b6d4]" style="width: 0%;"></div></div>
        <div class="flex items-center gap-3 md:gap-4">
            <a href="scaffale.php" class="font-bold text-xs md:text-sm text-cyan-400 hover:text-cyan-300 transition-colors bg-cyan-900/20 px-3 py-1.5 md:px-4 md:py-2 rounded-xl flex items-center gap-2 border border-cyan-500/30"><span>←</span> <span class="hidden sm:inline">Esci</span></a>
            <div class="h-6 w-px bg-white/10 hidden sm:block"></div>
            <div class="hidden sm:block"><h1 class="font-black text-xs md:text-sm text-white leading-tight truncate max-w-[150px] md:max-w-xs uppercase tracking-wide"><?= htmlspecialchars($libro['titolo']) ?></h1></div>
        </div>
        <div class="flex items-center gap-2 md:gap-3">
            <button onclick="toggleFullScreen()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-300 hover:text-white hover:bg-white/10 transition-colors"><svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg></button>
            <div class="h-6 w-px bg-white/10"></div>
            <button onclick="setTheme('theme-light')" class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-slate-200 border-2 border-slate-400 shadow-inner hover:scale-110 transition-transform"></button>
            <button onclick="setTheme('theme-sepia')" class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-[#fbf0d9] border-2 border-[#d4c5b0] shadow-inner hover:scale-110 transition-transform"></button>
            <button onclick="setTheme('theme-dark')" class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-[#0f172a] border-2 border-slate-600 shadow-inner hover:scale-110 transition-transform"></button>
        </div>
    </nav>

    <?php if ($esiste_pdf): ?>
        
        <div id="loader" class="absolute inset-0 z-[60] flex flex-col items-center justify-center bg-[#020617] transition-opacity duration-500">
            <div class="loader-ring mb-4"></div><p class="text-cyan-400 font-bold uppercase tracking-widest text-xs md:text-sm animate-pulse">Apertura E-book...</p>
        </div>

        <div id="paywall-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] flex items-center justify-center dict-hidden">
            <div class="bg-gradient-to-br from-slate-900 to-[#020617] p-8 rounded-[2rem] w-[90%] max-w-md border border-amber-500/50 text-center shadow-[0_0_50px_rgba(245,158,11,0.2)] relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 to-yellow-600"></div>
                <button onclick="closePaywall()" class="absolute top-4 right-4 text-slate-500 hover:text-white text-xl">✕</button>
                <div class="text-5xl mb-2">👑</div>
                <h2 class="text-2xl font-black text-white mb-1 uppercase tracking-widest">Nexus Pro</h2>
                <p class="text-amber-400 text-sm font-bold mb-6">Sblocca il vero potere della lettura.</p>
                <p class="text-slate-300 text-sm mb-6" id="paywall-feature-msg">Questa funzione è riservata agli utenti Premium.</p>
                <ul class="text-left text-xs text-slate-400 space-y-2 mb-8">
                    <li><span class="text-emerald-400 mr-2">✓</span> Audiolibro IA con voci Naturali & Karaoke</li>
                    <li><span class="text-emerald-400 mr-2">✓</span> Ricerca Globale (X-Ray Scanner)</li>
                    <li><span class="text-emerald-400 mr-2">✓</span> Sincronizzazione Cloud</li>
                    <li><span class="text-emerald-400 mr-2">✓</span> Statistiche WPM e Lounge Zen</li>
                </ul>
                <a href="premium.php" class="block w-full bg-gradient-to-r from-amber-500 to-yellow-600 text-white font-black py-3 rounded-xl uppercase tracking-widest shadow-lg hover:scale-105 transition-transform">Diventa Pro a 4,99€</a>
                <button onclick="closePaywall()" class="mt-4 text-xs text-slate-500 hover:text-white underline">No grazie</button>
            </div>
        </div>

        <div id="pomodoro-overlay" class="fixed inset-0 bg-[#020617] z-[90] flex flex-col items-center justify-center pointer-events-none opacity-0 transition-opacity duration-1000">
            <div class="text-6xl mb-6 animate-bounce">🍅</div><h1 class="text-4xl font-black text-white mb-2">Pausa Mentale</h1>
            <p class="text-slate-400 max-w-md text-center">Hai letto intensamente per 25 minuti. Guarda lontano dallo schermo per rilassare gli occhi.</p>
            <button onclick="stopPomodoroBreak()" class="mt-8 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 px-8 rounded-2xl pointer-events-auto border border-white/10">Riprendi Lettura</button>
        </div>

        <div id="dict-popup" class="fixed top-1/4 left-1/2 -translate-x-1/2 w-[90%] max-w-md glass-panel p-6 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.9)] z-[70] dict-hidden border border-cyan-500/50">
            <div class="flex justify-between items-start mb-4"><h3 id="dict-title" class="text-xl font-black text-cyan-400 uppercase tracking-widest">Cerca...</h3><button onclick="closeDictionary()" class="text-slate-400 hover:text-white bg-slate-800 rounded-full w-8 h-8 flex items-center justify-center">✕</button></div>
            <div id="dict-content" class="text-slate-300 text-sm leading-relaxed max-h-40 overflow-y-auto no-scrollbar"><div class="animate-pulse text-center">Interrogazione Oracolo...</div></div>
        </div>

        <div id="stats-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[70] flex items-center justify-center dict-hidden">
            <div class="glass-panel p-8 rounded-[2rem] w-[90%] max-w-lg border border-fuchsia-500/30 text-center">
                <div class="text-5xl mb-4">📊</div><h2 class="text-2xl font-black text-white mb-6 uppercase tracking-widest">Le tue Statistiche</h2>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-slate-900/80 p-4 rounded-xl border border-white/5"><p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1">Velocità</p><p class="text-2xl font-black text-cyan-400"><span id="stats-wpm">0</span> <span class="text-xs">WPM</span></p></div>
                    <div class="bg-slate-900/80 p-4 rounded-xl border border-white/5"><p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1">Tempo in Sessione</p><p class="text-2xl font-black text-emerald-400"><span id="stats-time">0</span> <span class="text-xs">min</span></p></div>
                </div>
                <div class="bg-indigo-900/20 border border-indigo-500/30 p-4 rounded-xl mb-6"><p class="text-xs text-slate-400 font-bold mb-1">Stima fine libro:</p><p class="text-lg font-black text-indigo-400" id="stats-eta">Calcolo in corso...</p></div>
                <button onclick="toggleStats()" class="bg-slate-800 text-white px-8 py-3 rounded-xl font-bold uppercase text-xs tracking-widest hover:bg-slate-700 transition-colors">Chiudi</button>
            </div>
        </div>

        <div id="xray-sidebar" class="fixed top-16 left-0 bottom-0 w-80 glass-panel border-r border-white/10 z-[45] transform -translate-x-full flex flex-col shadow-2xl">
            <div class="p-4 border-b border-white/10 flex justify-between items-center bg-black/40"><h3 class="text-white font-black uppercase tracking-widest text-sm flex items-center gap-2">🔍 X-Ray</h3><button onclick="toggleXRay()" class="text-slate-400 hover:text-white">✕</button></div>
            <div class="p-4 border-b border-white/10">
                <div class="flex gap-2">
                    <input type="text" id="xray-input" placeholder="Cerca nel libro..." class="w-full bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-white text-sm outline-none focus:border-cyan-500" onkeypress="if(event.key === 'Enter') runXRay()">
                    <button onclick="runXRay()" class="bg-cyan-600 hover:bg-cyan-500 text-white px-3 rounded-xl font-bold">Go</button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto p-4 flex flex-col gap-3" id="xray-results"><p class="text-xs text-slate-500 text-center mt-4">Scrivi una parola e cerca in tutto il libro.</p></div>
        </div>

        <div id="notes-sidebar" class="fixed top-16 right-0 bottom-0 w-80 glass-panel border-l border-white/10 z-[45] transform translate-x-full flex flex-col shadow-2xl">
            <div class="p-4 border-b border-white/10 flex justify-between items-center bg-black/40"><h3 class="text-white font-black uppercase tracking-widest text-sm"><?= $is_premium ? 'Libreria Cloud ☁️' : 'Appunti Locali' ?></h3><button onclick="toggleNotes()" class="text-slate-400 hover:text-white">✕</button></div>
            <div class="flex-1 overflow-y-auto notes-list p-4 flex flex-col gap-6">
                <div><h4 class="text-[10px] text-cyan-400 font-bold uppercase tracking-widest mb-3 flex items-center gap-2">🔖 Segnalibri</h4><div id="bookmarks-list" class="flex flex-wrap gap-2"></div></div>
                <div class="w-full h-px bg-white/5"></div>
                <div><h4 class="text-[10px] text-pink-400 font-bold uppercase tracking-widest mb-3 flex items-center gap-2">🖍️ Citazioni</h4><div id="highlights-list" class="flex flex-col gap-3"></div></div>
                <div class="w-full h-px bg-white/5"></div>
                <div class="flex-1">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-[10px] text-amber-400 font-bold uppercase tracking-widest">✍️ Appunti</h4>
                        <button onclick="<?= $is_premium ? 'exportNotes()' : "showPaywall('Esportazione Appunti')" ?>" class="text-[9px] bg-white/10 hover:bg-white/20 text-white px-2 py-1 rounded font-bold uppercase tracking-wider transition-colors relative">📥 Esporta <?= !$is_premium ? '<span class="crown-icon right-[-8px] top-[-8px]">👑</span>' : '' ?></button>
                    </div>
                    <div id="notes-list" class="flex flex-col gap-3"></div>
                </div>
            </div>
            <div class="p-4 bg-black/40 border-t border-white/10">
                <textarea id="new-note-text" class="w-full bg-slate-900 border border-white/10 rounded-xl p-3 text-white text-sm outline-none focus:border-amber-500 resize-none mb-2" rows="3" placeholder="Scrivi..."></textarea>
                <button onclick="saveNote()" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 rounded-xl text-xs uppercase tracking-widest transition-colors">Salva <?= $is_premium ? 'in Cloud' : 'in Locale' ?></button>
            </div>
        </div>

        <div id="highlight-menu" class="glass-panel p-2 rounded-xl flex gap-2 shadow-2xl border border-white/20">
            <div class="highlight-color" style="background:#fef08a" onclick="addHighlight('#fef08a')"></div>
            <div class="highlight-color" style="background:#a7f3d0" onclick="addHighlight('#a7f3d0')"></div>
            <div class="highlight-color" style="background:#fbcfe8" onclick="addHighlight('#fbcfe8')"></div>
        </div>

        <div id="tts-player" class="absolute top-16 md:top-20 left-1/2 -translate-x-1/2 w-[95%] max-w-4xl glass-panel rounded-2xl md:rounded-3xl p-4 md:p-6 shadow-2xl z-40 transition-all duration-500 transform -translate-y-[150%] opacity-0 border border-cyan-500/30 flex flex-col max-h-[80vh]">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4 shrink-0">
                <div class="flex items-center gap-2 md:gap-3">
                    <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-gradient-to-r from-cyan-500 to-blue-500 flex items-center justify-center text-sm md:text-xl shadow-[0_0_15px_rgba(6,182,212,0.5)]">🤖</div>
                    <div><h3 class="text-white font-black uppercase tracking-widest text-[10px] md:text-sm flex items-center gap-2">Lettore AI <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse inline-block"></span></h3><p class="text-[10px] md:text-xs text-slate-400">Doppio click x Dizionario</p></div>
                </div>
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    <select id="voice-select" class="bg-slate-900 border border-white/10 text-cyan-400 text-[10px] md:text-xs font-bold rounded-lg px-2 py-1 outline-none cursor-pointer max-w-[120px] md:max-w-[200px] truncate" onchange="changeVoice()"><option value="">Caricamento...</option></select>
                    <select id="tts-speed" class="bg-slate-900 border border-white/10 text-white text-[10px] md:text-xs font-bold rounded-lg px-2 py-1 outline-none cursor-pointer" onchange="changeVoice()"><option value="0.8">Lento</option><option value="1.0" selected>Normale</option><option value="1.3">Veloce</option><option value="1.8">Flash</option></select>
                    <button onclick="<?= $is_premium ? 'toggleBionicReading()' : "showPaywall('Lettura Bionica')" ?>" id="btnBionic" class="bg-slate-800 hover:bg-slate-700 text-slate-300 border border-white/10 rounded-lg px-2 py-1 text-xs font-bold transition-all relative">🧠 Bionic <?= !$is_premium ? '<span class="crown-icon right-[-5px] top-[-5px]">👑</span>' : '' ?></button>
                    <button onclick="toggleTTSPlayer();" class="w-8 h-8 flex items-center justify-center bg-red-500/20 text-red-400 hover:bg-red-500 hover:text-white rounded-lg ml-1 transition-colors">✕</button>
                </div>
            </div>
            <div id="subtitles-container" class="bg-black/60 rounded-xl md:rounded-2xl p-4 border border-white/5 text-slate-400 text-sm md:text-[1.1rem] font-medium leading-relaxed relative flex-1" onmouseup="showHighlightMenu(event)"><p id="tts-text" class="transition-all duration-200">Analisi testo...</p></div>
            <div class="flex justify-center items-center gap-4 mt-4 shrink-0">
                <button onclick="stopTTS()" class="w-10 h-10 bg-slate-800 hover:bg-rose-500 text-white rounded-full flex items-center justify-center text-lg transition-all active:scale-95">⏹</button>
                <button onclick="playTTS()" id="btnPlay" class="w-14 h-14 bg-emerald-500 hover:bg-emerald-400 text-white rounded-full flex items-center justify-center text-2xl shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all hover:scale-105 active:scale-95">▶</button>
                <button onclick="pauseTTS()" id="btnPause" class="w-14 h-14 bg-amber-500 hover:bg-amber-400 text-white rounded-full flex items-center justify-center text-2xl shadow-[0_0_20px_rgba(245,158,11,0.4)] transition-all hover:scale-105 active:scale-95 hidden">⏸</button>
            </div>
        </div>

        <main id="pdf-container">
            <div id="canvas-wrapper">
                <div id="dog-ear" onclick="toggleBookmark()" title="Aggiungi Segnalibro"></div>
                <canvas id="ambilight-canvas" class="<?= !$is_premium ? 'hidden' : '' ?>"></canvas>
                <canvas id="the-canvas"></canvas>
            </div>
        </main>

        <div id="zenMenu" class="fixed bottom-20 md:bottom-24 left-1/2 -translate-x-1/2 glass-panel p-3 md:p-4 rounded-xl md:rounded-2xl shadow-2xl z-40 opacity-0 pointer-events-none transition-all duration-300 scale-95 flex flex-col md:flex-row gap-3 md:gap-4">
            <div class="flex justify-center gap-2 md:border-r md:border-white/10 md:pr-4">
                <button onclick="toggleAudio('audio-rain', this)" class="audio-btn w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-slate-800 border border-white/5 flex items-center justify-center hover:bg-cyan-900/50 transition-colors"><span class="text-lg md:text-xl">🌧️</span></button>
                <button onclick="toggleAudio('audio-fire', this)" class="audio-btn w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-slate-800 border border-white/5 flex items-center justify-center hover:bg-orange-900/50 transition-colors"><span class="text-lg md:text-xl">🔥</span></button>
                <button onclick="toggleAudio('audio-forest', this)" class="audio-btn w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-slate-800 border border-white/5 flex items-center justify-center hover:bg-emerald-900/50 transition-colors"><span class="text-lg md:text-xl">🌲</span></button>
            </div>
            <div class="flex justify-center gap-2 md:pl-2">
                <button onclick="toggleFocusRuler(this)" class="h-10 md:h-12 px-4 rounded-lg md:rounded-xl bg-slate-800 border border-white/5 flex items-center justify-center gap-2 hover:bg-purple-900/50 transition-colors text-[10px] md:text-xs font-bold text-slate-300 uppercase tracking-widest"><span>🔦</span> Focus</button>
                <button onclick="togglePomodoro(this)" id="btnPomodoro" class="h-10 md:h-12 px-4 rounded-lg md:rounded-xl bg-slate-800 border border-white/5 flex items-center justify-center gap-2 hover:bg-red-900/50 transition-colors text-[10px] md:text-xs font-bold text-slate-300 uppercase tracking-widest"><span>🍅</span> Timer</button>
            </div>
        </div>

        <div class="fixed bottom-4 md:bottom-6 left-1/2 -translate-x-1/2 w-[98%] md:w-auto glass-panel px-3 py-2 md:px-4 md:py-3 rounded-[1rem] md:rounded-2xl flex items-center justify-between md:justify-center gap-1 md:gap-3 shadow-[0_10px_40px_rgba(0,0,0,0.9)] z-50 transition-transform duration-300" id="bottomNav">
            
            <div class="flex gap-1 md:gap-2">
                <button onclick="<?= $is_premium ? 'toggleXRay()' : "showPaywall('Motore X-Ray Avanzato')" ?>" class="btn-control bg-gradient-to-br from-indigo-600 to-blue-600 border-none shadow-lg relative">🔍 <?= !$is_premium ? '<span class="crown-icon">👑</span>' : '' ?></button>
                <button onclick="<?= $is_premium ? 'toggleTTSPlayer()' : "showPaywall('Lettore Vocale IA')" ?>" class="btn-control bg-gradient-to-br from-cyan-600 to-blue-600 border-none shadow-lg hidden sm:flex relative">🗣️ <?= !$is_premium ? '<span class="crown-icon">👑</span>' : '' ?></button>
                <button onclick="<?= $is_premium ? 'toggleZenMenu()' : "showPaywall('Lounge Zen & Pomodoro')" ?>" class="btn-control bg-gradient-to-br from-fuchsia-600 to-purple-600 border-none shadow-lg relative">🧘‍♂️ <?= !$is_premium ? '<span class="crown-icon">👑</span>' : '' ?></button>
                <button onclick="<?= $is_premium ? 'toggleStats()' : "showPaywall('Statistiche Biometriche')" ?>" class="btn-control bg-gradient-to-br from-emerald-600 to-teal-600 border-none shadow-lg hidden sm:flex relative">📊 <?= !$is_premium ? '<span class="crown-icon">👑</span>' : '' ?></button>
                <button onclick="toggleNotes()" class="btn-control bg-gradient-to-br from-amber-600 to-orange-600 border-none shadow-lg" title="Appunti & Segnalibri">✍️</button>
            </div>

            <div id="pomoDisplay" class="hidden items-center gap-2 bg-red-500/20 px-3 py-1.5 rounded-xl border border-red-500/30 text-red-400 font-mono text-xs font-bold ml-1 md:ml-2 shadow-[0_0_10px_rgba(239,68,68,0.2)] animate-pulse"><span>🍅</span> <span id="pomoTimeLeft">25:00</span></div>
            <div class="w-px h-6 md:h-8 bg-white/10 mx-1 md:mx-2 hidden md:block"></div>

            <div class="hidden md:flex items-center gap-1 bg-black/40 rounded-xl border border-white/5 p-1">
                <button class="w-6 h-6 text-slate-400 hover:text-white flex items-center justify-center" onclick="changeAutoScroll(-1)">-</button>
                <button onclick="toggleAutoScroll()" id="btnAutoScroll" class="w-8 h-8 rounded-lg bg-slate-800 text-slate-300 hover:bg-cyan-600 hover:text-white flex items-center justify-center transition-colors" title="Auto Scroll">⏬</button>
                <button class="w-6 h-6 text-slate-400 hover:text-white flex items-center justify-center" onclick="changeAutoScroll(1)">+</button>
            </div>
            
            <div class="w-px h-6 md:h-8 bg-white/10 mx-1 md:mx-2 hidden sm:block"></div>
            <div class="hidden sm:flex gap-1"><button class="btn-control" id="zoomOut" title="Rimpicciolisci">A-</button><button class="btn-control" id="zoomIn" title="Ingrandisci">A+</button></div>
            <div class="w-px h-6 md:h-8 bg-white/10 mx-1 md:mx-2"></div>

            <div class="flex items-center gap-1 md:gap-2">
                <button class="btn-control" id="prev" title="Pagina Precedente">◀</button>
                <div class="text-white font-bold text-xs md:text-sm tracking-widest bg-black/40 px-2 md:px-4 py-1.5 md:py-2 rounded-lg md:rounded-xl border border-white/5 flex flex-col items-center leading-none min-w-[50px] md:min-w-[70px]">
                    <div><span id="page_num" class="text-cyan-400">0</span><span class="text-slate-500">/</span><span id="page_count" class="text-slate-400">0</span></div>
                </div>
                <button class="btn-control" id="next" title="Pagina Successiva">▶</button>
            </div>
        </div>

    <?php else: ?>
        <main class="flex-1 flex flex-col items-center justify-center p-6 text-center bg-[#020617]"><div class="w-20 h-20 md:w-24 md:h-24 mb-6 rounded-full bg-slate-800 border border-slate-600 flex items-center justify-center text-3xl md:text-4xl shadow-lg">📁</div><h2 class="text-xl md:text-2xl font-black text-white mb-2 tracking-tight">PDF Non Trovato</h2></main>
    <?php endif; ?>

    <script>
        const isPremium = <?= $is_premium ? 'true' : 'false' ?>;

        function showPaywall(featureName) { document.getElementById('paywall-feature-msg').textContent = `L'accesso a "${featureName}" è riservato agli utenti Premium.`; document.getElementById('paywall-modal').classList.remove('dict-hidden'); }
        function closePaywall() { document.getElementById('paywall-modal').classList.add('dict-hidden'); }

        function setTheme(theme) { document.getElementById('readerBody').className = 'h-screen flex flex-col relative selection:bg-cyan-500 selection:text-white ' + theme; localStorage.setItem('nexus_reader_theme', theme); }
        if (localStorage.getItem('nexus_reader_theme')) setTheme(localStorage.getItem('nexus_reader_theme'));
        function toggleFullScreen() { if (!document.fullscreenElement) document.documentElement.requestFullscreen(); else document.exitFullscreen(); }

        let zenMenuOpen = false;
        function toggleZenMenu() { zenMenuOpen = !zenMenuOpen; const menu = document.getElementById('zenMenu'); if(zenMenuOpen) menu.classList.remove('opacity-0', 'pointer-events-none', 'scale-95'); else menu.classList.add('opacity-0', 'pointer-events-none', 'scale-95'); }

        let currentAudio = null;
        function toggleAudio(audioId, btn) { document.querySelectorAll('.audio-btn').forEach(b => b.classList.remove('ring-2', 'ring-cyan-400')); const audio = document.getElementById(audioId); if (currentAudio && currentAudio !== audio) currentAudio.pause(); if (audio.paused) { audio.volume = 0.5; audio.play(); currentAudio = audio; btn.classList.add('ring-2', 'ring-cyan-400'); } else { audio.pause(); currentAudio = null; } }

        let rulerActive = false;
        function toggleFocusRuler(btn) { rulerActive = !rulerActive; if(rulerActive) { document.getElementById('focus-ruler').style.display = 'block'; btn.classList.add('ring-2', 'ring-purple-400', 'text-white'); btn.classList.remove('text-slate-300'); } else { document.getElementById('focus-ruler').style.display = 'none'; btn.classList.remove('ring-2', 'ring-purple-400', 'text-white'); btn.classList.add('text-slate-300'); } }
        document.addEventListener('mousemove', e => { if(rulerActive) document.getElementById('focus-ruler').style.top = e.clientY + 'px'; });

        let pomoInterval = null; let pomoTimeLeft = 25 * 60; 
        function togglePomodoro(btn) {
            const display = document.getElementById('pomoDisplay');
            if(pomoInterval) { clearInterval(pomoInterval); pomoInterval = null; pomoTimeLeft = 25 * 60; btn.classList.remove('ring-2', 'ring-red-500', 'bg-red-900/30', 'text-white'); btn.classList.add('text-slate-300'); display.classList.add('hidden'); display.classList.remove('flex'); } 
            else { btn.classList.add('ring-2', 'ring-red-500', 'bg-red-900/30', 'text-white'); btn.classList.remove('text-slate-300'); display.classList.remove('hidden'); display.classList.add('flex');
                pomoInterval = setInterval(() => { pomoTimeLeft--; let m = Math.floor(pomoTimeLeft / 60); let s = pomoTimeLeft % 60; document.getElementById('pomoTimeLeft').textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                    if(pomoTimeLeft <= 0) { clearInterval(pomoInterval); document.getElementById('audio-bell').play(); document.getElementById('pomodoro-overlay').style.opacity = '1'; document.getElementById('pomodoro-overlay').style.pointerEvents = 'auto'; }
                }, 1000);
            }
        }
        function stopPomodoroBreak() { document.getElementById('pomodoro-overlay').style.opacity = '0'; document.getElementById('pomodoro-overlay').style.pointerEvents = 'none'; togglePomodoro(document.getElementById('btnPomodoro')); }

        let readSeconds = 0; let wordsReadInSession = 0; setInterval(() => { readSeconds++; }, 1000);
        function toggleStats() {
            const modal = document.getElementById('stats-modal');
            if(modal.classList.contains('dict-hidden')) { let minutes = readSeconds / 60; let wpm = minutes > 0 ? Math.round(wordsReadInSession / minutes) : 0; if(wpm > 1000 || isNaN(wpm)) wpm = "-"; document.getElementById('stats-wpm').textContent = wpm; document.getElementById('stats-time').textContent = Math.floor(minutes);
                if(pdfDoc && wpm > 0 && wpm !== "-") { let pagesLeft = pdfDoc.numPages - pageNum; let estimatedWordsLeft = pagesLeft * 300; let minsLeft = estimatedWordsLeft / wpm; let hrs = Math.floor(minsLeft / 60); let mns = Math.round(minsLeft % 60); document.getElementById('stats-eta').textContent = `${hrs} ore e ${mns} minuti`; } else { document.getElementById('stats-eta').textContent = "Leggi per calcolare..."; }
                modal.classList.remove('dict-hidden');
            } else { modal.classList.add('dict-hidden'); }
        }

        let autoScrollInterval = null; let autoScrollSpeed = 1; 
        function toggleAutoScroll() { const btn = document.getElementById('btnAutoScroll'); if(autoScrollInterval) { clearInterval(autoScrollInterval); autoScrollInterval = null; btn.classList.remove('bg-cyan-600', 'text-white'); btn.classList.add('bg-slate-800', 'text-slate-300'); } else { btn.classList.remove('bg-slate-800', 'text-slate-300'); btn.classList.add('bg-cyan-600', 'text-white'); autoScrollInterval = setInterval(() => { document.getElementById('pdf-container').scrollTop += autoScrollSpeed; }, 30); } }
        function changeAutoScroll(val) { autoScrollSpeed += val; if(autoScrollSpeed < 1) autoScrollSpeed = 1; if(autoScrollSpeed > 5) autoScrollSpeed = 5; }

        <?php if ($esiste_pdf): ?>
        
        let pageNum = <?= $cloud_page ?>; let cloudData = <?= $cloud_data ?>; if(!cloudData.notes) cloudData = {notes:[], bookmarks:[], highlights:[]};
        
        if(!isPremium) {
            let locPage = localStorage.getItem('nexus_book_' + <?= $id_libro ?>); if(locPage) pageNum = parseInt(locPage);
            let locNotes = localStorage.getItem('nexus_notes_' + <?= $id_libro ?>); let locMarks = localStorage.getItem('nexus_bookmarks_' + <?= $id_libro ?>);
            if(locNotes) cloudData.notes = JSON.parse(locNotes); if(locMarks) cloudData.bookmarks = JSON.parse(locMarks);
        }

        function saveProgress() {
            if(isPremium) {
                let fd = new FormData(); fd.append('api_action', 'sync_progress'); fd.append('id_libro', <?= $id_libro ?>); fd.append('pagina', pageNum); fd.append('dati_json', JSON.stringify(cloudData)); fetch('', { method: 'POST', body: fd });
            } else {
                localStorage.setItem('nexus_book_' + <?= $id_libro ?>, pageNum); localStorage.setItem('nexus_notes_' + <?= $id_libro ?>, JSON.stringify(cloudData.notes)); localStorage.setItem('nexus_bookmarks_' + <?= $id_libro ?>, JSON.stringify(cloudData.bookmarks));
            }
        }

        function triggerXPReward(amount, msg) {
            let fd = new FormData(); fd.append('api_action', 'add_xp'); fd.append('xp', amount);
            fetch('', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{ if(d.status === 'ok') {
                const toast = document.getElementById('xp-toast'); document.getElementById('xp-toast-val').textContent = '+'+amount+' XP'; document.getElementById('xp-toast-msg').textContent = msg; toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 4000);
            }});
        }

        let notesOpen = false;
        function toggleNotes() { notesOpen = !notesOpen; const sb = document.getElementById('notes-sidebar'); if(notesOpen) sb.classList.remove('translate-x-full'); else sb.classList.add('translate-x-full'); renderNotes(); renderBookmarks(); renderHighlights(); }
        function saveNote() { const t = document.getElementById('new-note-text').value.trim(); if(!t) return; cloudData.notes.push({ text: t, page: pageNum, date: new Date().toLocaleDateString() }); document.getElementById('new-note-text').value = ''; renderNotes(); saveProgress(); }
        function deleteNote(i) { cloudData.notes.splice(i, 1); renderNotes(); saveProgress(); }
        function renderNotes() { const list = document.getElementById('notes-list'); if(cloudData.notes.length === 0) { list.innerHTML = `<div class="text-center text-slate-500 mt-4 text-xs">Nessun appunto.</div>`; return; } list.innerHTML = cloudData.notes.map((n, i) => `<div class="bg-slate-900/60 p-3 rounded-xl border border-white/5 relative group"><p class="text-[10px] text-amber-400 font-bold mb-1 cursor-pointer hover:underline" onclick="queueRenderPage(${n.page})">Pagina ${n.page} <span class="text-slate-500 font-normal ml-2">${n.date}</span></p><p class="text-sm text-slate-300 italic">"${n.text}"</p><button onclick="deleteNote(${i})" class="absolute top-2 right-2 text-red-500 opacity-0 group-hover:opacity-100 transition-opacity bg-black/50 rounded-md px-1">✕</button></div>`).join(''); }

        function toggleBookmark() { const i = cloudData.bookmarks.indexOf(pageNum); if(i === -1) cloudData.bookmarks.push(pageNum); else cloudData.bookmarks.splice(i, 1); cloudData.bookmarks.sort((a,b) => a - b); checkBookmarkVisuals(); renderBookmarks(); saveProgress(); }
        function checkBookmarkVisuals() { if(cloudData.bookmarks.includes(pageNum)) document.getElementById('dog-ear').classList.add('bookmarked'); else document.getElementById('dog-ear').classList.remove('bookmarked'); }
        function renderBookmarks() { const list = document.getElementById('bookmarks-list'); if(cloudData.bookmarks.length === 0) { list.innerHTML = `<span class="text-slate-500 text-xs">Nessuno.</span>`; return; } list.innerHTML = cloudData.bookmarks.map(p => `<button onclick="queueRenderPage(${p})" class="bg-cyan-900/40 border border-cyan-500/30 text-cyan-400 text-[10px] font-bold px-3 py-1 rounded-lg hover:bg-cyan-500 hover:text-white transition-colors">Pag ${p}</button>`).join(''); }

        let selectedTextToHighlight = "";
        function showHighlightMenu(e) { if(!isPremium) return; selectedTextToHighlight = window.getSelection().toString().trim(); const menu = document.getElementById('highlight-menu'); if(selectedTextToHighlight.length > 3) { menu.style.display = 'flex'; menu.style.left = e.pageX + 'px'; menu.style.top = e.pageY + 'px'; } else { menu.style.display = 'none'; } }
        function addHighlight(color) { if(selectedTextToHighlight) { if(!cloudData.highlights) cloudData.highlights = []; cloudData.highlights.push({ text: selectedTextToHighlight, page: pageNum, color: color }); document.getElementById('highlight-menu').style.display = 'none'; window.getSelection().removeAllRanges(); renderHighlights(); saveProgress(); } }
        function deleteHighlight(i) { cloudData.highlights.splice(i, 1); renderHighlights(); saveProgress(); }
        function renderHighlights() { const list = document.getElementById('highlights-list'); if(!cloudData.highlights || cloudData.highlights.length === 0) { list.innerHTML = `<span class="text-slate-500 text-xs">Nessuna.</span>`; return; } list.innerHTML = cloudData.highlights.map((h, i) => `<div class="bg-slate-900/60 p-3 rounded-xl border-l-4 relative group" style="border-color:${h.color}"><p class="text-[10px] text-slate-400 font-bold mb-1 cursor-pointer hover:underline" onclick="queueRenderPage(${h.page})">Pag. ${h.page}</p><p class="text-xs text-white">"${h.text}"</p><button onclick="deleteHighlight(${i})" class="absolute top-2 right-2 text-red-500 opacity-0 group-hover:opacity-100 transition-opacity bg-black/50 rounded-md px-1">✕</button></div>`).join(''); }
        document.addEventListener('mousedown', (e) => { if(e.target.closest('#highlight-menu') === null) document.getElementById('highlight-menu').style.display='none'; });

        function exportNotes() {
            let t = "=================================\nNEXUS LIBRARY - CLOUD EXPORT\nLibro: <?= addslashes($libro['titolo']) ?>\n=================================\n\n";
            if(cloudData.bookmarks.length > 0) { t += "🔖 SEGNALIBRI:\n"; cloudData.bookmarks.forEach(p => { t += `- Pagina ${p}\n`; }); t += "\n"; }
            if(cloudData.highlights && cloudData.highlights.length > 0) { t += "🖍️ EVIDENZIAZIONI:\n"; cloudData.highlights.forEach(h => { t += `[Pag. ${h.page}]\n"${h.text}"\n\n`; }); }
            t += "✍️ RIFLESSIONI:\n"; if(cloudData.notes.length > 0) { cloudData.notes.forEach(n => { t += `[Pag. ${n.page} - ${n.date}]\n"${n.text}"\n\n`; }); } else { t += "Nessun appunto.\n"; }
            let blob = new Blob([t], {type: "text/plain;charset=utf-8"}); let link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = `Nexus_Export_<?= $id_libro ?>.txt`; link.click();
        }

        let xrayOpen = false;
        function toggleXRay() { xrayOpen = !xrayOpen; const sb = document.getElementById('xray-sidebar'); if(xrayOpen) { sb.classList.remove('-translate-x-full'); document.getElementById('xray-input').focus(); } else sb.classList.add('-translate-x-full'); }
        async function runXRay() {
            const q = document.getElementById('xray-input').value.trim().toLowerCase(); const rc = document.getElementById('xray-results');
            if(q.length < 3) { rc.innerHTML = "<p class='text-amber-500 text-xs text-center'>Inserisci almeno 3 lettere.</p>"; return; }
            rc.innerHTML = "<div class='loader-ring mx-auto mt-4'></div><p class='text-cyan-400 text-xs text-center mt-2 animate-pulse'>Scansione in corso...</p>";
            let found = [];
            for(let i = 1; i <= pdfDoc.numPages; i++) {
                try { let page = await pdfDoc.getPage(i); let content = await page.getTextContent(); let ft = content.items.map(s => s.str).join(' '); let lt = ft.toLowerCase(); let idx = lt.indexOf(q);
                    if(idx !== -1) { let st = Math.max(0, idx - 40); let en = Math.min(ft.length, idx + q.length + 40); let sn = ft.substring(st, en).replace(new RegExp(q, 'ig'), m => `<b class="text-cyan-400 bg-cyan-900/50 px-1 rounded">${m}</b>`); found.push({ page: i, snippet: "..." + sn + "..." }); }
                } catch(e) {}
            }
            if(found.length === 0) { rc.innerHTML = `<p class='text-red-400 text-xs text-center'>Nessun risultato per "${q}".</p>`; } else { rc.innerHTML = found.map(f => `<div class="bg-slate-900/60 p-3 rounded-xl border border-white/5 cursor-pointer hover:border-cyan-500/50 transition-colors" onclick="queueRenderPage(${f.page}); toggleXRay();"><span class="text-[10px] text-cyan-500 font-bold block mb-1">Pagina ${f.page}</span><p class="text-xs text-slate-300 italic">${f.snippet}</p></div>`).join(''); }
        }

        function closeDictionary() { document.getElementById('dict-popup').classList.add('dict-hidden'); }
        async function lookupWord(word) {
            let cw = word.replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g,"").trim().toLowerCase(); if(cw.length < 3) return;
            const popup = document.getElementById('dict-popup'), title = document.getElementById('dict-title'), content = document.getElementById('dict-content');
            title.textContent = cw; content.innerHTML = "<div class='animate-pulse text-cyan-400'>Ricerca in corso...</div>"; popup.classList.remove('dict-hidden');
            try { let res = await fetch(`https://it.wikipedia.org/api/rest_v1/page/summary/${cw}`); if(!res.ok) throw new Error("Non trovato"); let data = await res.json(); if(data.extract) { content.innerHTML = `<p>${data.extract}</p>`; } else { throw new Error("Nessun estratto"); } } 
            catch(e) { content.innerHTML = `<p class="text-amber-500">Nessuna definizione trovata.</p><a href="https://it.wikipedia.org/wiki/${cw}" target="_blank" class="text-cyan-400 underline text-xs mt-2 block">Cerca su Wiki</a>`; }
        }

        const url = '<?= $percorso_pdf ?>';
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null, pageRendering = false, pageNumPending = null, scale = 0, customZoom = false, pagesTurnedInSession = 0;
        const canvas = document.getElementById('the-canvas'), ctx = canvas.getContext('2d'), wrapper = document.getElementById('canvas-wrapper');
        const ambiCanvas = document.getElementById('ambilight-canvas'), ambiCtx = ambiCanvas.getContext('2d');

        pdfjsLib.getDocument({ url: url, cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/', cMapPacked: true }).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_; document.getElementById('page_count').textContent = pdfDoc.numPages;
            if(pageNum > pdfDoc.numPages) pageNum = 1;
            const loader = document.getElementById('loader'); loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 500);
            renderPage(pageNum);
        }).catch(function(e) { document.getElementById('loader').style.display = 'none'; alert("Errore Caricamento PDF: " + e.message); });

        function renderPage(num) {
            pageRendering = true; pageNum = num; wrapper.classList.add('page-changing'); checkBookmarkVisuals();
            if(isTTSOpen) extractTextForTTS(num);
            
            pdfDoc.getPage(num).then(function(page) {
                if (!customZoom || scale === 0) { let viewport = page.getViewport({scale: 1}); if(window.innerWidth < 768) scale = (window.innerWidth - 20) / viewport.width; else scale = 1.2; }
                let viewport = page.getViewport({scale: scale});
                canvas.height = viewport.height; canvas.width = viewport.width;
                const renderContext = { canvasContext: ctx, viewport: viewport };
                const renderTask = page.render(renderContext);

                renderTask.promise.then(function() {
                    if(isPremium) { ambiCanvas.width = canvas.width; ambiCanvas.height = canvas.height; ambiCtx.drawImage(canvas, 0, 0); }
                    pageRendering = false; wrapper.classList.remove('page-changing'); document.getElementById('pdf-container').scrollTop = 0;
                    if (pageNumPending !== null) { renderPage(pageNumPending); pageNumPending = null; }
                });
            });

            document.getElementById('page_num').textContent = num; document.getElementById('prev').disabled = (num <= 1); document.getElementById('next').disabled = (num >= pdfDoc.numPages);
            let percentage = ((num / pdfDoc.numPages) * 100).toFixed(0); document.getElementById('readProgress').style.width = percentage + '%';
            
            saveProgress(); wordsReadInSession += 300; 

            pagesTurnedInSession++;
            if(pagesTurnedInSession > 0 && pagesTurnedInSession % 5 === 0 && pageNum < pdfDoc.numPages) { triggerXPReward(10, "Lettore Assiduo"); }
            if(pageNum === pdfDoc.numPages && pagesTurnedInSession > 2) { triggerXPReward(100, "Libro Completato!"); }
        }

        function queueRenderPage(num) { if (pageRendering) pageNumPending = num; else renderPage(num); }
        function onPrevPage() { if (pageNum <= 1) return; queueRenderPage(pageNum - 1); }
        function onNextPage() { if (pageNum >= pdfDoc.numPages) return; queueRenderPage(pageNum + 1); }
        function onZoomIn() { if (scale >= 3.0) return; scale += 0.2; customZoom = true; queueRenderPage(pageNum); }
        function onZoomOut() { if (scale <= 0.6) return; scale -= 0.2; customZoom = true; queueRenderPage(pageNum); }

        document.getElementById('prev').addEventListener('click', onPrevPage); document.getElementById('next').addEventListener('click', onNextPage);
        if(document.getElementById('zoomIn')) document.getElementById('zoomIn').addEventListener('click', onZoomIn); if(document.getElementById('zoomOut')) document.getElementById('zoomOut').addEventListener('click', onZoomOut);
        document.addEventListener('keydown', (e) => { if(e.key === 'ArrowRight') onNextPage(); if(e.key === 'ArrowLeft') onPrevPage(); });
        window.addEventListener('resize', () => { if(window.innerWidth < 768) { customZoom = false; renderPage(pageNum); } });

        const synth = window.speechSynthesis; let utterance = null, currentTextToSpeak = "", isTTSOpen = false, ttsCurrentIndex = 0, isPaused = false, isBionic = false, wordElements = []; 
        function populateVoiceList() {
            let voices = synth.getVoices(); if(voices.length === 0) return;
            const voiceSelect = document.getElementById('voice-select'); voiceSelect.innerHTML = '';
            let itVoices = voices.filter(v => v.lang.includes('it')); if(itVoices.length === 0) itVoices = voices; 
            itVoices.forEach((voice) => { const opt = document.createElement('option'); opt.textContent = voice.name; opt.value = voice.name; voiceSelect.appendChild(opt); });
            let best = itVoices.find(v => v.name.includes('Natural') || v.name.includes('Online') || v.name.includes('Elsa') || v.name.includes('Premium'));
            if(!best) best = itVoices.find(v => v.name.includes('Google')); if(best) voiceSelect.value = best.name;
        }
        if (speechSynthesis.onvoiceschanged !== undefined) speechSynthesis.onvoiceschanged = populateVoiceList; setTimeout(populateVoiceList, 1000);

        function toggleTTSPlayer() { const player = document.getElementById('tts-player'); isTTSOpen = !isTTSOpen; if(isTTSOpen) { player.classList.remove('-translate-y-[150%]', 'opacity-0'); extractTextForTTS(pageNum); } else { player.classList.add('-translate-y-[150%]', 'opacity-0'); stopTTS(); } }
        function toggleBionicReading() { isBionic = !isBionic; const btn = document.getElementById('btnBionic'); if(isBionic) { btn.classList.replace('text-slate-300', 'text-cyan-400'); btn.classList.add('bg-cyan-900/40', 'border-cyan-500/50'); } else { btn.classList.replace('text-cyan-400', 'text-slate-300'); btn.classList.remove('bg-cyan-900/40', 'border-cyan-500/50'); } renderInteractiveText(); }
        function makeBionic(word) { if(!isBionic || word.length <= 1) return word; const mid = Math.ceil(word.length / 2); return `<b class="font-black text-white">${word.slice(0, mid)}</b>${word.slice(mid)}`; }

        async function extractTextForTTS(num) {
            document.getElementById('tts-page-indicator').textContent = num; const container = document.getElementById('tts-text'); container.innerHTML = "<span class='animate-pulse text-cyan-400'>Analisi in corso...</span>"; await new Promise(resolve => setTimeout(resolve, 50)); stopTTS(); 
            try { if(!pdfDoc) throw new Error("Doc"); const page = await pdfDoc.getPage(num); const textContent = await page.getTextContent(); if(!textContent || !textContent.items || textContent.items.length === 0) { container.innerHTML = "<span class='text-amber-500'>Testo non leggibile.</span>"; return; } let lastY, text = ''; for (let item of textContent.items) { if (lastY == item.transform[5] || !lastY) { text += item.str + ' '; } else { text += '\n' + item.str + ' '; } lastY = item.transform[5]; } currentTextToSpeak = text.replace(/\s+/g, ' ').trim(); if(!currentTextToSpeak) { container.innerHTML = "<span class='text-amber-500'>Vuoto.</span>"; return; } ttsCurrentIndex = 0; renderInteractiveText(); } catch(e) { container.innerHTML = `<span class='text-red-500'>Errore estrazione.</span>`; }
        }

        function renderInteractiveText() {
            if(!currentTextToSpeak) return; const container = document.getElementById('tts-text'); container.innerHTML = ''; wordElements = []; let currentIndex = 0; const parts = currentTextToSpeak.split(/(\s+)/); const fragment = document.createDocumentFragment(); 
            parts.forEach(part => {
                if(part.trim() === '') { fragment.appendChild(document.createTextNode(part)); } else { const span = document.createElement('span'); span.className = 'tts-word cursor-pointer transition-colors'; span.innerHTML = makeBionic(part); span.dataset.idx = currentIndex; span.onclick = () => jumpToWord(parseInt(span.dataset.idx)); span.ondblclick = (e) => { e.stopPropagation(); lookupWord(part); }; fragment.appendChild(span); wordElements.push({ el: span, start: currentIndex, end: currentIndex + part.length }); } currentIndex += part.length;
            });
            container.appendChild(fragment);
        }

        function jumpToWord(index) { ttsCurrentIndex = index; if(synth.speaking && !isPaused) { pauseTTS(); setTimeout(playTTS, 100); } else { highlightWord(index); playTTS(); } }
        function highlightWord(charIndex) { document.querySelectorAll('.tts-word.active').forEach(el => el.classList.remove('active')); const match = wordElements.find(w => charIndex >= w.start && charIndex < w.end); if(match) { match.el.classList.add('active'); const container = document.getElementById('subtitles-container'); container.scrollTop = match.el.offsetTop - (container.clientHeight / 2); } }

        function playTTS() {
            if(!currentTextToSpeak) return; synth.cancel(); isPaused = false; let textToPlay = currentTextToSpeak.slice(ttsCurrentIndex); if(textToPlay.trim() === '') { ttsCurrentIndex = 0; textToPlay = currentTextToSpeak; }
            utterance = new SpeechSynthesisUtterance(textToPlay); utterance.lang = 'it-IT'; utterance.rate = parseFloat(document.getElementById('tts-speed').value);
            const selVoice = document.getElementById('voice-select').value; const voices = synth.getVoices(); const chosen = voices.find(v => v.name === selVoice); if(chosen) utterance.voice = chosen; window.myUtterance = utterance; 
            utterance.onboundary = function(event) { if (event.name === 'word') { let absoluteIndex = ttsCurrentIndex + event.charIndex; window.tempIndex = absoluteIndex; highlightWord(absoluteIndex); } };
            utterance.onend = function() { if(!isPaused) { ttsCurrentIndex = 0; document.querySelectorAll('.tts-word.active').forEach(el => el.classList.remove('active')); document.getElementById('btnPlay').classList.remove('hidden'); document.getElementById('btnPause').classList.add('hidden'); onNextPage(); } };
            synth.speak(utterance); document.getElementById('btnPlay').classList.add('hidden'); document.getElementById('btnPause').classList.remove('hidden');
        }

        function pauseTTS() { isPaused = true; synth.cancel(); if(window.tempIndex) ttsCurrentIndex = window.tempIndex; document.getElementById('btnPlay').classList.remove('hidden'); document.getElementById('btnPause').classList.add('hidden'); }
        function stopTTS() { isPaused = true; synth.cancel(); ttsCurrentIndex = 0; document.querySelectorAll('.tts-word.active').forEach(el => el.classList.remove('active')); document.getElementById('btnPlay').classList.remove('hidden'); document.getElementById('btnPause').classList.add('hidden'); const container = document.getElementById('subtitles-container'); if(container) container.scrollTop = 0; }
        function changeVoice() { if(synth.speaking && !isPaused) { pauseTTS(); setTimeout(playTTS, 100); } }
        <?php endif; ?>
    </script>
</body>
</html>