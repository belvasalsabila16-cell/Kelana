
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

        // Ambil data penyewaan dan pelanggan
        $stmtCek = mysqli_prepare(
            $conn,
            "SELECT
                id_user,
                kode_sewa,
                tanggal_mulai,
                tanggal_selesai,
                status
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

        } else {

            // ==========================================
            // HITUNG BATAS TOLERANSI 2 HARI
            // ==========================================

            $tanggalSelesai = new DateTime(
                $dataSewa["tanggal_selesai"]
            );

            $batasToleransi = clone $tanggalSelesai;

            $batasToleransi->modify(
                "+" . $toleransiHari . " days"
            );

            $tanggalAktual = new DateTime($tanggalKembali);

            // ==========================================
            // HITUNG KETERLAMBATAN
            // ==========================================

            $hariTerlambat = 0;

            if ($tanggalAktual > $batasToleransi) {

                $selisih = $batasToleransi->diff(
                    $tanggalAktual
                );

                $hariTerlambat = (int) $selisih->days;
            }

            // ==========================================
            // HITUNG DENDA
            // ==========================================

            $denda = $hariTerlambat * $dendaPerHari;

            // ==========================================
            // SIMPAN PENGEMBALIAN DAN NOTIFIKASI
            // ==========================================

            mysqli_begin_transaction($conn);

            $stmtUpdate = null;
            $stmtNotif = null;

            try {

                // Update data penyewaan
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
                    !mysqli_stmt_execute($stmtUpdate) ||
                    mysqli_stmt_affected_rows($stmtUpdate) <= 0
                ) {
                    throw new Exception(
                        "Gagal menyimpan pengembalian. Silakan periksa kembali data booking."
                    );
                }

                mysqli_stmt_close($stmtUpdate);
                $stmtUpdate = null;

                // ==========================================
                // BUAT NOTIFIKASI UNTUK PELANGGAN
                // ==========================================

                $pesanPelanggan =
                    "Pengembalian booking " .
                    $dataSewa["kode_sewa"] .
                    " berhasil diproses. Barang telah diterima admin. Terima kasih sudah menyewa di Kelana!";

                $stmtNotif = mysqli_prepare(
                    $conn,
                    "INSERT INTO notifikasi_pelanggan
                     (id_user, id_sewa, pesan)
                     VALUES (?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $stmtNotif,
                    "iis",
                    $dataSewa["id_user"],
                    $idSewa,
                    $pesanPelanggan
                );

                if (!mysqli_stmt_execute($stmtNotif)) {
                    throw new Exception(
                        "Gagal membuat notifikasi pelanggan."
                    );
                }

                mysqli_stmt_close($stmtNotif);
                $stmtNotif = null;

                // ==========================================
                // SIMPAN SEMUA PERUBAHAN
                // ==========================================

                mysqli_commit($conn);

                header("Location: pengembalian.php?pesan=berhasil");
                exit;

            } catch (Exception $e) {

                mysqli_rollback($conn);

                if ($stmtUpdate !== null) {
                    mysqli_stmt_close($stmtUpdate);
                }

                if ($stmtNotif !== null) {
                    mysqli_stmt_close($stmtNotif);
                }

                $pesan = $e->getMessage();
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
            color: #26352d;
        }

        /* ==========================================
           LAYOUT ADMIN
        ========================================== */

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* ==========================================
           SIDEBAR - MENGIKUTI DASHBOARD
        ========================================== */

        .sidebar {
            width: 245px;
            min-height: 100vh;
            background: #31583c;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .sidebar-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 8px;
        }

        .sidebar-logo a {
            display: inline-block;
            text-decoration: none;
        }

        .sidebar-logo img {
            display: block;
            width: 190px;
            height: 90px;
            object-fit: contain;
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
        .sidebar a.aktif {
            background: #456e4f;
            color: white;
        }

        .sidebar .logout {
            margin-top: 35px;
            color: #ffd6d6;
        }

        /* ==========================================
           KONTEN UTAMA
        ========================================== */

        .main {
            flex: 1;
            min-width: 0;
            padding: 35px;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            color: #26352d;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .header p {
            color: #77847b;
            font-size: 14px;
        }

        /* ==========================================
           CARD
        ========================================== */

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            overflow-x: auto;
        }

        .card h2 {
            color: #26352d;
            font-size: 19px;
            margin-bottom: 20px;
        }

        /* ==========================================
           TABEL
        ========================================== */

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #edf0ed;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #f1f5ef;
            color: #31583c;
        }

        td {
            color: #444;
        }

        tbody tr:hover {
            background: #f8fbf7;
        }

        .kode {
            font-weight: bold;
            color: #294b39;
        }

        /* ==========================================
           FORM PENGEMBALIAN
        ========================================== */

        label {
            display: block;
            font-weight: bold;
            font-size: 13px;
            margin: 12px 0 7px;
            color: #385640;
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d9e0da;
            border-radius: 6px;
            font-size: 13px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #31583c;
        }

        input[type="date"] {
            cursor: pointer;
            color: #26352d;
            background: #ffffff;
            min-height: 40px;
            color-scheme: light;
        }

        textarea {
            min-height: 65px;
            resize: vertical;
        }

        .form-kembali {
            min-width: 190px;
        }

        button {
            background: #31583c;
            color: white;
            border: none;
            padding: 10px 13px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 12px;
            font-size: 12px;
        }

        button:hover {
            background: #456e4f;
        }

        /* ==========================================
           PESAN DAN INFORMASI
        ========================================== */

        .pesan {
            padding: 13px;
            border-radius: 7px;
            background: #e5f2e8;
            color: #285c38;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .pesan-error {
            background: #fce8e8;
            color: #9b2929;
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

        /* ==========================================
           RESPONSIVE
        ========================================== */

        @media(max-width: 800px) {

            .admin-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
            }

            .sidebar-logo img {
                width: 150px;
                height: 75px;
            }

            .sidebar a {
                display: inline-block;
                margin: 3px;
            }

            .main {
                margin-left: 0;
                padding: 22px 15px;
            }

        }

        @media(max-width: 480px) {

            .card {
                padding: 18px;
            }

        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-logo">
            <a href="../index.php">
                <img
                    src="../assets/images/logo-kelana.png"
                    alt="Logo KELANA"
                >
            </a>
        </div>

        <div class="subtitle">PANEL ADMIN</div>

        <a href="dashboard.php">Dashboard</a>

        <a href="barang.php">Data Barang</a>

        <a href="kategori.php">Kategori</a>

        <a href="member.php">Manajemen Member</a>

        <a href="booking.php">Data Booking</a>

        <a href="pengembalian.php" class="aktif">Pengembalian</a>

        <a href="faq.php">Kelola FAQ</a>

        <a href="laporan.php">Laporan</a>

        <a href="../index.php">Lihat Website</a>

        <a
            href="../auth/logout.php"
            class="logout"
            onclick="return confirm('Yakin ingin keluar dari akun?')"
        >
            Keluar
        </a>

    </aside>

    <!-- KONTEN UTAMA -->

    <main class="main">

        <div class="header">

            <h1>Pengembalian Barang</h1>

            <p>
                Catat pengembalian barang dan hitung denda keterlambatan pelanggan.
            </p>

        </div>

        <?php if ($pesan !== "") { ?>

            <div class="pesan pesan-error">
                <?= htmlspecialchars($pesan) ?>
            </div>

        <?php } ?>

        <?php if ($pesanUrl === "berhasil") { ?>

            <div class="pesan">
                Pengembalian berhasil dicatat, denda telah dihitung, dan notifikasi pelanggan telah dibuat.
            </div>

        <?php } ?>

        <!-- DAFTAR PENYEWAAN AKTIF -->

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
                                        value="<?= htmlspecialchars($tanggalHariIni) ?>"
                                        required
                                    >

                                    <label>Kondisi Barang</label>

                                    <textarea
                                        name="kondisi_pengembalian"
                                        placeholder="Contoh: Barang lengkap dan dalam kondisi baik."
                                        required
                                    ></textarea>

                                    <button
                                        type="submit"
                                        onclick="return confirm('Proses pengembalian barang ini?')"
                                    >
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

        <!-- RIWAYAT PENGEMBALIAN -->

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

    </main>

</div>

</body>
</html>
