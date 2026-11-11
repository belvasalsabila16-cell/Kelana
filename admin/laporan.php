
<?php
session_start();
include "../config/database.php";

// ==========================================
// 1. CEK LOGIN DAN HAK AKSES
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

if (!$dataRole || !in_array($dataRole["role"], ["admin", "pemilik"])) {
    die("Akses ditolak. Halaman ini hanya untuk admin.");
}

// ==========================================
// 2. FILTER TANGGAL BOOKING
// ==========================================
$tanggalAwal = $_GET["tanggal_awal"] ?? "";
$tanggalAkhir = $_GET["tanggal_akhir"] ?? "";

$filter = "";

if ($tanggalAwal !== "" && $tanggalAkhir !== "") {
    $awal = DateTime::createFromFormat("Y-m-d", $tanggalAwal);
    $akhir = DateTime::createFromFormat("Y-m-d", $tanggalAkhir);

    if (
        $awal && $akhir &&
        $awal->format("Y-m-d") === $tanggalAwal &&
        $akhir->format("Y-m-d") === $tanggalAkhir &&
        $tanggalAwal <= $tanggalAkhir
    ) {
        $filter = " WHERE DATE(s.tanggal_booking) BETWEEN '" .
            mysqli_real_escape_string($conn, $tanggalAwal) .
            "' AND '" .
            mysqli_real_escape_string($conn, $tanggalAkhir) . "'";
    } else {
        $tanggalAwal = "";
        $tanggalAkhir = "";
    }
} elseif ($tanggalAwal !== "" || $tanggalAkhir !== "") {
    $tanggalAwal = "";
    $tanggalAkhir = "";
}

// ==========================================
// 3. RINGKASAN LAPORAN
// ==========================================
$sqlRingkasan = "
    SELECT
        COUNT(*) AS total_booking,

        COALESCE(SUM(
            CASE
                WHEN s.status_pembayaran = 'Lunas'
                THEN s.total_harga
                ELSE 0
            END
        ), 0) AS total_pendapatan,

        COALESCE(SUM(
            CASE
                WHEN s.status_pembayaran = 'Belum Bayar'
                THEN s.total_harga
                ELSE 0
            END
        ), 0) AS belum_dibayar,

        SUM(
            CASE
                WHEN s.status = 'Dipesan' THEN 1
                ELSE 0
            END
        ) AS jumlah_dipesan,

        SUM(
            CASE
                WHEN s.status = 'Diambil' THEN 1
                ELSE 0
            END
        ) AS jumlah_diambil,

        SUM(
            CASE
                WHEN s.status = 'Dikembalikan' THEN 1
                ELSE 0
            END
        ) AS jumlah_dikembalikan,

        SUM(
            CASE
                WHEN s.status = 'Dibatalkan' THEN 1
                ELSE 0
            END
        ) AS jumlah_dibatalkan

    FROM sewa s
    $filter
";

$hasilRingkasan = mysqli_query($conn, $sqlRingkasan);
$ringkasan = mysqli_fetch_assoc($hasilRingkasan);

// ==========================================
// 4. DAFTAR TRANSAKSI
// ==========================================
$sqlTransaksi = "
    SELECT
        s.*,
        u.nama,
        u.email
    FROM sewa s
    LEFT JOIN user u ON s.id_user = u.id_user
    $filter
    ORDER BY s.tanggal_booking DESC, s.id_sewa DESC
";

$daftarTransaksi = mysqli_query($conn, $sqlTransaksi);

// ==========================================
// 5. FORMAT RUPIAH
// ==========================================
function rupiah($angka) {
    return "Rp " . number_format((float) $angka, 0, ",", ".");
}

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Kelana</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f4;
            color: #26352c;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: #244b38;
            color: white;
            position: fixed;
            padding: 28px 18px;
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
            margin: 0 12px 35px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 7px;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.aktif {
            background: #3d6a50;
        }

        .main {
            margin-left: 240px;
            padding: 35px;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 27px;
            margin-bottom: 8px;
        }

        .header p {
            color: #718078;
            font-size: 14px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 22px;
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 18px;
        }

        .filter-form {
            display: flex;
            align-items: end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-form label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .filter-form input {
            padding: 11px;
            border: 1px solid #d6ded8;
            border-radius: 7px;
            min-width: 190px;
        }

        button,
        .btn {
            padding: 11px 17px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-filter {
            background: #315d43;
            color: white;
        }

        .btn-reset {
            background: #e8ece9;
            color: #34483a;
        }

        .ringkasan {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            gap: 17px;
            margin-bottom: 22px;
        }

        .stat {
            background: white;
            padding: 22px;
            border-radius: 11px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }

        .stat p {
            color: #758179;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat h3 {
            font-size: 22px;
            color: #28523a;
            overflow-wrap: anywhere;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .status-item {
            background: #f5f8f5;
            padding: 15px;
            border-radius: 8px;
        }

        .status-item p {
            color: #718078;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .status-item h3 {
            font-size: 22px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th,
        td {
            padding: 13px 12px;
            border-bottom: 1px solid #edf0ed;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #f5f8f5;
            color: #405448;
        }

        .lunas {
            color: #287442;
            font-weight: bold;
        }

        .belum {
            color: #b36b20;
            font-weight: bold;
        }

        .kosong {
            text-align: center;
            padding: 25px;
            color: #87918a;
        }

        .catatan {
            color: #78837b;
            font-size: 12px;
            margin-top: 14px;
            line-height: 1.6;
        }

        @media(max-width: 1050px) {
            .ringkasan {
                grid-template-columns: repeat(2, 1fr);
            }

            .status-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 800px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main {
                margin-left: 0;
                padding: 20px;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">KELANA.</div>

    <a href="dashboard.php">Dashboard</a>
    <a href="barang.php">Data Barang</a>
    <a href="kategori.php">Kategori</a>
    <a href="booking.php">Data Booking</a>
    <a href="pengembalian.php">Pengembalian</a>
    <a href="faq.php">FAQ</a>
    <a href="laporan.php" class="aktif">Laporan</a>
    <a href="../index.php">Lihat Website</a>
</aside>

<main class="main">

    <div class="header">
        <h1>Laporan Penyewaan</h1>
        <p>Ringkasan transaksi dan pendapatan penyewaan Kelana.</p>
    </div>

    <!-- FILTER TANGGAL -->
    <section class="card">
        <h2>Filter Periode Booking</h2>

        <form method="GET" class="filter-form">
            <div>
                <label>Tanggal Awal</label>
                <input
                    type="date"
                    name="tanggal_awal"
                    value="<?= e($tanggalAwal) ?>"
                >
            </div>

            <div>
                <label>Tanggal Akhir</label>
                <input
                    type="date"
                    name="tanggal_akhir"
                    value="<?= e($tanggalAkhir) ?>"
                >
            </div>

            <button type="submit" class="btn-filter">
                Tampilkan
            </button>

            <a href="laporan.php" class="btn btn-reset">
                Reset
            </a>
        </form>
    </section>

    <!-- RINGKASAN -->
    <div class="ringkasan">

        <div class="stat">
            <p>Total Booking</p>
            <h3>
                <?= number_format((int) $ringkasan["total_booking"], 0, ",", ".") ?>
            </h3>
        </div>

        <div class="stat">
            <p>Total Pendapatan (Lunas)</p>
            <h3><?= rupiah($ringkasan["total_pendapatan"]) ?></h3>
        </div>

        <div class="stat">
            <p>Total Belum Dibayar</p>
            <h3><?= rupiah($ringkasan["belum_dibayar"]) ?></h3>
        </div>

        <div class="stat">
            <p>Booking Dikembalikan</p>
            <h3>
                <?= number_format((int) $ringkasan["jumlah_dikembalikan"], 0, ",", ".") ?>
            </h3>
        </div>

    </div>

    <!-- STATUS BOOKING -->
    <section class="card">
        <h2>Status Penyewaan</h2>

        <div class="status-grid">
            <div class="status-item">
                <p>Dipesan</p>
                <h3><?= (int) $ringkasan["jumlah_dipesan"] ?></h3>
            </div>

            <div class="status-item">
                <p>Sedang Disewa</p>
                <h3><?= (int) $ringkasan["jumlah_diambil"] ?></h3>
            </div>

            <div class="status-item">
                <p>Dikembalikan</p>
                <h3><?= (int) $ringkasan["jumlah_dikembalikan"] ?></h3>
            </div>

            <div class="status-item">
                <p>Dibatalkan</p>
                <h3><?= (int) $ringkasan["jumlah_dibatalkan"] ?></h3>
            </div>
        </div>
    </section>

    <!-- RINCIAN TRANSAKSI -->
    <section class="card">
        <h2>Rincian Transaksi</h2>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Booking</th>
                        <th>Masa Sewa</th>
                        <th>Total Harga</th>
                        <th>Pembayaran</th>
                        <th>Status Sewa</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (mysqli_num_rows($daftarTransaksi) > 0): ?>
                        <?php $no = 1; ?>

                        <?php while ($row = mysqli_fetch_assoc($daftarTransaksi)): ?>
                            <tr>
                                <td><?= $no++ ?></td>

                                <td><?= e($row["kode_sewa"]) ?></td>

                                <td>
                                    <?= e($row["nama"] ?? "Pelanggan") ?>
                                    <br>
                                    <small><?= e($row["email"] ?? "") ?></small>
                                </td>

                                <td>
                                    <?= !empty($row["tanggal_booking"])
                                        ? date("d-m-Y", strtotime($row["tanggal_booking"]))
                                        : "-" ?>
                                </td>

                                <td>
                                    <?= date("d-m-Y", strtotime($row["tanggal_mulai"])) ?>
                                    s.d.
                                    <?= date("d-m-Y", strtotime($row["tanggal_selesai"])) ?>
                                </td>

                                <td><?= rupiah($row["total_harga"]) ?></td>

                                <td class="<?= $row["status_pembayaran"] === "Lunas" ? "lunas" : "belum" ?>">
                                    <?= e($row["status_pembayaran"]) ?>
                                </td>

                                <td><?= e($row["status"]) ?></td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="kosong">
                                Belum ada transaksi pada periode ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="catatan">
            * Periode filter menggunakan tanggal booking.
            Total pendapatan hanya menghitung transaksi dengan status pembayaran Lunas.
            Transaksi yang dibatalkan tetap ditampilkan dalam rincian dan jumlah booking.
        </p>
    </section>

</main>

</body>
</html>
