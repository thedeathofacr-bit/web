<?php
include "connessione.php";

$id = $_GET['id'];
?>

<form method="POST" action="verifica_process.php">

<input type="hidden" name="id" value="<?php echo $id; ?>">

<input type="text" name="codice" placeholder="Codice email">

<button type="submit">Verifica</button>

</form>