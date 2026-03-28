<?php
include "connessione.php";
require_user_page($conn);
$mio_id = $_SESSION['user_id'];
$id_post = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Recupero Post
$res_post = $conn->query("SELECT f.*, u.username, u.foto FROM forum_discussioni f JOIN utenti u ON f.id_utente = u.id WHERE f.id = $id_post");
$post = $res_post->fetch_assoc();

if (!$post) { header("Location: forum_lista.php"); exit(); }

// FUNZIONE TAG DINAMICI
function formatMessage($text, $conn) {
    return preg_replace_callback('/@(\w+)/', function($matches) use ($conn) {
        $username = $conn->real_escape_string($matches[1]);
        $res = $conn->query("SELECT id FROM utenti WHERE username = '$username' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            return '<a href="profilo_view.php?id='.$user['id'].'" class="text-cyan-400 font-bold hover:underline transition-all">@'.$matches[1].'</a>';
        }
        return '@'.$matches[1];
    }, htmlspecialchars($text));
}

// Gestione Invio Commento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['corpo_commento'])) {
    $comm = $conn->real_escape_string($_POST['corpo_commento']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : "NULL";
    $conn->query("INSERT INTO forum_commenti (id_discussione, id_utente, corpo_commento, parent_id) VALUES ($id_post, $mio_id, '$comm', $parent_id)");
    header("Location: forum_dettaglio.php?id=$id_post");
    exit();
}

// Recupero Commenti
$commenti = $conn->query("SELECT c.*, u.username, u.foto FROM forum_commenti c JOIN utenti u ON c.id_utente = u.id WHERE c.id_discussione = $id_post ORDER BY c.data_creazione ASC");
$tree = [];
while($c = $commenti->fetch_assoc()) {
    if ($c['parent_id'] == null) { $tree[$c['id']] = $c; $tree[$c['id']]['replies'] = []; }
    else { $tree[$c['parent_id']]['replies'][] = $c; }
}
?>

<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['titolo_discussione']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #050b14; color: #e2e8f0; }
        .glass-panel { background: rgba(30, 41, 59, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .thread-line { position: absolute; left: -25px; top: 10px; bottom: 10px; width: 2px; background: rgba(6, 182, 212, 0.2); }
    </style>
</head>
<body class="min-h-screen pb-32">

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="forum_lista.php" class="text-slate-400 hover:text-white font-bold text-sm uppercase">← Feed</a>
            <span class="text-cyan-500 font-black tracking-tighter uppercase italic">Nexus Discussion</span>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4">
        
        <div class="glass-panel p-8 rounded-[2.5rem] mb-10 border border-cyan-500/30">
            <div class="flex items-center gap-4 mb-6">
                <a href="profilo_view.php?id=<?= $post['id_utente'] ?>">
                    <img src="<?= !empty($post['foto']) ? 'uploads/profili/'.$post['foto'] : 'https://ui-avatars.com/api/?name='.$post['username'] ?>" class="w-12 h-12 rounded-full border border-white/10">
                </a>
                <div>
                    <h2 class="text-2xl font-black text-white italic"><?= htmlspecialchars($post['titolo_discussione']) ?></h2>
                    <p class="text-xs text-slate-500 italic">Creato da <a href="profilo_view.php?id=<?= $post['id_utente'] ?>" class="text-cyan-400 font-bold hover:underline">@<?= htmlspecialchars($post['username']) ?></a></p>
                </div>
            </div>
            <p class="text-lg text-slate-200 leading-relaxed italic">
                "<?= formatMessage($post['messaggio'], $conn) ?>"
            </p>
        </div>

        <div class="space-y-8">
            <?php foreach($tree as $id => $main): ?>
                <div class="flex flex-col gap-4">
                    <div class="glass-panel p-6 rounded-[2rem]">
                        <div class="flex gap-4">
                            <a href="profilo_view.php?id=<?= $main['id_utente'] ?>">
                                <img src="<?= !empty($main['foto']) ? 'uploads/profili/'.$main['foto'] : 'https://ui-avatars.com/api/?name='.$main['username'] ?>" class="w-10 h-10 rounded-full border border-white/5">
                            </a>
                            <div class="flex-1">
                                <div class="flex justify-between items-center mb-2">
                                    <a href="profilo_view.php?id=<?= $main['id_utente'] ?>" class="font-bold text-cyan-400 text-sm hover:underline italic">@<?= htmlspecialchars($main['username']) ?></a>
                                    <button onclick="prepareReply(<?= $id ?>, '<?= $main['username'] ?>')" class="text-[9px] font-black text-slate-600 hover:text-cyan-500 uppercase tracking-widest">Rispondi</button>
                                </div>
                                <p class="text-slate-300 text-sm italic"><?= formatMessage($main['corpo_commento'], $conn) ?></p>
                            </div>
                        </div>
                    </div>

                    <?php if(!empty($main['replies'])): ?>
                        <div class="ml-12 space-y-3 relative">
                            <div class="thread-line"></div>
                            <?php foreach($main['replies'] as $reply): ?>
                                <div class="glass-panel p-5 rounded-[1.5rem] bg-white/5 border-none">
                                    <div class="flex gap-3">
                                        <a href="profilo_view.php?id=<?= $reply['id_utente'] ?>">
                                            <img src="<?= !empty($reply['foto']) ? 'uploads/profili/'.$reply['foto'] : 'https://ui-avatars.com/api/?name='.$reply['username'] ?>" class="w-8 h-8 rounded-full border border-white/5">
                                        </a>
                                        <div class="flex-1">
                                            <div class="flex justify-between items-center mb-1">
                                                <a href="profilo_view.php?id=<?= $reply['id_utente'] ?>" class="font-bold text-cyan-400 text-xs italic hover:underline">@<?= htmlspecialchars($reply['username']) ?></a>
                                                <span class="text-[8px] text-slate-700 italic"><?= date("H:i", strtotime($reply['data_creazione'])) ?></span>
                                            </div>
                                            <p class="text-slate-400 text-xs italic"><?= formatMessage($reply['corpo_commento'], $conn) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="fixed bottom-6 left-0 w-full px-4 z-50">
            <div class="max-w-4xl mx-auto glass-panel p-4 rounded-[2.5rem] shadow-2xl border border-cyan-500/20">
                <form id="replyForm" action="" method="POST">
                    <input type="hidden" name="parent_id" id="parent_id_input" value="">
                    <div id="reply-info" class="hidden flex items-center justify-between px-4 py-1 bg-cyan-500/10 rounded-lg mb-2">
                        <span class="text-[9px] font-black text-cyan-500 uppercase italic" id="reply-text"></span>
                        <button type="button" onclick="cancelReply()" class="text-slate-600 text-xs">✕</button>
                    </div>
                    <div class="flex gap-4 items-center">
                        <textarea name="corpo_commento" id="corpo_commento" required placeholder="Scrivi una risposta o @tagga..." class="flex-1 bg-transparent border-none text-white focus:ring-0 resize-none py-2 px-2 text-sm h-10"></textarea>
                        <button type="submit" class="bg-cyan-500 hover:bg-cyan-400 text-white font-black px-8 py-3 rounded-2xl transition-all uppercase text-[10px] tracking-widest italic">Invia</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
        function prepareReply(parentId, username) {
            document.getElementById('parent_id_input').value = parentId;
            const textarea = document.getElementById('corpo_commento');
            textarea.value = `@${username} `;
            textarea.focus();
            
            const info = document.getElementById('reply-info');
            info.classList.remove('hidden');
            document.getElementById('reply-text').innerText = `Risposta a @${username}`;
        }

        function cancelReply() {
            document.getElementById('parent_id_input').value = "";
            document.getElementById('reply-info').classList.add('hidden');
            document.getElementById('corpo_commento').value = "";
        }
    </script>
</body>
</html>