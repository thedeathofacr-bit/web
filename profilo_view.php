<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
require_user_page($conn);

$mio_id = $_SESSION['user_id']; 

// DETERMINA QUALE PROFILO VISUALIZZARE
$id_visualizzato = isset($_GET['id']) ? intval($_GET['id']) : $mio_id;
$is_mio_profilo = ($id_visualizzato === $mio_id);

// --- INIZIO: LOGICA RECUPERO LIBRERIE PER LO SWITCH ---
$librerie_disponibili = [];
$libreria_corrente = $_SESSION['id_libreria'] ?? 0;
$nome_libreria_corrente = 'Nessuna libreria selezionata';

// Evitiamo crash se la tabella libreria non esiste ancora (Corretto al singolare)
$check_table = $conn->query("SHOW TABLES LIKE 'libreria'");
if ($check_table && $check_table->num_rows > 0) {
    // Corretto 'libreria' al singolare
    $res_librerie = $conn->query("SELECT id, nome FROM libreria ORDER BY nome ASC");
    if ($res_librerie) {
        while ($row = $res_librerie->fetch_assoc()) {
            $librerie_disponibili[] = $row;
            // Setta la prima come default se non c'è in sessione
            if ($libreria_corrente == 0) {
                $libreria_corrente = $row['id'];
                $_SESSION['id_libreria'] = $row['id'];
                $_SESSION['nome_libreria'] = $row['nome'];
            }
            if ($row['id'] == $libreria_corrente) {
                $nome_libreria_corrente = $row['nome'];
            }
        }
    }
}
// --- FINE LOGICA LIBRERIE ---

// CREAZIONE AUTOMATICA TABELLA CARTA DI CREDITO
$conn->query("CREATE TABLE IF NOT EXISTS carte_credito (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL UNIQUE,
    numero_carta VARCHAR(20) NOT NULL,
    scadenza VARCHAR(5) NOT NULL,
    cvc VARCHAR(4) NOT NULL,
    saldo DECIMAL(10,2) DEFAULT 0.00
)");

// RECUPERO CARTA DI CREDITO
$stmt_carta = $conn->prepare("SELECT * FROM carte_credito WHERE id_utente = ?");
$stmt_carta->bind_param("i", $id_visualizzato);
$stmt_carta->execute();
$carta = $stmt_carta->get_result()->fetch_assoc();
$stmt_carta->close();

// 1. RECUPERO DATI UTENTE VISUALIZZATO
$colonna_xp = "punti_esperienza"; 
$query_user = "SELECT *, $colonna_xp as punti_effettivi FROM utenti WHERE id = ?";
$stmt_user = $conn->prepare($query_user);

if (!$stmt_user) {
    die("Errore SQL: " . $conn->error . ". Assicurati di avere la colonna '$colonna_xp' nella tabella utenti.");
}

$stmt_user->bind_param("i", $id_visualizzato);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) { die("Utente non trovato."); }

// Fallback dati e LOGICA GAMIFICATION AVANZATA
$nome_utente = !empty($user_data['nome']) ? $user_data['nome'] : $user_data['username'];
$ruolo = ucfirst($user_data['ruolo'] ?? 'Utente');
$data_reg = !empty($user_data['data_registrazione']) ? date("d M Y", strtotime($user_data['data_registrazione'])) : 'Recente';

// Calcolo Livello Reale (1 Livello ogni 50 XP)
$xp_attuali = isset($user_data['punti_effettivi']) ? (int)$user_data['punti_effettivi'] : 0;
$livello = floor($xp_attuali / 50) + 1;
$xp_per_prossimo_livello = $livello * 50;
$xp_nel_livello_corrente = $xp_attuali % 50;
$progresso_xp = ($xp_nel_livello_corrente / 50) * 100;

// Titoli sbloccabili in base al livello
$titolo_utente = "Lettore Novizio";
if($livello >= 3) $titolo_utente = "Esploratore di Libri";
if($livello >= 5) $titolo_utente = "Cavaliere della Conoscenza";
if($livello >= 10) $titolo_utente = "Maestro Bibliotecario";
if($livello >= 20) $titolo_utente = "Leggenda Vivente";

// --- GESTIONE IMMAGINI ---
$base_upload_path = "uploads/";
$foto_db = $user_data['foto'] ?? '';
$path_foto_final = (!empty($foto_db) && file_exists($base_upload_path . "profili/" . $foto_db)) ? $base_upload_path . "profili/" . $foto_db : "https://ui-avatars.com/api/?name=" . urlencode($nome_utente) . "&background=06b6d4&color=fff&size=256";

$banner_db = $user_data['banner'] ?? ''; 
if (!empty($banner_db) && file_exists($base_upload_path . "banners/" . $banner_db)) {
    $banner_style = "background-image: url('".$base_upload_path . "banners/" . $banner_db."'); background-size: cover; background-position: center;";
} else {
    $banner_style = "background: linear-gradient(to bottom right, #0891b2, #1e40af);";
}

// 2. RECUPERO STATISTICHE
$stats = ['prestiti' => 0, 'desideri' => 0, 'recensioni' => 0];
$res_prestiti = $conn->query("SELECT COUNT(*) as tot FROM prestiti WHERE email_cliente = '" . $conn->real_escape_string($user_data['email']) . "'");
if($res_prestiti) $stats['prestiti'] = $res_prestiti->fetch_assoc()['tot'];
$res_desideri = $conn->query("SELECT COUNT(*) as tot FROM lista_desideri WHERE id_utente = $id_visualizzato");
if($res_desideri) $stats['desideri'] = $res_desideri->fetch_assoc()['tot'];
$res_recensioni = $conn->query("SELECT COUNT(*) as tot FROM recensioni WHERE id_utente = $id_visualizzato");
if($res_recensioni) $stats['recensioni'] = $res_recensioni->fetch_assoc()['tot'];

// 3. LOGICA OBIETTIVI
$progresso_desideri = min(100, ($stats['desideri'] / 10) * 100);
$progresso_prestiti = min(100, ($stats['prestiti'] / 5) * 100); 
?>

<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo di <?= htmlspecialchars($nome_utente) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .tab-content { display: none; animation: fadeIn 0.4s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .progress-bar-animated { transition: width 1.5s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* Aura Animata per Livelli Alti */
        .avatar-glow { position: relative; }
        <?php if($livello >= 5): ?>
        .avatar-glow::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; background: linear-gradient(45deg, #06b6d4, #8b5cf6, #06b6d4); background-size: 200%; z-index: -1; animation: moveGlow 3s linear infinite; filter: blur(12px); opacity: 0.8; }
        @keyframes moveGlow { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }
        <?php endif; ?>

        /* Stile Carta di Credito */
        .credit-card { background: linear-gradient(135deg, #0f172a, #1e1b4b, #06b6d4); border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 2px 2px rgba(255,255,255,0.2); }
        .chip { width: 45px; height: 35px; background: linear-gradient(135deg, #fbbf24, #d97706); border-radius: 8px; border: 1px solid rgba(0,0,0,0.2); }

        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast { background: rgba(15, 23, 42, 0.95); border-left: 4px solid #06b6d4; padding: 12px 20px; border-radius: 12px; margin-bottom: 8px; animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px); font-size: 0.85rem; font-weight: 600; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .edit-banner-btn { opacity: 0; transition: opacity 0.3s; }
        .banner-container:hover .edit-banner-btn { opacity: 1; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen pb-20 selection:bg-cyan-500 selection:text-white">

    <div class="fixed inset-0 -z-10">
        <div class="absolute top-[0%] right-[0%] w-[40%] h-[40%] rounded-full bg-fuchsia-900/20 blur-[150px]"></div>
        <div class="absolute bottom-[0%] left-[0%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
    </div>

    <div id="toast-container"></div>

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors font-bold text-sm uppercase tracking-wider bg-slate-800/50 px-4 py-2 rounded-full border border-white/5">
                <span>←</span> Home
            </a>
            <h1 class="text-xl font-black text-white uppercase italic tracking-widest">Nexus <span class="text-cyan-500">Profile</span></h1>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 lg:px-6">
        <div class="flex flex-col lg:flex-row gap-8">

            <div class="w-full lg:w-1/3">
                <div class="glass-panel rounded-[3rem] overflow-hidden sticky top-28 shadow-2xl border border-white/10" data-tilt data-tilt-max="2" data-tilt-speed="400" data-tilt-perspective="1000">
                    
                    <div class="h-40 relative banner-container transition-all" style="<?= $banner_style ?>">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0f172a] to-transparent opacity-80"></div>
                        <?php if($is_mio_profilo): ?>
                        <div class="absolute top-4 right-4 z-10 edit-banner-btn">
                             <form id="form-banner" action="upload_banner.php" method="POST" enctype="multipart/form-data">
                                <input type="file" name="file_banner" id="input-banner" class="hidden" accept="image/*">
                                <button type="button" onclick="document.getElementById('input-banner').click();" class="w-10 h-10 bg-black/60 hover:bg-cyan-500 text-white rounded-full flex items-center justify-center border border-white/20 shadow-xl backdrop-blur-md transition-all active:scale-95">✏️</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <div class="absolute -bottom-10 -right-4 text-[8rem] font-black text-white/10 select-none pointer-events-none"><?= $livello ?></div>
                    </div>
                    
                    <div class="px-8 pb-10 relative text-center">
                        <div class="w-36 h-36 mx-auto -mt-18 rounded-full relative z-10 p-1 bg-gradient-to-tr from-indigo-500 to-cyan-400 shadow-2xl shadow-cyan-500/30 avatar-glow mb-4">
                            <img src="<?= $path_foto_final ?>" class="w-full h-full object-cover rounded-full border-4 border-[#0f172a]" alt="Avatar">
                            
                            <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-black text-sm px-5 py-1 rounded-full border-2 border-[#0f172a] shadow-lg whitespace-nowrap">
                                LIV <?= $livello ?>
                            </div>

                            <?php if($is_mio_profilo): ?>
                            <form id="form-foto" action="upload_foto.php" method="POST" enctype="multipart/form-data">
                                <input type="file" name="foto_profilo" id="input-foto" class="hidden" accept="image/*">
                                <button type="button" onclick="document.getElementById('input-foto').click();" class="absolute bottom-2 -right-2 w-10 h-10 bg-cyan-500 hover:bg-cyan-400 text-white rounded-full flex items-center justify-center border-4 border-[#0f172a] transition-all shadow-lg active:scale-95 hover:rotate-12">📷</button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <h2 class="text-3xl font-black text-white mt-6 tracking-tight"><?= htmlspecialchars($nome_utente) ?></h2>
                        <p class="text-cyan-400 font-bold text-sm mb-4 flex items-center justify-center gap-1.5">
                            <span>🏆</span> <?= $titolo_utente ?>
                        </p>

                        <div class="flex justify-center gap-2 mb-8">
                            <span class="px-3 py-1 bg-slate-800 text-slate-300 text-[10px] font-bold rounded-md border border-white/5 uppercase tracking-wider"><?= $ruolo ?></span>
                            <span class="px-3 py-1 bg-slate-800/50 text-slate-500 text-[10px] font-bold rounded-md uppercase tracking-wider">Dal <?= date("Y", strtotime($data_reg)) ?></span>
                        </div>

                        <div class="bg-slate-900/60 rounded-[2rem] p-6 border border-white/5 shadow-inner">
                            <div class="flex justify-between items-end mb-3">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Esperienza</p>
                                <p class="text-xl font-black text-white"><?= $xp_attuali ?> <span class="text-sm text-cyan-500">XP</span></p>
                            </div>
                            
                            <div class="w-full bg-slate-950 h-3 rounded-full overflow-hidden shadow-inner border border-white/5 relative">
                                <div class="bg-gradient-to-r from-cyan-400 to-indigo-500 h-full rounded-full progress-bar-animated relative" style="width: 0%" id="xpBar">
                                    <div class="absolute inset-0 bg-white/20 w-full h-full animate-pulse"></div>
                                    <div class="absolute right-0 top-0 bottom-0 w-4 bg-white/40 blur-[2px]"></div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between mt-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                <span>LIV <?= $livello ?></span>
                                <span><?= $xp_per_prossimo_livello ?> XP PER IL LIV <?= $livello + 1 ?></span>
                            </div>
                        </div>

                        <?php if($is_mio_profilo): ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 mt-6">
                            <a href="segnala.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl text-[9px] uppercase tracking-widest transition-colors border border-white/5 text-center flex flex-col items-center justify-center gap-1 shadow-lg">
                                <span class="text-lg">🎟️</span> Ticket
                            </a>
                            <a href="tessera.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl text-[9px] uppercase tracking-widest transition-all hover:scale-105 border border-white/5 text-center flex flex-col items-center justify-center gap-1 shadow-lg">
                                <span class="text-lg">🪪</span> Tessera
                            </a>
                            <a href="scaffale.php" class="bg-gradient-to-br from-cyan-600 to-blue-500 text-white font-black py-3 rounded-xl text-[9px] uppercase tracking-widest transition-all hover:scale-105 border border-white/20 text-center flex flex-col items-center justify-center gap-1 shadow-xl shadow-cyan-500/30">
                                <span class="text-lg">📚</span> Scaffale
                            </a>
                            <a href="wrapped.php" class="bg-gradient-to-r from-fuchsia-600 to-purple-600 text-white font-black py-3 rounded-xl text-[9px] uppercase tracking-widest transition-all hover:scale-105 border border-white/20 text-center flex flex-col items-center justify-center gap-1 shadow-[0_0_15px_rgba(192,38,211,0.4)] relative overflow-hidden group">
                                <div class="absolute inset-0 bg-white/20 blur-xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                <span class="text-lg relative z-10">🎬</span> <span class="relative z-10">Wrapped</span>
                            </a>
                            <a href="notifiche.php" class="col-span-2 md:col-span-1 lg:col-span-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl text-[9px] uppercase tracking-widest transition-colors border border-white/5 relative text-center flex flex-col items-center justify-center gap-1 shadow-lg">
                                <span class="text-lg">🔔</span> Notifiche
                                <span class="absolute top-2 right-2 w-2 h-2 bg-fuchsia-500 rounded-full animate-ping"></span>
                            </a>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <div class="w-full lg:w-2/3">
                <div class="glass-panel p-2 rounded-[2rem] flex flex-wrap md:flex-nowrap gap-2 mb-8 relative z-10 overflow-x-auto no-scrollbar shadow-lg">
                    <button class="tab-btn active flex-1 py-4 px-6 rounded-[1.5rem] font-bold text-sm transition-all bg-gradient-to-r from-cyan-600 to-cyan-500 text-white shadow-lg shadow-cyan-500/20" data-target="viaggio">🚀 Viaggio</button>
                    <button class="tab-btn flex-1 py-4 px-6 rounded-[1.5rem] font-bold text-sm text-slate-400 hover:text-white hover:bg-slate-800/50 transition-all" data-target="forum">💬 Forum</button>
                    <button class="tab-btn flex-1 py-4 px-6 rounded-[1.5rem] font-bold text-sm text-slate-400 hover:text-white hover:bg-slate-800/50 transition-all" data-target="trofei">🏆 Trofei</button>
                    <?php if($is_mio_profilo): ?>
                    <button class="tab-btn flex-1 py-4 px-6 rounded-[1.5rem] font-bold text-sm text-slate-400 hover:text-white hover:bg-slate-800/50 transition-all" data-target="portafoglio">💳 Wallet</button>
                    <button class="tab-btn flex-1 py-4 px-6 rounded-[1.5rem] font-bold text-sm text-slate-400 hover:text-white hover:bg-slate-800/50 transition-all" data-target="account">⚙️ Account</button>
                    <?php endif; ?>
                </div>

                <div id="viaggio" class="tab-content active space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="glass-panel p-8 rounded-[2.5rem] shadow-xl">
                            <h4 class="text-[10px] uppercase font-black text-cyan-500 mb-4 tracking-widest text-center">Analisi Gusti</h4>
                            <canvas id="radarChart" height="200"></canvas>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="scaffale.php" class="block glass-panel p-6 rounded-[2rem] text-center hover:-translate-y-2 hover:shadow-cyan-500/20 hover:shadow-xl transition-all border-t border-white/5" data-tilt data-tilt-max="5">
                                <div class="text-4xl mb-3">📚</div>
                                <h4 class="text-3xl font-black text-white"><?= $stats['prestiti'] ?></h4>
                                <p class="text-[9px] uppercase font-bold text-slate-500 mt-2 tracking-widest">I Miei Prestiti</p>
                            </a>
                            <a href="scaffale.php" class="block glass-panel p-6 rounded-[2rem] text-center hover:-translate-y-2 hover:shadow-fuchsia-500/20 hover:shadow-xl transition-all border-t border-white/5" data-tilt data-tilt-max="5">
                                <div class="text-4xl mb-3">❤️</div>
                                <h4 class="text-3xl font-black text-white"><?= $stats['desideri'] ?></h4>
                                <p class="text-[9px] uppercase font-bold text-slate-500 mt-2 tracking-widest">Lista Desideri</p>
                            </a>
                            <div class="glass-panel p-6 rounded-[2rem] text-center border-t border-cyan-500/30 bg-cyan-900/10 col-span-2 shadow-inner">
                                <div class="text-3xl font-black text-cyan-400"><?= $stats['recensioni'] ?></div>
                                <p class="text-[10px] uppercase font-bold text-cyan-600 mt-1 tracking-widest">Recensioni Scritte</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-panel p-8 rounded-[2.5rem] shadow-xl">
                        <h3 class="text-xl font-bold text-white mb-6">Attività Recente</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-5 p-5 bg-slate-900/60 rounded-[1.5rem] border border-white/5 shadow-inner">
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center text-xl shadow-lg">⚡</div>
                                <p class="text-sm font-medium text-slate-300">Esplora nuovi titoli e partecipa al forum per sbloccare obiettivi e salire di livello!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="forum" class="tab-content space-y-6">
                    <div class="glass-panel p-8 rounded-[2.5rem] border-dashed border border-white/10 shadow-xl">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
                            <h3 class="text-2xl font-black text-white italic">Community <span class="text-cyan-500">Lounge</span></h3>
                            <button onclick="document.getElementById('modalForum').classList.remove('hidden')" class="bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-black py-3 px-8 rounded-2xl text-xs uppercase tracking-widest shadow-lg shadow-cyan-500/30 transition-all hover:scale-105">+ Crea Discussione</button>
                        </div>
                        <form action="forum_lista.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="text" name="titolo" placeholder="Libro..." class="bg-slate-900/80 border border-white/5 rounded-2xl px-5 py-4 text-white outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 text-sm transition-all">
                            <select name="genere" class="bg-slate-900/80 border border-white/5 rounded-2xl px-5 py-4 text-white outline-none focus:border-cyan-500 text-sm appearance-none">
                                <option value="">Tutti i Generi</option>
                                <option value="Fantasy">Fantasy</option>
                                <option value="Saggi">Saggi</option>
                            </select>
                            <button class="bg-slate-800 text-cyan-400 font-bold rounded-2xl border border-white/5 hover:bg-cyan-500 hover:text-white transition-all shadow-md active:scale-95">🔍 Filtra Discussioni</button>
                        </form>
                    </div>
                </div>

                <div id="trofei" class="tab-content">
                    <div class="glass-panel p-8 md:p-10 rounded-[2.5rem] shadow-xl">
                        <h3 class="text-2xl font-black text-white mb-8 text-center md:text-left">Bacheca Trofei</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="border <?= $stats['desideri'] >= 10 ? 'border-cyan-500 bg-cyan-900/20' : 'border-slate-700/50 border-dashed bg-slate-900/50 opacity-60 hover:opacity-100' ?> rounded-[2rem] p-8 flex flex-col text-center relative overflow-hidden group transition-all duration-300">
                                <div class="w-20 h-20 mx-auto bg-slate-900 rounded-[1.5rem] flex items-center justify-center mb-6 text-4xl shadow-inner border border-white/5 group-hover:scale-110 transition-transform duration-500">
                                    <?= $stats['desideri'] >= 10 ? '🐭' : '🔒' ?>
                                </div>
                                <h4 class="text-sm font-black text-white uppercase tracking-widest mb-2">Topo di Biblioteca</h4>
                                <p class="text-xs text-slate-500 mb-6 font-medium">Aggiungi 10 libri ai desideri</p>
                                <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden mt-auto shadow-inner">
                                    <div class="bg-gradient-to-r from-cyan-500 to-blue-500 h-full transition-all duration-1000" style="width: <?= $progresso_desideri ?>%"></div>
                                </div>
                            </div>
                            <div class="border <?= $stats['prestiti'] >= 5 ? 'border-indigo-500 bg-indigo-900/20' : 'border-slate-700/50 border-dashed bg-slate-900/50 opacity-60 hover:opacity-100' ?> rounded-[2rem] p-8 flex flex-col text-center relative overflow-hidden group transition-all duration-300">
                                <div class="w-20 h-20 mx-auto bg-slate-900 rounded-[1.5rem] flex items-center justify-center mb-6 text-4xl shadow-inner border border-white/5 group-hover:scale-110 transition-transform duration-500">
                                    <?= $stats['prestiti'] >= 5 ? '⚡' : '🔒' ?>
                                </div>
                                <h4 class="text-sm font-black text-white uppercase tracking-widest mb-2">Fulmine della Lettura</h4>
                                <p class="text-xs text-slate-500 mb-6 font-medium">Completa 5 prestiti</p>
                                <div class="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden mt-auto shadow-inner">
                                    <div class="bg-gradient-to-r from-indigo-500 to-purple-500 h-full transition-all duration-1000" style="width: <?= $progresso_prestiti ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($is_mio_profilo): ?>
                
                <div id="portafoglio" class="tab-content">
                    <div class="glass-panel p-8 md:p-12 rounded-[3rem] shadow-2xl relative overflow-hidden">
                        
                        <div class="flex justify-between items-center mb-10">
                            <div>
                                <h3 class="text-3xl font-black text-white tracking-tight">Il tuo Wallet</h3>
                                <p class="text-slate-400 font-medium mt-1">Gestisci la tua carta e il saldo e-book.</p>
                            </div>
                            <div class="w-16 h-16 bg-slate-800/80 rounded-2xl flex items-center justify-center text-3xl shadow-inner border border-white/5">💳</div>
                        </div>

                        <?php if(!$carta): ?>
                            <div class="text-center bg-black/40 rounded-[2rem] p-10 border border-white/5 border-dashed">
                                <div class="text-6xl mb-6 opacity-80">🎁</div>
                                <h4 class="text-2xl font-black text-white mb-4">Richiedi la tua Nexus Card</h4>
                                <p class="text-slate-400 mb-8 max-w-sm mx-auto">È completamente gratuita, simulata e include un bonus di benvenuto di <strong class="text-emerald-400">50.00€</strong> per i tuoi acquisti!</p>
                                <button onclick="creaCarta()" class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:scale-105 text-white font-black px-8 py-4 rounded-2xl shadow-lg shadow-emerald-500/30 transition-all uppercase tracking-widest text-sm">
                                    Genera Carta Virtuale
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="grid lg:grid-cols-2 gap-12 items-center">
                                
                                <div class="relative perspective-1000 group">
                                    <div class="credit-card w-full aspect-[1.6/1] rounded-[2rem] p-8 flex flex-col justify-between relative overflow-hidden transform group-hover:rotate-y-12 transition-transform duration-500">
                                        <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-[50px] pointer-events-none"></div>
                                        <div class="flex justify-between items-start relative z-10">
                                            <div class="chip"></div>
                                            <div class="text-white/50 text-2xl font-black italic">NEXUS</div>
                                        </div>
                                        <div class="relative z-10">
                                            <div class="text-white font-mono text-xl md:text-2xl tracking-[4px] mb-2 text-shadow"><?= htmlspecialchars($carta['numero_carta']) ?></div>
                                            <div class="flex justify-between text-white/60 font-mono text-sm uppercase mt-4">
                                                <span><?= htmlspecialchars(explode(' ', $nome_utente)[0]) ?></span>
                                                <span><?= htmlspecialchars($carta['scadenza']) ?></span>
                                                <span><?= htmlspecialchars($carta['cvc']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="bg-black/30 rounded-[2rem] p-8 border border-white/5 text-center mb-6 shadow-inner relative overflow-hidden">
                                        <div class="absolute inset-0 bg-emerald-500/5"></div>
                                        <p class="text-xs font-black uppercase text-slate-500 tracking-widest mb-2 relative z-10">Saldo Disponibile</p>
                                        <p class="text-6xl font-black text-emerald-400 drop-shadow-md relative z-10">€ <?= number_format($carta['saldo'], 2, ',', '.') ?></p>
                                    </div>

                                    <div class="bg-slate-900/50 rounded-2xl p-6 border border-white/5">
                                        <p class="text-xs font-bold text-slate-400 mb-3">Ricarica il saldo (Simulata)</p>
                                        <div class="flex gap-2">
                                            <div class="relative flex-1">
                                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">€</span>
                                                <input type="number" id="importoRicarica" min="5" max="500" placeholder="50.00" class="w-full bg-black/50 border border-white/10 rounded-xl py-3 pl-8 pr-4 text-white font-bold outline-none focus:border-emerald-500 transition-colors">
                                            </div>
                                            <button onclick="ricaricaCarta()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black px-6 rounded-xl uppercase tracking-widest text-xs transition-colors shadow-lg active:scale-95">Ricarica</button>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <div id="account" class="tab-content">
                    
                    <div class="glass-panel p-8 rounded-[2.5rem] mb-6 shadow-xl relative overflow-hidden border border-cyan-500/30">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-500/10 rounded-full blur-[80px] pointer-events-none"></div>
                        
                        <div class="flex items-center gap-4 mb-6 relative z-10">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center text-2xl shadow-lg border border-white/10">
                                🏢
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white italic">Sede Operativa</h3>
                                <p class="text-sm text-slate-400 font-medium">Attualmente sei in: <strong class="text-cyan-400"><?= htmlspecialchars($nome_libreria_corrente) ?></strong></p>
                            </div>
                        </div>

                        <div class="flex flex-col md:flex-row gap-4 relative z-10">
                            <div class="relative flex-1">
                                <select id="libreriaSwitcher" class="w-full bg-slate-900/80 border border-white/5 rounded-2xl pl-5 pr-10 py-4 text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm appearance-none cursor-pointer">
                                    <?php if (empty($librerie_disponibili)): ?>
                                        <option value="">Nessuna libreria trovata nel DB</option>
                                    <?php else: ?>
                                        <?php foreach ($librerie_disponibili as $lib): ?>
                                            <option value="<?= $lib['id'] ?>" <?= $lib['id'] == $libreria_corrente ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($lib['nome']) ?> <?= $lib['id'] == $libreria_corrente ? '(Attuale)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="absolute right-5 top-1/2 -translate-y-1/2 pointer-events-none text-cyan-500 font-bold">▼</div>
                            </div>
                            <button onclick="switchLibreria()" id="btnSwitch" class="bg-cyan-500 hover:bg-cyan-400 text-white font-bold py-4 px-8 rounded-2xl transition-all shadow-lg shadow-cyan-500/20 active:scale-95 text-xs uppercase tracking-widest whitespace-nowrap border border-cyan-400/50">
                                Cambia Sede
                            </button>
                        </div>
                    </div>
                    <div class="glass-panel p-8 rounded-[2.5rem] mb-6 shadow-xl border border-white/5">
                        <h3 class="text-xl font-bold text-white mb-2 italic">Dati Personali</h3>
                        <p class="text-sm text-slate-400 mb-8 font-medium">Aggiorna le tue informazioni di base.</p>
                        <form class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 pl-1">Nome Completo</label>
                                    <input type="text" value="<?= htmlspecialchars($nome_utente) ?>" class="w-full bg-slate-900/80 border border-white/5 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 pl-1">Username</label>
                                    <input type="text" value="<?= htmlspecialchars($user_data['username'] ?? '') ?>" class="w-full bg-slate-900/40 border border-white/5 rounded-2xl px-5 py-4 text-slate-500 cursor-not-allowed text-sm" disabled>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 pl-1">Indirizzo Email</label>
                                <input type="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" class="w-full bg-slate-900/80 border border-white/5 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all text-sm">
                            </div>
                            <div class="pt-4 flex justify-end">
                                <button type="button" class="bg-cyan-500 hover:bg-cyan-400 text-white font-bold py-4 px-10 rounded-2xl transition-all shadow-lg shadow-cyan-500/20 active:scale-95 text-xs uppercase tracking-widest">Salva Modifiche</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="glass-panel p-8 rounded-[2.5rem] mb-6 shadow-xl border border-white/5">
                        <h3 class="text-xl font-bold text-white mb-2 italic">Sicurezza e Password</h3>
                        <form action="aggiorna_password.php" method="POST" class="space-y-5 mt-6">
                            <input type="password" name="nuova_password" required placeholder="Nuova Password" class="w-full bg-slate-900/80 border border-white/5 rounded-2xl px-5 py-4 text-white focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 outline-none text-sm transition-all">
                            <input type="password" name="conferma_password" required placeholder="Conferma Password" class="w-full bg-slate-900/80 border border-white/5 rounded-2xl px-5 py-4 text-white focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 outline-none text-sm transition-all">
                            <button type="submit" class="bg-slate-800 text-white font-bold py-4 px-8 rounded-2xl transition-all hover:bg-fuchsia-600 hover:shadow-lg hover:shadow-fuchsia-500/20 active:scale-95 text-xs uppercase tracking-widest border border-white/5">Aggiorna Password</button>
                        </form>
                    </div>

                    <div class="glass-panel p-8 rounded-[2.5rem] border border-red-500/30 bg-red-950/20 text-center relative overflow-hidden mt-6">
                        <div class="absolute inset-0 bg-red-500/5 blur-xl"></div>
                        <h3 class="text-lg font-black text-red-400 mb-3 relative z-10 uppercase tracking-widest">Zona Pericolosa</h3>
                        <p class="text-sm text-slate-400 mb-6 relative z-10">Una volta eliminato l'account, non si torna indietro.</p>
                        <button class="relative z-10 bg-transparent border-2 border-red-500/50 text-red-400 hover:bg-red-500 hover:text-white font-bold py-4 px-8 rounded-2xl transition-all active:scale-95 text-xs uppercase tracking-widest">Elimina Definitivamente</button>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <div id="modalForum" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/90 backdrop-blur-md p-4 transition-opacity">
        <div class="glass-panel w-full max-w-2xl p-10 rounded-[3rem] border border-cyan-500/30 relative shadow-2xl shadow-cyan-900/20">
            <button onclick="document.getElementById('modalForum').classList.add('hidden')" class="absolute top-8 right-8 text-slate-500 hover:text-white text-2xl transition-colors">✕</button>
            <h3 class="text-3xl font-black text-white mb-8 uppercase italic tracking-tight">Nuova <span class="text-cyan-500">Discussione</span></h3>
            <form action="salva_forum.php" method="POST" class="space-y-5">
                <input type="text" name="titolo_discussione" required placeholder="Oggetto della discussione..." class="w-full bg-slate-900 border border-white/5 rounded-2xl px-6 py-5 text-white focus:border-cyan-500 outline-none text-sm transition-all">
                <div class="grid grid-cols-2 gap-5">
                    <input type="text" name="libro_titolo" placeholder="Titolo Libro (Opzionale)" class="bg-slate-900 border border-white/5 rounded-2xl px-6 py-5 text-white outline-none focus:border-cyan-500 text-sm transition-all">
                    <input type="text" name="libro_autore" placeholder="Autore (Opzionale)" class="bg-slate-900 border border-white/5 rounded-2xl px-6 py-5 text-white outline-none focus:border-cyan-500 text-sm transition-all">
                </div>
                <textarea name="messaggio" rows="5" required placeholder="Scrivi il tuo messaggio qui..." class="w-full bg-slate-900 border border-white/5 rounded-2xl px-6 py-5 text-white focus:border-cyan-500 outline-none text-sm transition-all resize-none"></textarea>
                <button type="submit" class="w-full bg-gradient-to-r from-cyan-600 to-cyan-500 text-white font-black py-5 rounded-2xl shadow-xl shadow-cyan-500/20 hover:scale-[1.02] active:scale-95 uppercase tracking-widest text-sm transition-all">Pubblica Post</button>
            </form>
        </div>
    </div>

    <script>
        function showToast(msg) {
            const container = document.getElementById('toast-container');
            const t = document.createElement('div');
            t.className = 'toast';
            t.innerHTML = `Nexus System: <span class="text-cyan-400">${msg}</span>`;
            container.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // INIZIALIZZA GRAFICO RADAR
            const radarCtx = document.getElementById('radarChart').getContext('2d');
            new Chart(radarCtx, {
                type: 'radar',
                data: {
                    labels: ['Fantasy', 'Saggi', 'Thriller', 'Romanzi', 'Classici'],
                    datasets: [{
                        label: 'Interessi',
                        data: [80, 45, 65, 90, 50],
                        backgroundColor: 'rgba(6, 182, 212, 0.2)',
                        borderColor: '#06b6d4',
                        pointBackgroundColor: '#06b6d4',
                        borderWidth: 2
                    }]
                },
                options: {
                    scales: { r: { angleLines: { color: 'rgba(255,255,255,0.1)' }, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { display: false } } },
                    plugins: { legend: { display: false } }
                }
            });

            // GESTIONE TABS
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabBtns.forEach(b => { 
                        b.classList.remove('bg-gradient-to-r', 'from-cyan-600', 'to-cyan-500', 'text-white', 'shadow-lg', 'shadow-cyan-500/20'); 
                        b.classList.add('text-slate-400'); 
                    });
                    btn.classList.add('bg-gradient-to-r', 'from-cyan-600', 'to-cyan-500', 'text-white', 'shadow-lg', 'shadow-cyan-500/20');
                    btn.classList.remove('text-slate-400');
                    
                    tabContents.forEach(c => c.classList.remove('active'));
                    document.getElementById(btn.getAttribute('data-target')).classList.add('active');
                });
            });

            // ANIMAZIONE XP BAR
            setTimeout(() => {
                const xpBar = document.getElementById('xpBar');
                if(xpBar) xpBar.style.width = '<?= $progresso_xp ?>%';
            }, 500);

            // GESTIONE FOTO E BANNER
            <?php if($is_mio_profilo): ?>
            document.getElementById('input-foto').onchange = () => { showToast('Caricamento avatar in corso...'); document.getElementById('form-foto').submit(); };
            document.getElementById('input-banner').onchange = () => { showToast('Aggiornamento banner in corso...'); document.getElementById('form-banner').submit(); };
            <?php endif; ?>

            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.has('updated')) showToast('Modifiche salvate con successo! ✨');
        });

        // GESTIONE WALLET
        async function creaCarta() {
            let fd = new FormData();
            fd.append('action', 'crea');
            try {
                let res = await fetch('api_carta.php', { method: 'POST', body: fd });
                let data = await res.json();
                if(data.success) {
                    showToast('Nexus Card creata! Ti abbiamo accreditato 50€!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert(data.msg);
                }
            } catch(e) { console.log(e); }
        }

        async function ricaricaCarta() {
            let importo = document.getElementById('importoRicarica').value;
            if(!importo || importo <= 0) return alert('Inserisci un importo valido');
            
            let btn = event.target;
            btn.innerHTML = '...'; btn.disabled = true;

            let fd = new FormData();
            fd.append('action', 'ricarica');
            fd.append('importo', importo);
            
            try {
                let res = await fetch('api_carta.php', { method: 'POST', body: fd });
                let data = await res.json();
                if(data.success) {
                    showToast('Ricarica effettuata con successo! 💸');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert(data.msg);
                    btn.innerHTML = 'Ricarica'; btn.disabled = false;
                }
            } catch(e) { console.log(e); }
        }

        // --- GESTIONE CAMBIO LIBRERIA (SWITCHER) ---
        async function switchLibreria() {
            const btn = document.getElementById('btnSwitch');
            const select = document.getElementById('libreriaSwitcher');
            const nuovaLibreriaId = select.value;
            const currentId = "<?= $libreria_corrente ?>";

            if (!nuovaLibreriaId) {
                showToast("Nessuna libreria selezionata.");
                return;
            }

            if (nuovaLibreriaId === currentId) {
                showToast("Sei già operativo in questa sede.");
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-not-allowed');
            btn.innerHTML = 'Sincronizzazione...';

            const fd = new FormData();
            fd.append('id_libreria', nuovaLibreriaId);

            try {
                const res = await fetch('api_switch_libreria.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Sede cambiata con successo! Ricaricamento sistema...');
                    // Ricarichiamo la pagina per aggiornare le sessioni in tutto l'applicativo
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.message || 'Errore durante il cambio sede.');
                    btn.disabled = false;
                    btn.classList.remove('opacity-70', 'cursor-not-allowed');
                    btn.innerHTML = originalText;
                }
            } catch (e) {
                showToast('Errore di connessione al server centrale.');
                btn.disabled = false;
                btn.classList.remove('opacity-70', 'cursor-not-allowed');
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>