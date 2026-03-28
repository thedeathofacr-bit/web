<?php
include "connessione.php";
require_admin_page($conn, "login_gestioneutenti.php");

$adminLoggatoId = current_user_id();
$myLibraryId = current_library_id();
$isSuperAdmin = current_role() === 'superadmin';

$totaleUtenti = 0;
$totaleAdmin = 0;
$totaleUtentiNormali = 0;
$ultimoUtente = "Nessuno";
$attivitaRecenti = [];

$allowedSort = array("username", "ruolo", "stato", "created_at");
$allowedDir = array("asc", "desc");

$sort = isset($_GET["sort"]) ? $_GET["sort"] : "created_at";
$dir = isset($_GET["dir"]) ? strtolower($_GET["dir"]) : "desc";

if (!in_array($sort, $allowedSort, true)) {
    $sort = "created_at";
}

if (!in_array($dir, $allowedDir, true)) {
    $dir = "desc";
}

if ($sort === "username") {
    $orderBy = "username";
} elseif ($sort === "ruolo") {
    $orderBy = "ruolo";
} elseif ($sort === "stato") {
    $orderBy = "stato";
} else {
    $orderBy = "created_at";
}

$orderDir = strtoupper($dir);

/*
|--------------------------------------------------------------------------
| CONTROLLI BASE
|--------------------------------------------------------------------------
*/
if (!$isSuperAdmin && (int)$myLibraryId <= 0) {
    header("Location: index.php?error=" . urlencode("Libreria non valida."));
    exit;
}

/*
|--------------------------------------------------------------------------
| STATISTICHE
|--------------------------------------------------------------------------
*/
if ($isSuperAdmin) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM utenti");
    if ($stmt) {
        $stmt->execute();
        $resultTotale = $stmt->get_result();
        if ($resultTotale && $row = $resultTotale->fetch_assoc()) {
            $totaleUtenti = (int)$row["totale"];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'admin'");
    if ($stmt) {
        $stmt->execute();
        $resultAdmin = $stmt->get_result();
        if ($resultAdmin && $row = $resultAdmin->fetch_assoc()) {
            $totaleAdmin = (int)$row["totale"];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'utente'");
    if ($stmt) {
        $stmt->execute();
        $resultUtentiNormali = $stmt->get_result();
        if ($resultUtentiNormali && $row = $resultUtentiNormali->fetch_assoc()) {
            $totaleUtentiNormali = (int)$row["totale"];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT username FROM utenti ORDER BY created_at DESC, id DESC LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $resultUltimoUtente = $stmt->get_result();
        if ($resultUltimoUtente && $row = $resultUltimoUtente->fetch_assoc()) {
            $ultimoUtente = $row["username"];
        }
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE libreria_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $myLibraryId);
        $stmt->execute();
        $resultTotale = $stmt->get_result();
        if ($resultTotale && $row = $resultTotale->fetch_assoc()) {
            $totaleUtenti = (int)$row["totale"];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'admin' AND libreria_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $myLibraryId);
        $stmt->execute();
        $resultAdmin = $stmt->get_result();
        if ($resultAdmin && $row = $resultAdmin->fetch_assoc()) {
            $totaleAdmin = (int)$row["totale"];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS totale FROM utenti WHERE ruolo = 'utente' AND libreria_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $myLibraryId);
        $stmt->execute();
        $resultUtentiNormali = $stmt->get_result();
        if ($resultUtentiNormali && $row = $resultUtentiNormali->fetch_assoc()) {
            $totaleUtentiNormali = (int)$row["totale"];
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT username FROM utenti WHERE libreria_id = ? ORDER BY created_at DESC, id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $myLibraryId);
        $stmt->execute();
        $resultUltimoUtente = $stmt->get_result();
        if ($resultUltimoUtente && $row = $resultUltimoUtente->fetch_assoc()) {
            $ultimoUtente = $row["username"];
        }
        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| STORICO ATTIVITÀ
|--------------------------------------------------------------------------
| Nota: questo filtro funziona bene se log_attivita ha il campo libreria_id.
|--------------------------------------------------------------------------
*/
if ($isSuperAdmin) {
    $stmt = $conn->prepare("
        SELECT utente, azione, oggetto, descrizione, data_operazione
        FROM log_attivita
        ORDER BY data_operazione DESC, id DESC
        LIMIT 8
    ");

    if ($stmt) {
        $stmt->execute();
        $resultAttivita = $stmt->get_result();

        if ($resultAttivita && $resultAttivita->num_rows > 0) {
            while ($row = $resultAttivita->fetch_assoc()) {
                $attivitaRecenti[] = $row;
            }
        }

        $stmt->close();
    }
} else {
    $stmt = $conn->prepare("
        SELECT utente, azione, oggetto, descrizione, data_operazione
        FROM log_attivita
        WHERE libreria_id = ?
        ORDER BY data_operazione DESC, id DESC
        LIMIT 8
    ");

    if ($stmt) {
        $stmt->bind_param("i", $myLibraryId);
        $stmt->execute();
        $resultAttivita = $stmt->get_result();

        if ($resultAttivita && $resultAttivita->num_rows > 0) {
            while ($row = $resultAttivita->fetch_assoc()) {
                $attivitaRecenti[] = $row;
            }
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| LISTA UTENTI
|--------------------------------------------------------------------------
*/
$utenti = [];

if ($isSuperAdmin) {
    $sql = "SELECT id, username, ruolo, stato, created_at FROM utenti ORDER BY $orderBy $orderDir, id DESC";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $utenti[] = $row;
            }
        }

        $stmt->close();
    }
} else {
    $sql = "SELECT id, username, ruolo, stato, created_at FROM utenti WHERE libreria_id = ? ORDER BY $orderBy $orderDir, id DESC";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $myLibraryId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $utenti[] = $row;
            }
        }

        $stmt->close();
    }
}

$success = $_GET["success"] ?? "";
$error = $_GET["error"] ?? "";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione utenti</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>

<body class="bg-gray-100 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-6 py-8">

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Gestione utenti</h1>

            <div class="flex gap-3">
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow transition">
                    ← Libreria
                </a>

                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow transition">
                    Logout
                </a>
            </div>
        </div>

        <?php if ($success !== ""): ?>
            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 text-green-800 px-5 py-4 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300">
                Operazione completata con successo.
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                Operazione non riuscita.
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Utenti totali</p>
                <h3 class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo $totaleUtenti; ?></h3>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-cyan-200 dark:border-cyan-800 rounded-2xl shadow p-6">
                <p class="text-sm text-cyan-600 dark:text-cyan-400 mb-2">Admin</p>
                <h3 class="text-3xl font-bold text-cyan-700 dark:text-cyan-300"><?php echo $totaleAdmin; ?></h3>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">Utenti normali</p>
                <h3 class="text-3xl font-bold text-gray-800 dark:text-white"><?php echo $totaleUtentiNormali; ?></h3>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-800 rounded-2xl shadow p-6">
                <p class="text-sm text-amber-600 dark:text-amber-400 mb-2">Ultimo utente creato</p>
                <h3 class="text-xl font-bold text-gray-800 dark:text-white break-words">
                    <?php echo htmlspecialchars($ultimoUtente); ?>
                </h3>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow p-6 mb-8">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Storico attività recente</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Ultime operazioni registrate nel sistema</p>
            </div>

            <?php if (!empty($attivitaRecenti)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="text-left px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Utente</th>
                                <th class="text-left px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Azione</th>
                                <th class="text-left px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Oggetto</th>
                                <th class="text-left px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Descrizione</th>
                                <th class="text-left px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Data</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php foreach ($attivitaRecenti as $attivita): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <td class="px-4 py-3 text-gray-800 dark:text-white font-medium"><?php echo htmlspecialchars($attivita["utente"]); ?></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars(ucfirst($attivita["azione"])); ?></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars(ucfirst($attivita["oggetto"])); ?></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($attivita["descrizione"]); ?></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap"><?php echo date("d/m/Y H:i", strtotime($attivita["data_operazione"])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 dark:text-gray-300">Nessuna attività registrata.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Aggiungi nuovo utente</h2>

            <form action="crea_utente.php" method="POST" class="grid md:grid-cols-4 gap-4">
                <?php echo csrf_input(); ?>

                <input type="text" name="username" placeholder="Username" required class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-2">
                <input type="password" name="password" placeholder="Password" required class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-2">

                <select name="ruolo" class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-2">
                    <option value="utente">Utente</option>
                    <option value="admin">Admin</option>
                </select>

                <button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white font-medium px-4 py-2 rounded-lg shadow transition">
                    Crea
                </button>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Utenti registrati</h2>

                <div class="flex flex-col md:flex-row gap-3">
                    <div class="relative">
                        <input type="text" id="searchInput" placeholder="Cerca username, ruolo o stato..." class="w-full md:w-80 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-800 dark:text-white rounded-lg px-4 py-2 pr-10">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">⌕</span>
                    </div>

                    <button type="button" id="resetSearch" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-lg transition">
                        Reset
                    </button>
                </div>
            </div>

            <div id="searchStatus" class="hidden px-6 py-3 text-sm text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                Sto cercando...
            </div>

            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">
                            <button type="button" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition" onclick="cambiaOrdinamento('username')">
                                Username <span id="sort-username"></span>
                            </button>
                        </th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">
                            <button type="button" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition" onclick="cambiaOrdinamento('ruolo')">
                                Ruolo <span id="sort-ruolo"></span>
                            </button>
                        </th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">
                            <button type="button" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition" onclick="cambiaOrdinamento('stato')">
                                Stato <span id="sort-stato"></span>
                            </button>
                        </th>
                        <th class="text-left px-6 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">
                            <button type="button" class="hover:text-cyan-600 dark:hover:text-cyan-400 transition" onclick="cambiaOrdinamento('created_at')">
                                Data creazione <span id="sort-created_at"></span>
                            </button>
                        </th>
                        <th class="text-right px-6 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Azioni</th>
                    </tr>
                </thead>

                <tbody id="tabellaUtentiBody">
                    <?php if (!empty($utenti)): ?>
                        <?php foreach ($utenti as $row): ?>
                            <?php
                            $isSelf = ((int)$row["id"] === (int)$adminLoggatoId);
                            $isDisattivato = ($row["stato"] === "disattivato");
                            ?>
                            <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <td class="px-6 py-3 font-medium text-gray-800 dark:text-white">
                                    <?php echo htmlspecialchars($row["username"]); ?>
                                    <?php if ($isSelf): ?>
                                        <span class="ml-2 text-xs text-cyan-600 dark:text-cyan-400">(tu)</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-3">
                                    <?php if ($row["ruolo"] === "admin"): ?>
                                        <span class="bg-cyan-600 text-white text-xs px-3 py-1 rounded-full">ADMIN</span>
                                    <?php elseif ($row["ruolo"] === "superadmin"): ?>
                                        <span class="bg-purple-600 text-white text-xs px-3 py-1 rounded-full">SUPERADMIN</span>
                                    <?php else: ?>
                                        <span class="bg-gray-300 dark:bg-gray-600 text-xs px-3 py-1 rounded-full text-gray-800 dark:text-white">UTENTE</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-3">
                                    <?php if ($row["stato"] === "attivo"): ?>
                                        <span class="bg-green-600 text-white text-xs px-3 py-1 rounded-full">ATTIVO</span>
                                    <?php else: ?>
                                        <span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">DISATTIVATO</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                    <?php echo date("d/m/Y H:i", strtotime($row["created_at"])); ?>
                                </td>

                                <td class="px-6 py-3 text-right">
                                    <div class="flex justify-end gap-2 flex-wrap">
                                        <a href="modifica_utente.php?id=<?php echo $row['id']; ?>" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded-lg text-sm transition">
                                            Modifica
                                        </a>

                                        <?php if ($isDisattivato): ?>
                                            <form action="cambia_stato_utente.php" method="POST" class="inline">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="nuovo_stato" value="attivo">
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm transition">
                                                    Riattiva
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($isSelf): ?>
                                                <button type="button" disabled class="bg-gray-300 dark:bg-gray-700 text-white px-3 py-1 rounded-lg text-sm cursor-not-allowed opacity-70">
                                                    Disattiva
                                                </button>
                                            <?php else: ?>
                                                <form action="cambia_stato_utente.php" method="POST" class="inline">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="nuovo_stato" value="disattivato">
                                                    <button type="submit" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-1 rounded-lg text-sm transition">
                                                        Disattiva
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ($isSelf): ?>
                                            <button type="button" disabled class="bg-red-300 dark:bg-red-900/40 text-white px-3 py-1 rounded-lg text-sm cursor-not-allowed opacity-70">
                                                Elimina
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-sm transition" onclick="apriModalEliminazione('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>')">
                                                Elimina
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-300">
                                Nessun utente trovato.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div id="modalEliminazione" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">Conferma eliminazione</h3>
                    <button type="button" onclick="chiudiModalEliminazione()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white text-2xl leading-none">
                        &times;
                    </button>
                </div>

                <p class="text-gray-600 dark:text-gray-300 mb-6">
                    Sei sicuro di voler eliminare l'utente
                    <span id="nomeUtenteDaEliminare" class="font-semibold text-gray-800 dark:text-white"></span>?
                </p>

                <form action="elimina_utente.php" method="POST" class="flex justify-end gap-3">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="id" id="idUtenteDaEliminare">

                    <button type="button" onclick="chiudiModalEliminazione()" class="bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-lg transition">
                        Annulla
                    </button>

                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition">
                        Elimina definitivamente
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentSort = '<?php echo $sort; ?>';
        let currentDir = '<?php echo $dir; ?>';

        function aggiornaIndicatoriOrdinamento() {
            document.getElementById('sort-username').textContent = '';
            document.getElementById('sort-ruolo').textContent = '';
            document.getElementById('sort-stato').textContent = '';
            document.getElementById('sort-created_at').textContent = '';

            const arrow = currentDir === 'asc' ? '↑' : '↓';
            const target = document.getElementById('sort-' + currentSort);

            if (target) target.textContent = arrow;
        }

        function cambiaOrdinamento(colonna) {
            if (currentSort === colonna) {
                currentDir = currentDir === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort = colonna;
                currentDir = colonna === 'created_at' ? 'desc' : 'asc';
            }

            aggiornaIndicatoriOrdinamento();
            cercaUtenti();
        }

        function apriModalEliminazione(id, username) {
            document.getElementById('idUtenteDaEliminare').value = id;
            document.getElementById('nomeUtenteDaEliminare').textContent = username;

            const modal = document.getElementById('modalEliminazione');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function chiudiModalEliminazione() {
            const modal = document.getElementById('modalEliminazione');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('idUtenteDaEliminare').value = '';
            document.getElementById('nomeUtenteDaEliminare').textContent = '';
        }

        document.getElementById('modalEliminazione').addEventListener('click', function(e) {
            if (e.target === this) chiudiModalEliminazione();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') chiudiModalEliminazione();
        });

        const searchInput = document.getElementById('searchInput');
        const resetSearch = document.getElementById('resetSearch');
        const tabellaBody = document.getElementById('tabellaUtentiBody');
        const searchStatus = document.getElementById('searchStatus');

        let debounceTimer;
        let controller = null;

        function mostraLoader() {
            searchStatus.textContent = 'Sto cercando.';
            searchStatus.classList.remove('hidden');
        }

        function nascondiLoader() {
            searchStatus.classList.add('hidden');
        }

        function cercaUtenti() {
            const query = searchInput.value.trim();

            if (controller) controller.abort();
            controller = new AbortController();

            mostraLoader();

            fetch(
                'cerca_utenti.php?search=' + encodeURIComponent(query) +
                '&sort=' + encodeURIComponent(currentSort) +
                '&dir=' + encodeURIComponent(currentDir),
                { signal: controller.signal }
            )
            .then(response => response.text())
            .then(html => {
                tabellaBody.innerHTML = html;

                if (query !== '') {
                    searchStatus.textContent = 'Risultati per: "' + query + '"';
                    searchStatus.classList.remove('hidden');
                } else {
                    nascondiLoader();
                }
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    console.error('Errore nella ricerca utenti:', error);
                    searchStatus.textContent = 'Errore durante la ricerca.';
                    searchStatus.classList.remove('hidden');
                }
            });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                cercaUtenti();
            }, 250);
        });

        resetSearch.addEventListener('click', function () {
            searchInput.value = '';
            cercaUtenti();
            searchInput.focus();
        });

        aggiornaIndicatoriOrdinamento();
    </script>
</body>
</html>