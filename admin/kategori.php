
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

// Tambah kategori
if (isset($_POST["tambah"])) {
    $nama = trim($_POST["nama_kategori"]);
    $deskripsi = trim($_POST["deskripsi"]);

    if ($nama === "") {
        $pesan = "Nama kategori wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ss", $nama, $deskripsi);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: kategori.php?pesan=tambah");
            exit;
        } else {
            $pesan = "Gagal menambahkan kategori.";
        }
    }
}

// Edit kategori
if (isset($_POST["edit"])) {
    $id = (int) $_POST["id_kategori"];
    $nama = trim($_POST["nama_kategori"]);
    $deskripsi = trim($_POST["deskripsi"]);

    if ($nama === "") {
        $pesan = "Nama kategori wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id_kategori = ?"
        );
        mysqli_stmt_bind_param($stmt, "ssi", $nama, $deskripsi, $id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: kategori.php?pesan=edit");
            exit;
        } else {
            $pesan = "Gagal mengubah kategori.";
        }
    }
}

// Hapus kategori
if (isset($_POST["hapus"])) {
    $id = (int) $_POST["id_kategori"];

    // Periksa apakah kategori masih digunakan barang
    $stmtCek = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS jumlah FROM barang WHERE id_kategori = ?"
    );
    mysqli_stmt_bind_param($stmtCek, "i", $id);
    mysqli_stmt_execute($stmtCek);
    $hasilCek = mysqli_stmt_get_result($stmtCek);
    $jumlahBarang = mysqli_fetch_assoc($hasilCek)["jumlah"];

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
}

// Ambil data kategori untuk diedit
$dataEdit = null;

if (isset($_GET["edit"])) {
    $idEdit = (int) $_GET["edit"];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM kategori WHERE id_kategori = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $idEdit);
    mysqli_stmt_execute($stmt);
    $dataEdit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// Ambil seluruh kategori
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
            margin-bottom: 30px;
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
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .card h2 {
            font-size: 19px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9e0da;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        button, .btn {
            border: none;
            padding: 10px 17px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
        }

        .btn-simpan {
            background: #294b39;
            color: white;
        }

        .btn-edit {
            background: #e8a83e;
            color: white;
        }

        .btn-hapus {
            background: #c84b4b;
            color: white;
        }

        .btn-batal {
            background: #e5e9e5;
            color: #34443a;
            margin-left: 7px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #edf0ed;
            font-size: 13px;
        }

        th {
            background: #f4f7f4;
            color: #526357;
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

        .aksi {
            display: flex;
            gap: 7px;
            align-items: center;
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

<div class="sidebar">
    <div class="logo">KELANA.</div>

    <a href="dashboard.php">Dashboard</a>
    <a href="barang.php">Data Barang</a>
    <a href="kategori.php" class="aktif">Kategori</a>
    <a href="booking.php">Data Booking</a>
    <a href="pengembalian.php">Pengembalian</a>
    <a href="faq.php">FAQ</a>
    <a href="laporan.php">Laporan</a>
    <a href="../index.php">Lihat Website</a>
    <a href="../auth/logout.php">Keluar</a>
</div>

<div class="main">

    <div class="header">
        <h1>Kelola Kategori</h1>
        <p>Tambah, ubah, dan kelola kategori barang rental Kelana.</p>
    </div>

    <?php if ($pesan !== "") { ?>
        <div class="pesan">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <?php if ($pesanUrl === "tambah") { ?>
        <div class="pesan">Kategori berhasil ditambahkan.</div>
    <?php } elseif ($pesanUrl === "edit") { ?>
        <div class="pesan">Kategori berhasil diperbarui.</div>
    <?php } elseif ($pesanUrl === "hapus") { ?>
        <div class="pesan">Kategori berhasil dihapus.</div>
    <?php } elseif ($pesanUrl === "digunakan") { ?>
        <div class="pesan">
            Kategori tidak dapat dihapus karena masih digunakan oleh barang.
        </div>
    <?php } ?>

    <div class="card">
        <h2>
            <?= $dataEdit ? "Edit Kategori" : "Tambah Kategori Baru" ?>
        </h2>

        <form method="POST">

            <?php if ($dataEdit) { ?>
                <input type="hidden" name="id_kategori"
                       value="<?= $dataEdit['id_kategori'] ?>">
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
            <textarea name="deskripsi"
                placeholder="Masukkan deskripsi kategori..."><?= htmlspecialchars($dataEdit['deskripsi'] ?? '') ?></textarea>

            <?php if ($dataEdit) { ?>
                <button type="submit" name="edit" class="btn-simpan">
                    Simpan Perubahan
                </button>

                <a href="kategori.php" class="btn btn-batal">
                    Batal
                </a>
            <?php } else { ?>
                <button type="submit" name="tambah" class="btn-simpan">
                    + Tambah Kategori
                </button>
            <?php } ?>

        </form>
    </div>

    <div class="card">
        <h2>Daftar Kategori</h2>

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
                        <td><?= $no++ ?></td>

                        <td>
                            <?= htmlspecialchars($kategori['nama_kategori']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($kategori['deskripsi'] ?? '-') ?>
                        </td>

                        <td><?= $kategori['jumlah_barang'] ?> barang</td>

                        <td>
                            <div class="aksi">
                                <a
                                    href="kategori.php?edit=<?= $kategori['id_kategori'] ?>"
                                    class="btn btn-edit">
                                    Edit
                                </a>

                                <form method="POST"
                                      onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
                                    <input type="hidden" name="id_kategori"
                                           value="<?= $kategori['id_kategori'] ?>">

                                    <button type="submit" name="hapus"
                                            class="btn-hapus">
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

</body>
</html>
