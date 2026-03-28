<?php
include "connessione.php";
require_admin_page($conn);

$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="it" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Prestiti | Library Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script>
        tailwind.config = { 
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#f5f7ff', 100: '#ebf0fe', 200: '#ced9fd', 300: '#a1b6fb', 400: '#6d8bf7', 500: '#435df0', 600: '#2c3edb', 700: '#2330af', 800: '#212a8e', 900: '#1f2773', 950: '#131745' }
                    },
                    borderRadius: { '3xl': '1.5rem', '4xl': '2rem' }
                }
            }
        };
        if (localStorage.getItem('darkMode') === 'enabled') document.documentElement.classList.add('dark');
    </script>
    <style>
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }
        .dark .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); }
        .card-anim { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-anim:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1); }
        @keyframes shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 1000px 100%; animation: shimmer 2s infinite linear; }
        .dark .skeleton { background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%); background-size: 1000px 100%; }
    </style>
</head>
<body class="bg-[#f8fafc] dark:bg-slate-950 min-h-screen text-slate-900 dark:text-slate-100 font-sans">

<div class="fixed top-0 left-0 w-full h-full -z-10 overflow-hidden opacity-20 pointer-events-none">
    <div class="absolute -top-24 -left-24 w-96 h-96 bg-brand-500 rounded-full blur-[120px]"></div>
    <div class="absolute top-1/2 -right-24 w-80 h-80 bg-indigo-600 rounded-full blur-[100px]"></div>
</div>

<div class="max-w-7xl mx-auto px-6 py-10">
    <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

    <header class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12 animate__animated animate__fadeIn">
        <div>
            <nav class="flex items-center gap-2 text-sm font-medium text-brand-600 dark:text-brand-400 mb-2">
                <span>Dashboard</span>
                <span class="text-slate-400">/</span>
                <span class="text-slate-900 dark:text-white">Prestiti</span>
            </nav>
            <h1 class="text-4xl md:text-5xl font-black tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-slate-900 to-slate-600 dark:from-white dark:to-slate-400">
                Gestione Prestiti
            </h1>
        </div>

        <div class="flex gap-3">
            <a href="nuovo_prestito.php" class="group flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white px-6 py-3.5 rounded-2xl shadow-xl shadow-brand-600/20 transition-all hover:scale-105 active:scale-95">
                <svg class="w-5 h-5 transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span class="font-bold">Nuovo Prestito</span>
            </a>
        </div>
    </header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10 animate__animated animate__fadeInUp">
        <?php 
        $stats_config = [
            ['id' => 'statTotale', 'label' => 'Totali', 'color' => 'blue', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['id' => 'statAttivi', 'label' => 'Attivi', 'color' => 'amber', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['id' => 'statRestituiti', 'label' => 'Rientrati', 'color' => 'emerald', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['id' => 'statRitardo', 'label' => 'In Ritardo', 'color' => 'rose', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z']
        ];
        foreach($stats_config as $s): ?>
        <div class="glass border border-white/20 dark:border-slate-800 p-6 rounded-3xl shadow-sm overflow-hidden relative group">
            <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="<?php echo $s['icon']; ?>"/></svg>
            </div>
            <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1"><?php echo $s['label']; ?></p>
            <div id="<?php echo $s['id']; ?>" class="text-3xl font-black text-<?php echo $s['color']; ?>-600 dark:text-<?php echo $s['color']; ?>-400">0</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="glass border border-white/20 dark:border-slate-800 rounded-[2.5rem] shadow-2xl shadow-slate-200/50 dark:shadow-none overflow-hidden animate__animated animate__fadeInUp animate__delay-1s">
        <div class="p-8 border-b border-slate-200 dark:border-slate-800">
            <div class="flex flex-col xl:flex-row gap-6">
                <div class="flex-1 relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" id="searchInput" placeholder="Cerca libro, autore o cliente..." 
                           class="w-full pl-12 pr-4 py-4 rounded-2xl border-none bg-slate-100 dark:bg-slate-900 focus:ring-2 focus:ring-brand-500 transition-all outline-none text-lg">
                </div>
                <div class="flex flex-wrap md:flex-nowrap gap-4">
                    <select id="statusFilter" class="px-4 py-4 rounded-2xl bg-slate-100 dark:bg-slate-900 border-none focus:ring-2 focus:ring-brand-500 font-medium cursor-pointer">
                        <option value="">Tutti gli stati</option>
                        <option value="in_prestito">🟡 In prestito</option>
                        <option value="restituito">🟢 Restituiti</option>
                        <option value="ritardo">🔴 In ritardo</option>
                    </select>
                    <select id="sortBy" class="px-4 py-4 rounded-2xl bg-slate-100 dark:bg-slate-900 border-none focus:ring-2 focus:ring-brand-500 font-medium cursor-pointer">
                        <option value="recenti">Più recenti</option>
                        <option value="scadenza_asc">Scadenza vicina</option>
                        <option value="cliente_asc">Cliente A-Z</option>
                    </select>
                    <button id="resetFilters" class="p-4 rounded-2xl bg-slate-100 dark:bg-slate-900 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="loadingBox" class="hidden p-20">
            <div class="space-y-6">
                <div class="skeleton h-32 w-full rounded-3xl"></div>
                <div class="skeleton h-32 w-full rounded-3xl"></div>
                <div class="skeleton h-32 w-full rounded-3xl"></div>
            </div>
        </div>

        <div id="prestitiContainer" class="divide-y divide-slate-100 dark:divide-slate-800/50">
            </div>

        <div id="emptyBox" class="hidden p-20 text-center animate__animated animate__fadeIn">
            <div class="text-7xl mb-6">🏜️</div>
            <h3 class="text-2xl font-bold mb-2">Nessun prestito trovato</h3>
            <p class="text-slate-500 dark:text-slate-400">La ricerca non ha prodotto risultati. Prova a resettare i filtri.</p>
        </div>
        
        <div id="paginationContainer" class="hidden p-6 bg-slate-50/50 dark:bg-slate-900/30 flex items-center justify-between">
            <button id="prevPageBtn" class="flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 font-bold hover:bg-white dark:hover:bg-slate-800 transition-all disabled:opacity-30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg> Prec
            </button>
            <span id="pageIndicator" class="text-sm font-black uppercase tracking-tighter text-slate-400">Pagina 1 di 1</span>
            <button id="nextPageBtn" class="flex items-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 font-bold hover:bg-white dark:hover:bg-slate-800 transition-all disabled:opacity-30">
                Succ <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</div>

<div id="alertBox" class="hidden fixed bottom-10 right-10 z-[100] max-w-md animate__animated animate__bounceInUp"></div>

<div id="confirmModal" class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4">
    <div id="confirmModalOverlay" class="absolute inset-0 bg-slate-950/60 backdrop-blur-md"></div>
    <div class="relative glass border border-white/20 dark:border-slate-800 w-full max-w-lg rounded-[2.5rem] shadow-2xl p-10 text-center animate__animated animate__zoomIn">
        <div class="w-20 h-20 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-3xl flex items-center justify-center text-4xl mx-auto mb-6">📖</div>
        <h2 class="text-3xl font-black mb-4">Conferma Rientro</h2>
        <p class="text-slate-500 dark:text-slate-400 text-lg mb-8">Stai per segnare il libro come restituito. Questa azione aggiornerà la disponibilità in magazzino.</p>
        <div class="flex gap-4">
            <button id="confirmCancelBtn" class="flex-1 py-4 rounded-2xl font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition-all">Annulla</button>
            <button id="confirmOkBtn" class="flex-1 py-4 rounded-2xl font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-600/30 transition-all">Conferma</button>
        </div>
    </div>
</div>

<script>
// --- LOGICA JS OTTIMIZZATA ---
const container = document.getElementById('prestitiContainer');
const alertBox = document.getElementById('alertBox');
let currentAbortController = null;
let currentPage = 1;

// Funzione Helper per HTML sicuro
const h = (str) => String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

function showAlert(msg, type = 'success') {
    alertBox.classList.remove('hidden');
    const color = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
    alertBox.innerHTML = `<div class="${color} text-white px-8 py-4 rounded-2xl shadow-2xl font-bold flex items-center gap-3">
        <span>${type === 'success' ? '✅' : '❌'}</span> ${msg}
    </div>`;
    setTimeout(() => alertBox.classList.add('hidden'), 4000);
}

function renderCard(item) {
    const isRitardo = item.in_ritardo && item.stato !== 'restituito';
    const badgeClass = item.stato === 'restituito' 
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' 
        : (isRitardo ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400');
    
    return `
    <div class="card-anim p-8 group">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <div class="flex-1 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest ${badgeClass}">
                        ${h(item.stato).replace('_', ' ')} ${isRitardo ? '• In Ritardo' : ''}
                    </span>
                    <span class="text-slate-400 text-sm font-medium">ID #${h(item.id)}</span>
                </div>
                
                <div>
                    <h3 class="text-2xl font-black group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">${h(item.titolo)}</h3>
                    <p class="text-slate-500 font-medium">di ${h(item.autore)}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-lg">👤</div>
                        <div>
                            <p class="text-[10px] uppercase font-black text-slate-400 tracking-tighter">Cliente</p>
                            <p class="text-sm font-bold truncate max-w-[150px]">${h(item.nome_cliente)}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-lg">📅</div>
                        <div>
                            <p class="text-[10px] uppercase font-black text-slate-400 tracking-tighter">Inizio</p>
                            <p class="text-sm font-bold">${h(item.data_prestito)}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl ${isRitardo ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-600' : 'bg-slate-100 dark:bg-slate-800'} flex items-center justify-center text-lg">⌛</div>
                        <div>
                            <p class="text-[10px] uppercase font-black text-slate-400 tracking-tighter">Scadenza</p>
                            <p class="text-sm font-bold ${isRitardo ? 'text-rose-600' : ''}">${h(item.data_restituzione_prevista)}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full lg:w-auto self-center">
                ${item.stato === 'in_prestito' ? `
                    <button data-id="${h(item.id)}" class="btn-restituisci w-full lg:w-auto px-8 py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl transition-all hover:scale-105 active:scale-95 shadow-lg shadow-emerald-600/20">
                        Segna Rientro
                    </button>
                ` : `
                    <div class="text-right">
                        <p class="text-[10px] uppercase font-black text-slate-400 mb-1">Restituito il</p>
                        <p class="text-lg font-black text-emerald-600">${h(item.data_restituzione_effettiva)}</p>
                    </div>
                `}
            </div>
        </div>
    </div>`;
}

async function loadData() {
    if (currentAbortController) currentAbortController.abort();
    currentAbortController = new AbortController();

    document.getElementById('loadingBox').classList.remove('hidden');
    document.getElementById('emptyBox').classList.add('hidden');
    container.innerHTML = '';

    const params = new URLSearchParams({
        action: 'list',
        q: document.getElementById('searchInput').value,
        stato: document.getElementById('statusFilter').value,
        sort: document.getElementById('sortBy').value,
        page: currentPage
    });

    try {
        const res = await fetch(`api_prestiti.php?${params}`, { signal: currentAbortController.signal });
        const data = await res.json();
        
        if (!data.success) throw new Error(data.message);

        // Update Stats
        ['Totale', 'Attivi', 'Restituiti', 'Ritardo'].forEach(s => {
            const el = document.getElementById('stat' + s);
            if (el) el.textContent = data.stats[s.toLowerCase()] || 0;
        });

        if (data.items.length === 0) {
            document.getElementById('emptyBox').classList.remove('hidden');
        } else {
            container.innerHTML = data.items.map(renderCard).join('');
            
            // Paginazione
            const pag = document.getElementById('paginationContainer');
            if (data.totalPages > 1) {
                pag.classList.remove('hidden');
                document.getElementById('pageIndicator').textContent = `Pagina ${currentPage} di ${data.totalPages}`;
                document.getElementById('prevPageBtn').disabled = currentPage <= 1;
                document.getElementById('nextPageBtn').disabled = currentPage >= data.totalPages;
            } else {
                pag.classList.add('hidden');
            }
        }
    } catch (e) {
        if (e.name !== 'AbortError') showAlert(e.message, 'error');
    } finally {
        document.getElementById('loadingBox').classList.add('hidden');
    }
}

// Event Listeners (Filtri, Modale, etc.)
document.getElementById('searchInput').addEventListener('input', () => { currentPage = 1; loadData(); });
document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; loadData(); });
document.getElementById('sortBy').addEventListener('change', () => { currentPage = 1; loadData(); });
document.getElementById('resetFilters').addEventListener('click', () => {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    currentPage = 1;
    loadData();
});

// Gestione Restituzione
let selectedId = null;
container.addEventListener('click', e => {
    const btn = e.target.closest('.btn-restituisci');
    if (btn) {
        selectedId = btn.dataset.id;
        document.getElementById('confirmModal').classList.remove('hidden');
    }
});

document.getElementById('confirmOkBtn').addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'restituisci');
    fd.append('id', selectedId);
    fd.append('csrf_token', document.getElementById('csrf_token').value);

    try {
        const res = await fetch('api_prestiti.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showAlert("Libro restituito con successo!");
            document.getElementById('confirmModal').classList.add('hidden');
            loadData();
        } else throw new Error(data.message);
    } catch (e) { showAlert(e.message, 'error'); }
});

document.getElementById('confirmCancelBtn').addEventListener('click', () => document.getElementById('confirmModal').classList.add('hidden'));

// Init
loadData();
</script>
</body>
</html>