
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// 1. CEK ROLE USER
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

// ==========================================
// 2. INISIALISASI KERANJANG
// ==========================================

if (!isset($_SESSION["keranjang"])) {
    $_SESSION["keranjang"] = [];
}

// ==========================================
// 3. AMBIL DATA FAQ
// ==========================================

$queryFaq = mysqli_query(
    $conn,
    "SELECT * FROM faq ORDER BY id_faq ASC"
);

function e($nilai) {
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>FAQ - Kelana</title>

    <!-- CSS UTAMA KELANA -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        /* Background mengikuti CSS utama */
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
           TOMBOL NOTIFIKASI
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
           MENU AKUN
        ====================================== */

        .account-dropdown {
            position: relative;
            margin-left: 0;
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
           KONTEN FAQ
        ====================================== */

        .container {
            max-width: 900px;
            margin: 55px auto;
            padding: 0 20px;
        }

        .judul {
            text-align: center;
            margin-bottom: 35px;
        }

        .judul h1 {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .judul p {
            color: #78847b;
            font-size: 15px;
            line-height: 1.7;
        }

        .faq-item {
            background: rgba(255, 255, 255, 0.94);
            margin-bottom: 15px;
            border-radius: 10px;
            border: 1px solid #e7ece7;
            overflow: hidden;
        }

        .pertanyaan {
            width: 100%;
            padding: 20px;
            background: white;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: left;
            cursor: pointer;
            color: #294a36;
            font-size: 15px;
            font-weight: bold;
            gap: 15px;
        }

        .pertanyaan:hover {
            background: #f5f8f4;
        }

        .simbol {
            font-size: 22px;
            flex-shrink: 0;
        }

        .jawaban {
            display: none;
            padding: 0 20px 20px;
            color: #68756b;
            font-size: 14px;
            line-height: 1.8;
            white-space: normal;
        }

        .faq-item.aktif .jawaban {
            display: block;
        }

        .kosong {
            background: rgba(255, 255, 255, 0.94);
            padding: 35px;
            border-radius: 10px;
            text-align: center;
            color: #78847b;
            line-height: 1.8;
        }

        /* ======================================
           KONTAK ADMIN
        ====================================== */

        .kontak {
            text-align: center;
            margin-top: 45px;
            padding: 30px;
            background: rgba(234, 241, 233, 0.94);
            border-radius: 12px;
        }

        .kontak h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .kontak p {
            color: #68756b;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .tombol-wa {
            display: inline-block;
            background: #315d43;
            color: white;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 7px;
            font-size: 14px;
        }

        .tombol-wa:hover {
            background: #244b38;
        }

        /* ======================================
           FOOTER
        ====================================== */

        footer {
            background: #244b38;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 55px;
            font-size: 13px;
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

            .container {
                margin: 35px auto;
            }

            .judul h1 {
                font-size: 26px;
            }

        }

    </style>

</head>

<body>

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

            <a href="../auth/login.php" class="login">Masuk</a>

        <?php } ?>

    </div>

</nav>

<!-- ======================================
     KONTEN FAQ
====================================== -->

<main class="container">

    <div class="judul">

        <h1>Pertanyaan yang Sering Diajukan</h1>

        <p>
            Temukan jawaban atas pertanyaan seputar penyewaan
            perlengkapan camping di Kelana.
        </p>

    </div>

    <?php if ($queryFaq && mysqli_num_rows($queryFaq) > 0): ?>

        <?php while ($faq = mysqli_fetch_assoc($queryFaq)): ?>

            <div class="faq-item">

                <button type="button" class="pertanyaan">

                    <span>
                        <?= e($faq["pertanyaan"]) ?>
                    </span>

                    <span class="simbol">+</span>

                </button>

                <div class="jawaban">

                    <?= nl2br(e($faq["jawaban"])) ?>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="kosong">

            <h3>Belum Ada Pertanyaan</h3>

            <p>
                Informasi FAQ sedang disiapkan.
                Silakan hubungi admin Kelana jika membutuhkan bantuan.
            </p>

        </div>

    <?php endif; ?>

    <!-- KONTAK ADMIN -->

    <section class="kontak">

        <h2>Masih Ada Pertanyaan?</h2>

        <p>
            Hubungi admin Kelana untuk mendapatkan informasi lebih lanjut.
        </p>

        <a
            href="https://wa.me/6281216964867"
            target="_blank"
            rel="noopener noreferrer"
            class="tombol-wa"
        >
            Hubungi Admin
        </a>

    </section>

</main>

<!-- ======================================
     FOOTER
====================================== -->

<footer>

    &copy; 2026 Kelana. Rental Perlengkapan Camping.

</footer>

<!-- ======================================
     JAVASCRIPT FAQ
====================================== -->

<script>

const pertanyaan = document.querySelectorAll(".pertanyaan");

pertanyaan.forEach(function(tombol) {

    tombol.addEventListener("click", function() {

        const item = tombol.closest(".faq-item");

        const sedangAktif = item.classList.contains("aktif");

        document.querySelectorAll(".faq-item").forEach(function(faq) {

            faq.classList.remove("aktif");

            faq.querySelector(".simbol").textContent = "+";

        });

        if (!sedangAktif) {

            item.classList.add("aktif");

            tombol.querySelector(".simbol").textContent = "−";

        }

    });

});

</script>

<?php
// Popup notifikasi otomatis khusus pelanggan
if ($isPelanggan) {
    include "notifikasi_popup.php";
}
?>

</body>
</html>
