
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - Kelana</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
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

        .about-content img {
            width: 100%;
            border-radius: 15px;
            object-fit: cover;
        }

        .about-text h2 {
            margin-bottom: 15px;
        }

        .about-text p {
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .about-info {
            margin-top: 50px;
            padding: 30px;
            background: #f3f5f0;
            border-radius: 15px;
            text-align: center;
        }

        .about-info h2 {
            margin-bottom: 15px;
        }

        @media (max-width: 700px) {
            .about-content {
                grid-template-columns: 1fr;
            }

            .about-header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">

    <h2>KELANA.</h2>

    <div class="menu">
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
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

                <a href="../auth/logout.php" class="logout-link"
                   onclick="return confirm('Yakin ingin keluar dari akun?')">
                    Keluar
                </a>
            </div>
        </details>

    <?php } else { ?>

        <a href="../auth/login.php" class="login">Masuk</a>

    <?php } ?>

</nav>

<main class="about-container">

    <div class="about-header">
        <h1>Tentang Kelana</h1>
        <p>Teman Perjalanan untuk Setiap Petualangan.</p>
    </div>

    <div class="about-content">

        <div>
            <img src="../assets/images/camping.jpg"
                 alt="Suasana kegiatan camping"
                 onerror="this.style.display='none'">
        </div>

        <div class="about-text">
            <h2>Kenali Kami Lebih Dekat</h2>

            <p>
                Kelana merupakan layanan penyewaan perlengkapan camping
                yang hadir untuk membantu masyarakat menikmati kegiatan
                luar ruangan dengan lebih mudah dan praktis.
            </p>

            <p>
                Kami menyediakan berbagai perlengkapan camping,
                mulai dari tenda, carrier, hingga perlengkapan pendukung
                lainnya yang dapat disewa sesuai kebutuhan.
            </p>

            <p>
                Melalui Kelana, pelanggan dapat melihat katalog,
                memilih tanggal penyewaan, melakukan booking,
                dan mengelola riwayat penyewaan secara online.
            </p>
        </div>

    </div>

    <div class="about-info">
        <h2>Visi Kami</h2>
        <p>
            Menjadi layanan penyewaan perlengkapan camping yang
            memudahkan setiap orang untuk menjelajahi alam
            dan menciptakan pengalaman petualangan yang berkesan.
        </p>
    </div>

</main>

</body>
</html>
