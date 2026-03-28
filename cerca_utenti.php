<?php
include "connessione.php";
require_admin_api($conn);

$adminLoggatoId = current_user_id();

$search = trim($_GET["search"] ?? "");
$sort = $_GET["sort"] ?? "created_at";
$dir = strtolower($_GET["dir"] ?? "desc");

$allowedSort = array("username", "ruolo", "stato", "created_at");
$allowedDir = array("asc", "desc");

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

$sql = "SELECT id, username, ruolo, stato, created_at FROM utenti";
$params = [];
$types = "";

if ($search !== "") {
    $sql .= " WHERE username LIKE ? OR ruolo LIKE ? OR stato LIKE ?";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

$sql .= " ORDER BY $orderBy $orderDir, id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo '<tr><td colspan="5" class="px-6 py-6 text-center text-red-600 dark:text-red-400">Errore nella ricerca utenti.</td></tr>';
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<tr><td colspan="5" class="px-6 py-6 text-center text-gray-500 dark:text-gray-300">Nessun utente trovato.</td></tr>';
    exit;
}

while ($row = $result->fetch_assoc()) {
    $isSelf = ((int)$row["id"] === $adminLoggatoId);
    $isDisattivato = ($row["stato"] === "disattivato");

    echo '<tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">';

    echo '<td class="px-6 py-3 font-medium text-gray-800 dark:text-white">';
    echo htmlspecialchars($row["username"]);
    if ($isSelf) {
        echo '<span class="ml-2 text-xs text-cyan-600 dark:text-cyan-400">(tu)</span>';
    }
    echo '</td>';

    echo '<td class="px-6 py-3">';
    echo $row["ruolo"] === "admin"
        ? '<span class="bg-cyan-600 text-white text-xs px-3 py-1 rounded-full">ADMIN</span>'
        : '<span class="bg-gray-300 dark:bg-gray-600 text-xs px-3 py-1 rounded-full text-gray-800 dark:text-white">UTENTE</span>';
    echo '</td>';

    echo '<td class="px-6 py-3">';
    echo $row["stato"] === "attivo"
        ? '<span class="bg-green-600 text-white text-xs px-3 py-1 rounded-full">ATTIVO</span>'
        : '<span class="bg-red-600 text-white text-xs px-3 py-1 rounded-full">DISATTIVATO</span>';
    echo '</td>';

    echo '<td class="px-6 py-3 text-gray-700 dark:text-gray-300">';
    echo !empty($row["created_at"]) ? date("d/m/Y H:i", strtotime($row["created_at"])) : "-";
    echo '</td>';

    echo '<td class="px-6 py-3 text-right"><div class="flex justify-end gap-2 flex-wrap">';
    echo '<a href="modifica_utente.php?id=' . (int)$row["id"] . '" class="bg-amber-500 hover:bg-amber-600 text-white px-3 py-1 rounded-lg text-sm transition">Modifica</a>';

    if ($isDisattivato) {
        echo '<form action="cambia_stato_utente.php" method="POST" class="inline">';
        echo csrf_input();
        echo '<input type="hidden" name="id" value="' . (int)$row["id"] . '">';
        echo '<input type="hidden" name="nuovo_stato" value="attivo">';
        echo '<button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm transition">Riattiva</button>';
        echo '</form>';
    } else {
        if ($isSelf) {
            echo '<button type="button" disabled class="bg-gray-300 dark:bg-gray-700 text-white px-3 py-1 rounded-lg text-sm cursor-not-allowed opacity-70">Disattiva</button>';
        } else {
            echo '<form action="cambia_stato_utente.php" method="POST" class="inline">';
            echo csrf_input();
            echo '<input type="hidden" name="id" value="' . (int)$row["id"] . '">';
            echo '<input type="hidden" name="nuovo_stato" value="disattivato">';
            echo '<button type="submit" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-1 rounded-lg text-sm transition">Disattiva</button>';
            echo '</form>';
        }
    }

    if ($isSelf) {
        echo '<button type="button" disabled class="bg-red-300 dark:bg-red-900/40 text-white px-3 py-1 rounded-lg text-sm cursor-not-allowed opacity-70">Elimina</button>';
    } else {
        echo '<button type="button" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-sm transition" onclick="apriModalEliminazione(\'' . (int)$row["id"] . '\', \'' . htmlspecialchars($row["username"], ENT_QUOTES) . '\')">Elimina</button>';
    }

    echo '</div></td>';
    echo '</tr>';
}
?>