{{--
    Incolla questo blocco HTML+JS nella pagina dettaglio_libro.php,
    dopo il blocco principale del libro.
    Assicurati che $libro['id'] e csrf_token() siano disponibili.
--}}

<!-- ══════════════════════════════════════════════════
     BOTTONE LISTA DESIDERI
══════════════════════════════════════════════════ -->
<div class="mt-4">
    <button id="btnDesideri"
            onclick="toggleDesideri()"
            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border-2 border-red-400 text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 font-medium transition text-sm">
        <span id="iconaDesideri">🤍</span>
        <span id="labelDesideri">Aggiungi ai desideri</span>
    </button>
</div>

<!-- ══════════════════════════════════════════════════
     SEZIONE RECENSIONI
══════════════════════════════════════════════════ -->
<div class="mt-10">
    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">⭐ Recensioni</h3>

    <!-- Media voti -->
    <div id="riepilogoVoti" class="flex items-center gap-3 mb-6">
        <div id="stelleMedia" class="text-3xl"></div>
        <div>
            <div id="mediaVoto" class="text-2xl font-bold text-gray-800 dark:text-white"></div>
            <div id="totaleRecensioni" class="text-sm text-gray-500 dark:text-gray-400"></div>
        </div>
    </div>

    <!-- Form recensione -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow p-6 mb-6">
        <h4 class="font-semibold text-gray-800 dark:text-white mb-4">Scrivi la tua recensione</h4>

        <!-- Stelle interattive -->
        <div class="flex gap-1 mb-4" id="stelleInput">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button"
                    data-voto="<?= $i ?>"
                    onclick="setVoto(<?= $i ?>)"
                    class="text-3xl text-gray-300 dark:text-gray-600 hover:text-yellow-400 transition stella-input">
                ★
            </button>
            <?php endfor; ?>
        </div>
        <p id="votoLabel" class="text-sm text-gray-500 dark:text-gray-400 mb-3">Seleziona un voto</p>

        <input type="text" id="titoloRecensione"
               placeholder="Titolo della recensione (opzionale)"
               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3 text-sm">

        <textarea id="testoRecensione" rows="3"
                  placeholder="Cosa pensi di questo libro?"
                  class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm resize-none"></textarea>

        <div class="flex items-center gap-3 mt-3">
            <button onclick="salvaRecensione()"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-medium text-sm transition">
                Pubblica recensione
            </button>
            <span id="recensioneMsg" class="text-sm"></span>
        </div>
    </div>

    <!-- Lista recensioni -->
    <div id="listaRecensioni" class="space-y-4"></div>
</div>

<!-- Toast -->
<div id="toastRecensione" class="hidden fixed top-5 right-5 z-50 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium bg-green-600"></div>

<script>
const ID_LIBRO     = <?php echo (int)$libro['id']; ?>;
const CSRF_TOKEN   = <?php echo json_encode(csrf_token()); ?>;
let votoSelezionato = 0;

const votoLabels = ['', '😞 Pessimo', '😕 Scarso', '😐 Nella media', '😊 Buono', '🤩 Eccellente'];

// ── Lista Desideri ───────────────────────────────────────────
async function checkDesideri() {
    const res  = await fetch(`api_lista_desideri.php?action=check&id_libro=${ID_LIBRO}`);
    const data = await res.json();
    aggiornaBottoneDesideri(data.in_lista);
}

function aggiornaBottoneDesideri(inLista) {
    document.getElementById('iconaDesideri').textContent  = inLista ? '❤️' : '🤍';
    document.getElementById('labelDesideri').textContent  = inLista ? 'Nei tuoi desideri' : 'Aggiungi ai desideri';
}

async function toggleDesideri() {
    const fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('id_libro', ID_LIBRO);
    fd.append('csrf_token', CSRF_TOKEN);

    const res  = await fetch('api_lista_desideri.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        aggiornaBottoneDesideri(data.in_lista);
        showToast(data.message);
    }
}

// ── Stelle interattive ───────────────────────────────────────
function setVoto(v) {
    votoSelezionato = v;
    document.querySelectorAll('.stella-input').forEach((btn, i) => {
        btn.classList.toggle('text-yellow-400', i < v);
        btn.classList.toggle('text-gray-300',   i >= v);
        btn.classList.toggle('dark:text-gray-600', i >= v);
    });
    document.getElementById('votoLabel').textContent = votoLabels[v] || '';
}

// ── Salva Recensione ─────────────────────────────────────────
async function salvaRecensione() {
    if (!votoSelezionato) {
        document.getElementById('recensioneMsg').textContent = '⚠️ Seleziona un voto';
        document.getElementById('recensioneMsg').className   = 'text-sm text-red-500';
        return;
    }

    const fd = new FormData();
    fd.append('action',   'salva');
    fd.append('id_libro', ID_LIBRO);
    fd.append('voto',     votoSelezionato);
    fd.append('titolo',   document.getElementById('titoloRecensione').value.trim());
    fd.append('testo',    document.getElementById('testoRecensione').value.trim());
    fd.append('csrf_token', CSRF_TOKEN);

    const res  = await fetch('api_recensioni.php', { method: 'POST', body: fd });
    const data = await res.json();

    const msg = document.getElementById('recensioneMsg');
    if (data.success) {
        msg.textContent = '✓ ' + data.message;
        msg.className   = 'text-sm text-green-600';
        caricaRecensioni();
    } else {
        msg.textContent = '⚠️ ' + data.message;
        msg.className   = 'text-sm text-red-500';
    }
}

// ── Elimina Recensione ───────────────────────────────────────
async function eliminaRecensione(idRecensione) {
    if (!confirm('Eliminare questa recensione?')) return;

    const fd = new FormData();
    fd.append('action',        'elimina');
    fd.append('id_recensione', idRecensione);
    fd.append('csrf_token',    CSRF_TOKEN);

    const res  = await fetch('api_recensioni.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) { showToast('Recensione eliminata'); caricaRecensioni(); }
    else showToast('Errore', 'error');
}

// ── Carica Recensioni ────────────────────────────────────────
function stelleStatiche(media) {
    let h = '';
    for (let i = 1; i <= 5; i++) h += `<span class="${i <= Math.round(media) ? 'text-yellow-400' : 'text-gray-300 dark:text-gray-600'}">★</span>`;
    return h;
}

function escHtml(t) { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }

async function caricaRecensioni() {
    const res  = await fetch(`api_recensioni.php?action=get&id_libro=${ID_LIBRO}`);
    const data = await res.json();

    // Aggiorna riepilogo
    document.getElementById('stelleMedia').innerHTML      = stelleStatiche(data.media);
    document.getElementById('mediaVoto').textContent      = data.media > 0 ? data.media.toFixed(1) + ' / 5' : 'Nessuna valutazione';
    document.getElementById('totaleRecensioni').textContent = `${data.totale} recensione${data.totale !== 1 ? 'i' : ''}`;

    const container = document.getElementById('listaRecensioni');

    if (!data.recensioni || !data.recensioni.length) {
        container.innerHTML = `<p class="text-gray-400 dark:text-gray-500 text-sm">Nessuna recensione ancora. Sii il primo!</p>`;
        return;
    }

    container.innerHTML = data.recensioni.map(r => {
        const avatar = r.foto
            ? `<img src="uploads/profili/${escHtml(r.foto)}" class="w-10 h-10 rounded-full object-cover border border-gray-200 dark:border-gray-700">`
            : `<div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold text-sm">${escHtml((r.nome || 'U')[0])}</div>`;

        const data_fmt = new Date(r.data_recensione).toLocaleDateString('it-IT');

        return `
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    ${avatar}
                    <div>
                        <div class="font-semibold text-gray-800 dark:text-white text-sm">${escHtml(r.nome + ' ' + r.cognome)}</div>
                        <div class="flex items-center gap-1 text-sm">${stelleStatiche(r.voto)}<span class="text-gray-400 dark:text-gray-500 ml-1">${data_fmt}</span></div>
                    </div>
                </div>
                ${r.mia == 1 || <?php echo is_admin() ? 'true' : 'false'; ?>
                    ? `<button onclick="eliminaRecensione(${r.id})" class="text-red-400 hover:text-red-600 text-xs transition">✕ Elimina</button>`
                    : ''}
            </div>
            ${r.titolo ? `<p class="font-semibold text-gray-800 dark:text-white mt-3 text-sm">${escHtml(r.titolo)}</p>` : ''}
            ${r.testo  ? `<p class="text-gray-600 dark:text-gray-300 mt-1 text-sm leading-relaxed">${escHtml(r.testo)}</p>` : ''}
        </div>
        `;
    }).join('');
}

// ── Toast ────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.getElementById('toastRecensione');
    t.textContent = msg;
    t.className = `fixed top-5 right-5 z-50 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3000);
}

// ── Init ─────────────────────────────────────────────────────
checkDesideri();
caricaRecensioni();
</script>
