
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// 1. CEK HAK AKSES ADMIN
// ==========================================

$isAdmin = false;

if (isset($_SESSION["id_user"])) {

    $idUser = (int) $_SESSION["id_user"];

    $cekAdmin = mysqli_query(
        $conn,
        "SELECT role FROM user WHERE id_user = $idUser"
    );

    $dataAdmin = mysqli_fetch_assoc($cekAdmin);

    if ($dataAdmin && $dataAdmin["role"] === "admin") {
        $isAdmin = true;
    }
}

// ==========================================
// 2. PASTIKAN PELANGGAN SUDAH LOGIN
// ==========================================

if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php?lanjut=booking");
    exit;
}

// ==========================================
// 3. PASTIKAN AKUN BUKAN ADMIN
// ==========================================

if ($isAdmin) {
    header("Location: ../admin/dashboard.php");
    exit;
}

// ==========================================
// 4. PASTIKAN TANGGAL PENYEWAAN TERSEDIA
// ==========================================

if (
    empty($_SESSION["tanggal_mulai"]) ||
    empty($_SESSION["tanggal_selesai"])
) {
    header("Location: pilih_tanggal.php");
    exit;
}

// ==========================================
// 5. PASTIKAN KERANJANG TIDAK KOSONG
// ==========================================

$keranjang = $_SESSION["keranjang"] ?? [];

if (empty($keranjang)) {
    header("Location: keranjang.php");
    exit;
}

// ==========================================
// 6. AMBIL TANGGAL PENYEWAAN
// ==========================================

$awal = new DateTime($_SESSION["tanggal_mulai"]);
$akhir = new DateTime($_SESSION["tanggal_selesai"]);

$jumlahHari = $awal->diff($akhir)->days + 1;

$tanggalMulai = $awal->format("Y-m-d");
$tanggalSelesai = $akhir->format("Y-m-d");

// Batas maksimal penyewaan 14 hari
if ($jumlahHari > 14 || $akhir < $awal) {
    die("Periode penyewaan tidak valid. Maksimal penyewaan adalah 14 hari.");
}

// ==========================================
// 7. AMBIL PENGATURAN JEDA HARI
// ==========================================

$pengaturan = mysqli_query(
    $conn,
    "SELECT jeda_hari FROM pengaturan LIMIT 1"
);

$dataPengaturan = $pengaturan
    ? mysqli_fetch_assoc($pengaturan)
    : null;

$jedaHari = (int) ($dataPengaturan["jeda_hari"] ?? 0);

// ==========================================
// 8. AMBIL DATA BARANG DI KERANJANG
// ==========================================

$ids = array_map("intval", array_keys($keranjang));
$idList = implode(",", $ids);

$queryBarang = mysqli_query($conn, "
    SELECT * FROM barang
    WHERE id_barang IN ($idList)
");

$daftarBarang = [];
$totalHarga = 0;

while ($barang = mysqli_fetch_assoc($queryBarang)) {

    $id = (int) $barang["id_barang"];
    $jumlah = (int) ($keranjang[$id] ?? 0);

    if ($jumlah <= 0) {
        continue;
    }

    $subtotal = $barang["harga_sewa"] * $jumlah * $jumlahHari;

    $barang["jumlah"] = $jumlah;
    $barang["subtotal"] = $subtotal;

    $daftarBarang[] = $barang;
    $totalHarga += $subtotal;
}

// ==========================================
// 9. HITUNG DISKON
// ==========================================

$persentaseDiskon = 10;
$batasDiskon = 200000;

$diskon = 0;

if ($totalHarga >= $batasDiskon) {
    $diskon = round($totalHarga * $persentaseDiskon / 100);
}

$totalSetelahDiskon = $totalHarga - $diskon;

$pesan = "";

// ==========================================
// 10. PROSES KONFIRMASI BOOKING
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["buat_booking"])
) {

    if (empty($daftarBarang)) {

        $pesan = "Keranjang kamu kosong.";

    } else {

        try {

            mysqli_begin_transaction($conn);

            $totalBooking = 0;
            $barangBooking = [];

            foreach ($daftarBarang as $item) {

                $idBarang = (int) $item["id_barang"];
                $jumlahPesan = (int) $item["jumlah"];

                $stmtBarang = mysqli_prepare($conn, "
                    SELECT id_barang, nama_barang, stok, harga_sewa
                    FROM barang
                    WHERE id_barang = ?
                    FOR UPDATE
                ");

                mysqli_stmt_bind_param(
                    $stmtBarang,
                    "i",
                    $idBarang
                );

                mysqli_stmt_execute($stmtBarang);

                $hasilBarang = mysqli_stmt_get_result($stmtBarang);
                $dataBarang = mysqli_fetch_assoc($hasilBarang);

                mysqli_stmt_close($stmtBarang);

                if (!$dataBarang) {
                    throw new Exception("Barang tidak ditemukan.");
                }

                $stmtStok = mysqli_prepare($conn, "
                    SELECT COALESCE(SUM(ds.jumlah), 0) AS stok_terpakai
                    FROM detail_sewa ds
                    JOIN sewa s ON ds.id_sewa = s.id_sewa
                    WHERE ds.id_barang = ?
                    AND s.status IN ('Dipesan', 'Diambil')
                    AND s.tanggal_mulai <= DATE_ADD(?, INTERVAL ? DAY)
                    AND DATE_ADD(s.tanggal_selesai, INTERVAL ? DAY) >= ?
                ");

                mysqli_stmt_bind_param(
                    $stmtStok,
                    "isiis",
                    $idBarang,
                    $tanggalSelesai,
                    $jedaHari,
                    $jedaHari,
                    $tanggalMulai
                );

                mysqli_stmt_execute($stmtStok);

                $hasilStok = mysqli_stmt_get_result($stmtStok);
                $dataStok = mysqli_fetch_assoc($hasilStok);

                mysqli_stmt_close($stmtStok);

                $stokTerpakai = (int) $dataStok["stok_terpakai"];

                $stokTersedia =
                    (int) $dataBarang["stok"] - $stokTerpakai;

                if ($jumlahPesan > $stokTersedia) {

                    throw new Exception(
                        "Stok " . $dataBarang["nama_barang"] .
                        " tidak mencukupi untuk tanggal yang dipilih. " .
                        "Stok tersedia: " . max(0, $stokTersedia) . " unit."
                    );
                }

                $harga = (float) $dataBarang["harga_sewa"];

                $subtotal = $harga * $jumlahPesan * $jumlahHari;

                $totalBooking += $subtotal;

                $barangBooking[] = [
                    "id_barang" => $idBarang,
                    "jumlah" => $jumlahPesan,
                    "harga" => $harga,
                    "subtotal" => $subtotal
                ];
            }

            // ==========================================
            // 11. HITUNG DISKON BERDASARKAN TOTAL TERBARU
            // ==========================================

            $diskonBooking = 0;

            if ($totalBooking >= $batasDiskon) {
                $diskonBooking = round(
                    $totalBooking * $persentaseDiskon / 100
                );
            }

            $totalBayarBooking = $totalBooking - $diskonBooking;

            // ==========================================
            // 12. BUAT KODE BOOKING
            // ==========================================

            $kodeBooking =
                "KLN" . date("ymd") .
                strtoupper(bin2hex(random_bytes(3)));

            // ==========================================
            // 13. SIMPAN DATA UTAMA PENYEWAAN
            // ==========================================

            $stmtSewa = mysqli_prepare($conn, "
                INSERT INTO sewa
                (
                    kode_sewa,
                    id_user,
                    tanggal_mulai,
                    tanggal_selesai,
                    total_harga,
                    status,
                    status_pembayaran
                )
                VALUES (?, ?, ?, ?, ?, 'Dipesan', 'Belum Bayar')
            ");

            $idUser = (int) $_SESSION["id_user"];

            mysqli_stmt_bind_param(
                $stmtSewa,
                "sissd",
                $kodeBooking,
                $idUser,
                $tanggalMulai,
                $tanggalSelesai,
                $totalBayarBooking
            );

            if (!mysqli_stmt_execute($stmtSewa)) {
                throw new Exception("Gagal menyimpan booking.");
            }

            $idSewa = mysqli_insert_id($conn);

            mysqli_stmt_close($stmtSewa);

            // ==========================================
            // 14. SIMPAN DETAIL BARANG YANG DISEWA
            // ==========================================

            $stmtDetail = mysqli_prepare($conn, "
                INSERT INTO detail_sewa
                (
                    id_sewa,
                    id_barang,
                    jumlah,
                    harga_per_hari,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($barangBooking as $item) {

                mysqli_stmt_bind_param(
                    $stmtDetail,
                    "iiidd",
                    $idSewa,
                    $item["id_barang"],
                    $item["jumlah"],
                    $item["harga"],
                    $item["subtotal"]
                );

                if (!mysqli_stmt_execute($stmtDetail)) {
                    throw new Exception("Gagal menyimpan detail booking.");
                }
            }

            mysqli_stmt_close($stmtDetail);

            // ==========================================
            // 15. SIMPAN NOTIFIKASI UNTUK ADMIN
            // ==========================================

            $namaPelanggan = $_SESSION["nama"] ?? "Pelanggan";

            $isiNotifikasi =
                "Booking baru dari " . $namaPelanggan .
                " dengan kode booking " . $kodeBooking;

            $stmtNotifikasi = mysqli_prepare($conn, "
                INSERT INTO notifikasi_admin (id_sewa, pesan)
                VALUES (?, ?)
            ");

            if (!$stmtNotifikasi) {
                throw new Exception("Gagal menyiapkan notifikasi admin.");
            }

            mysqli_stmt_bind_param(
                $stmtNotifikasi,
                "is",
                $idSewa,
                $isiNotifikasi
            );

            if (!mysqli_stmt_execute($stmtNotifikasi)) {
                throw new Exception("Gagal menyimpan notifikasi admin.");
            }

            mysqli_stmt_close($stmtNotifikasi);

            // ==========================================
            // 16. SIMPAN NOTIFIKASI UNTUK PELANGGAN
            // ==========================================

            $pesanPelanggan =
                "Booking kamu dengan kode " . $kodeBooking .
                " berhasil dibuat. Silakan lakukan pembayaran.";

            $stmtNotifPelanggan = mysqli_prepare($conn, "
                INSERT INTO notifikasi_pelanggan
                (id_user, id_sewa, pesan)
                VALUES (?, ?, ?)
            ");

            if (!$stmtNotifPelanggan) {
                throw new Exception("Gagal menyiapkan notifikasi pelanggan.");
            }

            mysqli_stmt_bind_param(
                $stmtNotifPelanggan,
                "iis",
                $idUser,
                $idSewa,
                $pesanPelanggan
            );

            if (!mysqli_stmt_execute($stmtNotifPelanggan)) {
                throw new Exception("Gagal menyimpan notifikasi pelanggan.");
            }

            mysqli_stmt_close($stmtNotifPelanggan);

            // ==========================================
            // 17. SIMPAN SEMUA TRANSAKSI
            // ==========================================

            mysqli_commit($conn);

            $_SESSION["booking_terakhir"] = $kodeBooking;

            unset($_SESSION["keranjang"]);

            header(
                "Location: hasil_booking.php?kode=" .
                urlencode($kodeBooking)
            );

            exit;

        } catch (Throwable $e) {

            mysqli_rollback($conn);

            $pesan = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Konfirmasi Booking - Kelana</title>

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
            background: #25452e;
        }

        /* CHECKOUT */

        .checkout-container {
            padding: 50px 7%;
            max-width: 1000px;
            margin: auto;
        }

        .checkout-card {
            background: white;
            padding: 25px;
            margin-top: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }

        .checkout-card h2 {
            color: #31583c;
            margin-bottom: 18px;
        }

        .checkout-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .checkout-total {
            background: #f0f5ef;
            padding: 22px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .checkout-total h2 {
            color: #31583c;
        }

        .rincian-harga {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 9px 0;
            font-size: 15px;
        }

        .rincian-diskon {
            color: #278044;
            font-weight: bold;
        }

        .total-akhir {
            border-top: 1px solid #d5e1d5;
            margin-top: 12px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #31583c;
        }

        .info-diskon {
            background: #e7f4e9;
            color: #285c38;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 14px;
        }

        .checkout-btn {
            display: inline-block;
            background: #31583c;
            color: white;
            padding: 13px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            margin-top: 15px;
            font-size: 15px;
        }

        .checkout-btn:hover {
            background: #25452e;
        }

        .checkout-back {
            background: #777;
            margin-right: 8px;
        }

        .checkout-error {
            background: #fff0f0;
            color: #a33;
            padding: 15px;
            border-radius: 8px;
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

            .checkout-container {
                padding: 30px 5%;
            }

            .checkout-card {
                padding: 18px;
            }

            .rincian-harga {
                font-size: 14px;
            }

        }

    </style>

</head>

<body>

<nav class="navbar">

    <a href="../index.php" class="logo-link">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>

    <div class="menu">

        <a href="../index.php">Beranda</a>

        <a href="katalog.php">Katalog</a>

        <a href="keranjang.php">Keranjang</a>

        <a href="tentang.php">Tentang Kami</a>

        <a href="faq.php">FAQ</a>

        <?php if ($isAdmin) { ?>

            <a href="../admin/dashboard.php" class="admin-back-link">
                Kembali ke Panel Admin
            </a>

        <?php } ?>

    </div>

    <a href="profil.php" class="login">Profil Saya</a>

</nav>

<section class="checkout-container">

    <h1>Konfirmasi Booking</h1>

    <p>
        Periksa kembali pesanan sebelum melakukan booking.
    </p>

    <?php if ($pesan !== "") { ?>

        <div class="checkout-error">
            <?php echo htmlspecialchars($pesan); ?>
        </div>

    <?php } ?>

    <div class="checkout-card">

        <h2>Informasi Penyewaan</h2>

        <p>
            <strong>Nama Pelanggan:</strong>

            <?php echo htmlspecialchars($_SESSION["nama"] ?? "Pelanggan"); ?>
        </p>

        <p>
            <strong>Tanggal Mulai:</strong>

            <?php echo $awal->format("d M Y"); ?>
        </p>

        <p>
            <strong>Tanggal Selesai:</strong>

            <?php echo $akhir->format("d M Y"); ?>
        </p>

        <p>
            <strong>Durasi Penyewaan:</strong>

            <?php echo $jumlahHari; ?> hari
        </p>

    </div>

    <div class="checkout-card">

        <h2>Detail Barang</h2>

        <?php foreach ($daftarBarang as $barang) { ?>

            <div class="checkout-item">

                <h3>
                    <?php echo htmlspecialchars($barang["nama_barang"]); ?>
                </h3>

                <p>
                    Harga per hari:

                    Rp <?php echo number_format(
                        $barang["harga_sewa"],
                        0,
                        ",",
                        "."
                    ); ?>
                </p>

                <p>
                    Jumlah:

                    <?php echo $barang["jumlah"]; ?> unit
                </p>

                <p>
                    Subtotal:

                    <strong>
                        Rp <?php echo number_format(
                            $barang["subtotal"],
                            0,
                            ",",
                            "."
                        ); ?>
                    </strong>
                </p>

            </div>

        <?php } ?>

    </div>

    <div class="checkout-total">

        <h2>Rincian Pembayaran</h2>

        <div class="rincian-harga">
            <span>Total sebelum diskon</span>

            <strong>
                Rp <?php echo number_format(
                    $totalHarga,
                    0,
                    ",",
                    "."
                ); ?>
            </strong>
        </div>

        <div class="rincian-harga rincian-diskon">
            <span>Diskon (10%)</span>

            <strong>
                - Rp <?php echo number_format(
                    $diskon,
                    0,
                    ",",
                    "."
                ); ?>
            </strong>
        </div>

        <?php if ($diskon > 0) { ?>

            <div class="info-diskon">
                Selamat! Kamu mendapatkan diskon 10% karena total
                transaksi mencapai Rp200.000.
            </div>

        <?php } else { ?>

            <div class="info-diskon">
                Belanja hingga Rp200.000 untuk mendapatkan diskon 10%.
            </div>

        <?php } ?>

        <div class="rincian-harga total-akhir">
            <span>Total Pembayaran</span>

            <strong>
                Rp <?php echo number_format(
                    $totalSetelahDiskon,
                    0,
                    ",",
                    "."
                ); ?>
            </strong>
        </div>

        <p style="margin-top: 18px;">
            Status pembayaran setelah booking:

            <strong>Belum Bayar</strong>
        </p>

        <p style="margin-top: 8px;">
            Status penyewaan:

            <strong>Dipesan</strong>
        </p>

        <form
            method="POST"
            onsubmit="return confirm('Yakin ingin membuat booking ini?')"
        >

            <a href="keranjang.php" class="checkout-btn checkout-back">
                Kembali ke Keranjang
            </a>

            <button
                type="submit"
                name="buat_booking"
                class="checkout-btn"
            >
                Konfirmasi Booking
            </button>

        </form>

    </div>

</section>

</body>
</html>
