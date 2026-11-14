
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
        $awalAman = mysqli_real_escape_string($conn, $tanggalAwal);
        $akhirAman = mysqli_real_escape_string($conn, $tanggalAkhir);

        $filter = " WHERE DATE(s.tanggal_booking) BETWEEN '$awalAman' AND '$akhirAman'";
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
                AND s.status != 'Dibatalkan'
                THEN s.total_harga
                ELSE 0
            END
        ), 0) AS total_pendapatan,

        COALESCE(SUM(
            CASE
                WHEN s.status_pembayaran = 'Belum Bayar'
                AND s.status != 'Dibatalkan'
                THEN s.total_harga
                ELSE 0
            END
        ), 0) AS belum_dibayar,

        SUM(
            CASE WHEN s.status = 'Dipesan' THEN 1 ELSE 0 END
        ) AS jumlah_dipesan,

        SUM(
            CASE WHEN s.status = 'Diambil' THEN 1 ELSE 0 END
        ) AS jumlah_diambil,

        SUM(
            CASE WHEN s.status = 'Dikembalikan' THEN 1 ELSE 0 END
        ) AS jumlah_dikembalikan,

        SUM(
            CASE WHEN s.status = 'Dibatalkan' THEN 1 ELSE 0 END
        ) AS jumlah_dibatalkan

    FROM sewa s
    $filter
";

$hasilRingkasan = mysqli_query($conn, $sqlRingkasan);
$ringkasan = mysqli_fetch_assoc($hasilRingkasan);

// ==========================================
// 4. DAFTAR TRANSAKSI DAN RINCIAN DISKON
// ==========================================
$sqlTransaksi = "
    SELECT
        s.*,
        u.nama,
        u.email,

        COALESCE(detail.total_sebelum_diskon, 0) AS total_sebelum_diskon,

        GREATEST(
            COALESCE(detail.total_sebelum_diskon, 0) - s.total_harga,
            0
        ) AS nominal_diskon

    FROM sewa s

    LEFT JOIN user u
        ON s.id_user = u.id_user

    LEFT JOIN (
        SELECT
            id_sewa,
            SUM(subtotal) AS total_sebelum_diskon
        FROM detail_sewa
        GROUP BY id_sewa
    ) detail
        ON s.id_sewa = detail.id_sewa

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

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: transparent;
            color: #26352c;
        }

        /* SIDEBAR */
        .sidebar {
            width: 245px;
            height: 100vh;
            background: #31583c;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 28px 18px;
            overflow-y: auto;
            z-index: 100;
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo a {
            display: inline-block;
        }

        .logo img {
            width: 190px;
            height: 90px;
            object-fit: contain;
            display: block;
        }

        .sidebar a.menu-link {
            display: block;
            color: #e8efe7;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 7px;
            margin-bottom: 7px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a.menu-link:hover,
        .sidebar a.menu-link.aktif {
            background: #456e4f;
            color: white;
        }

        /* KONTEN UTAMA */
        .main {
            margin-left: 245px;
            padding: 35px;
            min-height: 100vh;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 27px;
            margin-bottom: 8px;
            color: #26352d;
        }

        .header p {
            color: #77847b;
            font-size: 14px;
        }

        /* CARD */
        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            margin-bottom: 22px;
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 18px;
            color: #26352d;
        }

        /* FILTER */
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
            color: #405448;
        }

        .filter-form input {
            padding: 11px;
            border: 1px solid #d6ded8;
            border-radius: 7px;
            min-width: 190px;
            color: #26352c;
        }

        .filter-form input:focus {
            outline: none;
            border-color: #456e4f;
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
            background: #31583c;
            color: white;
        }

        .btn-filter:hover {
            background: #456e4f;
        }

        .btn-reset {
            background: #e8ece9;
            color: #34483a;
        }

        .btn-reset:hover {
            background: #dce4dd;
        }

        /* RINGKASAN */
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .stat p {
            color: #77847b;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat h3 {
            font-size: 22px;
            color: #31583c;
            overflow-wrap: anywhere;
        }

        /* STATUS BOOKING */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .status-item {
            background: #f1f5ef;
            padding: 15px;
            border-radius: 8px;
        }

        .status-item p {
            color: #77847b;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .status-item h3 {
            font-size: 22px;
            color: #31583c;
        }

        /* TABEL */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1250px;
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
            background: #f1f5ef;
            color: #31583c;
        }

        td {
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

        .diskon {
            color: #287442;
            font-weight: bold;
        }

        .kosong {
            text-align: center;
            padding: 25px;
            color: #87918a;
        }

        .catatan {
            color: #77847b;
            font-size: 12px;
            margin-top: 14px;
            line-height: 1.6;
        }

        /* RESPONSIVE */
        @media (max-width: 1050px) {
            .ringkasan {
                grid-template-columns: repeat(2, 1fr);
            }

            .status-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 18px;
            }

            .logo {
                margin-bottom: 15px;
            }

            .logo img {
                width: 150px;
                height: 75px;
            }

            .main {
                margin-left: 0;
                padding: 20px;
                overflow-x: auto;
            }
        }

        @media (max-width: 500px) {
            .ringkasan,
            .status-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-form input {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <a href="../index.php">
            <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
        </a>
    </div>

    <a href="dashboard.php" class="menu-link">Dashboard</a>
    <a href="barang.php" class="menu-link">Data Barang</a>
    <a href="kategori.php" class="menu-link">Kategori</a>
    <a href="member.php" class="menu-link">Manajemen Member</a>
    <a href="booking.php" class="menu-link">Data Booking</a>
    <a href="pengembalian.php" class="menu-link">Pengembalian</a>
    <a href="faq.php" class="menu-link">FAQ</a>
    <a href="laporan.php" class="menu-link aktif">Laporan</a>
    <a href="../index.php" class="menu-link">Lihat Website</a>

</aside>

<main class="main">

    <div class="header">
        <h1>Laporan Penyewaan</h1>
        <p>Ringkasan transaksi, diskon, dan pendapatan penyewaan Kelana.</p>
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

        <div style="overflow-x: auto;">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Booking</th>
                        <th>Masa Sewa</th>
                        <th>Total Sebelum Diskon</th>
                        <th>Diskon</th>
                        <th>Total Setelah Diskon</th>
                        <th>Pembayaran</th>
                        <th>Status Sewa</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($daftarTransaksi && mysqli_num_rows($daftarTransaksi) > 0): ?>

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
                                    <?= !empty($row["tanggal_mulai"])
                                        ? date("d-m-Y", strtotime($row["tanggal_mulai"]))
                                        : "-" ?>
                                    s.d.
                                    <?= !empty($row["tanggal_selesai"])
                                        ? date("d-m-Y", strtotime($row["tanggal_selesai"]))
                                        : "-" ?>
                                </td>

                                <td>
                                    <?= rupiah($row["total_sebelum_diskon"]) ?>
                                </td>

                                <td class="diskon">
                                    <?= rupiah($row["nominal_diskon"]) ?>
                                </td>

                                <td>
                                    <strong><?= rupiah($row["total_harga"]) ?></strong>
                                </td>

                                <td class="<?= $row["status_pembayaran"] === "Lunas" ? "lunas" : "belum" ?>">
                                    <?= e($row["status_pembayaran"]) ?>
                                </td>

                                <td><?= e($row["status"]) ?></td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="10" class="kosong">
                                Belum ada transaksi pada periode ini.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="catatan">
            * Periode filter menggunakan tanggal booking.
            Total pendapatan hanya menghitung transaksi berstatus Lunas dan tidak dibatalkan.
            Total sebelum diskon dihitung dari penjumlahan subtotal barang.
            Nominal diskon dihitung dari selisih total sebelum diskon dengan total pembayaran tersimpan.
            Transaksi yang dibatalkan tetap ditampilkan dalam rincian dan jumlah booking.
        </p>

    </section>

</main>

</body>
</html>
