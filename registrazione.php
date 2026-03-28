<?php
include "connessione.php";

$libraries = [];
$libRes = $conn->query("SELECT id, nome, codice FROM libreria ORDER BY nome ASC");
if ($libRes && $libRes->num_rows > 0) {
    while ($row = $libRes->fetch_assoc()) {
        $libraries[] = $row;
    }
}
?>

<form method="POST" action="registrazione_process.php">

    <?php if (empty($libraries)): ?>
        <p style="color: red;">Nessuna libreria disponibile. Contatta l'amministratore.</p>
    <?php else: ?>
        <label for="libreria_id">Scegli libreria</label>
        <select name="libreria_id" id="libreria_id" required>
            <option value="">Seleziona una libreria</option>
            <?php foreach ($libraries as $lib): ?>
                <option value="<?php echo htmlspecialchars($lib['id']); ?>">
                    <?php echo htmlspecialchars($lib['nome'] . ' (' . $lib['codice'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>

    <input type="email" name="email" placeholder="Email" required>

    <input type="text" name="username" placeholder="Username" required>

    <input type="password" name="password" placeholder="Password" required>

    <button type="submit">Registrati</button>

</form>