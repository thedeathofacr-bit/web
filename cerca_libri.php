<?php
session_start();

if (!isset($_SESSION["admin"])) {
    exit;
}

include "connessione.php";

$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $likeSearch = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT id, username, ruolo FROM utenti WHERE username LIKE ? ORDER BY username");
    $stmt->bind_param("s", $likeSearch);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT id, username, ruolo FROM utenti ORDER BY username");
}

if ($result && $result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
?>
<tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
    <td class="px-6 py-3 font-medium text-gray-800 dark:text-white">
        <?php echo htmlspecialchars($row["username"]); ?>
    </td>

    <td class="px-6 py-3">
        <?php if ($row["ruolo"] === "admin"): ?>
            <span class="bg-cyan-600 text-white text-xs px-3 py-1 rounded-full">
                ADMIN
            </span>
        <?php else: ?>
            <span class="bg-gray-300 dark:bg-gray-600 text-xs px-3 py-1 rounded-full text-gray-800 dark:text-white">
                UTENTE
            </span>
        <?php endif; ?>
    </td>

    <td class="px-6 py-3 text-right">
        <div class="flex justify-end gap-2">
            <a
                href="modifica_utente.php?id=<?php echo $row['id']; ?>"
                class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded-lg text-sm transition"
            >
                Modifica
            </a>

            <button
                type="button"
                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-sm transition"
                onclick="apriModalEliminazione('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>')"
            >
                Elimina
            </button>
        </div>
    </td>
</tr>
<?php
    endwhile;
else:
?>
<tr>
    <td colspan="3" class="px-6 py-6 text-center text-gray-500 dark:text-gray-300">
        Nessun utente trovato.
    </td>
</tr>
<?php endif; ?>