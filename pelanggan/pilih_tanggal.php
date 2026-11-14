
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// 1. CEK HAK AKSES PENGGUNA
// ==========================================

$isAdmin = false;
$isPelanggan = false;
$jumlahNotifikasi = 0;

if (isset($_SESSION["id_user"])) {

    $idUser = (int) $_SESSION["id_user"];

    $cekAdmin = mysqli_query(
        $conn,
        "SELECT role FROM user WHERE id_user = $idUser"
    );

    $dataAdmin = mysqli_fetch_assoc($cekAdmin);

    if ($dataAdmin) {
        $isAdmin = ($dataAdmin["role"] === "admin");
        $isPelanggan = ($dataAdmin["role"] === "pelanggan");
    }

    // ==========================================
    // 2. JUMLAH NOTIFIKASI BELUM DIBACA
    // ==========================================

    if ($isPelanggan) {

        $stmtNotif = mysqli_prepare($conn, "
            SELECT COUNT(*) AS jumlah
            FROM notifikasi_pelanggan
            WHERE id_user = ?
            AND dibaca = 0
        ");

        mysqli_stmt_bind_param($stmtNotif, "i", $idUser);
        mysqli_stmt_execute($stmtNotif);

        $hasilNotif = mysqli_stmt_get_result($stmtNotif);
        $dataNotif = mysqli_fetch_assoc($hasilNotif);

        $jumlahNotifikasi = (int) $dataNotif["jumlah"];

        mysqli_stmt_close($stmtNotif);
    }
}

// ==========================================
// 3. NAVBAR DAN JUMLAH KERANJANG
// ==========================================

$halamanAktif = basename($_SERVER["PHP_SELF"]);

$jumlahKeranjang = isset($_SESSION["keranjang"])
    ? array_sum($_SESSION["keranjang"])
    : 0;

// ==========================================
// 4. ATURAN PENYEWAAN KELANA
// ==========================================

$maksHariSewa = 14;
$toleransiPengembalian = 2;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $mulai = $_POST["tanggal_mulai"] ?? "";
    $selesai = $_POST["tanggal_selesai"] ?? "";

    $awal = DateTime::createFromFormat("!Y-m-d", $mulai);
    $akhir = DateTime::createFromFormat("!Y-m-d", $selesai);
    $hariIni = new DateTime("today");

    if (
        !$awal || !$akhir ||
        $awal->format("Y-m-d") !== $mulai ||
        $akhir->format("Y-m-d") !== $selesai ||
        $awal < $hariIni ||
        $akhir < $awal
    ) {

        $error = "Tanggal penyewaan tidak valid.";

    } else {

        $durasiSewa = $awal->diff($akhir)->days + 1;

        if ($durasiSewa > $maksHariSewa) {

            $error = "Maksimal penyewaan Kelana adalah 14 hari.";

        } else {

            $batasPengembalian = clone $akhir;

            $batasPengembalian->modify(
                "+{$toleransiPengembalian} days"
            );

            $_SESSION["tanggal_mulai"] = $mulai;
            $_SESSION["tanggal_selesai"] = $selesai;

            $_SESSION["batas_pengembalian"] =
                $batasPengembalian->format("Y-m-d");

            header("Location: katalog.php");
            exit;
        }
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

/* LATAR HALAMAN */

body {
    background-color: transparent;
}

/* LOGO NAVBAR */

.navbar .logo-link {
    display: flex;
    align-items: center;
    text-decoration: none;
    flex-shrink: 0;
}

.navbar .logo-link img {
    width: 220px;
    height: 100px;
    object-fit: contain;
    display: block;
}

/* NAVBAR */

.navbar {
    flex-wrap: wrap;
    gap: 15px;
}

.menu {
    flex-wrap: wrap;
}

.navbar .menu a.active {
    color: #31583c;
    font-weight: 700;
    position: relative;
}

.navbar .menu a.active::after {
    content: "";
    position: absolute;
    bottom: -6px;
    left: 0;
    width: 100%;
    height: 2px;
    background: #31583c;
}

/* KELOMPOK NOTIFIKASI DAN AKUN */

.navbar-account-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

/* LONCENG NOTIFIKASI */

.notification-link {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    color: #31583c;
    text-decoration: none;
    border-radius: 50%;
    transition: .2s;
}

.notification-link:hover {
    background: #f0f5f0;
}

.notification-link svg {
    width: 23px;
    height: 23px;
}

.notification-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #dc3545;
    color: white;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
    border: 2px solid white;
}

/* DROPDOWN AKUN */

.account-dropdown {
    position: relative;
    display: inline-block;
}

.account-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #31583c;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
}

.account-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    min-width: 190px;
    background: white;
    box-shadow: 0 4px 15px rgba(0,0,0,.12);
    border-radius: 8px;
    padding: 8px 0;
    z-index: 1000;
}

.account-dropdown:hover .account-menu {
    display: block;
}

.account-menu a {
    display: block;
    padding: 11px 16px;
    color: #31583c;
    text-decoration: none;
    font-size: 14px;
}

.account-menu a:hover,
.account-menu a.active {
    background: #f0f5f0;
    font-weight: 700;
}

/* TOMBOL ADMIN */

.admin-back-link {
    display: inline-block;
    background: #31583c;
    color: white !important;
    padding: 9px 14px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
}

.admin-back-link:hover {
    background: #24442d;
}

/* PILIH TANGGAL */

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

@media (max-width: 700px) {

    .navbar {
        flex-wrap: wrap;
        gap: 15px;
    }

    .navbar .logo-link img {
        width: 120px;
        height: 55px;
    }

    .menu {
        flex-wrap: wrap;
    }

    .navbar-account-group {
        margin-left: auto;
    }

    .booking-date {
        margin: 30px 15px;
        padding: 20px;
    }
}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <a href="../index.php" class="logo-link">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>

    <div class="menu">

        <a href="../index.php"
           class="<?php echo $halamanAktif === 'index.php' ? 'active' : ''; ?>">
            Beranda
        </a>

        <a href="katalog.php"
           class="<?php echo $halamanAktif === 'katalog.php' ? 'active' : ''; ?>">
            Katalog
        </a>

        <a href="keranjang.php"
           class="<?php echo $halamanAktif === 'keranjang.php' ? 'active' : ''; ?>">
            Keranjang (<?php echo $jumlahKeranjang; ?>)
        </a>

        <a href="tentang.php"
           class="<?php echo $halamanAktif === 'tentang.php' ? 'active' : ''; ?>">
            Tentang Kami
        </a>

        <a href="faq.php"
           class="<?php echo $halamanAktif === 'faq.php' ? 'active' : ''; ?>">
            FAQ
        </a>

        <?php if ($isAdmin) { ?>

            <a href="../admin/dashboard.php" class="admin-back-link">
                Kembali ke Panel Admin
            </a>

        <?php } ?>

    </div>

    <?php if ($isPelanggan) { ?>

        <div class="navbar-account-group">

            <!-- LONCENG NOTIFIKASI -->

            <a href="notifikasi.php"
               class="notification-link"
               title="Notifikasi">

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                    <path d="M10 21h4"></path>
                </svg>

                <?php if ($jumlahNotifikasi > 0) { ?>

                    <span class="notification-badge">
                        <?php echo $jumlahNotifikasi > 99 ? '99+' : $jumlahNotifikasi; ?>
                    </span>

                <?php } ?>

            </a>

            <!-- DROPDOWN AKUN -->

            <div class="account-dropdown">

                <a href="profil.php" class="account-trigger">
                    <span>Akun</span>
                    <span>⌄</span>
                </a>

                <div class="account-menu">

                    <a href="profil.php">
                        Profil Saya
                    </a>

                    <a href="riwayat.php">
                        Riwayat Penyewaan
                    </a>

                    <a href="../auth/logout.php">
                        Keluar
                    </a>

                </div>

            </div>

        </div>

    <?php } elseif (isset($_SESSION["id_user"])) { ?>

        <a href="profil.php" class="login">Akun Saya</a>

    <?php } else { ?>

        <a href="../auth/login.php" class="login">Masuk</a>

    <?php } ?>

</nav>

<!-- PILIH TANGGAL -->

<section class="booking-date">

    <h1>Pilih Tanggal Penyewaan</h1>

    <p>
        Tentukan kapan kamu mulai dan selesai menyewa perlengkapan camping.
    </p>

    <div class="date-display">

        <div>
            <small>Tanggal penyewaan</small>
            <br>
            <strong id="dateText">
                Pilih tanggal mulai dan selesai
            </strong>
        </div>

        <span>📅</span>

    </div>

    <div style="background:#f0f5ef; padding:15px; border-radius:8px; margin-bottom:20px;">

        <strong>Informasi Penyewaan Kelana</strong>

        <p style="margin:8px 0;">
            • Maksimal durasi sewa: 14 hari.
        </p>

        <p style="margin:8px 0;">
            • Toleransi pengembalian: 2 hari setelah tanggal selesai sewa.
        </p>

        <p style="margin:8px 0;">
            • Denda berlaku jika melewati batas toleransi.
        </p>

    </div>

    <div id="rentalInfo" style="margin-bottom:15px; color:#31583c;"></div>

    <div class="calendar">

        <div class="calendar-header">

            <button type="button" id="prev">‹</button>

            <strong id="monthYear"></strong>

            <button type="button" id="next">›</button>

        </div>

        <div class="days">

            <div>Min</div>
            <div>Sen</div>
            <div>Sel</div>
            <div>Rab</div>
            <div>Kam</div>
            <div>Jum</div>
            <div>Sab</div>

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

        <p class="error">
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php } ?>

</section>

<script>

// Setiap membuka halaman, mulai dengan pilihan tanggal yang kosong
let start = null;
let end = null;

const MAKS_HARI = 14;
const TOLERANSI = 2;

let current = new Date();

if (start) {

    const tanggalAwal = new Date(start + "T00:00:00");

    current = new Date(
        tanggalAwal.getFullYear(),
        tanggalAwal.getMonth(),
        1
    );

} else {

    current.setDate(1);

}

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

function formatIndonesia(dateString) {

    const date = new Date(dateString + "T00:00:00");

    return date.toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric"
    });
}

function hitungDurasi(tanggalAwal, tanggalAkhir) {

    const awal = new Date(tanggalAwal + "T00:00:00");
    const akhir = new Date(tanggalAkhir + "T00:00:00");

    return Math.round((akhir - awal) / 86400000) + 1;
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

        if (start && !end) {

            const batasTanggal = new Date(start + "T00:00:00");

            batasTanggal.setDate(
                batasTanggal.getDate() + MAKS_HARI - 1
            );

            if (date > batasTanggal) {
                button.disabled = true;
            }
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

                const durasi = hitungDurasi(start, key);

                if (durasi > MAKS_HARI) {

                    alert("Maksimal penyewaan adalah 14 hari.");
                    return;

                }

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
    const info = document.getElementById("rentalInfo");

    if (start && end) {

        const durasi = hitungDurasi(start, end);

        const batas = new Date(end + "T00:00:00");

        batas.setDate(batas.getDate() + TOLERANSI);

        const tanggalBatas = formatDate(batas);

        text.textContent =
            formatIndonesia(start) + " – " + formatIndonesia(end);

        info.innerHTML = `
            <strong>Durasi penyewaan:</strong> ${durasi} hari<br>
            <strong>Batas pengembalian:</strong>
            ${formatIndonesia(tanggalBatas)}<br>
            <small>Pengembalian sampai tanggal tersebut tidak dikenakan denda.</small>
        `;

        submit.disabled = false;

    } else if (start) {

        text.textContent =
            "Mulai: " + formatIndonesia(start) +
            " (pilih tanggal selesai)";

        info.textContent =
            "Silakan pilih tanggal selesai maksimal 14 hari dari tanggal mulai.";

        submit.disabled = true;

    } else {

        text.textContent = "Pilih tanggal mulai dan selesai";

        info.textContent = "";

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

    if (!start || !end || hitungDurasi(start, end) > MAKS_HARI) {

        event.preventDefault();

        alert("Silakan pilih tanggal yang valid. Maksimal sewa 14 hari.");

    }

});

updateSelection();
renderCalendar();

</script>

<?php
// POPUP NOTIFIKASI KHUSUS PELANGGAN
if ($isPelanggan) {
    include "notifikasi_popup.php";
}
?>

</body>
</html>