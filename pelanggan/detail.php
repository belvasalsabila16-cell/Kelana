
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
// 2. INISIALISASI KERANJANG
// ==========================================

if (!isset($_SESSION["keranjang"])) {
    $_SESSION["keranjang"] = [];
}

// ==========================================
// 3. AMBIL ID BARANG
// ==========================================

$id_barang = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id_barang <= 0) {
    die("Barang tidak ditemukan.");
}

// ==========================================
// 4. AMBIL DATA BARANG DAN KATEGORI
// ==========================================

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

// ==========================================
// 5. PROSES TAMBAH KE KERANJANG
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["tambah_keranjang"])
) {
    $jumlah = (int) ($_POST["jumlah"] ?? 0);
    $stok = (int) $barang["stok"];

    if ($stok <= 0) {
        $_SESSION["pesan_keranjang"] = [
            "tipe" => "error",
            "pesan" => "Maaf, stok barang sedang habis."
        ];

    } elseif ($jumlah < 1 || $jumlah > $stok) {
        $_SESSION["pesan_keranjang"] = [
            "tipe" => "error",
            "pesan" => "Jumlah barang tidak valid. Maksimal stok tersedia adalah " . $stok . " unit."
        ];

    } else {
        $_SESSION["keranjang"][$id_barang] = $jumlah;

        $_SESSION["pesan_keranjang"] = [
            "tipe" => "success",
            "pesan" => htmlspecialchars($barang["nama_barang"]) . " berhasil ditambahkan ke keranjang!"
        ];
    }

    header("Location: detail.php?id=" . $id_barang);
    exit;
}

// ==========================================
// 6. SIAPKAN GAMBAR BARANG
// ==========================================

$namaGambar = basename(
    str_replace("\\", "/", (string) ($barang["gambar"] ?? ""))
);

$lokasiGambar = "../assets/images/" . $namaGambar;

$fileGambarAda = (
    $namaGambar !== "" &&
    is_file(__DIR__ . "/../assets/images/" . $namaGambar)
);

// ==========================================
// 7. AMBIL PESAN NOTIFIKASI
// ==========================================

$pesanKeranjang = $_SESSION["pesan_keranjang"] ?? null;
unset($_SESSION["pesan_keranjang"]);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Barang - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* ======================================
           NAVBAR
        ====================================== */

        .navbar .menu a.active {
            color: #31583c;
            font-weight: 700;
            position: relative;
        }

        .navbar .menu a.active::after {
            width: 100%;
        }

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
            background: #24442d;
        }

        /* ======================================
           MENU AKUN
        ====================================== */

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

        /* ======================================
           DETAIL BARANG
        ====================================== */

        .detail-container {
            padding: 50px 8%;
        }

        .detail-card {
            background: white;
            padding: 35px;
            border-radius: 15px;
            max-width: 1000px;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .detail-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            align-items: start;
        }

        .detail-image {
            width: 100%;
            height: 350px;
            object-fit: cover;
            border-radius: 12px;
            background: #edf2ec;
        }

        .image-placeholder {
            width: 100%;
            height: 350px;
            background: #edf2ec;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #78847b;
        }

        .detail-category {
            color: #78847b;
            font-size: 14px;
        }

        .detail-info h1 {
            color: #31583c;
            margin: 12px 0 18px;
        }

        .description-title {
            color: #31583c;
            margin-bottom: 8px;
            font-size: 17px;
        }

        .description-text {
            color: #626e65;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .detail-price {
            font-size: 23px;
            font-weight: bold;
            color: #31583c;
            margin: 20px 0 12px;
        }

        .stock-info {
            color: #46564a;
            margin-bottom: 20px;
        }

        /* ======================================
           FORM KERANJANG
        ====================================== */

        .quantity-label {
            display: block;
            color: #46564a;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .quantity-input {
            width: 100px;
            padding: 11px;
            border: 1px solid #ccd5cc;
            border-radius: 6px;
            font-size: 15px;
        }

        .cart-button {
            display: block;
            width: 100%;
            margin-top: 18px;
            padding: 13px;
            background: #31583c;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .cart-button:hover {
            background: #24442d;
        }

        .cart-button:disabled {
            background: #999;
            cursor: not-allowed;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            background: #edf2ec;
            color: #31583c;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-btn:hover {
            background: #dfe9dd;
        }

        /* ======================================
           POP-UP NOTIFIKASI
        ====================================== */

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
        }

        .popup-box {
            width: 100%;
            max-width: 380px;
            background: white;
            padding: 30px;
            border-radius: 13px;
            text-align: center;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
        }

        .popup-icon {
            font-size: 38px;
            margin-bottom: 12px;
        }

        .popup-box h3 {
            color: #31583c;
            margin-bottom: 10px;
        }

        .popup-box p {
            color: #626e65;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .popup-button {
            display: inline-block;
            padding: 11px 20px;
            background: #31583c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }

        .popup-button:hover {
            background: #24442d;
        }

        @media (max-width: 700px) {

            .navbar {
                flex-wrap: wrap;
                gap: 15px;
            }

            .menu {
                flex-wrap: wrap;
            }

            .detail-container {
                padding: 30px 5%;
            }

            .detail-card {
                padding: 22px;
            }

            .detail-content {
                grid-template-columns: 1fr;
            }

            .detail-image,
            .image-placeholder {
                height: 260px;
            }

        }

    </style>

</head>

<body>

<!-- ======================================
     NAVBAR
====================================== -->

<nav class="navbar">

    <h2>KELANA.</h2>

    <div class="menu">

        <a href="../index.php">Beranda</a>

        <a href="katalog.php" class="active">Katalog</a>

        <a href="keranjang.php">
            Keranjang (<?= count($_SESSION["keranjang"]); ?>)
        </a>

        <a href="tentang.php">Tentang Kami</a>

        <a href="faq.php">FAQ</a>

        <?php if ($isAdmin) { ?>

            <a href="../admin/dashboard.php" class="admin-back-link">
                Kembali ke Panel Admin
            </a>

        <?php } ?>

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

                <span>Akun</span>
                <span>⌄</span>

            </summary>

            <div class="dropdown-content">

                <a href="profil.php">
                    <span>Profil Saya</span>
                    <span>›</span>
                </a>

                <div class="dropdown-divider"></div>

                <a href="riwayat.php">
                    <span>Riwayat Penyewaan</span>
                    <span>›</span>
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

        <a href="../auth/login.php" class="login">
            Masuk
        </a>

    <?php } ?>

</nav>

<!-- ======================================
     DETAIL BARANG
====================================== -->

<section class="detail-container">

    <div class="detail-card">

        <div class="detail-content">

            <!-- FOTO BARANG -->

            <div>

                <?php if ($fileGambarAda): ?>

                    <img
                        src="<?= htmlspecialchars($lokasiGambar); ?>"
                        alt="<?= htmlspecialchars($barang["nama_barang"]); ?>"
                        class="detail-image"
                    >

                <?php else: ?>

                    <div class="image-placeholder">
                        Foto belum tersedia
                    </div>

                <?php endif; ?>

            </div>

            <!-- INFORMASI BARANG -->

            <div class="detail-info">

                <p class="detail-category">
                    Kategori:
                    <?= htmlspecialchars($barang["nama_kategori"]); ?>
                </p>

                <h1>
                    <?= htmlspecialchars($barang["nama_barang"]); ?>
                </h1>

                <h3 class="description-title">Deskripsi Barang</h3>

                <p class="description-text">
                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $barang["deskripsi"] ?? ""
                        )
                    );
                    ?>
                </p>

                <p class="detail-price">
                    Rp <?= number_format(
                        $barang["harga_sewa"],
                        0,
                        ",",
                        "."
                    ); ?> / hari
                </p>

                <p class="stock-info">
                    Stok tersedia:
                    <strong><?= (int) $barang["stok"]; ?> unit</strong>
                </p>

                <!-- FORM TAMBAH KERANJANG -->

                <form method="POST">

                    <label class="quantity-label" for="jumlah">
                        Jumlah barang:
                    </label>

                    <input
                        type="number"
                        id="jumlah"
                        name="jumlah"
                        class="quantity-input"
                        value="<?= (int) ($_SESSION["keranjang"][$id_barang] ?? 1); ?>"
                        min="1"
                        max="<?= (int) $barang["stok"]; ?>"
                        required
                        <?= (int) $barang["stok"] <= 0 ? "disabled" : ""; ?>
                    >

                    <button
                        type="submit"
                        name="tambah_keranjang"
                        class="cart-button"
                        <?= (int) $barang["stok"] <= 0 ? "disabled" : ""; ?>
                    >
                        + Tambah ke Keranjang
                    </button>

                </form>

                <a href="katalog.php" class="back-btn">
                    ← Kembali ke Katalog
                </a>

            </div>

        </div>

    </div>

</section>

<!-- ======================================
     POP-UP NOTIFIKASI
====================================== -->

<?php if ($pesanKeranjang): ?>

    <div class="popup-overlay" id="popupNotifikasi">

        <div class="popup-box">

            <div class="popup-icon">
                <?= $pesanKeranjang["tipe"] === "success" ? "✓" : "!"; ?>
            </div>

            <h3>
                <?= $pesanKeranjang["tipe"] === "success"
                    ? "Berhasil!"
                    : "Perhatian"; ?>
            </h3>

            <p>
                <?= $pesanKeranjang["pesan"]; ?>
            </p>

            <?php if ($pesanKeranjang["tipe"] === "success"): ?>

                <a href="keranjang.php" class="popup-button">
                    Lihat Keranjang
                </a>

                <button
                    type="button"
                    class="popup-button"
                    onclick="document.getElementById('popupNotifikasi').remove()"
                >
                    Lanjut Belanja
                </button>

            <?php else: ?>

                <button
                    type="button"
                    class="popup-button"
                    onclick="document.getElementById('popupNotifikasi').remove()"
                >
                    Mengerti
                </button>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

</body>
</html>
