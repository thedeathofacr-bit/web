<?php
include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) header("Location: login.php");

$userId = $_SESSION['user_id'];
$successo = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oggetto = $_POST['oggetto'];
    $messaggio = $_POST['messaggio'];
    $id_libreria = $_POST['id_libreria']; 
    
    $stmt = $conn->prepare("INSERT INTO segnalazioni (id_utente, id_libreria, oggetto, messaggio) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiss", $userId, $id_libreria, $oggetto, $messaggio);
        if ($stmt->execute()) {
            $successo = "Segnalazione inviata! Il team la prenderà in carico. (+5 XP per la collaborazione)";
            // Opzionale: aggiungi punti all'invio
            include_once "funzioni_gamification.php";
            aggiungiPunti($conn, $userId, 5); 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>Invia Segnalazione</title>
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #020617; }
        .glass-card { background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.03); }
    </style>
</head>
<body class="text-slate-200 min-h-screen p-6 lg:p-12 relative overflow-x-hidden">
    <div class="fixed inset-0 -z-10">
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-900/30 blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-cyan-900/30 blur-[120px]"></div>
    </div>

    <div class="max-w-6xl mx-auto">
        <div class="glass-card p-6 rounded-[2rem] flex justify-between items-center mb-10 shadow-xl">
            <h2 class="text-3xl font-black text-white flex items-center gap-3">
                <span class="p-2 bg-cyan-500/20 rounded-xl text-cyan-400">📝</span> Centro Segnalazioni
            </h2>
            <a href="profilo_view.php" class="bg-slate-800 px-5 py-2.5 rounded-full text-sm font-bold text-slate-300 hover:bg-slate-700 transition">← Torna al Profilo</a>
        </div>

        <?php if($successo): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 p-5 rounded-[1.5rem] mb-8 font-bold text-center shadow-lg shadow-emerald-500/10">
                ✨ <?= $successo ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-10">
            <div class="lg:col-span-2">
                <form method="POST" class="glass-card p-8 rounded-[2.5rem] space-y-6 shadow-2xl border-t border-white/10">
                    <h3 class="text-xl font-bold text-white mb-2">Crea Ticket</h3>
                    <p class="text-xs text-slate-400 mb-6">Aiutaci a migliorare la libreria. Guadagni XP per ogni segnalazione utile!</p>

                    <select name="id_libreria" required class="w-full bg-slate-900/80 border border-white/10 p-4 rounded-2xl outline-none focus:border-cyan-500 transition text-slate-200">
                        <option value="" disabled selected>Seleziona la tua Libreria...</option>
                        <?php
                        $lib_res = $conn->query("SELECT id, nome FROM libreria ORDER BY nome");
                        while ($lib = $lib_res->fetch_assoc()) {
                            echo "<option value='" . $lib['id'] . "'>" . htmlspecialchars($lib['nome']) . "</option>";
                        }
                        ?>
                    </select>

                    <input type="text" name="oggetto" placeholder="Oggetto (es. Libro danneggiato)" required 
                           class="w-full bg-slate-900/80 border border-white/10 p-4 rounded-2xl outline-none focus:border-cyan-500 transition">
                    
                    <textarea name="messaggio" rows="5" placeholder="Descrivi il problema..." required 
                              class="w-full bg-slate-900/80 border border-white/10 p-4 rounded-2xl outline-none focus:border-cyan-500 transition resize-none"></textarea>
                    
                    <button type="submit" class="w-full bg-gradient-to-r from-cyan-600 to-indigo-600 p-4 rounded-2xl font-black uppercase tracking-widest text-sm text-white hover:shadow-lg hover:shadow-cyan-500/30 transition-all active:scale-95">Invia Segnalazione</button>
                </form>
            </div>

            <div class="lg:col-span-3 space-y-6">
                <h3 class="text-2xl font-black text-white mb-4">I tuoi ticket recenti</h3>
                <div class="grid grid-cols-1 gap-4">
                    <?php
                    $res = $conn->query("SELECT s.*, l.nome as nome_libreria FROM segnalazioni s LEFT JOIN libreria l ON s.id_libreria = l.id WHERE s.id_utente = $userId ORDER BY data_invio DESC");
                    if ($res && $res->num_rows > 0):
                        while($row = $res->fetch_assoc()):
                            $isChiusa = $row['stato'] == 'chiusa';
                    ?>
                    <div class="glass-card p-6 rounded-[2rem] border-l-4 <?= $isChiusa ? 'border-l-emerald-500' : 'border-l-cyan-500' ?> transition hover:bg-slate-800/40">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <span class="text-[10px] bg-slate-800 text-slate-400 px-2 py-1 rounded-md mb-2 inline-block"><?= htmlspecialchars($row['nome_libreria']) ?></span>
                                <h4 class="font-bold text-xl text-white"><?= htmlspecialchars($row['oggetto']) ?></h4>
                            </div>
                            <span class="text-[10px] font-black uppercase px-3 py-1 rounded-full <?= $isChiusa ? 'bg-emerald-500/20 text-emerald-400' : 'bg-cyan-500/20 text-cyan-400' ?>">
                                <?= $row['stato'] ?>
                            </span>
                        </div>
                        <p class="text-sm text-slate-400 line-clamp-2 italic">"<?= htmlspecialchars($row['messaggio']) ?>"</p>
                        
                        <?php if($isChiusa && !empty($row['risposta_admin'])): ?>
                            <div class="mt-4 p-4 bg-slate-900/80 rounded-2xl text-sm text-slate-300 border border-white/5">
                                <strong class="text-emerald-400">Risposta dello Staff:</strong><br>
                                <?= htmlspecialchars($row['risposta_admin']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php 
                        endwhile; 
                    else:
                        echo "<div class='glass-card p-10 rounded-[2rem] text-center text-slate-500 border-dashed border-white/10'>Non hai ancora aperto nessun ticket. Ottimo lavoro!</div>";
                    endif; 
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>