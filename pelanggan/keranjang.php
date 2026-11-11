
<?php
session_start();
include "../config/database.php";

// Pastikan tanggal penyewaan sudah dipilih
if (empty($_SESSION['tanggal_mulai']) || empty($_SESSION['tanggal_selesai'])) {
    header("Location: pilih_tanggal.php");
    exit;
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// Proses mengubah isi keranjang
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["aksi_keranjang"])) {

    $id_barang = (int) $_POST["id_barang"];
    $aksi = $_POST["aksi_keranjang"];

    if (isset($_SESSION["keranjang"][$id_barang])) {

        if ($aksi === "kurang") {

            $_SESSION["keranjang"][$id_barang]--;

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

// Menghitung batas pengembalian
if (!empty($_SESSION['batas_pengembalian'])) {
    $batasPengembalian = new DateTime($_SESSION['batas_pengembalian']);
} else {
    $batasPengembalian = clone $akhir;
    $batasPengembalian->modify('+2 days');
}

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
        $jumlah = (int) $keranjang[$id];

        if ($jumlah <= 0) {
            continue;
        }

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
            font-size: 14px;
        }

        .cart-btn:hover {
            opacity: 0.9;
        }

        .empty-cart {
            margin-top: 30px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            text-align: center;
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

            .cart-container {
                padding: 30px 5%;
            }
        }
    </style>
</head>

<body>

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

<!-- Isi Keranjang -->
<section class="cart-container">

    <h1>Keranjang Saya</h1>

    <p>Periksa perlengkapan camping yang ingin kamu sewa.</p>

    <div style="background:#f0f5ef; padding:20px; border-radius:10px; margin-top:20px;">

        <p>
            <strong>Tanggal Mulai:</strong>
            <?php echo $awal->format('d M Y'); ?>
        </p>

        <p>
            <strong>Tanggal Selesai:</strong>
            <?php echo $akhir->format('d M Y'); ?>
        </p>

        <p>
            <strong>Durasi Penyewaan:</strong>
            <?php echo $jumlahHari; ?> hari
        </p>

        <p>
            <strong>Batas Pengembalian:</strong>
            <?php echo $batasPengembalian->format('d M Y'); ?>
        </p>

        <p style="color:#31583c;">
            Pengembalian sampai tanggal batas tersebut tidak dikenakan denda.
        </p>

    </div>

    <?php if (empty($daftarBarang)) { ?>

        <div class="empty-cart">

            <h3>Keranjang masih kosong</h3>

            <p>Yuk, pilih perlengkapan camping yang kamu butuhkan.</p>

            <a href="katalog.php" class="cart-btn">
                Lihat Katalog
            </a>

        </div>

    <?php } else { ?>

        <?php foreach ($daftarBarang as $barang) { ?>

            <div class="cart-item">

                <h3>
                    <?php echo htmlspecialchars($barang['nama_barang']); ?>
                </h3>

                <p>
                    Harga per hari:
                    Rp <?php echo number_format(
                        $barang['harga_sewa'], 0, ',', '.'
                    ); ?>
                </p>

                <p>Jumlah:</p>

                <div style="display:flex; align-items:center; gap:10px; margin:10px 0; flex-wrap:wrap;">

                    <form method="POST">

                        <input type="hidden" name="id_barang"
                               value="<?php echo $barang['id_barang']; ?>">

                        <button type="submit"
                                name="aksi_keranjang"
                                value="kurang"
                                class="cart-btn"
                                style="margin-top:0;">
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
                                style="margin-top:0;">
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
                                style="margin-top:0; background:#a33;">
                            Hapus
                        </button>

                    </form>

                </div>

                <p>Durasi: <?php echo $jumlahHari; ?> hari</p>

                <strong>
                    Subtotal: Rp
                    <?php echo number_format(
                        $barang['subtotal'], 0, ',', '.'
                    ); ?>
                </strong>

            </div>

        <?php } ?>

        <div class="cart-total">

            <h2>
                Total: Rp <?php echo number_format(
                    $totalHarga, 0, ',', '.'
                ); ?>
            </h2>

            <p>Belum termasuk denda keterlambatan jika ada.</p>

            <a href="katalog.php" class="cart-btn">
                + Tambah Barang
            </a>

            <?php if (isset($_SESSION["id_user"])) { ?>

                <a href="checkout.php"
                   class="cart-btn"
                   style="background:#d69b45; margin-left:10px;">
                    Booking Sekarang
                </a>

            <?php } else { ?>

                <a href="../auth/login.php?lanjut=booking"
                   class="cart-btn"
                   style="background:#d69b45; margin-left:10px;">
                    Booking Sekarang
                </a>

            <?php } ?>

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
