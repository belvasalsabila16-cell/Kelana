
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelana - Rental Alat Camping</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<nav class="navbar">

    <h2>KELANA.</h2>

    <div class="menu">
        <a href="index.php">Beranda</a>
        <a href="pelanggan/katalog.php">Katalog</a>
        <a href="tentang.php">Tentang Kami</a>
        <a href="pelanggan/faq.php">FAQ</a>
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

                <a href="pelanggan/profil.php">
                    <span>Profil Saya</span>
                    <span class="dropdown-arrow">›</span>
                </a>

                <div class="dropdown-divider"></div>

                <a href="pelanggan/riwayat.php">
                    <span>Riwayat Penyewaan</span>
                    <span class="dropdown-arrow">›</span>
                </a>

                <div class="dropdown-divider"></div>

                <a href="auth/logout.php" class="logout-link"
                   onclick="return confirm('Yakin ingin keluar dari akun?')">
                    Keluar
                </a>

            </div>

        </details>

    <?php } else { ?>

        <a href="auth/login.php" class="login">Masuk</a>

    <?php } ?>

</nav>

<section class="hero">

    <h1>Jelajahi Alam,<br>Mulai Petualanganmu.</h1>

    <p>Sewa perlengkapan camping dengan mudah bersama Kelana.</p>

    <a href="pelanggan/pilih_tanggal.php" class="btn">
        Mulai Sewa
    </a>

</section>

</body>
</html>
