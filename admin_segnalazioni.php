<?php
include "connessione.php";
include "funzioni_gamification.php"; 
if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SESSION['ruolo'] !== 'gestore' && $_SESSION['ruolo'] !== 'admin') header("Location: index.php");

$id_libreria_admin = isset($_SESSION['id_libreria']) ? (int)$_SESSION['id_libreria'] : 0;
$admin_nome = isset($_SESSION['nome_utente']) ? htmlspecialchars($_SESSION['nome_utente']) : 'Admin';

// Recupero nome libreria per estetica
$lib_info = $conn->query("SELECT nome FROM libreria WHERE id = $id_libreria_admin");
$lib_nome = ($lib_info && $lib_info->num_rows > 0) ? htmlspecialchars($lib_info->fetch_assoc()['nome']) : 'tua libreria';

// Conteggio ticket aperti
$count_res = $conn->query("SELECT COUNT(*) as totale FROM segnalazioni WHERE stato != 'chiusa' AND id_libreria = $id_libreria_admin");
$ticket_aperti = ($count_res) ? $count_res->fetch_assoc()['totale'] : 0;

if (isset($_POST['rispondi'])) {
    $id = $_POST['id_segnalazione'];
    $risposta = $_POST['risposta'];
    $id_utente = $_POST['id_utente'];
    $oggetto_ticket = $_POST['oggetto_ticket'];

    $stmt = $conn->prepare("UPDATE segnalazioni SET risposta_admin = ?, stato = 'chiusa' WHERE id = ? AND id_libreria = ?");
    $stmt->bind_param("sii", $risposta, $id, $id_libreria_admin);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        aggiungiPunti($conn, $id_utente, 10);
        
        $msg_notifica = "Il ticket '" . htmlspecialchars($oggetto_ticket) . "' è stato risolto! Hai guadagnato +10 XP.";
        $stmt_not = $conn->prepare("INSERT INTO notifiche (id_utente, messaggio, data_invio) VALUES (?, ?, NOW())");
        if($stmt_not) {
            $stmt_not->bind_param("is", $id_utente, $msg_notifica);
            $stmt_not->execute();
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
    <title>Pannello Segnalazioni | <?= $lib_nome ?></title>
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #020617; }
        .glass-card { background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.03); }
        .ai-btn { background: linear-gradient(45deg, #8b5cf6, #3b82f6); background-size: 200% 200%; animation: gradientAI 3s ease infinite; }
        @keyframes gradientAI { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
    </style>
</head>
<body class="text-slate-200 min-h-screen">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-cyan-950/40 blur-[150px]"></div>
        <div class="absolute -bottom-[10%] -right-[10%] w-[40%] h-[40%] rounded-full bg-blue-950/40 blur-[150px]"></div>
    </div>

    <div class="max-w-7xl mx-auto p-6 md:p-10">
        <header class="glass-card p-6 rounded-[2.5rem] mb-10 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-5">
                <div class="p-4 bg-cyan-950/50 rounded-3xl text-cyan-400 border border-cyan-800/50">
                    <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <div>
                    <h1 class="text-4xl font-black text-white bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">Dashboard Ticket</h1>
                    <p class="text-cyan-400 text-sm font-semibold italic"><?= $lib_nome ?></p>
                </div>
            </div>
            <div class="bg-slate-900/60 p-4 rounded-3xl border border-white/5 flex items-center gap-3">
                <span class="text-sm">Admin: <strong><?= $admin_nome ?></strong></span>
            </div>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
            <div class="glass-card p-8 rounded-[2.5rem] flex items-center justify-between border border-cyan-900/40">
                <div>
                    <p class="text-slate-400 font-semibold mb-1">Ticket aperti</p>
                    <p class="text-6xl font-black text-cyan-400"><?= $ticket_aperti ?></p>
                </div>
                <svg class="w-12 h-12 text-cyan-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            
            <div class="glass-card p-8 rounded-[2.5rem] border border-blue-900/40 flex flex-col justify-center gap-4">
                <a href="scanner_prestiti.php" class="bg-gradient-to-r from-cyan-600 to-blue-600 p-4 rounded-2xl font-black uppercase text-white hover:shadow-lg hover:shadow-cyan-500/30 w-full text-center text-xs transition-all flex items-center justify-center gap-2 active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    Apri Scanner QR
                </a>
                <div class="flex gap-4">
                    <a href="profilo_view.php" class="bg-slate-800 p-3 rounded-2xl font-bold uppercase text-slate-300 hover:bg-slate-700 hover:text-white w-full text-center text-[10px] tracking-widest transition-all">Il mio Profilo</a>
                    <a href="mappa.php" class="bg-slate-800 p-3 rounded-2xl font-bold uppercase text-slate-300 hover:bg-slate-700 hover:text-white w-full text-center text-[10px] tracking-widest transition-all">Mappa Sedi</a>
                </div>
            </div>
        </section>

        <div class="space-y-8">
            <?php
            $query = "SELECT s.*, u.nome, u.email, u.foto_profilo FROM segnalazioni s JOIN utenti u ON s.id_utente = u.id WHERE s.stato != 'chiusa' AND s.id_libreria = ? ORDER BY s.data_invio ASC";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("i", $id_libreria_admin);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows === 0) {
                    echo "<div class='glass-card p-12 rounded-[2.5rem] text-center border border-dashed border-slate-800 text-slate-500 italic'>Nessun ticket da gestire per questa libreria.</div>";
                } else {
                    while($row = $res->fetch_assoc()) {
                        $user_img = (!empty($row['foto_profilo']) && file_exists('uploads/'.$row['foto_profilo'])) ? 'uploads/'.$row['foto_profilo'] : 'https://ui-avatars.com/api/?name='.urlencode($row['nome']).'&background=0ea5e9&color=fff&bold=true';
            ?>
                <div class="glass-card rounded-[2rem] p-8 flex flex-col xl:flex-row gap-10 border border-white/5 shadow-xl transition-all hover:border-cyan-500/30">
                    <div class="flex-1">
                        <span class="bg-cyan-950 text-cyan-400 text-[10px] font-black uppercase px-3 py-1 rounded-full border border-cyan-800/70 inline-flex items-center gap-1.5 mb-3">
                            <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></span>In Attesa
                        </span>
                        <h4 class="text-3xl font-black text-white"><?= htmlspecialchars($row['oggetto']) ?></h4>
                        <div class="bg-black/40 p-6 mt-4 rounded-3xl border-l-4 border-cyan-500 italic text-slate-300 text-lg" id="msg_<?= $row['id'] ?>">
                            "<?= htmlspecialchars($row['messaggio']) ?>"
                        </div>
                    </div>

                    <div class="w-full xl:w-96 flex flex-col gap-4">
                        <div class="bg-slate-900/80 p-5 rounded-3xl border border-white/5 flex items-center gap-4">
                            <img src="<?= $user_img ?>" class="w-14 h-14 rounded-full border-2 border-cyan-500 object-cover shadow-lg" alt="User">
                            <div class="overflow-hidden text-sm">
                                <p class="text-white font-bold truncate"><?= htmlspecialchars($row['nome']) ?></p>
                            </div>
                        </div>
                        
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="id_segnalazione" value="<?= $row['id'] ?>">
                            <input type="hidden" name="id_utente" value="<?= $row['id_utente'] ?>">
                            <input type="hidden" name="oggetto_ticket" value="<?= htmlspecialchars($row['oggetto']) ?>">
                            
                            <button type="button" onclick="generaAI(<?= $row['id'] ?>)" class="w-full ai-btn text-white p-3 rounded-2xl font-bold text-xs uppercase tracking-widest flex items-center justify-center gap-2 hover:opacity-90 transition">
                                ✨ Genera Risposta con IA
                            </button>

                            <textarea id="reply_<?= $row['id'] ?>" name="risposta" placeholder="Rispondi all'utente..." required class="w-full h-28 bg-slate-800/50 border border-white/5 p-4 rounded-2xl text-slate-100 outline-none focus:ring-2 focus:ring-cyan-500 transition resize-none"></textarea>
                            
                            <button name="rispondi" class="w-full bg-gradient-to-r from-cyan-600 to-cyan-500 text-white p-4 rounded-2xl font-black text-xs uppercase tracking-widest flex items-center justify-center gap-3 transition-all active:scale-95">
                                Rispondi e Chiudi (+10 XP)
                            </button>
                        </form>
                    </div>
                </div>
            <?php } } } ?>
        </div>
    </div>

    <script>
        function generaAI(ticketId) {
            const textarea = document.getElementById('reply_' + ticketId);
            const userMessage = document.getElementById('msg_' + ticketId).innerText;
            textarea.value = "⏳ L'IA sta scrivendo la risposta...";
            
            setTimeout(() => {
                textarea.value = `Gentile utente, grazie per la segnalazione. Abbiamo preso in carico il problema: ${userMessage} Provvederemo a risolverlo il prima possibile. Grazie per la collaborazione!`;
            }, 1500);
        }
    </script>
</body>
</html>