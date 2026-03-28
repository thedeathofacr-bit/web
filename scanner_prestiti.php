<?php
include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
// Controllo di sicurezza aggiornato (basato sui ruoli che hai impostato)
if (!isset($_SESSION['ruolo']) || ($_SESSION['ruolo'] !== 'admin' && $_SESSION['ruolo'] !== 'gestore')) { 
    die("Accesso riservato allo staff della libreria."); 
}
$admin_nome = htmlspecialchars($_SESSION['nome_utente'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner QR - Gestione Libreria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #020617; }
        .glass-panel { background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* Stili per sovrascrivere la UI brutta di html5-qrcode */
        #reader { border: none !important; border-radius: 1.5rem; overflow: hidden; background: #000; }
        #reader img { display: none !important; } /* Nasconde l'icona info */
        #reader button { background: #06b6d4 !important; color: white !important; font-weight: bold !important; border-radius: 1rem !important; padding: 10px 20px !important; border: none !important; cursor: pointer; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; margin-top: 10px;}
        #reader select { background: #1e293b; color: white; padding: 10px; border-radius: 1rem; border: 1px solid rgba(255,255,255,0.1); outline: none; margin-bottom: 10px;}
        
        /* Scanner Laser Line Animation */
        .scanner-laser { position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: #06b6d4; box-shadow: 0 0 10px #06b6d4, 0 0 20px #06b6d4; animation: scan 2s linear infinite; z-index: 10; pointer-events: none;}
        @keyframes scan { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
    </style>
</head>
<body class="text-white min-h-screen flex flex-col items-center p-6 lg:p-12 relative overflow-hidden">

    <div class="fixed inset-0 -z-10">
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-blue-900/20 blur-[150px]"></div>
    </div>

    <div class="w-full max-w-4xl flex justify-between items-center mb-10">
        <a href="admin_segnalazioni.php" class="bg-slate-800/80 px-5 py-2.5 rounded-full text-xs font-bold text-slate-300 hover:text-white border border-white/5 transition shadow-lg backdrop-blur-sm">← Torna alla Dashboard</a>
        <div class="text-right">
            <p class="text-[10px] uppercase font-black text-cyan-500 tracking-widest">Terminale Staff</p>
            <p class="font-bold text-slate-300"><?= $admin_nome ?></p>
        </div>
    </div>

    <div class="w-full max-w-lg z-10">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-cyan-500/10 text-cyan-400 mb-4 border border-cyan-500/20 shadow-lg shadow-cyan-500/10">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
            </div>
            <h2 class="text-4xl font-black text-white tracking-tight">Scanner QR</h2>
            <p class="text-slate-400 text-sm mt-2 font-medium">Inquadra la tessera digitale dell'utente</p>
        </div>
        
        <div class="glass-panel p-4 rounded-[2rem] shadow-2xl relative border border-cyan-500/20 group">
            <div class="absolute inset-0 border-2 border-cyan-500 rounded-[2rem] opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none z-20 shadow-[0_0_30px_rgba(6,182,212,0.3)]"></div>
            
            <div class="relative rounded-3xl overflow-hidden bg-black">
                <div class="scanner-laser hidden" id="laser"></div>
                <div id="reader" class="w-full"></div>
            </div>
        </div>

        <div id="result" class="mt-8 hidden glass-panel p-8 border border-green-500/30 rounded-[2rem] text-center shadow-[0_0_40px_rgba(34,197,94,0.2)] transform scale-95 opacity-0 transition-all duration-500">
            <div class="w-16 h-16 mx-auto bg-green-500/20 text-green-400 rounded-full flex items-center justify-center mb-4 border border-green-500/30">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <p class="text-xs font-black text-green-400 uppercase tracking-widest mb-1">Tessera Riconosciuta</p>
            <h3 id="user_found" class="text-3xl font-black text-white"></h3>
            
            <a id="link_prestito" href="#" class="inline-block mt-6 w-full bg-gradient-to-r from-green-500 to-emerald-400 text-slate-900 px-6 py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all hover:shadow-lg hover:shadow-green-500/40 active:scale-95">
                Apri Profilo Prestito →
            </a>
            
            <button onclick="resetScanner()" class="mt-4 text-[10px] text-slate-400 uppercase font-bold tracking-widest hover:text-white transition">← Esegui nuova scansione</button>
        </div>
    </div>

    <audio id="beep-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

    <script>
        let html5QrcodeScanner;
        
        function initScanner() {
            document.getElementById('laser').classList.remove('hidden');
            html5QrcodeScanner = new Html5QrcodeScanner("reader", { 
                fps: 10, 
                qrbox: {width: 250, height: 250},
                aspectRatio: 1.0,
                showTorchButtonIfSupported: true
            });
            html5QrcodeScanner.render(onScanSuccess);
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (decodedText.startsWith("USER_ID:")) {
                const userId = decodedText.split(":")[1];
                
                // Ferma scanner e laser
                html5QrcodeScanner.clear();
                document.getElementById('laser').classList.add('hidden');

                // Suono di successo
                document.getElementById('beep-sound').play();

                // Mostra Risultato con animazione
                const resultBox = document.getElementById('result');
                resultBox.classList.remove('hidden');
                setTimeout(() => {
                    resultBox.classList.remove('scale-95', 'opacity-0');
                    resultBox.classList.add('scale-100', 'opacity-100');
                }, 50);

                // Aggiorna dati (Assumo che tu abbia una pagina gestisci_prestiti.php)
                document.getElementById('user_found').innerText = "Utente #" + userId;
                document.getElementById('link_prestito').href = "gestisci_prestiti.php?id_utente=" + userId;
            }
        }

        function resetScanner() {
            // Nasconde risultato
            const resultBox = document.getElementById('result');
            resultBox.classList.remove('scale-100', 'opacity-100');
            resultBox.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { resultBox.classList.add('hidden'); }, 500);
            
            // Riavvia
            initScanner();
        }

        // Avvio iniziale
        window.onload = initScanner;
    </script>
</body>
</html>