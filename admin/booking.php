
<?php
session_start();
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
// 2. TOKEN KEAMANAN FORM
// ==========================================

if (empty($_SESSION["csrf_booking"])) {
    $_SESSION["csrf_booking"] = bin2hex(random_bytes(32));
}

$pesan = "";

// ==========================================
// 3. PROSES HAPUS NOTIFIKASI ADMIN
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["hapus_notifikasi"])
) {

    $idNotifikasi = (int) ($_POST["id_notifikasi"] ?? 0);
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_booking"], $token)) {

        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif ($idNotifikasi <= 0) {

        $pesan = "Notifikasi tidak valid.";

    } else {

        $stmtHapus = mysqli_prepare(
            $conn,
            "DELETE FROM notifikasi_admin
             WHERE id_notifikasi = ?"
        );

        if ($stmtHapus) {

            mysqli_stmt_bind_param(
                $stmtHapus,
                "i",
                $idNotifikasi
            );

            if (mysqli_stmt_execute($stmtHapus)) {

                mysqli_stmt_close($stmtHapus);

                header("Location: booking.php?pesan=notif_dihapus");
                exit;

            } else {

                $pesan = "Gagal menghapus notifikasi.";
                mysqli_stmt_close($stmtHapus);

            }

        } else {

            $pesan = "Gagal menyiapkan penghapusan notifikasi.";

        }
    }
}

// ==========================================
// 4. PROSES PERUBAHAN STATUS BOOKING
// DAN PEMBAYARAN
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["simpan_status"])
) {

    $idSewa = (int) ($_POST["id_sewa"] ?? 0);
    $status = $_POST["status"] ?? "";
    $pembayaran = $_POST["status_pembayaran"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    $daftarStatus = [
        "Dipesan",
        "Diambil",
        "Dibatalkan"
    ];

    $daftarPembayaran = [
        "Belum Bayar",
        "Lunas"
    ];

    if (!hash_equals($_SESSION["csrf_booking"], $token)) {

        $pesan = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } elseif (
        $idSewa <= 0 ||
        !in_array($pembayaran, $daftarPembayaran, true)
    ) {

        $pesan = "Data booking atau pembayaran tidak valid.";

    } else {

        // Ambil data booking sebelumnya
        $stmtCek = mysqli_prepare($conn, "
            SELECT
                id_user,
                kode_sewa,
                status,
                status_pembayaran
            FROM sewa
            WHERE id_sewa = ?
        ");

        mysqli_stmt_bind_param($stmtCek, "i", $idSewa);
        mysqli_stmt_execute($stmtCek);

        $hasilCek = mysqli_stmt_get_result($stmtCek);
        $bookingLama = mysqli_fetch_assoc($hasilCek);

        mysqli_stmt_close($stmtCek);

        if (!$bookingLama) {

            $pesan = "Data booking tidak ditemukan.";

        } else {

            // Status booking yang sudah dikembalikan tidak boleh diubah
            if ($bookingLama["status"] === "Dikembalikan") {

                $statusFinal = "Dikembalikan";

            } elseif (in_array($status, $daftarStatus, true)) {

                $statusFinal = $status;

            } else {

                $statusFinal = "";
                $pesan = "Pilihan status tidak valid.";
            }

            if ($pesan === "") {

                $idPelanggan = (int) $bookingLama["id_user"];
                $kodeBooking = $bookingLama["kode_sewa"];

                $statusLama = $bookingLama["status"];
                $pembayaranLama = $bookingLama["status_pembayaran"];

                mysqli_begin_transaction($conn);

                try {

                    // ==========================================
                    // UPDATE STATUS BOOKING DAN PEMBAYARAN
                    // ==========================================

                    $stmtUpdate = mysqli_prepare($conn, "
                        UPDATE sewa
                        SET status = ?, status_pembayaran = ?
                        WHERE id_sewa = ?
                    ");

                    if (!$stmtUpdate) {
                        throw new Exception(
                            "Gagal menyiapkan perubahan booking."
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmtUpdate,
                        "ssi",
                        $statusFinal,
                        $pembayaran,
                        $idSewa
                    );

                    if (!mysqli_stmt_execute($stmtUpdate)) {
                        throw new Exception(
                            "Gagal memperbarui booking."
                        );
                    }

                    mysqli_stmt_close($stmtUpdate);

                    // ==========================================
                    // SIAPKAN PESAN NOTIFIKASI
                    // ==========================================

                    $daftarPesan = [];

                    if ($statusLama !== $statusFinal) {

                        if ($statusFinal === "Diambil") {

                            $daftarPesan[] =
                                "Booking " . $kodeBooking .
                                ": barang sudah diambil. Selamat menikmati penyewaanmu!";

                        } elseif ($statusFinal === "Dibatalkan") {

                            $daftarPesan[] =
                                "Booking " . $kodeBooking .
                                " telah dibatalkan.";

                        } elseif ($statusFinal === "Dipesan") {

                            $daftarPesan[] =
                                "Booking " . $kodeBooking .
                                " telah diperbarui menjadi Dipesan.";
                        }
                    }

                    // Notifikasi pembayaran lunas
                    if (
                        $pembayaranLama !== $pembayaran
                        && $pembayaran === "Lunas"
                    ) {

                        $daftarPesan[] =
                            "Pembayaran booking " . $kodeBooking .
                            " telah dikonfirmasi LUNAS. Terima kasih!";
                    }

                    // ==========================================
                    // SIMPAN NOTIFIKASI KE PELANGGAN
                    // ==========================================

                    if (!empty($daftarPesan)) {

                        $stmtNotif = mysqli_prepare($conn, "
                            INSERT INTO notifikasi_pelanggan
                            (id_user, id_sewa, pesan)
                            VALUES (?, ?, ?)
                        ");

                        if (!$stmtNotif) {
                            throw new Exception(
                                "Gagal menyiapkan notifikasi pelanggan."
                            );
                        }

                        foreach ($daftarPesan as $isiPesan) {

                            mysqli_stmt_bind_param(
                                $stmtNotif,
                                "iis",
                                $idPelanggan,
                                $idSewa,
                                $isiPesan
                            );

                            if (!mysqli_stmt_execute($stmtNotif)) {
                                throw new Exception(
                                    "Gagal menyimpan notifikasi pelanggan."
                                );
                            }
                        }

                        mysqli_stmt_close($stmtNotif);
                    }

                    // ==========================================
                    // SIMPAN TRANSAKSI
                    // ==========================================

                    mysqli_commit($conn);

                    header("Location: booking.php?pesan=berhasil");
                    exit;

                } catch (Throwable $e) {

                    mysqli_rollback($conn);

                    $pesan = "Gagal menyimpan perubahan: " .
                        $e->getMessage();
                }
            }
        }
    }
}

// ==========================================
// 5. AMBIL NOTIFIKASI BOOKING BARU
// ==========================================

$daftarNotifikasi = [];

$queryNotifikasi = mysqli_query($conn, "
    SELECT id_notifikasi, pesan, tanggal_notifikasi
    FROM notifikasi_admin
    WHERE dibaca = 0
    ORDER BY tanggal_notifikasi DESC
");

if ($queryNotifikasi) {

    while ($notifikasi = mysqli_fetch_assoc($queryNotifikasi)) {
        $daftarNotifikasi[] = $notifikasi;
    }
}

$jumlahNotifikasi = count($daftarNotifikasi);

// ==========================================
// 6. AMBIL DATA BOOKING DAN PELANGGAN
// BOOKING SELESAI DAN LUNAS DISEMBUNYIKAN
// ==========================================

$queryBooking = mysqli_query(
    $conn,
    "SELECT s.*, u.nama, u.email, u.no_hp
     FROM sewa s
     JOIN user u ON s.id_user = u.id_user
     WHERE NOT (
         s.status_pembayaran = 'Lunas'
         AND s.status IN ('Dibatalkan', 'Dikembalikan')
     )
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

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

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

        .badge-notifikasi {
            display: inline-block;
            background: #e05252;
            color: white;
            padding: 3px 7px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 5px;
        }

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

        .card,
        .notifikasi-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card {
            overflow-x: auto;
        }

        .card h2,
        .notifikasi-card h2 {
            color: #26352d;
            font-size: 19px;
            margin-bottom: 20px;
        }

        .notifikasi-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f1f5ef;
            border-left: 4px solid #31583c;
            border-radius: 7px;
        }

        .notifikasi-info {
            flex: 1;
            min-width: 0;
        }

        .notifikasi-info strong {
            display: block;
            color: #294b39;
            font-size: 14px;
            line-height: 1.6;
        }

        .notifikasi-info p {
            margin-top: 7px;
            font-size: 12px;
            color: #77847b;
        }

        .notifikasi-kosong {
            color: #849087;
            font-size: 14px;
        }

        .btn-hapus-notif {
            flex-shrink: 0;
            width: 29px;
            height: 29px;
            padding: 0;
            border: none;
            border-radius: 50%;
            background: #fce8e8;
            color: #bd3e3e;
            font-size: 21px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-hapus-notif:hover {
            background: #bd3e3e;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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

        .barang {
            line-height: 1.8;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            background: #edf1ed;
            color: #31583c;
        }

        .form-status {
            min-width: 150px;
        }

        .form-status label {
            display: block;
            color: #526357;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #d9e0da;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 12px;
            background: white;
        }

        button {
            background: #31583c;
            color: white;
            border: none;
            padding: 9px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        button:hover {
            background: #456e4f;
        }

        .catatan {
            font-size: 11px;
            color: #77847b;
            margin-bottom: 8px;
        }

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

            .card,
            .notifikasi-card {
                padding: 18px;
            }

            .notifikasi-item {
                padding: 12px;
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

        <a href="booking.php" class="aktif">
            Data Booking

            <?php if ($jumlahNotifikasi > 0) { ?>
                <span class="badge-notifikasi">
                    <?= $jumlahNotifikasi ?>
                </span>
            <?php } ?>

        </a>

        <a href="pengembalian.php">Pengembalian</a>

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

            <h1>Data Booking</h1>

            <p>
                Kelola pesanan dan pembayaran penyewaan pelanggan.
            </p>

        </div>

        <!-- PESAN -->

        <?php if ($pesan !== "") { ?>

            <div class="pesan pesan-error">
                <?= htmlspecialchars($pesan) ?>
            </div>

        <?php } ?>

        <?php if ($pesanUrl === "berhasil") { ?>

            <div class="pesan">
                Status booking dan pembayaran berhasil diperbarui.
            </div>

        <?php } elseif ($pesanUrl === "notif_dihapus") { ?>

            <div class="pesan">
                Notifikasi berhasil dihapus.
            </div>

        <?php } ?>

        <!-- NOTIFIKASI BOOKING BARU -->

        <div class="notifikasi-card">

            <h2>Notifikasi Booking Baru</h2>

            <?php if ($jumlahNotifikasi > 0) { ?>

                <?php foreach ($daftarNotifikasi as $notifikasi) { ?>

                    <div class="notifikasi-item">

                        <div class="notifikasi-info">

                            <strong>
                                <?= htmlspecialchars($notifikasi["pesan"]) ?>
                            </strong>

                            <p>
                                <?= date(
                                    "d-m-Y H:i",
                                    strtotime($notifikasi["tanggal_notifikasi"])
                                ) ?>
                            </p>

                        </div>

                        <form
                            method="POST"
                            onsubmit="return confirm('Yakin ingin menghapus notifikasi ini?')"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION["csrf_booking"]) ?>"
                            >

                            <input
                                type="hidden"
                                name="id_notifikasi"
                                value="<?= (int) $notifikasi["id_notifikasi"] ?>"
                            >

                            <button
                                type="submit"
                                name="hapus_notifikasi"
                                class="btn-hapus-notif"
                                title="Hapus notifikasi"
                                aria-label="Hapus notifikasi"
                            >
                                &times;
                            </button>

                        </form>

                    </div>

                <?php } ?>

            <?php } else { ?>

                <p class="notifikasi-kosong">
                    Belum ada booking baru.
                </p>

            <?php } ?>

        </div>

        <!-- DAFTAR BOOKING YANG MASIH PERLU DITAMPILKAN -->

        <div class="card">

            <h2>Daftar Booking</h2>

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

                if ($queryBooking && mysqli_num_rows($queryBooking) > 0) {

                    while ($booking = mysqli_fetch_assoc($queryBooking)) {

                        $id = (int) $booking["id_sewa"];

                        $queryDetail = mysqli_query(
                            $conn,
                            "SELECT d.jumlah, b.nama_barang
                             FROM detail_sewa d
                             JOIN barang b
                             ON d.id_barang = b.id_barang
                             WHERE d.id_sewa = $id"
                        );

                        $sudahDikembalikan =
                            $booking["status"] === "Dikembalikan";
                ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>

                        <td class="kode">
                            <?= htmlspecialchars($booking["kode_sewa"]) ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars($booking["nama"]) ?>
                            </strong>

                            <br>

                            <?= htmlspecialchars($booking["email"]) ?>

                            <br>

                            <?= htmlspecialchars($booking["no_hp"] ?? "-") ?>

                        </td>

                        <td class="barang">

                            <?php if ($queryDetail) { ?>

                                <?php while ($detail = mysqli_fetch_assoc($queryDetail)) { ?>

                                    <?= htmlspecialchars($detail["nama_barang"]) ?>
                                    (<?= (int) $detail["jumlah"] ?> buah)

                                    <br>

                                <?php } ?>

                            <?php } ?>

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

                            Rp<?= number_format(
                                $booking["total_harga"],
                                0,
                                ",",
                                "."
                            ) ?>

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

                            <form method="POST" class="form-status">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION["csrf_booking"]) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id_sewa"
                                    value="<?= (int) $booking["id_sewa"] ?>"
                                >

                                <?php if (!$sudahDikembalikan) { ?>

                                    <label>Status Penyewaan</label>

                                    <select name="status">

                                        <?php
                                        $opsiStatus = [
                                            "Dipesan",
                                            "Diambil",
                                            "Dibatalkan"
                                        ];

                                        foreach ($opsiStatus as $opsi) {
                                        ?>

                                            <option
                                                value="<?= htmlspecialchars($opsi) ?>"
                                                <?= $booking["status"] === $opsi ? "selected" : "" ?>
                                            >
                                                <?= htmlspecialchars($opsi) ?>
                                            </option>

                                        <?php } ?>

                                    </select>

                                <?php } else { ?>

                                    <p class="catatan">
                                        Status penyewaan sudah selesai.
                                    </p>

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="Dikembalikan"
                                    >

                                <?php } ?>

                                <label>Pembayaran</label>

                                <select name="status_pembayaran">

                                    <option
                                        value="Belum Bayar"
                                        <?= $booking["status_pembayaran"] === "Belum Bayar" ? "selected" : "" ?>
                                    >
                                        Belum Bayar
                                    </option>

                                    <option
                                        value="Lunas"
                                        <?= $booking["status_pembayaran"] === "Lunas" ? "selected" : "" ?>
                                    >
                                        Lunas
                                    </option>

                                </select>

                                <button
                                    type="submit"
                                    name="simpan_status"
                                    onclick="return confirm('Simpan perubahan booking ini?')"
                                >
                                    Simpan
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php
                    }

                } else {
                ?>

                    <tr>

                        <td colspan="9" class="kosong">
                            Tidak ada booking yang perlu diproses.
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
