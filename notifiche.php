<?php
include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) header("Location: login.php");

$userId = $_SESSION['user_id'];

// Segna tutte le notifiche dell'utente come "lette" appena apre la pagina
$conn->query("UPDATE notifiche SET letta = 1 WHERE id_utente = $userId");

// Recupera le notifiche
$query = "SELECT * FROM notifiche WHERE id_utente = ? ORDER BY data_invio DESC";
$stmt = $conn->prepare($query);

// Sistema Anti-Crash
if (!$stmt) {
    die("<div style='background:#020617; color:#f87171; padding:2rem; font-family:sans-serif; text-align:center;'>
            <h2>🚨 Tabella Mancante!</h2>
            <p>Esegui questa query su phpMyAdmin per far funzionare le notifiche:</p>
            <code>CREATE TABLE notifiche (id INT AUTO_INCREMENT PRIMARY KEY, id_utente INT, messaggio TEXT, letta TINYINT(1) DEFAULT 0, data_invio DATETIME);</code>
         </div>");
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$notifiche = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <title>Le tue Notifiche</title>
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #020617; overflow-x: hidden; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body class="text-slate-200 min-h-screen p-6 md:p-12 relative">
    
    <div class="fixed inset-0 -z-10">
        <div class="absolute top-[0%] right-[0%] w-[40%] h-[40%] rounded-full bg-fuchsia-900/20 blur-[150px]"></div>
        <div class="absolute bottom-[0%] left-[0%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
    </div>

    <div class="max-w-4xl mx-auto space-y-8">
        <div class="glass-card p-6 rounded-[2rem] flex justify-between items-center shadow-xl">
            <h1 class="text-3xl font-black text-white flex items-center gap-3">
                <span class="p-3 bg-fuchsia-500/20 rounded-2xl text-fuchsia-400">🔔</span> Notifiche
            </h1>
            <a href="profilo_view.php" class="bg-slate-800 px-5 py-3 rounded-xl text-sm font-bold text-slate-300 hover:bg-slate-700 transition shadow-lg">← Torna al Profilo</a>
        </div>

        <div class="space-y-4">
            <?php if ($notifiche->num_rows === 0): ?>
                <div class="glass-card p-12 rounded-[2.5rem] text-center border border-dashed border-white/10">
                    <div class="text-6xl mb-4 opacity-50">📭</div>
                    <p class="text-xl font-bold text-slate-500">Nessuna notifica</p>
                    <p class="text-slate-600">Non hai nuovi avvisi al momento.</p>
                </div>
            <?php else: ?>
                <?php while($row = $notifiche->fetch_assoc()): 
                    $dataFmt = date('d M Y - H:i', strtotime($row['data_invio']));
                    $isNuova = $row['letta'] == 0;
                ?>
                <div class="glass-card p-6 rounded-[2rem] flex items-start gap-5 transition-all hover:bg-slate-800/40 <?= $isNuova ? 'border-l-4 border-l-fuchsia-500' : 'border-l-4 border-l-transparent' ?>">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl shrink-0 <?= $isNuova ? 'bg-fuchsia-500/20 text-fuchsia-400' : 'bg-slate-800 text-slate-500' ?>">
                        <?= strpos(strtolower($row['messaggio']), 'xp') !== false ? '✨' : '📌' ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <?php if($isNuova): ?>
                                <span class="bg-fuchsia-500 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded-full animate-pulse">Nuova</span>
                            <?php endif; ?>
                            <span class="text-xs font-bold text-slate-500"><?= $dataFmt ?></span>
                        </div>
                        <p class="text-lg font-medium text-slate-300 leading-snug">
                            <?= htmlspecialchars($row['messaggio']) ?>
                        </p>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>