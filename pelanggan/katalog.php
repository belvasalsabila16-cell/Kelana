
<?php

session_start();

include_once "../config/database.php";

// ==========================================
// FUNGSI BANTU
// ==========================================

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, "UTF-8");
}

function formatKategori($nama) {
    return strtolower(trim((string) $nama));
}

// ==========================================
// 1. CEK ROLE PENGGUNA DAN NOTIFIKASI
// ==========================================

$isAdmin = false;
$isPelanggan = false;
$jumlahNotifikasi = 0;

if (isset($_SESSION["id_user"])) {
    $idUser = (int) $_SESSION["id_user"];

    $cekRole = mysqli_query(
        $conn,
        "SELECT role FROM user WHERE id_user = $idUser"
    );

    $dataRole = mysqli_fetch_assoc($cekRole);

    if ($dataRole) {
        if ($dataRole["role"] === "admin") {
            $isAdmin = true;
        }

        if ($dataRole["role"] === "pelanggan") {
            $isPelanggan = true;

            $queryNotifikasi = mysqli_query($conn, "
                SELECT COUNT(*) AS jumlah
                FROM notifikasi_pelanggan
                WHERE id_user = $idUser
                AND dibaca = 0
            ");

            if ($queryNotifikasi) {
                $dataNotifikasi = mysqli_fetch_assoc($queryNotifikasi);
                $jumlahNotifikasi = (int) $dataNotifikasi["jumlah"];
            }
        }
    }
}

$halamanAktif = basename($_SERVER["PHP_SELF"]);

// ==========================================
// 2. INISIALISASI KERANJANG
// ==========================================

if (!isset($_SESSION["keranjang"])) {
    $_SESSION["keranjang"] = [];
}

// ==========================================
// 3. CEK TANGGAL PENYEWAAN
// ==========================================

// Pengunjung boleh melihat katalog tanpa memilih tanggal.
// Informasi periode hanya ditampilkan jika tanggal sudah dipilih.

$adaPeriodeSewa = (
    !empty($_SESSION["tanggal_mulai"]) &&
    !empty($_SESSION["tanggal_selesai"])
);

// ==========================================
// 4. AMBIL DATA KATEGORI BARANG AKTIF
// ==========================================

$queryKategori = mysqli_query($conn, "
    SELECT MIN(k.id_kategori) AS id_kategori, k.nama_kategori
    FROM kategori k
    INNER JOIN barang b
        ON k.id_kategori = b.id_kategori
        AND b.status_barang = 'aktif'
    GROUP BY k.nama_kategori
    ORDER BY k.nama_kategori ASC
");

// ==========================================
// 5. AMBIL DATA BARANG AKTIF
// ==========================================

$query = mysqli_query($conn, "
    SELECT barang.*, kategori.nama_kategori
    FROM barang
    INNER JOIN kategori
        ON barang.id_kategori = kategori.id_kategori
    WHERE barang.status_barang = 'aktif'
    ORDER BY barang.id_barang ASC
");

// ==========================================
// 6. HITUNG INFORMASI PENYEWAAN
// ==========================================

$tanggalMulai = null;
$tanggalSelesai = null;
$durasiSewa = 0;
$batasPengembalian = null;

if ($adaPeriodeSewa) {
    $tanggalMulai = new DateTime($_SESSION["tanggal_mulai"]);
    $tanggalSelesai = new DateTime($_SESSION["tanggal_selesai"]);

    $durasiSewa = $tanggalMulai->diff($tanggalSelesai)->days + 1;

    if (!empty($_SESSION["batas_pengembalian"])) {
        $batasPengembalian = new DateTime($_SESSION["batas_pengembalian"]);
    } else {
        $batasPengembalian = clone $tanggalSelesai;
        $batasPengembalian->modify("+2 days");
    }
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
        * {
            box-sizing: border-box;
        }

        body {
            background-color: transparent;
            color: #293b30;
        }

        /* ======================================
           LOGO NAVBAR
        ====================================== */

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

        /* ======================================
           PENANDA HALAMAN AKTIF
        ====================================== */

        .navbar .menu a.active,
        .navbar .menu a.active:visited {
            color: #31583c !important;
            font-weight: 700 !important;
            position: relative;
        }

        .navbar .menu a.active::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -7px;
            width: 100% !important;
            height: 2px;
            background: #31583c;
        }

        /* ======================================
           KELOMPOK LONCENG DAN AKUN
        ====================================== */

        .navbar-account-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            flex-shrink: 0;
        }

        /* ======================================
           IKON NOTIFIKASI
        ====================================== */

        .notification-link {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            margin: 0;
            color: #31583c;
            text-decoration: none;
            border-radius: 50%;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .notification-link:hover {
            background: #f0f5ef;
        }

        .notification-link svg {
            width: 23px;
            height: 23px;
            stroke: currentColor;
        }

        .notification-badge {
            position: absolute;
            top: -2px;
            right: -3px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            background: #d93025;
            color: white;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #f7f8f5;
        }

        /* ======================================
           MENU AKUN
        ====================================== */

        .account-dropdown {
            position: relative;
            margin: 0;
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

        /* ======================================
           TOMBOL KEMBALI KE PANEL ADMIN
        ====================================== */

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
           INFORMASI PERIODE PENYEWAAN
        ====================================== */

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

        .detail-btn {
            display: inline-block;
            background: #31583c;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        .detail-btn:hover {
            background: #24442d;
        }

        /* ======================================
           AREA KATALOG
        ====================================== */

        .catalog {
            padding: 45px 7%;
        }

        .catalog-heading {
            margin-bottom: 30px;
        }

        .catalog-heading h1 {
            color: #294a36;
            margin-bottom: 10px;
        }

        .catalog-heading p {
            color: #78847b;
        }

        .catalog-layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 30px;
            align-items: start;
        }

        /* ======================================
           FILTER KATEGORI
        ====================================== */

        .filter-box {
            background: white;
            border: 1px solid #e7ece7;
            border-radius: 10px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .filter-box h3 {
            color: #31583c;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: #46564a;
            font-size: 14px;
            cursor: pointer;
        }

        .filter-option input {
            width: 16px;
            height: 16px;
            accent-color: #31583c;
            cursor: pointer;
            flex-shrink: 0;
        }

        .filter-reset {
            width: 100%;
            margin-top: 8px;
            padding: 10px;
            border: 1px solid #31583c;
            border-radius: 6px;
            background: white;
            color: #31583c;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-reset:hover {
            background: #f0f5ef;
        }

        /* ======================================
           PENCARIAN BARANG
        ====================================== */

        .catalog-search {
            position: relative;
            margin-bottom: 25px;
        }

        .catalog-search input {
            width: 100%;
            padding: 14px 18px 14px 45px;
            border: 1px solid #dce4dc;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            background: white;
        }

        .catalog-search input:focus {
            border-color: #31583c;
            box-shadow: 0 0 0 3px rgba(49,88,60,0.08);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #78847b;
            font-size: 18px;
        }

        /* ======================================
           GRID PRODUK
        ====================================== */

        .product-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .product-card {
            display: block;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #edf0eb;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 7px 18px rgba(0,0,0,0.10);
        }

        .product-image {
            width: 100%;
            height: 175px;
            object-fit: cover;
            display: block;
            background: #edf2ec;
        }

        .image-placeholder {
            width: 100%;
            height: 175px;
            background: #edf2ec;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #78847b;
            font-size: 13px;
        }

        .product-info {
            padding: 15px 12px 18px;
            text-align: center;
        }

        .product-info h3 {
            color: #31583c;
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 9px;
        }

        .product-info .price {
            color: #293b30;
            font-size: 14px;
            font-weight: 700;
            margin: 0;
        }

        .empty-result {
            display: none;
            padding: 35px 20px;
            text-align: center;
            background: white;
            border-radius: 10px;
            color: #78847b;
            border: 1px solid #edf0eb;
        }

        .empty-result h3 {
            color: #31583c;
            margin-bottom: 8px;
        }

        /* ======================================
           RESPONSIVE
        ====================================== */

        @media (max-width: 1100px) {
            .product-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {
            .catalog-layout {
                grid-template-columns: 190px minmax(0, 1fr);
                gap: 20px;
            }

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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

            .navbar-account-group {
                margin-left: 0;
            }

            .catalog-layout {
                grid-template-columns: 1fr;
            }

            .filter-box {
                padding: 18px;
            }

            .rental-period {
                padding: 20px 5%;
            }

            .catalog {
                padding: 35px 5%;
            }
        }

        @media (max-width: 450px) {
            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .product-image,
            .image-placeholder {
                height: 140px;
            }

            .product-info h3 {
                font-size: 13px;
            }

            .product-info .price {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<!-- ======================================
     INFORMASI PERIODE PENYEWAAN
====================================== -->

<?php if ($adaPeriodeSewa): ?>

<div class="rental-period">

    <div>

        <small>Periode Penyewaan</small>

        <h3>
            <?= $tanggalMulai->format("d M Y"); ?>
            -
            <?= $tanggalSelesai->format("d M Y"); ?>
        </h3>

        <div class="rental-info">

            <p>
                <strong>Durasi:</strong>
                <?= $durasiSewa; ?> hari
            </p>

            <p>
                <strong>Batas Pengembalian:</strong>
                <?= $batasPengembalian->format("d M Y"); ?>
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

<?php endif; ?>

<!-- ======================================
     NAVBAR KELANA
====================================== -->

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
            Keranjang (<?php echo count($_SESSION["keranjang"]); ?>)
        </a>

        <a href="tentang.php"
           class="<?php echo $halamanAktif === 'tentang.php' ? 'active' : ''; ?>">
            Tentang Kami
        </a>

        <a href="faq.php"
           class="<?php echo $halamanAktif === 'faq.php' ? 'active' : ''; ?>">
            FAQ
        </a>

        <?php if ($isAdmin) { ?>
            <a href="../admin/dashboard.php" class="admin-back-link">
                Kembali ke Panel Admin
            </a>
        <?php } ?>

    </div>

    <div class="navbar-account-group">

        <?php if ($isPelanggan) { ?>

            <a href="notifikasi.php"
               class="notification-link"
               title="Notifikasi"
               aria-label="Notifikasi">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                    <path d="M10 21h4"/>

                </svg>

                <?php if ($jumlahNotifikasi > 0) { ?>
                    <span class="notification-badge">
                        <?= $jumlahNotifikasi > 99 ? "99+" : $jumlahNotifikasi; ?>
                    </span>
                <?php } ?>

            </a>

        <?php } ?>

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

            <a href="../auth/login.php" class="login">Masuk</a>

        <?php } ?>

    </div>

</nav>

<!-- ======================================
     KONTEN KATALOG
====================================== -->

<section class="catalog">

    <div class="catalog-heading">
        <h1>Katalog Perlengkapan Camping</h1>
        <p>Temukan perlengkapan terbaik untuk petualanganmu.</p>
    </div>

    <div class="catalog-layout">

        <!-- FILTER KATEGORI -->

        <aside class="filter-box">

            <h3>Kategori Barang</h3>

            <div id="categoryFilters">

                <?php if ($queryKategori && mysqli_num_rows($queryKategori) > 0): ?>

                    <?php while ($kategori = mysqli_fetch_assoc($queryKategori)): ?>

                        <label class="filter-option">

                            <input
                                type="checkbox"
                                class="category-checkbox"
                                value="<?= e(formatKategori($kategori["nama_kategori"])); ?>"
                            >

                            <span>
                                <?= e($kategori["nama_kategori"]); ?>
                            </span>

                        </label>

                    <?php endwhile; ?>

                <?php else: ?>

                    <p style="font-size: 13px; color: #78847b;">
                        Belum ada kategori barang.
                    </p>

                <?php endif; ?>

            </div>

            <button type="button" class="filter-reset" id="resetFilter">
                Tampilkan Semua
            </button>

        </aside>

        <!-- DAFTAR BARANG -->

        <div class="catalog-content">

            <div class="catalog-search">

                <span class="search-icon">⌕</span>

                <input
                    type="search"
                    id="searchProduct"
                    placeholder="Cari perlengkapan camping..."
                    autocomplete="off"
                >

            </div>

            <div class="product-grid" id="productGrid">

                <?php if ($query && mysqli_num_rows($query) > 0): ?>

                    <?php while ($barang = mysqli_fetch_assoc($query)): ?>

                        <?php

                        $namaGambar = basename(
                            str_replace(
                                "\\",
                                "/",
                                (string) ($barang["gambar"] ?? "")
                            )
                        );

                        $lokasiGambar = "../assets/images/" . $namaGambar;

                        $fileGambarAda = (
                            $namaGambar !== "" &&
                            is_file(__DIR__ . "/../assets/images/" . $namaGambar)
                        );

                        ?>

                        <a
                            href="detail.php?id=<?= (int) $barang["id_barang"]; ?>"
                            class="product-card"
                            data-category="<?= e(formatKategori($barang["nama_kategori"])); ?>"
                            data-name="<?= e(strtolower($barang["nama_barang"])); ?>"
                        >

                            <?php if ($fileGambarAda): ?>

                                <img
                                    src="<?= e($lokasiGambar); ?>"
                                    alt="<?= e($barang["nama_barang"]); ?>"
                                    class="product-image"
                                >

                            <?php else: ?>

                                <div class="image-placeholder">
                                    Foto belum tersedia
                                </div>

                            <?php endif; ?>

                            <div class="product-info">

                                <h3>
                                    <?= e($barang["nama_barang"]); ?>
                                </h3>

                                <p class="price">
                                    Rp <?= number_format(
                                        $barang["harga_sewa"],
                                        0,
                                        ",",
                                        "."
                                    ); ?> / hari
                                </p>

                            </div>

                        </a>

                    <?php endwhile; ?>

                <?php else: ?>

                    <p>Belum ada barang yang tersedia.</p>

                <?php endif; ?>

            </div>

            <div class="empty-result" id="emptyResult">
                <h3>Barang Tidak Ditemukan</h3>

                <p>
                    Coba gunakan kata kunci lain atau ubah kategori yang dipilih.
                </p>
            </div>

        </div>

    </div>

</section>

<!-- ======================================
     JAVASCRIPT FILTER DAN PENCARIAN
====================================== -->

<script>

const checkboxes = document.querySelectorAll(".category-checkbox");
const productCards = document.querySelectorAll(".product-card");
const resetFilter = document.getElementById("resetFilter");
const emptyResult = document.getElementById("emptyResult");
const searchProduct = document.getElementById("searchProduct");

function filterProducts() {

    const kategoriDipilih = Array.from(checkboxes)
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.value);

    const kataKunci = searchProduct.value
        .trim()
        .toLowerCase();

    let jumlahTampil = 0;

    productCards.forEach(function(card) {

        const kategoriBarang = card.dataset.category;
        const namaBarang = card.dataset.name;

        const cocokKategori = (
            kategoriDipilih.length === 0 ||
            kategoriDipilih.includes(kategoriBarang)
        );

        const cocokPencarian = namaBarang.includes(kataKunci);

        const cocok = cocokKategori && cocokPencarian;

        card.style.display = cocok ? "" : "none";

        if (cocok) {
            jumlahTampil++;
        }

    });

    emptyResult.style.display = jumlahTampil === 0 ? "block" : "none";
}

checkboxes.forEach(function(checkbox) {
    checkbox.addEventListener("change", filterProducts);
});

searchProduct.addEventListener("input", filterProducts);

resetFilter.addEventListener("click", function() {

    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });

    searchProduct.value = "";

    filterProducts();

});

</script>

<!-- ======================================
     POPUP NOTIFIKASI GLOBAL
====================================== -->

<?php include "notifikasi_popup.php"; ?>

</body>
</html>