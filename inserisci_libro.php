<?php include "connessione.php";
require_admin_page($conn);
$libreriaId = (int) current_library_id();
if ($libreriaId <= 0) {
    header("Location: index.php");
    exit();
}
$errore = "";
$erroriCampi = [
    "isbn" => "",
    "titolo" => "",
    "autore" => "",
    "genere" => "",
    "anno_pubblicazione" => "",
    "prezzo" => "",
];
$valori = [
    "isbn" => trim($_POST["isbn"] ?? ""),
    "titolo" => trim($_POST["titolo"] ?? ""),
    "autore" => trim($_POST["autore"] ?? ""),
    "genere" => trim($_POST["genere"] ?? ""),
    "anno_pubblicazione" => trim($_POST["anno_pubblicazione"] ?? ""),
    "prezzo" => trim($_POST["prezzo"] ?? ""),
    "descrizione" => trim($_POST["descrizione"] ?? ""),
];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($valori["isbn"] === "") {
        $erroriCampi["isbn"] = "L'ISBN è obbligatorio.";
    }
    if ($valori["titolo"] === "") {
        $erroriCampi["titolo"] = "Il titolo è obbligatorio.";
    }
    if ($valori["autore"] === "") {
        $erroriCampi["autore"] = "L'autore è obbligatorio.";
    }
    if ($valori["genere"] === "") {
        $erroriCampi["genere"] = "Il genere è obbligatorio.";
    }
    if ($valori["anno_pubblicazione"] === "") {
        $erroriCampi["anno_pubblicazione"] =
            "L'anno di pubblicazione è obbligatorio.";
    } elseif (
        !is_numeric($valori["anno_pubblicazione"]) ||
        (int) $valori["anno_pubblicazione"] < 1000 ||
        (int) $valori["anno_pubblicazione"] > (int) date("Y")
    ) {
        $erroriCampi["anno_pubblicazione"] = "Inserisci un anno valido.";
    }
    if ($valori["prezzo"] === "") {
        $erroriCampi["prezzo"] = "Il prezzo è obbligatorio.";
    } elseif (!is_numeric($valori["prezzo"]) || (float) $valori["prezzo"] < 0) {
        $erroriCampi["prezzo"] = "Inserisci un prezzo valido.";
    }
    $haErrori = false;
    foreach ($erroriCampi as $erroreCampo) {
        if ($erroreCampo !== "") {
            $haErrori = true;
            break;
        }
    }
    if ($haErrori) {
        $errore = "Correggi i campi evidenziati prima di continuare.";
    }
    if (function_exists("verify_csrf_or_die")) {
        verify_csrf_or_die($conn);
    }
    if (!$haErrori) {
        $immagine = "";
        if (
            isset($_FILES["immagine"]) &&
            isset($_FILES["immagine"]["error"]) &&
            $_FILES["immagine"]["error"] === UPLOAD_ERR_OK &&
            !empty($_FILES["immagine"]["name"])
        ) {
            $cartellaUpload = __DIR__ . "/uploads/libri/";
            if (!is_dir($cartellaUpload)) {
                mkdir($cartellaUpload, 0777, true);
            }
            $nomeOriginale = $_FILES["immagine"]["name"];
            $estensione = strtolower(
                pathinfo($nomeOriginale, PATHINFO_EXTENSION)
            );
            $estensioniConsentite = ["jpg", "jpeg", "png", "webp", "gif"];
            if (!in_array($estensione, $estensioniConsentite, true)) {
                $errore = "Formato immagine non valido.";
            } else {
                $nomeFile = uniqid("cover_", true) . "." . $estensione;
                $percorsoCompleto = $cartellaUpload . $nomeFile;
                if (
                    move_uploaded_file(
                        $_FILES["immagine"]["tmp_name"],
                        $percorsoCompleto
                    )
                ) {
                    $immagine = "uploads/libri/" . $nomeFile;
                } else {
                    $errore = "Errore durante il caricamento della copertina.";
                }
            }
        } elseif (!empty($_POST["copertina_url"])) {
            $immagine = trim((string) $_POST["copertina_url"]);
        }
        if ($errore === "") {
            $annoInt = (int) $valori["anno_pubblicazione"];
            $prezzoFloat = (float) str_replace(",", ".", $valori["prezzo"]);
            $descrizione = $valori["descrizione"];
            $sql =
                "INSERT INTO libri ( titolo, autore, anno_pubblicazione, isbn, genere, prezzo, descrizione, immagine, id_libreria ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $errore =
                    "Errore nella preparazione della query: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "ssissdssi",
                    $valori["titolo"],
                    $valori["autore"],
                    $annoInt,
                    $valori["isbn"],
                    $valori["genere"],
                    $prezzoFloat,
                    $descrizione,
                    $immagine,
                    $libreriaId
                );
                if ($stmt->execute()) {
                    $newBookId = (int) $conn->insert_id;
                    if (function_exists("log_attivita")) {
                        log_attivita(
                            $conn,
                            "inserimento",
                            "libro",
                            $newBookId,
                            "Inserito libro: " . $valori["titolo"]
                        );
                    }
                    $stmt->close();
                    header("Location: index.php?success=libro_aggiunto");
                    exit();
                }
                $errore =
                    "Errore durante il salvataggio del libro: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
function input_class($campo, $erroriCampi)
{
    $base =
        "w-full px-4 py-3 rounded-xl border bg-white dark:bg-slate-900 text-gray-800 dark:text-white focus:outline-none focus:ring-2 transition";
    if (!empty($erroriCampi[$campo])) {
        return $base . " border-red-500 dark:border-red-500 focus:ring-red-500";
    }
    return $base . " border-gray-300 dark:border-gray-700 focus:ring-cyan-500";
}
?> <!DOCTYPE html> <html lang="it"> <head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Aggiungi libro</title> <link rel="icon" href="assets/logo.png"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/@zxing/library@latest"></script> <script> tailwind.config = { darkMode: 'class' }; if (localStorage.getItem('darkMode') === 'enabled') { document.documentElement.classList.add('dark'); } </script> <style> @keyframes popupShake { 0% { transform: translateX(0) scale(1); } 20% { transform: translateX(-8px) scale(1.01); } 40% { transform: translateX(8px) scale(1.01); } 60% { transform: translateX(-6px) scale(1.01); } 80% { transform: translateX(6px) scale(1.01); } 100% { transform: translateX(0) scale(1); } } .popup-shake { animation: popupShake 0.38s ease; } </style> </head> <body class="min-h-screen bg-gradient-to-br from-cyan-200 via-white to-indigo-200 dark:from-slate-950 dark:via-slate-900 dark:to-black transition-colors duration-300"> <div class="max-w-5xl mx-auto px-4 py-12"> <div class="mb-6"> <a href="index.php" class="inline-flex items-center gap-2 px-5 py-3 bg-white/90 dark:bg-slate-800/90 border border-gray-300 dark:border-slate-600 text-gray-800 dark:text-white rounded-xl shadow-lg hover:bg-white dark:hover:bg-slate-700 transition font-semibold backdrop-blur" > <span class="text-lg">←</span> <span>Torna alla libreria</span> </a> </div> <div class="backdrop-blur-xl bg-white/80 dark:bg-slate-900/80 border border-white/30 dark:border-slate-700 rounded-3xl shadow-2xl p-10 transition"> <div class="flex items-center justify-between gap-4 mb-8 flex-wrap"> <h1 class="text-4xl font-bold text-gray-800 dark:text-white flex items-center gap-3"> 📚 Aggiungi nuovo libro </h1> <div class="text-sm text-gray-500 dark:text-gray-400"> <span class="text-red-500 font-bold">*</span> campo obbligatorio </div> </div> <?php if (
     $errore !== ""
 ): ?> <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-6"> <?php echo htmlspecialchars(
     $errore
 ); ?> </div> <?php endif; ?> <form method="POST" enctype="multipart/form-data" class="space-y-6" id="bookForm" novalidate> <?php echo csrf_input(); ?> <input type="hidden" name="copertina_url" id="copertina_url"> <div class="grid md:grid-cols-3 gap-4"> <div class="md:col-span-1"> <label for="isbn" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> ISBN <span class="text-red-500">*</span> </label> <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars(
     $valori["isbn"]
 ); ?>" placeholder="Inserisci ISBN" class="<?php echo input_class(
    "isbn",
    $erroriCampi
); ?>" > <p id="error-isbn" class="mt-2 text-sm <?php echo $erroriCampi[
    "isbn"
] !== ""
    ? "text-red-500"
    : "text-gray-400 dark:text-gray-500"; ?>"> <?php echo $erroriCampi[
    "isbn"
] !== ""
    ? htmlspecialchars($erroriCampi["isbn"])
    : "Inserisci il codice ISBN del libro."; ?> </p> </div> <div class="flex items-end"> <button type="button" onclick="scanISBN()" class="w-full bg-purple-600 hover:bg-purple-700 text-white rounded-xl shadow transition px-4 py-3 font-semibold" > 📷 Scanner </button> </div> <div class="flex items-end"> <button type="button" onclick="searchISBN()" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white rounded-xl shadow transition px-4 py-3 font-semibold" > 🔎 Cerca ISBN </button> </div> </div> <div class="grid md:grid-cols-2 gap-6"> <div> <label for="titolo" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Titolo <span class="text-red-500">*</span> </label> <input type="text" id="titolo" name="titolo" value="<?php echo htmlspecialchars(
     $valori["titolo"]
 ); ?>" placeholder="Titolo" class="<?php echo input_class(
    "titolo",
    $erroriCampi
); ?>" > <p id="error-titolo" class="mt-2 text-sm <?php echo $erroriCampi[
    "titolo"
] !== ""
    ? "text-red-500"
    : "text-gray-400 dark:text-gray-500"; ?>"> <?php echo $erroriCampi[
    "titolo"
] !== ""
    ? htmlspecialchars($erroriCampi["titolo"])
    : "Inserisci il titolo del libro."; ?> </p> </div> <div> <label for="autore" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Autore <span class="text-red-500">*</span> </label> <input type="text" id="autore" name="autore" list="autori" value="<?php echo htmlspecialchars(
     $valori["autore"]
 ); ?>" placeholder="Autore" class="<?php echo input_class(
    "autore",
    $erroriCampi
); ?>" > <datalist id="autori"></datalist> <p id="error-autore" class="mt-2 text-sm <?php echo $erroriCampi[
    "autore"
] !== ""
    ? "text-red-500"
    : "text-gray-400 dark:text-gray-500"; ?>"> <?php echo $erroriCampi[
    "autore"
] !== ""
    ? htmlspecialchars($erroriCampi["autore"])
    : "Inserisci l'autore del libro."; ?> </p> </div> </div> <div class="grid md:grid-cols-2 gap-6"> <div> <label for="genere" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Genere <span class="text-red-500">*</span> </label> <input type="text" id="genere" name="genere" value="<?php echo htmlspecialchars(
     $valori["genere"]
 ); ?>" placeholder="Genere" class="<?php echo input_class(
    "genere",
    $erroriCampi
); ?>" > <p id="error-genere" class="mt-2 text-sm <?php echo $erroriCampi[
    "genere"
] !== ""
    ? "text-red-500"
    : "text-gray-400 dark:text-gray-500"; ?>"> <?php echo $erroriCampi[
    "genere"
] !== ""
    ? htmlspecialchars($erroriCampi["genere"])
    : "Inserisci il genere del libro."; ?> </p> </div> <div> <label for="anno" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Anno pubblicazione <span class="text-red-500">*</span> </label> <input type="number" id="anno" name="anno_pubblicazione" value="<?php echo htmlspecialchars(
     $valori["anno_pubblicazione"]
 ); ?>" placeholder="Anno pubblicazione" class="<?php echo input_class(
    "anno_pubblicazione",
    $erroriCampi
); ?>" > <p id="error-anno_pubblicazione" class="mt-2 text-sm <?php echo $erroriCampi[
    "anno_pubblicazione"
] !== ""
    ? "text-red-500"
    : "text-gray-400 dark:text-gray-500"; ?>"> <?php echo $erroriCampi[
    "anno_pubblicazione"
] !== ""
    ? htmlspecialchars($erroriCampi["anno_pubblicazione"])
    : "Inserisci l'anno di pubblicazione."; ?> </p> </div> </div> <div> <label for="prezzo" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Prezzo <span class="text-red-500">*</span> </label> <input type="number" step="0.01" id="prezzo" name="prezzo" value="<?php echo htmlspecialchars(
     $valori["prezzo"]
 ); ?>" placeholder="Prezzo" class="<?php echo input_class(
    "prezzo",
    $erroriCampi
); ?>" > <p id="error-prezzo" class="mt-2 text-sm <?php echo $erroriCampi[
    "prezzo"
] !== ""
    ? "text-red-500"
    : "text-gray-400 dark:text-gray-500"; ?>"> <?php echo $erroriCampi[
    "prezzo"
] !== ""
    ? htmlspecialchars($erroriCampi["prezzo"])
    : "Inserisci il prezzo del libro."; ?> </p> </div> <div> <label for="descrizione" class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Descrizione </label> <textarea id="descrizione" name="descrizione" rows="4" placeholder="Descrizione" class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-slate-900 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition" ><?php echo htmlspecialchars(
     $valori["descrizione"]
 ); ?></textarea> <p class="mt-2 text-sm text-gray-400 dark:text-gray-500"> Campo facoltativo. </p> </div> <div> <label class="block mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200"> Copertina </label> <div id="dropzone" class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-2xl p-8 text-center cursor-pointer hover:border-cyan-500 hover:bg-cyan-50/40 dark:hover:bg-slate-800 transition" > <input type="file" name="immagine" id="imageInput" accept="image/*" class="hidden"> <p class="text-gray-600 dark:text-gray-300 text-lg font-medium"> 📂 Trascina qui la copertina </p> <p class="text-sm text-gray-400 mt-1"> oppure clicca per selezionare </p> </div> <img id="preview" class="w-40 rounded-xl shadow-lg mt-4 hidden border border-gray-200 dark:border-gray-700" alt="Anteprima copertina" > <button type="button" id="removeCoverBtn" class="mt-3 hidden px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow transition" > 🗑️ Rimuovi copertina </button> <p class="mt-2 text-sm text-gray-400 dark:text-gray-500"> Campo facoltativo. </p> </div> <div class="flex justify-end gap-4 pt-4"> <button type="submit" class="px-6 py-3 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-bold rounded-xl shadow-lg transition" > 💾 Salva libro </button> </div> </form> </div> </div> <video id="scanner" class="hidden fixed inset-0 w-full h-full object-cover z-50 bg-black"></video> <div id="customPopup" class="fixed inset-0 z-[9999] hidden items-center justify-center px-4"> <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div> <div id="popupBox" class="relative w-full max-w-md rounded-3xl bg-white dark:bg-slate-900 border border-white/20 dark:border-slate-700 shadow-2xl p-6 scale-95 opacity-0 transition duration-300"> <div class="flex items-start gap-4"> <div id="popupIcon" class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400"> ⚠️ </div> <div class="flex-1"> <h3 id="popupTitle" class="text-xl font-bold text-gray-800 dark:text-white"> Attenzione </h3> <p id="popupMessage" class="mt-2 text-gray-600 dark:text-gray-300 leading-relaxed"> Messaggio popup </p> </div> </div> <div class="mt-6 flex justify-end"> <button type="button" onclick="closePopup()" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold shadow-lg transition" > Ho capito </button> </div> </div> </div> <script> const dropzone = document.getElementById("dropzone"); const input = document.getElementById("imageInput"); const preview = document.getElementById("preview"); const removeCoverBtn = document.getElementById("removeCoverBtn"); const form = document.getElementById("bookForm"); const fields = { isbn: { input: document.getElementById("isbn"), error: document.getElementById("error-isbn"), requiredMessage: "L'ISBN è obbligatorio.", hint: "Inserisci il codice ISBN del libro." }, titolo: { input: document.getElementById("titolo"), error: document.getElementById("error-titolo"), requiredMessage: "Il titolo è obbligatorio.", hint: "Inserisci il titolo del libro." }, autore: { input: document.getElementById("autore"), error: document.getElementById("error-autore"), requiredMessage: "L'autore è obbligatorio.", hint: "Inserisci l'autore del libro." }, genere: { input: document.getElementById("genere"), error: document.getElementById("error-genere"), requiredMessage: "Il genere è obbligatorio.", hint: "Inserisci il genere del libro." }, anno_pubblicazione: { input: document.getElementById("anno"), error: document.getElementById("error-anno_pubblicazione"), requiredMessage: "L'anno di pubblicazione è obbligatorio.", hint: "Inserisci l'anno di pubblicazione." }, prezzo: { input: document.getElementById("prezzo"), error: document.getElementById("error-prezzo"), requiredMessage: "Il prezzo è obbligatorio.", hint: "Inserisci il prezzo del libro." } }; function setFieldError(fieldKey, message) { const field = fields[fieldKey]; field.input.classList.remove("border-gray-300", "dark:border-gray-700", "focus:ring-cyan-500"); field.input.classList.add("border-red-500", "dark:border-red-500", "focus:ring-red-500"); field.error.textContent = message; field.error.classList.remove("text-gray-400", "dark:text-gray-500"); field.error.classList.add("text-red-500"); } function clearFieldError(fieldKey) { const field = fields[fieldKey]; field.input.classList.remove("border-red-500", "dark:border-red-500", "focus:ring-red-500"); field.input.classList.add("border-gray-300", "dark:border-gray-700", "focus:ring-cyan-500"); field.error.textContent = field.hint; field.error.classList.remove("text-red-500"); field.error.classList.add("text-gray-400", "dark:text-gray-500"); } function validateField(fieldKey) { const field = fields[fieldKey]; const value = field.input.value.trim(); if (value === "") { setFieldError(fieldKey, field.requiredMessage); return false; } if (fieldKey === "anno_pubblicazione") { const currentYear = new Date().getFullYear(); const year = parseInt(value, 10); if (isNaN(year) || year < 1000 || year > currentYear) { setFieldError(fieldKey, "Inserisci un anno valido."); return false; } } if (fieldKey === "prezzo") { const price = parseFloat(value.replace(",", ".")); if (isNaN(price) || price < 0) { setFieldError(fieldKey, "Inserisci un prezzo valido."); return false; } } clearFieldError(fieldKey); return true; } Object.keys(fields).forEach((key) => { fields[key].input.addEventListener("input", () => validateField(key)); fields[key].input.addEventListener("blur", () => validateField(key)); }); async function searchISBN() { let isbn = document.getElementById("isbn").value.trim(); if (!isbn) { setFieldError("isbn", "Inserisci un ISBN prima della ricerca."); showPopup("Campi obbligatori", "Inserisci un ISBN prima di avviare la ricerca.", "warning"); return; } try { let url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" + encodeURIComponent(isbn); let res = await fetch(url); let data = await res.json(); if (!data.items || !data.items.length) { showPopup("Nessun risultato", "Non ho trovato nessun libro con questo ISBN.", "warning"); return; } let book = data.items[0].volumeInfo || {}; document.getElementById("titolo").value = book.title || ""; document.getElementById("autore").value = (book.authors && book.authors[0]) ? book.authors[0] : ""; document.getElementById("anno").value = book.publishedDate ? book.publishedDate.substring(0, 4) : ""; validateField("isbn"); validateField("titolo"); validateField("autore"); validateField("anno_pubblicazione"); if (book.imageLinks) { let img = book.imageLinks.thumbnail || book.imageLinks.smallThumbnail || ""; if (img) { preview.src = img; preview.classList.remove("hidden"); removeCoverBtn.classList.remove("hidden"); document.getElementById("copertina_url").value = img; } } } catch (e) { console.error("Errore ricerca ISBN:", e); showPopup("Errore", "Si è verificato un problema durante la ricerca ISBN.", "error"); } } async function scanISBN() { let video = document.getElementById("scanner"); video.classList.remove("hidden"); try { const codeReader = new ZXing.BrowserBarcodeReader(); let devices = await ZXing.BrowserBarcodeReader.listVideoInputDevices(); if (!devices || !devices.length) { showPopup("Fotocamera non trovata", "Nessuna fotocamera disponibile sul dispositivo.", "warning"); video.classList.add("hidden"); return; } codeReader.decodeFromVideoDevice(devices[0].deviceId, video, (result, err) => { if (result) { document.getElementById("isbn").value = result.text; video.classList.add("hidden"); codeReader.reset(); validateField("isbn"); searchISBN(); } }); } catch (e) { console.error("Errore scanner ISBN:", e); showPopup("Errore", "Impossibile avviare la fotocamera.", "error"); video.classList.add("hidden"); } } dropzone.onclick = () => input.click(); dropzone.addEventListener("dragover", function(e) { e.preventDefault(); dropzone.classList.add("border-cyan-500", "bg-cyan-50/40"); }); dropzone.addEventListener("dragleave", function() { dropzone.classList.remove("border-cyan-500", "bg-cyan-50/40"); }); dropzone.addEventListener("drop", function(e) { e.preventDefault(); dropzone.classList.remove("border-cyan-500", "bg-cyan-50/40"); const files = e.dataTransfer.files; if (files.length > 0) { input.files = files; showPreview(files[0]); } }); input.addEventListener("change", function() { const file = this.files[0]; if (!file) return; showPreview(file); }); function showPreview(file) { const reader = new FileReader(); reader.onload = e => { preview.src = e.target.result; preview.classList.remove("hidden"); removeCoverBtn.classList.remove("hidden"); document.getElementById("copertina_url").value = ""; }; reader.readAsDataURL(file); } removeCoverBtn.addEventListener("click", function() { preview.src = ""; preview.classList.add("hidden"); input.value = ""; document.getElementById("copertina_url").value = ""; removeCoverBtn.classList.add("hidden"); }); function showPopup(title, message, type = "warning") { const popup = document.getElementById("customPopup"); const popupBox = document.getElementById("popupBox"); const popupTitle = document.getElementById("popupTitle"); const popupMessage = document.getElementById("popupMessage"); const popupIcon = document.getElementById("popupIcon"); popupTitle.textContent = title; popupMessage.textContent = message; popupIcon.className = "w-14 h-14 rounded-2xl flex items-center justify-center text-2xl"; if (type === "success") { popupIcon.classList.add("bg-emerald-100", "text-emerald-600", "dark:bg-emerald-500/20", "dark:text-emerald-400"); popupIcon.textContent = "✅"; } else if (type === "error") { popupIcon.classList.add("bg-red-100", "text-red-600", "dark:bg-red-500/20", "dark:text-red-400"); popupIcon.textContent = "⛔"; } else { popupIcon.classList.add("bg-amber-100", "text-amber-600", "dark:bg-amber-500/20", "dark:text-amber-400"); popupIcon.textContent = "⚠️"; } popup.classList.remove("hidden"); popup.classList.add("flex"); popupBox.classList.remove("popup-shake"); popupBox.classList.remove("scale-100", "opacity-100"); popupBox.classList.add("scale-95", "opacity-0"); setTimeout(() => { popupBox.classList.remove("scale-95", "opacity-0"); popupBox.classList.add("scale-100", "opacity-100"); popupBox.classList.add("popup-shake"); }, 10); } function closePopup() { const popup = document.getElementById("customPopup"); const popupBox = document.getElementById("popupBox"); popupBox.classList.remove("scale-100", "opacity-100", "popup-shake"); popupBox.classList.add("scale-95", "opacity-0"); setTimeout(() => { popup.classList.add("hidden"); popup.classList.remove("flex"); }, 250); } document.getElementById("customPopup").addEventListener("click", function(e) { if (e.target === this) { closePopup(); } }); form.addEventListener("submit", function(e) { let formValido = true; Object.keys(fields).forEach((key) => { const valido = validateField(key); if (!valido) { formValido = false; } }); if (!formValido) { e.preventDefault(); showPopup("Campi non validi", "Correggi i campi evidenziati in rosso prima di salvare il libro.", "warning"); } }); </script> </body> </html>
