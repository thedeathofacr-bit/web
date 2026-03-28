<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id_utente = $_SESSION['user_id'];
$email_utente = $_SESSION['email']; // Usata per i prestiti
$id_libro = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_libro === 0) {
    header("Location: index.php");
    exit;
}

// 1. Recupero Info Libro e Statistiche Voti
$stmt = $conn->prepare("
    SELECT l.*, COALESCE(AVG(r.voto), 0) AS media_voti, COUNT(r.id) AS num_recensioni 
    FROM libri l 
    LEFT JOIN recensioni r ON r.id_libro = l.id 
    WHERE l.id = ? 
    GROUP BY l.id
");
$stmt->bind_param("i", $id_libro);
$stmt->execute();
$libro = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$libro) die("<h1 style='color:white; text-align:center; margin-top:50px;'>Libro non trovato.</h1>");

// 2. Controllo se è nei Desideri
$stmt_wish = $conn->prepare("SELECT id FROM lista_desideri WHERE id_utente = ? AND id_libro = ?");
$stmt_wish->bind_param("ii", $id_utente, $id_libro);
$stmt_wish->execute();
$in_wishlist = $stmt_wish->get_result()->num_rows > 0;
$stmt_wish->close();

// 3. Controllo se è attualmente in Prestito
$stmt_prestito = $conn->prepare("SELECT id FROM prestiti WHERE email_cliente = ? AND libro_id = ?");
$stmt_prestito->bind_param("si", $email_utente, $id_libro);
$stmt_prestito->execute();
$in_prestito = $stmt_prestito->get_result()->num_rows > 0;
$stmt_prestito->close();

// 4. Controllo se è Acquistato (Novità E-Reader!)
$acquistato = false;
$stmt_acq = $conn->prepare("SELECT id FROM acquisti WHERE id_utente = ? AND id_libro = ?");
if ($stmt_acq) { // Evita errori se la tabella non è stata ancora creata (prima del primissimo checkout)
    $stmt_acq->bind_param("ii", $id_utente, $id_libro);
    $stmt_acq->execute();
    $acquistato = $stmt_acq->get_result()->num_rows > 0;
    $stmt_acq->close();
}

// Funzione intelligente per le immagini
function getImageUrl($imgStr) {
    $imgStr = trim((string)$imgStr);
    if (empty($imgStr)) return 'uploads/placeholder.png';
    if (strpos($imgStr, 'http://') === 0 || strpos($imgStr, 'https://') === 0) return $imgStr;
    if (strpos($imgStr, 'uploads/') === 0) return $imgStr;
    return 'uploads/' . $imgStr;
}

// Generatore Stelle
function getStelle($media) {
    $html = "";
    for ($i = 1; $i <= 5; $i++) {
        $html .= ($i <= round($media)) ? "<span class='text-yellow-400 drop-shadow-[0_0_5px_rgba(250,204,21,0.5)]'>★</span>" : "<span class='text-slate-700'>★</span>";
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($libro['titolo']) ?> | Nexus</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .book-shadow { box-shadow: -20px 20px 30px rgba(0,0,0,0.8), inset 2px 0 5px rgba(255,255,255,0.2); }
    </style>
</head>
<body class="min-h-screen relative pb-20 selection:bg-cyan-500 selection:text-white">

    <div class="fixed inset-0 -z-20">
        <div class="absolute top-[0%] right-[10%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
        <div class="absolute bottom-[0%] left-[10%] w-[50%] h-[50%] rounded-full bg-indigo-900/10 blur-[150px]"></div>
    </div>

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="javascript:history.back()" class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors font-bold text-sm uppercase tracking-wider bg-slate-800/50 px-4 py-2 rounded-full border border-white/5">
                <span>←</span> Indietro
            </a>
            <h1 class="text-xl font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                Dettaglio <span class="text-cyan-400">Libro</span>
            </h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 lg:px-6">
        
        <div class="glass-panel rounded-[3rem] p-8 md:p-12 shadow-2xl border border-white/10 relative overflow-hidden flex flex-col lg:flex-row gap-12">
            
            <div class="w-full lg:w-1/3 flex flex-col items-center relative z-10">
                <div class="relative group perspective-1000 mb-8">
                    <img src="<?= htmlspecialchars(getImageUrl($libro['immagine'])) ?>" alt="Copertina" class="w-64 h-96 object-cover rounded-r-2xl rounded-l-md book-shadow transition-transform duration-500 group-hover:rotate-y-12">
                    
                    <button id="btnWish" data-id="<?= $id_libro ?>" class="absolute -top-4 -right-4 w-14 h-14 bg-slate-900 rounded-full flex items-center justify-center border border-white/10 shadow-2xl hover:scale-110 transition-transform text-2xl z-20 <?= $in_wishlist ? "text-rose-500" : "text-slate-600" ?>">
                        <?= $in_wishlist ? "❤️" : "🤍" ?>
                    </button>
                </div>

                <div class="text-center w-full">
                    <p class="text-[10px] font-black uppercase text-slate-500 tracking-widest mb-1">Valore Catalogo</p>
                    <p class="text-5xl font-black text-emerald-400 drop-shadow-md mb-8">€ <?= number_format($libro['prezzo'], 2, ',', '.') ?></p>
                </div>
            </div>

            <div class="w-full lg:w-2/3 flex flex-col relative z-10">
                
                <div class="mb-6 flex flex-wrap gap-2">
                    <span class="bg-slate-800 border border-white/5 text-slate-300 px-3 py-1 rounded-md text-xs font-black uppercase tracking-widest"><?= htmlspecialchars($libro['genere'] ?? 'Varie') ?></span>
                    <span class="bg-slate-800 border border-white/5 text-slate-300 px-3 py-1 rounded-md text-xs font-black uppercase tracking-widest">ISBN: <?= htmlspecialchars($libro['isbn'] ?? 'N/A') ?></span>
                    <span class="bg-slate-800 border border-white/5 text-slate-300 px-3 py-1 rounded-md text-xs font-black uppercase tracking-widest"><?= htmlspecialchars($libro['anno_pubblicazione'] ?? 'N/A') ?></span>
                </div>

                <h2 class="text-4xl md:text-5xl font-black text-white tracking-tight mb-2 leading-tight"><?= htmlspecialchars($libro['titolo']) ?></h2>
                <p class="text-xl font-bold text-cyan-400 mb-6">di <?= htmlspecialchars($libro['autore']) ?></p>

                <div class="flex items-center gap-3 mb-8 bg-black/30 w-max px-4 py-2 rounded-2xl border border-white/5">
                    <div class="text-xl"><?= getStelle($libro['media_voti']) ?></div>
                    <span class="text-sm font-bold text-slate-300"><?= number_format($libro['media_voti'], 1) ?> <span class="text-slate-500 font-normal">(<?= $libro['num_recensioni'] ?> recensioni)</span></span>
                </div>

                <div class="prose prose-invert mb-10 max-w-none text-slate-300">
                    <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 mb-3">Trama</h3>
                    <p class="leading-relaxed"><?= !empty($libro['descrizione']) ? nl2br(htmlspecialchars($libro['descrizione'])) : "Nessuna descrizione disponibile per questo libro. Scopri il mistero pagina dopo pagina." ?></p>
                </div>

                <div class="mt-auto grid grid-cols-1 sm:grid-cols-2 gap-4">
                    
                    <?php if($in_prestito): ?>
                        <div class="bg-slate-800 border border-cyan-500/50 text-cyan-400 font-black px-6 py-4 rounded-2xl uppercase tracking-widest shadow-lg text-center flex items-center justify-center gap-2">
                            <span>📖</span> In tuo possesso
                        </div>
                    <?php else: ?>
                        <a href="prestiti.php" class="bg-slate-800 hover:bg-slate-700 text-white font-black px-6 py-4 rounded-2xl uppercase tracking-widest shadow-lg border border-white/10 hover:-translate-y-1 transition-all flex items-center justify-center gap-3 text-center">
                            <span>📚</span> Richiedi Cartaceo
                        </a>
                    <?php endif; ?>

                    <?php if($acquistato || $in_prestito): ?>
                        <a href="leggi_libro.php?id=<?= $id_libro ?>" class="bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 text-white font-black px-6 py-4 rounded-2xl uppercase tracking-widest shadow-lg shadow-cyan-500/30 hover:-translate-y-1 transition-all flex items-center justify-center gap-3 text-center">
                            <span class="text-xl">📱</span> Leggi Ora
                        </a>
                    <?php else: ?>
                        <a href="checkout.php?id_libro=<?= $id_libro ?>" class="bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-black px-6 py-4 rounded-2xl uppercase tracking-widest shadow-lg shadow-emerald-500/30 hover:-translate-y-1 transition-all flex items-center justify-center gap-3 text-center">
                            <span class="text-xl">💳</span> Compra Ebook
                        </a>
                    <?php endif; ?>

                </div>
            </div>
        </div>

    </main>

    <script>
        // Logica per aggiungere/rimuovere dai Desideri senza ricaricare la pagina
        const btnWish = document.getElementById('btnWish');
        if (btnWish) {
            btnWish.addEventListener('click', async function() {
                const idLibro = this.dataset.id;
                this.disabled = true;
                this.classList.add('animate-pulse');
                
                const fd = new FormData();
                fd.append('action', 'toggle');
                fd.append('id_libro', idLibro);
                
                try {
                    const res = await fetch('api_lista_desideri.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if (data.success) {
                        if (data.in_lista) {
                            this.innerHTML = '❤️';
                            this.classList.add('text-rose-500');
                            this.classList.remove('text-slate-600');
                        } else {
                            this.innerHTML = '🤍';
                            this.classList.remove('text-rose-500');
                            this.classList.add('text-slate-600');
                        }
                    }
                } catch(e) {
                    console.error(e);
                } finally {
                    this.disabled = false;
                    this.classList.remove('animate-pulse');
                }
            });
        }
    </script>
</body>
</html>