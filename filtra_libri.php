<?php
session_start();
include "connessione.php";

header('Content-Type: application/json; charset=utf-8');

// Funzione di controllo per l'ID libreria
$libraryId = 0;
if (function_exists('current_library_id')) {
    $libraryId = (int) current_library_id();
}

if ($libraryId <= 0) {
    echo json_encode(["libri" => [], "totale" => 0, "error" => "Libreria non identificata"]);
    exit;
}

// Parametri di ricerca
$search = trim($_GET["search"] ?? "");
$field  = $_GET["field"] ?? "titolo";
$sort   = $_GET["sort"] ?? "titolo_asc";
$page   = max(1, (int)($_GET["page"] ?? 1));
$limit  = max(1, (int)($_GET["limit"] ?? 10));
$genere = trim($_GET["genere"] ?? "");
$offset = ($page - 1) * $limit;

// Mappatura ordinamento (Usa gli alias l.)
$allowedSorts = [
    "titolo_asc"  => "l.titolo ASC",
    "titolo_desc" => "l.titolo DESC",
    "prezzo_asc"  => "l.prezzo ASC",
    "prezzo_desc" => "l.prezzo DESC",
    "anno_desc"   => "l.anno_pubblicazione DESC",
    "anno_asc"    => "l.anno_pubblicazione ASC"
];
$orderSql = $allowedSorts[$sort] ?? "l.titolo ASC";

// COSTRUZIONE QUERY - ATTENZIONE: Controlla se nel tuo DB è id_libreria o libreria_id
// Qui uso id_libreria perché è quello che appare nel tuo index.php
$where = ["l.id_libreria = ?"]; 
$params = [$libraryId];
$types = "i";

if ($search !== "") {
    $where[] = "l.$field LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($genere !== "") {
    $where[] = "l.genere = ?";
    $params[] = $genere;
    $types .= "s";
}

$whereSql = "WHERE " . implode(" AND ", $where);

// 1. CONTEGGIO
$sqlCount = "SELECT COUNT(*) AS totale FROM libri l $whereSql";
$stmtC = $conn->prepare($sqlCount);
$stmtC->bind_param($types, ...$params);
$stmtC->execute();
$totale = $stmtC->get_result()->fetch_assoc()["totale"];

// 2. QUERY LIBRI CON JOIN RECENSIONI
$sqlLibri = "
    SELECT 
        l.*, 
        COALESCE(AVG(r.voto), 0) AS media_voti, 
        COUNT(r.id) AS num_recensioni
    FROM libri l
    LEFT JOIN recensioni r ON r.id_libro = l.id
    $whereSql
    GROUP BY l.id
    ORDER BY $orderSql 
    LIMIT ? OFFSET ?
";

$stmtL = $conn->prepare($sqlLibri);
$typesL = $types . "ii";
$paramsL = array_merge($params, [$limit, $offset]);
$stmtL->bind_param($typesL, ...$paramsL);
$stmtL->execute();
$resL = $stmtL->get_result();

$libri = [];
while ($row = $resL->fetch_assoc()) {
    // Forziamo i valori numerici per il JavaScript
    $row['media_voti'] = (float)$row['media_voti'];
    $row['num_recensioni'] = (int)$row['num_recensioni'];
    $libri[] = $row;
}

// 3. STATISTICHE VELOCI
$sqlStats = "SELECT COUNT(DISTINCT genere) as tot_gen, AVG(prezzo) as avg_p FROM libri l $whereSql";
$stmtS = $conn->prepare($sqlStats);
$stmtS->bind_param($types, ...$params);
$stmtS->execute();
$s = $stmtS->get_result()->fetch_assoc();

echo json_encode([
    "libri" => $libri,
    "totale" => (int)$totale,
    "pagina" => $page,
    "limite" => $limit,
    "totale_pagine" => ceil($totale / $limit),
    "stats" => [
        "totale_libri" => (int)$totale,
        "totale_generi" => (int)$s['tot_gen'],
        "prezzo_medio" => round($s['avg_p'], 2),
        "prezzo_massimo" => 0,
        "libro_piu_costoso" => "N/D"
    ]
], JSON_UNESCAPED_UNICODE);