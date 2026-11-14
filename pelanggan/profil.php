
<?php
session_start();
include_once "../config/database.php";

// ==========================================
// 1. CEK LOGIN DAN ROLE
// ==========================================

if (!isset($_SESSION["id_user"])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION["id_user"];

$cekRole = mysqli_query(
    $conn,
    "SELECT role FROM user WHERE id_user = $id_user"
);

$dataRole = mysqli_fetch_assoc($cekRole);

if (!$dataRole) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$isAdmin = ($dataRole["role"] === "admin");
$isPelanggan = ($dataRole["role"] === "pelanggan");

// ==========================================
// 2. JUMLAH NOTIFIKASI BELUM DIBACA
// ==========================================

$jumlahNotifikasi = 0;

if ($isPelanggan) {
    $stmtNotif = mysqli_prepare($conn, "
        SELECT COUNT(*) AS jumlah
        FROM notifikasi_pelanggan
        WHERE id_user = ? AND dibaca = 0
    ");

    mysqli_stmt_bind_param($stmtNotif, "i", $id_user);
    mysqli_stmt_execute($stmtNotif);

    $hasilNotif = mysqli_stmt_get_result($stmtNotif);
    $dataNotif = mysqli_fetch_assoc($hasilNotif);

    $jumlahNotifikasi = (int) ($dataNotif["jumlah"] ?? 0);

    mysqli_stmt_close($stmtNotif);
}

// Menentukan halaman aktif
$halamanAktif = basename($_SERVER["PHP_SELF"]);

// Menghitung jumlah barang dalam keranjang
$jumlahKeranjang = isset($_SESSION["keranjang"])
    ? array_sum($_SESSION["keranjang"])
    : 0;

// ==========================================
// 3. AMBIL DATA USER
// ==========================================

$pesan = "";
$jenis_pesan = "";

$query = mysqli_prepare($conn, "
    SELECT nama, email, no_hp, alamat, password
    FROM user
    WHERE id_user = ?
");

mysqli_stmt_bind_param($query, "i", $id_user);
mysqli_stmt_execute($query);

$result = mysqli_stmt_get_result($query);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// ==========================================
// 4. PROSES FORM
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $aksi = $_POST["aksi"] ?? "";

    // ======================================
    // UPDATE PROFIL
    // ======================================

    if ($aksi === "update_profil") {

        $nama = trim($_POST["nama"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $no_hp = trim($_POST["no_hp"] ?? "");
        $alamat = trim($_POST["alamat"] ?? "");

        if ($nama === "" || $email === "") {

            $pesan = "Nama dan email wajib diisi.";
            $jenis_pesan = "error";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $pesan = "Format email tidak valid.";
            $jenis_pesan = "error";

        } else {

            $cek = mysqli_prepare($conn, "
                SELECT id_user FROM user
                WHERE email = ? AND id_user != ?
            ");

            mysqli_stmt_bind_param(
                $cek,
                "si",
                $email,
                $id_user
            );

            mysqli_stmt_execute($cek);

            $hasil_cek = mysqli_stmt_get_result($cek);

            if (mysqli_num_rows($hasil_cek) > 0) {

                $pesan = "Email sudah digunakan akun lain.";
                $jenis_pesan = "error";

            } else {

                $update = mysqli_prepare($conn, "
                    UPDATE user
                    SET nama = ?, email = ?, no_hp = ?, alamat = ?
                    WHERE id_user = ?
                ");

                mysqli_stmt_bind_param(
                    $update,
                    "ssssi",
                    $nama,
                    $email,
                    $no_hp,
                    $alamat,
                    $id_user
                );

                if (mysqli_stmt_execute($update)) {

                    $_SESSION["nama"] = $nama;
                    $_SESSION["email"] = $email;

                    $user["nama"] = $nama;
                    $user["email"] = $email;
                    $user["no_hp"] = $no_hp;
                    $user["alamat"] = $alamat;

                    $pesan = "Profil berhasil diperbarui!";
                    $jenis_pesan = "success";

                } else {

                    $pesan = "Gagal memperbarui profil.";
                    $jenis_pesan = "error";

                }
            }
        }
    }

    // ======================================
    // UBAH PASSWORD
    // ======================================

    elseif ($aksi === "ubah_password") {

        $password_lama = $_POST["password_lama"] ?? "";
        $password_baru = $_POST["password_baru"] ?? "";
        $konfirmasi = $_POST["konfirmasi_password"] ?? "";

        if (
            $password_lama === "" ||
            $password_baru === "" ||
            $konfirmasi === ""
        ) {

            $pesan = "Semua kolom password wajib diisi.";
            $jenis_pesan = "error";

        } elseif (!password_verify($password_lama, $user["password"])) {

            $pesan = "Password lama yang kamu masukkan salah.";
            $jenis_pesan = "error";

        } elseif (strlen($password_baru) < 6) {

            $pesan = "Password baru minimal 6 karakter.";
            $jenis_pesan = "error";

        } elseif ($password_baru !== $konfirmasi) {

            $pesan = "Konfirmasi password baru tidak cocok.";
            $jenis_pesan = "error";

        } elseif ($password_lama === $password_baru) {

            $pesan = "Password baru harus berbeda dari password lama.";
            $jenis_pesan = "error";

        } else {

            $hash_password = password_hash(
                $password_baru,
                PASSWORD_DEFAULT
            );

            $update_password = mysqli_prepare($conn, "
                UPDATE user
                SET password = ?
                WHERE id_user = ?
            ");

            mysqli_stmt_bind_param(
                $update_password,
                "si",
                $hash_password,
                $id_user
            );

            if (mysqli_stmt_execute($update_password)) {

                $pesan = "Password berhasil diubah!";
                $jenis_pesan = "success";

                $user["password"] = $hash_password;

            } else {

                $pesan = "Gagal mengubah password.";
                $jenis_pesan = "error";

            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Saya - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        /* Background halaman */
        body {
            background-color: transparent;
        }

        /* Logo navbar */
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

        .admin-back-link {
            display: inline-block;
            background: #31583c;
            color: white !important;
            padding: 9px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }

        .admin-back-link:hover {
            background: #25452e;
        }

        .navbar {
            flex-wrap: wrap;
            gap: 15px;
        }

        .menu {
            flex-wrap: wrap;
        }

        .navbar .menu a.active {
            color: #31583c;
            font-weight: 700;
            position: relative;
        }

        .navbar .menu a.active::after {
            width: 100%;
        }

        /* Kelompok tombol kanan navbar */
        .navbar-account-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
            flex-shrink: 0;
        }

        /* Tombol notifikasi */
        .notification-link {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: #31583c;
            text-decoration: none;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .notification-link:hover {
            background: #edf3ed;
        }

        .notification-link svg {
            width: 23px;
            height: 23px;
        }

        .notification-badge {
            position: absolute;
            top: -3px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e53935;
            color: white;
            font-size: 10px;
            font-weight: 700;
            border-radius: 20px;
            border: 2px solid white;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            color: #888;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password svg {
            width: 21px;
            height: 21px;
        }

        @media (max-width: 768px) {
            .navbar-account-group {
                margin-left: 0;
            }
        }

        @media (max-width: 700px) {
            .navbar .logo-link img {
                width: 120px;
                height: 55px;
            }
        }

    </style>

</head>

<body>

<!-- ======================================
     NAVBAR
====================================== -->

<nav class="navbar">

    <a href="../index.php" class="logo-link">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>

    <div class="menu">

        <a href="../index.php"
           class="<?php echo $halamanAktif === 'index.php' ? 'active' : ''; ?>">
            Beranda
        </a>

        <a href="katalog.php"
           class="<?php echo $halamanAktif === 'katalog.php' ? 'active' : ''; ?>">
            Katalog
        </a>

        <a href="keranjang.php"
           class="<?php echo $halamanAktif === 'keranjang.php' ? 'active' : ''; ?>">
            Keranjang (<?php echo $jumlahKeranjang; ?>)
        </a>

        <a href="tentang.php"
           class="<?php echo $halamanAktif === 'tentang.php' ? 'active' : ''; ?>">
            Tentang Kami
        </a>

        <a href="faq.php"
           class="<?php echo $halamanAktif === 'faq.php' ? 'active' : ''; ?>">
            FAQ
        </a>

        <?php if ($isAdmin) { ?>

            <a href="../admin/dashboard.php" class="admin-back-link">
                Kembali ke Panel Admin
            </a>

        <?php } ?>

    </div>

    <div class="navbar-account-group">

        <?php if ($isPelanggan) { ?>

            <a href="notifikasi.php"
               class="notification-link"
               title="Notifikasi">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="1.8"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>

                    <path d="M10 21h4"/>

                </svg>

                <?php if ($jumlahNotifikasi > 0) { ?>

                    <span class="notification-badge">
                        <?php echo $jumlahNotifikasi > 99 ? "99+" : $jumlahNotifikasi; ?>
                    </span>

                <?php } ?>

            </a>

        <?php } ?>

        <a href="../index.php" class="login">
            Kembali
        </a>

    </div>

</nav>

<!-- ======================================
     PROFIL SAYA
====================================== -->

<div class="profile-container">

    <div class="profile-heading">

        <h1>Profil Saya</h1>

        <p>Kelola informasi akun Kelana kamu.</p>

    </div>

    <div class="profile-card">

        <div class="profile-top">

            <div class="profile-avatar">

                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">

                    <circle cx="12" cy="8" r="3.5"/>

                    <path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>

                </svg>

            </div>

            <div>

                <h3>
                    <?php echo htmlspecialchars($user["nama"]); ?>
                </h3>

                <p>
                    <?php echo $isAdmin ? "Administrator Kelana" : "Pelanggan Kelana"; ?>
                </p>

            </div>

        </div>

        <?php if ($pesan !== "") { ?>

            <div class="profile-message <?php echo $jenis_pesan; ?>">

                <?php echo htmlspecialchars($pesan); ?>

            </div>

        <?php } ?>

        <!-- INFORMASI PRIBADI -->

        <h3 class="profile-section-title">
            Informasi Pribadi
        </h3>

        <form method="POST">

            <input type="hidden" name="aksi" value="update_profil">

            <div class="profile-form-group">

                <label>Nama Lengkap</label>

                <input
                    type="text"
                    name="nama"
                    value="<?php echo htmlspecialchars($user["nama"]); ?>"
                    required
                >

            </div>

            <div class="profile-form-group">

                <label>Email</label>

                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($user["email"]); ?>"
                    required
                >

            </div>

            <div class="profile-form-group">

                <label>Nomor HP</label>

                <input
                    type="text"
                    name="no_hp"
                    value="<?php echo htmlspecialchars($user["no_hp"] ?? ""); ?>"
                    placeholder="Masukkan nomor HP"
                >

            </div>

            <div class="profile-form-group">

                <label>Alamat</label>

                <textarea
                    name="alamat"
                    rows="3"
                    placeholder="Masukkan alamat lengkap"
                ><?php echo htmlspecialchars($user["alamat"] ?? ""); ?></textarea>

            </div>

            <div class="profile-actions">

                <button type="submit" class="profile-save">
                    Simpan Perubahan
                </button>

            </div>

        </form>

        <div class="profile-section-divider"></div>

        <!-- KEAMANAN AKUN -->

        <h3 class="profile-section-title">
            Keamanan Akun
        </h3>

        <p class="password-description">
            Perbarui password secara berkala untuk menjaga keamanan akunmu.
        </p>

        <form method="POST">

            <input type="hidden" name="aksi" value="ubah_password">

            <div class="profile-form-group">

                <label>Password Lama</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        name="password_lama"
                        id="password_lama"
                        placeholder="Masukkan password lama"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        aria-label="Tampilkan password"
                        onclick="togglePassword('password_lama', this)"
                    >

                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round">

                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>

                            <circle cx="12" cy="12" r="3"/>

                        </svg>

                    </button>

                </div>

            </div>

            <div class="profile-form-group">

                <label>Password Baru</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        name="password_baru"
                        id="password_baru"
                        placeholder="Minimal 6 karakter"
                        minlength="6"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        aria-label="Tampilkan password"
                        onclick="togglePassword('password_baru', this)"
                    >

                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round">

                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>

                            <circle cx="12" cy="12" r="3"/>

                        </svg>

                    </button>

                </div>

            </div>

            <div class="profile-form-group">

                <label>Konfirmasi Password Baru</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        name="konfirmasi_password"
                        id="konfirmasi_password"
                        placeholder="Masukkan ulang password baru"
                        minlength="6"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        aria-label="Tampilkan password"
                        onclick="togglePassword('konfirmasi_password', this)"
                    >

                        <svg viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round">

                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>

                            <circle cx="12" cy="12" r="3"/>

                        </svg>

                    </button>

                </div>

            </div>

            <div class="profile-actions">

                <button type="submit" class="profile-save">
                    Ubah Password
                </button>

            </div>

        </form>

    </div>

</div>

<script>

function togglePassword(id, tombol) {

    const input = document.getElementById(id);

    if (input.type === "password") {

        input.type = "text";

        tombol.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        `;

        tombol.style.color = "#31583c";
        tombol.setAttribute("aria-label", "Sembunyikan password");

    } else {

        input.type = "password";

        tombol.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3l18 18"/>
                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5
                         c5 0 8.3 4.5 9 7
                         a10.8 10.8 0 0 1-2.1 3.5"/>
                <path d="M6.2 6.2C4.1 7.5 2.6 9.6 2 12
                         c.7 2.5 4 7 10 7
                         1.3 0 2.5-.3 3.5-.8"/>
            </svg>
        `;

        tombol.style.color = "#888";
        tombol.setAttribute("aria-label", "Tampilkan password");

    }
}

</script>

<?php
// Popup notifikasi otomatis khusus pelanggan
if ($isPelanggan) {
    include "notifikasi_popup.php";
}
?>

</body>
</html>
