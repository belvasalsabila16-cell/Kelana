
<?php
session_start();
include "../config/database.php";

// Pastikan hanya admin yang bisa mengakses
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

// Pastikan folder gambar tersedia
if (!is_dir($folderGambar)) {
    mkdir($folderGambar, 0777, true);
}

// Fungsi upload gambar
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

// Proses tambah, edit, dan hapus
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksi = $_POST["aksi"] ?? "";

    // TAMBAH BARANG
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
                (id_kategori, nama_barang, deskripsi, stok, harga_sewa, gambar)
                VALUES (?, ?, ?, ?, ?, ?)
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

    // EDIT BARANG
    elseif ($aksi === "edit") {

        $id_barang = (int) ($_POST["id_barang"] ?? 0);
        $id_kategori = (int) ($_POST["id_kategori"] ?? 0);
        $nama = trim($_POST["nama_barang"] ?? "");
        $deskripsi = trim($_POST["deskripsi"] ?? "");
        $stok = (int) ($_POST["stok"] ?? 0);
        $harga = (float) ($_POST["harga_sewa"] ?? 0);

        $stmtLama = mysqli_prepare($conn, "
            SELECT gambar FROM barang WHERE id_barang = ?
        ");

        mysqli_stmt_bind_param($stmtLama, "i", $id_barang);
        mysqli_stmt_execute($stmtLama);

        $dataLama = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtLama)
        );

        if (!$dataLama) {
            $pesan = "Data barang tidak ditemukan.";
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

                    if ($gambarBaru !== null && !empty($dataLama["gambar"])) {
                        $gambarLama = $folderGambar . basename($dataLama["gambar"]);

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

    // HAPUS BARANG
    elseif ($aksi === "hapus") {

        $id_barang = (int) ($_POST["id_barang"] ?? 0);

        $stmtCek = mysqli_prepare($conn, "
            SELECT gambar FROM barang WHERE id_barang = ?
        ");

        mysqli_stmt_bind_param($stmtCek, "i", $id_barang);
        mysqli_stmt_execute($stmtCek);

        $dataBarang = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtCek)
        );

        if (!$dataBarang) {
            $pesan = "Barang tidak ditemukan.";
        } else {

            // Periksa apakah barang pernah digunakan dalam booking
            $stmtDetail = mysqli_prepare($conn, "
                SELECT COUNT(*) AS total
                FROM detail_sewa
                WHERE id_barang = ?
            ");

            mysqli_stmt_bind_param($stmtDetail, "i", $id_barang);
            mysqli_stmt_execute($stmtDetail);

            $detail = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmtDetail)
            );

            if ($detail["total"] > 0) {

                $pesan = "Barang tidak bisa dihapus karena sudah tercatat dalam riwayat penyewaan.";

            } else {

                $stmtHapus = mysqli_prepare($conn, "
                    DELETE FROM barang WHERE id_barang = ?
                ");

                mysqli_stmt_bind_param($stmtHapus, "i", $id_barang);

                if (mysqli_stmt_execute($stmtHapus)) {

                    if (!empty($dataBarang["gambar"])) {
                        $fileGambar = $folderGambar . basename($dataBarang["gambar"]);

                        if (is_file($fileGambar)) {
                            unlink($fileGambar);
                        }
                    }

                    header("Location: barang.php?pesan=hapus");
                    exit;

                } else {
                    $pesan = "Barang gagal dihapus.";
                }
            }
        }
    }
}

// Pesan setelah berhasil
if (isset($_GET["pesan"])) {
    if ($_GET["pesan"] === "tambah") {
        $pesan = "Barang berhasil ditambahkan!";
    } elseif ($_GET["pesan"] === "edit") {
        $pesan = "Data barang berhasil diperbarui!";
    } elseif ($_GET["pesan"] === "hapus") {
        $pesan = "Barang berhasil dihapus!";
    }
}

// Ambil daftar kategori
$queryKategori = mysqli_query($conn, "
    SELECT * FROM kategori ORDER BY nama_kategori ASC
");

// Ambil data barang
$queryBarang = mysqli_query($conn, "
    SELECT barang.*, kategori.nama_kategori
    FROM barang
    LEFT JOIN kategori
    ON barang.id_kategori = kategori.id_kategori
    ORDER BY barang.id_barang DESC
");

// Ambil data barang untuk diedit
$dataEdit = null;

if (isset($_GET["edit"])) {

    $idEdit = (int) $_GET["edit"];

    $stmtEdit = mysqli_prepare($conn, "
        SELECT * FROM barang WHERE id_barang = ?
    ");

    mysqli_stmt_bind_param($stmtEdit, "i", $idEdit);
    mysqli_stmt_execute($stmtEdit);

    $dataEdit = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmtEdit)
    );
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
            background: #f5f7f3;
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

        .sidebar h2 {
            text-align: center;
            margin: 0 0 8px;
            letter-spacing: 2px;
        }

        .subtitle {
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
            min-width: 850px;
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
        }

        .action-buttons form {
            margin: 0;
        }

        .hint {
            color: #888;
            font-size: 12px;
            margin-top: 6px;
        }

        @media (max-width: 800px) {
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

        <h2>KELANA.</h2>
        <div class="subtitle">PANEL ADMIN</div>

        <a href="dashboard.php">Dashboard</a>
        <a href="barang.php" class="active">Data Barang</a>
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

    <!-- KONTEN -->
    <main class="main-content">

        <div class="header">
            <h1>Data Barang</h1>
            <p>Kelola perlengkapan camping yang tersedia di Kelana.</p>
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

                <input type="hidden" name="aksi"
                       value="<?php echo $dataEdit ? "edit" : "tambah"; ?>">

                <?php if ($dataEdit) { ?>
                    <input type="hidden" name="id_barang"
                           value="<?php echo $dataEdit["id_barang"]; ?>">
                <?php } ?>

                <div class="form-group">

                    <label>Nama Barang</label>

                    <input type="text"
                           name="nama_barang"
                           placeholder="Contoh: Tenda Dome 2 Orang"
                           value="<?php echo htmlspecialchars($dataEdit["nama_barang"] ?? ""); ?>"
                           required>

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

                    <textarea name="deskripsi"
                              placeholder="Masukkan deskripsi barang"><?php echo htmlspecialchars($dataEdit["deskripsi"] ?? ""); ?></textarea>

                </div>

                <div class="form-row">

                    <div class="form-group">

                        <label>Stok Barang</label>

                        <input type="number"
                               name="stok"
                               min="0"
                               value="<?php echo htmlspecialchars($dataEdit["stok"] ?? "0"); ?>"
                               required>

                    </div>

                    <div class="form-group">

                        <label>Harga Sewa per Hari (Rp)</label>

                        <input type="number"
                               name="harga_sewa"
                               min="1"
                               value="<?php echo htmlspecialchars($dataEdit["harga_sewa"] ?? ""); ?>"
                               required>

                    </div>

                </div>

                <div class="form-group">

                    <label>Foto Barang</label>

                    <input type="file"
                           name="gambar"
                           accept=".jpg,.jpeg,.png,.webp">

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
                            alt="Foto barang">

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
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($queryBarang) > 0) { ?>

                            <?php $no = 1; ?>

                            <?php while ($barang = mysqli_fetch_assoc($queryBarang)) { ?>

                                <tr>

                                    <td><?php echo $no++; ?></td>

                                    <td>

                                        <?php if (!empty($barang["gambar"])) { ?>

                                            <img
                                                src="../assets/images/<?php echo htmlspecialchars(basename($barang["gambar"])); ?>"
                                                class="product-image"
                                                alt="Foto barang">

                                        <?php } else { ?>

                                            <span class="no-image">Belum ada foto</span>

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
                                            $barang["harga_sewa"], 0, ',', '.'
                                        ); ?>
                                    </td>

                                    <td>

                                        <div class="action-buttons">

                                            <a href="barang.php?edit=<?php echo $barang["id_barang"]; ?>"
                                               class="btn btn-edit">
                                                Edit
                                            </a>

                                            <form method="POST"
                                                  onsubmit="return confirm('Yakin ingin menghapus barang ini?')">

                                                <input type="hidden"
                                                       name="aksi"
                                                       value="hapus">

                                                <input type="hidden"
                                                       name="id_barang"
                                                       value="<?php echo $barang["id_barang"]; ?>">

                                                <button type="submit"
                                                        class="btn btn-delete">
                                                    Hapus
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php } ?>

                        <?php } else { ?>

                            <tr>
                                <td colspan="7" style="text-align:center;">
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
