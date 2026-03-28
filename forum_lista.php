<?php
include "connessione.php";
require_user_page($conn);
$mio_id = $_SESSION['user_id'];

$query = "SELECT f.*, u.username, u.foto, u.livello,
          (SELECT COUNT(*) FROM forum_likes l WHERE l.id_discussione = f.id) as likes_tot,
          (SELECT COUNT(*) FROM forum_commenti c WHERE c.id_discussione = f.id) as comm_tot,
          (SELECT COUNT(*) FROM forum_likes WHERE id_utente = $mio_id AND id_discussione = f.id) as i_like
          FROM forum_discussioni f 
          JOIN utenti u ON f.id_utente = u.id 
          ORDER BY f.data_creazione DESC";
$risultato = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Nexus Feed - AJAX Like</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>
    <style>
        body { background-color: #020617; color: #e2e8f0; font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        
        /* Animazione battito per il like */
        .like-active { color: #f43f5e !important; transform: scale(1.2); filter: drop-shadow(0 0 8px #f43f5e); }
        .heart-pop { animation: heartPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes heartPop {
            0% { transform: scale(1); }
            50% { transform: scale(1.5); }
            100% { transform: scale(1.2); }
        }
    </style>
</head>
<body class="p-6">
    <div class="max-w-4xl mx-auto">
        <header class="flex justify-between items-center mb-10">
            <h1 class="text-3xl font-black italic uppercase">Nexus <span class="text-cyan-500">Live Feed</span></h1>
            <a href="profilo_view.php" class="text-xs font-bold text-slate-500 hover:text-white uppercase tracking-widest">Il Mio Profilo</a>
        </header>

        <div class="space-y-6">
            <?php while($row = $risultato->fetch_assoc()): ?>
                <div class="glass p-8 rounded-[2.5rem] border border-white/5 transition-all hover:border-cyan-500/30" data-tilt data-tilt-max="2">
                    
                    <div class="flex items-center gap-4 mb-6">
                        <img src="<?= !empty($row['foto']) ? 'uploads/profili/'.$row['foto'] : 'https://ui-avatars.com/api/?name='.$row['username'] ?>" class="w-12 h-12 rounded-full border-2 border-slate-800">
                        <div>
                            <p class="font-black text-white text-sm italic uppercase"><?= htmlspecialchars($row['username']) ?></p>
                            <p class="text-[9px] text-slate-600 uppercase font-bold"><?= date("d M, H:i", strtotime($row['data_creazione'])) ?></p>
                        </div>
                    </div>

                    <div class="cursor-pointer" onclick="location.href='forum_dettaglio.php?id=<?= $row['id'] ?>'">
                        <h2 class="text-2xl font-black text-white mb-4"><?= htmlspecialchars($row['titolo_discussione']) ?></h2>
                        <p class="text-slate-400 text-sm italic mb-6">"<?= mb_strimwidth($row['messaggio'], 0, 150, "...") ?>"</p>
                    </div>

                    <div class="flex items-center gap-6 pt-6 border-t border-white/5">
                        <button onclick="toggleLike(this, <?= $row['id'] ?>)" class="flex items-center gap-2 transition-all outline-none">
                            <span class="heart-icon text-xl transition-all <?= $row['i_like'] ? 'like-active' : 'text-slate-500' ?>">
                                <?= $row['i_like'] ? '❤️' : '🤍' ?>
                            </span>
                            <span class="like-count text-xs font-black text-slate-500"><?= $row['likes_tot'] ?></span>
                        </button>

                        <div class="flex items-center gap-2">
                            <span class="text-xl text-slate-500">💬</span>
                            <span class="text-xs font-black text-slate-500"><?= $row['comm_tot'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        async function toggleLike(btn, postId) {
            const icon = btn.querySelector('.heart-icon');
            const countLabel = btn.querySelector('.like-count');

            try {
                // Chiamata AJAX al server
                const response = await fetch(`gestisci_like.php?post_id=${postId}`);
                const data = await response.json();

                if (data.status === 'success') {
                    // Aggiorna interfaccia in base alla risposta
                    if (data.action === 'liked') {
                        icon.innerHTML = '❤️';
                        icon.classList.add('like-active', 'heart-pop');
                        icon.classList.remove('text-slate-500');
                    } else {
                        icon.innerHTML = '🤍';
                        icon.classList.remove('like-active', 'heart-pop');
                        icon.classList.add('text-slate-500');
                    }
                    
                    // Aggiorna il numero con una piccola animazione
                    countLabel.innerText = data.count;
                    
                    // Rimuove la classe animazione dopo che è finita
                    setTimeout(() => icon.classList.remove('heart-pop'), 400);
                }
            } catch (error) {
                console.error("Errore nel sistema Like:", error);
            }
        }
    </script>
</body>
</html>