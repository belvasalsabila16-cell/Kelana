
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
// 2. PROSES TAMBAH FAQ
// ==========================================
$pesan = "";
$jenisPesan = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if ($aksi === "tambah") {
        $pertanyaan = trim($_POST["pertanyaan"] ?? "");
        $jawaban = trim($_POST["jawaban"] ?? "");

        if ($pertanyaan === "" || $jawaban === "") {
            $pesan = "Pertanyaan dan jawaban wajib diisi.";
            $jenisPesan = "error";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO faq (pertanyaan, jawaban) VALUES (?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "ss", $pertanyaan, $jawaban);

            if (mysqli_stmt_execute($stmt)) {
                header("Location: faq.php?pesan=tambah");
                exit;
            } else {
                $pesan = "FAQ gagal ditambahkan.";
                $jenisPesan = "error";
            }
        }
    }

    // ======================================
    // 3. PROSES EDIT FAQ
    // ======================================
    if ($aksi === "edit") {
        $idFaq = (int) ($_POST["id_faq"] ?? 0);
        $pertanyaan = trim($_POST["pertanyaan"] ?? "");
        $jawaban = trim($_POST["jawaban"] ?? "");

        if ($idFaq <= 0 || $pertanyaan === "" || $jawaban === "") {
            $pesan = "Data FAQ tidak valid atau belum lengkap.";
            $jenisPesan = "error";
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE faq SET pertanyaan = ?, jawaban = ? WHERE id_faq = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $pertanyaan,
                $jawaban,
                $idFaq
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: faq.php?pesan=edit");
                exit;
            } else {
                $pesan = "FAQ gagal diperbarui.";
                $jenisPesan = "error";
            }
        }
    }

    // ======================================
    // 4. PROSES HAPUS FAQ
    // ======================================
    if ($aksi === "hapus") {
        $idFaq = (int) ($_POST["id_faq"] ?? 0);

        if ($idFaq > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM faq WHERE id_faq = ?"
            );
            mysqli_stmt_bind_param($stmt, "i", $idFaq);

            if (mysqli_stmt_execute($stmt)) {
                header("Location: faq.php?pesan=hapus");
                exit;
            } else {
                $pesan = "FAQ gagal dihapus.";
                $jenisPesan = "error";
            }
        }
    }
}

// ==========================================
// 5. NOTIFIKASI
// ==========================================
if (isset($_GET["pesan"])) {
    if ($_GET["pesan"] === "tambah") {
        $pesan = "FAQ berhasil ditambahkan.";
    } elseif ($_GET["pesan"] === "edit") {
        $pesan = "FAQ berhasil diperbarui.";
    } elseif ($_GET["pesan"] === "hapus") {
        $pesan = "FAQ berhasil dihapus.";
    }
}

// ==========================================
// 6. AMBIL DATA FAQ UNTUK DIEDIT
// ==========================================
$dataEdit = null;

if (isset($_GET["edit"])) {
    $idEdit = (int) $_GET["edit"];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM faq WHERE id_faq = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $idEdit);
    mysqli_stmt_execute($stmt);

    $hasilEdit = mysqli_stmt_get_result($stmt);
    $dataEdit = mysqli_fetch_assoc($hasilEdit);
}

// ==========================================
// 7. AMBIL SELURUH DATA FAQ
// ==========================================
$daftarFaq = mysqli_query(
    $conn,
    "SELECT * FROM faq ORDER BY id_faq DESC"
);

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola FAQ - Kelana</title>

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
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            margin-bottom: 25px;
        }

        .card h2 {
            font-size: 19px;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d6ded8;
            border-radius: 7px;
            margin-bottom: 17px;
            font-size: 14px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        button,
        .btn {
            border: none;
            border-radius: 7px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-simpan {
            background: #315d43;
            color: white;
        }

        .btn-batal {
            background: #e8ece9;
            color: #34483a;
            margin-left: 5px;
        }

        .btn-edit {
            background: #e5eee8;
            color: #28523a;
        }

        .btn-hapus {
            background: #fbe9e7;
            color: #b13b32;
        }

        .aksi {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf0ed;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
            line-height: 1.6;
        }

        th {
            background: #f5f8f5;
            color: #405448;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #e6f4e9;
            color: #27623a;
        }

        .error {
            background: #fdebea;
            color: #a52e2e;
        }

        .kosong {
            text-align: center;
            color: #87918a;
            padding: 25px;
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
    <a href="faq.php" class="aktif">FAQ</a>
    <a href="laporan.php">Laporan</a>
    <a href="../index.php">Lihat Website</a>
</aside>

<main class="main">

    <div class="header">
        <h1>Kelola FAQ</h1>
        <p>Kelola pertanyaan dan jawaban yang sering ditanyakan pelanggan.</p>
    </div>

    <?php if ($pesan !== ""): ?>
        <div class="alert <?= e($jenisPesan) ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2><?= $dataEdit ? "Edit Pertanyaan" : "Tambah Pertanyaan Baru" ?></h2>

        <form method="POST">
            <input
                type="hidden"
                name="aksi"
                value="<?= $dataEdit ? "edit" : "tambah" ?>"
            >

            <?php if ($dataEdit): ?>
                <input
                    type="hidden"
                    name="id_faq"
                    value="<?= (int) $dataEdit["id_faq"] ?>"
                >
            <?php endif; ?>

            <label>Pertanyaan</label>
            <input
                type="text"
                name="pertanyaan"
                placeholder="Masukkan pertanyaan pelanggan"
                value="<?= e($dataEdit["pertanyaan"] ?? "") ?>"
                required
            >

            <label>Jawaban</label>
            <textarea
                name="jawaban"
                placeholder="Masukkan jawaban"
                required
            ><?= e($dataEdit["jawaban"] ?? "") ?></textarea>

            <button type="submit" class="btn-simpan">
                <?= $dataEdit ? "Simpan Perubahan" : "Tambah FAQ" ?>
            </button>

            <?php if ($dataEdit): ?>
                <a href="faq.php" class="btn btn-batal">Batal</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="card">
        <h2>Daftar Pertanyaan</h2>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th width="60">No.</th>
                        <th>Pertanyaan</th>
                        <th>Jawaban</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (mysqli_num_rows($daftarFaq) > 0): ?>
                        <?php $no = 1; ?>
                        <?php while ($faq = mysqli_fetch_assoc($daftarFaq)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= nl2br(e($faq["pertanyaan"])) ?></td>
                                <td><?= nl2br(e($faq["jawaban"])) ?></td>
                                <td>
                                    <div class="aksi">
                                        <a
                                            href="faq.php?edit=<?= (int) $faq["id_faq"] ?>"
                                            class="btn btn-edit"
                                        >Edit</a>

                                        <form method="POST"
                                              onsubmit="return confirm('Yakin ingin menghapus FAQ ini?');">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input
                                                type="hidden"
                                                name="id_faq"
                                                value="<?= (int) $faq["id_faq"] ?>"
                                            >
                                            <button type="submit" class="btn-hapus">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="kosong">
                                Belum ada pertanyaan FAQ.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>
