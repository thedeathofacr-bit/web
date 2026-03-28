<?php
include "connessione.php";
require_admin_page($conn);

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=libri.csv");

$output = fopen("php://output", "w");

fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, ["ID", "Titolo", "Autore", "Genere", "ISBN", "Anno pubblicazione", "Prezzo", "Descrizione"]);

$result = $conn->query("SELECT id, titolo, autore, genere, isbn, anno_pubblicazione, prezzo, descrizione FROM libri ORDER BY titolo ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row["id"],
            $row["titolo"],
            $row["autore"],
            $row["genere"],
            $row["isbn"],
            $row["anno_pubblicazione"],
            $row["prezzo"],
            $row["descrizione"]
        ]);
    }
}

fclose($output);
exit;