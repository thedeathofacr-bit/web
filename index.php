<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
require_user_page($conn);
$id_utente = $_SESSION['user_id']; 

$stmt_user = $conn->prepare("SELECT foto, nome, ruolo FROM utenti WHERE id = ?");
$stmt_user->bind_param("i", $id_utente);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();
$stmt_user->close();

$libraryId = current_library_id();

// --- CONTEGGIO SEGNALAZIONI APERTE (Solo per Admin) ---
$count_aperte = 0;
if (is_admin()) {
    $res_count = $conn->query("SELECT COUNT(*) as tot FROM segnalazioni WHERE stato = 'aperta'");
    if ($res_count) {
        $count_aperte = $res_count->fetch_assoc()['tot'];
    }
}
// -------------------------------------------------------------

$libri = [];
$totaleLibri = 0;
$generi = [];

$stats = [
    'totale_libri' => 0,
    'totale_generi' => 0,
    'prezzo_medio' => 0,
    'prezzo_massimo' => 0
];

$topLibro = ['titolo' => 'Nessuno', 'prezzo' => 0];
$attivitaRecenti = [];

// Caricamento dei primi 10 libri con le recensioni incluse
$stmt = $conn->prepare("
    SELECT l.*,
           COALESCE(AVG(r.voto), 0) AS media_voti,
           COUNT(r.id) AS num_recensioni
    FROM libri l
    LEFT JOIN recensioni r ON r.id_libro = l.id
    WHERE l.id_libreria = ?
    GROUP BY l.id
    ORDER BY l.titolo ASC
    LIMIT 10
");
if (!$stmt) { die("Errore SQL (libri iniziali): " . $conn->error); }
$stmt->bind_param("i", $libraryId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { $libri[] = $row; }
}
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM libri WHERE id_libreria = ?");
if (!$stmt) { die("Errore SQL (conteggio libri): " . $conn->error); }
$stmt->bind_param("i", $libraryId);
$stmt->execute();
$countResult = $stmt->get_result();
if ($countResult) {
    $rowCount = $countResult->fetch_assoc();
    $totaleLibri = (int)($rowCount['totale'] ?? 0);
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT DISTINCT genere
    FROM libri
    WHERE id_libreria = ?
      AND genere IS NOT NULL
      AND genere <> ''
    ORDER BY genere ASC
");
if (!$stmt) { die("Errore SQL (generi): " . $conn->error); }
$stmt->bind_param("i", $libraryId);
$stmt->execute();
$generiResult = $stmt->get_result();
if ($generiResult && $generiResult->num_rows > 0) {
    while ($row = $generiResult->fetch_assoc()) { $generi[] = $row['genere']; }
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS totale_libri,
        COUNT(DISTINCT CASE WHEN genere IS NOT NULL AND genere <> '' THEN genere END) AS totale_generi,
        AVG(prezzo) AS prezzo_medio,
        MAX(prezzo) AS prezzo_massimo
    FROM libri
    WHERE id_libreria = ?
");
if (!$stmt) { die("Errore SQL (statistiche): " . $conn->error); }
$stmt->bind_param("i", $libraryId);
$stmt->execute();
$statsQuery = $stmt->get_result();
if ($statsQuery) {
    $statsRow = $statsQuery->fetch_assoc();
    $stats = [
        'totale_libri' => (int)($statsRow['totale_libri'] ?? 0),
        'totale_generi' => (int)($statsRow['totale_generi'] ?? 0),
        'prezzo_medio' => (float)($statsRow['prezzo_medio'] ?? 0),
        'prezzo_massimo' => (float)($statsRow['prezzo_massimo'] ?? 0)
    ];
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT titolo, prezzo
    FROM libri
    WHERE id_libreria = ?
    ORDER BY prezzo DESC, titolo ASC
    LIMIT 1
");
if (!$stmt) { die("Errore SQL (libro più costoso): " . $conn->error); }
$stmt->bind_param("i", $libraryId);
$stmt->execute();
$topQuery = $stmt->get_result();
if ($topQuery && $topQuery->num_rows > 0) { $topLibro = $topQuery->fetch_assoc(); }
$stmt->close();

if (is_admin()) {
    $stmt = $conn->prepare("
        SELECT utente, azione, oggetto, oggetto_id, descrizione, data_operazione
        FROM log_attivita
        WHERE libreria_id = ?
        ORDER BY data_operazione DESC, id DESC
        LIMIT 6
    ");
    if (!$stmt) { die("Errore SQL (log attività): " . $conn->error); }
    $stmt->bind_param("i", $libraryId);
    $stmt->execute();
    $logResult = $stmt->get_result();
    if ($logResult && $logResult->num_rows > 0) {
        while ($row = $logResult->fetch_assoc()) { $attivitaRecenti[] = $row; }
    }
    $stmt->close();
}

$wishlistIds = [];
$stmt = $conn->prepare("SELECT id_libro FROM lista_desideri WHERE id_utente = ? AND id_libreria = ?");
if ($stmt) {
    $stmt->bind_param("ii", $id_utente, $libraryId);
    $stmt->execute();
    $resWish = $stmt->get_result();
    if ($resWish) {
        while ($wRow = $resWish->fetch_assoc()) {
            $wishlistIds[] = $wRow['id_libro'];
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Nexus Library</title>
<link rel="icon" type="image/png" href="assets/logo.png">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
    .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
    .glass-input { background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); color: white; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    /* Scrollbar Chat IA */
    #aiMessages::-webkit-scrollbar { width: 7px; }
    #aiMessages::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #06b6d4, #8b5cf6); border-radius: 999px; }
    #aiMessages::-webkit-scrollbar-track { background: transparent; }
    .ai-message-enter { animation: aiMessageEnter 0.25s ease-out; }
    @keyframes aiMessageEnter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .ai-typing-dot { animation: aiTypingBounce 1.2s infinite ease-in-out; }
    .ai-typing-dot:nth-child(2) { animation-delay: 0.15s; }
    .ai-typing-dot:nth-child(3) { animation-delay: 0.3s; }
    @keyframes aiTypingBounce { 0%, 80%, 100% { transform: translateY(0); opacity: 0.45; } 40% { transform: translateY(-4px); opacity: 1; } }
</style>
</head>
<body class="min-h-screen relative pb-20 selection:bg-cyan-500 selection:text-white">

    <div class="fixed inset-0 -z-20">
        <div class="absolute top-[0%] right-[0%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
        <div class="absolute bottom-[0%] left-[0%] w-[40%] h-[40%] rounded-full bg-fuchsia-900/20 blur-[150px]"></div>
    </div>

    <nav class="sticky top-0 z-40 glass-panel border-b border-white/5 px-6 py-4 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-cyan-500/30">N</div>
                <div>
                    <h1 class="text-xl font-black text-white uppercase italic tracking-widest leading-none">Nexus</h1>
                    <p class="text-[10px] text-cyan-400 font-bold uppercase tracking-widest leading-none">Library System</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <a href="profilo_view.php" class="flex items-center gap-3 bg-slate-800/50 hover:bg-slate-800 border border-white/5 px-3 py-1.5 rounded-full transition-all group shadow-sm active:scale-95">
                    <?php 
                        $foto_db = $user_data['foto'] ?? '';
                        $path_foto = (!empty($foto_db) && file_exists("uploads/profili/" . $foto_db)) ? "uploads/profili/" . $foto_db : "https://ui-avatars.com/api/?name=" . urlencode(explode(' ', $user_data['nome'] ?? 'User')[0]) . "&background=0891b2&color=fff";
                    ?>
                    <img src="<?= htmlspecialchars($path_foto) ?>" class="w-8 h-8 rounded-full object-cover border border-cyan-500/50" alt="Profilo">
                    <div class="hidden md:block text-left">
                        <p class="text-xs font-bold text-white leading-tight"><?= htmlspecialchars(explode(' ', $user_data['nome'] ?? 'User')[0]) ?></p>
                        <p class="text-[9px] text-cyan-400 font-bold uppercase tracking-widest leading-tight"><?= is_admin() ? 'Admin' : 'Utente' ?></p>
                    </div>
                </a>

                <a href="logout.php" class="w-10 h-10 flex items-center justify-center rounded-full bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white border border-red-500/20 transition-all shadow-sm active:scale-95" title="Esci">
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6">

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="glass-panel rounded-[2rem] p-6 border-t border-white/10 hover:-translate-y-1 transition-transform relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-10 group-hover:scale-110 transition-transform">📚</div>
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-1">Totale Libri</p>
                <p id="statTotaleLibri" class="text-4xl font-black text-white drop-shadow-md"><?php echo (int)$stats['totale_libri']; ?></p>
            </div>
            <div class="glass-panel rounded-[2rem] p-6 border-t border-white/10 hover:-translate-y-1 transition-transform relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-10 group-hover:scale-110 transition-transform">🏷️</div>
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-1">Generi</p>
                <p id="statTotaleGeneri" class="text-4xl font-black text-white drop-shadow-md"><?php echo (int)$stats['totale_generi']; ?></p>
            </div>
            <div class="glass-panel rounded-[2rem] p-6 border-t border-white/10 hover:-translate-y-1 transition-transform relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-10 group-hover:scale-110 transition-transform">💶</div>
                <p class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-1">Prezzo Medio</p>
                <p id="statPrezzoMedio" class="text-4xl font-black text-cyan-400 drop-shadow-md">€<?php echo number_format((float)$stats['prezzo_medio'], 2, ',', '.'); ?></p>
            </div>
            <div class="glass-panel rounded-[2rem] p-6 border-t border-cyan-500/30 bg-cyan-900/10 hover:-translate-y-1 transition-transform relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-6xl opacity-10 group-hover:scale-110 transition-transform">💎</div>
                <p class="text-[10px] font-black uppercase text-cyan-500 tracking-widest mb-1">Il più prezioso</p>
                <p id="statPrezzoMassimo" class="text-3xl font-black text-white drop-shadow-md mb-1">€<?php echo number_format((float)($topLibro['prezzo'] ?? 0), 2, ',', '.'); ?></p>
                <p id="statLibroCostoso" class="text-xs font-bold text-slate-400 truncate"><?php echo htmlspecialchars($topLibro['titolo'] ?? 'Nessuno'); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="glass-panel p-8 rounded-[2.5rem] relative overflow-hidden group border border-white/5 <?= is_admin() ? 'lg:col-span-1' : 'lg:col-span-3' ?>">
                <div class="absolute -right-4 -bottom-4 text-8xl opacity-5 transition-transform group-hover:scale-110">💬</div>
                <h3 class="text-2xl font-black text-white tracking-tight mb-2">Supporto & Bug</h3>
                <p class="text-slate-400 text-sm font-medium mb-6">Migliora la libreria segnalando problemi. Guadagni <span class="font-bold text-yellow-500 bg-yellow-500/10 px-2 py-0.5 rounded">+10 XP</span>!</p>
                <a href="segnala.php" class="inline-flex items-center gap-2 bg-gradient-to-r from-cyan-600 to-cyan-500 hover:from-cyan-500 hover:to-cyan-400 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest transition-all shadow-lg shadow-cyan-500/20 active:scale-95">
                    Invia Ticket →
                </a>
            </div>

            <?php if (is_admin()): ?>
            <div class="lg:col-span-2 glass-panel p-8 rounded-[2.5rem] relative overflow-hidden group border border-fuchsia-500/20 bg-fuchsia-900/5">
                <div class="absolute -right-4 -bottom-4 text-8xl opacity-5 transition-transform group-hover:scale-110">🛡️</div>
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <h3 class="text-2xl font-black text-white tracking-tight">Centro Operativo Admin</h3>
                    <?php if($count_aperte > 0): ?>
                        <span class="bg-red-500/20 border border-red-500 text-red-400 text-[10px] font-black px-3 py-1.5 rounded-full animate-pulse uppercase tracking-widest">
                            🚨 <?= $count_aperte ?> Ticket Aperti
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="flex flex-wrap gap-3 relative z-10">
                    <a href="admin_segnalazioni.php" class="flex-1 min-w-[140px] text-center bg-slate-800 hover:bg-slate-700 text-white px-4 py-4 rounded-2xl font-bold text-xs uppercase tracking-widest transition-colors shadow border border-white/5">
                        📩 Gestisci Ticket
                    </a>
                    <a href="gestione_utenti.php" class="flex-1 min-w-[140px] text-center bg-slate-800 hover:bg-slate-700 text-white px-4 py-4 rounded-2xl font-bold text-xs uppercase tracking-widest transition-colors shadow border border-white/5">
                        👥 Utenti
                    </a>
                    <a href="prestiti.php" class="flex-1 min-w-[140px] text-center bg-slate-800 hover:bg-slate-700 text-white px-4 py-4 rounded-2xl font-bold text-xs uppercase tracking-widest transition-colors shadow border border-white/5">
                        ⏳ Prestiti
                    </a>
                </div>
                
                <div class="mt-4 pt-4 border-t border-white/5 flex flex-wrap gap-2 relative z-10">
                    <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest mr-2 self-center">Export:</span>
                    <a href="export_csv.php" class="bg-white/5 hover:bg-white/10 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition border border-white/5">CSV</a>
                    <a href="export_excel.php" class="bg-white/5 hover:bg-white/10 text-emerald-400 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition border border-emerald-500/30">Excel</a>
                    <a href="export_pdf.php" target="_blank" class="bg-white/5 hover:bg-white/10 text-red-400 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest transition border border-red-500/30">PDF</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="glass-panel p-4 rounded-[2rem] mb-8 flex flex-col xl:flex-row gap-4 items-center justify-between shadow-xl z-20 relative">
            
            <div class="flex flex-wrap gap-2 w-full xl:w-auto">
                <?php if (is_admin()): ?>
                    <a href="inserisci_libro.php" class="bg-gradient-to-r from-emerald-500 to-green-500 hover:scale-105 text-white font-black px-5 py-3 rounded-xl shadow-lg shadow-green-500/20 transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                        <span>+</span> Nuovo Libro
                    </a>
                <?php endif; ?>
                <a href="lista_desideri.php" class="bg-slate-800 hover:bg-rose-500/20 hover:border-rose-500/50 hover:text-rose-400 text-white border border-white/5 font-bold px-4 py-3 rounded-xl transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                    ❤️ Desideri
                </a>
                <a href="mappa.php" class="bg-slate-800 hover:bg-indigo-500/20 hover:border-indigo-500/50 hover:text-indigo-400 text-white border border-white/5 font-bold px-4 py-3 rounded-xl transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                    🗺️ Mappa
                </a>
                <a href="classifica.php" class="bg-gradient-to-r from-yellow-500 to-amber-500 hover:scale-105 text-white font-black px-5 py-3 rounded-xl shadow-lg shadow-yellow-500/20 transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                    <span>🏆</span> Classifica
                </a>
                <button id="switchViewBtn" class="bg-slate-800 hover:bg-slate-700 text-slate-300 border border-white/5 font-bold px-4 py-3 rounded-xl transition-all text-xs uppercase tracking-widest flex items-center gap-2">
                    <span>☰</span> Tabella
                </button>
            </div>

            <form id="searchForm" class="flex flex-col sm:flex-row gap-2 w-full xl:w-auto flex-1 xl:max-w-3xl">
                <select id="searchField" class="glass-input px-4 py-3 rounded-xl text-sm outline-none w-full sm:w-32 appearance-none cursor-pointer font-medium">
                    <option value="titolo" class="bg-slate-900">Titolo</option>
                    <option value="autore" class="bg-slate-900">Autore</option>
                    <option value="genere" class="bg-slate-900">Genere</option>
                    <option value="isbn" class="bg-slate-900">ISBN</option>
                    <option value="anno_pubblicazione" class="bg-slate-900">Anno</option>
                </select>
                
                <div class="relative flex-1">
                    <input type="text" id="searchInput" placeholder="Cerca nel catalogo..." autocomplete="off" class="w-full glass-input px-4 py-3 rounded-xl text-sm outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 transition-all font-medium placeholder:text-slate-500">
                </div>
                
                <select id="genreFilter" class="glass-input px-4 py-3 rounded-xl text-sm outline-none w-full sm:w-36 appearance-none cursor-pointer font-medium">
                    <option value="" class="bg-slate-900">Tutti i generi</option>
                    <?php foreach ($generi as $genere): ?>
                        <option value="<?php echo htmlspecialchars($genere); ?>" class="bg-slate-900"><?php echo htmlspecialchars($genere); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="sortField" class="glass-input px-4 py-3 rounded-xl text-sm outline-none w-full sm:w-40 appearance-none cursor-pointer font-medium">
                    <option value="titolo_asc" class="bg-slate-900">A - Z</option>
                    <option value="titolo_desc" class="bg-slate-900">Z - A</option>
                    <option value="prezzo_asc" class="bg-slate-900">Prezzo Min</option>
                    <option value="prezzo_desc" class="bg-slate-900">Prezzo Max</option>
                    <option value="anno_desc" class="bg-slate-900">Più Recenti</option>
                    <option value="anno_asc" class="bg-slate-900">Più Vecchi</option>
                </select>
                
                <div class="flex gap-2 w-full sm:w-auto">
                    <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white px-5 py-3 rounded-xl shadow-lg font-black text-xs uppercase tracking-widest transition flex-1 sm:flex-none">🔍</button>
                    <button type="button" id="resetSearch" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-3 rounded-xl shadow-lg font-black text-xs uppercase tracking-widest transition" title="Reset">✖</button>
                    <a href="scanner_isbn.php" class="bg-gradient-to-r from-emerald-500 to-teal-500 hover:scale-105 text-white px-4 py-3 rounded-xl shadow-lg shadow-emerald-500/20 font-black text-lg transition flex items-center justify-center" title="Ricerca Smart (Voce / Codice a Barre)">📷</a>
                </div>
            </form>
        </div>

        <div id="searchInfo" class="mb-4 text-xs font-bold text-cyan-400 uppercase tracking-widest hidden ml-2"></div>

        <div id="cardsView" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-6"></div>

        <div id="tableView" class="hidden">
            <div class="overflow-x-auto glass-panel rounded-[2rem] shadow-xl">
                <table class="min-w-full divide-y divide-white/5">
                    <thead class="bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Libro</th>
                            <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Dettagli</th>
                            <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Rating</th>
                            <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Prezzo</th>
                            <th class="px-6 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-white/5"></tbody>
                </table>
            </div>
        </div>

        <div id="paginationWrapper" class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-900/50 p-4 rounded-3xl border border-white/5">
            <div id="paginationInfo" class="text-xs font-bold text-slate-400 uppercase tracking-widest pl-2"></div>
            <div id="paginationControls" class="flex flex-wrap items-center gap-2"></div>
        </div>

        <?php if (is_admin()): ?>
        <div class="glass-panel p-8 rounded-[2.5rem] mt-12 border border-yellow-500/20">
            <div class="flex items-center gap-3 mb-6">
                <span class="text-2xl">📜</span>
                <div>
                    <h3 class="text-xl font-black text-white">Log di Sistema</h3>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Ultime attività registrate</p>
                </div>
            </div>
            
            <?php if (!empty($attivitaRecenti)): ?>
                <div class="space-y-3">
                    <?php foreach ($attivitaRecenti as $attivita): ?>
                        <div class="bg-slate-900/50 border border-white/5 p-4 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-xs font-black text-slate-400 border border-white/5">
                                    <?= substr($attivita['utente'], 0, 2) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($attivita['descrizione']); ?></p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-cyan-500 bg-cyan-900/30 px-2 py-0.5 rounded"><?php echo htmlspecialchars($attivita['azione']); ?></span>
                                        <span class="text-[9px] font-bold uppercase tracking-widest text-slate-500"><?php echo htmlspecialchars($attivita['oggetto']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right whitespace-nowrap">
                                <?php echo date("d/m/Y H:i", strtotime($attivita['data_operazione'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-slate-500 font-bold text-sm italic">Nessuna attività registrata al momento.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <div id="confirmDeleteModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center">
        <div id="modalOverlay" class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
        <div class="relative z-[110] glass-panel w-full max-w-md p-8 rounded-[2.5rem] border border-red-500/30 text-center shadow-2xl">
            <div class="w-20 h-20 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center mx-auto mb-6 text-4xl border border-red-500/30">🗑️</div>
            <h3 class="text-2xl font-black text-white mb-2">Elimina Libro</h3>
            <p class="text-sm text-slate-400 mb-6 font-medium">Sei sicuro di voler rimuovere <strong id="libroDaEliminare" class="text-white"></strong> dal catalogo? L'azione è irreversibile.</p>
            <div class="flex gap-3">
                <button id="annullaBtn" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3.5 rounded-2xl text-xs uppercase tracking-widest transition-all">Annulla</button>
                <button id="btnConfermaElimina" class="flex-1 bg-red-600 hover:bg-red-500 text-white font-bold py-3.5 rounded-2xl text-xs uppercase tracking-widest shadow-lg shadow-red-600/30 transition-all active:scale-95">Elimina</button>
            </div>
        </div>
    </div>

    <div id="eliminaToast" class="hidden fixed top-24 right-5 z-[100] bg-emerald-600/90 backdrop-blur-md border border-emerald-400 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 font-bold text-sm animate-bounce">
        <span class="text-xl">✅</span>
        <span id="eliminaToastBody">Operazione completata!</span>
    </div>

    <div id="aiChatWidget" class="fixed bottom-6 right-6 z-50">
        <button id="openAiChat" class="group flex items-center gap-3 rounded-full bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white px-5 py-4 shadow-lg shadow-cyan-500/30 transition-all hover:scale-105 border border-white/10 active:scale-95">
            <span class="text-2xl">🤖</span>
            <div class="text-left hidden sm:block">
                <div class="font-black text-xs uppercase tracking-widest leading-none mb-1">Nexus AI</div>
                <div class="text-[9px] text-cyan-200 uppercase tracking-widest leading-none">Chiedi all'oracolo</div>
            </div>
        </button>
    </div>

    <div id="aiChatOverlay" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[80]"></div>

    <div id="aiChatPanel" class="hidden fixed bottom-24 right-6 w-full max-w-[400px] h-[650px] max-h-[80vh] rounded-[2.5rem] glass-panel border border-cyan-500/30 shadow-2xl overflow-hidden z-[90] opacity-0 translate-y-4 scale-95 transition-all duration-300 flex flex-col" style="left:auto; top:auto;">
        
        <div id="aiChatDragHandle" class="bg-slate-900/80 border-b border-white/5 px-6 py-5 cursor-move select-none flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-xl shadow-lg shadow-cyan-500/20">🤖</div>
                <div>
                    <h3 class="font-black text-white text-sm uppercase tracking-widest">Nexus AI</h3>
                    <p class="text-[9px] text-cyan-400 font-bold uppercase tracking-widest">Assistente Libreria</p>
                </div>
            </div>
            <button id="closeAiChat" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-slate-400 hover:text-white transition-colors">✕</button>
        </div>

        <div class="bg-slate-900/50 border-b border-white/5 px-4 py-3 flex gap-2 overflow-x-auto no-scrollbar">
            <button class="quickPrompt shrink-0 px-3 py-1.5 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/20 text-[10px] font-black text-cyan-400 uppercase tracking-widest transition" data-prompt="Quanti prestiti attivi ci sono?">Prestiti</button>
            <button class="quickPrompt shrink-0 px-3 py-1.5 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/20 text-[10px] font-black text-cyan-400 uppercase tracking-widest transition" data-prompt="Fammi un riassunto della biblioteca">Riassunto</button>
            <button class="quickPrompt shrink-0 px-3 py-1.5 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/20 text-[10px] font-black text-cyan-400 uppercase tracking-widest transition" data-prompt="Ci sono prestiti in ritardo?">Ritardi</button>
        </div>

        <div id="aiMessages" class="flex-1 overflow-y-auto px-5 py-6 space-y-5 bg-black/20"></div>

        <form id="aiChatForm" class="border-t border-white/5 bg-slate-900/80 p-4">
            <?php echo csrf_input(); ?>
            <div class="flex items-end gap-2">
                <textarea id="aiPrompt" rows="1" placeholder="Scrivi un messaggio..." class="flex-1 resize-none overflow-hidden px-4 py-3 rounded-xl border border-white/10 bg-slate-800 text-white text-sm focus:outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500 min-h-[44px] max-h-24"></textarea>
                <button type="submit" id="sendAiMessage" class="h-[44px] w-[44px] shrink-0 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white shadow-lg shadow-cyan-500/20 transition flex items-center justify-center text-lg active:scale-95">➤</button>
            </div>
        </form>
    </div>

<script>
// ==========================================
// FUNZIONE FIX IMMAGINI (Aggiunta per gestire i percorsi in modo intelligente)
// ==========================================
function getImageUrl(imgStr) {
    if (!imgStr || String(imgStr).trim() === '') return 'uploads/placeholder.png';
    const img = String(imgStr).trim();
    if (img.startsWith('http://') || img.startsWith('https://')) return img;
    if (img.startsWith('uploads/')) return img;
    return 'uploads/' + img;
}

// ==========================================
// LOGICA CHAT IA
// ==========================================
const openAiChat = document.getElementById('openAiChat');
const closeAiChat = document.getElementById('closeAiChat');
const aiChatPanel = document.getElementById('aiChatPanel');
const aiChatOverlay = document.getElementById('aiChatOverlay');
const aiMessages = document.getElementById('aiMessages');
const aiChatForm = document.getElementById('aiChatForm');
const aiPrompt = document.getElementById('aiPrompt');
const sendAiMessage = document.getElementById('sendAiMessage');
const aiChatDragHandle = document.getElementById('aiChatDragHandle');

let isDraggingChat = false;
let chatDragOffsetX = 0;
let chatDragOffsetY = 0;
let chatStarted = false;

function enableChatDragging() {
    if (!aiChatPanel || !aiChatDragHandle) return;
    const startDrag = (clientX, clientY) => {
        const rect = aiChatPanel.getBoundingClientRect();
        isDraggingChat = true;
        chatDragOffsetX = clientX - rect.left;
        chatDragOffsetY = clientY - rect.top;
        aiChatPanel.classList.remove('transition-all', 'duration-300', 'translate-y-4', 'scale-95');
        aiChatPanel.style.right = 'auto';
        aiChatPanel.style.bottom = 'auto';
        aiChatPanel.style.left = rect.left + 'px';
        aiChatPanel.style.top = rect.top + 'px';
    };
    const moveDrag = (clientX, clientY) => {
        if (!isDraggingChat) return;
        const panelWidth = aiChatPanel.offsetWidth;
        const panelHeight = aiChatPanel.offsetHeight;
        const maxLeft = window.innerWidth - panelWidth - 8;
        const maxTop = window.innerHeight - panelHeight - 8;
        let newLeft = Math.max(8, Math.min(clientX - chatDragOffsetX, maxLeft));
        let newTop = Math.max(8, Math.min(clientY - chatDragOffsetY, maxTop));
        aiChatPanel.style.left = newLeft + 'px';
        aiChatPanel.style.top = newTop + 'px';
    };
    const endDrag = () => { isDraggingChat = false; };

    aiChatDragHandle.addEventListener('mousedown', (e) => { if (!e.target.closest('#closeAiChat')) startDrag(e.clientX, e.clientY); });
    document.addEventListener('mousemove', (e) => moveDrag(e.clientX, e.clientY));
    document.addEventListener('mouseup', endDrag);
}

function openChat() {
    if (!aiChatPanel || !aiChatOverlay) return;
    aiChatOverlay.classList.remove('hidden');
    aiChatPanel.classList.remove('hidden');
    if ((!aiChatPanel.style.left || aiChatPanel.style.left === 'auto') && (!aiChatPanel.style.top || aiChatPanel.style.top === 'auto')) {
        aiChatPanel.style.right = '24px';
        aiChatPanel.style.bottom = '96px';
    }
    requestAnimationFrame(() => aiChatPanel.classList.remove('opacity-0', 'translate-y-4', 'scale-95'));
    if (!chatStarted) {
        addBotMessage("Ciao! Sono l'IA di Nexus Library. Chiedimi pure un riassunto dei libri, i prestiti attivi o chi è in ritardo!");
        chatStarted = true;
    }
    setTimeout(() => { aiPrompt.focus(); autoResizeTextarea(); }, 180);
}

function closeChat() {
    aiChatPanel.classList.add('opacity-0', 'translate-y-4', 'scale-95');
    setTimeout(() => { aiChatPanel.classList.add('hidden'); aiChatOverlay.classList.add('hidden'); }, 300);
}

if (openAiChat) openAiChat.addEventListener('click', openChat);
if (closeAiChat) closeAiChat.addEventListener('click', closeChat);
if (aiChatOverlay) aiChatOverlay.addEventListener('click', closeChat);

document.querySelectorAll('.quickPrompt').forEach(btn => {
    btn.addEventListener('click', () => { aiPrompt.value = btn.dataset.prompt; autoResizeTextarea(); aiChatForm.requestSubmit(); });
});

function formatMessage(text) { return text.replace(/\n/g, '<br>'); }
function scrollChat() { aiMessages.scrollTop = aiMessages.scrollHeight; }
function autoResizeTextarea() { aiPrompt.style.height = '44px'; aiPrompt.style.height = Math.min(aiPrompt.scrollHeight, 100) + 'px'; }

function renderBooksCards(items) {
    if (!Array.isArray(items) || !items.length) return '';
    return `<div class="mt-3 space-y-2">` + items.map(item => `
        <div class="bg-slate-800 border border-white/5 p-2 rounded-xl flex gap-3 items-center">
            <div class="w-8 h-12 bg-slate-900 rounded bg-cover bg-center shrink-0" style="background-image:url('${getImageUrl(item.immagine)}')"></div>
            <div class="text-xs">
                <div class="font-bold text-white">${item.titolo || ''}</div>
                <div class="text-slate-400">${item.autore || ''}</div>
            </div>
        </div>`).join('') + `</div>`;
}

function renderLoansCards(items) {
    if (!Array.isArray(items) || !items.length) return '';
    return `<div class="mt-3 space-y-2">` + items.map(item => `
        <div class="bg-red-900/20 border border-red-500/30 p-2 rounded-xl text-xs">
            <div class="font-bold text-red-400">${item.titolo || ''}</div>
            <div class="text-slate-300">Utente: ${item.cliente || ''}</div>
            <div class="font-bold text-white">Scade: ${item.scadenza || ''}</div>
        </div>`).join('') + `</div>`;
}

function addUserMessage(text) {
    aiMessages.innerHTML += `
        <div class="flex justify-end ai-message-enter mb-4">
            <div class="max-w-[85%]">
                <div class="mb-1 text-right text-[9px] uppercase tracking-widest text-slate-500 font-bold">Tu</div>
                <div class="rounded-[1.2rem] rounded-br-sm bg-cyan-600 text-white px-4 py-3 shadow-md text-sm leading-relaxed border border-cyan-500">
                    ${formatMessage(text)}
                </div>
            </div>
        </div>`;
    scrollChat();
}

function addBotMessage(text, type = 'normal', extra = null) {
    let style = type === 'error' ? 'bg-red-900/30 border-red-500/30 text-red-200' : 'bg-slate-800 border-white/10 text-slate-200';
    let icon = type === 'error' ? '⚠️' : '🤖';
    let extraHtml = (extra && extra.view === 'books') ? renderBooksCards(extra.items) : ((extra && extra.view === 'loans') ? renderLoansCards(extra.items) : '');

    aiMessages.innerHTML += `
        <div class="flex justify-start ai-message-enter mb-4">
            <div class="max-w-[90%] flex items-start gap-2">
                <div class="mt-1 w-8 h-8 shrink-0 rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 text-white flex items-center justify-center shadow-lg text-sm">${icon}</div>
                <div>
                    <div class="mb-1 text-[9px] uppercase tracking-widest text-slate-500 font-bold">Nexus AI</div>
                    <div class="rounded-[1.2rem] rounded-bl-sm border px-4 py-3 shadow-md text-sm leading-relaxed ${style}">
                        ${formatMessage(text)}
                        ${extraHtml}
                    </div>
                </div>
            </div>
        </div>`;
    scrollChat();
}

function addTyping() {
    aiMessages.innerHTML += `
        <div id="aiTyping" class="flex justify-start ai-message-enter mb-4">
            <div class="flex items-start gap-2">
                <div class="mt-1 w-8 h-8 rounded-full bg-slate-800 border border-white/5 flex items-center justify-center text-sm opacity-50">🤖</div>
                <div class="rounded-[1.2rem] rounded-bl-sm border border-white/5 bg-slate-800 px-4 py-3">
                    <div class="flex gap-1"><span class="ai-typing-dot w-2 h-2 rounded-full bg-cyan-500"></span><span class="ai-typing-dot w-2 h-2 rounded-full bg-cyan-500"></span><span class="ai-typing-dot w-2 h-2 rounded-full bg-cyan-500"></span></div>
                </div>
            </div>
        </div>`;
    scrollChat();
}
function removeTyping() { const el = document.getElementById('aiTyping'); if (el) el.remove(); }

if (aiChatForm) {
    aiChatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const prompt = aiPrompt.value.trim();
        if (!prompt) return;
        addUserMessage(prompt);
        aiPrompt.value = ''; autoResizeTextarea();
        addTyping();
        sendAiMessage.disabled = true; sendAiMessage.classList.add('opacity-50');
        try {
            const fd = new FormData(); fd.append('prompt', prompt);
            const res = await fetch('api_chat_ia.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            const data = await res.json();
            removeTyping();
            if (!data.success) addBotMessage(data.message || 'Errore.', 'error');
            else addBotMessage(data.reply, 'normal', data);
        } catch (err) { removeTyping(); addBotMessage("Errore server.", 'error'); } 
        finally { sendAiMessage.disabled = false; sendAiMessage.classList.remove('opacity-50'); }
    });
}


// ==========================================
// LOGICA LISTA LIBRI E RENDER (Javascript)
// ==========================================
const initialBooks = <?php echo json_encode($libri, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const initialTotalBooks = <?php echo (int)$totaleLibri; ?>;
const initialStats = <?php echo json_encode([
    'totale_libri' => (int)$stats['totale_libri'],
    'totale_generi' => (int)$stats['totale_generi'],
    'prezzo_medio' => (float)$stats['prezzo_medio'],
    'prezzo_massimo' => (float)($topLibro['prezzo'] ?? 0),
    'libro_piu_costoso' => $topLibro['titolo'] ?? 'Nessuno'
], JSON_UNESCAPED_UNICODE); ?>;

const userRole = <?php echo json_encode(is_admin() ? 'admin' : 'utente'); ?>;
const csrfToken = <?php echo json_encode(function_exists('csrf_token') ? csrf_token() : ''); ?>;
const booksPerPage = 10;

let libroIdDaEliminare = null;
let allBooks = [...initialBooks];
let currentPage = 1;
let currentSearch = ''; let currentField = 'titolo'; let currentSort = 'titolo_asc'; let currentGenre = '';
let totalBooks = initialTotalBooks;
let totalPages = Math.max(1, Math.ceil(totalBooks / booksPerPage));

const modal = document.getElementById('confirmDeleteModal');
const cardsView = document.getElementById('cardsView');
const tableView = document.getElementById('tableView');
const tableBody = document.getElementById('tableBody');
const searchForm = document.getElementById('searchForm');

function escapeHtml(text) { if (text == null) return ''; return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
function formatEuro(val) { return '€ ' + Number(val||0).toLocaleString('it-IT', {minimumFractionDigits:2}); }

function getAdminActions(id, titolo) {
    if (userRole !== 'admin') return '';
    return `
        <a href='aggiorna_libro.php?id=${id}' class='w-8 h-8 bg-slate-800 hover:bg-yellow-500 rounded-full flex items-center justify-center transition-colors shadow-lg border border-white/5 text-sm' title='Modifica'>✏️</a>
        <button class='w-8 h-8 bg-slate-800 hover:bg-red-500 rounded-full flex items-center justify-center transition-colors shadow-lg border border-white/5 text-sm deleteBtn' data-id='${id}' data-titolo="${titolo}" title='Elimina'>🗑️</button>
    `;
}

function stelleHtml(media, num) {
    if (!media || media == 0) return '<span class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">Nessun Voto</span>';
    let h = '<span class="flex items-center gap-0.5 text-sm">';
    for (let i = 1; i <= 5; i++) { h += `<span class="${i <= Math.round(media) ? 'text-yellow-400 drop-shadow-[0_0_3px_rgba(250,204,21,0.5)]' : 'text-slate-700'}">★</span>`; }
    h += `<span class="text-[10px] text-slate-400 font-bold ml-2 tracking-widest">${Number(media).toFixed(1)} (${num})</span></span>`;
    return h;
}

const wishlistCache = new Set(<?php echo json_encode($wishlistIds); ?>.map(String));

async function toggleWishlist(idLibro, btn) {
    const fd = new FormData(); fd.append('action', 'toggle'); fd.append('id_libro', idLibro); fd.append('csrf_token', csrfToken);
    btn.disabled = true; btn.classList.add('animate-pulse');
    try {
        const res = await fetch('api_lista_desideri.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            if (data.in_lista) { wishlistCache.add(String(idLibro)); btn.innerHTML = '❤️'; btn.classList.add('text-rose-500'); btn.classList.remove('text-slate-600'); } 
            else { wishlistCache.delete(String(idLibro)); btn.innerHTML = '🤍'; btn.classList.remove('text-rose-500'); btn.classList.add('text-slate-600'); }
        }
    } catch(e) { console.error(e); } finally { btn.disabled = false; btn.classList.remove('animate-pulse'); }
}

function renderBooks(books) {
    cardsView.innerHTML = ''; tableBody.innerHTML = '';
    if (!books.length) {
        cardsView.innerHTML = `<div class='col-span-full glass-panel p-10 text-center rounded-[2.5rem]'><span class='text-4xl opacity-50'>📭</span><p class='text-slate-400 font-bold mt-4 uppercase tracking-widest text-sm'>Nessun libro trovato.</p></div>`;
        return;
    }

    books.forEach(row => {
        const titolo = escapeHtml(row.titolo); const autore = escapeHtml(row.autore); const genere = row.genere || 'Varie'; const isbn = row.isbn || 'N/A';
        const img = getImageUrl(row.immagine);
        const inWish = wishlistCache.has(String(row.id));

        // CARD (Glassmorphism + Hover)
        cardsView.innerHTML += `
            <div class='glass-panel p-5 rounded-[2rem] shadow-xl hover:-translate-y-1 hover:shadow-cyan-500/10 transition-all border border-white/5 group relative overflow-hidden flex flex-col md:flex-row gap-5'>
                <div class='absolute inset-0 bg-gradient-to-r from-cyan-500/0 to-blue-500/0 group-hover:from-cyan-500/5 group-hover:to-blue-500/5 transition-colors pointer-events-none'></div>
                
                <div class='shrink-0 relative w-32 mx-auto md:mx-0'>
                    <a href='dettaglio_libro.php?id=${row.id}' class="block relative rounded-2xl overflow-hidden shadow-2xl group-hover:scale-[1.02] transition-transform z-10">
                        <div class="absolute inset-0 bg-cyan-500/20 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <img src='${img}' class='w-full h-48 object-cover border border-white/10'>
                    </a>
                    <button class='wish-btn absolute -top-3 -right-3 w-10 h-10 bg-slate-900 rounded-full flex items-center justify-center border border-white/10 shadow-lg hover:scale-110 transition-transform text-lg z-20 ${inWish ? "text-rose-500" : "text-slate-600"}' data-id='${row.id}'>
                        ${inWish ? "❤️" : "🤍"}
                    </button>
                </div>
                
                <div class='flex-1 flex flex-col relative z-10'>
                    <div class="flex justify-between items-start gap-2">
                        <a href='dettaglio_libro.php?id=${row.id}' class='text-xl font-black text-white hover:text-cyan-400 transition leading-tight line-clamp-2'>${titolo}</a>
                    </div>
                    <p class='text-sm text-cyan-400 font-bold mb-2'>${autore}</p>
                    
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="bg-slate-900 border border-white/5 text-slate-300 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest">${genere}</span>
                        <span class="bg-slate-900 border border-white/5 text-slate-400 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest opacity-60">ISBN: ${isbn}</span>
                    </div>
                    
                    <div class='mb-auto'>${stelleHtml(row.media_voti, row.num_recensioni)}</div>
                    
                    <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                        <span class="text-xl font-black text-emerald-400 drop-shadow-md">${formatEuro(row.prezzo)}</span>
                        <div class='flex gap-2'>${getAdminActions(row.id, titolo)}</div>
                    </div>
                </div>
            </div>`;

        // TABELLA (Dark style)
        tableBody.innerHTML += `
            <tr class='hover:bg-white/5 transition-colors group'>
                <td class='px-6 py-4'>
                    <a href='dettaglio_libro.php?id=${row.id}'><img src='${img}' class='w-12 h-16 object-cover rounded-lg border border-white/10 group-hover:scale-110 transition-transform shadow-md'></a>
                </td>
                <td class='px-6 py-4 min-w-[200px]'>
                    <a href='dettaglio_libro.php?id=${row.id}' class='text-sm font-black text-white hover:text-cyan-400 block mb-1'>${titolo}</a>
                    <span class='text-xs text-cyan-400 font-bold'>${autore}</span>
                    <div class='text-[10px] text-slate-500 mt-1 uppercase tracking-widest'>${genere} • ${row.anno_pubblicazione||'-'}</div>
                </td>
                <td class='px-6 py-4 whitespace-nowrap'>${stelleHtml(row.media_voti, row.num_recensioni)}</td>
                <td class='px-6 py-4 text-emerald-400 font-black whitespace-nowrap'>${formatEuro(row.prezzo)}</td>
                <td class='px-6 py-4 text-right whitespace-nowrap'>
                    <div class='flex gap-2 justify-end items-center'>
                        <button class='wish-btn text-xl hover:scale-110 transition ${inWish ? "text-rose-500" : "text-slate-600"}' data-id='${row.id}'>${inWish ? "❤️" : "🤍"}</button>
                        ${getAdminActions(row.id, titolo)}
                    </div>
                </td>
            </tr>`;
    });

    document.querySelectorAll('.deleteBtn').forEach(b => b.addEventListener('click', function() {
        libroIdDaEliminare = this.dataset.id;
        document.getElementById('libroDaEliminare').textContent = this.dataset.titolo;
        document.getElementById('confirmDeleteModal').classList.remove('hidden');
    }));
    document.querySelectorAll('.wish-btn').forEach(b => b.onclick = function() { toggleWishlist(this.dataset.id, this); });
}

// LOGICA RICERCA, PAGINAZIONE, STATS (Invariata come nel tuo codice)
function updateStats(stats) {
    document.getElementById('statTotaleLibri').textContent = stats.totale_libri;
    document.getElementById('statTotaleGeneri').textContent = stats.totale_generi;
    document.getElementById('statPrezzoMedio').textContent = formatEuro(stats.prezzo_medio);
    if(stats.prezzo_massimo) {
        document.getElementById('statPrezzoMassimo').textContent = formatEuro(stats.prezzo_massimo);
        if(stats.libro_piu_costoso) document.getElementById('statLibroCostoso').textContent = stats.libro_piu_costoso;
    }
}

function fetchBooks(search='', field='titolo', sort='titolo_asc', page=1, genre='') {
    const url = `filtra_libri.php?search=${encodeURIComponent(search)}&field=${encodeURIComponent(field)}&sort=${encodeURIComponent(sort)}&page=${page}&limit=${booksPerPage}&genere=${encodeURIComponent(genre)}`;
    fetch(url).then(r => r.json()).then(data => {
        allBooks = data.libri || []; totalBooks = parseInt(data.totale||0); currentPage = page; totalPages = Math.max(1, Math.ceil(totalBooks/booksPerPage));
        currentSearch = search; currentField = field; currentSort = sort; currentGenre = genre;
        renderBooks(allBooks); renderPagination(); updateStats(data.stats || {});
        
        let info = document.getElementById('searchInfo');
        if(search || genre || sort!=='titolo_asc') {
            info.textContent = `Trovati ${totalBooks} risultati`; info.classList.remove('hidden');
        } else info.classList.add('hidden');
    });
}

function renderPagination() {
    const wrap = document.getElementById('paginationControls'); wrap.innerHTML = '';
    const info = document.getElementById('paginationInfo');
    if (totalPages <= 1) { info.textContent = totalBooks>0 ? `${totalBooks} libri nel catalogo` : ''; return; }
    
    info.textContent = `Pagina ${currentPage} di ${totalPages}`;
    
    let btnClass = 'w-8 h-8 rounded-lg bg-slate-800 border border-white/5 text-slate-400 font-bold flex items-center justify-center hover:bg-slate-700 hover:text-white transition disabled:opacity-30';
    
    let prev = document.createElement('button'); prev.className=btnClass; prev.innerHTML='&larr;'; prev.disabled = currentPage===1;
    prev.onclick = () => fetchBooks(currentSearch, currentField, currentSort, currentPage-1, currentGenre);
    wrap.appendChild(prev);

    for(let i=Math.max(1, currentPage-2); i<=Math.min(totalPages, Math.max(1, currentPage-2)+4); i++) {
        let p = document.createElement('button'); p.textContent = i;
        p.className = i===currentPage ? 'w-8 h-8 rounded-lg bg-cyan-600 text-white font-black shadow-lg shadow-cyan-500/30 flex items-center justify-center' : btnClass;
        p.onclick = () => fetchBooks(currentSearch, currentField, currentSort, i, currentGenre);
        wrap.appendChild(p);
    }

    let next = document.createElement('button'); next.className=btnClass; next.innerHTML='&rarr;'; next.disabled = currentPage===totalPages;
    next.onclick = () => fetchBooks(currentSearch, currentField, currentSort, currentPage+1, currentGenre);
    wrap.appendChild(next);
}

searchForm.addEventListener('submit', (e) => { e.preventDefault(); fetchBooks(document.getElementById('searchInput').value, document.getElementById('searchField').value, document.getElementById('sortField').value, 1, document.getElementById('genreFilter').value); });
document.getElementById('resetSearch').addEventListener('click', () => { searchForm.reset(); fetchBooks(); });
document.getElementById('sortField').addEventListener('change', () => searchForm.requestSubmit());
document.getElementById('genreFilter').addEventListener('change', () => searchForm.requestSubmit());

// LOGICA MODALE E VIEW TOGGLE
document.getElementById('annullaBtn').onclick = () => document.getElementById('confirmDeleteModal').classList.add('hidden');
document.getElementById('modalOverlay').onclick = () => document.getElementById('confirmDeleteModal').classList.add('hidden');

document.getElementById('btnConfermaElimina').addEventListener('click', async function() {
    if (!libroIdDaEliminare) return;
    this.innerHTML = '...'; this.disabled = true;
    const fd = new FormData(); fd.append('id', libroIdDaEliminare); fd.append('csrf_token', csrfToken);
    try {
        const res = await fetch('elimina_libro.php', { method: 'POST', body: fd });
        if(await res.text() === 'OK') {
            document.getElementById('confirmDeleteModal').classList.add('hidden');
            let t = document.getElementById('eliminaToast'); t.classList.remove('hidden'); setTimeout(()=>t.classList.add('hidden'), 3000);
            fetchBooks(currentSearch, currentField, currentSort, currentPage, currentGenre);
        } else alert('Errore eliminazione');
    } catch(e) { alert('Errore rete'); } finally { this.innerHTML = 'Elimina'; this.disabled = false; }
});

document.getElementById('switchViewBtn').addEventListener('click', function() {
    let t = document.getElementById('tableView'); let c = document.getElementById('cardsView');
    if(t.classList.contains('hidden')) { t.classList.remove('hidden'); c.classList.add('hidden'); this.innerHTML = '<span>🃏</span> Griglia'; }
    else { t.classList.add('hidden'); c.classList.remove('hidden'); this.innerHTML = '<span>☰</span> Tabella'; }
});

enableChatDragging();
renderBooks(allBooks); renderPagination();
</script>
</body>
</html>