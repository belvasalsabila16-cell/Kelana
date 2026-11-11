
<?php
session_start();
include "../config/database.php";

// Pastikan pelanggan sudah login
if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil kode booking
$kodeBooking = $_GET["kode"] ?? ($_SESSION["booking_terakhir"] ?? "");

if (empty($kodeBooking)) {
    header("Location: katalog.php");
    exit;
}

// Ambil data booking milik pelanggan yang sedang login
$stmt = mysqli_prepare($conn, "
    SELECT s.*, u.nama, u.email, u.no_hp
    FROM sewa s
    JOIN user u ON s.id_user = u.id_user
    WHERE s.kode_sewa = ? AND s.id_user = ?
");

$idUser = (int) $_SESSION["id_user"];

mysqli_stmt_bind_param($stmt, "si", $kodeBooking, $idUser);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($hasil);

// Pastikan booking ditemukan
if (!$booking) {
    die("Data booking tidak ditemukan.");
}

// Ambil detail barang yang dipesan
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

// Ambil nomor WhatsApp admin dari tabel pengaturan
$queryPengaturan = mysqli_query($conn, "
    SELECT no_wa_admin FROM pengaturan LIMIT 1
");

$dataPengaturan = $queryPengaturan
    ? mysqli_fetch_assoc($queryPengaturan)
    : null;

$nomorAdmin = $dataPengaturan["no_wa_admin"] ?? "";

// Bersihkan nomor dari karakter selain angka
$nomorAdmin = preg_replace('/\D+/', '', (string) $nomorAdmin);

// Ubah format nomor Indonesia menjadi internasional
if (substr($nomorAdmin, 0, 1) === "0") {
    $nomorAdmin = "62" . substr($nomorAdmin, 1);
}

// Siapkan pesan WhatsApp
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

// Buat tautan WhatsApp
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

<nav class="navbar">

    <h2>KELANA.</h2>

    <div class="menu">
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
        <a href="keranjang.php">Keranjang</a>
        <a href="tentang.php">Tentang Kami</a>
        <a href="faq.php">FAQ</a>
    </div>

    <a href="profil.php" class="login">Profil Saya</a>

</nav>

<section class="result-container">

    <!-- Informasi booking berhasil -->
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

    <!-- Informasi pelanggan -->
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

    <!-- Informasi penyewaan -->
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

    <!-- Rincian barang -->
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

    <!-- Langkah selanjutnya -->
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

</body>
</html>
