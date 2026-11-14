
<?php
session_start();
include "../config/database.php";

// ==========================================
// 1. CEK HAK AKSES ADMIN
// ==========================================

if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION["id_user"];

$queryUser = mysqli_query($conn, "
    SELECT nama, role FROM user
    WHERE id_user = $id_user
");

$user = mysqli_fetch_assoc($queryUser);

if (!$user || $user["role"] !== "admin") {
    header("Location: ../index.php");
    exit;
}

// ==========================================
// 2. STATISTIK BARANG
// ==========================================

$queryBarang = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM barang
");

$totalBarang = mysqli_fetch_assoc($queryBarang)["total"];

// ==========================================
// 3. STATISTIK BOOKING
// ==========================================

$queryBooking = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM sewa
    WHERE status = 'Dipesan'
");

$bookingBaru = mysqli_fetch_assoc($queryBooking)["total"];

// ==========================================
// 4. PENYEWAAN AKTIF
// ==========================================

$queryAktif = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM sewa
    WHERE status = 'Diambil'
");

$sewaAktif = mysqli_fetch_assoc($queryAktif)["total"];

// ==========================================
// 5. TOTAL PENDAPATAN LUNAS
// ==========================================

$queryPendapatan = mysqli_query($conn, "
    SELECT COALESCE(SUM(total_harga + denda), 0) AS total
    FROM sewa
    WHERE status_pembayaran = 'Lunas'
");

$totalPendapatan = mysqli_fetch_assoc($queryPendapatan)["total"];

// ==========================================
// 6. BOOKING TERBARU
// ==========================================

$queryTerbaru = mysqli_query($conn, "
    SELECT s.*, u.nama
    FROM sewa s
    JOIN user u ON s.id_user = u.id_user
    ORDER BY s.tanggal_booking DESC
    LIMIT 5
");

// ==========================================
// 7. BATAS NOTIFIKASI SAAT DASHBOARD DIBUKA
// ==========================================

if (!isset($_SESSION["notifikasi_terakhir_dilihat"])) {

    $hasilAwal = mysqli_query($conn, "
        SELECT MAX(id_notifikasi) AS id_terakhir
        FROM notifikasi_admin
    ");

    $dataAwal = mysqli_fetch_assoc($hasilAwal);

    $_SESSION["notifikasi_terakhir_dilihat"] =
        (int) ($dataAwal["id_terakhir"] ?? 0);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background-color: transparent;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 245px;
            background: #31583c;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .sidebar-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 8px;
        }

        .sidebar-logo a {
            display: inline-block;
            text-decoration: none;
        }

        .sidebar-logo img {
            display: block;
            width: 190px;
            height: 90px;
            object-fit: contain;
        }

        .sidebar .subtitle {
            text-align: center;
            font-size: 12px;
            color: #d9e5d8;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            color: #e8efe7;
            text-decoration: none;
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #456e4f;
            color: white;
        }

        .sidebar .logout {
            margin-top: 35px;
            color: #ffd6d6;
        }

        /* KONTEN */

        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .topbar h1 {
            color: #31583c;
            margin: 0 0 8px;
            font-size: 27px;
        }

        .topbar p {
            margin: 0;
            color: #777;
            font-size: 14px;
        }

        .admin-info {
            background: white;
            padding: 12px 18px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            color: #31583c;
            font-size: 14px;
        }

        /* KARTU STATISTIK */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            display: block;
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            text-decoration: none;
            color: inherit;
            transition: .2s ease;
        }

        .stat-card p {
            color: #777;
            font-size: 13px;
            margin: 0 0 12px;
        }

        .stat-card h2 {
            color: #31583c;
            font-size: 27px;
            margin: 0;
        }

        a.stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0,0,0,.12);
            background: #f8fbf7;
        }

        a.stat-card:focus-visible {
            outline: 3px solid #8bad8e;
            outline-offset: 3px;
        }

        .stat-card .card-hint {
            display: block;
            color: #78917b;
            font-size: 11px;
            margin-top: 12px;
        }

        .stat-card.information-card {
            cursor: default;
        }

        /* TABEL */

        .table-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            overflow-x: auto;
        }

        .table-container h2 {
            color: #31583c;
            font-size: 19px;
            margin: 0 0 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        th {
            background: #f1f5ef;
            color: #31583c;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #fff2d8;
            color: #9a6b19;
            font-size: 11px;
        }

        .status.lunas {
            background: #e2f3e5;
            color: #28733a;
        }

        .status.diambil {
            background: #e3edff;
            color: #315c9b;
        }

        .empty-data {
            text-align: center;
            color: #888;
            padding: 25px;
        }

        /* POP-UP NOTIFIKASI BOOKING */

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
        }

        .popup-overlay[hidden] {
            display: none;
        }

        .popup-box {
            background: white;
            width: 100%;
            max-width: 420px;
            padding: 28px;
            border-radius: 15px;
            box-shadow: 0 10px 35px rgba(0,0,0,.2);
            position: relative;
            animation: popupMasuk .3s ease;
        }

        .popup-box h2 {
            color: #31583c;
            font-size: 21px;
            margin: 0 0 12px;
        }

        .popup-box > p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .popup-close {
            position: absolute;
            right: 15px;
            top: 12px;
            background: none;
            color: #777;
            font-size: 24px;
            padding: 3px 8px;
            border: none;
            cursor: pointer;
        }

        .popup-close:hover {
            background: #eee;
            color: #333;
        }

        .popup-item {
            background: #f3f7f1;
            padding: 13px;
            border-radius: 8px;
            margin: 12px 0;
            border-left: 4px solid #d69a35;
        }

        .popup-item p {
            margin: 0;
            color: #31583c;
            font-size: 14px;
            line-height: 1.5;
        }

        .popup-item small {
            display: block;
            color: #888;
            margin-top: 7px;
        }

        .popup-action {
            display: inline-block;
            background: #31583c;
            color: white;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 7px;
            font-size: 13px;
            margin-top: 10px;
        }

        .popup-action:hover {
            background: #456e4f;
        }

        @keyframes popupMasuk {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 700px) {

            .admin-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
            }

            .sidebar-logo img {
                width: 150px;
                height: 75px;
            }

            .sidebar a {
                display: inline-block;
                margin: 3px;
            }

            .main-content {
                padding: 22px 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-card h2 {
                font-size: 21px;
            }

        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-logo">
            <a href="../index.php">
                <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
            </a>
        </div>

        <div class="subtitle">PANEL ADMIN</div>

        <a href="dashboard.php" class="active">Dashboard</a>

        <a href="barang.php">Data Barang</a>

        <a href="kategori.php">Kategori</a>

        <a href="member.php">Manajemen Member</a>

        <a href="booking.php">Data Booking</a>

        <a href="pengembalian.php">Pengembalian</a>

        <a href="faq.php">Kelola FAQ</a>

        <a href="laporan.php">Laporan</a>

        <a href="../index.php">Lihat Website</a>

        <a href="../auth/logout.php"
           class="logout"
           onclick="return confirm('Yakin ingin keluar dari akun?')">

            Keluar

        </a>

    </aside>

    <!-- KONTEN DASHBOARD -->

    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Dashboard Admin</h1>

                <p>
                    Selamat datang di panel pengelolaan Kelana.
                </p>

            </div>

            <div class="admin-info">

                Admin: <strong>
                    <?php echo htmlspecialchars($user["nama"]); ?>
                </strong>

            </div>

        </div>

        <!-- STATISTIK -->

        <div class="stats-grid">

            <a href="barang.php" class="stat-card">

                <p>Total Barang</p>

                <h2>
                    <?php echo $totalBarang; ?>
                </h2>

                <span class="card-hint">
                    Klik untuk mengelola barang →
                </span>

            </a>

            <a href="booking.php" class="stat-card">

                <p>Booking Baru</p>

                <h2>
                    <?php echo $bookingBaru; ?>
                </h2>

                <span class="card-hint">
                    Klik untuk melihat booking →
                </span>

            </a>

            <a href="pengembalian.php" class="stat-card">

                <p>Penyewaan Aktif</p>

                <h2>
                    <?php echo $sewaAktif; ?>
                </h2>

                <span class="card-hint">
                    Klik untuk mengelola pengembalian →
                </span>

            </a>

            <div class="stat-card information-card">

                <p>Total Pendapatan Lunas</p>

                <h2 style="font-size:21px;">

                    Rp <?php echo number_format(
                        $totalPendapatan,
                        0,
                        ',',
                        '.'
                    ); ?>

                </h2>

                <span class="card-hint">
                    Total pembayaran yang sudah lunas
                </span>

            </div>

        </div>

        <!-- BOOKING TERBARU -->

        <div class="table-container">

            <h2>Booking Terbaru</h2>

            <table>

                <thead>

                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Sewa</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if ($queryTerbaru && mysqli_num_rows($queryTerbaru) > 0) { ?>

                        <?php $no = 1; ?>

                        <?php while ($booking = mysqli_fetch_assoc($queryTerbaru)) { ?>

                            <tr>

                                <td>
                                    <?php echo $no++; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($booking["kode_sewa"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($booking["nama"]); ?>
                                </td>

                                <td>

                                    <?php
                                    echo date(
                                        'd-m-Y',
                                        strtotime($booking["tanggal_mulai"])
                                    );
                                    ?>

                                </td>

                                <td>

                                    Rp <?php echo number_format(
                                        $booking["total_harga"],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>

                                </td>

                                <td>

                                    <span class="status <?php
                                        echo $booking["status"] === "Diambil"
                                            ? "diambil" : "";
                                    ?>">

                                        <?php echo htmlspecialchars($booking["status"]); ?>

                                    </span>

                                </td>

                                <td>

                                    <span class="status <?php
                                        echo $booking["status_pembayaran"] === "Lunas"
                                            ? "lunas" : "";
                                    ?>">

                                        <?php echo htmlspecialchars(
                                            $booking["status_pembayaran"]
                                        ); ?>

                                    </span>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="7" class="empty-data">
                                Belum ada data booking.
                            </td>

                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </main>

</div>

<!-- POP-UP NOTIFIKASI BOOKING -->

<div class="popup-overlay" id="popupNotifikasi" hidden>

    <div class="popup-box">

        <button
            type="button"
            class="popup-close"
            onclick="tutupPopup()"
            aria-label="Tutup notifikasi"
        >
            &times;
        </button>

        <h2>Booking Baru!</h2>

        <p>
            Ada pelanggan yang baru saja melakukan booking.
        </p>

        <div id="isiNotifikasi"></div>

        <a href="booking.php" class="popup-action">
            Lihat Booking
        </a>

    </div>

</div>

<!-- JAVASCRIPT NOTIFIKASI OTOMATIS -->

<script>

let sedangMemeriksa = false;

function cekNotifikasi() {

    if (sedangMemeriksa) {
        return;
    }

    sedangMemeriksa = true;

    fetch("cek_notifikasi.php", {
        method: "GET",
        cache: "no-store"
    })

    .then(response => {

        if (!response.ok) {
            throw new Error("Gagal mengambil notifikasi");
        }

        return response.json();

    })

    .then(data => {

        if (
            data.status === "berhasil" &&
            data.notifikasi &&
            data.notifikasi.length > 0
        ) {

            const popup = document.getElementById("popupNotifikasi");
            const isi = document.getElementById("isiNotifikasi");

            isi.innerHTML = "";

            data.notifikasi.forEach(item => {

                const kotak = document.createElement("div");
                kotak.className = "popup-item";

                const pesan = document.createElement("p");
                pesan.textContent = item.pesan;

                const tanggal = document.createElement("small");
                tanggal.textContent = item.tanggal_notifikasi;

                kotak.appendChild(pesan);
                kotak.appendChild(tanggal);

                isi.appendChild(kotak);

            });

            popup.hidden = false;

        }

    })

    .catch(error => {

        console.error("Notifikasi:", error);

    })

    .finally(() => {

        sedangMemeriksa = false;

    });

}

function tutupPopup() {

    document.getElementById("popupNotifikasi").hidden = true;

}

cekNotifikasi();

setInterval(cekNotifikasi, 5000);

</script>

</body>
</html>
