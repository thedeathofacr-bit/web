<?php
include "connessione.php";
require_admin_page($conn);

$id = isset($_GET["id"]) ? ($_GET["id"]) : ($_POST["id"] ?? "");

if (!is_numeric($id) || (int)$id <= 0) {
    header("Location: index.php");
    exit;
}

$id = (int)$id;
$libreriaId = (int) current_library_id();

if ($libreriaId <= 0) {
    header("Location: index.php");
    exit;
}

$messaggioErrore = "";
$messaggioSuccesso = "";

/* VALIDAZIONI */

function validate_price($price) {
    return is_numeric(str_replace(",", ".", $price)) && (float)str_replace(",", ".", $price) >= 0;
}

function validate_year($year) {
    if ($year === "") return true;
    return is_numeric($year) && $year > 0 && $year <= date("Y");
}

function validate_isbn($isbn) {
    if ($isbn === "") return true;
    return preg_match('/^[0-9A-Za-z\-]+$/', $isbn);
}

function secure_upload_image($file, $uploadDir) {
    if (!isset($file) || !isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
        return ["success" => false, "message" => "Nessun file valido caricato."];
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedMime = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
        "image/gif"  => "gif"
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime])) {
        return ["success" => false, "message" => "Formato immagine non supportato."];
    }

    if (($file["size"] ?? 0) > 5 * 1024 * 1024) {
        return ["success" => false, "message" => "L'immagine supera 5MB."];
    }

    $ext = $allowedMime[$mime];
    $fileName = "cover_" . bin2hex(random_bytes(8)) . "." . $ext;
    $destination = rtrim($uploadDir, "/") . "/" . $fileName;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        return ["success" => false, "message" => "Errore durante il caricamento dell'immagine."];
    }

    return [
        "success" => true,
        "path" => "uploads/libri/" . $fileName
    ];
}

/* RECUPERA LIBRO */

$stmt = $conn->prepare("
    SELECT *
    FROM libri
    WHERE id = ? AND id_libreria = ?
");

if (!$stmt) {
    die("Errore SQL: " . $conn->error);
}

$stmt->bind_param("ii", $id, $libreriaId);
$stmt->execute();
$result = $stmt->get_result();
$libro = $result->fetch_assoc();

if (!$libro) {
    header("Location: index.php");
    exit;
}

$stmt->close();

/* UPDATE */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titolo = trim($_POST["titolo"] ?? "");
    $autore = trim($_POST["autore"] ?? "");
    $isbn = trim($_POST["isbn"] ?? "");
    $genere = trim($_POST["genere"] ?? "");
    $anno = trim($_POST["anno_pubblicazione"] ?? "");
    $prezzo = trim($_POST["prezzo"] ?? "");
    $descrizione = trim($_POST["descrizione"] ?? "");
    $immagine = $libro["immagine"] ?? "";

    if ($titolo === "" || $autore === "") {
        $messaggioErrore = "Titolo e autore sono obbligatori.";
    } elseif (!validate_price($prezzo)) {
        $messaggioErrore = "Prezzo non valido.";
    } elseif (!validate_year($anno)) {
        $messaggioErrore = "Anno non valido.";
    } elseif (!validate_isbn($isbn)) {
        $messaggioErrore = "ISBN non valido.";
    }

    if ($messaggioErrore === "") {
        if (!empty($_POST["rimuovi_copertina"])) {
            $immagine = "";
        }

        if (isset($_FILES["immagine"]) && !empty($_FILES["immagine"]["name"])) {
            $upload = secure_upload_image($_FILES["immagine"], __DIR__ . "/uploads/libri");

            if (!$upload["success"]) {
                $messaggioErrore = $upload["message"];
            } else {
                $immagine = $upload["path"];
            }
        }
    }

    if ($messaggioErrore === "") {
        $prezzoDb = (float) str_replace(",", ".", $prezzo);
        $annoDb = ($anno === "") ? null : (int)$anno;

        $stmt = $conn->prepare("
            UPDATE libri
            SET titolo=?, autore=?, isbn=?, genere=?, anno_pubblicazione=?, prezzo=?, descrizione=?, immagine=?
            WHERE id=? AND id_libreria=?
        ");

        if (!$stmt) {
            die("Errore SQL: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssdssii",
            $titolo,
            $autore,
            $isbn,
            $genere,
            $annoDb,
            $prezzoDb,
            $descrizione,
            $immagine,
            $id,
            $libreriaId
        );

        if ($stmt->execute()) {
            if (function_exists("log_attivita")) {
                log_attivita(
                    $conn,
                    "modifica",
                    "libro",
                    $id,
                    "Modificato libro: " . $titolo
                );
            }

            header("Location: dettaglio_libro.php?id=" . $id . "&updated=1");
            exit;
        } else {
            $messaggioErrore = "Errore aggiornamento: " . $stmt->error;
        }

        $stmt->close();
    }

    $libro["titolo"] = $titolo;
    $libro["autore"] = $autore;
    $libro["isbn"] = $isbn;
    $libro["genere"] = $genere;
    $libro["anno_pubblicazione"] = $anno;
    $libro["prezzo"] = $prezzo;
    $libro["descrizione"] = $descrizione;
    $libro["immagine"] = $immagine;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<title>Modifica libro</title>
</head>

<body class="min-h-screen bg-[radial-gradient(circle_at_top,_#1e293b,_#0f172a_40%,_#020617_100%)] text-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <p class="text-sky-300/80 text-sm uppercase tracking-[0.25em] font-semibold">Gestione libreria</p>
                <h1 class="text-3xl sm:text-4xl font-black mt-1">Modifica libro</h1>
                <p class="text-slate-300 mt-2">Aggiorna informazioni e copertina con un'interfaccia più moderna.</p>
            </div>

            <div class="flex gap-3">
                <a href="index.php" class="rounded-2xl border border-white/10 bg-white/5 px-5 py-3 text-sm font-semibold text-slate-100 shadow-lg backdrop-blur hover:bg-white/10 transition">
                    ← Torna alla libreria
                </a>
                <a href="dettaglio_libro.php?id=<?= $id ?>" class="rounded-2xl bg-slate-700/70 px-5 py-3 text-sm font-semibold shadow-lg hover:bg-slate-600 transition">
                    Dettaglio libro
                </a>
            </div>
        </div>

        <?php if ($messaggioErrore !== ""): ?>
            <div class="mb-6 rounded-2xl border border-red-400/30 bg-red-500/10 px-5 py-4 text-red-100 shadow-xl backdrop-blur">
                <div class="font-semibold">Errore</div>
                <div class="text-sm mt-1"><?= htmlspecialchars($messaggioErrore) ?></div>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-[360px_minmax(0,1fr)] gap-6">
            <div class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-2xl backdrop-blur-xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold">Copertina</h2>
                    <span class="rounded-full bg-sky-400/15 px-3 py-1 text-xs font-semibold text-sky-200">Live preview</span>
                </div>

                <div id="coverCard" class="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900/70 aspect-[3/4] flex items-center justify-center shadow-inner">
                    <?php $cover = trim((string)($libro["immagine"] ?? "")); ?>
                    <img
                        id="coverPreview"
                        src="<?= htmlspecialchars($cover !== "" ? $cover : "https://placehold.co/600x800/0f172a/e2e8f0?text=Nessuna+copertina") ?>"
                        alt="Copertina libro"
                        class="h-full w-full object-cover"
                    >
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent p-4">
                        <div class="text-sm font-semibold"><?= htmlspecialchars($libro["titolo"] ?? "Titolo libro") ?></div>
                        <div class="text-xs text-slate-300 mt-1"><?= htmlspecialchars($libro["autore"] ?? "Autore") ?></div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="group cursor-pointer rounded-2xl bg-sky-500 px-4 py-3 text-center text-sm font-bold text-white shadow-lg transition hover:bg-sky-400">
                        Cambia copertina
                        <input type="file" name="immagine" id="immagineInput" form="bookForm" accept=".jpg,.jpeg,.png,.webp,.gif" class="hidden">
                    </label>

                    <button type="button" id="removeCoverBtn" class="rounded-2xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm font-bold text-red-100 transition hover:bg-red-500/20">
                        Rimuovi
                    </button>
                </div>

                <p class="mt-4 text-xs leading-6 text-slate-300">
                    Formati supportati: JPG, PNG, WEBP, GIF. Dimensione massima consigliata: 5MB.
                </p>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 sm:p-8 shadow-2xl backdrop-blur-xl">
                <form id="bookForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="rimuovi_copertina" id="rimuoviCopertina" value="0">

                    <div class="grid md:grid-cols-2 gap-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-200">Titolo *</label>
                            <input
                                name="titolo"
                                value="<?= htmlspecialchars($libro["titolo"] ?? "") ?>"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white outline-none ring-0 transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                                placeholder="Inserisci il titolo"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-200">Autore *</label>
                            <input
                                name="autore"
                                value="<?= htmlspecialchars($libro["autore"] ?? "") ?>"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                                placeholder="Inserisci l'autore"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-200">ISBN</label>
                            <input
                                name="isbn"
                                value="<?= htmlspecialchars($libro["isbn"] ?? "") ?>"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                                placeholder="ISBN del libro"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-200">Genere</label>
                            <input
                                name="genere"
                                value="<?= htmlspecialchars($libro["genere"] ?? "") ?>"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                                placeholder="Genere"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-200">Anno pubblicazione</label>
                            <input
                                name="anno_pubblicazione"
                                value="<?= htmlspecialchars((string)($libro["anno_pubblicazione"] ?? "")) ?>"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                                placeholder="Es. 2024"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-200">Prezzo</label>
                            <input
                                name="prezzo"
                                value="<?= htmlspecialchars((string)($libro["prezzo"] ?? "")) ?>"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/70 px-4 py-3 text-white outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                                placeholder="Es. 14.90"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-200">Descrizione</label>
                        <textarea
                            name="descrizione"
                            rows="6"
                            class="w-full rounded-3xl border border-white/10 bg-slate-900/70 px-4 py-4 text-white outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:shadow-[0_0_0_4px_rgba(56,189,248,0.15)]"
                            placeholder="Scrivi una descrizione del libro..."
                        ><?= htmlspecialchars($libro["descrizione"] ?? "") ?></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-sky-500 to-blue-600 px-6 py-4 text-sm font-extrabold tracking-wide text-white shadow-[0_18px_45px_rgba(2,132,199,0.35)] transition hover:scale-[1.01] hover:from-sky-400 hover:to-blue-500"
                        >
                            💾 Salva modifiche
                        </button>

                        <a
                            href="dettaglio_libro.php?id=<?= $id ?>"
                            class="inline-flex items-center justify-center rounded-2xl border border-white/10 bg-white/5 px-6 py-4 text-sm font-bold text-slate-100 transition hover:bg-white/10"
                        >
                            Annulla
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
const input = document.getElementById('immagineInput');
const preview = document.getElementById('coverPreview');
const removeBtn = document.getElementById('removeCoverBtn');
const removeField = document.getElementById('rimuoviCopertina');
const placeholder = 'https://placehold.co/600x800/0f172a/e2e8f0?text=Nessuna+copertina';

if (input) {
    input.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;

        removeField.value = '0';

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

if (removeBtn) {
    removeBtn.addEventListener('click', function () {
        if (input) {
            input.value = '';
        }
        removeField.value = '1';
        preview.src = placeholder;
    });
}
</script>

</body>
</html>
