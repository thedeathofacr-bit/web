<?php
header('Content-Type: text/html; charset=utf-8');
include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id_utente = $_SESSION['user_id'];
$id_libro = isset($_GET['id_libro']) ? (int)$_GET['id_libro'] : 0;

if ($id_libro === 0) die("Libro non specificato.");

// 1. Creiamo la tabella acquisti automaticamente se non esiste
$conn->query("CREATE TABLE IF NOT EXISTS acquisti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    id_libro INT NOT NULL,
    data_acquisto DATETIME DEFAULT CURRENT_TIMESTAMP,
    prezzo DECIMAL(10,2) NOT NULL
)");

// Recupero dati del libro
$stmt = $conn->prepare("SELECT titolo, autore, prezzo, immagine FROM libri WHERE id = ?");
$stmt->bind_param("i", $id_libro);
$stmt->execute();
$libro = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$libro) die("Libro non trovato.");

// Controllo se l'ha già comprato
$stmt_check = $conn->prepare("SELECT id FROM acquisti WHERE id_utente = ? AND id_libro = ?");
$stmt_check->bind_param("ii", $id_utente, $id_libro);
$stmt_check->execute();
$gia_comprato = $stmt_check->get_result()->num_rows > 0;
$stmt_check->close();

// --- RECUPERO IL VERO WALLET DALLA TUA TABELLA ---
$stmt_carta = $conn->prepare("SELECT * FROM carte_credito WHERE id_utente = ?");
$stmt_carta->bind_param("i", $id_utente);
$stmt_carta->execute();
$carta = $stmt_carta->get_result()->fetch_assoc();
$stmt_carta->close();

$saldo_attuale = $carta ? (float)$carta['saldo'] : 0.00;
$successo = false;
$messaggio = '';

// --- LOGICA REALE DI PAGAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$gia_comprato) {
    $prezzo = (float)$libro['prezzo'];

    if (!$carta) {
        $messaggio = "<div class='bg-red-500/20 text-red-400 border border-red-500/50 p-4 rounded-xl mb-6 font-bold text-sm text-center'>❌ Non hai ancora una Nexus Card. Generala dal tuo Profilo.</div>";
    } elseif ($saldo_attuale >= $prezzo) {
        
        // 1. Scaliamo i soldi dal Wallet
        $nuovo_saldo = $saldo_attuale - $prezzo;
        $stmt_upd_wallet = $conn->prepare("UPDATE carte_credito SET saldo = ? WHERE id_utente = ?");
        $stmt_upd_wallet->bind_param("di", $nuovo_saldo, $id_utente);
        
        if ($stmt_upd_wallet->execute()) {
            // 2. Registriamo l'acquisto
            $stmt_buy = $conn->prepare("INSERT INTO acquisti (id_utente, id_libro, prezzo) VALUES (?, ?, ?)");
            $stmt_buy->bind_param("iid", $id_utente, $id_libro, $prezzo);
            $stmt_buy->execute();
            $stmt_buy->close();
            
            $successo = true;
            $gia_comprato = true;
            $saldo_attuale = $nuovo_saldo; // Aggiorna il saldo a schermo
        } else {
            $messaggio = "<div class='bg-red-500/20 text-red-400 border border-red-500/50 p-4 rounded-xl mb-6 text-sm text-center'>❌ Errore durante la transazione. Riprova.</div>";
        }
        $stmt_upd_wallet->close();
    } else {
        $messaggio = "<div class='bg-red-500/20 text-red-400 border border-red-500/50 p-4 rounded-xl mb-6 font-bold text-sm text-center'>❌ Saldo insufficiente. Hai " . number_format($saldo_attuale, 2) . "€. Ricarica dal profilo.</div>";
    }
}

function getImageUrl($imgStr) {
    $imgStr = trim((string)$imgStr);
    if (empty($imgStr)) return 'uploads/placeholder.png';
    if (strpos($imgStr, 'http') === 0 || strpos($imgStr, 'uploads/') === 0) return $imgStr;
    return 'uploads/' . $imgStr;
}
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Nexus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: white; }
        .glass-panel { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .input-glass { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white; outline: none; transition: all 0.3s; }
        .input-glass:focus { border-color: #06b6d4; box-shadow: 0 0 10px rgba(6,182,212,0.3); }
        
        /* Carta di Credito 3D */
        .credit-card { background: linear-gradient(135deg, #0f172a, #1e1b4b, #06b6d4); border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 2px 2px rgba(255,255,255,0.2); }
        .chip { width: 45px; height: 35px; background: linear-gradient(135deg, #fbbf24, #d97706); border-radius: 8px; border: 1px solid rgba(0,0,0,0.2); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-0 right-0 w-96 h-96 bg-cyan-600/20 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-indigo-600/20 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-4xl grid md:grid-cols-2 gap-8 glass-panel p-8 rounded-[3rem] shadow-2xl relative">
        
        <?php if ($successo): ?>
            <div class="col-span-1 md:col-span-2 text-center py-16">
                <div class="w-24 h-24 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-emerald-400">
                    <span class="text-5xl">✓</span>
                </div>
                <h2 class="text-4xl font-black text-white mb-4">Pagamento Riuscito!</h2>
                <p class="text-slate-400 text-lg mb-10">Hai acquistato l'e-book "<?= htmlspecialchars($libro['titolo']) ?>".</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="leggi_libro.php?id=<?= $id_libro ?>" class="bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-black px-8 py-4 rounded-2xl uppercase tracking-widest shadow-lg shadow-cyan-500/30 hover:scale-105 transition-transform">📖 Inizia a Leggere</a>
                    <a href="scaffale.php" class="bg-slate-800 text-white font-bold px-8 py-4 rounded-2xl uppercase tracking-widest hover:bg-slate-700 transition-colors">Vai allo Scaffale</a>
                </div>
            </div>
        <?php elseif ($gia_comprato): ?>
            <div class="col-span-1 md:col-span-2 text-center py-16">
                <div class="text-6xl mb-6">📚</div>
                <h2 class="text-3xl font-black text-white mb-4">Possiedi già questo libro!</h2>
                <p class="text-slate-400 mb-8">Non c'è bisogno di comprarlo di nuovo. Puoi leggerlo subito.</p>
                <a href="leggi_libro.php?id=<?= $id_libro ?>" class="bg-cyan-600 text-white font-black px-8 py-4 rounded-2xl uppercase tracking-widest shadow-lg hover:bg-cyan-500 transition-colors">📖 Leggi Ora</a>
            </div>
        <?php else: ?>
            <div>
                <a href="javascript:history.back()" class="text-slate-400 hover:text-white mb-6 inline-block font-bold text-sm">← Torna indietro</a>
                <h2 class="text-3xl font-black mb-2">Checkout</h2>
                <p class="text-slate-400 mb-8">Acquista la versione E-book ad alta risoluzione.</p>

                <div class="flex gap-4 items-center bg-black/40 p-4 rounded-2xl border border-white/5 mb-8">
                    <img src="<?= getImageUrl($libro['immagine']) ?>" class="w-16 h-24 object-cover rounded-lg shadow-md">
                    <div>
                        <h4 class="font-bold text-white text-lg leading-tight"><?= htmlspecialchars($libro['titolo']) ?></h4>
                        <p class="text-sm text-slate-400 mb-2"><?= htmlspecialchars($libro['autore']) ?></p>
                        <p class="text-2xl font-black text-cyan-400">€ <?= number_format($libro['prezzo'], 2, ',', '.') ?></p>
                    </div>
                </div>

                <?= $messaggio ?>

                <div class="bg-slate-900/50 p-6 rounded-2xl border border-white/5 mb-6">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Saldo Nexus Card</p>
                    <p class="text-3xl font-black <?= $saldo_attuale >= $libro['prezzo'] ? 'text-emerald-400' : 'text-red-400' ?>">
                        € <?= number_format($saldo_attuale, 2, ',', '.') ?>
                    </p>
                    <?php if($saldo_attuale < $libro['prezzo']): ?>
                        <p class="text-xs text-red-400 mt-2">Saldo non sufficiente per l'acquisto.</p>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <button type="submit" <?= ($saldo_attuale < $libro['prezzo'] || !$carta) ? 'disabled' : '' ?> class="w-full <?= ($saldo_attuale >= $libro['prezzo'] && $carta) ? 'bg-emerald-600 hover:bg-emerald-500 hover:-translate-y-1 shadow-emerald-600/30' : 'bg-slate-700 cursor-not-allowed' ?> text-white font-black py-5 rounded-xl uppercase tracking-widest shadow-lg transition-all flex justify-center items-center gap-2">
                        Paga € <?= number_format($libro['prezzo'], 2, ',', '.') ?> (1-Click)
                    </button>
                    <p class="text-center text-[10px] text-slate-500 font-bold mt-3">L'importo verrà scalato direttamente dal tuo wallet.</p>
                </form>
            </div>

            <div class="hidden md:flex items-center justify-center relative">
                <div class="credit-card w-full max-w-sm aspect-[1.6/1] rounded-[2rem] p-8 flex flex-col justify-between relative overflow-hidden transform -rotate-6 hover:rotate-0 transition-transform duration-500 cursor-pointer">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-[50px] pointer-events-none"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div class="chip"></div>
                        <div class="text-white/50 text-3xl font-black italic">NEXUS</div>
                    </div>
                    <div class="relative z-10">
                        <div class="text-white/90 font-mono text-2xl tracking-[4px] mb-2 text-shadow">
                            <?= $carta ? htmlspecialchars($carta['numero_carta']) : '•••• •••• •••• ••••' ?>
                        </div>
                        <div class="flex justify-between text-white/60 font-mono text-sm uppercase">
                            <span><?= htmlspecialchars(explode(' ', $_SESSION['nome'] ?? 'USER')[0]) ?></span>
                            <span><?= $carta ? htmlspecialchars($carta['scadenza']) : '12/28' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>