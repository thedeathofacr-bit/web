<?php
session_start();
include "connessione.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id_utente = $_SESSION['user_id'];
$return_id = isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0;
$messaggio = '';
$successo = false;
$costo_abbonamento = 4.99;

// 1. Assicuriamoci che la colonna is_premium esista nella tabella utenti
$check_premium = $conn->query("SHOW COLUMNS FROM utenti LIKE 'is_premium'");
if ($check_premium && $check_premium->num_rows == 0) {
    $conn->query("ALTER TABLE utenti ADD COLUMN is_premium INT DEFAULT 0");
}

// 2. Recuperiamo i dati dell'utente per vedere se è già premium
$stmt_utente = $conn->prepare("SELECT is_premium FROM utenti WHERE id = ?");
$stmt_utente->bind_param("i", $id_utente);
$stmt_utente->execute();
$utente_data = $stmt_utente->get_result()->fetch_assoc();
$stmt_utente->close();

$is_premium = (isset($utente_data['is_premium']) && $utente_data['is_premium'] == 1);

// 3. RECUPERO IL VERO WALLET DALLA TUA TABELLA 'carte_credito'
$stmt_carta = $conn->prepare("SELECT * FROM carte_credito WHERE id_utente = ?");
$stmt_carta->bind_param("i", $id_utente);
$stmt_carta->execute();
$carta = $stmt_carta->get_result()->fetch_assoc();
$stmt_carta->close();

$saldo_attuale = $carta ? (float)$carta['saldo'] : 0.00;

// 4. ELABORAZIONE DEL PAGAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_premium) {
    $metodo = $_POST['metodo_pagamento'] ?? 'carta';

    if ($metodo === 'wallet') {
        if (!$carta) {
            $messaggio = "<div class='bg-red-500/20 text-red-400 border border-red-500/50 p-4 rounded-xl mt-4'>❌ Non hai ancora una Nexus Card. Generala dal tuo Profilo.</div>";
        } elseif ($saldo_attuale >= $costo_abbonamento) {
            $nuovo_saldo = $saldo_attuale - $costo_abbonamento;
            
            // Scaliamo i soldi dalla TUA tabella carte_credito
            $stmt_upd_wallet = $conn->prepare("UPDATE carte_credito SET saldo = ? WHERE id_utente = ?");
            $stmt_upd_wallet->bind_param("di", $nuovo_saldo, $id_utente);
            $stmt_upd_wallet->execute();
            $stmt_upd_wallet->close();

            // Attiviamo il premium
            $stmt_upd_prem = $conn->prepare("UPDATE utenti SET is_premium = 1 WHERE id = ?");
            $stmt_upd_prem->bind_param("i", $id_utente);
            
            if ($stmt_upd_prem->execute()) { 
                $successo = true; 
                $is_premium = true;
                $saldo_attuale = $nuovo_saldo; // Aggiorna per la visualizzazione
                
                if ($return_id > 0) {
                    echo "<script>setTimeout(() => { window.location.href = 'leggi_libro.php?id=$return_id'; }, 3000);</script>";
                }
            } else {
                $messaggio = "<div class='bg-red-500/20 text-red-400 border border-red-500/50 p-4 rounded-xl mt-4'>❌ Errore di sistema durante l'attivazione.</div>";
            }
            $stmt_upd_prem->close();
            
        } else {
            $messaggio = "<div class='bg-red-500/20 text-red-400 border border-red-500/50 p-4 rounded-xl mt-4'>❌ Saldo insufficiente. Hai " . number_format($saldo_attuale, 2) . "€.</div>";
        }
    } elseif ($metodo === 'carta') {
        // Simulazione approvazione con carta di credito "esterna"
        $stmt_upd_prem = $conn->prepare("UPDATE utenti SET is_premium = 1 WHERE id = ?");
        $stmt_upd_prem->bind_param("i", $id_utente);
        if ($stmt_upd_prem->execute()) { 
            $successo = true; 
            $is_premium = true;
            if ($return_id > 0) {
                echo "<script>setTimeout(() => { window.location.href = 'leggi_libro.php?id=$return_id'; }, 3000);</script>";
            }
        }
        $stmt_upd_prem->close();
    }
}
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Nexus Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: white; }
        .glass-panel { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .input-glass { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white; outline: none; transition: all 0.3s; }
        .input-glass:focus { border-color: #f59e0b; box-shadow: 0 0 10px rgba(245,158,11,0.3); }
        
        /* Carta 3D Tema Oro/Premium */
        .credit-card { background: linear-gradient(135deg, #0f172a, #1e1b4b, #d97706); border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 2px 2px rgba(255,255,255,0.2); }
        .chip { width: 45px; height: 35px; background: linear-gradient(135deg, #fbbf24, #d97706); border-radius: 8px; border: 1px solid rgba(0,0,0,0.2); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-0 right-0 w-96 h-96 bg-amber-600/20 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-indigo-600/20 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-4xl grid md:grid-cols-2 gap-8 glass-panel p-8 rounded-[3rem] shadow-2xl relative">
        
        <?php if ($successo): ?>
            <div class="col-span-1 md:col-span-2 text-center py-16">
                <div class="w-24 h-24 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-emerald-400">
                    <span class="text-5xl text-emerald-400">✓</span>
                </div>
                <h2 class="text-4xl font-black text-white mb-4">Pagamento Riuscito!</h2>
                <p class="text-slate-400 text-lg mb-10">Benvenuto in Nexus Pro. Hai sbloccato il vero potere della lettura.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <?php if($return_id > 0): ?>
                        <a href="leggi_libro.php?id=<?= $return_id ?>" class="bg-gradient-to-r from-amber-500 to-yellow-600 text-white font-black px-8 py-4 rounded-2xl uppercase tracking-widest shadow-lg shadow-amber-500/30 hover:scale-105 transition-transform">📖 Torna al Libro</a>
                    <?php else: ?>
                        <a href="scaffale.php" class="bg-gradient-to-r from-amber-500 to-yellow-600 text-white font-black px-8 py-4 rounded-2xl uppercase tracking-widest shadow-lg shadow-amber-500/30 hover:scale-105 transition-transform">📚 Vai alla Libreria</a>
                    <?php endif; ?>
                </div>
                <?php if($return_id > 0): ?>
                    <p class="text-xs text-slate-500 mt-6 animate-pulse">Reindirizzamento automatico in corso...</p>
                <?php endif; ?>
            </div>

        <?php elseif ($is_premium): ?>
            <div class="col-span-1 md:col-span-2 text-center py-16">
                <div class="text-6xl mb-6">👑</div>
                <h2 class="text-3xl font-black text-white mb-4">Sei già un utente Pro!</h2>
                <p class="text-slate-400 mb-8">Il tuo abbonamento è attivo. Hai già accesso a tutte le funzionalità premium.</p>
                <a href="<?= $return_id > 0 ? 'leggi_libro.php?id='.$return_id : 'scaffale.php' ?>" class="bg-slate-800 text-white font-black px-8 py-4 rounded-2xl uppercase tracking-widest shadow-lg hover:bg-slate-700 transition-colors">Torna indietro</a>
            </div>

        <?php else: ?>
            <div>
                <?php if($return_id > 0): ?>
                    <a href="leggi_libro.php?id=<?= $return_id ?>" class="text-slate-400 hover:text-white mb-6 inline-block font-bold text-sm">← Annulla e torna al libro</a>
                <?php else: ?>
                    <a href="scaffale.php" class="text-slate-400 hover:text-white mb-6 inline-block font-bold text-sm">← Torna alla Libreria</a>
                <?php endif; ?>
                
                <h2 class="text-3xl font-black mb-2 tracking-tight">Diventa <span class="text-amber-500">Pro</span></h2>
                <p class="text-slate-400 mb-8 text-sm">Sblocca Audiolibro IA, Sincronizzazione Cloud, Statistiche Avanzate e molto altro.</p>

                <div class="bg-black/40 p-4 rounded-2xl border border-white/5 mb-6 flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 font-bold uppercase tracking-widest">Il tuo Wallet</p>
                        <p class="text-2xl font-black <?= $saldo_attuale >= $costo_abbonamento ? 'text-emerald-400' : 'text-red-400' ?>">
                            € <?= number_format($saldo_attuale, 2, ',', '.') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 font-bold uppercase tracking-widest">Totale Da Pagare</p>
                        <p class="text-xl font-black text-amber-500">€ <?= number_format($costo_abbonamento, 2, ',', '.') ?></p>
                    </div>
                </div>

                <?= $messaggio ?>

                <form method="POST" class="space-y-4" id="payment-form">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="metodo_pagamento" value="wallet" class="peer sr-only" <?= ($carta && $saldo_attuale >= $costo_abbonamento) ? 'checked' : '' ?> onchange="toggleMetodo()">
                            <div class="p-3 rounded-xl border border-white/10 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 text-center transition-all">
                                <span class="block text-xl mb-1">💰</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-300">Nexus Wallet</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="metodo_pagamento" value="carta" class="peer sr-only" <?= (!$carta || $saldo_attuale < $costo_abbonamento) ? 'checked' : '' ?> onchange="toggleMetodo()">
                            <div class="p-3 rounded-xl border border-white/10 peer-checked:border-amber-500 peer-checked:bg-amber-500/10 text-center transition-all">
                                <span class="block text-xl mb-1">💳</span>
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-300">Altra Carta</span>
                            </div>
                        </label>
                    </div>

                    <div id="form-carta" class="space-y-4 <?= ($carta && $saldo_attuale >= $costo_abbonamento) ? 'hidden' : '' ?>">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Numero Carta Finta</label>
                            <input type="text" placeholder="4000 1234 5678 9010" maxlength="19" class="w-full input-glass p-4 rounded-xl font-mono text-sm tracking-widest" id="req-num" <?= (!$carta || $saldo_attuale < $costo_abbonamento) ? 'required' : '' ?>>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Scadenza</label>
                                <input type="text" placeholder="MM/AA" maxlength="5" class="w-full input-glass p-4 rounded-xl font-mono text-sm" id="req-scad" <?= (!$carta || $saldo_attuale < $costo_abbonamento) ? 'required' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">CVC</label>
                                <input type="password" placeholder="123" maxlength="3" class="w-full input-glass p-4 rounded-xl font-mono text-sm" id="req-cvc" <?= (!$carta || $saldo_attuale < $costo_abbonamento) ? 'required' : '' ?>>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="paga_premium" id="btn-paga" class="w-full bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-400 hover:to-yellow-500 text-white font-black py-5 rounded-xl uppercase tracking-widest mt-6 shadow-lg shadow-amber-600/30 transition-all hover:-translate-y-1">
                        Paga € <?= number_format($costo_abbonamento, 2, ',', '.') ?>
                    </button>
                </form>
            </div>

            <div class="hidden md:flex items-center justify-center relative">
                <div class="credit-card w-full max-w-sm aspect-[1.6/1] rounded-[2rem] p-8 flex flex-col justify-between relative overflow-hidden transform -rotate-6 hover:rotate-0 transition-transform duration-500 cursor-pointer">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-[50px] pointer-events-none"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div class="chip"></div>
                        <div class="text-white/80 text-2xl font-black tracking-widest">NEXUS <span class="text-amber-400">PRO</span></div>
                    </div>
                    <div class="relative z-10">
                        <div class="text-white/90 font-mono text-xl md:text-2xl tracking-[4px] mb-2 text-shadow">
                            <?= $carta ? htmlspecialchars($carta['numero_carta']) : '•••• •••• •••• ••••' ?>
                        </div>
                        <div class="flex justify-between text-white/60 font-mono text-sm uppercase mt-4">
                            <span><?= htmlspecialchars(explode(' ', $_SESSION['nome'] ?? 'USER')[0]) ?></span>
                            <span><?= $carta ? htmlspecialchars($carta['scadenza']) : '12/28' ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                function toggleMetodo() {
                    const isCarta = document.querySelector('input[name="metodo_pagamento"]:checked').value === 'carta';
                    const formCarta = document.getElementById('form-carta');
                    const btn = document.getElementById('btn-paga');
                    
                    if(isCarta) {
                        formCarta.classList.remove('hidden');
                        document.getElementById('req-num').required = true;
                        document.getElementById('req-scad').required = true;
                        document.getElementById('req-cvc').required = true;
                        btn.innerHTML = 'Paga € <?= number_format($costo_abbonamento, 2, ',', '.') ?> (Carta Esterna)';
                    } else {
                        formCarta.classList.add('hidden');
                        document.getElementById('req-num').required = false;
                        document.getElementById('req-scad').required = false;
                        document.getElementById('req-cvc').required = false;
                        btn.innerHTML = 'Paga € <?= number_format($costo_abbonamento, 2, ',', '.') ?> (Dal tuo Wallet)';
                    }
                }
                
                // Lancia la funzione all'avvio per impostare il tasto corretto in base alla preselezione
                window.onload = toggleMetodo;
            </script>
        <?php endif; ?>

    </div>
</body>
</html>