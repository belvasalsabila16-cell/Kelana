
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

$queryUser = mysqli_query($conn, "
    SELECT nama, role FROM user
    WHERE id_user = $id_user
");

$user = mysqli_fetch_assoc($queryUser);

if (!$user || $user["role"] !== "admin") {
    header("Location: ../index.php");
    exit;
}

$pesan = "";

// ==========================================
// 2. FUNGSI CEK NAMA KATEGORI DUPLIKAT
// ==========================================

function cekNamaKategori($conn, $nama, $idKecuali = 0) {

    $nama = trim($nama);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_kategori
         FROM kategori
         WHERE LOWER(TRIM(nama_kategori)) = LOWER(TRIM(?))
         AND id_kategori != ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "si", $nama, $idKecuali);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $ada = mysqli_num_rows($hasil) > 0;

    mysqli_stmt_close($stmt);

    return $ada;
}

// ==========================================
// 3. TAMBAH KATEGORI
// ==========================================

if (isset($_POST["tambah"])) {

    $nama = trim($_POST["nama_kategori"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");

    if ($nama === "") {

        $pesan = "Nama kategori wajib diisi.";

    } elseif (cekNamaKategori($conn, $nama)) {

        $pesan = "Nama kategori sudah tersedia. Silakan gunakan nama lain.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO kategori (nama_kategori, deskripsi)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "ss", $nama, $deskripsi);

        if (mysqli_stmt_execute($stmt)) {

            header("Location: kategori.php?pesan=tambah");
            exit;

        } else {

            $pesan = "Gagal menambahkan kategori.";

        }

        mysqli_stmt_close($stmt);
    }
}

// ==========================================
// 4. EDIT KATEGORI
// ==========================================

if (isset($_POST["edit"])) {

    $id = (int) ($_POST["id_kategori"] ?? 0);
    $nama = trim($_POST["nama_kategori"] ?? "");
    $deskripsi = trim($_POST["deskripsi"] ?? "");

    if ($nama === "") {

        $pesan = "Nama kategori wajib diisi.";

    } elseif (cekNamaKategori($conn, $nama, $id)) {

        $pesan = "Nama kategori sudah digunakan kategori lain. Silakan gunakan nama lain.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE kategori
             SET nama_kategori = ?, deskripsi = ?
             WHERE id_kategori = ?"
        );

        mysqli_stmt_bind_param($stmt, "ssi", $nama, $deskripsi, $id);

        if (mysqli_stmt_execute($stmt)) {

            header("Location: kategori.php?pesan=edit");
            exit;

        } else {

            $pesan = "Gagal mengubah kategori.";

        }

        mysqli_stmt_close($stmt);
    }
}

// ==========================================
// 5. HAPUS KATEGORI
// ==========================================

if (isset($_POST["hapus"])) {

    $id = (int) $_POST["id_kategori"];

    $stmtCek = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS jumlah
         FROM barang
         WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($stmtCek, "i", $id);
    mysqli_stmt_execute($stmtCek);

    $hasilCek = mysqli_stmt_get_result($stmtCek);

    $jumlahBarang = mysqli_fetch_assoc($hasilCek)["jumlah"];

    mysqli_stmt_close($stmtCek);

    if ($jumlahBarang > 0) {

        header("Location: kategori.php?pesan=digunakan");
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM kategori WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {

        header("Location: kategori.php?pesan=hapus");
        exit;

    } else {

        $pesan = "Gagal menghapus kategori.";

    }

    mysqli_stmt_close($stmt);
}

// ==========================================
// 6. AMBIL DATA KATEGORI UNTUK DIEDIT
// ==========================================

$dataEdit = null;

if (isset($_GET["edit"])) {

    $idEdit = (int) $_GET["edit"];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM kategori WHERE id_kategori = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $idEdit);
    mysqli_stmt_execute($stmt);

    $dataEdit = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmt)
    );

    mysqli_stmt_close($stmt);
}

// ==========================================
// 7. AMBIL SELURUH DATA KATEGORI
// ==========================================

$queryKategori = mysqli_query(
    $conn,
    "SELECT k.*, COUNT(b.id_barang) AS jumlah_barang
     FROM kategori k
     LEFT JOIN barang b ON k.id_kategori = b.id_kategori
     GROUP BY k.id_kategori
     ORDER BY k.id_kategori ASC"
);

$pesanUrl = $_GET["pesan"] ?? "";

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Kategori - Kelana</title>

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
            color: #333;
        }

        /* LAYOUT ADMIN */

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 245px;
            background: #31583c;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
            min-height: 100vh;
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
        .sidebar a.active {
            background: #456e4f;
            color: white;
        }

        .sidebar .logout {
            margin-top: 35px;
            color: #ffd6d6;
        }

        /* KONTEN UTAMA */

        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            color: #31583c;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .header p {
            color: #777;
            font-size: 14px;
        }

        /* CARD */

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .card h2 {
            color: #31583c;
            font-size: 19px;
            margin-bottom: 20px;
        }

        /* FORM */

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: bold;
            color: #385640;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9e0da;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
            outline: none;
        }

        input:focus,
        textarea:focus {
            border-color: #31583c;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        /* TOMBOL */

        button,
        .btn {
            border: none;
            padding: 10px 17px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
        }

        .btn-simpan {
            background: #31583c;
            color: white;
        }

        .btn-simpan:hover {
            background: #456e4f;
        }

        .btn-edit {
            background: #e8a83e;
            color: white;
        }

        .btn-edit:hover {
            background: #d7972d;
        }

        .btn-hapus {
            background: #c84b4b;
            color: white;
        }

        .btn-hapus:hover {
            background: #ad3838;
        }

        .btn-batal {
            background: #e5e9e5;
            color: #34443a;
            margin-left: 7px;
        }

        .btn-batal:hover {
            background: #d9e0d9;
        }

        /* TABEL */

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th,
        td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
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

        /* PESAN */

        .pesan {
            padding: 13px;
            border-radius: 7px;
            background: #e2f3e5;
            color: #28733a;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .pesan-error {
            background: #fce8e8;
            color: #a52d2d;
        }

        .kosong {
            text-align: center;
            padding: 25px;
            color: #849087;
        }

        /* AKSI TABEL */

        .aksi {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .aksi form {
            margin: 0;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .main-content {
                padding: 25px;
            }

        }

        @media (max-width: 700px) {

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

            .main-content {
                padding: 22px 15px;
            }

        }

        @media (max-width: 480px) {

            .card {
                padding: 18px;
            }

            .aksi {
                flex-wrap: wrap;
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
                <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
            </a>
        </div>

        <div class="subtitle">PANEL ADMIN</div>

        <a href="dashboard.php">Dashboard</a>

        <a href="barang.php">Data Barang</a>

        <a href="kategori.php" class="active">Kategori</a>

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

    <!-- KONTEN UTAMA -->

    <main class="main-content">

        <div class="header">

            <h1>Kelola Kategori</h1>

            <p>
                Tambah, ubah, dan kelola kategori barang rental Kelana.
            </p>

        </div>

        <!-- PESAN -->

        <?php if ($pesan !== "") { ?>

            <div class="pesan pesan-error">
                <?= htmlspecialchars($pesan) ?>
            </div>

        <?php } ?>

        <?php if ($pesanUrl === "tambah") { ?>

            <div class="pesan">
                Kategori berhasil ditambahkan.
            </div>

        <?php } elseif ($pesanUrl === "edit") { ?>

            <div class="pesan">
                Kategori berhasil diperbarui.
            </div>

        <?php } elseif ($pesanUrl === "hapus") { ?>

            <div class="pesan">
                Kategori berhasil dihapus.
            </div>

        <?php } elseif ($pesanUrl === "digunakan") { ?>

            <div class="pesan pesan-error">
                Kategori tidak dapat dihapus karena masih digunakan oleh barang.
            </div>

        <?php } ?>

        <!-- FORM TAMBAH / EDIT -->

        <div class="card">

            <h2>
                <?= $dataEdit ? "Edit Kategori" : "Tambah Kategori Baru" ?>
            </h2>

            <form method="POST">

                <?php if ($dataEdit) { ?>

                    <input
                        type="hidden"
                        name="id_kategori"
                        value="<?= $dataEdit['id_kategori'] ?>"
                    >

                <?php } ?>

                <label>Nama Kategori</label>

                <input
                    type="text"
                    name="nama_kategori"
                    placeholder="Contoh: Perlengkapan Camping"
                    value="<?= htmlspecialchars($dataEdit['nama_kategori'] ?? '') ?>"
                    required
                >

                <label>Deskripsi</label>

                <textarea
                    name="deskripsi"
                    placeholder="Masukkan deskripsi kategori..."
                ><?= htmlspecialchars($dataEdit['deskripsi'] ?? '') ?></textarea>

                <?php if ($dataEdit) { ?>

                    <button
                        type="submit"
                        name="edit"
                        class="btn-simpan"
                    >
                        Simpan Perubahan
                    </button>

                    <a href="kategori.php" class="btn btn-batal">
                        Batal
                    </a>

                <?php } else { ?>

                    <button
                        type="submit"
                        name="tambah"
                        class="btn-simpan"
                    >
                        + Tambah Kategori
                    </button>

                <?php } ?>

            </form>

        </div>

        <!-- TABEL DAFTAR KATEGORI -->

        <div class="card">

            <h2>Daftar Kategori</h2>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Jumlah Barang</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php
                        $no = 1;

                        if (mysqli_num_rows($queryKategori) > 0) {

                            while ($kategori = mysqli_fetch_assoc($queryKategori)) {
                        ?>

                            <tr>

                                <td>
                                    <?= $no++ ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($kategori['deskripsi'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= $kategori['jumlah_barang'] ?> barang
                                </td>

                                <td>

                                    <div class="aksi">

                                        <a
                                            href="kategori.php?edit=<?= $kategori['id_kategori'] ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="id_kategori"
                                                value="<?= $kategori['id_kategori'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus"
                                                class="btn-hapus"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php
                            }

                        } else {
                        ?>

                            <tr>

                                <td colspan="5" class="kosong">
                                    Belum ada kategori.
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
