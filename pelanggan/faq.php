
<?php
session_start();
include "../config/database.php";

// Ambil data FAQ dari database
$queryFaq = mysqli_query(
    $conn,
    "SELECT * FROM faq ORDER BY id_faq ASC"
);

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Kelana</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f7f8f5;
            color: #293b30;
        }

        header {
            background: #244b38;
            padding: 20px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo {
            color: white;
            font-size: 25px;
            font-weight: bold;
            text-decoration: none;
        }

        nav {
            display: flex;
            gap: 23px;
            flex-wrap: wrap;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        nav a:hover {
            color: #c8dfc9;
        }

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
            background: white;
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
            background: white;
            padding: 35px;
            border-radius: 10px;
            text-align: center;
            color: #78847b;
            line-height: 1.8;
        }

        .kontak {
            text-align: center;
            margin-top: 45px;
            padding: 30px;
            background: #eaf1e9;
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

        footer {
            background: #244b38;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 55px;
            font-size: 13px;
        }

        @media(max-width: 600px) {
            header {
                padding: 18px 5%;
            }

            nav {
                gap: 13px;
            }

            .judul h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<header>
    <a href="../index.php" class="logo">KELANA.</a>

    <nav>
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
        <a href="riwayat.php">Riwayat</a>
        <a href="faq.php">FAQ</a>
        <a href="profil.php">Profil</a>
    </nav>
</header>

<main class="container">

    <div class="judul">
        <h1>Pertanyaan yang Sering Diajukan</h1>
        <p>
            Temukan jawaban atas pertanyaan seputar penyewaan
            perlengkapan camping di Kelana.
        </p>
    </div>

    <?php if (mysqli_num_rows($queryFaq) > 0): ?>

        <?php while ($faq = mysqli_fetch_assoc($queryFaq)): ?>

            <div class="faq-item">
                <button type="button" class="pertanyaan">
                    <span><?= e($faq["pertanyaan"]) ?></span>
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

    <section class="kontak">
        <h2>Masih Ada Pertanyaan?</h2>
        <p>Hubungi admin Kelana untuk mendapatkan informasi lebih lanjut.</p>

        <a
            href="https://wa.me/6281216964867"
            target="_blank"
            class="tombol-wa"
        >
            Hubungi Admin
        </a>
    </section>

</main>

<footer>
    &copy; 2026 Kelana. Rental Perlengkapan Camping.
</footer>

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

</body>
</html>
