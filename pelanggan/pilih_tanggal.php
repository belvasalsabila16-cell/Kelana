
<?php
session_start();
include "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mulai = $_POST["tanggal_mulai"] ?? "";
    $selesai = $_POST["tanggal_selesai"] ?? "";

    $awal = DateTime::createFromFormat("Y-m-d", $mulai);
    $akhir = DateTime::createFromFormat("Y-m-d", $selesai);
    $hariIni = new DateTime("today");

    if (
        $awal && $akhir &&
        $awal->format("Y-m-d") === $mulai &&
        $akhir->format("Y-m-d") === $selesai &&
        $awal >= $hariIni &&
        $akhir >= $awal
    ) {
        $_SESSION["tanggal_mulai"] = $mulai;
        $_SESSION["tanggal_selesai"] = $selesai;

        header("Location: katalog.php");
        exit;
    } else {
        $error = "Tanggal penyewaan tidak valid.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pilih Tanggal - Kelana</title>
<link rel="stylesheet" href="../assets/css/style.css">

<style>
.booking-date {
    max-width: 650px;
    margin: 60px auto;
    padding: 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
}

.booking-date h1 {
    color: #31583c;
    margin-bottom: 10px;
}

.date-display {
    border: 1px solid #ccc;
    padding: 15px;
    border-radius: 8px;
    margin: 25px 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.date-display strong {
    color: #31583c;
}

.calendar {
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 18px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.calendar-header button {
    border: none;
    background: #e5ede5;
    padding: 8px 13px;
    border-radius: 5px;
    cursor: pointer;
}

.days, .dates {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    gap: 5px;
}

.days div {
    font-weight: bold;
    color: #31583c;
    padding: 8px 0;
    font-size: 13px;
}

.dates button {
    border: none;
    background: white;
    padding: 12px 0;
    border-radius: 6px;
    cursor: pointer;
}

.dates button:hover:not(:disabled) {
    background: #e5ede5;
}

.dates button:disabled {
    color: #ccc;
    cursor: not-allowed;
}

.dates button.selected {
    background: #31583c;
    color: white;
}

.dates button.in-range {
    background: #dce8dd;
    border-radius: 0;
}

.dates button.range-start {
    border-radius: 6px 0 0 6px;
}

.dates button.range-end {
    border-radius: 0 6px 6px 0;
}

.submit-date {
    width: 100%;
    margin-top: 20px;
    padding: 14px;
    background: #31583c;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 16px;
}

.submit-date:disabled {
    background: #999;
    cursor: not-allowed;
}

.error {
    color: #c0392b;
    margin-top: 12px;
}
</style>
</head>

<body>

<nav class="navbar">
    <h2>KELANA.</h2>
    <div class="menu">
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
        <a href="#">Tentang Kami</a>
        <a href="#">FAQ</a>
    </div>
    <a href="../auth/login.php" class="login">Masuk</a>
</nav>

<section class="booking-date">

    <h1>Pilih Tanggal Penyewaan</h1>
    <p>Tentukan kapan kamu mulai dan selesai menyewa perlengkapan camping.</p>

    <div class="date-display">
        <div>
            <small>Tanggal penyewaan</small>
            <br>
            <strong id="dateText">Pilih tanggal mulai dan selesai</strong>
        </div>
        <span>📅</span>
    </div>

    <div class="calendar">
        <div class="calendar-header">
            <button type="button" id="prev">‹</button>
            <strong id="monthYear"></strong>
            <button type="button" id="next">›</button>
        </div>

        <div class="days">
            <div>Min</div><div>Sen</div><div>Sel</div>
            <div>Rab</div><div>Kam</div><div>Jum</div><div>Sab</div>
        </div>

        <div class="dates" id="dates"></div>
    </div>

    <form method="POST" id="dateForm">
        <input type="hidden" name="tanggal_mulai" id="startDate">
        <input type="hidden" name="tanggal_selesai" id="endDate">

        <button type="submit" class="submit-date" id="submitBtn" disabled>
            Lanjut Pilih Barang
        </button>
    </form>

    <?php if (isset($error)) { ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php } ?>

</section>

<script>
let current = new Date();
current.setDate(1);

let start = null;
let end = null;

const today = new Date();
today.setHours(0, 0, 0, 0);

const datesBox = document.getElementById("dates");
const monthYear = document.getElementById("monthYear");

function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

function renderCalendar() {
    datesBox.innerHTML = "";

    const year = current.getFullYear();
    const month = current.getMonth();

    monthYear.textContent = current.toLocaleDateString("id-ID", {
        month: "long",
        year: "numeric"
    });

    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();

    for (let i = 0; i < firstDay; i++) {
        datesBox.appendChild(document.createElement("span"));
    }

    for (let day = 1; day <= lastDate; day++) {
        const date = new Date(year, month, day);
        const key = formatDate(date);

        const button = document.createElement("button");
        button.type = "button";
        button.textContent = day;

        if (date < today) {
            button.disabled = true;
        }

        if (start && key === start) {
            button.classList.add("selected");
        }

        if (end && key === end) {
            button.classList.add("selected");
        }

        if (start && end && key > start && key < end) {
            button.classList.add("in-range");
        }

        if (start && key === start && end) {
            button.classList.add("range-start");
        }

        if (end && key === end && start) {
            button.classList.add("range-end");
        }

        button.addEventListener("click", () => {
            if (!start || end) {
                start = key;
                end = null;
            } else if (key < start) {
                start = key;
            } else {
                end = key;
            }

            updateSelection();
            renderCalendar();
        });

        datesBox.appendChild(button);
    }
}

function updateSelection() {
    document.getElementById("startDate").value = start || "";
    document.getElementById("endDate").value = end || "";

    const text = document.getElementById("dateText");
    const submit = document.getElementById("submitBtn");

    if (start && end) {
        text.textContent = start + " – " + end;
        submit.disabled = false;
    } else if (start) {
        text.textContent = "Mulai: " + start + " (pilih tanggal selesai)";
        submit.disabled = true;
    } else {
        text.textContent = "Pilih tanggal mulai dan selesai";
        submit.disabled = true;
    }
}

document.getElementById("prev").addEventListener("click", () => {
    current.setMonth(current.getMonth() - 1);
    renderCalendar();
});

document.getElementById("next").addEventListener("click", () => {
    current.setMonth(current.getMonth() + 1);
    renderCalendar();
});

document.getElementById("dateForm").addEventListener("submit", (event) => {
    if (!start || !end) {
        event.preventDefault();
        alert("Silakan pilih tanggal mulai dan selesai.");
    }
});

renderCalendar();
</script>

</body>
</html>
