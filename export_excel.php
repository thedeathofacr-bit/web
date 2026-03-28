<?php
include "connessione.php";
require_admin_page($conn);

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=libri.xls");

echo "\xEF\xBB\xBF";

$result = $conn->query("SELECT id, titolo, autore, genere, isbn, anno_pubblicazione, prezzo, descrizione FROM libri ORDER BY titolo ASC");
?>
<table border="1">
    <tr>
        <th>ID</th>
        <th>Titolo</th>
        <th>Autore</th>
        <th>Genere</th>
        <th>ISBN</th>
        <th>Anno pubblicazione</th>
        <th>Prezzo</th>
        <th>Descrizione</th>
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
                <td><?php echo htmlspecialchars($row["prezzo"]); ?></td>
                <td><?php echo htmlspecialchars($row["descrizione"]); ?></td>
            </tr>
        <?php endwhile; ?>
    <?php endif; ?>
</table>
<?php exit; ?>