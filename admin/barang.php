
<?php
session_start();
include "../config/database.php";

// ==========================================
// 1. CEK HAK AKSES ADMIN
// ==========================================

if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION["id_user"];

$cekAdmin = mysqli_query($conn, "
    SELECT role FROM user WHERE id_user = $id_user
");

$dataAdmin = mysqli_fetch_assoc($cekAdmin);

if (!$dataAdmin || $dataAdmin["role"] !== "admin") {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$folderGambar = "../assets/images/";

// ==========================================
// 2. FOLDER GAMBAR
// ==========================================

if (!is_dir($folderGambar)) {
    mkdir($folderGambar, 0777, true);
}

// ==========================================
// 3. FUNGSI UPLOAD GAMBAR
// ==========================================

function uploadGambar($folderGambar) {

    if (
        !isset($_FILES["gambar"]) ||
        $_FILES["gambar"]["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($_FILES["gambar"]["error"] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($_FILES["gambar"]["size"] > 3 * 1024 * 1024) {
        return false;
    }

    $info = getimagesize($_FILES["gambar"]["tmp_name"]);

    if ($info === false) {
        return false;
    }

    $mime = $info["mime"];

    $format = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($format[$mime])) {
        return false;
    }

    $namaFile = "barang_" . uniqid() . "." . $format[$mime];

    if (move_uploaded_file(
        $_FILES["gambar"]["tmp_name"],
        $folderGambar . $namaFile
    )) {
        return $namaFile;
    }

    return false;
}

// ==========================================
// 4. PROSES TAMBAH, EDIT, NONAKTIFKAN,
//    DAN AKTIFKAN BARANG
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksi = $_POST["aksi"] ?? "";

    // ======================================
    // TAMBAH BARANG
    // ======================================

    if ($aksi === "tambah") {

        $id_kategori = (int) ($_POST["id_kategori"] ?? 0);
        $nama = trim($_POST["nama_barang"] ?? "");
        $deskripsi = trim($_POST["deskripsi"] ?? "");
        $stok = (int) ($_POST["stok"] ?? 0);
        $harga = (float) ($_POST["harga_sewa"] ?? 0);

        $gambar = uploadGambar($folderGambar);

        if (
            $nama === "" ||
            $id_kategori <= 0 ||
            $stok < 0 ||
            $harga <= 0
        ) {

            $pesan = "Lengkapi data barang dengan benar.";

        } elseif ($gambar === false) {

            $pesan = "Foto gagal diunggah. Gunakan JPG, PNG, atau WEBP maksimal 3 MB.";

        } else {

            $stmt = mysqli_prepare($conn, "
                INSERT INTO barang
                (
                    id_kategori,
                    nama_barang,
                    deskripsi,
                    stok,
                    harga_sewa,
                    gambar,
                    status_barang
                )
                VALUES (?, ?, ?, ?, ?, ?, 'aktif')
            ");

            mysqli_stmt_bind_param(
                $stmt,
                "issids",
                $id_kategori,
                $nama,
                $deskripsi,
                $stok,
                $harga,
                $gambar
            );

            if (mysqli_stmt_execute($stmt)) {

                header("Location: barang.php?pesan=tambah");
                exit;

            } else {

                $pesan = "Barang gagal ditambahkan.";

            }
        }
    }

    // ======================================
    // EDIT BARANG
    // ======================================

    elseif ($aksi === "edit") {

        $id_barang = (int) ($_POST["id_barang"] ?? 0);
        $id_kategori = (int) ($_POST["id_kategori"] ?? 0);
        $nama = trim($_POST["nama_barang"] ?? "");
        $deskripsi = trim($_POST["deskripsi"] ?? "");
        $stok = (int) ($_POST["stok"] ?? 0);
        $harga = (float) ($_POST["harga_sewa"] ?? 0);

        $stmtLama = mysqli_prepare($conn, "
            SELECT gambar, status_barang
            FROM barang
            WHERE id_barang = ?
        ");

        mysqli_stmt_bind_param($stmtLama, "i", $id_barang);
        mysqli_stmt_execute($stmtLama);

        $dataLama = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtLama)
        );

        if (!$dataLama) {

            $pesan = "Data barang tidak ditemukan.";

        } elseif ($dataLama["status_barang"] !== "aktif") {

            $pesan = "Barang nonaktif tidak dapat diedit. Aktifkan barang terlebih dahulu.";

        } elseif (
            $nama === "" ||
            $id_kategori <= 0 ||
            $stok < 0 ||
            $harga <= 0
        ) {

            $pesan = "Lengkapi data barang dengan benar.";

        } else {

            $gambarBaru = uploadGambar($folderGambar);

            if ($gambarBaru === false) {

                $pesan = "Foto gagal diunggah. Gunakan JPG, PNG, atau WEBP maksimal 3 MB.";

            } else {

                $gambar = $gambarBaru ?? $dataLama["gambar"];

                $stmt = mysqli_prepare($conn, "
                    UPDATE barang SET
                        id_kategori = ?,
                        nama_barang = ?,
                        deskripsi = ?,
                        stok = ?,
                        harga_sewa = ?,
                        gambar = ?
                    WHERE id_barang = ?
                    AND status_barang = 'aktif'
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "issidsi",
                    $id_kategori,
                    $nama,
                    $deskripsi,
                    $stok,
                    $harga,
                    $gambar,
                    $id_barang
                );

                if (mysqli_stmt_execute($stmt)) {

                    // Hapus foto lama jika diganti
                    if (
                        $gambarBaru !== null &&
                        !empty($dataLama["gambar"])
                    ) {

                        $gambarLama = $folderGambar .
                            basename($dataLama["gambar"]);

                        if (is_file($gambarLama)) {
                            unlink($gambarLama);
                        }
                    }

                    header("Location: barang.php?pesan=edit");
                    exit;

                } else {

                    $pesan = "Data barang gagal diperbarui.";

                }
            }
        }
    }

    // ======================================
    // NONAKTIFKAN BARANG
    // ======================================

    elseif ($aksi === "hapus") {

        $id_barang = (int) ($_POST["id_barang"] ?? 0);

        $stmtCek = mysqli_prepare($conn, "
            SELECT id_barang, status_barang
            FROM barang
            WHERE id_barang = ?
        ");

        mysqli_stmt_bind_param($stmtCek, "i", $id_barang);
        mysqli_stmt_execute($stmtCek);

        $dataBarang = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtCek)
        );

        if (!$dataBarang) {

            $pesan = "Barang tidak ditemukan.";

        } elseif ($dataBarang["status_barang"] === "nonaktif") {

            $pesan = "Barang sudah berstatus nonaktif.";

        } else {

            $stmtNonaktif = mysqli_prepare($conn, "
                UPDATE barang
                SET status_barang = 'nonaktif'
                WHERE id_barang = ?
            ");

            mysqli_stmt_bind_param(
                $stmtNonaktif,
                "i",
                $id_barang
            );

            if (mysqli_stmt_execute($stmtNonaktif)) {

                header("Location: barang.php?pesan=nonaktif");
                exit;

            } else {

                $pesan = "Barang gagal dinonaktifkan.";

            }
        }
    }

    // ======================================
    // AKTIFKAN KEMBALI BARANG
    // ======================================

    elseif ($aksi === "aktifkan") {

        $id_barang = (int) ($_POST["id_barang"] ?? 0);

        $stmtCek = mysqli_prepare($conn, "
            SELECT id_barang, status_barang
            FROM barang
            WHERE id_barang = ?
        ");

        mysqli_stmt_bind_param($stmtCek, "i", $id_barang);
        mysqli_stmt_execute($stmtCek);

        $dataBarang = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtCek)
        );

        if (!$dataBarang) {

            $pesan = "Barang tidak ditemukan.";

        } elseif ($dataBarang["status_barang"] === "aktif") {

            $pesan = "Barang sudah berstatus aktif.";

        } else {

            $stmtAktif = mysqli_prepare($conn, "
                UPDATE barang
                SET status_barang = 'aktif'
                WHERE id_barang = ?
            ");

            mysqli_stmt_bind_param(
                $stmtAktif,
                "i",
                $id_barang
            );

            if (mysqli_stmt_execute($stmtAktif)) {

                header("Location: barang.php?pesan=aktif");
                exit;

            } else {

                $pesan = "Barang gagal diaktifkan.";

            }
        }
    }
}

// ==========================================
// 5. PESAN SETELAH BERHASIL
// ==========================================

if (isset($_GET["pesan"])) {

    if ($_GET["pesan"] === "tambah") {
        $pesan = "Barang berhasil ditambahkan!";

    } elseif ($_GET["pesan"] === "edit") {
        $pesan = "Data barang berhasil diperbarui!";

    } elseif ($_GET["pesan"] === "nonaktif") {
        $pesan = "Barang berhasil dinonaktifkan dan tidak ditampilkan di katalog pelanggan!";

    } elseif ($_GET["pesan"] === "aktif") {
        $pesan = "Barang berhasil diaktifkan kembali!";

    }
}

// ==========================================
// 6. AMBIL DATA KATEGORI
// ==========================================

$queryKategori = mysqli_query($conn, "
    SELECT * FROM kategori
    ORDER BY nama_kategori ASC
");

// ==========================================
// 7. AMBIL SEMUA DATA BARANG
// TERMASUK BARANG NONAKTIF
// ==========================================

$queryBarang = mysqli_query($conn, "
    SELECT barang.*, kategori.nama_kategori
    FROM barang
    LEFT JOIN kategori
    ON barang.id_kategori = kategori.id_kategori
    ORDER BY
        CASE
            WHEN barang.status_barang = 'aktif' THEN 0
            ELSE 1
        END,
        barang.id_barang DESC
");

// ==========================================
// 8. AMBIL DATA BARANG UNTUK DIEDIT
// ==========================================

$dataEdit = null;
$idBarangSedangDiedit = 0;

if (isset($_GET["edit"])) {

    $idEdit = (int) $_GET["edit"];

    $stmtEdit = mysqli_prepare($conn, "
        SELECT *
        FROM barang
        WHERE id_barang = ?
        AND status_barang = 'aktif'
    ");

    mysqli_stmt_bind_param($stmtEdit, "i", $idEdit);
    mysqli_stmt_execute($stmtEdit);

    $dataEdit = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmtEdit)
    );

    if ($dataEdit) {
        $idBarangSedangDiedit = (int) $dataEdit["id_barang"];
    } else {
        $pesan = "Barang tidak ditemukan atau sudah berstatus nonaktif.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Barang - Admin Kelana</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: transparent;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 245px;
            background: #31583c;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .logo-link {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 8px;
            text-decoration: none;
        }

        .logo-link img {
            width: 190px;
            height: 90px;
            object-fit: contain;
            display: block;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
            color: #d9e5d8;
            margin-bottom: 35px;
        }

        .sidebar a:not(.logo-link) {
            display: block;
            color: #e8efe7;
            text-decoration: none;
            padding: 13px 15px;
            border-radius: 7px;
            margin-bottom: 7px;
            font-size: 14px;
        }

        .sidebar a:not(.logo-link):hover,
        .sidebar a.active {
            background: #456e4f;
            color: white;
        }

        .sidebar .logout {
            margin-top: 35px;
            color: #ffd6d6;
        }

        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            color: #31583c;
            margin: 0 0 8px;
        }

        .header p {
            color: #777;
            margin: 0;
        }

        .form-container,
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            margin-bottom: 25px;
        }

        .form-container h2,
        .table-container h2 {
            color: #31583c;
            margin-top: 0;
            font-size: 19px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 85px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #31583c;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-primary {
            background: #31583c;
            color: white;
        }

        .btn-edit {
            background: #d69b45;
            color: white;
        }

        .btn-delete {
            background: #a33;
            color: white;
        }

        .btn-activate {
            background: #31583c;
            color: white;
        }

        .btn-cancel {
            background: #777;
            color: white;
        }

        .btn:hover {
            opacity: .85;
        }

        .message {
            padding: 13px 16px;
            border-radius: 7px;
            background: #e5f3e6;
            color: #31583c;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
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

        .product-image {
            width: 65px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            background: #f1f1f1;
        }

        .no-image {
            color: #999;
            font-size: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-buttons form {
            margin: 0;
        }

        .hint {
            color: #888;
            font-size: 12px;
            margin-top: 6px;
        }

        /* PENANDA BARANG YANG SEDANG DIEDIT */

        tr.editing-row {
            background: #e5f1e6;
        }

        tr.editing-row td {
            border-bottom: 1px solid #c5ddc7;
        }

        /* PENANDA STATUS BARANG */

        .status-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .3px;
        }

        .status-aktif {
            background: #e1f3e4;
            color: #26723a;
        }

        .status-nonaktif {
            background: #fde5e5;
            color: #a32626;
        }

        tr.inactive-row {
            background: #fff5f5;
        }

        tr.inactive-row td {
            color: #777;
        }

        tr.inactive-row .product-image {
            opacity: .65;
        }

        .status-info {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            font-size: 12px;
            color: #666;
        }

        .status-info span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 800px) {

            .admin-layout {
                display: block;
            }

            .sidebar {
                width: 100%;
            }

            .logo-link img {
                width: 150px;
                height: 75px;
            }

            .sidebar a:not(.logo-link) {
                display: inline-block;
                margin: 3px;
            }

            .main-content {
                padding: 22px 15px;
            }
        }

        @media (max-width: 550px) {

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <a href="../index.php" class="logo-link">
            <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
        </a>

        <div class="subtitle">PANEL ADMIN</div>

        <a href="dashboard.php">Dashboard</a>

        <a href="barang.php" class="active">Data Barang</a>

        <a href="kategori.php">Kategori</a>

        <a href="member.php">Manajemen Member</a>

        <a href="booking.php">Data Booking</a>

        <a href="pengembalian.php">Pengembalian</a>

        <a href="faq.php">Kelola FAQ</a>

        <a href="laporan.php">Laporan</a>

        <a href="../index.php">Lihat Website</a>

        <a href="../auth/logout.php"
           class="logout"
           onclick="return confirm('Yakin ingin keluar dari akun?')">
            Keluar
        </a>

    </aside>

    <!-- KONTEN -->

    <main class="main-content">

        <div class="header">

            <h1>Data Barang</h1>

            <p>
                Kelola perlengkapan camping yang tersedia di Kelana.
            </p>

        </div>

        <?php if ($pesan !== "") { ?>

            <div class="message">
                <?php echo htmlspecialchars($pesan); ?>
            </div>

        <?php } ?>

        <!-- FORM TAMBAH / EDIT -->

        <div class="form-container">

            <h2>
                <?php echo $dataEdit ? "Edit Barang" : "Tambah Barang Baru"; ?>
            </h2>

            <form method="POST" enctype="multipart/form-data">

                <input
                    type="hidden"
                    name="aksi"
                    value="<?php echo $dataEdit ? "edit" : "tambah"; ?>"
                >

                <?php if ($dataEdit) { ?>

                    <input
                        type="hidden"
                        name="id_barang"
                        value="<?php echo $dataEdit["id_barang"]; ?>"
                    >

                <?php } ?>

                <div class="form-group">

                    <label>Nama Barang</label>

                    <input
                        type="text"
                        name="nama_barang"
                        placeholder="Contoh: Tenda Dome 2 Orang"
                        value="<?php echo htmlspecialchars($dataEdit["nama_barang"] ?? ""); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Kategori</label>

                    <select name="id_kategori" required>

                        <option value="">-- Pilih Kategori --</option>

                        <?php while ($kategori = mysqli_fetch_assoc($queryKategori)) { ?>

                            <option
                                value="<?php echo $kategori["id_kategori"]; ?>"
                                <?php
                                if (
                                    $dataEdit &&
                                    $dataEdit["id_kategori"] == $kategori["id_kategori"]
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >
                                <?php echo htmlspecialchars($kategori["nama_kategori"]); ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Deskripsi Barang</label>

                    <textarea
                        name="deskripsi"
                        placeholder="Masukkan deskripsi barang"
                    ><?php echo htmlspecialchars($dataEdit["deskripsi"] ?? ""); ?></textarea>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label>Stok Barang</label>

                        <input
                            type="number"
                            name="stok"
                            min="0"
                            value="<?php echo htmlspecialchars($dataEdit["stok"] ?? "0"); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>Harga Sewa per Hari (Rp)</label>

                        <input
                            type="number"
                            name="harga_sewa"
                            min="1"
                            value="<?php echo htmlspecialchars($dataEdit["harga_sewa"] ?? ""); ?>"
                            required
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label>Foto Barang</label>

                    <input
                        type="file"
                        name="gambar"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <p class="hint">

                        Format JPG, PNG, atau WEBP. Maksimal 3 MB.

                        <?php if ($dataEdit) { ?>
                            Kosongkan jika tidak ingin mengganti foto.
                        <?php } ?>

                    </p>

                    <?php if ($dataEdit && !empty($dataEdit["gambar"])) { ?>

                        <p>Foto saat ini:</p>

                        <img
                            src="../assets/images/<?php echo htmlspecialchars(basename($dataEdit["gambar"])); ?>"
                            class="product-image"
                            alt="Foto barang"
                        >

                    <?php } ?>

                </div>

                <button type="submit" class="btn btn-primary">

                    <?php echo $dataEdit ? "Simpan Perubahan" : "Tambah Barang"; ?>

                </button>

                <?php if ($dataEdit) { ?>

                    <a href="barang.php" class="btn btn-cancel">
                        Batal
                    </a>

                <?php } ?>

            </form>

        </div>

        <!-- TABEL BARANG -->

        <div class="table-container">

            <h2>Daftar Barang Kelana</h2>

            <div class="status-info">

                <span>
                    <span class="status-badge status-aktif">AKTIF</span>
                    Ditampilkan di katalog pelanggan
                </span>

                <span>
                    <span class="status-badge status-nonaktif">NONAKTIF</span>
                    Tidak ditampilkan di katalog pelanggan
                </span>

            </div>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>Foto</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Harga per Hari</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($queryBarang) > 0) { ?>

                            <?php $no = 1; ?>

                            <?php while ($barang = mysqli_fetch_assoc($queryBarang)) { ?>

                                <?php
                                $sedangDiedit = (
                                    $idBarangSedangDiedit > 0 &&
                                    (int) $barang["id_barang"] === $idBarangSedangDiedit
                                );

                                $barangNonaktif = (
                                    $barang["status_barang"] === "nonaktif"
                                );
                                ?>

                                <tr class="<?php
                                    echo $sedangDiedit
                                        ? 'editing-row'
                                        : ($barangNonaktif ? 'inactive-row' : '');
                                ?>">

                                    <td>
                                        <?php echo $no++; ?>
                                    </td>

                                    <td>

                                        <?php if (!empty($barang["gambar"])) { ?>

                                            <img
                                                src="../assets/images/<?php echo htmlspecialchars(basename($barang["gambar"])); ?>"
                                                class="product-image"
                                                alt="Foto barang"
                                            >

                                        <?php } else { ?>

                                            <span class="no-image">
                                                Belum ada foto
                                            </span>

                                        <?php } ?>

                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($barang["nama_barang"]); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($barang["nama_kategori"] ?? "-"); ?>
                                    </td>

                                    <td>
                                        <?php echo $barang["stok"]; ?> unit
                                    </td>

                                    <td>
                                        Rp <?php echo number_format(
                                            $barang["harga_sewa"],
                                            0,
                                            ',',
                                            '.'
                                        ); ?>
                                    </td>

                                    <td>

                                        <?php if ($barangNonaktif) { ?>

                                            <span class="status-badge status-nonaktif">
                                                NONAKTIF
                                            </span>

                                        <?php } else { ?>

                                            <span class="status-badge status-aktif">
                                                AKTIF
                                            </span>

                                        <?php } ?>

                                    </td>

                                    <td>

                                        <div class="action-buttons">

                                            <?php if (!$barangNonaktif) { ?>

                                                <a
                                                    href="barang.php?edit=<?php echo $barang["id_barang"]; ?>"
                                                    class="btn btn-edit"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin menonaktifkan barang ini? Barang akan disembunyikan dari katalog pelanggan, tetapi riwayat penyewaan tetap tersimpan.')"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="aksi"
                                                        value="hapus"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_barang"
                                                        value="<?php echo $barang["id_barang"]; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-delete"
                                                    >
                                                        Nonaktifkan
                                                    </button>

                                                </form>

                                            <?php } else { ?>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Yakin ingin mengaktifkan kembali barang ini? Barang akan muncul kembali di katalog pelanggan.')"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="aksi"
                                                        value="aktifkan"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_barang"
                                                        value="<?php echo $barang["id_barang"]; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-activate"
                                                    >
                                                        Aktifkan
                                                    </button>

                                                </form>

                                            <?php } ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php } ?>

                        <?php } else { ?>

                            <tr>

                                <td colspan="8" style="text-align:center;">
                                    Belum ada data barang.
                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>
</html>
