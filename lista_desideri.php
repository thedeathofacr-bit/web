<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
require_user_page($conn);

$id_utente   = $_SESSION['user_id'];
$id_libreria = current_library_id();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>La mia Lista Desideri</title>
<link rel="icon" type="image/png" href="assets/logo.ico">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { darkMode: 'class' };</script>
<script>if (localStorage.getItem('darkMode') === 'enabled') document.documentElement.classList.add('dark');</script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen transition-colors duration-300">

<div class="max-w-5xl mx-auto px-4 py-10">

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">❤️ Lista Desideri</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">I libri che vuoi leggere</p>
        </div>
        <a href="index.php" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-lg transition text-sm">
            ← Torna alla libreria
        </a>
    </div>

    <div id="barraControlli" class="hidden flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700 transition-all">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6">
            <div id="contatore" class="text-sm font-medium text-gray-600 dark:text-gray-400"></div>
            <div id="valoreTotale" class="text-sm font-bold text-green-600 dark:text-green-400"></div>
        </div>
        
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <select id="ordinamento" onchange="ordinaLista()" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:w-auto p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white transition">
                <option value="default">Ordina per...</option>
                <option value="prezzo_asc">Prezzo: dal più basso</option>
                <option value="prezzo_desc">Prezzo: dal più alto</option>
                <option value="voto_desc">Valutazione: i migliori</option>
                <option value="alfa_asc">Titolo: A-Z</option>
            </select>
            
            <button onclick="chiediSvuotaLista()" class="shrink-0 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                🗑️ Svuota
            </button>
        </div>
    </div>

    <div id="listaDesideri" class="space-y-4">
        <div class="animate-pulse bg-white dark:bg-gray-800 rounded-xl shadow p-4 flex gap-4 border border-gray-200 dark:border-gray-700">
            <div class="w-24 h-32 bg-gray-300 dark:bg-gray-700 rounded-lg shrink-0"></div>
            <div class="flex-1 py-2 space-y-4">
                <div class="h-4 bg-gray-300 dark:bg-gray-700 rounded w-3/4"></div>
                <div class="h-3 bg-gray-300 dark:bg-gray-700 rounded w-1/2"></div>
                <div class="h-4 bg-gray-300 dark:bg-gray-700 rounded w-1/4 mt-6"></div>
            </div>
        </div>
    </div>

</div>

<div id="toast" class="hidden fixed top-5 right-5 z-50 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium transition-all"></div>

<div id="modalConferma" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 max-w-sm w-full mx-4 transform transition-all scale-95 opacity-0" id="modalContent">
        <h3 id="modalTitolo" class="text-xl font-bold text-gray-900 dark:text-white mb-2">Conferma azione</h3>
        <p id="modalMessaggio" class="text-sm text-gray-600 dark:text-gray-400 mb-6">Sei sicuro di voler procedere?</p>
        
        <div class="flex gap-3 justify-end">
            <button onclick="chiudiModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white rounded-lg text-sm font-medium transition">
                Annulla
            </button>
            <button id="modalBtnConferma" onclick="eseguiAzioneModal()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition shadow-sm">
                Sì, procedi
            </button>
        </div>
    </div>
</div>

<script>
const csrfToken = <?php echo json_encode(function_exists('csrf_token') ? csrf_token() : ''); ?>;
let libriCaricati = []; 

// --- GESTIONE MODALE CUSTOM ---
let azionePendente = null;

function apriModal(titolo, messaggio, callback) {
    document.getElementById('modalTitolo').textContent = titolo;
    document.getElementById('modalMessaggio').textContent = messaggio;
    azionePendente = callback;
    
    const modal = document.getElementById('modalConferma');
    const content = document.getElementById('modalContent');
    
    modal.classList.remove('hidden');
    // Piccolo ritardo per permettere l'animazione CSS
    setTimeout(() => {
        content.classList.remove('scale-95', 'opacity-0');
        content.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function chiudiModal() {
    const modal = document.getElementById('modalConferma');
    const content = document.getElementById('modalContent');
    
    content.classList.remove('scale-100', 'opacity-100');
    content.classList.add('scale-95', 'opacity-0');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        azionePendente = null;
    }, 200); // Attende la fine dell'animazione
}

function eseguiAzioneModal() {
    if (azionePendente) {
        azionePendente();
    }
    chiudiModal();
}

// --- UTILITIES ---

function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = `fixed top-5 right-5 z-50 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3000);
}

function stelle(media) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<span class="${i <= Math.round(media) ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600'}">★</span>`;
    }
    return html;
}

function formatEuro(v) {
    return '€' + Number(v || 0).toLocaleString('it-IT', { minimumFractionDigits: 2 });
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
}

// --- API FETCH E LOGICA APP ---

async function caricaLista() {
    try {
        const res  = await fetch('api_lista_desideri.php?action=lista');
        const data = await res.json(); 

        if (!data.success) {
            document.getElementById('listaDesideri').innerHTML = `<div class="p-6 bg-red-100 text-red-800 rounded-xl">Errore dal server: ${escapeHtml(data.message)}</div>`;
            return;
        }

        libriCaricati = data.lista || [];
        renderizzaInterfaccia();

    } catch(e) {
        console.error(e);
        document.getElementById('listaDesideri').innerHTML = `<div class="text-center py-12 text-red-500">Errore di connessione. Ricarica la pagina.</div>`;
    }
}

// Funzione intermediaria per chiedere conferma prima di rimuovere un libro
function chiediRimuovi(idLibro, el) {
    apriModal(
        "Rimuovi libro", 
        "Vuoi davvero togliere questo libro dalla tua lista desideri?", 
        () => rimuoviDaLista(idLibro, el)
    );
}

async function rimuoviDaLista(idLibro, el) {
    el.disabled = true;
    el.classList.add('opacity-50');

    const fd = new FormData();
    fd.append('action', 'rimuovi');
    fd.append('id_libro', idLibro);
    fd.append('csrf_token', csrfToken);

    try {
        const res  = await fetch('api_lista_desideri.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast('Rimosso dalla lista ✓');
            libriCaricati = libriCaricati.filter(l => l.id_libro != idLibro);
            renderizzaInterfaccia();
        } else {
            showToast('Errore nella rimozione', 'error');
            el.disabled = false;
            el.classList.remove('opacity-50');
        }
    } catch(e) {
        showToast('Errore di connessione', 'error');
        el.disabled = false;
        el.classList.remove('opacity-50');
    }
}

// Funzione intermediaria per chiedere conferma prima di svuotare tutta la lista
function chiediSvuotaLista() {
    apriModal(
        "Svuota la lista", 
        "Sei sicuro di voler rimuovere TUTTI i libri? L'azione è irreversibile.", 
        () => eseguiSvuotaLista()
    );
}

async function eseguiSvuotaLista() {
    const fd = new FormData();
    fd.append('action', 'svuota');
    fd.append('csrf_token', csrfToken);

    try {
        const res = await fetch('api_lista_desideri.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast('Lista svuotata con successo! 🧹');
            libriCaricati = []; 
            renderizzaInterfaccia(); 
        } else {
            showToast('Errore: impossibile svuotare', 'error');
        }
    } catch(e) {
        showToast('Errore di connessione', 'error');
    }
}

async function aggiungiAlCarrello(idLibro, el) {
    el.disabled = true;
    el.innerHTML = '⏳ Attendi...';
    setTimeout(() => {
        showToast('Aggiunto al carrello! 🛒', 'success');
        el.disabled = false;
        el.innerHTML = '🛒 Nel carrello';
        el.classList.replace('bg-green-600', 'bg-green-800');
    }, 500);
}

// --- LOGICA UI ---

function ordinaLista() {
    const criterio = document.getElementById('ordinamento').value;
    
    if (criterio === 'prezzo_asc') {
        libriCaricati.sort((a, b) => parseFloat(a.prezzo) - parseFloat(b.prezzo));
    } else if (criterio === 'prezzo_desc') {
        libriCaricati.sort((a, b) => parseFloat(b.prezzo) - parseFloat(a.prezzo));
    } else if (criterio === 'voto_desc') {
        libriCaricati.sort((a, b) => parseFloat(b.media_voti || 0) - parseFloat(a.media_voti || 0));
    } else if (criterio === 'alfa_asc') {
        libriCaricati.sort((a, b) => a.titolo.localeCompare(b.titolo));
    }
    
    renderizzaInterfaccia(); 
}

function renderizzaInterfaccia() {
    const container = document.getElementById('listaDesideri');
    const barra = document.getElementById('barraControlli');

    if (libriCaricati.length === 0) {
        barra.classList.add('hidden');
        barra.classList.remove('flex');
        container.innerHTML = `
            <div class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow animate-fade-in">
                <div class="text-6xl mb-4">💔</div>
                <p class="text-xl font-semibold text-gray-700 dark:text-white">La tua lista è vuota</p>
                <p class="text-gray-500 dark:text-gray-400 mt-2">Vai al catalogo e aggiungi libri che ti interessano!</p>
                <a href="index.php" class="mt-6 inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl transition">Vai al catalogo</a>
            </div>`;
        return;
    }

    barra.classList.remove('hidden');
    barra.classList.add('flex');

    document.getElementById('contatore').textContent = `📚 ${libriCaricati.length} ${libriCaricati.length === 1 ? 'libro' : 'libri'} salvati`;

    const sommaPrezzi = libriCaricati.reduce((tot, libro) => tot + parseFloat(libro.prezzo || 0), 0);
    document.getElementById('valoreTotale').textContent = `Valore totale: ${formatEuro(sommaPrezzi)}`;

    container.innerHTML = libriCaricati.map(libro => {
        let percorsoImmagine = 'uploads/placeholder.png';
        if (libro.immagine) {
            percorsoImmagine = (libro.immagine.startsWith('http') || libro.immagine.startsWith('uploads/')) 
                                ? libro.immagine 
                                : 'uploads/' + libro.immagine;
        }

        return `
        <div data-libro="${libro.id_libro}" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 flex flex-col sm:flex-row items-start gap-4 transition hover:shadow-md animate-fade-in">
            
            <img src="${percorsoImmagine}"
                 alt="Copertina"
                 onerror="this.onerror=null; this.src='https://via.placeholder.com/96x128.png?text=No+Cover';"
                 class="w-24 h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0 bg-gray-100 dark:bg-gray-800">

            <div class="flex-1 min-w-0">
                <a href="dettaglio_libro.php?id=${libro.id_libro}" class="text-lg font-bold text-gray-800 dark:text-white hover:text-indigo-600 transition">
                    ${escapeHtml(libro.titolo)}
                </a>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">${escapeHtml(libro.autore || '')}</p>
                ${libro.genere ? `<span class="inline-block mt-2 px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">${escapeHtml(libro.genere)}</span>` : ''}

                <div class="flex items-center gap-2 mt-2">
                    <div class="flex text-base">${stelle(libro.media_voti)}</div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">${Number(libro.media_voti || 0).toFixed(1)}</span>
                </div>

                <p class="text-green-600 dark:text-green-400 font-bold mt-2 text-lg">${formatEuro(libro.prezzo)}</p>
            </div>

            <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto mt-4 sm:mt-0">
                <button onclick="aggiungiAlCarrello(${libro.id_libro}, this)"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center justify-center gap-2">
                    🛒 Aggiungi
                </button>
                <div class="flex gap-2">
                    <a href="dettaglio_libro.php?id=${libro.id_libro}"
                       class="flex-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-3 py-2 rounded-lg text-sm text-center transition">
                        Dettagli
                    </a>
                    
                    <button onclick="chiediRimuovi(${libro.id_libro}, this)"
                            class="flex-1 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 px-3 py-2 rounded-lg text-sm transition">
                        ✕ Rimuovi
                    </button>
                </div>
            </div>
        </div>
    `}).join('');
}

caricaLista();
</script>

<style>
.animate-fade-in { animation: fadeIn 0.4s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

</body>
</html>