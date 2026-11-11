
<?php
session_start();
include "../config/database.php";

// Pastikan pelanggan sudah login
if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php?lanjut=booking");
    exit;
}

// Pastikan tanggal penyewaan tersedia
if (empty($_SESSION["tanggal_mulai"]) || empty($_SESSION["tanggal_selesai"])) {
    header("Location: pilih_tanggal.php");
    exit;
}

// Pastikan keranjang tidak kosong
$keranjang = $_SESSION["keranjang"] ?? [];

if (empty($keranjang)) {
    header("Location: keranjang.php");
    exit;
}

// Ambil tanggal penyewaan
$awal = new DateTime($_SESSION["tanggal_mulai"]);
$akhir = new DateTime($_SESSION["tanggal_selesai"]);

$jumlahHari = $awal->diff($akhir)->days + 1;
$tanggalMulai = $awal->format("Y-m-d");
$tanggalSelesai = $akhir->format("Y-m-d");

// Batas maksimal penyewaan 14 hari
if ($jumlahHari > 14 || $akhir < $awal) {
    die("Periode penyewaan tidak valid. Maksimal penyewaan adalah 14 hari.");
}

// Ambil pengaturan jeda hari
$pengaturan = mysqli_query($conn, "
    SELECT jeda_hari FROM pengaturan LIMIT 1
");

$dataPengaturan = $pengaturan ? mysqli_fetch_assoc($pengaturan) : null;
$jedaHari = (int) ($dataPengaturan["jeda_hari"] ?? 0);

// Ambil data barang di keranjang
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

$pesan = "";

// Proses konfirmasi booking
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["buat_booking"])) {

    if (empty($daftarBarang)) {
        $pesan = "Keranjang kamu kosong.";
    } else {

        try {
            mysqli_begin_transaction($conn);

            $totalBooking = 0;
            $barangBooking = [];

            // Periksa stok dan ketersediaan setiap barang
            foreach ($daftarBarang as $item) {

                $idBarang = (int) $item["id_barang"];
                $jumlahPesan = (int) $item["jumlah"];

                // Kunci data barang selama proses booking
                $stmtBarang = mysqli_prepare($conn, "
                    SELECT id_barang, nama_barang, stok, harga_sewa
                    FROM barang
                    WHERE id_barang = ?
                    FOR UPDATE
                ");

                mysqli_stmt_bind_param($stmtBarang, "i", $idBarang);
                mysqli_stmt_execute($stmtBarang);

                $hasilBarang = mysqli_stmt_get_result($stmtBarang);
                $dataBarang = mysqli_fetch_assoc($hasilBarang);

                if (!$dataBarang) {
                    throw new Exception("Barang tidak ditemukan.");
                }

                // Hitung jumlah barang yang sudah dipesan
                // pada periode yang berdekatan, termasuk jeda hari
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

                $stokTerpakai = (int) $dataStok["stok_terpakai"];
                $stokTersedia = (int) $dataBarang["stok"] - $stokTerpakai;

                if ($jumlahPesan > $stokTersedia) {
                    throw new Exception(
                        "Stok " . $dataBarang["nama_barang"] .
                        " tidak mencukupi untuk tanggal yang dipilih. " .
                        "Stok tersedia: " . max(0, $stokTersedia) . " unit."
                    );
                }

                // Hitung harga berdasarkan data terbaru dari database
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

            // Buat kode booking
            $kodeBooking = "KLN" . date("ymd") . strtoupper(bin2hex(random_bytes(3)));

            // Simpan data utama penyewaan
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
                $totalBooking
            );

            if (!mysqli_stmt_execute($stmtSewa)) {
                throw new Exception("Gagal menyimpan booking.");
            }

            $idSewa = mysqli_insert_id($conn);

            // Simpan detail barang yang disewa
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

            // Semua data berhasil disimpan
            mysqli_commit($conn);

            // Simpan kode booking untuk halaman hasil
            $_SESSION["booking_terakhir"] = $kodeBooking;

            // Kosongkan keranjang setelah booking berhasil
            unset($_SESSION["keranjang"]);

            header("Location: hasil_booking.php?kode=" . urlencode($kodeBooking));
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

<section class="checkout-container">

    <h1>Konfirmasi Booking</h1>
    <p>Periksa kembali pesanan sebelum melakukan booking.</p>

    <?php if ($pesan !== "") { ?>
        <div class="checkout-error">
            <?php echo htmlspecialchars($pesan); ?>
        </div>
    <?php } ?>

    <div class="checkout-card">

        <h2>Informasi Penyewaan</h2>

        <p>
            <strong>Nama Pelanggan:</strong>
            <?php echo htmlspecialchars($_SESSION["nama"]); ?>
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
                    Rp <?php echo number_format($barang["harga_sewa"], 0, ",", "."); ?>
                </p>

                <p>
                    Jumlah:
                    <?php echo $barang["jumlah"]; ?> unit
                </p>

                <p>
                    Subtotal:
                    <strong>
                        Rp <?php echo number_format($barang["subtotal"], 0, ",", "."); ?>
                    </strong>
                </p>

            </div>

        <?php } ?>

    </div>

    <div class="checkout-total">

        <h2>
            Total Pembayaran:
            Rp <?php echo number_format($totalHarga, 0, ",", "."); ?>
        </h2>

        <p>
            Status pembayaran setelah booking:
            <strong>Belum Bayar</strong>
        </p>

        <p>
            Status penyewaan:
            <strong>Dipesan</strong>
        </p>

        <form method="POST"
              onsubmit="return confirm('Yakin ingin membuat booking ini?')">

            <a href="keranjang.php"
               class="checkout-btn checkout-back">
                Kembali ke Keranjang
            </a>

            <button type="submit"
                    name="buat_booking"
                    class="checkout-btn">
                Konfirmasi Booking
            </button>

        </form>

    </div>

</section>

</body>
</html>
