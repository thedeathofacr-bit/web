<?php
include "connessione.php";
require_admin_page($conn);

$result = $conn->query("SELECT id, titolo, autore, genere, isbn, anno_pubblicazione, prezzo FROM libri ORDER BY titolo ASC");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Esporta PDF - Libri</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 30px;
            color: #111;
        }
        h1 {
            margin-bottom: 10px;
        }
        p {
            color: #555;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #999;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #eee;
        }
        .actions {
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            margin-right: 10px;
        }
        .btn.gray {
            background: #666;
        }
        @media print {
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="actions">
    <button onclick="window.print()" class="btn">Stampa / Salva come PDF</button>
    <a href="index.php" class="btn gray">Torna alla libreria</a>
</div>

<h1>Elenco libri</h1>
<p>Esportazione generata il <?php echo date("d/m/Y H:i"); ?></p>

<table>
    <tr>
        <th>ID</th>
        <th>Titolo</th>
        <th>Autore</th>
        <th>Genere</th>
        <th>ISBN</th>
        <th>Anno</th>
        <th>Prezzo</th>
    </tr>

    <?php if ($result): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row["id"]); ?></td>
                <td><?php echo htmlspecialchars($row["titolo"]); ?></td>
                <td><?php echo htmlspecialchars($row["autore"]); ?></td>
                <td><?php echo htmlspecialchars($row["genere"]); ?></td>
                <td><?php echo htmlspecialchars($row["isbn"]); ?></td>
                <td><?php echo htmlspecialchars($row["anno_pubblicazione"]); ?></td>
                <td>€ <?php echo htmlspecialchars($row["prezzo"]); ?></td>
            </tr>
        <?php endwhile; ?>
    <?php endif; ?>
</table>

</body>
</html>