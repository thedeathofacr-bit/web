<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id_utente = $_SESSION['user_id'];
$email_utente = $_SESSION['email'];

// 1. Dati Utente Base e XP
$stmt = $conn->prepare("SELECT nome, username, punti_esperienza FROM utenti WHERE id = ?");
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$nome = !empty($user_data['nome']) ? $user_data['nome'] : $user_data['username'];
$xp = (int)$user_data['punti_esperienza'];

// 2. Calcolo Posizione Classifica Globale (Corretto il termine riservato "rank")
$stmt_rank = $conn->prepare("SELECT COUNT(*) as posizione_classifica FROM utenti WHERE punti_esperienza > ?");
$stmt_rank->bind_param("i", $xp);
$stmt_rank->execute();
$rank_data = $stmt_rank->get_result()->fetch_assoc();
$posizione = (int)$rank_data['posizione_classifica'] + 1; // +1 perché se 0 hanno più punti, sei 1°

// 3. Totale Libri Letti (Prestiti)
$stmt_libri = $conn->prepare("SELECT COUNT(*) as tot FROM prestiti WHERE email_cliente = ?");
$stmt_libri->bind_param("s", $email_utente);
$stmt_libri->execute();
$libri_letti = $stmt_libri->get_result()->fetch_assoc()['tot'];

// 4. Genere Preferito
$genere_preferito = "Sconosciuto";
$stmt_genere = $conn->prepare("
    SELECT l.genere, COUNT(*) as conteggio 
    FROM prestiti p JOIN libri l ON p.libro_id = l.id 
    WHERE p.email_cliente = ? AND l.genere IS NOT NULL AND l.genere != ''
    GROUP BY l.genere ORDER BY conteggio DESC LIMIT 1
");
$stmt_genere->bind_param("s", $email_utente);
$stmt_genere->execute();
$res_genere = $stmt_genere->get_result();
if ($res_genere->num_rows > 0) {
    $genere_preferito = $res_genere->fetch_assoc()['genere'];
}

// 5. Totale Recensioni
$stmt_rec = $conn->prepare("SELECT COUNT(*) as tot FROM recensioni WHERE id_utente = ?");
$stmt_rec->bind_param("i", $id_utente);
$stmt_rec->execute();
$tot_recensioni = $stmt_rec->get_result()->fetch_assoc()['tot'];

// Se non ha letto nulla, mostriamo un messaggio
$ha_dati = ($libri_letti > 0 || $xp > 0);
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Nexus Wrapped</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: white; overflow: hidden; margin: 0; padding: 0; height: 100vh; width: 100vw; }
        
        /* Contenitore Principale della Storia */
        .story-container { width: 100%; height: 100%; position: relative; display: flex; flex-direction: column; }
        
        /* Barre di progresso in alto */
        .progress-container { display: flex; gap: 6px; padding: 15px 10px; position: absolute; top: 0; left: 0; width: 100%; z-index: 100; }
        .progress-bar { flex: 1; height: 4px; background: rgba(255, 255, 255, 0.2); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; width: 0%; background: white; border-radius: 4px; }
        
        /* Slides */
        .slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; items-center; text-align: center; padding: 2rem; opacity: 0; pointer-events: none; transition: opacity 0.4s ease; }
        .slide.active { opacity: 1; pointer-events: auto; }
        
        /* Aree cliccabili per andare avanti/indietro */
        .click-area-left { position: absolute; top: 50px; left: 0; width: 30%; height: calc(100% - 50px); z-index: 90; cursor: pointer; }
        .click-area-right { position: absolute; top: 50px; right: 0; width: 70%; height: calc(100% - 50px); z-index: 90; cursor: pointer; }

        /* Sfondi Animati per le varie slide */
        .bg-gradient-1 { background: radial-gradient(circle at center, #1e1b4b 0%, #020617 100%); }
        .bg-gradient-2 { background: radial-gradient(circle at center, #064e3b 0%, #020617 100%); }
        .bg-gradient-3 { background: radial-gradient(circle at center, #4c1d95 0%, #020617 100%); }
        .bg-gradient-4 { background: radial-gradient(circle at center, #7c2d12 0%, #020617 100%); }

        /* Animazioni di entrata testo */
        .slide.active h2 { animation: slideUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards; }
        .slide.active .big-number { animation: popIn 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.3s forwards; opacity: 0; transform: scale(0.5); }
        .slide.active p { animation: slideUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) 0.6s forwards; opacity: 0; }
        
        @keyframes slideUp { from { transform: translateY(40px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 70% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); opacity: 1; } }

        .glass-btn { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body>

    <?php if (!$ha_dati): ?>
        <div class="flex flex-col items-center justify-center h-full text-center p-8 bg-gradient-1">
            <div class="text-6xl mb-6">🥺</div>
            <h1 class="text-3xl font-black mb-4">Non hai ancora dati!</h1>
            <p class="text-slate-400 mb-8">Prendi in prestito qualche libro e guadagna XP per sbloccare il tuo riepilogo.</p>
            <a href="profilo_view.php" class="glass-btn px-8 py-4 rounded-full font-bold uppercase tracking-widest text-sm">Torna al Profilo</a>
        </div>
    <?php else: ?>

    <div class="story-container" id="storyContainer">
        
        <a href="profilo_view.php" class="absolute top-5 right-5 z-[150] w-10 h-10 bg-white/10 backdrop-blur-md rounded-full flex items-center justify-center text-white/70 hover:text-white font-bold text-xl border border-white/20">✕</a>

        <div class="progress-container">
            <div class="progress-bar"><div class="progress-fill" id="fill-0"></div></div>
            <div class="progress-bar"><div class="progress-fill" id="fill-1"></div></div>
            <div class="progress-bar"><div class="progress-fill" id="fill-2"></div></div>
            <div class="progress-bar"><div class="progress-fill" id="fill-3"></div></div>
        </div>

        <div class="click-area-left" onclick="prevSlide()"></div>
        <div class="click-area-right" onclick="nextSlide()"></div>

        <div class="slide bg-gradient-1 active" id="slide-0">
            <h2 class="text-2xl font-bold text-indigo-300 uppercase tracking-widest mb-4">Nexus Wrapped</h2>
            <div class="big-number text-7xl md:text-8xl font-black text-transparent bg-clip-text bg-gradient-to-br from-indigo-400 to-cyan-300 mb-6 drop-shadow-lg leading-tight">
                Pronto,<br><?= htmlspecialchars($nome) ?>?
            </div>
            <p class="text-xl text-slate-300 font-medium">È il momento di scoprire come hai vissuto la tua libreria quest'anno.</p>
        </div>

        <div class="slide bg-gradient-2" id="slide-1">
            <h2 class="text-2xl font-bold text-emerald-300 uppercase tracking-widest mb-4">La tua fame di sapere</h2>
            <p class="text-xl text-slate-300 font-medium mb-4 opacity-0">Hai divorato ben</p>
            <div class="big-number text-9xl font-black text-transparent bg-clip-text bg-gradient-to-br from-emerald-400 to-teal-200 mb-2 drop-shadow-lg">
                <?= $libri_letti ?>
            </div>
            <p class="text-3xl font-black text-white mb-6 opacity-0">Libri</p>
            <p class="text-lg text-emerald-200/70 font-medium max-w-md mx-auto opacity-0">Ogni libro è un viaggio. Tu hai viaggiato parecchio!</p>
        </div>

        <div class="slide bg-gradient-3" id="slide-2">
            <h2 class="text-xl font-bold text-fuchsia-300 uppercase tracking-widest mb-6">I tuoi gusti</h2>
            <p class="text-2xl text-white font-medium mb-2 opacity-0">Il tuo genere preferito è stato...</p>
            <div class="big-number text-6xl md:text-7xl font-black text-transparent bg-clip-text bg-gradient-to-br from-fuchsia-400 to-purple-200 mb-10 drop-shadow-lg">
                <?= htmlspecialchars($genere_preferito) ?>
            </div>
            
            <?php if($tot_recensioni > 0): ?>
            <div class="glass-btn p-6 rounded-3xl mx-auto max-w-sm border border-fuchsia-500/30 opacity-0 relative z-10">
                <div class="text-4xl mb-2">✍️</div>
                <p class="text-lg text-slate-200">Hai anche condiviso la tua opinione scrivendo <strong class="text-fuchsia-400"><?= $tot_recensioni ?> recensioni</strong>. La community ti ringrazia!</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="slide bg-gradient-4" id="slide-3">
            <h2 class="text-2xl font-bold text-orange-300 uppercase tracking-widest mb-4">La tua Eredità</h2>
            <p class="text-xl text-slate-300 font-medium mb-4 opacity-0">Hai accumulato un totale di</p>
            <div class="big-number text-8xl font-black text-transparent bg-clip-text bg-gradient-to-br from-orange-400 to-yellow-200 mb-2 drop-shadow-lg">
                <?= $xp ?> XP
            </div>
            <p class="text-xl text-white font-medium mb-10 opacity-0">piazzandoti al <strong class="text-orange-400 text-3xl">#<?= $posizione ?></strong> posto in classifica globale!</p>
            
            <div class="opacity-0 relative z-[100]" style="animation: slideUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) 1.2s forwards;">
                <button onclick="shareResult()" class="bg-gradient-to-r from-orange-500 to-red-500 text-white font-black px-8 py-4 rounded-full uppercase tracking-widest shadow-[0_0_30px_rgba(249,115,22,0.4)] hover:scale-105 transition-transform active:scale-95 mb-4 block mx-auto">
                    Condividi Risultato
                </button>
                <a href="profilo_view.php" class="text-sm font-bold text-slate-400 uppercase tracking-widest hover:text-white transition-colors relative z-[100]">Torna alla realtà →</a>
            </div>
        </div>

    </div>

    <script>
        const totalSlides = 4;
        let currentSlide = 0;
        let timer;
        const slideDuration = 5000; // 5 secondi per slide
        let startTime;
        let animationFrame;

        function updateProgress() {
            const now = Date.now();
            const elapsed = now - startTime;
            const percentage = Math.min((elapsed / slideDuration) * 100, 100);
            
            document.getElementById(`fill-${currentSlide}`).style.width = `${percentage}%`;

            if (percentage >= 100) {
                nextSlide();
            } else {
                animationFrame = requestAnimationFrame(updateProgress);
            }
        }

        function resetProgress() {
            for (let i = 0; i < totalSlides; i++) {
                const fill = document.getElementById(`fill-${i}`);
                if (i < currentSlide) {
                    fill.style.width = '100%';
                } else {
                    fill.style.width = '0%';
                }
            }
        }

        function showSlide(index) {
            cancelAnimationFrame(animationFrame);
            
            document.querySelectorAll('.slide').forEach(s => s.classList.remove('active'));
            document.getElementById(`slide-${index}`).classList.add('active');
            
            resetProgress();
            
            if (index === 3) {
                // Spara i coriandoli nell'ultima slide!
                setTimeout(() => {
                    confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 }, colors: ['#f97316', '#fcd34d', '#ffffff'] });
                }, 500);
                
                // L'ultima slide non va avanti da sola
                document.getElementById(`fill-${index}`).style.width = '100%';
            } else {
                startTime = Date.now();
                animationFrame = requestAnimationFrame(updateProgress);
            }
        }

        function nextSlide() {
            if (currentSlide < totalSlides - 1) {
                currentSlide++;
                showSlide(currentSlide);
            }
        }

        function prevSlide() {
            if (currentSlide > 0) {
                currentSlide--;
                showSlide(currentSlide);
            } else {
                // Se sei alla prima e torni indietro, ricarica la barra
                showSlide(0);
            }
        }

        function shareResult() {
            const text = `📚 Il mio anno su Nexus Library!\nHo letto <?= $libri_letti ?> libri, il mio genere è <?= htmlspecialchars($genere_preferito) ?> e ho <?= $xp ?> XP! Sono #<?= $posizione ?> in classifica globale! 🏆`;
            if (navigator.share) {
                navigator.share({ title: 'Nexus Wrapped', text: text });
            } else {
                alert("Copia questo testo per condividerlo:\n\n" + text);
            }
        }

        // Avvia la storia
        showSlide(0);
    </script>
    <?php endif; ?>
</body>
</html>