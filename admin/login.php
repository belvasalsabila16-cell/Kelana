<?php
session_start();
include "../config/database.php";

// Jika sudah login sebagai admin, langsung ke dashboard
if (isset($_SESSION["id_user"])) {

    $idUser = (int) $_SESSION["id_user"];

    $cek = mysqli_query(
        $conn,
        "SELECT role FROM user WHERE id_user = $idUser"
    );

    $data = mysqli_fetch_assoc($cek);

    if ($data && $data["role"] === "admin") {
        header("Location: dashboard.php");
        exit;
    }

    session_unset();
    session_destroy();
    session_start();
}

$pesan = "";

// Proses login admin
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $pesan = "Email dan password wajib diisi.";

    } else {

        $query = mysqli_prepare($conn, "
            SELECT * FROM `user`
            WHERE email = ? AND role = 'admin'
        ");

        mysqli_stmt_bind_param($query, "s", $email);
        mysqli_stmt_execute($query);

        $hasil = mysqli_stmt_get_result($query);

        if (mysqli_num_rows($hasil) === 1) {

            $user = mysqli_fetch_assoc($hasil);

            if (password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["id_user"] = $user["id_user"];
                $_SESSION["nama"] = $user["nama"];
                $_SESSION["email"] = $user["email"];

                header("Location: dashboard.php");
                exit;

            } else {
                $pesan = "Email atau password admin salah.";
            }

        } else {
            $pesan = "Email atau password admin salah.";
        }

        mysqli_stmt_close($query);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login Admin - Kelana</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        body {
            background-color: transparent;
            color: #293b30;
        }

        /* LOGO */
        .login-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px auto 0;
        }

        .login-logo img {
            width: 220px;
            height: 100px;
            object-fit: contain;
            display: block;
        }

        .login-logo a {
            display: inline-block;
            text-decoration: none;
        }

        /* CONTAINER LOGIN */
        .login-container {
            max-width: 450px;
            margin: 20px auto 60px;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,.1);
        }

        .login-container h1 {
            text-align: center;
            color: #31583c;
        }

        /* INPUT */
        .login-container input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        /* PASSWORD */
        .password-wrapper {
            position: relative;
            width: 100%;
            margin: 8px 0 18px;
        }

        .password-wrapper input {
            width: 100%;
            padding: 12px 45px 12px 12px;
            margin: 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
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

        .toggle-password:hover {
            background: transparent;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        /* TOMBOL MASUK */
        .login-container button[type="submit"] {
            width: 100%;
            padding: 13px;
            background: #31583c;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .login-container button[type="submit"]:hover {
            background: #25452e;
        }

        /* PESAN ERROR */
        .pesan {
            color: #a33;
            margin-bottom: 15px;
            text-align: center;
        }

        .login-container a {
            color: #31583c;
        }

        /* RESPONSIVE */
        @media (max-width: 700px) {

            .login-logo img {
                width: 120px;
                height: 55px;
            }

            .login-container {
                width: calc(100% - 30px);
                margin: 15px auto 40px;
                padding: 25px 20px;
                box-sizing: border-box;
            }
        }

    </style>
</head>

<body>

<!-- LOGO KELANA -->
<div class="login-logo">
    <a href="../index.php">
        <img src="../assets/images/logo-kelana.png" alt="Logo KELANA">
    </a>
</div>

<!-- FORM LOGIN ADMIN -->
<div class="login-container">

    <h1>Masuk ke Kelana</h1>

    <p style="text-align:center;">
        Silakan masuk menggunakan akun administrator Kelana.
    </p>

    <?php if ($pesan !== "") { ?>
        <p class="pesan">
            <?php echo htmlspecialchars($pesan); ?>
        </p>
    <?php } ?>

    <form method="POST">

        <label>Email Admin</label>

        <input
            type="email"
            name="email"
            placeholder="Masukkan email admin"
            required
        >

        <label>Password</label>

        <div class="password-wrapper">

            <input
                type="password"
                name="password"
                id="password"
                placeholder="Masukkan password admin"
                required
            >

            <button
                type="button"
                class="toggle-password"
                onclick="togglePassword()"
                aria-label="Tampilkan password"
            >

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

            </button>

        </div>

        <button type="submit">Masuk sebagai Admin</button>

    </form>

    <p style="text-align:center; margin-top:20px;">
        <a href="../index.php">Kembali ke Beranda</a>
    </p>

</div>

<script>

function togglePassword() {

    const input = document.getElementById("password");
    const tombol = document.querySelector(".toggle-password");

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

        tombol.setAttribute("aria-label", "Sembunyikan password");
        tombol.style.color = "#31583c";

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

        tombol.setAttribute("aria-label", "Tampilkan password");
        tombol.style.color = "#888";
    }
}

</script>

</body>
</html>