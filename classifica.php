<?php
// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();

// Recuperiamo l'ID dell'utente loggato per evidenziarlo in classifica
$mio_id = $_SESSION['user_id'] ?? 0;

// Query per recuperare la Top 50 degli utenti ordinati per XP
$query = "SELECT id, username, nome, foto, punti_esperienza FROM utenti ORDER BY punti_esperienza DESC LIMIT 50";
$result = $conn->query($query);

$utenti = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $utenti[] = $row;
    }
}

// Separiamo i primi 3 (Podio) dal resto della classifica
$top3 = array_slice($utenti, 0, 3);
$altri = array_slice($utenti, 3);

// Funzione Helper per calcolare Livello e Titolo (Come nel Profilo)
function calcolaLivelloETitolo($xp) {
    $livello = floor($xp / 50) + 1;
    $titolo = "Lettore Novizio";
    if($livello >= 3) $titolo = "Esploratore";
    if($livello >= 5) $titolo = "Cavaliere";
    if($livello >= 10) $titolo = "Maestro";
    if($livello >= 20) $titolo = "Leggenda";
    return ['livello' => $livello, 'titolo' => $titolo];
}

// Funzione Helper per l'immagine
function getAvatar($nome, $foto) {
    if (!empty($foto) && file_exists("uploads/profili/" . $foto)) {
        return "uploads/profili/" . $foto;
    }
    return "https://ui-avatars.com/api/?name=" . urlencode($nome) . "&background=06b6d4&color=fff&bold=true";
}
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall of Fame | Nexus Library</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* Animazioni Avatar Podio */
        .float-avatar { animation: float 4s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
        
        /* Effetti Glow Podio */
        .glow-gold { box-shadow: 0 0 40px -10px rgba(250, 204, 21, 0.6); }
        .glow-silver { box-shadow: 0 0 30px -10px rgba(148, 163, 184, 0.5); }
        .glow-bronze { box-shadow: 0 0 30px -10px rgba(217, 119, 6, 0.5); }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen relative pb-20 selection:bg-cyan-500 selection:text-white">

    <div class="fixed inset-0 -z-20">
        <div class="absolute top-[0%] right-[20%] w-[30%] h-[30%] rounded-full bg-yellow-500/10 blur-[120px]"></div>
        <div class="absolute bottom-[10%] left-[10%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
    </div>

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors font-bold text-sm uppercase tracking-wider bg-slate-800/50 px-4 py-2 rounded-full border border-white/5">
                <span>←</span> Dashboard
            </a>
            <h1 class="text-xl font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                <span class="text-yellow-400 text-2xl drop-shadow-md">🏆</span> Hall of Fame
            </h1>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4 lg:px-6">

        <div class="text-center mb-16 mt-8">
            <h2 class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-yellow-200 to-amber-500 tracking-tighter mb-4 drop-shadow-lg">I Migliori Lettori</h2>
            <p class="text-slate-400 font-medium text-lg">Classifica globale basata sui Punti Esperienza (XP)</p>
        </div>

        <?php if (count($utenti) > 0): ?>
        
        <div class="flex flex-col md:flex-row items-end justify-center gap-4 md:gap-6 mb-20 px-4 h-[400px]">
            
            <?php if (isset($top3[1])): 
                $u = $top3[1]; $dati = calcolaLivelloETitolo($u['punti_esperienza']); $nome = !empty($u['nome']) ? $u['nome'] : $u['username'];
            ?>
            <div class="w-full md:w-1/3 flex flex-col items-center order-2 md:order-1" data-tilt data-tilt-max="5" data-tilt-scale="1.05">
                <div class="relative float-avatar mb-4" style="animation-delay: 0.5s;">
                    <img src="<?= getAvatar($nome, $u['foto']) ?>" class="w-24 h-24 rounded-full border-4 border-slate-300 object-cover shadow-2xl glow-silver">
                    <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 bg-slate-300 text-slate-900 font-black w-8 h-8 rounded-full flex items-center justify-center border-2 border-[#020617] text-lg">2</div>
                </div>
                <div class="w-full bg-gradient-to-t from-slate-900 to-slate-800 border-t-4 border-slate-300 rounded-t-3xl p-4 text-center h-[180px] shadow-2xl flex flex-col justify-start">
                    <a href="profilo_view.php?id=<?= $u['id'] ?>" class="font-black text-white text-lg truncate hover:text-cyan-400"><?= htmlspecialchars($nome) ?></a>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1"><?= $dati['titolo'] ?> (LIV <?= $dati['livello'] ?>)</p>
                    <p class="text-2xl font-black text-slate-300 mt-3 drop-shadow-md"><?= $u['punti_esperienza'] ?> <span class="text-xs">XP</span></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($top3[0])): 
                $u = $top3[0]; $dati = calcolaLivelloETitolo($u['punti_esperienza']); $nome = !empty($u['nome']) ? $u['nome'] : $u['username'];
            ?>
            <div class="w-full md:w-1/3 flex flex-col items-center order-1 md:order-2 z-10" data-tilt data-tilt-max="5" data-tilt-scale="1.05">
                <div class="text-4xl animate-bounce mb-2 drop-shadow-[0_0_10px_rgba(250,204,21,0.8)]">👑</div>
                <div class="relative float-avatar mb-4">
                    <img src="<?= getAvatar($nome, $u['foto']) ?>" class="w-32 h-32 rounded-full border-4 border-yellow-400 object-cover shadow-2xl glow-gold">
                    <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 bg-yellow-400 text-yellow-900 font-black w-10 h-10 rounded-full flex items-center justify-center border-4 border-[#020617] text-xl shadow-lg">1</div>
                </div>
                <div class="w-full bg-gradient-to-t from-yellow-900/40 to-yellow-600/20 border-t-4 border-yellow-400 rounded-t-3xl p-5 text-center h-[230px] shadow-[0_0_50px_-15px_rgba(250,204,21,0.4)] flex flex-col justify-start backdrop-blur-md">
                    <a href="profilo_view.php?id=<?= $u['id'] ?>" class="font-black text-white text-2xl truncate hover:text-yellow-300"><?= htmlspecialchars($nome) ?></a>
                    <p class="text-xs text-yellow-200 font-bold uppercase tracking-widest mt-1"><?= $dati['titolo'] ?> (LIV <?= $dati['livello'] ?>)</p>
                    <p class="text-4xl font-black text-yellow-400 mt-4 drop-shadow-md"><?= $u['punti_esperienza'] ?> <span class="text-sm text-yellow-500">XP</span></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($top3[2])): 
                $u = $top3[2]; $dati = calcolaLivelloETitolo($u['punti_esperienza']); $nome = !empty($u['nome']) ? $u['nome'] : $u['username'];
            ?>
            <div class="w-full md:w-1/3 flex flex-col items-center order-3 md:order-3" data-tilt data-tilt-max="5" data-tilt-scale="1.05">
                <div class="relative float-avatar mb-4" style="animation-delay: 1s;">
                    <img src="<?= getAvatar($nome, $u['foto']) ?>" class="w-20 h-20 rounded-full border-4 border-orange-500 object-cover shadow-2xl glow-bronze">
                    <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 bg-orange-500 text-orange-950 font-black w-8 h-8 rounded-full flex items-center justify-center border-2 border-[#020617] text-lg">3</div>
                </div>
                <div class="w-full bg-gradient-to-t from-slate-900 to-slate-800 border-t-4 border-orange-500 rounded-t-3xl p-4 text-center h-[150px] shadow-2xl flex flex-col justify-start">
                    <a href="profilo_view.php?id=<?= $u['id'] ?>" class="font-black text-white text-lg truncate hover:text-cyan-400"><?= htmlspecialchars($nome) ?></a>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1"><?= $dati['titolo'] ?> (LIV <?= $dati['livello'] ?>)</p>
                    <p class="text-2xl font-black text-orange-400 mt-2 drop-shadow-md"><?= $u['punti_esperienza'] ?> <span class="text-xs">XP</span></p>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <?php if (count($altri) > 0): ?>
        <div class="glass-panel rounded-[2.5rem] p-6 shadow-2xl">
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-6 px-4 border-b border-white/5 pb-4">Top 50 Lettori</h3>
            
            <div class="space-y-3">
                <?php 
                $posizione = 4;
                foreach ($altri as $u): 
                    $dati = calcolaLivelloETitolo($u['punti_esperienza']);
                    $nome = !empty($u['nome']) ? $u['nome'] : $u['username'];
                    $is_me = ($u['id'] == $mio_id);
                ?>
                <div class="flex items-center p-4 rounded-2xl transition-all hover:bg-white/5 border <?= $is_me ? 'border-cyan-500/50 bg-cyan-900/10' : 'border-transparent' ?> group">
                    <div class="w-10 text-center font-black text-slate-500 text-lg group-hover:text-white transition-colors">#<?= $posizione ?></div>
                    
                    <img src="<?= getAvatar($nome, $u['foto']) ?>" class="w-12 h-12 rounded-full object-cover ml-2 mr-4 border border-white/10 group-hover:border-cyan-500 transition-colors">
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <a href="profilo_view.php?id=<?= $u['id'] ?>" class="font-black text-white text-base truncate hover:underline <?= $is_me ? 'text-cyan-400' : '' ?>"><?= htmlspecialchars($nome) ?></a>
                            <?php if($is_me): ?><span class="bg-cyan-500 text-[#020617] text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-widest">Tu</span><?php endif; ?>
                        </div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?= $dati['titolo'] ?> (LIV <?= $dati['livello'] ?>)</p>
                    </div>

                    <div class="text-right">
                        <p class="font-black text-lg text-white <?= $is_me ? 'text-cyan-400' : '' ?>"><?= $u['punti_esperienza'] ?></p>
                        <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">XP</p>
                    </div>
                </div>
                <?php $posizione++; endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="glass-panel p-16 text-center rounded-[3rem]">
            <div class="text-6xl mb-4 opacity-50">😴</div>
            <h3 class="text-xl font-black text-white uppercase tracking-widest">Nessun utente trovato</h3>
            <p class="text-slate-400 mt-2">La classifica è attualmente vuota.</p>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>