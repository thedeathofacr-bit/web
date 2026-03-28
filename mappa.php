<?php
// ABILITIAMO GLI ERRORI PER CAPIRE ESATTAMENTE COSA SUCCEDE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

include "connessione.php";

// Controllo sessione utente
if (function_exists('require_user_page')) {
    require_user_page($conn);
}

$librerie = [];
$errore_sql = "";

// Usiamo TRY-CATCH come scudo: se la tabella non ha le colonne giuste, non crasha!
try {
    $query = "
        SELECT l.id, l.nome, l.indirizzo, l.latitudine, l.longitudine,
               (SELECT COUNT(*) FROM libri WHERE id_libreria = l.id) AS totale_libri
        FROM libreria l
        WHERE l.latitudine IS NOT NULL AND l.longitudine IS NOT NULL
    ";
    
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['latitudine'] = (float)$row['latitudine'];
            $row['longitudine'] = (float)$row['longitudine'];
            $row['totale_libri'] = (int)$row['totale_libri'];
            $librerie[] = $row;
        }
        $stmt->close();
    } else {
        $errore_sql = $conn->error;
    }
} catch (Exception $e) {
    $errore_sql = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mappa Librerie | Nexus</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        body { font-family: 'Quicksand', sans-serif; background-color: #020617; color: #e2e8f0; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-input { background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); color: white; }
        
        #mappa { height: 100%; min-height: 600px; width: 100%; z-index: 10; border-radius: 2rem; background: #e5e5e5;}
        
        /* Stili Popup Leaflet Glassmorphism */
        .leaflet-popup-content-wrapper { background: rgba(15, 23, 42, 0.95) !important; color: white !important; border: 1px solid rgba(239,68,68,0.3); border-radius: 1.5rem; backdrop-filter: blur(10px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); }
        .leaflet-popup-tip { background: rgba(15, 23, 42, 0.95) !important; border-bottom: 1px solid rgba(239,68,68,0.3); border-right: 1px solid rgba(239,68,68,0.3); }
        .leaflet-container a.leaflet-popup-close-button { color: #94a3b8; font-size: 20px; padding: 10px; }
        .leaflet-container a.leaflet-popup-close-button:hover { color: #ef4444; }
        .leaflet-control-zoom a { background: rgba(15, 23, 42, 0.8) !important; color: #ef4444 !important; border: 1px solid rgba(255,255,255,0.1) !important; backdrop-filter: blur(10px); }

        /* Effetto Radar sui Marker (Rosso brillante per la mappa chiara) */
        .radar-marker { border-radius: 50%; border: 2px solid #ef4444; box-shadow: 0 0 15px #ef4444, inset 0 0 15px #ef4444; animation: pulseRadar 2s infinite; background: rgba(239, 68, 68, 0.2); }
        @keyframes pulseRadar { 0% { transform: scale(0.8); opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { transform: scale(1.1); opacity: 0.8; box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { transform: scale(0.8); opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen relative pb-10 selection:bg-red-500 selection:text-white">

    <div class="fixed inset-0 -z-20">
        <div class="absolute top-[0%] right-[0%] w-[40%] h-[40%] rounded-full bg-red-900/10 blur-[150px]"></div>
        <div class="absolute bottom-[0%] left-[0%] w-[40%] h-[40%] rounded-full bg-blue-900/10 blur-[150px]"></div>
    </div>

    <nav class="sticky top-0 z-50 glass-panel border-b border-white/5 px-6 py-4 mb-8 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="index.php" class="bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/10 text-white px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-widest transition-all flex items-center gap-2 shadow-sm active:scale-95">
                <span>←</span> Dashboard
            </a>
            <h1 class="text-2xl font-black text-white uppercase italic tracking-widest flex items-center gap-3 drop-shadow-md">
                <span class="text-red-500 text-3xl">🗺️</span> Radar Sedi
            </h1>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 lg:px-6 relative z-10">

        <?php if (!empty($errore_sql)): ?>
            <div class="glass-panel border-l-4 border-l-red-500 bg-red-950/30 p-8 rounded-[2.5rem] mb-8 shadow-2xl relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 text-9xl opacity-10">⚠️</div>
                <h2 class="font-black text-3xl mb-3 text-red-400 flex items-center gap-3">Errore Database Rilevato!</h2>
                <p class="mb-6 text-slate-300 font-medium">Sembra che la tua tabella <strong>libreria</strong> non sia ancora stata aggiornata con le nuove colonne per la mappa.</p>
                
                <div class="bg-black/50 p-5 rounded-2xl border border-red-500/30 mb-6 font-mono text-sm overflow-x-auto text-red-400 shadow-inner">
                    <?php echo htmlspecialchars($errore_sql); ?>
                </div>
                
                <p class="font-black text-white uppercase tracking-widest text-xs mb-3">🛠️ Fix in 1 minuto su phpMyAdmin:</p>
                <pre class="bg-slate-900 border border-white/10 text-cyan-400 p-5 rounded-2xl overflow-x-auto text-sm shadow-inner leading-relaxed">ALTER TABLE libreria 
ADD COLUMN indirizzo VARCHAR(255) DEFAULT NULL, 
ADD COLUMN latitudine DECIMAL(10, 8) DEFAULT NULL, 
ADD COLUMN longitudine DECIMAL(11, 8) DEFAULT NULL;</pre>
            </div>
        <?php else: ?>
        
        <div class="grid lg:grid-cols-3 gap-8 h-[calc(100vh-160px)] min-h-[650px]">
            
            <div class="glass-panel rounded-[2.5rem] shadow-2xl flex flex-col h-full overflow-hidden border border-white/10">
                
                <div class="p-6 border-b border-white/5 bg-slate-900/40 relative space-y-4">
                    <button id="btnTrovaPosizione" class="w-full bg-gradient-to-r from-red-600 to-orange-500 hover:shadow-lg hover:shadow-red-500/30 text-white px-5 py-4 rounded-2xl font-black uppercase tracking-widest text-xs transition-all flex justify-center items-center gap-3 active:scale-95">
                        <span class="text-xl leading-none">📍</span> Trova la sede più vicina
                    </button>
                    <p id="statoGps" class="text-[10px] uppercase tracking-widest font-bold text-center text-slate-500">Usa il GPS per calcolare le distanze</p>
                    
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">🔍</span>
                        <input type="text" id="inputRicerca" placeholder="Cerca sede o indirizzo..." class="w-full glass-input pl-11 pr-4 py-3 rounded-xl text-sm focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-all placeholder:text-slate-500">
                    </div>
                </div>

                <div id="listaLibrerie" class="overflow-y-auto p-4 flex-1 space-y-4 no-scrollbar">
                    </div>
            </div>

            <div class="lg:col-span-2 glass-panel rounded-[2.5rem] shadow-2xl border border-white/10 p-2 relative group overflow-hidden">
                <button id="btnSwitchMappa" class="absolute top-6 right-6 z-[400] bg-slate-900/80 backdrop-blur-md border border-white/20 text-white px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg hover:bg-slate-800 transition-all flex items-center gap-2">
                    🛰️ Vista Satellite
                </button>
                
                <div id="mappa"></div>
            </div>
            
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($errore_sql)): ?>
    <script>
    let librerie = <?php echo json_encode($librerie, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); ?>;
    let librerieFiltrate = [...librerie]; // Copia per la ricerca

    // 1. INIZIALIZZAZIONE MAPPA E LIVELLI
    var mappa = L.map('mappa', { zoomControl: false }).setView([41.9028, 12.4964], 5);
    
    // Spostiamo i controlli dello zoom in basso a destra per pulizia visiva
    L.control.zoom({ position: 'bottomright' }).addTo(mappa);

    // Mappa Stile Google (CartoDB Voyager - Chiara e pulita)
    var lightLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '© OpenStreetMap © CARTO',
        subdomains: 'abcd',
        maxZoom: 20
    });

    // Mappa Satellitare Reale (Esri World Imagery)
    var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles © Esri',
        maxZoom: 18
    });

    // Imposta il layer chiaro di default
    lightLayer.addTo(mappa);
    let isSatellite = false;

    // Logica Bottone Switch Mappa
    document.getElementById('btnSwitchMappa').addEventListener('click', function() {
        if(isSatellite) {
            mappa.removeLayer(satelliteLayer);
            lightLayer.addTo(mappa);
            this.innerHTML = "🛰️ Vista Satellite";
            this.classList.replace('bg-blue-900', 'bg-slate-900/80');
        } else {
            mappa.removeLayer(lightLayer);
            satelliteLayer.addTo(mappa);
            this.innerHTML = "🗺️ Vista Mappa";
            this.classList.replace('bg-slate-900/80', 'bg-blue-900');
        }
        isSatellite = !isSatellite;
    });

    // 2. ICONE PERSONALIZZATE (Rosse per contrastare col chiaro)
    var iconaLibreria = L.divIcon({
        className: 'custom-div-icon',
        html: `<div class="w-10 h-10 radar-marker flex items-center justify-center bg-slate-900 rounded-full">
                <img src="https://cdn-icons-png.flaticon.com/512/2232/2232688.png" class="w-5 h-5 filter brightness-0 invert drop-shadow-[0_0_2px_rgba(255,255,255,1)]">
               </div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        popupAnchor: [0, -20]
    });
    
    var iconaUtente = L.divIcon({
        className: 'custom-div-icon',
        html: `<div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center border-2 border-emerald-400 shadow-[0_0_15px_#34d399] animate-bounce">
                <span class="text-2xl">😎</span>
               </div>`,
        iconSize: [48, 48],
        iconAnchor: [24, 48],
        popupAnchor: [0, -48]
    });
    var markerUtente = null;
    let markersList = [];

    // 3. DISEGNO MARKER SULLA MAPPA
    function disegnaMarker(arrayLibrerie) {
        markersList.forEach(m => mappa.removeLayer(m));
        markersList = [];
        let bounds = [];

        arrayLibrerie.forEach(lib => {
            let marker = L.marker([lib.latitudine, lib.longitudine], {icon: iconaLibreria}).addTo(mappa);
            
            // Link a Google Maps Navigator
            let gMapsLink = `http://googleusercontent.com/maps.google.com/maps?daddr=${lib.latitudine},${lib.longitudine}&navigate=yes`;

            let popupHtml = `
                <div class="p-3 min-w-[200px] text-center">
                    <h3 class="font-black text-xl text-red-400 uppercase tracking-wider mb-2">${lib.nome}</h3>
                    <p class="text-xs text-slate-300 font-medium mb-4 opacity-90">${lib.indirizzo}</p>
                    
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div class="bg-slate-900 border border-white/10 rounded-xl p-2 text-[10px] font-bold uppercase text-white shadow-inner">
                            <span class="block text-lg mb-1">📚</span> ${lib.totale_libri} libri
                        </div>
                        <div class="bg-slate-900 border border-white/10 rounded-xl p-2 text-[10px] font-bold uppercase text-white shadow-inner">
                            <span class="block text-lg mb-1">🎟️</span> Info
                        </div>
                    </div>
                    
                    <a href="${gMapsLink}" target="_blank" class="block w-full bg-red-600 hover:bg-red-500 text-white py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                        🚗 Portami Qui
                    </a>
                </div>`;
            
            marker.bindPopup(popupHtml);
            markersList.push(marker);
            bounds.push([lib.latitudine, lib.longitudine]);
        });

        if (bounds.length > 0) mappa.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
    }

    // 4. DISEGNO LISTA LATERALE
    function disegnaLista(arrayLibrerie) {
        const container = document.getElementById('listaLibrerie');
        container.innerHTML = '';
        if (arrayLibrerie.length === 0) { 
            container.innerHTML = `
                <div class="text-center mt-10">
                    <span class="text-4xl opacity-30">👻</span>
                    <p class="text-slate-500 font-bold text-xs uppercase tracking-widest mt-3">Nessun risultato.</p>
                </div>`; 
            return; 
        }
        
        arrayLibrerie.forEach(lib => {
            let badgeDistanza = lib.distanza !== undefined 
                ? `<span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-sm">📍 ${lib.distanza.toFixed(1)} km</span>` 
                : '';
                
            container.innerHTML += `
                <div class="p-5 rounded-[1.5rem] border border-white/5 bg-slate-900/50 hover:bg-slate-800 transition-all cursor-pointer shadow-inner group relative overflow-hidden" onclick="mappa.setView([${lib.latitudine}, ${lib.longitudine}], 16)">
                    <div class="absolute inset-0 bg-gradient-to-r from-red-500/0 to-red-500/5 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                    <h4 class="font-black text-white group-hover:text-red-400 transition-colors text-xl relative z-10">${lib.nome}</h4>
                    <p class="text-xs text-slate-400 mt-1 mb-4 line-clamp-1 font-medium relative z-10">${lib.indirizzo}</p>
                    <div class="flex justify-between items-center relative z-10">
                        <span class="text-slate-300 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-black/40 border border-white/5">📚 ${lib.totale_libri} libri</span>
                        ${badgeDistanza}
                    </div>
                </div>`;
        });
    }

    // Inizializza
    disegnaMarker(librerieFiltrate);
    disegnaLista(librerieFiltrate);

    // 5. MOTORE DI RICERCA LIVE
    document.getElementById('inputRicerca').addEventListener('input', function(e) {
        const testo = e.target.value.toLowerCase();
        librerieFiltrate = librerie.filter(lib => 
            lib.nome.toLowerCase().includes(testo) || 
            (lib.indirizzo && lib.indirizzo.toLowerCase().includes(testo))
        );
        disegnaMarker(librerieFiltrate);
        disegnaLista(librerieFiltrate);
    });

    // Formula per il calcolo della distanza
    function calcolaDistanzaKm(lat1, lon1, lat2, lon2) {
        const R = 6371; const dLat = (lat2 - lat1) * Math.PI / 180; const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2); 
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    // 6. RILEVAMENTO GPS
    document.getElementById('btnTrovaPosizione').addEventListener('click', function() {
        const btn = this; const stato = document.getElementById('statoGps');
        if (!navigator.geolocation) { stato.textContent = "GPS NON SUPPORTATO."; stato.classList.add("text-red-400"); return; }
        
        btn.disabled = true; btn.classList.add('opacity-70', 'animate-pulse'); btn.innerHTML = "⏳ Ricerca Satellite...";
        stato.textContent = "Accetta la richiesta dal browser...";
        
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const uLat = pos.coords.latitude, uLon = pos.coords.longitude;
                btn.disabled = false; btn.classList.remove('opacity-70', 'animate-pulse'); 
                btn.innerHTML = "📍 Sede più vicina trovata!";
                btn.classList.replace('from-red-600', 'from-emerald-600'); 
                btn.classList.replace('to-orange-500', 'to-emerald-500');
                stato.textContent = "Distanze calcolate con successo.";
                
                if (markerUtente) mappa.removeLayer(markerUtente);
                markerUtente = L.marker([uLat, uLon], {icon: iconaUtente}).addTo(mappa)
                    .bindPopup('<div class="text-center p-2"><h3 class="font-black text-lg text-emerald-400 uppercase">Tu sei qui</h3></div>')
                    .openPopup();
                
                librerie.forEach(lib => lib.distanza = calcolaDistanzaKm(uLat, uLon, lib.latitudine, lib.longitudine));
                
                // Aggiorniamo anche l'array filtrato se c'è una ricerca attiva
                librerieFiltrate.forEach(lib => {
                    const originalLib = librerie.find(l => l.id === lib.id);
                    if(originalLib) lib.distanza = originalLib.distanza;
                });

                librerieFiltrate.sort((a, b) => a.distanza - b.distanza);
                librerie.sort((a, b) => a.distanza - b.distanza);
                
                disegnaLista(librerieFiltrate); 
                
                // Centra la vista sull'utente e i dintorni
                mappa.setView([uLat, uLon], 12);
            },
            (err) => {
                btn.disabled = false; btn.classList.remove('opacity-70', 'animate-pulse'); btn.innerHTML = "📍 Riprova GPS";
                stato.classList.add("text-red-400"); stato.textContent = "Permesso negato o segnale assente.";
            }, { enableHighAccuracy: true }
        );
    });
    </script>
    <?php endif; ?>
</body>
</html>