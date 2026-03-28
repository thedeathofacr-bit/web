<?php
// FORZA LA CONNESSIONE SICURA HTTPS (Obbligatoria per Fotocamera e Microfono)
$is_https = false;
if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
    $is_https = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $is_https = true;
}

if (!$is_https) {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $location);
    exit;
}

// FORZA IL BROWSER A LEGGERE LE EMOJI IN UTF-8
header('Content-Type: text/html; charset=utf-8');

include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricerca Avanzata | Nexus</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* Stili Scanner sovrascritti */
        #reader { border: none !important; border-radius: 2rem; overflow: hidden; background: #000; width: 100%; box-shadow: inset 0 0 50px rgba(0,0,0,0.8); }
        #reader img { display: none !important; }
        #reader button { background: #06b6d4 !important; color: white !important; font-weight: 800 !important; border-radius: 1rem !important; padding: 12px 24px !important; border: none !important; cursor: pointer; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; margin-top: 15px; transition: all 0.3s; }
        #reader button:hover { background: #0891b2 !important; transform: scale(1.05); }
        #reader select { background: rgba(30,41,59,0.8); color: white; padding: 12px; border-radius: 1rem; border: 1px solid rgba(255,255,255,0.1); outline: none; margin-bottom: 15px; width: 100%; max-width: 300px;}
        
        /* Effetto Scanner Laser Line Animation */
        .scanner-laser { position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: #22d3ee; box-shadow: 0 0 20px #22d3ee, 0 0 40px #22d3ee; animation: scan 2s linear infinite; z-index: 10; pointer-events: none; }
        @keyframes scan { 0% { top: 0; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: 100%; opacity: 0; } }

        /* Animazione Microfono */
        .mic-pulse { animation: pulseMic 1.5s infinite; }
        @keyframes pulseMic { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
    </style>
</head>
<body class="min-h-screen relative pb-20 selection:bg-cyan-500 selection:text-white">

    <div class="fixed inset-0 -z-20">
        <div class="absolute top-[10%] right-[10%] w-[30%] h-[30%] rounded-full bg-emerald-500/10 blur-[120px]"></div>
        <div class="absolute bottom-[10%] left-[10%] w-[40%] h-[40%] rounded-full bg-cyan-900/20 blur-[150px]"></div>
    </div>

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8 shadow-lg shadow-black/20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-3 text-slate-400 hover:text-white transition-colors font-bold text-sm uppercase tracking-wider bg-slate-800/50 px-4 py-2 rounded-full border border-white/5">
                <span>←</span> Dashboard
            </a>
            <h1 class="text-xl font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                <span class="text-cyan-400 text-2xl drop-shadow-md">🔍</span> Smart Search
            </h1>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 lg:px-6">
        
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-black text-white tracking-tighter mb-4 drop-shadow-lg">Ricerca <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-blue-500">Avanzata</span></h2>
            <p class="text-slate-400 font-medium text-lg">Scansiona un codice ISBN, usa la voce o scrivi velocemente.</p>
        </div>

        <div class="grid md:grid-cols-2 gap-8">
            
            <div class="glass-panel p-8 rounded-[2.5rem] shadow-2xl relative overflow-hidden group border border-white/5 hover:border-cyan-500/30 transition-all">
                <div class="absolute -right-4 -bottom-4 text-8xl opacity-5 transition-transform group-hover:scale-110">📷</div>
                
                <h3 class="text-2xl font-black text-white mb-2 flex items-center gap-3">
                    <span class="text-cyan-400">1.</span> Scanner ISBN
                </h3>
                <p class="text-slate-400 text-sm mb-6">Inquadra il codice a barre sul retro del libro.</p>

                <div class="bg-black/50 p-2 rounded-[2rem] shadow-inner relative border border-white/5 group-hover:border-cyan-500/50 transition-colors">
                    <div class="relative rounded-[1.5rem] overflow-hidden bg-black aspect-square flex items-center justify-center">
                        <div class="scanner-laser hidden" id="laser"></div>
                        <div id="reader" class="w-full"></div>
                    </div>
                </div>
            </div>

            <div class="glass-panel p-8 rounded-[2.5rem] shadow-2xl relative overflow-hidden group border border-white/5 hover:border-emerald-500/30 transition-all flex flex-col h-full min-h-[400px]">
                <div class="absolute -left-4 -bottom-4 text-8xl opacity-5 transition-transform group-hover:scale-110">🎙️</div>
                
                <div class="text-center mb-6 relative z-10">
                    <h3 class="text-2xl font-black text-white mb-2 flex items-center justify-center gap-3">
                        <span class="text-emerald-400">2.</span> Ricerca Smart
                    </h3>
                    <p class="text-slate-400 text-sm">Tocca il microfono o digita il titolo del libro.</p>
                </div>

                <div class="text-center relative z-10 mb-6">
                    <button id="btnMic" class="w-24 h-24 mx-auto rounded-full bg-slate-800 border-4 border-slate-700 flex items-center justify-center text-4xl shadow-xl transition-all hover:scale-105 active:scale-95">
                        🎤
                    </button>
                    <div id="statusVocale" class="mt-4 text-sm font-bold text-slate-300 min-h-[20px] italic"></div>
                </div>

                <div class="flex items-center gap-4 mb-6 relative z-10 opacity-40">
                    <div class="h-px bg-white flex-1"></div>
                    <span class="text-[10px] uppercase tracking-widest font-bold text-white">OPPURE</span>
                    <div class="h-px bg-white flex-1"></div>
                </div>

                <div class="relative z-10 mt-auto">
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full">
                        <input type="text" id="testoRicerca" placeholder="Es: Harry Potter..." class="w-full bg-slate-900/80 border border-white/10 rounded-2xl px-5 py-4 text-white focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-sm shadow-inner transition-all">
                        <button id="btnTesto" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 px-6 rounded-2xl transition-all shadow-lg shadow-emerald-500/20 uppercase tracking-widest text-xs whitespace-nowrap active:scale-95">Cerca</button>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <audio id="beep-sound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

    <script>
        // ==========================================
        // 1. LOGICA SCANNER CODICE A BARRE (ISBN)
        // ==========================================
        let html5QrcodeScanner;
        
        function initScanner() {
            document.getElementById('laser').classList.remove('hidden');
            html5QrcodeScanner = new Html5QrcodeScanner("reader", { 
                fps: 10, 
                qrbox: {width: 250, height: 150},
                aspectRatio: 1.0,
                formatsToSupport: [ Html5QrcodeSupportedFormats.EAN_13, Html5QrcodeSupportedFormats.EAN_8, Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE ]
            });
            html5QrcodeScanner.render(onScanSuccess);
        }

        function onScanSuccess(decodedText, decodedResult) {
            document.getElementById('beep-sound').play();
            html5QrcodeScanner.clear();
            document.getElementById('laser').classList.add('hidden');

            document.getElementById('reader').innerHTML = `
                <div class="flex flex-col items-center justify-center h-full w-full text-center p-6 bg-cyan-900/30">
                    <span class="text-5xl mb-4">✅</span>
                    <p class="text-cyan-400 font-black uppercase tracking-widest text-sm mb-1">Codice Rilevato!</p>
                    <p class="text-white font-mono text-xl">${decodedText}</p>
                    <p class="text-slate-400 text-xs mt-4">Reindirizzamento in corso...</p>
                </div>
            `;

            setTimeout(() => {
                window.location.href = 'index.php?search=' + encodeURIComponent(decodedText) + '&field=isbn';
            }, 1500);
        }

        window.onload = () => {
            initScanner();
        };

        // ==========================================
        // 2. LOGICA RICERCA TESTUALE
        // ==========================================
        const testoRicerca = document.getElementById('testoRicerca');
        const btnTesto = document.getElementById('btnTesto');

        const eseguiRicercaTestuale = () => {
            const val = testoRicerca.value.trim();
            if (val) {
                window.location.href = 'index.php?search=' + encodeURIComponent(val) + '&field=titolo';
            }
        };

        btnTesto.addEventListener('click', eseguiRicercaTestuale);
        testoRicerca.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') eseguiRicercaTestuale();
        });


        // ==========================================
        // 3. LOGICA RICERCA VOCALE (Web Speech API)
        // ==========================================
        const btnMic = document.getElementById('btnMic');
        const statusVocale = document.getElementById('statusVocale');

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        
        if (SpeechRecognition) {
            // Browser supportato (Chrome, Edge, Safari)
            const recognition = new SpeechRecognition();
            recognition.lang = 'it-IT';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            btnMic.addEventListener('click', () => {
                try {
                    recognition.start();
                    btnMic.classList.add('mic-pulse', 'bg-red-500/20', 'border-red-500');
                    btnMic.classList.remove('bg-slate-800', 'border-slate-700');
                    statusVocale.innerHTML = "<span class='text-red-400'>Ti ascolto... Parla ora.</span>";
                } catch(e) {
                    // Evita errori se si clicca due volte velocemente
                }
            });

            recognition.onresult = (event) => {
                const testoAscoltato = event.results[0][0].transcript;
                statusVocale.innerHTML = `Hai detto: <span class='text-emerald-400'>${testoAscoltato}</span>`;
                testoRicerca.value = testoAscoltato; // Inserisce il testo anche nella barra
                
                document.getElementById('beep-sound').play();

                setTimeout(() => {
                    window.location.href = 'index.php?search=' + encodeURIComponent(testoAscoltato) + '&field=titolo';
                }, 1000);
            };

            recognition.onspeechend = () => {
                recognition.stop();
                resetMic();
            };

            recognition.onerror = (event) => {
                statusVocale.innerHTML = "<span class='text-red-500'>Non ho capito. Riprova o scrivi sotto.</span>";
                resetMic();
            };

            function resetMic() {
                btnMic.classList.remove('mic-pulse', 'bg-red-500/20', 'border-red-500');
                btnMic.classList.add('bg-slate-800', 'border-slate-700');
            }

        } else {
            // Browser NON supportato (es. Firefox)
            btnMic.addEventListener('click', () => {
                statusVocale.innerHTML = "<span class='text-amber-400'>Il tuo browser non supporta la voce.<br>Usa la barra di ricerca qui sotto! 👇</span>";
                btnMic.classList.add('opacity-50');
                setTimeout(() => {
                    statusVocale.innerHTML = "";
                    btnMic.classList.remove('opacity-50');
                }, 4000);
            });
        }
    </script>
</body>
</html>