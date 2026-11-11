
<?php
session_start();
include "../config/database.php";

// Pastikan pelanggan sudah login
if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$idUser = (int) $_SESSION["id_user"];

// Ambil seluruh riwayat booking milik pelanggan
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

<nav class="navbar">

    <h2>KELANA.</h2>

    <div class="menu">
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
        <a href="keranjang.php">Keranjang</a>
        <a href="tentang.php">Tentang Kami</a>
        <a href="faq.php">FAQ</a>
    </div>

    <a href="profil.php" class="login">Profil Saya</a>

</nav>

<section class="history-container">

    <div class="history-header">
        <h1>Riwayat Penyewaan</h1>
        <p>Lihat seluruh transaksi penyewaan perlengkapan camping kamu di Kelana.</p>
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
                                $sewa["total_harga"], 0, ",", "."
                            ); ?>
                        </strong>
                    </div>

                    <div>
                        <p>Status Pembayaran</p>
                        <span class="history-status <?php echo $classPembayaran; ?>">
                            <?php echo htmlspecialchars($sewa["status_pembayaran"]); ?>
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

</body>
</html>
