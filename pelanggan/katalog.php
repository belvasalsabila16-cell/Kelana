
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
        $_SESSION["keranjang"][$id_barang] = $jumlah;
    }

    header("Location: katalog.php");
    exit;
}

// Pastikan tanggal penyewaan sudah dipilih
if (empty($_SESSION['tanggal_mulai']) || empty($_SESSION['tanggal_selesai'])) {
    header("Location: pilih_tanggal.php");
    exit;
}

// Ambil data barang
$query = mysqli_query($conn, "
    SELECT barang.*, kategori.nama_kategori
    FROM barang
    JOIN kategori
    ON barang.id_kategori = kategori.id_kategori
    ORDER BY barang.id_barang ASC
");

// Menghitung informasi penyewaan
$tanggalMulai = new DateTime($_SESSION['tanggal_mulai']);
$tanggalSelesai = new DateTime($_SESSION['tanggal_selesai']);

// Durasi dihitung termasuk tanggal mulai dan selesai
$durasiSewa = $tanggalMulai->diff($tanggalSelesai)->days + 1;

// Mengambil batas pengembalian dari session
if (!empty($_SESSION['batas_pengembalian'])) {
    $batasPengembalian = new DateTime($_SESSION['batas_pengembalian']);
} else {
    $batasPengembalian = clone $tanggalSelesai;
    $batasPengembalian->modify('+2 days');
}
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

        .rental-period {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            background: #f0f5ef;
            padding: 20px 7%;
            margin-top: 20px;
        }

        .rental-info {
            color: #31583c;
            margin-top: 10px;
            line-height: 1.8;
        }

        .rental-info p {
            margin: 3px 0;
        }

        /* Menu Akun */
        .account-dropdown {
            position: relative;
            margin-left: 10px;
        }

        .account-dropdown summary {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            list-style: none;
            color: #31583c;
            font-weight: 600;
        }

        .account-dropdown summary::-webkit-details-marker {
            display: none;
        }

        .profile-circle {
            width: 35px;
            height: 35px;
            border: 1.5px solid #31583c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-circle svg {
            width: 21px;
            height: 21px;
        }

        .account-label {
            font-size: 15px;
        }

        .arrow {
            font-size: 17px;
        }

        .dropdown-content {
            position: absolute;
            right: 0;
            top: 45px;
            width: 190px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.13);
            padding: 8px 0;
            z-index: 1000;
        }

        .dropdown-content a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 15px;
            text-decoration: none;
            color: #31583c;
            font-size: 14px;
        }

        .dropdown-content a:hover {
            background: #f0f5ef;
        }

        .dropdown-divider {
            height: 1px;
            background: #eee;
            margin: 5px 0;
        }

        .dropdown-content .logout-link {
            color: #a33;
        }

        @media (max-width: 700px) {
            .navbar {
                flex-wrap: wrap;
                gap: 15px;
            }

            .menu {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

<!-- Informasi Periode Penyewaan -->
<div class="rental-period">

    <div>
        <small>Periode Penyewaan</small>

        <h3>
            <?php echo $tanggalMulai->format('d M Y'); ?>
            -
            <?php echo $tanggalSelesai->format('d M Y'); ?>
        </h3>

        <div class="rental-info">

            <p>
                <strong>Durasi:</strong>
                <?php echo $durasiSewa; ?> hari
            </p>

            <p>
                <strong>Batas Pengembalian:</strong>
                <?php echo $batasPengembalian->format('d M Y'); ?>
            </p>

            <p>
                Pengembalian sampai tanggal tersebut tidak dikenakan denda.
            </p>

        </div>
    </div>

    <a href="pilih_tanggal.php" class="detail-btn">
        ← Ubah Tanggal
    </a>

</div>

<!-- Navbar -->
<nav class="navbar">

    <h2>KELANA.</h2>

    <div class="menu">

        <a href="../index.php">Beranda</a>

        <a href="katalog.php">Katalog</a>

        <a href="keranjang.php">
            Keranjang (<?php echo count($_SESSION['keranjang'] ?? []); ?>)
        </a>

        <a href="tentang.php">Tentang Kami</a>

        <a href="faq.php">FAQ</a>

    </div>

    <?php if (isset($_SESSION["id_user"])) { ?>

        <details class="account-dropdown">

            <summary>

                <span class="profile-circle">
                    <svg viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="8" r="3.5"/>
                        <path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>
                    </svg>
                </span>

                <span class="account-label">Akun</span>

                <span class="arrow">⌄</span>

            </summary>

            <div class="dropdown-content">

                <a href="profil.php">
                    <span>Profil Saya</span>
                    <span class="dropdown-arrow">›</span>
                </a>

                <div class="dropdown-divider"></div>

                <a href="riwayat.php">
                    <span>Riwayat Penyewaan</span>
                    <span class="dropdown-arrow">›</span>
                </a>

                <div class="dropdown-divider"></div>

                <a href="../auth/logout.php"
                   class="logout-link"
                   onclick="return confirm('Yakin ingin keluar dari akun?')">
                    Keluar
                </a>

            </div>

        </details>

    <?php } else { ?>

        <a href="../auth/login.php" class="login">Masuk</a>

    <?php } ?>

</nav>

<!-- Katalog Barang -->
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
                    Rp <?php echo number_format(
                        $barang['harga_sewa'], 0, ',', '.'
                    ); ?> / hari
                </p>

                <p class="stock">
                    Stok tersedia: <?php echo $barang['stok']; ?>
                </p>

                <a href="detail.php?id=<?php echo $barang['id_barang']; ?>"
                   class="detail-btn">
                    Lihat Detail
                </a>

                <form method="POST" style="margin-top: 15px;">

                    <input type="hidden"
                           name="id_barang"
                           value="<?php echo $barang['id_barang']; ?>">

                    <label>Jumlah:</label>

                    <input type="number"
                           name="jumlah"
                           value="<?php echo $_SESSION['keranjang'][$barang['id_barang']] ?? 1; ?>"
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

<script>
// Simpan posisi scroll sebelum form dikirim
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        sessionStorage.setItem(
            'posisiScrollKatalog',
            window.scrollY
        );
    });
});

// Kembalikan posisi scroll setelah halaman dimuat
window.addEventListener('load', function() {
    const posisi = sessionStorage.getItem('posisiScrollKatalog');

    if (posisi !== null) {
        window.scrollTo(0, parseInt(posisi));
        sessionStorage.removeItem('posisiScrollKatalog');
    }
});
</script>

</body>
</html>
