<?php
include "connessione.php";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// FIX: Usiamo la colonna corretta "foto" invece di "foto_profilo"
$stmt = $conn->prepare("SELECT nome, username, email, punti_esperienza, foto FROM utenti WHERE id = ?");
if(!$stmt) die("Errore DB. Assicurati che le colonne esistano.");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Logica Gamification (allineata al profilo: 1 livello ogni 50 XP)
$nome_display = !empty($user_data['nome']) ? $user_data['nome'] : $user_data['username'];
$email = $user_data['email'];
$xp = isset($user_data['punti_esperienza']) ? (int)$user_data['punti_esperienza'] : 0;
$livello = floor($xp / 50) + 1;
$xp_nel_livello = $xp % 50;
$percentuale_xp = ($xp_nel_livello / 50) * 100;

// FIX: Percorso corretto della foto (uploads/profili/)
$foto_db = $user_data['foto'] ?? '';
$path_foto = 'uploads/profili/' . $foto_db;
$foto = (!empty($foto_db) && file_exists($path_foto)) ? $path_foto : 'https://ui-avatars.com/api/?name='.urlencode($nome_display).'&background=06b6d4&color=fff';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Mia Tessera - Libreria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.7.0/vanilla-tilt.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #020617; overflow: hidden; }
        .glass-btn { background: rgba(30, 41, 59, 0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Effetto Olografico della carta */
        .card-holographic {
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%), 
                        linear-gradient(45deg, #4f46e5 0%, #06b6d4 100%);
            box-shadow: 0 25px 50px -12px rgba(6, 182, 212, 0.4);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }
        .card-holographic::before {
            content: ""; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-20deg); animation: shine 6s infinite;
        }
        @keyframes shine { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }
    </style>
</head>
<body class="text-white min-h-screen flex flex-col items-center justify-center p-6 relative">

    <div class="fixed inset-0 -z-10">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-cyan-600/30 rounded-full blur-[120px] mix-blend-screen"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-indigo-600/30 rounded-full blur-[120px] mix-blend-screen"></div>
    </div>

    <div class="absolute top-6 left-6 z-20">
        <a href="profilo_view.php" class="glass-btn px-5 py-2.5 rounded-full text-sm font-bold text-slate-300 hover:text-white transition shadow-lg">← Torna al Profilo</a>
    </div>

    <div class="w-full max-w-md perspective-1000 z-10">
        <div class="card-holographic rounded-[2.5rem] p-8" data-tilt data-tilt-max="15" data-tilt-speed="400" data-tilt-glare data-tilt-max-glare="0.4">
            
            <div class="flex justify-between items-start mb-10 relative z-10">
                <div>
                    <h1 class="text-[10px] uppercase tracking-[0.3em] font-black opacity-80 mb-1 shadow-black drop-shadow-md">Tessera Digitale</h1>
                    <p class="text-3xl font-black tracking-tighter drop-shadow-lg">NEXUS<span class="text-cyan-300">LIBRARY</span></p>
                </div>
                <div class="bg-white/10 backdrop-blur-md p-1.5 rounded-2xl border border-white/20 shadow-xl">
                    <img src="<?php echo $foto; ?>" class="w-14 h-14 rounded-xl object-cover" alt="Avatar">
                </div>
            </div>

            <div class="mb-8 relative z-10">
                <p class="text-3xl font-black tracking-tight drop-shadow-md"><?php echo htmlspecialchars($nome_display); ?></p>
                <p class="text-sm text-cyan-100 font-semibold opacity-90 drop-shadow-md"><?php echo htmlspecialchars($email); ?></p>
            </div>

            <div class="flex gap-4 mb-8 relative z-10">
                <div class="bg-black/30 backdrop-blur-sm rounded-2xl px-5 py-3 border border-white/10 flex flex-col items-center shadow-inner">
                    <span class="text-[10px] uppercase font-black opacity-70 tracking-widest">LIV</span>
                    <span class="text-3xl font-black text-white"><?php echo $livello; ?></span>
                </div>
                <div class="flex-1 bg-black/30 backdrop-blur-sm rounded-2xl px-5 py-3 border border-white/10 shadow-inner flex flex-col justify-center">
                    <div class="flex justify-between text-[10px] uppercase font-black opacity-70 tracking-widest mb-2">
                        <span>Esperienza</span>
                        <span><?php echo $xp; ?> XP</span>
                    </div>
                    <div class="w-full bg-white/10 h-2.5 rounded-full overflow-hidden shadow-inner">
                        <div class="bg-gradient-to-r from-cyan-400 to-white h-full transition-all duration-1000" style="width: <?php echo $percentuale_xp; ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-[2rem] flex items-center justify-center shadow-2xl mx-auto w-52 h-52 border-[6px] border-white/20 relative z-10 hover:scale-105 transition-transform">
                <div id="qrcode"></div>
            </div>

            <p class="text-center mt-6 text-[10px] font-black uppercase tracking-[0.2em] opacity-80 drop-shadow-md relative z-10">
                Mostra questo codice in cassa
            </p>
        </div>

        <button onclick="window.print()" class="w-full mt-10 glass-btn text-cyan-400 font-black uppercase tracking-widest text-xs hover:bg-cyan-500 hover:text-white transition-all py-4 rounded-2xl shadow-lg active:scale-95">
            🖨️ Stampa Copia Fisica
        </button>
    </div>

    <script>
        // Genera il QR Code con l'ID dell'utente
        new QRCode(document.getElementById("qrcode"), {
            text: "USER_ID:<?php echo $user_id; ?>",
            width: 176, // Adattato al padding
            height: 176,
            colorDark : "#0f172a", 
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>