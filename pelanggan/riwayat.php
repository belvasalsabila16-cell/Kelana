
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// 1. CEK LOGIN DAN ROLE
// ==========================================

if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$idUser = (int) $_SESSION["id_user"];

$cekRole = mysqli_query(
    $conn,
    "SELECT role FROM user WHERE id_user = $idUser"
);

$dataRole = mysqli_fetch_assoc($cekRole);

// Halaman riwayat hanya untuk pelanggan
if (!$dataRole || $dataRole["role"] !== "pelanggan") {
    header("Location: ../admin/dashboard.php");
    exit;
}

// ==========================================
// 2. NAVBAR DAN JUMLAH KERANJANG
// ==========================================

$halamanAktif = basename($_SERVER["PHP_SELF"]);

$jumlahKeranjang = isset($_SESSION["keranjang"])
    ? array_sum($_SESSION["keranjang"])
    : 0;

// ==========================================
// 3. JUMLAH NOTIFIKASI BELUM DIBACA
// ==========================================

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

// ==========================================
// 4. AMBIL RIWAYAT BOOKING PELANGGAN
// ==========================================

$stmt = mysqli_prepare($conn, "
    SELECT *
    FROM sewa
    WHERE id_user = ?
    ORDER BY tanggal_booking DESC
");

mysqli_stmt_bind_param($stmt, "i", $idUser);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Penyewaan - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* BACKGROUND HALAMAN */
        body {
            background-color: transparent;
        }

        /* LOGO NAVBAR */
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

        .navbar {
            flex-wrap: wrap;
            gap: 15px;
        }

        .menu {
            flex-wrap: wrap;
        }

        .navbar .menu a.active {
            color: #31583c;
            font-weight: 700;
            position: relative;
        }

        .navbar .menu a.active::after {
            content: "";
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #31583c;
        }

        /* KELOMPOK LONCENG DAN AKUN */

        .navbar-account-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        /* NOTIFIKASI */

        .notification-link {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            color: #31583c;
            text-decoration: none;
            border-radius: 50%;
            transition: .2s;
        }

        .notification-link:hover {
            background: #f0f5f0;
        }

        .notification-link svg {
            width: 23px;
            height: 23px;
        }

        .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dc3545;
            color: white;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            border: 2px solid white;
        }

        /* DROPDOWN AKUN */

        .account-dropdown {
            position: relative;
            display: inline-block;
        }

        .account-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #31583c;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .account-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            min-width: 190px;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,.12);
            border-radius: 8px;
            padding: 8px 0;
            z-index: 1000;
        }

        .account-dropdown:hover .account-menu {
            display: block;
        }

        .account-menu a {
            display: block;
            padding: 11px 16px;
            color: #31583c;
            text-decoration: none;
            font-size: 14px;
        }

        .account-menu a:hover,
        .account-menu a.active {
            background: #f0f5f0;
            font-weight: 700;
        }

        /* RIWAYAT PENYEWAAN */

        .history-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .history-header {
            margin-bottom: 25px;
        }

        .history-header h1 {
            color: #31583c;
            margin-bottom: 8px;
        }

        .history-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }

        .history-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .history-code {
            color: #31583c;
            font-weight: bold;
            font-size: 17px;
        }

        .history-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-top: 18px;
        }

        .history-info p {
            margin: 5px 0;
            color: #777;
            font-size: 14px;
        }

        .history-info strong {
            color: #333;
        }

        .history-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #fff0d8;
            color: #9a681b;
        }

        .history-status.lunas,
        .history-status.dikembalikan {
            background: #e4f3e7;
            color: #287b48;
        }

        .history-status.dibatalkan {
            background: #fce5e5;
            color: #b43d3d;
        }

        .history-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .history-btn {
            display: inline-block;
            padding: 10px 16px;
            background: #31583c;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
        }

        .history-empty {
            background: white;
            padding: 45px 20px;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,.07);
        }

        .history-empty h3 {
            color: #31583c;
        }

        .history-empty a {
            display: inline-block;
            margin-top: 15px;
            padding: 11px 20px;
            background: #31583c;
            color: white;
            text-decoration: none;
            border-radius: 7px;
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
                margin-left: auto;
            }

        }

        @media (max-width: 600px) {

            .history-info {
                grid-template-columns: 1fr;
            }

            .history-card {
                padding: 18px;
            }

        }

    </style>

</head>

<body>

<!-- NAVBAR -->

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

    </div>

    <div class="navbar-account-group">

        <!-- LONCENG NOTIFIKASI -->

        <a href="notifikasi.php"
           class="notification-link"
           title="Notifikasi">

            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                <path d="M10 21h4"></path>
            </svg>

            <?php if ($jumlahNotifikasi > 0) { ?>

                <span class="notification-badge">
                    <?php echo $jumlahNotifikasi > 99 ? '99+' : $jumlahNotifikasi; ?>
                </span>

            <?php } ?>

        </a>

        <!-- DROPDOWN AKUN -->

        <div class="account-dropdown">

            <a href="profil.php" class="account-trigger">
                <span>Akun</span>
                <span>⌄</span>
            </a>

            <div class="account-menu">

                <a href="profil.php"
                   class="<?php echo $halamanAktif === 'profil.php' ? 'active' : ''; ?>">
                    Profil Saya
                </a>

                <a href="riwayat.php"
                   class="<?php echo $halamanAktif === 'riwayat.php' ? 'active' : ''; ?>">
                    Riwayat Penyewaan
                </a>

                <a href="../auth/logout.php">
                    Keluar
                </a>

            </div>

        </div>

    </div>

</nav>

<!-- RIWAYAT PENYEWAAN -->

<section class="history-container">

    <div class="history-header">

        <h1>Riwayat Penyewaan</h1>

        <p>
            Lihat seluruh transaksi penyewaan perlengkapan camping kamu di Kelana.
        </p>

    </div>

    <?php if (mysqli_num_rows($hasil) > 0) { ?>

        <?php while ($sewa = mysqli_fetch_assoc($hasil)) { ?>

            <?php

            $statusBooking = strtolower($sewa["status"]);
            $statusPembayaran = strtolower($sewa["status_pembayaran"]);

            $classBooking = "";

            if ($statusBooking === "dikembalikan") {
                $classBooking = "dikembalikan";
            } elseif ($statusBooking === "dibatalkan") {
                $classBooking = "dibatalkan";
            }

            $classPembayaran = ($statusPembayaran === "lunas")
                ? "lunas"
                : "";

            ?>

            <div class="history-card">

                <div class="history-top">

                    <div>

                        <div class="history-code">
                            <?php echo htmlspecialchars($sewa["kode_sewa"]); ?>
                        </div>

                        <small>
                            Booking:

                            <?php echo date(
                                "d M Y, H:i",
                                strtotime($sewa["tanggal_booking"])
                            ); ?>

                        </small>

                    </div>

                    <span class="history-status <?php echo $classBooking; ?>">

                        <?php echo htmlspecialchars($sewa["status"]); ?>

                    </span>

                </div>

                <div class="history-info">

                    <div>

                        <p>Tanggal Mulai</p>

                        <strong>
                            <?php echo date(
                                "d M Y",
                                strtotime($sewa["tanggal_mulai"])
                            ); ?>
                        </strong>

                    </div>

                    <div>

                        <p>Tanggal Selesai</p>

                        <strong>
                            <?php echo date(
                                "d M Y",
                                strtotime($sewa["tanggal_selesai"])
                            ); ?>
                        </strong>

                    </div>

                    <div>

                        <p>Total Penyewaan</p>

                        <strong>
                            Rp <?php echo number_format(
                                $sewa["total_harga"],
                                0,
                                ",",
                                "."
                            ); ?>
                        </strong>

                    </div>

                    <div>

                        <p>Status Pembayaran</p>

                        <span class="history-status <?php echo $classPembayaran; ?>">

                            <?php echo htmlspecialchars(
                                $sewa["status_pembayaran"]
                            ); ?>

                        </span>

                    </div>

                </div>

                <div class="history-actions">

                    <a
                        href="hasil_booking.php?kode=<?php echo urlencode($sewa["kode_sewa"]); ?>"
                        class="history-btn"
                    >
                        Lihat Detail Booking
                    </a>

                </div>

            </div>

        <?php } ?>

    <?php } else { ?>

        <div class="history-empty">

            <h3>Belum Ada Riwayat Penyewaan</h3>

            <p>
                Kamu belum pernah melakukan booking perlengkapan camping.
            </p>

            <a href="katalog.php">Mulai Sewa</a>

        </div>

    <?php } ?>

</section>

<?php
// Popup notifikasi pelanggan
include "notifikasi_popup.php";
?>

</body>
</html>
