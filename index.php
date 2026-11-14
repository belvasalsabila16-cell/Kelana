
<?php
session_start();
include "config/database.php";

// ==========================================
// CEK ROLE PENGGUNA
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
        $isAdmin = $dataRole["role"] === "admin";
        $isPelanggan = $dataRole["role"] === "pelanggan";
    }

    if ($isPelanggan) {

        $stmtNotif = mysqli_prepare($conn, "
            SELECT COUNT(*) AS jumlah
            FROM notifikasi_pelanggan
            WHERE id_user = ?
            AND dibaca = 0
        ");

        mysqli_stmt_bind_param($stmtNotif, "i", $idUser);
        mysqli_stmt_execute($stmtNotif);

        $hasilNotif = mysqli_stmt_get_result($stmtNotif);
        $dataNotif = mysqli_fetch_assoc($hasilNotif);

        $jumlahNotifikasi = (int) $dataNotif["jumlah"];

        mysqli_stmt_close($stmtNotif);
    }
}

$halamanAktif = basename($_SERVER["PHP_SELF"]);

$jumlahKeranjang = isset($_SESSION["keranjang"])
    ? array_sum($_SESSION["keranjang"])
    : 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelana - Rental Alat Camping</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .admin-back-link {
            display: inline-block;
            background: #31583c;
            color: white !important;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: .2s;
        }

        .admin-back-link:hover {
            background: #25452e;
        }

        .navbar .menu a.active {
            color: #31583c;
            font-weight: 700;
            position: relative;
        }

        .navbar .menu a.active::after {
            width: 100%;
        }

        /* Ikon notifikasi */
        .notification-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: #31583c;
            text-decoration: none;
            margin-right: 10px;
        }

        .notification-link svg {
            width: 24px;
            height: 24px;
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #d93636;
            color: white;
            border-radius: 50%;
            min-width: 17px;
            height: 17px;
            padding: 0 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .customer-account-area {
            display: flex;
            align-items: center;
        }

        /* =====================================
           BAGIAN CARA SEWA
        ===================================== */

        .cara-sewa-section {
            padding: 75px 7%;
            background: rgba(247, 249, 244, 0.96);
        }

        .cara-sewa-container {
            max-width: 1100px;
            margin: auto;
        }

        .cara-sewa-header {
            text-align: center;
            margin-bottom: 45px;
        }

        .cara-sewa-label {
            display: inline-block;
            background: #e5eee5;
            color: #31583c;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        .cara-sewa-header h2 {
            color: #263b2c;
            font-size: 34px;
            margin: 0 0 15px;
        }

        .cara-sewa-header p {
            color: #657267;
            font-size: 15px;
            line-height: 1.8;
            max-width: 750px;
            margin: auto;
        }

        .cara-sewa-intro {
            background: #31583c;
            color: white;
            padding: 25px 30px;
            border-radius: 14px;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .cara-sewa-intro h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .cara-sewa-intro p {
            margin: 0;
            color: #edf3ed;
        }

        .cara-sewa-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .cara-sewa-card {
            background: white;
            border: 1px solid #e3e9e1;
            border-radius: 13px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(31, 55, 36, 0.06);
        }

        .cara-sewa-card h3 {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #31583c;
            font-size: 18px;
            margin: 0 0 15px;
            line-height: 1.5;
        }

        .nomor-cara-sewa {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #e5eee5;
            color: #31583c;
            font-size: 15px;
            font-weight: 700;
        }

        .cara-sewa-card p {
            color: #59665c;
            font-size: 14px;
            line-height: 1.8;
            margin: 0 0 12px;
        }

        .cara-sewa-card ul {
            padding-left: 20px;
            margin: 8px 0 0;
        }

        .cara-sewa-card li {
            color: #59665c;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 7px;
        }

        .cara-sewa-card strong {
            color: #354b39;
        }

        .cara-sewa-subjudul {
            color: #31583c;
            font-size: 15px;
            font-weight: 700;
            margin: 17px 0 7px;
        }

        .cara-sewa-bantuan {
            background: #eaf1e8;
            border-left: 5px solid #31583c;
            padding: 22px 25px;
            border-radius: 8px;
            margin-top: 25px;
            color: #405343;
            line-height: 1.8;
        }

        .cara-sewa-bantuan strong {
            color: #31583c;
        }

        /* Responsif HP */
        @media (max-width: 768px) {
            .cara-sewa-section {
                padding: 50px 5%;
            }

            .cara-sewa-header h2 {
                font-size: 27px;
            }

            .cara-sewa-list {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .cara-sewa-card {
                padding: 20px;
            }

            .cara-sewa-intro {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">

    <!-- LOGO KELANA -->
    <a href="index.php" class="logo-link">
        <img src="assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>

    <div class="menu">

        <a href="index.php"
           class="<?php echo $halamanAktif === 'index.php' ? 'active' : ''; ?>">
            Beranda
        </a>

        <a href="pelanggan/katalog.php"
           class="<?php echo $halamanAktif === 'katalog.php' ? 'active' : ''; ?>">
            Katalog
        </a>

        <a href="pelanggan/keranjang.php"
           class="<?php echo $halamanAktif === 'keranjang.php' ? 'active' : ''; ?>">
            Keranjang (<?php echo $jumlahKeranjang; ?>)
        </a>

        <a href="pelanggan/tentang.php"
           class="<?php echo $halamanAktif === 'tentang.php' ? 'active' : ''; ?>">
            Tentang Kami
        </a>

        <a href="pelanggan/faq.php"
           class="<?php echo $halamanAktif === 'faq.php' ? 'active' : ''; ?>">
            FAQ
        </a>

    </div>

    <?php if ($isAdmin) { ?>

        <a href="admin/dashboard.php" class="admin-back-link">
            Kembali ke Panel Admin
        </a>

    <?php } elseif ($isPelanggan) { ?>

        <div class="customer-account-area">

            <a href="pelanggan/notifikasi.php"
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

            <details class="account-dropdown">

                <summary>

                    <span class="profile-circle">
                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.7"
                             stroke-linecap="round"
                             stroke-linejoin="round">

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

                    <a href="auth/logout.php"
                       class="logout-link"
                       onclick="return confirm('Yakin ingin keluar dari akun?')">
                        Keluar
                    </a>

                </div>

            </details>

        </div>

    <?php } elseif (isset($_SESSION["id_user"])) { ?>

        <a href="auth/logout.php" class="login">
            Keluar
        </a>

    <?php } else { ?>

        <a href="auth/login.php" class="login">
            Masuk
        </a>

    <?php } ?>

</nav>

<!-- =====================================
     HERO / BANNER BERANDA
===================================== -->

<section class="hero">

    <h1>
        Jelajahi Alam,<br>
        Mulai Petualanganmu.
    </h1>

    <p>
        Sewa perlengkapan camping dengan mudah bersama Kelana.
    </p>

    <a href="pelanggan/pilih_tanggal.php" class="btn">
        Mulai Sewa
    </a>

</section>


<!-- =====================================
     PANDUAN CARA SEWA
===================================== -->

<section class="cara-sewa-section">

    <div class="cara-sewa-container">

        <div class="cara-sewa-header">

            <span class="cara-sewa-label">PANDUAN PENYEWAAN</span>

            <h2>Cara Sewa di KELANA</h2>

            <p>
                Ikuti panduan berikut untuk menyewa perlengkapan camping
                dan outdoor di KELANA dengan mudah dan nyaman.
            </p>

        </div>

        <div class="cara-sewa-intro">

            <h3>Selamat Datang di KELANA!</h3>

            <p>
                KELANA menyediakan layanan penyewaan perlengkapan pendakian
                dan kegiatan outdoor. Kami berkomitmen memberikan kemudahan
                dan kenyamanan dalam setiap proses penyewaan.
                Berikut panduan yang perlu diperhatikan sebelum menyewa.
            </p>

        </div>

        <div class="cara-sewa-list">

            <!-- 1 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">1</span>
                    Periksa Ketersediaan Peralatan
                </h3>

                <p>
                    Kunjungi website KELANA untuk melihat daftar peralatan
                    beserta stok yang tersedia pada tanggal yang diinginkan.
                    Stok dapat berubah sewaktu-waktu.
                </p>

                <p>
                    Pada akhir pekan, hari libur nasional, dan musim pendakian
                    ramai, stok dapat lebih cepat habis. Oleh karena itu,
                    sebaiknya lakukan pemesanan jauh-jauh hari.
                </p>

            </div>

            <!-- 2 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">2</span>
                    Pastikan Anda Telah Terdaftar
                </h3>

                <p>
                    Layanan penyewaan tersedia bagi pengguna yang telah
                    memiliki akun di website KELANA.
                </p>

                <ul>
                    <li>Jika belum memiliki akun, lakukan pendaftaran melalui website.</li>
                    <li>Lengkapi data diri sesuai formulir pendaftaran.</li>
                    <li>Pastikan nomor telepon atau WhatsApp yang dicantumkan aktif.</li>
                    <li>Ikuti ketentuan verifikasi akun apabila diminta oleh admin.</li>
                </ul>

            </div>

            <!-- 3 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">3</span>
                    Pilih Barang dan Lakukan Transaksi
                </h3>

                <p>
                    Lakukan pemesanan melalui website KELANA dengan langkah
                    berikut:
                </p>

                <ul>
                    <li>Tentukan tanggal mulai dan tanggal selesai penyewaan.</li>
                    <li>Pilih peralatan yang ingin disewa.</li>
                    <li>Masukkan barang ke keranjang dan lanjutkan ke checkout.</li>
                    <li>Periksa kembali pesanan dan total biaya penyewaan.</li>
                    <li>Selesaikan proses pemesanan sesuai petunjuk pembayaran.</li>
                    <li>Tunggu konfirmasi dari admin mengenai pesanan Anda.</li>
                </ul>

            </div>

            <!-- 4 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">4</span>
                    Ketentuan Jenis Sewa
                </h3>

                <p>
                    Pilih perlengkapan sesuai jenis pendakian agar barang
                    yang disewa sesuai dengan kebutuhan.
                </p>

                <div class="cara-sewa-subjudul">
                    Tektok (Naik dan Turun dalam Satu Hari)
                </div>

                <p>
                    Pendakian tektok tidak memerlukan perlengkapan tidur.
                    Peralatan yang dapat dipertimbangkan antara lain:
                </p>

                <ul>
                    <li>Carrier atau daypack sekitar 20–40 liter.</li>
                    <li>Headlamp.</li>
                    <li>Trekking pole.</li>
                    <li>Jas hujan atau ponco.</li>
                    <li>Jaket.</li>
                    <li>Kompor mini dan nesting kecil jika diperlukan.</li>
                </ul>

                <div class="cara-sewa-subjudul">
                    Menginap (Bermalam di Gunung)
                </div>

                <p>
                    Pendakian menginap membutuhkan perlengkapan bermalam
                    dan memasak, seperti:
                </p>

                <ul>
                    <li>Tenda sesuai kapasitas, lengkap dengan flysheet, frame, dan pasak.</li>
                    <li>Sleeping bag dan matras.</li>
                    <li>Carrier ukuran besar sekitar 50–70 liter.</li>
                    <li>Kompor, nesting, dan alat makan.</li>
                    <li>Headlamp dan trekking pole.</li>
                    <li>Jas hujan atau ponco serta jaket.</li>
                </ul>

                <p>
                    Jika masih ragu menentukan perlengkapan, konsultasikan
                    kebutuhan Anda dengan admin KELANA.
                </p>

            </div>

            <!-- 5 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">5</span>
                    Ketentuan Sewa untuk Rombongan
                </h3>

                <p>
                    Untuk penyewaan lebih dari dua orang, perhatikan
                    beberapa hal berikut:
                </p>

                <ul>
                    <li>
                        <strong>Pilih kapasitas tenda yang sesuai.</strong>
                        Tenda kapasitas dua orang untuk 1–2 pendaki dan
                        kapasitas empat orang untuk 3–4 pendaki.
                    </li>
                    <li>
                        <strong>Peralatan bersama bisa dibagi.</strong>
                        Tenda, kompor, dan nesting dapat digunakan bersama
                        sesuai kebutuhan kelompok.
                    </li>
                    <li>
                        <strong>Peralatan pribadi disesuaikan.</strong>
                        Carrier, sleeping bag, matras, dan headlamp
                        disesuaikan dengan jumlah peserta.
                    </li>
                    <li>
                        <strong>Tunjuk satu penanggung jawab.</strong>
                        Penyewa utama bertanggung jawab atas pemesanan
                        dan seluruh peralatan yang disewa.
                    </li>
                    <li>
                        <strong>Pastikan data peserta tersedia.</strong>
                        Siapkan identitas dan nomor kontak yang dapat dihubungi
                        apabila diperlukan.
                    </li>
                    <li>
                        Untuk rombongan besar, hubungi admin terlebih dahulu
                        agar ketersediaan stok dapat dipastikan.
                    </li>
                </ul>

            </div>

            <!-- 6 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">6</span>
                    Informasi Musim dan Cuaca
                </h3>

                <p>
                    Kondisi musim dan cuaca dapat memengaruhi kebutuhan
                    perlengkapan serta keselamatan selama pendakian.
                </p>

                <ul>
                    <li>
                        Musim hujan di Indonesia umumnya berlangsung sekitar
                        Oktober hingga Maret, tetapi dapat berbeda setiap
                        tahun dan wilayah.
                    </li>
                    <li>
                        Musim kemarau umumnya berlangsung sekitar April
                        hingga September.
                    </li>
                    <li>
                        Pada musim hujan, pertimbangkan perlengkapan tambahan
                        seperti flysheet, ponco, rain cover carrier,
                        dan jas hujan.
                    </li>
                    <li>
                        Periksa prakiraan cuaca melalui BMKG sebelum berangkat.
                    </li>
                    <li>
                        Periksa juga informasi buka-tutup jalur pendakian
                        dari pengelola gunung atau basecamp.
                    </li>
                    <li>
                        Hindari memaksakan pendakian ketika cuaca ekstrem.
                    </li>
                </ul>

            </div>

            <!-- 7 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">7</span>
                    Pengambilan Peralatan di Store
                </h3>

                <p>
                    Datanglah ke lokasi store sesuai jadwal pengambilan
                    yang telah disepakati.
                </p>

                <ul>
                    <li>Bawa identitas sesuai ketentuan penyewaan KELANA.</li>
                    <li>
                        Periksa kondisi dan kelengkapan barang bersama petugas
                        sebelum peralatan dibawa.
                    </li>
                    <li>
                        Pastikan tenda memiliki kelengkapan seperti flysheet,
                        frame, dan pasak.
                    </li>
                    <li>
                        Pastikan peralatan lainnya berada dalam kondisi baik.
                    </li>
                </ul>

                <p>
                    <strong>Jam operasional:</strong> 07.00-23.00
                </p>

                <p>
                    <strong>Alamat store:</strong> Tembalang, Semarang, Jawa Tengah
                </p>

            </div>

            <!-- 8 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">8</span>
                    Gunakan Peralatan dengan Tanggung Jawab
                </h3>

                <p>
                    Selama masa penyewaan, pengguna wajib menjaga peralatan
                    agar tetap dalam kondisi baik.
                </p>

                <ul>
                    <li>Gunakan peralatan sesuai fungsi dan petunjuk penggunaannya.</li>
                    <li>Jangan menjemur tenda terlalu dekat dengan api unggun.</li>
                    <li>Jangan memasak di dalam tenda karena berisiko kebakaran dan paparan gas berbahaya.</li>
                    <li>Jaga kebersihan dan kelengkapan seluruh peralatan.</li>
                    <li>
                        Kerusakan atau kehilangan akibat kelalaian akan
                        ditangani sesuai ketentuan denda yang berlaku.
                    </li>
                    <li>
                        Segera laporkan kepada admin apabila terjadi kerusakan
                        atau kendala teknis.
                    </li>
                </ul>

            </div>

            <!-- 9 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">9</span>
                    Pengembalian Peralatan
                </h3>

                <p>
                    Setelah masa penyewaan selesai, kembalikan seluruh
                    peralatan ke store sesuai jadwal yang telah ditentukan.
                </p>

                <ul>
                    <li>
                        Kembalikan peralatan sesuai tanggal dan waktu
                        yang disepakati.
                    </li>
                    <li>
                        Peralatan tidak perlu dicuci sebelum dikembalikan.
                        Namun, buang sampah dan sisa makanan dari tas maupun tenda.
                    </li>
                    <li>
                        Petugas akan memeriksa kondisi dan kelengkapan
                        barang bersama penyewa.
                    </li>
                    <li>
                        Keterlambatan pengembalian dapat dikenakan denda
                        sesuai ketentuan yang berlaku.
                    </li>
                </ul>

            </div>

            <!-- 10 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">10</span>
                    Pembayaran, Jaminan, dan Pembatalan
                </h3>

                <p>
                    Perhatikan ketentuan berikut sebelum menyelesaikan
                    pemesanan:
                </p>

                <ul>
                    <li>
                        <strong>Metode pembayaran:</strong>
                        transfer bank / e-wallet / QRIS.
                    </li>
                    <li>
                        <strong>Jaminan:</strong>
                        identitas asli yang dikembalikan saat peralatan dikembalikan dalam kondisi baik.
                    </li>
                    <li>
                        <strong>Pembatalan:</strong>
                        Bisa didiskusikan dengan Admin.
                    </li>
                </ul>

            </div>

            <!-- 11 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">11</span>
                    Tips Memilih Peralatan
                </h3>

                <p>
                    Agar kegiatan outdoor lebih nyaman, pilih perlengkapan
                    sesuai kebutuhan perjalanan.
                </p>

                <ul>
                    <li>
                        Sesuaikan sleeping bag dan jaket dengan ketinggian
                        serta suhu gunung yang akan dikunjungi.
                    </li>
                    <li>
                        Pilih ukuran carrier sekitar 30–40 liter untuk
                        pendakian tektok.
                    </li>
                    <li>
                        Untuk pendakian menginap, pertimbangkan carrier
                        sekitar 50–70 liter.
                    </li>
                    <li>
                        Sesuaikan kapasitas tenda dengan jumlah peserta.
                    </li>
                    <li>
                        Konsultasikan kebutuhan perlengkapan kepada admin
                        jika masih ragu.
                    </li>
                </ul>

            </div>

            <!-- 12 -->
            <div class="cara-sewa-card">

                <h3>
                    <span class="nomor-cara-sewa">12</span>
                    Keselamatan Pendakian
                </h3>

                <p>
                    Keselamatan merupakan hal utama dalam setiap kegiatan
                    pendakian. Pastikan Anda mempersiapkan perjalanan
                    dengan baik.
                </p>

                <ul>
                    <li>Daftarkan diri di basecamp dan patuhi aturan pengelola gunung.</li>
                    <li>Beri tahu keluarga atau teman mengenai rencana pendakian.</li>
                    <li>Bawa perlengkapan P3K pribadi.</li>
                    <li>Siapkan air minum dan logistik secukupnya.</li>
                    <li>Perhatikan kondisi fisik dan cuaca sebelum berangkat.</li>
                    <li>Jangan meninggalkan sampah di gunung dan bawa turun kembali sampah Anda.</li>
                </ul>

            </div>

        </div>

        <div class="cara-sewa-bantuan">

            <strong>Butuh Bantuan?</strong>

            <p>
                Jika masih memiliki pertanyaan mengenai penyewaan,
                pemilihan perlengkapan, atau ketersediaan barang,
                silakan hubungi admin KELANA melalui WhatsApp.
            </p>

            <p>
                <strong>WhatsApp:</strong> +62 812-1696-4867
            </p>

        </div>

    </div>

</section>


<!-- POPUP NOTIFIKASI GLOBAL -->
<?php include "pelanggan/notifikasi_popup.php"; ?>

</body>
</html>
