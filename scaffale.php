<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id_utente = $_SESSION['user_id'];
$email_utente = $_SESSION['email']; // Usiamo l'email per cercare nei prestiti
$errore_sql = "";

// Funzione intelligente per le immagini
function getImageUrl($imgStr) {
    $imgStr = trim((string)$imgStr);
    if (empty($imgStr)) return 'uploads/placeholder.png';
    if (strpos($imgStr, 'http://') === 0 || strpos($imgStr, 'https://') === 0) return $imgStr;
    if (strpos($imgStr, 'uploads/') === 0) return $imgStr;
    return 'uploads/' . $imgStr;
}

// Recuperiamo i libri: Prestiti (email), Desideri (id) e Acquisti (id)
$libri = [];
$query = "
    (SELECT l.id, l.titolo, l.autore, l.immagine, 'Prestito' as tipo 
     FROM prestiti p JOIN libri l ON p.libro_id = l.id 
     WHERE p.email_cliente = ?)
    UNION
    (SELECT l.id, l.titolo, l.autore, l.immagine, 'Desiderio' as tipo 
     FROM lista_desideri d JOIN libri l ON d.id_libro = l.id 
     WHERE d.id_utente = ?)
    UNION
    (SELECT l.id, l.titolo, l.autore, l.immagine, 'Acquistato' as tipo 
     FROM acquisti a JOIN libri l ON a.id_libro = l.id 
     WHERE a.id_utente = ?)
    ORDER BY titolo ASC
";

$stmt = $conn->prepare($query);
if ($stmt) {
    // Passiamo 3 parametri: stringa(email), intero(id), intero(id)
    $stmt->bind_param("sii", $email_utente, $id_utente, $id_utente);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Evitiamo i duplicati mostrando la priorità (Acquistato vince su Prestito/Desiderio)
        if (!isset($libri[$row['id']]) || $row['tipo'] == 'Acquistato') {
            $libri[$row['id']] = $row;
        }
    }
    $stmt->close();
} else {
    // Catturiamo eventuali errori del database (es. se non hai ancora cliccato su Compra e creato la tabella)
    $errore_sql = $conn->error;
}

// Resettiamo le chiavi dell'array e lo dividiamo in scaffali (max 8 per mensola)
$libri_finali = array_values($libri);
$scaffali = array_chunk($libri_finali, 8);
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il Mio Scaffale 3D | Nexus</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* 🪄 MAGIA 3D DELLO SCAFFALE 🪄 */
        .shelf-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            padding: 2rem 5% 0 5%;
            justify-content: center;
            align-items: flex-end;
            position: relative;
            margin-bottom: 6rem;
            min-height: 250px;
        }

        /* La mensola di vetro */
        .shelf-container::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 2%;
            right: 2%;
            height: 15px;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.02));
            backdrop-filter: blur(12px);
            border-top: 2px solid rgba(34, 211, 238, 0.4);
            border-radius: 4px;
            box-shadow: 0 20px 30px rgba(0,0,0,0.8), inset 0 2px 5px rgba(255,255,255,0.2);
            z-index: 1;
        }

        /* Riflesso luminoso sulla mensola */
        .shelf-container::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 10%;
            right: 10%;
            height: 50px;
            background: radial-gradient(ellipse at bottom, rgba(34, 211, 238, 0.15) 0%, transparent 70%);
            z-index: 0;
            pointer-events: none;
        }

        /* Il contenitore del libro per la prospettiva */
        .book-wrapper {
            perspective: 1000px;
            width: 130px;
            height: 195px;
            position: relative;
            z-index: 10;
        }

        /* Il libro fisico */
        .book-3d {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transform: rotateY(-25deg) translateZ(0);
            transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.5s ease;
            box-shadow: -15px 15px 20px rgba(0,0,0,0.6);
            cursor: pointer;
            border-radius: 2px 6px 6px 2px;
        }

        /* Il dorso del libro (crea il volume) */
        .book-3d::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 25px;
            height: 100%;
            background: linear-gradient(90deg, rgba(255,255,255,0.4), rgba(0,0,0,0.4));
            transform-origin: left;
            transform: rotateY(-90deg);
            border-radius: 4px 0 0 4px;
        }

        /* Le pagine visibili (crea lo spessore bianco a destra) */
        .book-3d::after {
            content: '';
            position: absolute;
            right: -10px;
            top: 2%;
            width: 10px;
            height: 96%;
            background: #e2e8f0;
            transform-origin: left;
            transform: rotateY(90deg);
            background-image: repeating-linear-gradient(to bottom, #cbd5e1 0px, #cbd5e1 2px, #f8fafc 2px, #f8fafc 4px);
        }

        /* Copertina frontale */
        .book-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 2px 6px 6px 2px;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 2;
        }

        /* Animazione Hover (estrae il libro) */
        .book-wrapper:hover {
            z-index: 50; /* Lo porta in primo piano */
        }
        .book-wrapper:hover .book-3d {
            transform: rotateY(0deg) translateZ(40px) translateY(-15px);
            box-shadow: 0 30px 40px rgba(0,0,0,0.8);
        }

        /* Tooltip a comparsa */
        .book-tooltip {
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s ease;
            white-space: nowrap;
            z-index: 100;
        }
        .book-wrapper:hover .book-tooltip {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen relative pb-20 selection:bg-cyan-500 selection:text-white">

    <div class="fixed inset-0 -z-20">
        <div class="absolute top-[0%] right-[10%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
        <div class="absolute bottom-[0%] left-[10%] w-[50%] h-[50%] rounded-full bg-indigo-900/10 blur-[150px]"></div>
    </div>

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="profilo_view.php" class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors font-bold text-sm uppercase tracking-wider bg-slate-800/50 px-4 py-2 rounded-full border border-white/5">
                <span>←</span> Il mio Profilo
            </a>
            <h1 class="text-xl font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                <span class="text-cyan-400 text-2xl drop-shadow-md">📚</span> La mia Libreria
            </h1>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 lg:px-6">
        
        <div class="text-center mb-16 mt-8">
            <h2 class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-500 tracking-tighter mb-4 drop-shadow-lg">Il Tuo Scaffale</h2>
            <p class="text-slate-400 font-medium text-lg">I tuoi ebook acquistati, prestiti fisici e desideri in un'unica stanza virtuale.</p>
        </div>

        <?php if (!empty($errore_sql) && strpos($errore_sql, "acquisti' doesn't exist") === false): ?>
            <div class="glass-panel p-8 text-center rounded-[2rem] border border-red-500/30 bg-red-950/20 max-w-2xl mx-auto mt-10">
                <div class="text-4xl mb-4">⚠️</div>
                <h3 class="text-xl font-black text-red-400 uppercase tracking-widest mb-2">Errore SQL Rilevato</h3>
                <p class="text-slate-300 font-mono text-sm bg-black/50 p-4 rounded-xl border border-red-500/20 break-all mb-4">
                    <?= htmlspecialchars($errore_sql) ?>
                </p>
            </div>

        <?php elseif (empty($libri_finali)): ?>
            <div class="glass-panel p-16 text-center rounded-[3rem] border border-dashed border-white/10 mt-20 max-w-2xl mx-auto">
                <div class="text-6xl mb-4 opacity-50">🕸️</div>
                <h3 class="text-xl font-black text-white uppercase tracking-widest">Lo scaffale è vuoto</h3>
                <p class="text-slate-400 mt-2 mb-6">Non hai prestiti, desideri o ebook. Visita il catalogo per iniziare la tua collezione!</p>
                <a href="index.php" class="inline-block bg-cyan-600 hover:bg-cyan-500 text-white font-bold py-3 px-8 rounded-2xl transition-all shadow-lg text-xs uppercase tracking-widest active:scale-95">Esplora il Catalogo</a>
            </div>
        <?php else: ?>

            <?php foreach ($scaffali as $indice_scaffale => $libri_mensola): ?>
                
                <div class="shelf-container">
                    <?php foreach ($libri_mensola as $libro): ?>
                        <?php 
                        // Determina il link in base al tipo di libro
                        $link_click = ($libro['tipo'] == 'Acquistato' || $libro['tipo'] == 'Prestito') ? "leggi_libro.php?id=".$libro['id'] : "dettaglio_libro.php?id=".$libro['id'];
                        ?>
                        <div class="book-wrapper" onclick="window.location.href='<?= $link_click ?>'">
                            
                            <div class="book-tooltip bg-slate-900/90 backdrop-blur-md border <?= $libro['tipo'] == 'Acquistato' ? 'border-amber-500/50' : ($libro['tipo'] == 'Prestito' ? 'border-emerald-500/50' : 'border-rose-500/50') ?> text-white p-3 rounded-xl shadow-xl text-center">
                                <p class="text-xs font-black uppercase tracking-widest <?= $libro['tipo'] == 'Acquistato' ? 'text-amber-400' : ($libro['tipo'] == 'Prestito' ? 'text-emerald-400' : 'text-rose-400') ?> mb-1">
                                    <?php 
                                        if($libro['tipo'] == 'Acquistato') echo '🛍️ Acquistato (Leggi)';
                                        else if($libro['tipo'] == 'Prestito') echo '📖 In Lettura';
                                        else echo '❤️ Desiderio';
                                    ?>
                                </p>
                                <p class="text-sm font-bold truncate w-40"><?= htmlspecialchars($libro['titolo']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($libro['autore']) ?></p>
                            </div>
                            
                            <div class="book-3d">
                                <img src="<?= htmlspecialchars(getImageUrl($libro['immagine'])) ?>" alt="Copertina" class="book-cover">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>

</body>
</html>