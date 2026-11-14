
<?php
session_start();
include "../config/database.php";

$pesan = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama = trim($_POST["nama"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $no_hp = trim($_POST["no_hp"] ?? "");
    $password = $_POST["password"] ?? "";
    $konfirmasi = $_POST["konfirmasi_password"] ?? "";

    if ($nama === "" || $email === "" || $password === "") {
        $pesan = "Nama, email, dan password wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = "Format email tidak valid.";

    } elseif (strlen($password) < 6) {
        $pesan = "Password minimal 6 karakter.";

    } elseif ($password !== $konfirmasi) {
        $pesan = "Konfirmasi password tidak cocok.";

    } else {

        $cek = mysqli_prepare($conn, "
            SELECT id_user FROM `user` WHERE email = ?
        ");

        mysqli_stmt_bind_param($cek, "s", $email);
        mysqli_stmt_execute($cek);
        $hasil = mysqli_stmt_get_result($cek);

        if (mysqli_num_rows($hasil) > 0) {
            $pesan = "Email sudah terdaftar. Silakan gunakan email lain.";

        } else {

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $query = mysqli_prepare($conn, "
                INSERT INTO `user`
                (nama, email, password, no_hp, role)
                VALUES (?, ?, ?, ?, 'pelanggan')
            ");

            mysqli_stmt_bind_param(
                $query,
                "ssss",
                $nama,
                $email,
                $passwordHash,
                $no_hp
            );

            if (mysqli_stmt_execute($query)) {
                header("Location: login.php?daftar=berhasil");
                exit;
            } else {
                $pesan = "Registrasi gagal. Silakan coba lagi.";
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

    <title>Daftar Akun - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            background-color: transparent;
            color: #293b30;
        }

        .register-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 15px auto 0;
        }

        .register-logo a {
            display: inline-block;
            text-decoration: none;
        }

        .register-logo img {
            width: 220px;
            height: 100px;
            object-fit: contain;
            display: block;
        }

        .register-container {
            max-width: 500px;
            margin: 15px auto 50px;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,.1);
        }

        .register-container h1 {
            color: #31583c;
            text-align: center;
        }

        .register-container input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        /* ===== PASSWORD ===== */

        .password-wrapper {
            position: relative;
            width: 100%;
            margin: 8px 0 15px;
        }

        .password-wrapper input {
            width: 100%;
            padding: 12px 45px 12px 12px;
            margin: 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            outline: none;
        }

        .password-wrapper input:focus {
            border-color: #31583c;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #888;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
        }

        .toggle-password svg {
            width: 19px;
            height: 19px;
        }

        .password-hint {
            font-size: 13px;
            color: #777;
            margin-top: -8px;
            margin-bottom: 18px;
        }

        .register-container button[type="submit"] {
            width: 100%;
            padding: 13px;
            background: #31583c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .register-container button[type="submit"]:hover {
            background: #24452e;
        }

        .pesan {
            color: #a33;
            margin-bottom: 15px;
        }

        .register-container a {
            color: #31583c;
        }

        @media (max-width: 700px) {
            .register-logo img {
                width: 120px;
                height: 55px;
            }

            .register-container {
                width: calc(100% - 30px);
                margin: 15px auto 40px;
                padding: 25px 20px;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

<div class="register-logo">
    <a href="../index.php">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>
</div>

<div class="register-container">

    <h1>Daftar Akun Kelana</h1>

    <p style="text-align:center;">
        Buat akun untuk melanjutkan penyewaan.
    </p>

    <?php if ($pesan !== "") { ?>
        <p class="pesan">
            <?php echo htmlspecialchars($pesan); ?>
        </p>
    <?php } ?>

    <form method="POST">

        <label>Nama Lengkap</label>
        <input type="text" name="nama"
            value="<?php echo htmlspecialchars($_POST["nama"] ?? ""); ?>"
            required>

        <label>Email</label>
        <input type="email" name="email"
            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
            required>

        <label>Nomor HP</label>
        <input type="text" name="no_hp"
            value="<?php echo htmlspecialchars($_POST["no_hp"] ?? ""); ?>">

        <label>Password</label>

        <div class="password-wrapper">
            <input
                type="password"
                name="password"
                id="password"
                placeholder="Masukkan password"
                required
            >

            <button type="button" class="toggle-password"
                onclick="togglePassword('password', this)"
                aria-label="Tampilkan password">

                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>

            </button>
        </div>

        <p class="password-hint">
            Password minimal 6 karakter. Disarankan menggunakan kombinasi huruf dan angka agar lebih aman.
        </p>

        <label>Konfirmasi Password</label>

        <div class="password-wrapper">
            <input
                type="password"
                name="konfirmasi_password"
                id="konfirmasi_password"
                placeholder="Masukkan ulang password"
                required
            >

            <button type="button" class="toggle-password"
                onclick="togglePassword('konfirmasi_password', this)"
                aria-label="Tampilkan password">

                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>

            </button>
        </div>

        <button type="submit">Daftar Sekarang</button>

    </form>

    <p style="text-align:center; margin-top:20px;">
        Sudah punya akun?
        <a href="login.php">Masuk di sini</a>
    </p>

</div>

<script>
function togglePassword(id, tombol) {
    const input = document.getElementById(id);

    if (input.type === "password") {
        input.type = "text";

        tombol.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        `;

    } else {
        input.type = "password";

        tombol.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3l18 18"/>
                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                <path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7s-3.5 7-10 7c-1.1 0-2.1-.2-3-.5"/>
                <path d="M6.2 6.2C3.5 8 2 12 2 12s3.5 7 10 7c1.1 0 2.1-.2 3-.5"/>
            </svg>
        `;
    }
}
</script>

</body>
</html>
