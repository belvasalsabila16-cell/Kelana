
<?php
session_start();
include_once "../config/database.php";

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
    header("Location: ../index.php");
    exit;
}

// ==========================================
// 2. PENCARIAN DAN FILTER ROLE
// ==========================================

$keyword = trim($_GET["cari"] ?? "");
$filterRole = $_GET["role"] ?? "";

$daftarRole = ["admin", "pemilik", "pelanggan"];

if (!in_array($filterRole, $daftarRole, true)) {
    $filterRole = "";
}

$keywordSQL = mysqli_real_escape_string($conn, $keyword);

$sql = "
    SELECT id_user, nama, email, no_hp, alamat, role
    FROM user
    WHERE 1=1
";

if ($keyword !== "") {
    $sql .= "
        AND (
            nama LIKE '%$keywordSQL%'
            OR email LIKE '%$keywordSQL%'
            OR no_hp LIKE '%$keywordSQL%'
        )
    ";
}

if ($filterRole !== "") {
    $roleSQL = mysqli_real_escape_string($conn, $filterRole);
    $sql .= " AND role = '$roleSQL'";
}

$sql .= " ORDER BY id_user DESC";

$query = mysqli_query($conn, $sql);

// ==========================================
// 3. HITUNG JUMLAH AKUN
// ==========================================

$totalMember = mysqli_num_rows(mysqli_query(
    $conn,
    "SELECT id_user FROM user"
));

$totalPelanggan = mysqli_num_rows(mysqli_query(
    $conn,
    "SELECT id_user FROM user WHERE role = 'pelanggan'"
));

$totalPemilik = mysqli_num_rows(mysqli_query(
    $conn,
    "SELECT id_user FROM user WHERE role = 'pemilik'"
));

$totalAdmin = mysqli_num_rows(mysqli_query(
    $conn,
    "SELECT id_user FROM user WHERE role = 'admin'"
));

// ==========================================
// 4. FUNGSI KEAMANAN
// ==========================================

function e($nilai) {
    return htmlspecialchars((string) $nilai, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Member - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: transparent;
            color: #333;
        }

        /* LOGO */

        .top-logo {
            padding: 15px 35px 0;
        }

        .logo-link {
            display: inline-block;
        }

        .logo-link img {
            display: block;
            width: 190px;
            height: 90px;
            object-fit: contain;
        }

        /* CONTAINER */

        .container {
            max-width: 1200px;
            margin: 20px auto 35px;
            padding: 0 25px;
        }

        /* TOMBOL KEMBALI */

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #31583c;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            color: #456e4f;
            text-decoration: underline;
        }

        /* JUDUL */

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
            color: #31583c;
            font-size: 27px;
        }

        .header p {
            color: #777;
            margin: 0;
            font-size: 14px;
        }

        /* STATISTIK */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
            transition: .2s ease;
        }

        .stat-card p {
            margin: 0 0 12px;
            color: #777;
            font-size: 13px;
        }

        .stat-card h2 {
            margin: 0;
            color: #31583c;
            font-size: 27px;
        }

        /* KONTEN */

        .content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .content-header h2 {
            margin: 0;
            color: #31583c;
            font-size: 19px;
        }

        /* PENCARIAN DAN FILTER */

        .filter-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-form input,
        .filter-form select {
            padding: 10px 13px;
            border: 1px solid #d5ded7;
            border-radius: 7px;
            outline: none;
            background: white;
            font-size: 13px;
            color: #333;
        }

        .filter-form input {
            min-width: 230px;
        }

        .filter-form input:focus,
        .filter-form select:focus {
            border-color: #31583c;
        }

        .reset-link {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border-radius: 7px;
            background: #31583c;
            color: white;
            text-decoration: none;
            font-size: 13px;
            transition: .2s ease;
        }

        .reset-link:hover {
            background: #456e4f;
        }

        .result-info {
            color: #777;
            font-size: 13px;
            margin-bottom: 15px;
        }

        /* TABEL */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
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

        td {
            color: #444;
        }

        tbody tr:hover {
            background: #f8fbf7;
        }

        /* LABEL ROLE */

        .role {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .role-admin {
            background: #e4edff;
            color: #3159a5;
        }

        .role-pemilik {
            background: #fff0d9;
            color: #9b641b;
        }

        .role-pelanggan {
            background: #e2f3e5;
            color: #28733a;
        }

        /* DATA KOSONG */

        .empty {
            text-align: center;
            padding: 30px;
            color: #888;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 700px) {

            .top-logo {
                padding: 10px 20px 0;
            }

            .logo-link img {
                width: 150px;
                height: 75px;
            }

            .container {
                padding: 0 15px;
                margin-top: 15px;
            }

            .content {
                padding: 18px;
            }

        }

        @media (max-width: 480px) {

            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-card h2 {
                font-size: 22px;
            }

            .filter-form {
                width: 100%;
            }

            .filter-form input,
            .filter-form select {
                width: 100%;
                min-width: 0;
            }

            .reset-link {
                justify-content: center;
            }

        }

    </style>

</head>

<body>

<!-- LOGO -->

<div class="top-logo">
    <a href="../index.php" class="logo-link">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>
</div>

<div class="container">

    <!-- KEMBALI -->

    <a href="dashboard.php" class="back-link">
        &larr; Kembali ke Dashboard
    </a>

    <!-- JUDUL -->

    <div class="header">
        <h1>Manajemen Member</h1>
        <p>Kelola dan lihat seluruh akun yang terdaftar di Kelana.</p>
    </div>

    <!-- STATISTIK -->

    <div class="stats">

        <div class="stat-card">
            <p>Total Akun</p>
            <h2><?= $totalMember ?></h2>
        </div>

        <div class="stat-card">
            <p>Pelanggan</p>
            <h2><?= $totalPelanggan ?></h2>
        </div>

        <div class="stat-card">
            <p>Pemilik</p>
            <h2><?= $totalPemilik ?></h2>
        </div>

        <div class="stat-card">
            <p>Admin</p>
            <h2><?= $totalAdmin ?></h2>
        </div>

    </div>

    <!-- DAFTAR AKUN -->

    <div class="content">

        <div class="content-header">

            <h2>Daftar Akun</h2>

            <!-- FORM PENCARIAN OTOMATIS -->

            <form method="GET" class="filter-form" id="filterForm">

                <input
                    type="text"
                    name="cari"
                    id="inputCari"
                    placeholder="Cari nama, email, atau HP..."
                    value="<?= e($keyword) ?>"
                    autocomplete="off"
                >

                <select name="role" id="filterRole">

                    <option value="">Semua Role</option>

                    <option value="pelanggan"
                        <?= $filterRole === "pelanggan" ? "selected" : "" ?>>
                        Pelanggan
                    </option>

                    <option value="pemilik"
                        <?= $filterRole === "pemilik" ? "selected" : "" ?>>
                        Pemilik
                    </option>

                    <option value="admin"
                        <?= $filterRole === "admin" ? "selected" : "" ?>>
                        Admin
                    </option>

                </select>

                <a href="member.php" class="reset-link">
                    Reset
                </a>

            </form>

        </div>

        <div class="result-info">
            Menampilkan <?= $query ? mysqli_num_rows($query) : 0 ?> akun.
        </div>

        <!-- TABEL MEMBER -->

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>No. HP</th>
                        <th>Alamat</th>
                        <th>Role</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($query && mysqli_num_rows($query) > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($member = mysqli_fetch_assoc($query)): ?>

                            <tr>

                                <td><?= $no++ ?></td>

                                <td><?= e($member["nama"]) ?></td>

                                <td><?= e($member["email"]) ?></td>

                                <td><?= e($member["no_hp"] ?: "-") ?></td>

                                <td><?= e($member["alamat"] ?: "-") ?></td>

                                <td>

                                    <?php
                                    $roleClass = "role-pelanggan";

                                    if ($member["role"] === "admin") {
                                        $roleClass = "role-admin";
                                    } elseif ($member["role"] === "pemilik") {
                                        $roleClass = "role-pemilik";
                                    }
                                    ?>

                                    <span class="role <?= $roleClass ?>">
                                        <?= e(ucfirst($member["role"])) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty">
                                Tidak ada akun yang ditemukan.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- JAVASCRIPT FILTER OTOMATIS -->

<script>

const form = document.getElementById("filterForm");
const inputCari = document.getElementById("inputCari");
const filterRole = document.getElementById("filterRole");

let waktuKetik;

inputCari.addEventListener("input", function () {

    clearTimeout(waktuKetik);

    waktuKetik = setTimeout(function () {
        form.requestSubmit();
    }, 400);

});

filterRole.addEventListener("change", function () {
    form.requestSubmit();
});

</script>

</body>
</html>
