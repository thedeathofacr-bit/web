<?php
include "connessione.php";
require_admin_page($conn);

$errore = "";

$sqlLibri = "
    SELECT l.id, l.titolo, l.autore, l.genere, l.immagine
    FROM libri l
    WHERE l.id NOT IN (
        SELECT p.libro_id
        FROM prestiti p
        WHERE p.stato = 'in_prestito'
    )
    ORDER BY l.titolo ASC
";
$resultLibri = $conn->query($sqlLibri);

$libri = [];
if ($resultLibri && $resultLibri->num_rows > 0) {
    while ($row = $resultLibri->fetch_assoc()) {
        $libri[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf_or_die($conn);

    $libro_id = (int)($_POST["libro_id"] ?? 0);
    $nome_cliente = clean_string($_POST["nome_cliente"] ?? "", 150);
    $email_cliente = clean_string($_POST["email_cliente"] ?? "", 150);
    $telefono_cliente = clean_string($_POST["telefono_cliente"] ?? "", 50);
    $data_prestito = $_POST["data_prestito"] ?? "";
    $data_restituzione_prevista = $_POST["data_restituzione_prevista"] ?? "";
    $note = trim((string)($_POST["note"] ?? ""));

    if ($libro_id <= 0 || $nome_cliente === "" || $data_prestito === "" || $data_restituzione_prevista === "") {
        $errore = "Compila tutti i campi obbligatori.";
    } elseif ($data_restituzione_prevista < $data_prestito) {
        $errore = "La data di restituzione prevista non può essere precedente alla data di prestito.";
    } else {
        $check = $conn->prepare("SELECT id FROM prestiti WHERE libro_id = ? AND stato = 'in_prestito' LIMIT 1");

        if ($check) {
            $check->bind_param("i", $libro_id);
            $check->execute();
            $resCheck = $check->get_result();

            if ($resCheck && $resCheck->num_rows > 0) {
                $errore = "Questo libro risulta già in prestito.";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO prestiti
                    (libro_id, nome_cliente, email_cliente, telefono_cliente, data_prestito, data_restituzione_prevista, note, stato)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'in_prestito')
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "issssss",
                        $libro_id,
                        $nome_cliente,
                        $email_cliente,
                        $telefono_cliente,
                        $data_prestito,
                        $data_restituzione_prevista,
                        $note
                    );

                    if ($stmt->execute()) {
                        log_attivita($conn, "creazione", "prestito", $stmt->insert_id, "Creato nuovo prestito");
                        header("Location: prestiti.php?ok=prestito_creato");
                        exit;
                    } else {
                        $errore = "Errore durante il salvataggio del prestito.";
                    }
                } else {
                    $errore = "Errore nella query di inserimento.";
                }
            }
        } else {
            $errore = "Errore nel controllo disponibilità libro.";
        }
    }
}

$dataOggi = date("Y-m-d");
$dataDefaultRestituzione = date("Y-m-d", strtotime("+14 days"));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo prestito</title>
    <link rel="icon" type="image/png" href="assets/logo.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="min-h-screen bg-gray-100 dark:bg-[#020617] text-gray-800 dark:text-white">

<div id="toastContainer" class="fixed top-5 right-5 z-50 space-y-3"></div>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="relative overflow-hidden rounded-[32px] bg-gradient-to-r from-indigo-600 via-violet-600 to-blue-600 p-8 md:p-10 shadow-2xl mb-8">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,white,transparent_35%)]"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-medium backdrop-blur">
                    <span>📚</span>
                    <span>Gestione prestiti premium</span>
                </div>

                <h1 class="mt-5 text-4xl md:text-5xl font-black tracking-tight text-white">
                    Nuovo prestito
                </h1>

                <p class="mt-3 max-w-2xl text-white/85 text-sm md:text-base">
                    Una schermata moderna per assegnare un libro in prestito con ricerca live,
                    anteprima dinamica e riepilogo in tempo reale.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="prestiti.php"
                   class="inline-flex items-center gap-2 rounded-2xl bg-white/15 hover:bg-white/20 text-white px-5 py-3 font-medium backdrop-blur transition">
                    <span>←</span>
                    <span>Torna ai prestiti</span>
                </a>

                <a href="index.php"
                   class="inline-flex items-center gap-2 rounded-2xl bg-white text-gray-900 hover:bg-gray-100 px-5 py-3 font-semibold transition">
                    <span>🏠</span>
                    <span>Libreria</span>
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="rounded-3xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="text-sm text-gray-500 dark:text-slate-400">Libri disponibili</div>
            <div class="mt-2 text-3xl font-black"><?php echo count($libri); ?></div>
        </div>

        <div class="rounded-3xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="text-sm text-gray-500 dark:text-slate-400">Durata consigliata</div>
            <div class="mt-2 text-3xl font-black text-indigo-600 dark:text-indigo-400">14 giorni</div>
        </div>

        <div class="rounded-3xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-5 shadow-sm">
            <div class="text-sm text-gray-500 dark:text-slate-400">Esperienza</div>
            <div class="mt-2 text-lg font-bold text-emerald-600 dark:text-emerald-400">Ricerca live attiva</div>
        </div>
    </div>

    <?php if ($errore !== ""): ?>
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-100 px-5 py-4 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
            <div class="font-bold mb-1">Errore</div>
            <div><?php echo htmlspecialchars($errore); ?></div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2">
            <div class="rounded-[28px] bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-slate-800">
                    <h2 class="text-2xl font-black">Compila il prestito</h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                        Scegli un libro, inserisci i dati cliente e controlla il riepilogo a destra.
                    </p>
                </div>

                <form method="POST" id="prestitoForm" class="p-6 space-y-6">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="libro_id" id="libro_id" value="<?php echo (int)($_POST["libro_id"] ?? 0); ?>">

                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Libro *</label>

                        <div class="relative">
                            <input
                                type="text"
                                id="bookSearch"
                                placeholder="Cerca per titolo o autore..."
                                autocomplete="off"
                                class="w-full px-4 py-4 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >

                            <div id="bookDropdown"
                                 class="hidden absolute z-30 mt-2 w-full rounded-2xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-950 shadow-2xl overflow-hidden">
                                <div id="bookList" class="max-h-80 overflow-y-auto"></div>
                            </div>
                        </div>

                        <p id="selectedBookText" class="mt-3 text-sm text-gray-500 dark:text-slate-400">
                            Nessun libro selezionato
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Nome cliente *</label>
                            <input
                                type="text"
                                name="nome_cliente"
                                id="nome_cliente"
                                required
                                value="<?php echo htmlspecialchars($_POST["nome_cliente"] ?? ""); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Email cliente</label>
                            <input
                                type="email"
                                name="email_cliente"
                                value="<?php echo htmlspecialchars($_POST["email_cliente"] ?? ""); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Telefono</label>
                            <input
                                type="text"
                                name="telefono_cliente"
                                value="<?php echo htmlspecialchars($_POST["telefono_cliente"] ?? ""); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Data prestito *</label>
                            <input
                                type="date"
                                name="data_prestito"
                                id="dataPrestito"
                                required
                                value="<?php echo htmlspecialchars($_POST["data_prestito"] ?? $dataOggi); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Data restituzione prevista *</label>
                            <input
                                type="date"
                                name="data_restituzione_prevista"
                                id="dataRestituzione"
                                required
                                value="<?php echo htmlspecialchars($_POST["data_restituzione_prevista"] ?? $dataDefaultRestituzione); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            <p id="dateWarning" class="hidden mt-2 text-sm text-red-500 font-medium">
                                La data di restituzione non può essere prima della data di prestito.
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-2 text-gray-700 dark:text-slate-300">Note</label>
                        <textarea
                            name="note"
                            rows="5"
                            placeholder="Inserisci eventuali annotazioni..."
                            class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        ><?php echo htmlspecialchars($_POST["note"] ?? ""); ?></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
                        <a href="prestiti.php"
                           class="px-5 py-3 rounded-2xl bg-gray-200 hover:bg-gray-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-gray-800 dark:text-white text-center transition">
                            Annulla
                        </a>

                        <button
                            type="submit"
                            class="px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold shadow-lg shadow-indigo-600/20 transition transform hover:-translate-y-0.5"
                        >
                            Salva prestito
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-[28px] bg-gradient-to-br from-indigo-600 to-violet-600 text-white p-6 shadow-2xl">
                <div class="text-sm uppercase tracking-widest text-white/70">Riepilogo live</div>
                <div id="summaryTitolo" class="mt-3 text-2xl font-black">Nessun libro selezionato</div>
                <div id="summaryAutore" class="mt-2 text-white/80">Scegli un libro per visualizzare i dettagli</div>

                <div class="grid grid-cols-2 gap-3 mt-5">
                    <div class="rounded-2xl bg-white/10 p-4">
                        <div class="text-xs text-white/70">Genere</div>
                        <div id="summaryGenere" class="mt-1 font-bold">—</div>
                    </div>

                    <div class="rounded-2xl bg-white/10 p-4">
                        <div class="text-xs text-white/70">Durata</div>
                        <div id="summaryDurata" class="mt-1 font-bold">0 giorni</div>
                    </div>

                    <div class="rounded-2xl bg-white/10 p-4 col-span-2">
                        <div class="text-xs text-white/70">Cliente</div>
                        <div id="summaryCliente" class="mt-1 font-bold">Non inserito</div>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-6 shadow-sm">
                <h3 class="text-xl font-black mb-4">Anteprima libro</h3>

                <div class="flex gap-4 items-start">
                    <div id="previewImageWrap" class="w-28 h-40 rounded-2xl bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 flex items-center justify-center overflow-hidden shrink-0">
                        <span class="text-4xl">📘</span>
                    </div>

                    <div class="min-w-0">
                        <div id="previewTitle" class="font-black text-lg">Nessun libro selezionato</div>
                        <div id="previewAuthor" class="text-gray-500 dark:text-slate-400 mt-1">—</div>
                        <div id="previewGenre" class="text-sm text-gray-500 dark:text-slate-400 mt-2">Genere: —</div>
                        <div class="mt-4 inline-flex px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                            Disponibile
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-[28px] bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-6 shadow-sm">
                <h3 class="text-xl font-black mb-4">Tips smart</h3>
                <div class="space-y-3 text-sm text-gray-600 dark:text-slate-400">
                    <div class="rounded-2xl bg-gray-50 dark:bg-slate-950 p-4 border border-gray-200 dark:border-slate-800">
                        Usa una restituzione di 14 giorni per la maggior parte dei prestiti.
                    </div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-slate-950 p-4 border border-gray-200 dark:border-slate-800">
                        Inserisci note solo se davvero utili: libro danneggiato, copia riservata, consegna speciale.
                    </div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-slate-950 p-4 border border-gray-200 dark:border-slate-800">
                        Controlla il riepilogo a destra prima di salvare.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const books = <?php echo json_encode($libri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const bookSearch = document.getElementById('bookSearch');
const bookDropdown = document.getElementById('bookDropdown');
const bookList = document.getElementById('bookList');
const libroIdInput = document.getElementById('libro_id');
const selectedBookText = document.getElementById('selectedBookText');

const summaryTitolo = document.getElementById('summaryTitolo');
const summaryAutore = document.getElementById('summaryAutore');
const summaryGenere = document.getElementById('summaryGenere');
const summaryDurata = document.getElementById('summaryDurata');
const summaryCliente = document.getElementById('summaryCliente');

const previewTitle = document.getElementById('previewTitle');
const previewAuthor = document.getElementById('previewAuthor');
const previewGenre = document.getElementById('previewGenre');
const previewImageWrap = document.getElementById('previewImageWrap');

const dataPrestito = document.getElementById('dataPrestito');
const dataRestituzione = document.getElementById('dataRestituzione');
const dateWarning = document.getElementById('dateWarning');
const nomeCliente = document.getElementById('nome_cliente');
const prestitoForm = document.getElementById('prestitoForm');

let selectedBook = null;

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');

    const base = 'px-4 py-3 rounded-2xl shadow-2xl border text-sm font-semibold transition-all duration-300 opacity-0 translate-y-2';
    const style = type === 'error'
        ? 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800'
        : 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800';

    toast.className = `${base} ${style}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('opacity-0', 'translate-y-2');
    });

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 2800);
}

function renderBookList(items) {
    if (!items.length) {
        bookList.innerHTML = `
            <div class="px-4 py-4 text-sm text-gray-500 dark:text-slate-400">
                Nessun libro trovato
            </div>
        `;
        bookDropdown.classList.remove('hidden');
        return;
    }

    bookList.innerHTML = items.map(book => `
        <button
            type="button"
            class="w-full text-left px-4 py-4 hover:bg-gray-50 dark:hover:bg-slate-900 transition border-b border-gray-100 dark:border-slate-800 last:border-b-0"
            onclick="selectBook(${book.id})"
        >
            <div class="font-bold text-gray-900 dark:text-white">${escapeHtml(book.titolo)}</div>
            <div class="text-sm text-gray-500 dark:text-slate-400 mt-1">${escapeHtml(book.autore)}${book.genere ? ' • ' + escapeHtml(book.genere) : ''}</div>
        </button>
    `).join('');

    bookDropdown.classList.remove('hidden');
}

function updatePreview() {
    if (!selectedBook) {
        summaryTitolo.textContent = 'Nessun libro selezionato';
        summaryAutore.textContent = 'Scegli un libro per visualizzare i dettagli';
        summaryGenere.textContent = '—';

        previewTitle.textContent = 'Nessun libro selezionato';
        previewAuthor.textContent = '—';
        previewGenre.textContent = 'Genere: —';
        previewImageWrap.innerHTML = '<span class="text-4xl">📘</span>';
        selectedBookText.textContent = 'Nessun libro selezionato';
        return;
    }

    summaryTitolo.textContent = selectedBook.titolo || '—';
    summaryAutore.textContent = selectedBook.autore || '—';
    summaryGenere.textContent = selectedBook.genere || 'Non specificato';

    previewTitle.textContent = selectedBook.titolo || '—';
    previewAuthor.textContent = selectedBook.autore || '—';
    previewGenre.textContent = 'Genere: ' + (selectedBook.genere || 'Non specificato');
    selectedBookText.textContent = 'Libro selezionato: ' + selectedBook.titolo + ' — ' + selectedBook.autore;

    if (selectedBook.immagine) {
        previewImageWrap.innerHTML = `
            <img src="uploads/${encodeURIComponent(selectedBook.immagine)}"
                 alt="Copertina"
                 class="w-full h-full object-cover">
        `;
    } else {
        previewImageWrap.innerHTML = '<span class="text-4xl">📘</span>';
    }
}

function updateDuration() {
    if (!dataPrestito.value || !dataRestituzione.value) {
        summaryDurata.textContent = '0 giorni';
        dateWarning.classList.add('hidden');
        return true;
    }

    const start = new Date(dataPrestito.value);
    const end = new Date(dataRestituzione.value);
    const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));

    if (isNaN(diff)) {
        summaryDurata.textContent = '0 giorni';
        dateWarning.classList.add('hidden');
        return true;
    }

    if (diff < 0) {
        summaryDurata.textContent = 'Data non valida';
        dateWarning.classList.remove('hidden');
        return false;
    }

    summaryDurata.textContent = diff + ' giorni';
    dateWarning.classList.add('hidden');
    return true;
}

function selectBook(id) {
    selectedBook = books.find(book => Number(book.id) === Number(id)) || null;
    libroIdInput.value = selectedBook ? selectedBook.id : '';
    bookSearch.value = selectedBook ? `${selectedBook.titolo} — ${selectedBook.autore}` : '';
    bookDropdown.classList.add('hidden');
    updatePreview();

    if (selectedBook) {
        showToast('Libro selezionato correttamente.');
    }
}

function filterBooks() {
    const value = bookSearch.value.trim().toLowerCase();

    if (!value) {
        renderBookList(books);
        return;
    }

    const filtered = books.filter(book => {
        const text = `${book.titolo} ${book.autore} ${book.genere || ''}`.toLowerCase();
        return text.includes(value);
    });

    renderBookList(filtered);
}

bookSearch.addEventListener('focus', () => {
    renderBookList(books);
});

bookSearch.addEventListener('input', () => {
    libroIdInput.value = '';
    selectedBook = null;
    updatePreview();
    filterBooks();
});

document.addEventListener('click', (e) => {
    if (!bookSearch.contains(e.target) && !bookDropdown.contains(e.target)) {
        bookDropdown.classList.add('hidden');
    }
});

dataPrestito.addEventListener('change', updateDuration);
dataRestituzione.addEventListener('change', updateDuration);

nomeCliente.addEventListener('input', () => {
    summaryCliente.textContent = nomeCliente.value.trim() || 'Non inserito';
});

prestitoForm.addEventListener('submit', (e) => {
    let valid = true;

    if (!libroIdInput.value || Number(libroIdInput.value) <= 0) {
        valid = false;
        showToast('Seleziona un libro dalla lista.', 'error');
    }

    if (!nomeCliente.value.trim()) {
        valid = false;
        showToast('Inserisci il nome del cliente.', 'error');
    }

    if (!updateDuration()) {
        valid = false;
        showToast('Controlla le date del prestito.', 'error');
    }

    if (!valid) {
        e.preventDefault();
    }
});

(function initPage() {
    const selectedId = Number(libroIdInput.value || 0);
    if (selectedId > 0) {
        const found = books.find(book => Number(book.id) === selectedId);
        if (found) {
            selectedBook = found;
            bookSearch.value = `${found.titolo} — ${found.autore}`;
        }
    }

    updatePreview();
    updateDuration();
    summaryCliente.textContent = nomeCliente.value.trim() || 'Non inserito';
})();
</script>

</body>
</html>