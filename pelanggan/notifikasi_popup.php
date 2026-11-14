
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["id_user"])) {
    return;
}

$idPelangganPopup = (int) $_SESSION["id_user"];

$cekRolePopup = mysqli_query(
    $conn,
    "SELECT role FROM user WHERE id_user = $idPelangganPopup"
);

$rolePopup = mysqli_fetch_assoc($cekRolePopup);

if (!$rolePopup || $rolePopup["role"] !== "pelanggan") {
    return;
}

// Tentukan alamat endpoint berdasarkan halaman
$scriptName = $_SERVER["SCRIPT_NAME"];

if (strpos($scriptName, "/pelanggan/") !== false) {
    $urlCekNotifikasi = "cek_notifikasi.php";
    $urlHalamanNotifikasi = "notifikasi.php";
} else {
    $urlCekNotifikasi = "pelanggan/cek_notifikasi.php";
    $urlHalamanNotifikasi = "pelanggan/notifikasi.php";
}
?>

<style>
    .kelana-popup {
        position: fixed;
        right: 25px;
        bottom: 25px;
        width: 320px;
        max-width: calc(100% - 40px);
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0,0,0,.2);
        z-index: 99999;
        border-left: 5px solid #d69b35;
        display: none;
    }

    .kelana-popup h3 {
        margin: 0 0 10px;
        color: #8a5b16;
    }

    .kelana-popup p {
        margin: 0 0 12px;
        color: #333;
        line-height: 1.5;
    }

    .kelana-popup a {
        color: #8a5b16;
        font-weight: bold;
        text-decoration: none;
    }

    .kelana-popup-tutup {
        position: absolute;
        top: 8px;
        right: 12px;
        border: none;
        background: none;
        font-size: 22px;
        cursor: pointer;
    }
</style>

<div class="kelana-popup" id="kelanaPopup">
    <button type="button"
        class="kelana-popup-tutup"
        onclick="tutupPopupKelana()">×</button>

    <h3>Notifikasi Baru!</h3>
    <p id="kelanaPopupPesan"></p>

    <a href="<?= htmlspecialchars($urlHalamanNotifikasi) ?>">
        Lihat Notifikasi
    </a>
</div>

<script>
(function () {
    const idPelanggan = <?= $idPelangganPopup ?>;
    const urlCek = <?= json_encode($urlCekNotifikasi) ?>;

    const kunciCursor = "kelana_notif_cursor_" + idPelanggan;

    let cursor = sessionStorage.getItem(kunciCursor);
    let pemeriksaanPertama = cursor === null;

    async function periksaNotifikasi() {
        try {
            const response = await fetch(
                urlCek + "?after_id=" + (cursor || 0),
                { cache: "no-store" }
            );

            if (!response.ok) return;

            const data = await response.json();

            if (!data.success) return;

            const daftar = data.notifikasi || [];

            if (daftar.length > 0) {
                const idTerakhir = Math.max(
                    ...daftar.map(item => Number(item.id_notifikasi))
                );

                if (!pemeriksaanPertama) {
                    const terbaru = daftar[daftar.length - 1];

                    document.getElementById(
                        "kelanaPopupPesan"
                    ).textContent = terbaru.pesan;

                    document.getElementById(
                        "kelanaPopup"
                    ).style.display = "block";
                }

                cursor = String(idTerakhir);

                sessionStorage.setItem(
                    kunciCursor,
                    cursor
                );
            }

            pemeriksaanPertama = false;

        } catch (error) {
            console.error("Gagal memeriksa notifikasi:", error);
        }
    }

    periksaNotifikasi();

    setInterval(periksaNotifikasi, 5000);
})();

function tutupPopupKelana() {
    document.getElementById("kelanaPopup").style.display = "none";
}
</script>
