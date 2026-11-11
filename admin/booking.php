
<?php
session_start();
include "../config/database.php";

// Periksa login dan hak akses admin
if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$idUser = (int) $_SESSION["id_user"];
$cekRole = mysqli_query($conn, "SELECT role FROM user WHERE id_user = $idUser");
$dataRole = mysqli_fetch_assoc($cekRole);

if (!$dataRole || $dataRole["role"] !== "admin") {
    echo "Akses ditolak. Halaman ini hanya untuk admin.";
    exit;
}

$pesan = "";

// Proses perubahan status booking dan pembayaran
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["simpan_status"])) {
    $idSewa = (int) $_POST["id_sewa"];
    $status = $_POST["status"] ?? "";
    $pembayaran = $_POST["status_pembayaran"] ?? "";

    $daftarStatus = ["Dipesan", "Diambil", "Dibatalkan"];
    $daftarPembayaran = ["Belum Bayar", "Lunas"];

    if (
        !in_array($status, $daftarStatus, true) ||
        !in_array($pembayaran, $daftarPembayaran, true)
    ) {
        $pesan = "Pilihan status tidak valid.";
    } else {
        // Ambil status booking saat ini
        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT status FROM sewa WHERE id_sewa = ?"
        );
        mysqli_stmt_bind_param($stmtCek, "i", $idSewa);
        mysqli_stmt_execute($stmtCek);
        $bookingLama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCek));

        if (!$bookingLama) {
            $pesan = "Data booking tidak ditemukan.";
        } elseif ($bookingLama["status"] === "Dikembalikan") {
            $pesan = "Booking yang sudah dikembalikan tidak dapat diubah di halaman ini.";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE sewa
                 SET status = ?, status_pembayaran = ?
                 WHERE id_sewa = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $status,
                $pembayaran,
                $idSewa
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: booking.php?pesan=berhasil");
                exit;
            } else {
                $pesan = "Gagal memperbarui booking.";
            }
        }
    }
}

// Ambil data booking dan pelanggan
$queryBooking = mysqli_query(
    $conn,
    "SELECT s.*, u.nama, u.email, u.no_hp
     FROM sewa s
     JOIN user u ON s.id_user = u.id_user
     ORDER BY s.tanggal_booking DESC"
);

$pesanUrl = $_GET["pesan"] ?? "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Booking - Kelana</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f6f3;
            color: #26352d;
        }

        .sidebar {
            width: 240px;
            height: 100vh;
            background: #203c30;
            color: white;
            position: fixed;
            padding: 30px 20px;
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
            margin-bottom: 35px;
            letter-spacing: 2px;
        }

        .sidebar a {
            display: block;
            color: #e5eee8;
            text-decoration: none;
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.aktif {
            background: #42634f;
            color: white;
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
            color: #77847b;
            font-size: 14px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
            overflow-x: auto;
        }

        .card h2 {
            font-size: 19px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #edf0ed;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #f4f7f4;
            color: #526357;
        }

        .kode {
            font-weight: bold;
            color: #294b39;
        }

        .barang {
            line-height: 1.8;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            background: #edf1ed;
        }

        .form-status {
            min-width: 150px;
        }

        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #d9e0da;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        button {
            background: #294b39;
            color: white;
            border: none;
            padding: 9px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        button:hover {
            background: #42634f;
        }

        .pesan {
            padding: 13px;
            border-radius: 7px;
            background: #e5f2e8;
            color: #285c38;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .kosong {
            text-align: center;
            padding: 25px;
            color: #849087;
        }

        .detail-link {
            color: #315b42;
            text-decoration: none;
            font-weight: bold;
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
            }
        }
    </style>
</head>

<body>

<div class="sidebar">
    <div class="logo">KELANA.</div>

    <a href="dashboard.php">Dashboard</a>
    <a href="barang.php">Data Barang</a>
    <a href="kategori.php">Kategori</a>
    <a href="booking.php" class="aktif">Data Booking</a>
    <a href="pengembalian.php">Pengembalian</a>
    <a href="faq.php">FAQ</a>
    <a href="laporan.php">Laporan</a>
    <a href="../index.php">Lihat Website</a>
    <a href="../auth/logout.php">Keluar</a>
</div>

<div class="main">

    <div class="header">
        <h1>Data Booking</h1>
        <p>Kelola pesanan dan pembayaran penyewaan pelanggan.</p>
    </div>

    <?php if ($pesan !== "") { ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <?php if ($pesanUrl === "berhasil") { ?>
        <div class="pesan">
            Status booking berhasil diperbarui.
        </div>
    <?php } ?>

    <div class="card">
        <h2>Daftar Seluruh Booking</h2>

        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Kode Booking</th>
                    <th>Pelanggan</th>
                    <th>Barang Disewa</th>
                    <th>Tanggal Sewa</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                    <th>Kelola</th>
                </tr>
            </thead>

            <tbody>

            <?php
            $no = 1;

            if (mysqli_num_rows($queryBooking) > 0) {
                while ($booking = mysqli_fetch_assoc($queryBooking)) {

                    $id = (int) $booking["id_sewa"];

                    $queryDetail = mysqli_query(
                        $conn,
                        "SELECT d.jumlah, b.nama_barang
                         FROM detail_sewa d
                         JOIN barang b ON d.id_barang = b.id_barang
                         WHERE d.id_sewa = $id"
                    );
            ?>

                <tr>
                    <td><?= $no++ ?></td>

                    <td class="kode">
                        <?= htmlspecialchars($booking["kode_sewa"]) ?>
                    </td>

                    <td>
                        <strong><?= htmlspecialchars($booking["nama"]) ?></strong><br>
                        <?= htmlspecialchars($booking["email"]) ?><br>
                        <?= htmlspecialchars($booking["no_hp"] ?? "-") ?>
                    </td>

                    <td class="barang">
                        <?php while ($detail = mysqli_fetch_assoc($queryDetail)) { ?>
                            <?= htmlspecialchars($detail["nama_barang"]) ?>
                            (<?= (int) $detail["jumlah"] ?> buah)<br>
                        <?php } ?>
                    </td>

                    <td>
                        <?= date("d-m-Y", strtotime($booking["tanggal_mulai"])) ?>
                        s.d.
                        <?= date("d-m-Y", strtotime($booking["tanggal_selesai"])) ?>
                    </td>

                    <td>
                        Rp<?= number_format($booking["total_harga"], 0, ",", ".") ?>
                    </td>

                    <td>
                        <span class="status">
                            <?= htmlspecialchars($booking["status"]) ?>
                        </span>
                    </td>

                    <td>
                        <span class="status">
                            <?= htmlspecialchars($booking["status_pembayaran"]) ?>
                        </span>
                    </td>

                    <td>
                        <?php if ($booking["status"] !== "Dikembalikan") { ?>
                            <form method="POST" class="form-status">

                                <input type="hidden" name="id_sewa"
                                       value="<?= $booking["id_sewa"] ?>">

                                <label>Status</label>
                                <select name="status">
                                    <?php
                                    $opsiStatus = [
                                        "Dipesan",
                                        "Diambil",
                                        "Dibatalkan"
                                    ];

                                    foreach ($opsiStatus as $opsi) {
                                    ?>
                                        <option value="<?= $opsi ?>"
                                            <?= $booking["status"] === $opsi ? "selected" : "" ?>>
                                            <?= $opsi ?>
                                        </option>
                                    <?php } ?>
                                </select>

                                <label>Pembayaran</label>
                                <select name="status_pembayaran">
                                    <option value="Belum Bayar"
                                        <?= $booking["status_pembayaran"] === "Belum Bayar" ? "selected" : "" ?>>
                                        Belum Bayar
                                    </option>

                                    <option value="Lunas"
                                        <?= $booking["status_pembayaran"] === "Lunas" ? "selected" : "" ?>>
                                        Lunas
                                    </option>
                                </select>

                                <button type="submit" name="simpan_status">
                                    Simpan
                                </button>
                            </form>
                        <?php } else { ?>
                            <span class="status">Sudah dikembalikan</span>
                        <?php } ?>
                    </td>
                </tr>

            <?php
                }
            } else {
            ?>
                <tr>
                    <td colspan="9" class="kosong">
                        Belum ada data booking.
                    </td>
                </tr>
            <?php } ?>

            </tbody>
        </table>
    </div>

</div>

</body>
</html>
