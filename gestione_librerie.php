<?php
include "connessione.php";

// Solo utenti autenticati possono accedere (la gestione dettagliata è poi limitata al creatore o al superadmin)
require_user_page($conn);

$errore = '';
$successo = '';
$currentUserId = current_user_id();
$isSuperAdmin = current_role() === 'superadmin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die($conn);

    // ---- Creazione libreria ----
    if (isset($_POST['create_libreria'])) {
        $nome = clean_string($_POST['nome'] ?? '', 255);
        $codice = clean_string($_POST['codice'] ?? '', 100);

        if ($nome === '' || $codice === '') {
            $errore = 'Compila tutti i campi.';
        } else {
            $stmt = $conn->prepare("INSERT INTO libreria (nome, codice, creator_id) VALUES (?, ?, ?)");
            if (!$stmt) {
                $errore = 'Errore interno DB: ' . $conn->error;
            } else {
                $stmt->bind_param('ssi', $nome, $codice, $currentUserId);
                if ($stmt->execute()) {
                    // Assicuriamo che il creatore diventi admin (per gestire la libreria)
                    $stmtRole = $conn->prepare("UPDATE utenti SET ruolo = 'admin' WHERE id = ? AND ruolo = 'utente'");
                    if ($stmtRole) {
                        $stmtRole->bind_param('i', $currentUserId);
                        $stmtRole->execute();
                        if ($stmtRole->affected_rows > 0) {
                            // Aggiorna anche la sessione in modo che l'utente diventi subito admin
                            $_SESSION['ruolo'] = 'admin';
                        }
                        $stmtRole->close();
                    }

                    $successo = 'Libreria creata con successo.';
                } else {
                    if ($conn->errno === 1062) {
                        $errore = 'Codice libreria già esistente.';
                    } else {
                        $errore = 'Errore interno DB: ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
    }

    // ---- Modifica libreria (solo creatore) ----
    if (isset($_POST['update_libreria'])) {
        $editId = (int)($_POST['edit_id'] ?? 0);
        $nome = clean_string($_POST['nome'] ?? '', 255);
        $codice = clean_string($_POST['codice'] ?? '', 100);

        if ($editId <= 0 || $nome === '' || $codice === '') {
            $errore = 'Dati non validi per modifica.';
        } else {
            // Verifica permessi: solo creatore o superadmin
            $stmt = $conn->prepare("SELECT creator_id FROM libreria WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $editId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if (!$row) {
                    $errore = 'Libreria non trovata.';
                } else {
                    $creatorId = (int)$row['creator_id'];
                    if (!$isSuperAdmin && $creatorId !== $currentUserId) {
                        $errore = 'Non hai i permessi per modificare questa libreria.';
                    } else {
                        $stmt2 = $conn->prepare("UPDATE libreria SET nome = ?, codice = ? WHERE id = ?");
                        if ($stmt2) {
                            $stmt2->bind_param('ssi', $nome, $codice, $editId);
                            if ($stmt2->execute()) {
                                $successo = 'Libreria aggiornata con successo.';
                            } else {
                                $errore = 'Errore aggiornamento: ' . $stmt2->error;
                            }
                            $stmt2->close();
                        } else {
                            $errore = 'Errore interno DB: ' . $conn->error;
                        }
                    }
                }
            } else {
                $errore = 'Errore interno DB: ' . $conn->error;
            }
        }
    }
}

$libraries = [];
$result = $conn->query("SELECT l.id, l.nome, l.codice, l.created_at, l.creator_id, u.username AS creator_username FROM libreria l LEFT JOIN utenti u ON u.id = l.creator_id ORDER BY l.created_at DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $libraries[] = $row;
    }
}

// Se siamo in modalità modifica, recuperiamo i dati dell'elemento
$editLibrary = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($libraries as $lib) {
        if ((int)$lib['id'] === $editId) {
            // Solo il creatore (o superadmin) può modificare
            if ($isSuperAdmin || (int)$lib['creator_id'] === $currentUserId) {
                $editLibrary = $lib;
            }
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione librerie</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-slate-950 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Gestione librerie</h1>
            <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">Torna a dashboard</a>
        </div>

        <?php if ($errore): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300">
                <?php echo htmlspecialchars($errore); ?>
            </div>
        <?php endif; ?>

        <?php if ($successo): ?>
            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 text-green-800 px-5 py-4 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300">
                <?php echo htmlspecialchars($successo); ?>
            </div>
        <?php endif; ?>

        <?php if ($editLibrary !== null): ?>
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow p-6 mb-10">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Modifica libreria</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="update_libreria" value="1">
                    <input type="hidden" name="edit_id" value="<?php echo (int)$editLibrary['id']; ?>">

                    <div class="md:col-span-1">
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                        <input type="text" name="nome" required value="<?php echo htmlspecialchars($editLibrary['nome']); ?>" class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-1">
                        <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Codice</label>
                        <input type="text" name="codice" required value="<?php echo htmlspecialchars($editLibrary['codice']); ?>" class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="md:col-span-1 flex items-end">
                        <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white px-4 py-3 rounded-2xl font-semibold transition">Aggiorna</button>
                    </div>
                </form>
                <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    <a href="gestione_librerie.php" class="text-indigo-600 hover:underline">Annulla modifica</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow p-6 mb-10">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Crea una nuova libreria</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="create_libreria" value="1">

                <div class="md:col-span-1">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                    <input type="text" name="nome" required class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-1">
                    <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Codice</label>
                    <input type="text" name="codice" required class="w-full px-4 py-3 rounded-2xl border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-1 flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-2xl font-semibold transition">Crea</button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow p-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Librerie esistenti</h2>
            <?php if (empty($libraries)): ?>
                <p class="text-gray-600 dark:text-gray-300">Nessuna libreria ancora creata.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">ID</th>
                                <th class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Nome</th>
                                <th class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Codice</th>
                                <th class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Creato da</th>
                                <th class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Creato il</th>
                                <th class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <?php foreach ($libraries as $lib): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($lib['id']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($lib['nome']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($lib['codice']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($lib['creator_username'] ?? '-'); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($lib['created_at']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                        <?php if ($isSuperAdmin || (int)$lib['creator_id'] === $currentUserId): ?>
                                            <a href="gestione_librerie.php?edit=<?php echo (int)$lib['id']; ?>" class="text-indigo-600 hover:underline">Modifica</a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
