
<?php
session_start();
include "../config/database.php";

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION["id_user"]) || !isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

// Ambil data akun dari database
$id_user = (int) $_SESSION["id_user"];

$queryUser = mysqli_query($conn, "
    SELECT nama, role FROM user
    WHERE id_user = $id_user
");

$user = mysqli_fetch_assoc($queryUser);

// Tolak pelanggan dan pemilik
if (!$user || $user["role"] !== "admin") {
    header("Location: ../index.php");
    exit;
}

// Statistik barang
$queryBarang = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM barang
");
$totalBarang = mysqli_fetch_assoc($queryBarang)["total"];

// Statistik booking
$queryBooking = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM sewa
    WHERE status = 'Dipesan'
");
$bookingBaru = mysqli_fetch_assoc($queryBooking)["total"];

// Penyewaan aktif
$queryAktif = mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM sewa
    WHERE status = 'Diambil'
");
$sewaAktif = mysqli_fetch_assoc($queryAktif)["total"];

// Total pendapatan dari pembayaran lunas
$queryPendapatan = mysqli_query($conn, "
    SELECT COALESCE(SUM(total_harga + denda), 0) AS total
    FROM sewa
    WHERE status_pembayaran = 'Lunas'
");
$totalPendapatan = mysqli_fetch_assoc($queryPendapatan)["total"];

// Booking terbaru
$queryTerbaru = mysqli_query($conn, "
    SELECT s.*, u.nama
    FROM sewa s
    JOIN user u ON s.id_user = u.id_user
    ORDER BY s.tanggal_booking DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7f3;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 245px;
            background: #31583c;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .sidebar h2 {
            text-align: center;
            margin: 0 0 8px;
            font-size: 25px;
            letter-spacing: 2px;
        }

        .sidebar .subtitle {
            text-align: center;
            font-size: 12px;
            color: #d9e5d8;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            color: #e8efe7;
            text-decoration: none;
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #456e4f;
            color: white;
        }

        .sidebar .logout {
            margin-top: 35px;
            color: #ffd6d6;
        }

        /* Konten */
        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .topbar h1 {
            color: #31583c;
            margin: 0 0 8px;
            font-size: 27px;
        }

        .topbar p {
            margin: 0;
            color: #777;
            font-size: 14px;
        }

        .admin-info {
            background: white;
            padding: 12px 18px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            color: #31583c;
            font-size: 14px;
        }

        /* Kartu Statistik */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .stat-card p {
            color: #777;
            font-size: 13px;
            margin: 0 0 12px;
        }

        .stat-card h2 {
            color: #31583c;
            font-size: 27px;
            margin: 0;
        }

        /* Tabel */
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            overflow-x: auto;
        }

        .table-container h2 {
            color: #31583c;
            font-size: 19px;
            margin: 0 0 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        th {
            background: #f1f5ef;
            color: #31583c;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #fff2d8;
            color: #9a6b19;
            font-size: 11px;
        }

        .status.lunas {
            background: #e2f3e5;
            color: #28733a;
        }

        .status.diambil {
            background: #e3edff;
            color: #315c9b;
        }

        .empty-data {
            text-align: center;
            color: #888;
            padding: 25px;
        }

        @media (max-width: 1000px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 700px) {
            .admin-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
            }

            .sidebar a {
                display: inline-block;
                margin: 3px;
            }

            .main-content {
                padding: 22px 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-card h2 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <h2>KELANA.</h2>
        <div class="subtitle">PANEL ADMIN</div>

        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="barang.php">Data Barang</a>
        <a href="kategori.php">Kategori</a>
        <a href="booking.php">Data Booking</a>
        <a href="pengembalian.php">Pengembalian</a>
        <a href="faq.php">Kelola FAQ</a>
        <a href="laporan.php">Laporan</a>

        <a href="../index.php">Lihat Website</a>

        <a href="../auth/logout.php" class="logout"
           onclick="return confirm('Yakin ingin keluar dari akun?')">
            Keluar
        </a>

    </aside>

    <!-- Konten Dashboard -->
    <main class="main-content">

        <div class="topbar">

            <div>
                <h1>Dashboard Admin</h1>
                <p>Selamat datang di panel pengelolaan Kelana.</p>
            </div>

            <div class="admin-info">
                Admin: <strong>
                    <?php echo htmlspecialchars($user["nama"]); ?>
                </strong>
            </div>

        </div>

        <!-- Statistik -->
        <div class="stats-grid">

            <div class="stat-card">
                <p>Total Barang</p>
                <h2><?php echo $totalBarang; ?></h2>
            </div>

            <div class="stat-card">
                <p>Booking Baru</p>
                <h2><?php echo $bookingBaru; ?></h2>
            </div>

            <div class="stat-card">
                <p>Penyewaan Aktif</p>
                <h2><?php echo $sewaAktif; ?></h2>
            </div>

            <div class="stat-card">
                <p>Total Pendapatan Lunas</p>
                <h2 style="font-size:21px;">
                    Rp <?php echo number_format(
                        $totalPendapatan, 0, ',', '.'
                    ); ?>
                </h2>
            </div>

        </div>

        <!-- Booking Terbaru -->
        <div class="table-container">

            <h2>Booking Terbaru</h2>

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Sewa</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($queryTerbaru) > 0) { ?>

                        <?php $no = 1; ?>

                        <?php while ($booking = mysqli_fetch_assoc($queryTerbaru)) { ?>

                            <tr>

                                <td><?php echo $no++; ?></td>

                                <td>
                                    <?php echo htmlspecialchars($booking["kode_sewa"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($booking["nama"]); ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        'd-m-Y',
                                        strtotime($booking["tanggal_mulai"])
                                    );
                                    ?>
                                </td>

                                <td>
                                    Rp <?php echo number_format(
                                        $booking["total_harga"], 0, ',', '.'
                                    ); ?>
                                </td>

                                <td>
                                    <span class="status <?php
                                        echo $booking["status"] === "Diambil"
                                            ? "diambil" : "";
                                    ?>">
                                        <?php echo htmlspecialchars($booking["status"]); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status <?php
                                        echo $booking["status_pembayaran"] === "Lunas"
                                            ? "lunas" : "";
                                    ?>">
                                        <?php echo htmlspecialchars(
                                            $booking["status_pembayaran"]
                                        ); ?>
                                    </span>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="7" class="empty-data">
                                Belum ada data booking.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </main>

</div>

</body>
</html>
