
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// CEK ROLE USER
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
        $isAdmin = ($dataRole["role"] === "admin");
        $isPelanggan = ($dataRole["role"] === "pelanggan");
    }

    // Menghitung notifikasi pelanggan yang belum dibaca
    if ($isPelanggan) {

        $stmtNotif = mysqli_prepare($conn, "
            SELECT COUNT(*) AS jumlah
            FROM notifikasi_pelanggan
            WHERE id_user = ? AND dibaca = 0
        ");

        mysqli_stmt_bind_param($stmtNotif, "i", $idUser);
        mysqli_stmt_execute($stmtNotif);

        $hasilNotif = mysqli_stmt_get_result($stmtNotif);
        $dataNotif = mysqli_fetch_assoc($hasilNotif);

        $jumlahNotifikasi = (int) ($dataNotif["jumlah"] ?? 0);

        mysqli_stmt_close($stmtNotif);
    }
}

// Menentukan halaman yang sedang dibuka
$halamanAktif = basename($_SERVER["PHP_SELF"]);

// Menghitung jumlah barang dalam keranjang
$jumlahKeranjang = isset($_SESSION["keranjang"])
    ? array_sum($_SESSION["keranjang"])
    : 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tentang Kami - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

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

        /* ======================================
           KONTEN TENTANG KAMI
        ====================================== */

        .about-container {
            max-width: 1000px;
            margin: 60px auto;
            padding: 30px 20px;
        }

        .about-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .about-header h1 {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            align-items: center;
        }

        /* LOGO DI SAMPING TEKS */

        .about-content .about-logo {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .about-content .about-logo img {
            width: 100%;
            max-width: 350px;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .about-text h2 {
            margin-bottom: 15px;
        }

        .about-text p {
            line-height: 1.8;
            margin-bottom: 15px;
        }

        /* ======================================
           VISI KAMI
        ====================================== */

        .about-info {
            margin-top: 35px;
            padding: 30px;
            background: #f3f5f0;
            border-radius: 15px;
            text-align: center;
        }

        .about-info h2 {
            margin-bottom: 15px;
            color: #31583c;
        }

        .about-info p {
            line-height: 1.8;
        }

        /* ======================================
           MISI KAMI
        ====================================== */

        .about-mission {
            margin-top: 25px;
            padding: 30px;
            background: #f3f5f0;
            border-radius: 15px;
        }

        .about-mission h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #31583c;
        }

        .about-mission ul {
            max-width: 700px;
            margin: 0 auto;
            padding-left: 25px;
        }

        .about-mission li {
            line-height: 1.8;
            margin-bottom: 10px;
        }

        /* ======================================
           TOMBOL KEMBALI ADMIN
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
            background: #25452e;
        }

        /* ======================================
           NAVBAR
        ====================================== */

        .navbar {
            flex-wrap: wrap;
            gap: 15px;
        }

        .menu {
            flex-wrap: wrap;
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
           KELOMPOK AKUN DAN NOTIFIKASI
        ====================================== */

        .navbar-account-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }

        /* ======================================
           TOMBOL LONCENG
        ====================================== */

        .notification-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: #31583c;
            text-decoration: none;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .notification-link:hover {
            background: #edf3ed;
        }

        .notification-link svg {
            width: 23px;
            height: 23px;
        }

        .notification-badge {
            position: absolute;
            top: -3px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e53935;
            color: white;
            font-size: 10px;
            font-weight: 700;
            border-radius: 20px;
            border: 2px solid white;
        }

        /* ======================================
           RESPONSIVE
        ====================================== */

        @media (max-width: 700px) {

            .navbar .logo-link img {
                width: 120px;
                height: 55px;
            }

            .about-content {
                grid-template-columns: 1fr;
            }

            .about-header h1 {
                font-size: 28px;
            }

            .about-content .about-logo img {
                max-width: 250px;
            }

            .navbar-account-group {
                margin-left: 0;
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
            Keranjang (<?php echo $jumlahKeranjang; ?>)
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
               title="Notifikasi">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>

                    <path d="M10 21h4"/>

                </svg>

                <?php if ($jumlahNotifikasi > 0) { ?>

                    <span class="notification-badge">
                        <?php echo $jumlahNotifikasi > 99 ? "99+" : $jumlahNotifikasi; ?>
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

            <a href="../auth/login.php" class="login">
                Masuk
            </a>

        <?php } ?>

    </div>

</nav>

<!-- ======================================
     KONTEN TENTANG KAMI
====================================== -->

<main class="about-container">

    <div class="about-header">

        <h1>Tentang KELANA</h1>

        <p>Teman Perjalanan untuk Setiap Petualangan.</p>

    </div>

    <div class="about-content">

        <!-- LOGO KELANA -->

        <div class="about-logo">

            <img
                src="../assets/images/logo-kelana.png"
                alt="Logo KELANA"
            >

        </div>

        <!-- PROFIL KELANA -->

        <div class="about-text">

            <h2>Kenali Kami Lebih Dekat</h2>

            <p>
                KELANA merupakan layanan penyewaan peralatan camping
                yang hadir untuk memudahkan masyarakat dalam mempersiapkan
                kegiatan berkemah dan aktivitas alam terbuka.
            </p>

            <p>
                Kami menyediakan berbagai perlengkapan camping yang dapat
                disewa sesuai kebutuhan, mulai dari tenda, carrier,
                hingga perlengkapan pendukung lainnya.
            </p>

            <p>
                Melalui website KELANA, pelanggan dapat melihat katalog barang,
                memeriksa informasi harga, memilih tanggal penyewaan,
                serta melakukan pemesanan secara online dengan mudah dan praktis.
            </p>

        </div>

    </div>

    <!-- VISI KELANA -->

    <div class="about-info">

        <h2>Visi Kami</h2>

        <p>
            Menjadi layanan penyewaan peralatan camping yang praktis,
            terpercaya, dan mudah dijangkau oleh masyarakat.
        </p>

    </div>

    <!-- MISI KELANA -->

    <div class="about-mission">

        <h2>Misi Kami</h2>

        <ul>

            <li>
                Menyediakan berbagai pilihan perlengkapan camping
                sesuai kebutuhan pelanggan.
            </li>

            <li>
                Memudahkan pelanggan dalam proses pemesanan
                dan penyewaan peralatan camping.
            </li>

            <li>
                Menjaga kualitas serta kondisi perlengkapan
                yang disewakan agar siap digunakan.
            </li>

            <li>
                Memberikan pelayanan yang ramah dan informatif
                kepada setiap pelanggan.
            </li>

        </ul>

    </div>

</main>

<?php
// Popup notifikasi otomatis khusus pelanggan
if ($isPelanggan) {
    include "notifikasi_popup.php";
}
?>

</body>
</html>
