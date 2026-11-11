
<?php
session_start();
include "../config/database.php";

// Pastikan tanggal penyewaan sudah dipilih
if (empty($_SESSION['tanggal_mulai']) || empty($_SESSION['tanggal_selesai'])) {
    header("Location: pilih_tanggal.php");
    exit;
}

// Proses mengubah isi keranjang
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["aksi_keranjang"])) {

    $id_barang = (int) $_POST["id_barang"];
    $aksi = $_POST["aksi_keranjang"];

    if (isset($_SESSION["keranjang"][$id_barang])) {

        if ($aksi === "kurang") {
            $_SESSION["keranjang"][$id_barang]--;

            // Jika jumlah menjadi 0, barang dihapus
            if ($_SESSION["keranjang"][$id_barang] <= 0) {
                unset($_SESSION["keranjang"][$id_barang]);
            }

        } elseif ($aksi === "tambah") {

            $cek = mysqli_query($conn, "
                SELECT stok FROM barang
                WHERE id_barang = $id_barang
            ");

            $data = mysqli_fetch_assoc($cek);

            if ($data && $_SESSION["keranjang"][$id_barang] < $data["stok"]) {
                $_SESSION["keranjang"][$id_barang]++;
            }

        } elseif ($aksi === "hapus") {
            unset($_SESSION["keranjang"][$id_barang]);
        }
    }

    header("Location: keranjang.php");
    exit;
}

// Ambil lama penyewaan
$awal = new DateTime($_SESSION['tanggal_mulai']);
$akhir = new DateTime($_SESSION['tanggal_selesai']);
$jumlahHari = $awal->diff($akhir)->days + 1;

// Ambil isi keranjang
$keranjang = $_SESSION['keranjang'] ?? [];

$totalHarga = 0;
$daftarBarang = [];

if (!empty($keranjang)) {
    $ids = array_map('intval', array_keys($keranjang));
    $idList = implode(',', $ids);

    $query = mysqli_query($conn, "
        SELECT * FROM barang
        WHERE id_barang IN ($idList)
    ");

    while ($barang = mysqli_fetch_assoc($query)) {
        $id = $barang['id_barang'];
        $jumlah = $keranjang[$id];
        $subtotal = $barang['harga_sewa'] * $jumlah * $jumlahHari;

        $barang['jumlah'] = $jumlah;
        $barang['subtotal'] = $subtotal;

        $daftarBarang[] = $barang;
        $totalHarga += $subtotal;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Kelana</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .cart-container {
            padding: 50px 7%;
        }

        .cart-item {
            background: white;
            padding: 20px;
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }

        .cart-item h3 {
            color: #31583c;
        }

        .cart-total {
            margin-top: 25px;
            padding: 25px;
            background: #f1f4ed;
            border-radius: 10px;
        }

        .cart-total h2 {
            color: #31583c;
        }

        .cart-btn {
            display: inline-block;
            background: #31583c;
            color: white;
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 15px;
            cursor: pointer;
        }

        .empty-cart {
            margin-top: 30px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            text-align: center;
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
        <a href="#">Tentang Kami</a>
        <a href="#">FAQ</a>
    </div>
    <a href="../auth/login.php" class="login">Masuk</a>
</nav>

<section class="cart-container">

    <h1>Keranjang Saya</h1>
    <p>Periksa perlengkapan camping yang ingin kamu sewa.</p>

    <p>
        <strong>Periode:</strong>
        <?php echo date('d M Y', strtotime($_SESSION['tanggal_mulai'])); ?>
        -
        <?php echo date('d M Y', strtotime($_SESSION['tanggal_selesai'])); ?>
        (<?php echo $jumlahHari; ?> hari)
    </p>

    <?php if (empty($daftarBarang)) { ?>

        <div class="empty-cart">
            <h3>Keranjang masih kosong</h3>
            <p>Yuk, pilih perlengkapan camping yang kamu butuhkan.</p>
            <a href="katalog.php" class="cart-btn">Lihat Katalog</a>
        </div>

    <?php } else { ?>

        <?php foreach ($daftarBarang as $barang) { ?>

            <div class="cart-item">

                <h3>
                    <?php echo htmlspecialchars($barang['nama_barang']); ?>
                </h3>

                <p>Harga per hari:
                    Rp <?php echo number_format($barang['harga_sewa'], 0, ',', '.'); ?>
                </p>

                <p>Jumlah:</p>

<div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">

    <form method="POST">
        <input type="hidden" name="id_barang"
               value="<?php echo $barang['id_barang']; ?>">

        <button type="submit"
                name="aksi_keranjang"
                value="kurang"
                class="cart-btn"
                style="margin-top: 0;">
            −
        </button>
    </form>

    <strong><?php echo $barang['jumlah']; ?> unit</strong>

    <form method="POST">
        <input type="hidden" name="id_barang"
               value="<?php echo $barang['id_barang']; ?>">

        <button type="submit"
                name="aksi_keranjang"
                value="tambah"
                class="cart-btn"
                style="margin-top: 0;">
            +
        </button>
    </form>

    <form method="POST">
        <input type="hidden" name="id_barang"
               value="<?php echo $barang['id_barang']; ?>">

        <button type="submit"
                name="aksi_keranjang"
                value="hapus"
                class="cart-btn"
                style="margin-top: 0; background: #a33;">
            Hapus
        </button>
    </form>

</div>

                <p>Durasi: <?php echo $jumlahHari; ?> hari</p>

                <strong>
                    Subtotal: Rp
                    <?php echo number_format($barang['subtotal'], 0, ',', '.'); ?>
                </strong>

            </div>

        <?php } ?>

        <div class="cart-total">
            <h2>
                Total: Rp <?php echo number_format($totalHarga, 0, ',', '.'); ?>
            </h2>

            <p>Belum termasuk denda keterlambatan jika ada.</p>

            <a href="katalog.php" class="cart-btn">
                + Tambah Barang
            </a>
        </div>

    <?php } ?>

</section>

<script>
// Simpan posisi scroll sebelum form dikirim
document.querySelectorAll('.cart-item form').forEach(form => {
    form.addEventListener('submit', function () {
        sessionStorage.setItem('posisiScroll', window.scrollY);
    });
});

// Kembalikan posisi scroll setelah halaman dimuat
window.addEventListener('load', function () {
    const posisi = sessionStorage.getItem('posisiScroll');

    if (posisi !== null) {
        window.scrollTo(0, parseInt(posisi, 10));
        sessionStorage.removeItem('posisiScroll');
    }
});
</script>

</body>
</html>
