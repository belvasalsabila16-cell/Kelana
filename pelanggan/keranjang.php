
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// 1. CEK HAK AKSES DAN NOTIFIKASI
// ==========================================

$isAdmin = false;
$isPelanggan = false;
$jumlahNotifikasi = 0;

if (isset($_SESSION["id_user"])) {
    $idUser = (int) $_SESSION["id_user"];

    $cekAdmin = mysqli_query(
        $conn,
        "SELECT role FROM user WHERE id_user = $idUser"
    );

    $dataAdmin = mysqli_fetch_assoc($cekAdmin);

    if ($dataAdmin) {
        if ($dataAdmin["role"] === "admin") {
            $isAdmin = true;
        }

        if ($dataAdmin["role"] === "pelanggan") {
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

// Menentukan halaman yang sedang dibuka
$halamanAktif = basename($_SERVER["PHP_SELF"]);

// ==========================================
// 2. PASTIKAN TANGGAL PENYEWAAN DIPILIH
// ==========================================

if (
    empty($_SESSION["tanggal_mulai"]) ||
    empty($_SESSION["tanggal_selesai"])
) {
    header("Location: pilih_tanggal.php");
    exit;
}

// ==========================================
// 3. INISIALISASI KERANJANG
// ==========================================

if (!isset($_SESSION["keranjang"])) {
    $_SESSION["keranjang"] = [];
}

// ==========================================
// 4. PROSES MENGUBAH ISI KERANJANG
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["aksi_keranjang"])
) {
    $id_barang = (int) ($_POST["id_barang"] ?? 0);
    $aksi = $_POST["aksi_keranjang"];

    if (isset($_SESSION["keranjang"][$id_barang])) {

        if ($aksi === "kurang") {

            $_SESSION["keranjang"][$id_barang]--;

            if ($_SESSION["keranjang"][$id_barang] <= 0) {
                unset($_SESSION["keranjang"][$id_barang]);
            }

        } elseif ($aksi === "tambah") {

            $cek = mysqli_query(
                $conn,
                "SELECT stok FROM barang WHERE id_barang = $id_barang"
            );

            $data = mysqli_fetch_assoc($cek);

            if (
                $data &&
                $_SESSION["keranjang"][$id_barang] < $data["stok"]
            ) {
                $_SESSION["keranjang"][$id_barang]++;
            }

        } elseif ($aksi === "hapus") {

            unset($_SESSION["keranjang"][$id_barang]);

        }
    }

    header("Location: keranjang.php");
    exit;
}

// ==========================================
// 5. HITUNG DURASI PENYEWAAN
// ==========================================

$awal = new DateTime($_SESSION["tanggal_mulai"]);
$akhir = new DateTime($_SESSION["tanggal_selesai"]);

$jumlahHari = $awal->diff($akhir)->days + 1;

// ==========================================
// 6. HITUNG BATAS PENGEMBALIAN
// ==========================================

if (!empty($_SESSION["batas_pengembalian"])) {

    $batasPengembalian = new DateTime(
        $_SESSION["batas_pengembalian"]
    );

} else {

    $batasPengembalian = clone $akhir;
    $batasPengembalian->modify("+2 days");

}

// ==========================================
// 7. AMBIL ISI KERANJANG
// ==========================================

$keranjang = $_SESSION["keranjang"] ?? [];

$totalHarga = 0;
$daftarBarang = [];

if (!empty($keranjang)) {

    $ids = array_map("intval", array_keys($keranjang));
    $idList = implode(",", $ids);

    $query = mysqli_query($conn, "
        SELECT * FROM barang
        WHERE id_barang IN ($idList)
    ");

    while ($barang = mysqli_fetch_assoc($query)) {

        $id = $barang["id_barang"];
        $jumlah = (int) $keranjang[$id];

        if ($jumlah <= 0) {
            continue;
        }

        $subtotal = $barang["harga_sewa"] * $jumlah * $jumlahHari;

        $barang["jumlah"] = $jumlah;
        $barang["subtotal"] = $subtotal;

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

        * {
            box-sizing: border-box;
        }

        /* ======================================
           BACKGROUND HALAMAN
        ====================================== */

        body {
            background-color: transparent;
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

        /* ======================================
           PENANDA HALAMAN AKTIF
        ====================================== */

        .navbar .menu a.active {
            color: #31583c;
            font-weight: 700;
            position: relative;
        }

        .navbar .menu a.active::after {
            width: 100%;
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
           RESPONSIVE
        ====================================== */

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

            .cart-container {
                padding: 30px 5%;
            }

        }

    </style>

</head>

<body>

<!-- ======================================
     NAVBAR
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
            Keranjang (<?php echo count($_SESSION["keranjang"] ?? []); ?>)
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

    <!-- LONCENG DAN AKUN -->

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
     ISI KERANJANG
====================================== -->

<section class="cart-container">

    <h1>Keranjang Saya</h1>

    <p>Periksa perlengkapan camping yang ingin kamu sewa.</p>

    <div style="background:#f0f5ef; padding:20px; border-radius:10px; margin-top:20px;">

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

        <p>
            <strong>Batas Pengembalian:</strong>
            <?php echo $batasPengembalian->format("d M Y"); ?>
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
                    <?php echo htmlspecialchars($barang["nama_barang"]); ?>
                </h3>

                <p>
                    Harga per hari:
                    Rp <?php echo number_format(
                        $barang["harga_sewa"], 0, ",", "."
                    ); ?>
                </p>

                <p>Jumlah:</p>

                <div style="display:flex; align-items:center; gap:10px; margin:10px 0; flex-wrap:wrap;">

                    <form method="POST">

                        <input type="hidden"
                               name="id_barang"
                               value="<?php echo $barang["id_barang"]; ?>">

                        <button type="submit"
                                name="aksi_keranjang"
                                value="kurang"
                                class="cart-btn"
                                style="margin-top:0;">
                            −
                        </button>

                    </form>

                    <strong>
                        <?php echo $barang["jumlah"]; ?> unit
                    </strong>

                    <form method="POST">

                        <input type="hidden"
                               name="id_barang"
                               value="<?php echo $barang["id_barang"]; ?>">

                        <button type="submit"
                                name="aksi_keranjang"
                                value="tambah"
                                class="cart-btn"
                                style="margin-top:0;">
                            +
                        </button>

                    </form>

                    <form method="POST">

                        <input type="hidden"
                               name="id_barang"
                               value="<?php echo $barang["id_barang"]; ?>">

                        <button type="submit"
                                name="aksi_keranjang"
                                value="hapus"
                                class="cart-btn"
                                style="margin-top:0; background:#a33;">
                            Hapus
                        </button>

                    </form>

                </div>

                <p>
                    Durasi: <?php echo $jumlahHari; ?> hari
                </p>

                <strong>
                    Subtotal: Rp
                    <?php echo number_format(
                        $barang["subtotal"], 0, ",", "."
                    ); ?>
                </strong>

            </div>

        <?php } ?>

        <div class="cart-total">

            <h2>
                Total: Rp
                <?php echo number_format(
                    $totalHarga, 0, ",", "."
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

<!-- ======================================
     JAVASCRIPT POSISI SCROLL
====================================== -->

<script>

document.querySelectorAll(".cart-item form").forEach(form => {

    form.addEventListener("submit", function () {

        sessionStorage.setItem(
            "posisiScroll",
            window.scrollY
        );

    });

});

window.addEventListener("load", function () {

    const posisi = sessionStorage.getItem("posisiScroll");

    if (posisi !== null) {

        window.scrollTo(0, parseInt(posisi, 10));

        sessionStorage.removeItem("posisiScroll");

    }

});

</script>

<!-- ======================================
     POPUP NOTIFIKASI GLOBAL
====================================== -->

<?php include "notifikasi_popup.php"; ?>

</body>
</html>
