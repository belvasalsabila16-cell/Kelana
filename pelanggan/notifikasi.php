
<?php
session_start();
include_once "../config/database.php";

// Pastikan pelanggan sudah login
if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$idUser = (int) $_SESSION["id_user"];

// Pastikan akun adalah pelanggan
$cekRole = mysqli_query(
    $conn,
    "SELECT role FROM user WHERE id_user = $idUser"
);

$dataRole = mysqli_fetch_assoc($cekRole);

if (!$dataRole || $dataRole["role"] !== "pelanggan") {
    header("Location: ../index.php");
    exit;
}

// Tandai notifikasi sebagai sudah dibaca
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idNotifikasi = (int) ($_POST["id_notifikasi"] ?? 0);

    if ($idNotifikasi > 0) {

        $stmtBaca = mysqli_prepare($conn, "
            UPDATE notifikasi_pelanggan
            SET dibaca = 1
            WHERE id_notifikasi = ?
            AND id_user = ?
        ");

        mysqli_stmt_bind_param(
            $stmtBaca,
            "ii",
            $idNotifikasi,
            $idUser
        );

        mysqli_stmt_execute($stmtBaca);
        mysqli_stmt_close($stmtBaca);
    }

    header("Location: notifikasi.php");
    exit;
}

// Ambil notifikasi milik pelanggan yang sedang login
$stmt = mysqli_prepare($conn, "
    SELECT
        n.id_notifikasi,
        n.pesan,
        n.dibaca,
        n.tanggal_notifikasi,
        s.kode_sewa
    FROM notifikasi_pelanggan n
    LEFT JOIN sewa s ON n.id_sewa = s.id_sewa
    WHERE n.id_user = ?
    ORDER BY n.tanggal_notifikasi DESC
");

mysqli_stmt_bind_param($stmt, "i", $idUser);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);

$daftarNotifikasi = [];
$jumlahBelumDibaca = 0;

while ($row = mysqli_fetch_assoc($hasil)) {

    $daftarNotifikasi[] = $row;

    if ((int) $row["dibaca"] === 0) {
        $jumlahBelumDibaca++;
    }
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifikasi Saya - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* BACKGROUND HALAMAN */
        body {
            background-color: transparent;
        }

        /* LOGO NAVBAR */
        .navbar .logo-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .navbar .logo-link img {
            width: 220px;
            height: 100px;
            object-fit: contain;
            display: block;
        }

        .navbar {
            flex-wrap: wrap;
            gap: 15px;
        }

        .menu {
            flex-wrap: wrap;
        }

        .notifikasi-container {
            max-width: 900px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .notifikasi-container h1 {
            color: #31583c;
            margin-bottom: 10px;
        }

        .notifikasi-info {
            color: #666;
            margin-bottom: 25px;
        }

        .notifikasi-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
            border-left: 5px solid #ccc;
        }

        .notifikasi-card.belum-dibaca {
            border-left-color: #31583c;
            background: #f5f9f4;
        }

        .notifikasi-card p {
            margin: 8px 0;
            color: #333;
        }

        .notifikasi-card small {
            color: #888;
        }

        .notifikasi-card form {
            margin-top: 12px;
        }

        .btn-baca {
            background: #31583c;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 9px 14px;
            cursor: pointer;
        }

        .notifikasi-kosong {
            background: white;
            padding: 35px;
            border-radius: 12px;
            text-align: center;
            color: #777;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
        }

        .notifikasi-kembali {
            display: inline-block;
            margin-top: 20px;
            color: #31583c;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 700px) {

            .navbar .logo-link img {
                width: 120px;
                height: 55px;
            }

            .navbar {
                flex-wrap: wrap;
                gap: 15px;
            }

            .menu {
                flex-wrap: wrap;
            }

        }

    </style>
</head>

<body>

<nav class="navbar">

    <a href="../index.php" class="logo-link">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>

    <div class="menu">
        <a href="../index.php">Beranda</a>
        <a href="katalog.php">Katalog</a>
        <a href="keranjang.php">Keranjang</a>
        <a href="tentang.php">Tentang Kami</a>
        <a href="faq.php">FAQ</a>
    </div>

    <a href="profil.php" class="login">Profil Saya</a>

</nav>

<section class="notifikasi-container">

    <h1>Notifikasi Saya</h1>

    <p class="notifikasi-info">
        Kamu memiliki
        <strong><?php echo $jumlahBelumDibaca; ?></strong>
        notifikasi yang belum dibaca.
    </p>

    <?php if (empty($daftarNotifikasi)) { ?>

        <div class="notifikasi-kosong">
            <h3>Belum Ada Notifikasi</h3>
            <p>Notifikasi booking dan informasi penyewaan akan muncul di sini.</p>
        </div>

    <?php } else { ?>

        <?php foreach ($daftarNotifikasi as $notif) { ?>

            <div class="notifikasi-card <?php echo (int) $notif["dibaca"] === 0 ? "belum-dibaca" : ""; ?>">

                <p>
                    <strong>
                        <?php echo htmlspecialchars($notif["pesan"]); ?>
                    </strong>
                </p>

                <?php if (!empty($notif["kode_sewa"])) { ?>
                    <p>
                        Kode Booking:
                        <?php echo htmlspecialchars($notif["kode_sewa"]); ?>
                    </p>
                <?php } ?>

                <small>
                    <?php echo htmlspecialchars($notif["tanggal_notifikasi"]); ?>
                </small>

                <?php if ((int) $notif["dibaca"] === 0) { ?>

                    <form method="POST">
                        <input
                            type="hidden"
                            name="id_notifikasi"
                            value="<?php echo (int) $notif["id_notifikasi"]; ?>"
                        >

                        <button type="submit" class="btn-baca">
                            Tandai Sudah Dibaca
                        </button>
                    </form>

                <?php } else { ?>

                    <p><small>Sudah dibaca</small></p>

                <?php } ?>

            </div>

        <?php } ?>

    <?php } ?>

    <a href="profil.php" class="notifikasi-kembali">
        ← Kembali ke Profil
    </a>

</section>

</body>
</html>
