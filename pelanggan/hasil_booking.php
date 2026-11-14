
<?php
session_start();
include "../config/database.php";

// ==========================================
// 1. CEK LOGIN
// ==========================================

if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$idUser = (int) $_SESSION["id_user"];

// ==========================================
// 2. NAVBAR DAN JUMLAH KERANJANG
// ==========================================

$halamanAktif = basename($_SERVER["PHP_SELF"]);

$jumlahKeranjang = isset($_SESSION["keranjang"])
    ? array_sum($_SESSION["keranjang"])
    : 0;

// ==========================================
// 3. JUMLAH NOTIFIKASI BELUM DIBACA
// ==========================================

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

// ==========================================
// 4. AMBIL KODE BOOKING
// ==========================================

$kodeBooking = $_GET["kode"] ?? ($_SESSION["booking_terakhir"] ?? "");

if (empty($kodeBooking)) {
    header("Location: katalog.php");
    exit;
}

// ==========================================
// 5. AMBIL DATA BOOKING PELANGGAN
// ==========================================

$stmt = mysqli_prepare($conn, "
    SELECT s.*, u.nama, u.email, u.no_hp
    FROM sewa s
    JOIN user u ON s.id_user = u.id_user
    WHERE s.kode_sewa = ? AND s.id_user = ?
");

mysqli_stmt_bind_param($stmt, "si", $kodeBooking, $idUser);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($hasil);

if (!$booking) {
    die("Data booking tidak ditemukan.");
}

// ==========================================
// 6. AMBIL DETAIL BARANG
// ==========================================

$stmtDetail = mysqli_prepare($conn, "
    SELECT ds.*, b.nama_barang
    FROM detail_sewa ds
    JOIN barang b ON ds.id_barang = b.id_barang
    WHERE ds.id_sewa = ?
");

$idSewa = (int) $booking["id_sewa"];

mysqli_stmt_bind_param($stmtDetail, "i", $idSewa);
mysqli_stmt_execute($stmtDetail);

$hasilDetail = mysqli_stmt_get_result($stmtDetail);

$daftarBarang = [];

while ($barang = mysqli_fetch_assoc($hasilDetail)) {
    $daftarBarang[] = $barang;
}

// ==========================================
// 7. AMBIL NOMOR WHATSAPP ADMIN
// ==========================================

$queryPengaturan = mysqli_query($conn, "
    SELECT no_wa_admin FROM pengaturan LIMIT 1
");

$dataPengaturan = $queryPengaturan
    ? mysqli_fetch_assoc($queryPengaturan)
    : null;

$nomorAdmin = $dataPengaturan["no_wa_admin"] ?? "";

$nomorAdmin = preg_replace('/\D+/', '', (string) $nomorAdmin);

if (substr($nomorAdmin, 0, 1) === "0") {
    $nomorAdmin = "62" . substr($nomorAdmin, 1);
}

// ==========================================
// 8. SIAPKAN PESAN WHATSAPP
// ==========================================

$pesanWA = "Halo Admin Kelana, saya ingin mengonfirmasi booking.\n\n";

$pesanWA .= "Kode Booking: " . $booking["kode_sewa"] . "\n";
$pesanWA .= "Nama: " . $booking["nama"] . "\n";
$pesanWA .= "Email: " . $booking["email"] . "\n";

$pesanWA .= "Tanggal Sewa: " .
    date("d-m-Y", strtotime($booking["tanggal_mulai"])) .
    " sampai " .
    date("d-m-Y", strtotime($booking["tanggal_selesai"])) . "\n\n";

$pesanWA .= "Daftar Barang:\n";

foreach ($daftarBarang as $barang) {
    $pesanWA .= "- " . $barang["nama_barang"] .
        " (" . $barang["jumlah"] . " unit)\n";
}

$pesanWA .= "\nTotal Pembayaran: Rp" .
    number_format($booking["total_harga"], 0, ",", ".");

$pesanWA .= "\nStatus Pembayaran: " .
    $booking["status_pembayaran"];

$linkWA = "";

if (!empty($nomorAdmin)) {
    $linkWA = "https://wa.me/" . $nomorAdmin .
        "?text=" . urlencode($pesanWA);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hasil Booking - Kelana</title>

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

        /* HASIL BOOKING */

        .result-container {
            max-width: 850px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .result-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,.08);
            margin-top: 25px;
        }

        .result-success {
            text-align: center;
            padding: 25px;
            background: #f0f5ef;
            border-radius: 10px;
        }

        .result-success h1 {
            color: #31583c;
        }

        .booking-code {
            font-size: 23px;
            font-weight: bold;
            color: #31583c;
            margin: 15px 0;
            word-break: break-word;
        }

        .result-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f7f8f5;
            border-radius: 8px;
        }

        .info-item p {
            margin: 5px 0;
        }

        .result-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .result-total {
            background: #f0f5ef;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .result-total h2 {
            color: #31583c;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background: #fff0d8;
            color: #9a681b;
            font-size: 13px;
            font-weight: bold;
        }

        .result-btn {
            display: inline-block;
            padding: 13px 20px;
            border-radius: 7px;
            text-decoration: none;
            color: white;
            background: #31583c;
            margin-top: 15px;
            border: none;
            cursor: pointer;
            text-align: center;
            transition: .2s;
        }

        .result-btn:hover {
            opacity: .88;
        }

        .wa-btn {
            background: #287b48;
        }

        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
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

        }

        @media (max-width: 600px) {

            .result-info {
                grid-template-columns: 1fr;
            }

            .result-card {
                padding: 20px;
            }

            .result-actions {
                flex-direction: column;
            }

            .result-btn {
                width: 100%;
                box-sizing: border-box;
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

    </div>

    <div class="navbar-account-group">

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

        <div class="account-dropdown">

            <a href="profil.php" class="account-trigger">
                <span>Akun</span>
                <span>⌄</span>
            </a>

            <div class="account-menu">

                <a href="profil.php"
                   class="<?php echo $halamanAktif === 'profil.php' ? 'active' : ''; ?>">
                    Profil Saya
                </a>

                <a href="riwayat.php"
                   class="<?php echo $halamanAktif === 'riwayat.php' ? 'active' : ''; ?>">
                    Riwayat Penyewaan
                </a>

                <a href="../auth/logout.php">
                    Keluar
                </a>

            </div>

        </div>

    </div>

</nav>

<!-- HASIL BOOKING -->

<section class="result-container">

    <div class="result-success">

        <h1>Booking Berhasil!</h1>

        <p>
            Terima kasih telah melakukan pemesanan
            perlengkapan camping di Kelana.
        </p>

        <p>Kode Booking Kamu:</p>

        <div class="booking-code">
            <?php echo htmlspecialchars($booking["kode_sewa"]); ?>
        </div>

        <p>Simpan kode booking ini untuk keperluan konfirmasi.</p>

    </div>

    <!-- INFORMASI PELANGGAN -->

    <div class="result-card">

        <h2>Informasi Pelanggan</h2>

        <div class="result-info">

            <div class="info-item">
                <p>Nama Pelanggan</p>
                <strong>
                    <?php echo htmlspecialchars($booking["nama"]); ?>
                </strong>
            </div>

            <div class="info-item">
                <p>Email</p>
                <strong>
                    <?php echo htmlspecialchars($booking["email"]); ?>
                </strong>
            </div>

            <div class="info-item">
                <p>Tanggal Booking</p>
                <strong>
                    <?php echo date(
                        "d M Y, H:i",
                        strtotime($booking["tanggal_booking"])
                    ); ?>
                </strong>
            </div>

            <div class="info-item">
                <p>Status Booking</p>
                <span class="status">
                    <?php echo htmlspecialchars($booking["status"]); ?>
                </span>
            </div>

        </div>

    </div>

    <!-- INFORMASI PENYEWAAN -->

    <div class="result-card">

        <h2>Informasi Penyewaan</h2>

        <div class="result-info">

            <div class="info-item">
                <p>Tanggal Mulai</p>
                <strong>
                    <?php echo date(
                        "d M Y",
                        strtotime($booking["tanggal_mulai"])
                    ); ?>
                </strong>
            </div>

            <div class="info-item">
                <p>Tanggal Selesai</p>
                <strong>
                    <?php echo date(
                        "d M Y",
                        strtotime($booking["tanggal_selesai"])
                    ); ?>
                </strong>
            </div>

            <div class="info-item">
                <p>Status Pembayaran</p>
                <span class="status">
                    <?php echo htmlspecialchars(
                        $booking["status_pembayaran"]
                    ); ?>
                </span>
            </div>

        </div>

    </div>

    <!-- RINCIAN BARANG -->

    <div class="result-card">

        <h2>Rincian Barang</h2>

        <?php foreach ($daftarBarang as $barang) { ?>

            <div class="result-item">

                <h3>
                    <?php echo htmlspecialchars($barang["nama_barang"]); ?>
                </h3>

                <p>
                    Jumlah:
                    <?php echo (int) $barang["jumlah"]; ?> unit
                </p>

                <p>
                    Harga per hari:
                    Rp <?php echo number_format(
                        $barang["harga_per_hari"], 0, ",", "."
                    ); ?>
                </p>

                <strong>
                    Subtotal:
                    Rp <?php echo number_format(
                        $barang["subtotal"], 0, ",", "."
                    ); ?>
                </strong>

            </div>

        <?php } ?>

        <div class="result-total">

            <h2>
                Total Pembayaran:
                Rp <?php echo number_format(
                    $booking["total_harga"], 0, ",", "."
                ); ?>
            </h2>

            <p>
                Status pembayaran:
                <strong>
                    <?php echo htmlspecialchars(
                        $booking["status_pembayaran"]
                    ); ?>
                </strong>
            </p>

        </div>

    </div>

    <!-- LANGKAH SELANJUTNYA -->

    <div class="result-card">

        <h2>Langkah Selanjutnya</h2>

        <p>
            Hubungi admin Kelana melalui WhatsApp untuk
            melakukan konfirmasi booking dan informasi pembayaran.
        </p>

        <div class="result-actions">

            <?php if (!empty($linkWA)) { ?>

                <a
                    href="<?php echo htmlspecialchars($linkWA); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="result-btn wa-btn"
                >
                    Hubungi Admin via WhatsApp
                </a>

            <?php } else { ?>

                <p style="color:#a33;">
                    Nomor WhatsApp admin belum diatur.
                    Silakan hubungi admin secara langsung.
                </p>

            <?php } ?>

            <a href="katalog.php" class="result-btn">
                Kembali ke Katalog
            </a>

        </div>

    </div>

</section>

<?php
// POPUP NOTIFIKASI PELANGGAN
include "notifikasi_popup.php";
?>

</body>
</html>
