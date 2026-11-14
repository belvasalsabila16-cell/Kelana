
<?php
session_start();
include_once "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["id_user"])) {
    http_response_code(401);
    echo json_encode(["success" => false]);
    exit;
}

$idUser = (int) $_SESSION["id_user"];

// Pastikan pengguna adalah pelanggan
$stmtRole = mysqli_prepare($conn, "
    SELECT role FROM user WHERE id_user = ?
");

mysqli_stmt_bind_param($stmtRole, "i", $idUser);
mysqli_stmt_execute($stmtRole);

$hasilRole = mysqli_stmt_get_result($stmtRole);
$dataRole = mysqli_fetch_assoc($hasilRole);
mysqli_stmt_close($stmtRole);

if (!$dataRole || $dataRole["role"] !== "pelanggan") {
    http_response_code(403);
    echo json_encode(["success" => false]);
    exit;
}

// Ambil ID notifikasi terakhir yang sudah diperiksa browser
$afterId = isset($_GET["after_id"])
    ? max(0, (int) $_GET["after_id"])
    : 0;

// Ambil notifikasi baru yang belum dibaca
$stmt = mysqli_prepare($conn, "
    SELECT id_notifikasi, pesan, tanggal_notifikasi
    FROM notifikasi_pelanggan
    WHERE id_user = ?
    AND dibaca = 0
    AND id_notifikasi > ?
    ORDER BY id_notifikasi ASC
    LIMIT 10
");

mysqli_stmt_bind_param($stmt, "ii", $idUser, $afterId);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);
$daftar = [];

while ($row = mysqli_fetch_assoc($hasil)) {
    $daftar[] = [
        "id_notifikasi" => (int) $row["id_notifikasi"],
        "pesan" => $row["pesan"],
        "tanggal_notifikasi" => $row["tanggal_notifikasi"]
    ];
}

mysqli_stmt_close($stmt);

// Hitung semua notifikasi yang belum dibaca
$stmtJumlah = mysqli_prepare($conn, "
    SELECT COUNT(*) AS jumlah
    FROM notifikasi_pelanggan
    WHERE id_user = ? AND dibaca = 0
");

mysqli_stmt_bind_param($stmtJumlah, "i", $idUser);
mysqli_stmt_execute($stmtJumlah);

$hasilJumlah = mysqli_stmt_get_result($stmtJumlah);
$dataJumlah = mysqli_fetch_assoc($hasilJumlah);

mysqli_stmt_close($stmtJumlah);

echo json_encode([
    "success" => true,
    "notifikasi" => $daftar,
    "jumlah_belum_dibaca" => (int) $dataJumlah["jumlah"]
]);
