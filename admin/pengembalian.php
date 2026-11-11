
<?php
session_start();

date_default_timezone_set("Asia/Jakarta");

include "../config/database.php";

// ==========================================
// 1. CEK LOGIN DAN HAK AKSES ADMIN
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

if (!$dataRole || $dataRole["role"] !== "admin") {
    echo "Akses ditolak. Halaman ini hanya untuk admin.";
    exit;
}

// ==========================================
// 2. PENGATURAN DENDA DAN TANGGAL
// ==========================================

$pesan = "";
$tanggalHariIni = date("Y-m-d");

$queryPengaturan = mysqli_query(
    $conn,
    "SELECT denda_per_hari FROM pengaturan LIMIT 1"
);

$pengaturan = mysqli_fetch_assoc($queryPengaturan);

$dendaPerHari = (float) ($pengaturan["denda_per_hari"] ?? 10000);

$toleransiHari = 2;

// ==========================================
// 3. PROSES PENGEMBALIAN
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["proses_pengembalian"])
) {

    $idSewa = (int) ($_POST["id_sewa"] ?? 0);
    $tanggalKembali = $_POST["tanggal_pengembalian"] ?? "";
    $kondisi = trim($_POST["kondisi_pengembalian"] ?? "");

    $tanggalValid = DateTime::createFromFormat(
        "!Y-m-d",
        $tanggalKembali
    );

    if (
        !$tanggalValid ||
        $tanggalValid->format("Y-m-d") !== $tanggalKembali
    ) {

        $pesan = "Tanggal pengembalian tidak valid.";

    } elseif ($kondisi === "") {

        $pesan = "Kondisi barang wajib diisi.";

    } else {

        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT tanggal_mulai, tanggal_selesai, status
             FROM sewa
             WHERE id_sewa = ?"
        );

        mysqli_stmt_bind_param($stmtCek, "i", $idSewa);
        mysqli_stmt_execute($stmtCek);

        $dataSewa = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtCek)
        );

        mysqli_stmt_close($stmtCek);

        if (!$dataSewa) {

            $pesan = "Data penyewaan tidak ditemukan.";

        } elseif ($dataSewa["status"] !== "Diambil") {

            $pesan = "Barang hanya dapat dikembalikan jika status penyewaan Diambil.";

        } elseif ($tanggalKembali < $dataSewa["tanggal_mulai"]) {

            $pesan = "Tanggal pengembalian tidak boleh sebelum tanggal mulai sewa.";

        } elseif ($tanggalKembali > $tanggalHariIni) {

            $pesan = "Tanggal pengembalian tidak boleh melebihi tanggal hari ini.";

        } else {

            // Hitung batas toleransi 2 hari
            $tanggalSelesai = new DateTime(
                $dataSewa["tanggal_selesai"]
            );

            $batasToleransi = clone $tanggalSelesai;

            $batasToleransi->modify(
                "+" . $toleransiHari . " days"
            );

            $tanggalAktual = new DateTime($tanggalKembali);

            // Hitung keterlambatan
            $hariTerlambat = 0;

            if ($tanggalAktual > $batasToleransi) {

                $selisih = $batasToleransi->diff(
                    $tanggalAktual
                );

                $hariTerlambat = (int) $selisih->days;
            }

            // Hitung denda
            $denda = $hariTerlambat * $dendaPerHari;

            // Simpan pengembalian
            $stmtUpdate = mysqli_prepare(
                $conn,
                "UPDATE sewa
                 SET tanggal_pengembalian = ?,
                     kondisi_pengembalian = ?,
                     denda = ?,
                     status = 'Dikembalikan'
                 WHERE id_sewa = ?
                 AND status = 'Diambil'"
            );

            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ssdi",
                $tanggalKembali,
                $kondisi,
                $denda,
                $idSewa
            );

            if (
                mysqli_stmt_execute($stmtUpdate) &&
                mysqli_stmt_affected_rows($stmtUpdate) > 0
            ) {

                mysqli_stmt_close($stmtUpdate);

                header("Location: pengembalian.php?pesan=berhasil");
                exit;

            } else {

                $pesan = "Gagal menyimpan pengembalian. Silakan periksa kembali data booking.";

                mysqli_stmt_close($stmtUpdate);
            }
        }
    }
}

// ==========================================
// 4. AMBIL DAFTAR PENYEWAAN AKTIF
// ==========================================

$queryPengembalian = mysqli_query(
    $conn,
    "SELECT s.*, u.nama, u.no_hp
     FROM sewa s
     JOIN user u ON s.id_user = u.id_user
     WHERE s.status = 'Diambil'
     ORDER BY s.tanggal_selesai ASC"
);

// ==========================================
// 5. AMBIL RIWAYAT PENGEMBALIAN
// ==========================================

$queryRiwayat = mysqli_query(
    $conn,
    "SELECT s.*, u.nama
     FROM sewa s
     JOIN user u ON s.id_user = u.id_user
     WHERE s.status = 'Dikembalikan'
     ORDER BY s.tanggal_pengembalian DESC"
);

$pesanUrl = $_GET["pesan"] ?? "";

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengembalian - Kelana</title>

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
            margin-bottom: 25px;
            overflow-x: auto;
        }

        .card h2 {
            font-size: 19px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
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

        label {
            display: block;
            font-weight: bold;
            font-size: 13px;
            margin: 12px 0 7px;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d9e0da;
            border-radius: 6px;
            font-size: 13px;
        }

        input[type="date"] {
            cursor: pointer;
            color: #26352d;
            background: #ffffff;
            min-height: 40px;
            color-scheme: light;
        }

        input[type="date"]:disabled {
            opacity: 1;
            background: #ffffff;
        }

        textarea {
            min-height: 65px;
            resize: vertical;
        }

        button {
            background: #294b39;
            color: white;
            border: none;
            padding: 10px 13px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 12px;
            font-size: 12px;
        }

        button:hover {
            background: #42634f;
        }

        .form-kembali {
            min-width: 190px;
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

        .info {
            color: #77847b;
            font-size: 12px;
            margin-top: 8px;
            margin-bottom: 15px;
            line-height: 1.6;
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
        <a href="booking.php">Data Booking</a>
        <a href="pengembalian.php" class="aktif">Pengembalian</a>
        <a href="faq.php">FAQ</a>
        <a href="laporan.php">Laporan</a>
        <a href="../index.php">Lihat Website</a>
        <a href="../auth/logout.php">Keluar</a>

    </div>

    <div class="main">

        <div class="header">

            <h1>Pengembalian Barang</h1>

            <p>
                Catat pengembalian barang dan hitung denda keterlambatan pelanggan.
            </p>

        </div>

        <?php if ($pesan !== "") { ?>

            <div class="pesan">
                <?= htmlspecialchars($pesan) ?>
            </div>

        <?php } ?>

        <?php if ($pesanUrl === "berhasil") { ?>

            <div class="pesan">
                Pengembalian berhasil dicatat dan denda telah dihitung.
            </div>

        <?php } ?>

        <div class="card">

            <h2>Daftar Penyewaan Aktif</h2>

            <p class="info">

                Toleransi pengembalian:
                <?= $toleransiHari ?> hari setelah tanggal selesai sewa.

                Denda:
                Rp<?= number_format($dendaPerHari, 0, ",", ".") ?>
                per hari keterlambatan.

            </p>

            <table>

                <thead>

                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Pelanggan</th>
                        <th>Tanggal Sewa</th>
                        <th>Batas Toleransi</th>
                        <th>Proses Pengembalian</th>
                    </tr>

                </thead>

                <tbody>

                    <?php

                    $no = 1;

                    if (mysqli_num_rows($queryPengembalian) > 0) {

                        while ($booking = mysqli_fetch_assoc($queryPengembalian)) {

                            $batas = new DateTime(
                                $booking["tanggal_selesai"]
                            );

                            $batas->modify(
                                "+" . $toleransiHari . " days"
                            );

                            $tanggalMulai = $booking["tanggal_mulai"];

                            // Pastikan batas kalender tidak saling bertabrakan
                            $tanggalMaksimal = $tanggalHariIni;

                            if ($tanggalMaksimal < $tanggalMulai) {
                                $tanggalMaksimal = $tanggalMulai;
                            }

                    ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td class="kode">
                                <?= htmlspecialchars($booking["kode_sewa"]) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($booking["nama"]) ?>
                                </strong>

                                <br>

                                <?= htmlspecialchars($booking["no_hp"] ?? "-") ?>

                            </td>

                            <td>

                                <?= date(
                                    "d-m-Y",
                                    strtotime($booking["tanggal_mulai"])
                                ) ?>

                                s.d.

                                <?= date(
                                    "d-m-Y",
                                    strtotime($booking["tanggal_selesai"])
                                ) ?>

                            </td>

                            <td>
                                <?= $batas->format("d-m-Y") ?>
                            </td>

                            <td>

                                <form method="POST" class="form-kembali">

                                    <input
                                        type="hidden"
                                        name="id_sewa"
                                        value="<?= (int) $booking["id_sewa"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="proses_pengembalian"
                                        value="1"
                                    >

                                    <label>Tanggal Pengembalian</label>

                                    <input
                                        type="date"
                                        name="tanggal_pengembalian"
                                        value="<?= htmlspecialchars($tanggalMaksimal) ?>"
                                        min="<?= htmlspecialchars($tanggalMulai) ?>"
                                        max="<?= htmlspecialchars($tanggalMaksimal) ?>"
                                        required
                                    >

                                    <label>Kondisi Barang</label>

                                    <textarea
                                        name="kondisi_pengembalian"
                                        placeholder="Contoh: Barang lengkap dan dalam kondisi baik."
                                        required
                                    ></textarea>

                                    <button type="submit">
                                        Proses Pengembalian
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php

                        }

                    } else {

                    ?>

                        <tr>

                            <td colspan="6" class="kosong">
                                Belum ada penyewaan aktif yang menunggu pengembalian.
                            </td>

                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

        <div class="card">

            <h2>Riwayat Pengembalian</h2>

            <table>

                <thead>

                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Pelanggan</th>
                        <th>Tanggal Selesai</th>
                        <th>Tanggal Dikembalikan</th>
                        <th>Kondisi Barang</th>
                        <th>Hari Terlambat</th>
                        <th>Denda</th>
                    </tr>

                </thead>

                <tbody>

                    <?php

                    $no = 1;

                    if (mysqli_num_rows($queryRiwayat) > 0) {

                        while ($riwayat = mysqli_fetch_assoc($queryRiwayat)) {

                            $batas = new DateTime(
                                $riwayat["tanggal_selesai"]
                            );

                            $batas->modify(
                                "+" . $toleransiHari . " days"
                            );

                            $aktual = new DateTime(
                                $riwayat["tanggal_pengembalian"]
                            );

                            $hariTerlambat = 0;

                            if ($aktual > $batas) {

                                $hariTerlambat = (int) $batas->diff(
                                    $aktual
                                )->days;

                            }

                    ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td class="kode">
                                <?= htmlspecialchars($riwayat["kode_sewa"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($riwayat["nama"]) ?>
                            </td>

                            <td>
                                <?= date(
                                    "d-m-Y",
                                    strtotime($riwayat["tanggal_selesai"])
                                ) ?>
                            </td>

                            <td>
                                <?= date(
                                    "d-m-Y",
                                    strtotime($riwayat["tanggal_pengembalian"])
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $riwayat["kondisi_pengembalian"] ?? "-"
                                ) ?>
                            </td>

                            <td>
                                <?= $hariTerlambat ?> hari
                            </td>

                            <td>
                                Rp<?= number_format(
                                    $riwayat["denda"],
                                    0,
                                    ",",
                                    "."
                                ) ?>
                            </td>

                        </tr>

                    <?php

                        }

                    } else {

                    ?>

                        <tr>

                            <td colspan="8" class="kosong">
                                Belum ada riwayat pengembalian.
                            </td>

                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</body>

</html>
