
<?php
include "../config/database.php";

// Ambil ID barang dari URL
$id_barang = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_barang <= 0) {
    die("Barang tidak ditemukan.");
}

// Ambil data barang dan kategorinya
$stmt = mysqli_prepare($conn, "
    SELECT barang.*, kategori.nama_kategori
    FROM barang
    JOIN kategori ON barang.id_kategori = kategori.id_kategori
    WHERE barang.id_barang = ?
");

mysqli_stmt_bind_param($stmt, "i", $id_barang);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$barang = mysqli_fetch_assoc($result);

if (!$barang) {
    die("Barang tidak ditemukan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Barang - Kelana</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .detail-container {
            padding: 60px 8%;
        }

        .detail-card {
            background: white;
            padding: 35px;
            border-radius: 15px;
            max-width: 850px;
            margin: 30px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .detail-card h1 {
            color: #31583c;
            margin: 15px 0;
        }

        .detail-info {
            margin: 15px 0;
            line-height: 1.8;
        }

        .detail-price {
            font-size: 23px;
            font-weight: bold;
            color: #31583c;
            margin: 20px 0;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            background: #31583c;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 6px;
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

<section class="detail-container">

    <div class="detail-card">

        <p>
            Kategori:
            <?php echo htmlspecialchars($barang['nama_kategori']); ?>
        </p>

        <h1>
            <?php echo htmlspecialchars($barang['nama_barang']); ?>
        </h1>

        <div class="detail-info">
            <h3>Deskripsi Barang</h3>
            <p>
                <?php echo nl2br(htmlspecialchars($barang['deskripsi'])); ?>
            </p>
        </div>

        <p class="detail-price">
            Rp <?php echo number_format($barang['harga_sewa'], 0, ',', '.'); ?> / hari
        </p>

        <p>
            Stok tersedia:
            <strong><?php echo $barang['stok']; ?> unit</strong>
        </p>

        <a href="katalog.php" class="back-btn">
            Kembali ke Katalog
        </a>

    </div>

</section>

</body>
</html>
