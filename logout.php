<?php
include "connessione.php";
logout_user($conn);
header("Location: login_gestioneutenti.php");
exit;