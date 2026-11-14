
<?php
session_start();
include "../config/database.php";

header("Content-Type: application/json; charset=UTF-8");

// ==========================================
// CEK LOGIN DAN ROLE ADMIN
// ==========================================

if (!isset($_SESSION["id_user"])) {
    http_response_code(401);
    echo json_encode(["status" => "gagal"]);
    exit;
}

$idUser = (int) $_SESSION["id_user"];

$cekRole = mysqli_query(
    $conn,
    "SELECT role FROM user WHERE id_user = $idUser"
);

$dataRole = mysqli_fetch_assoc($cekRole);

if (!$dataRole || $dataRole["role"] !== "admin") {
    http_response_code(403);
    echo json_encode(["status" => "gagal"]);
    exit;
}

// ==========================================
// TOKEN KEAMANAN NOTIFIKASI
// ==========================================

if (empty($_SESSION["csrf_notifikasi"])) {
    $_SESSION["csrf_notifikasi"] = bin2hex(random_bytes(32));
}

// ==========================================
// TANDAI NOTIFIKASI SUDAH DIBACA
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $idNotifikasi = (int) ($_POST["id_notifikasi"] ?? 0);
    $token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals($_SESSION["csrf_notifikasi"], $token) ||
        $idNotifikasi <= 0
    ) {
        http_response_code(400);
        echo json_encode(["status" => "gagal"]);
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifikasi_admin
         SET dibaca = 1
         WHERE id_notifikasi = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $idNotifikasi);
    $berhasil = mysqli_stmt_execute($stmt);

    echo json_encode([
        "status" => $berhasil ? "berhasil" : "gagal"
    ]);

    mysqli_stmt_close($stmt);
    exit;
}

// ==========================================
// TENTUKAN BATAS NOTIFIKASI AWAL
// ==========================================

if (!isset($_SESSION["notifikasi_terakhir_dilihat"])) {

    $hasilAwal = mysqli_query(
        $conn,
        "SELECT MAX(id_notifikasi) AS id_terakhir
         FROM notifikasi_admin"
    );

    $dataAwal = mysqli_fetch_assoc($hasilAwal);

    $_SESSION["notifikasi_terakhir_dilihat"] =
        (int) ($dataAwal["id_terakhir"] ?? 0);
}

// ==========================================
// AMBIL NOTIFIKASI BARU
// ==========================================

$idTerakhir = (int) $_SESSION["notifikasi_terakhir_dilihat"];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id_notifikasi, pesan, tanggal_notifikasi
     FROM notifikasi_admin
     WHERE dibaca = 0
     AND id_notifikasi > ?
     ORDER BY id_notifikasi ASC
     LIMIT 5"
);

mysqli_stmt_bind_param($stmt, "i", $idTerakhir);
mysqli_stmt_execute($stmt);

$hasil = mysqli_stmt_get_result($stmt);

$daftar = [];
$idTerbesar = $idTerakhir;

while ($row = mysqli_fetch_assoc($hasil)) {

    $daftar[] = $row;

    if ((int) $row["id_notifikasi"] > $idTerbesar) {
        $idTerbesar = (int) $row["id_notifikasi"];
    }
}

// Simpan batas agar notifikasi yang sama
// tidak muncul berulang kali.

$_SESSION["notifikasi_terakhir_dilihat"] = $idTerbesar;

echo json_encode([
    "status" => "berhasil",
    "notifikasi" => $daftar
]);

mysqli_stmt_close($stmt);
?>
