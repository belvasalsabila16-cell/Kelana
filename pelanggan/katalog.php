
<?php
session_start();
include "../config/database.php";
// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// Proses tambah barang ke keranjang
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tambah_keranjang"])) {

    $id_barang = (int) $_POST["id_barang"];
    $jumlah = (int) $_POST["jumlah"];

    $cekBarang = mysqli_query($conn, "
        SELECT * FROM barang WHERE id_barang = $id_barang
    ");

    $barang = mysqli_fetch_assoc($cekBarang);

    if ($barang && $jumlah > 0 && $jumlah <= $barang["stok"]) {

        if (isset($_SESSION["keranjang"][$id_barang])) {
            $jumlahBaru = $_SESSION["keranjang"][$id_barang] + $jumlah;

            if ($jumlahBaru <= $barang["stok"]) {
                $_SESSION["keranjang"][$id_barang] = $jumlahBaru;
            }
        } else {
            $_SESSION["keranjang"][$id_barang] = $jumlah;
        }
    }

    header("Location: katalog.php");
    exit;
}

if (empty($_SESSION['tanggal_mulai']) || empty($_SESSION['tanggal_selesai'])) {
    header("Location: pilih_tanggal.php");
    exit;
}

$query = mysqli_query($conn, "
    SELECT barang.*, kategori.nama_kategori
    FROM barang
    JOIN kategori
    ON barang.id_kategori = kategori.id_kategori
    ORDER BY barang.id_barang ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog - Kelana</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .catalog {
            padding: 50px 7%;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .product-card h3 {
            margin: 12px 0;
            color: #31583c;
        }

        .category {
            color: #777;
            font-size: 14px;
        }

        .price {
            font-weight: bold;
            margin: 12px 0;
        }

        .stock {
            font-size: 14px;
            margin-bottom: 15px;
        }

        .detail-btn {
            display: inline-block;
            background: #31583c;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="rental-period">
    <div>
        <small>Periode Penyewaan</small>
        <h3>
            <?php echo date('d M Y', strtotime($_SESSION['tanggal_mulai'])); ?>
            -
            <?php echo date('d M Y', strtotime($_SESSION['tanggal_selesai'])); ?>
        </h3>
    </div>

    <a href="pilih_tanggal.php" class="detail-btn">
        ← Ubah Tanggal
    </a>
</div>

<nav class="navbar">
    <h2>KELANA.</h2>
    <div class="menu">
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
        <a href="keranjang.php">
            Keranjang (<?php echo count($_SESSION['keranjang'] ?? []); ?>)
        </a>
        <a href="#">Tentang Kami</a>
        <a href="#">FAQ</a>
    </div>
    <a href="../auth/login.php" class="login">Masuk</a>
</nav>

<section class="catalog">
    <h1>Katalog Perlengkapan Camping</h1>
    <p>Temukan perlengkapan terbaik untuk petualanganmu.</p>

    <div class="product-grid">

        <?php while ($barang = mysqli_fetch_assoc($query)) { ?>

            <div class="product-card">

                <p class="category">
                    <?php echo htmlspecialchars($barang['nama_kategori']); ?>
                </p>

                <h3>
                    <?php echo htmlspecialchars($barang['nama_barang']); ?>
                </h3>

                <p>
                    <?php echo htmlspecialchars($barang['deskripsi']); ?>
                </p>

                <p class="price">
                    Rp <?php echo number_format($barang['harga_sewa'], 0, ',', '.'); ?> / hari
                </p>

                <p class="stock">
                    Stok tersedia: <?php echo $barang['stok']; ?>
                </p>

                <a href="detail.php?id=<?php echo $barang['id_barang']; ?>"
   class="detail-btn">
    Lihat Detail
</a>

<form method="POST" style="margin-top: 15px;">

    <input type="hidden" name="id_barang"
           value="<?php echo $barang['id_barang']; ?>">

    <label>Jumlah:</label>

    <input type="number"
           name="jumlah"
           value="1"
           min="1"
           max="<?php echo $barang['stok']; ?>"
           required
           style="width: 60px; padding: 8px;">

    <button type="submit"
            name="tambah_keranjang"
            class="detail-btn"
            style="border: none; cursor: pointer; margin-top: 10px;">
        + Tambah ke Keranjang
    </button>

</form>

            </div>

        <?php } ?>

    </div>
</section>

</body>
</html>
