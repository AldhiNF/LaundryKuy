<?php
// 1. Mulai Session (Sangat penting untuk menyimpan data user yang sedang aktif agar tidak ter-logout saat pindah halaman)
session_start();

// 2. Cek apakah user sebenarnya SUDAH login sebelumnya.
// Jika sudah memiliki session 'role', sistem akan langsung melempar user kembali ke dashboard
// sesuai jabatannya agar mereka tidak bisa melihat form login ini lagi.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'kasir') { 
        header("Location: dashboard_kasirkuy.php"); 
        exit(); 
    } else if ($_SESSION['role'] == 'owner') { 
        header("Location: dashboard_ownerkuy.php"); 
        exit(); 
    }
}

// 3. Panggil file koneksi untuk menyambungkan aplikasi ini dengan database MySQL
include 'connectkuy.php';

// Siapkan variabel kosong untuk menampung pesan error (jika nanti loginnya gagal)
$pesan_error = "";

// 4. Logika utama yang akan berjalan KETIKA tombol "Login" ditekan pada form di bawah
if (isset($_POST['btn_login'])) {
    
    // Tangkap inputan username dari form
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // -----------------------------------------------------------------------
    // SISTEM LOGIN AMAN - PREPARED STATEMENT + PASSWORD_VERIFY
    // Cara kerja:
    // - Kita hanya cari berdasarkan USERNAME saja ke database
    // - Password TIDAK pernah dikirim langsung ke SQL (anti SQL Injection)
    // - Pengecekan password dilakukan di PHP pakai password_verify()
    //   yang membandingkan inputan dengan hash yang tersimpan di database
    // -----------------------------------------------------------------------
    // [FIX] Ambil kolom spesifik saja, bukan SELECT * (hindari password hash masuk memori tidak perlu)
    $stmt = mysqli_prepare($koneksi, "SELECT id_user, username, password, role, status FROM t_user WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    if (mysqli_num_rows($hasil) > 0) {
        $data = mysqli_fetch_assoc($hasil);

        // Bandingkan password inputan dengan hash yang ada di database
        if (password_verify($password, $data['password'])) {

            // Cek apakah akun sedang dinonaktifkan oleh Owner
            if (isset($data['status']) && $data['status'] == 'nonaktif') {
                $pesan_error = "Akun Anda sedang dinonaktifkan. Hubungi Owner untuk informasi lebih lanjut.";
            } else {
                // Password cocok & akun aktif
                // [FIX] session_regenerate_id mencegah Session Fixation Attack:
                // attacker tidak bisa memaksa korban pakai session ID yang sudah diketahui
                session_regenerate_id(true);
                $_SESSION['id_user']  = $data['id_user'];
                $_SESSION['username'] = $data['username'];
                $_SESSION['role']     = $data['role'];

                if ($data['role'] == 'kasir') {
                    header("Location: dashboard_kasirkuy.php");
                } else if ($data['role'] == 'owner') {
                    header("Location: dashboard_ownerkuy.php");
                }
                exit();
            }

        } else {
            // Username ada tapi password salah
            $pesan_error = "Username atau Password salah!";
        }
    } else {
        // Username tidak ditemukan di database
        $pesan_error = "Username atau Password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LaundryKuy</title>
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --navy-900: #111d2b;
            --navy-800: #1e2d40;
            --navy-600: #2e4d6e;
            --cream-50:  #fdf8f0;
            --cream-100: #f5edd8;
            --cream-200: #e8d5b7;
            --gold:      #c9a96e;
            --gold-dark: #a8874f;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            background: var(--navy-900);
            overflow: hidden;
        }

        /* Panel kiri — dekorasi Navy */
        .login-left {
            flex: 1;
            background: var(--navy-800);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px;
            position: relative;
            overflow: hidden;
        }

        /* Lingkaran dekorasi background */
        .login-left::before {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,169,110,0.12) 0%, transparent 70%);
            top: -100px; left: -100px;
        }
        .login-left::after {
            content: '';
            position: absolute;
            width: 350px; height: 350px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(46,77,110,0.4) 0%, transparent 70%);
            bottom: -80px; right: -80px;
        }

        .login-left-content { position: relative; z-index: 1; text-align: center; }

        /* Animasi mesin cuci Navy Cream */
        .mesin-wrap {
            width: 110px; height: 130px;
            background: var(--cream-50);
            border: 3px solid var(--cream-200);
            border-radius: 16px;
            position: relative;
            margin: 0 auto 32px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            animation: floatUp 3s ease-in-out infinite;
        }
        .mesin-wrap::before {
            content: '';
            position: absolute;
            top: 12px; left: 50%; transform: translateX(-50%);
            width: 72px; height: 22px;
            background: var(--cream-100);
            border-radius: 5px;
            border: 1.5px solid var(--cream-200);
        }
        .pintu {
            width: 70px; height: 70px;
            background: #fff;
            border: 5px solid var(--cream-200);
            border-radius: 50%;
            position: absolute;
            top: 46px; left: 50%; transform: translateX(-50%);
            overflow: hidden;
        }
        .pintu::after {
            content: '';
            position: absolute;
            width: 150%; height: 150%;
            background: var(--gold);
            top: 50%; left: -25%;
            border-radius: 40%;
            animation: muter 1.8s infinite ease-in-out;
            opacity: 0.85;
        }

        @keyframes muter {
            0%   { transform: rotate(0deg); top: 50%; }
            50%  { top: 35%; }
            100% { transform: rotate(360deg); top: 50%; }
        }
        @keyframes floatUp {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-8px); }
        }

        .login-brand {
            font-size: 28px;
            font-weight: 800;
            color: var(--cream-200);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        .login-tagline {
            font-size: 14px;
            color: var(--gold);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Fitur list */
        .login-features {
            margin-top: 48px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            text-align: left;
        }
        .login-feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(232,213,183,0.75);
            font-size: 13px;
            font-weight: 500;
        }
        .login-feature-icon {
            width: 34px; height: 34px;
            background: rgba(201,169,110,0.15);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: var(--gold);
            font-size: 16px;
            flex-shrink: 0;
        }

        /* Panel kanan — form */
        .login-right {
            width: 440px;
            background: var(--cream-50);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 44px;
            position: relative;
        }

        .login-right-inner { width: 100%; }

        .login-greeting {
            font-size: 24px;
            font-weight: 800;
            color: var(--navy-800);
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .login-greeting-sub {
            font-size: 13.5px;
            color: #7a92a8;
            margin-bottom: 36px;
            font-weight: 500;
        }

        .form-group { margin-bottom: 20px; }

        .form-label-kuy {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--navy-800);
            margin-bottom: 7px;
            letter-spacing: 0.2px;
        }

        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--gold);
            font-size: 17px;
            pointer-events: none;
        }
        .kuy-login-input {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1.5px solid var(--cream-200);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--navy-800);
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .kuy-login-input:focus {
            border-color: var(--navy-600);
            box-shadow: 0 0 0 3px rgba(46,77,110,0.12);
        }
        .kuy-login-input::placeholder { color: #b0c0cc; }

        /* Toggle password */
        .pw-toggle {
            position: absolute;
            right: 14px; top: 50%; transform: translateY(-50%);
            cursor: pointer;
            color: #b0c0cc;
            font-size: 17px;
            transition: color 0.2s;
        }
        .pw-toggle:hover { color: var(--navy-600); }

        /* Tombol login */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--navy-800);
            color: var(--cream-200);
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
        }
        .btn-login:hover {
            background: var(--navy-600);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(30,45,64,0.25);
        }
        .btn-login:active { transform: translateY(0); }

        /* Footer login */
        .login-footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: #b0c0cc;
        }
        .login-footer strong { color: var(--gold); }

        /* Loading overlay */
        #loadingOverlay {
            background: var(--navy-900);
        }
        .loading-mesin {
            width: 90px; height: 110px;
            background: var(--cream-50);
            border: 3px solid var(--cream-200);
            border-radius: 14px;
            position: relative;
            margin: 0 auto 20px;
        }
        .loading-mesin::before {
            content: '';
            position: absolute;
            top: 10px; left: 50%; transform: translateX(-50%);
            width: 60px; height: 18px;
            background: var(--cream-100);
            border-radius: 4px;
        }
        .loading-pintu {
            width: 58px; height: 58px;
            background: #fff;
            border: 4px solid var(--cream-200);
            border-radius: 50%;
            position: absolute;
            top: 40px; left: 50%; transform: translateX(-50%);
            overflow: hidden;
        }
        .loading-pintu::after {
            content: '';
            position: absolute;
            width: 150%; height: 150%;
            background: var(--gold);
            top: 50%; left: -25%;
            border-radius: 40%;
            animation: muter 1.2s infinite linear;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right {
                width: 100%;
                padding: 40px 28px;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 justify-content-center align-items-center" style="z-index:9999;">
        <div class="text-center">
            <div class="loading-mesin"><div class="loading-pintu"></div></div>
            <h5 class="text-white fw-bold mb-1" style="font-family:'Plus Jakarta Sans',sans-serif;">Sedang Memverifikasi...</h5>
            <p style="color:var(--gold); font-size:13px; font-family:'Plus Jakarta Sans',sans-serif;">Mohon tunggu sebentar</p>
        </div>
    </div>

    <!-- Panel Kiri -->
    <div class="login-left">
        <div class="login-left-content">
            <div class="mesin-wrap"><div class="pintu"></div></div>
            <div class="login-brand">
                <img src="assets/iconlog.png" alt="Logo" style="height:28px; filter:brightness(0) invert(1); margin-right:8px; margin-top:-4px; vertical-align:middle;">
                LaundryKuy
            </div>
            <div class="login-tagline">Sistem Manajemen Laundry Modern</div>

            <div class="login-features">
                <div class="login-feature-item">
                    <div class="login-feature-icon"><i class="bi bi-shield-lock-fill"></i></div>
                    <span>Sistem login aman dengan enkripsi password</span>
                </div>
                <div class="login-feature-item">
                    <div class="login-feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <span>Laporan keuangan & analisis bisnis lengkap</span>
                </div>
                <div class="login-feature-item">
                    <div class="login-feature-icon"><i class="bi bi-people-fill"></i></div>
                    <span>Manajemen pelanggan & karyawan terpusat</span>
                </div>
                <div class="login-feature-item">
                    <div class="login-feature-icon"><i class="bi bi-receipt"></i></div>
                    <span>Kasir cepat & cetak struk otomatis</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel Kanan — Form Login -->
    <div class="login-right">
        <div class="login-right-inner">

            <div class="login-greeting">Selamat Datang !!!</div>
            <div class="login-greeting-sub">Masuk ke panel manajemen LaundryKuy Anda</div>

            <form id="formLogin" method="POST" action="">

                <!-- Username -->
                <div class="form-group">
                    <label class="form-label-kuy">Username</label>
                    <div class="input-wrap">
                        <i class="bi bi-person-fill input-icon"></i>
                        <input type="text" class="kuy-login-input" name="username"
                            placeholder="Masukkan username..." required autocomplete="username">
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label-kuy">Password</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock-fill input-icon"></i>
                        <input type="password" class="kuy-login-input" name="password"
                            id="inputPassword"
                            placeholder="Masukkan password..." required autocomplete="current-password">
                        <i class="bi bi-eye pw-toggle" id="pwToggle"
                            onclick="togglePw()"></i>
                    </div>
                </div>

                <button type="submit" name="btn_login" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i>
                    MASUK SEKARANG
                </button>

            </form>

            <div class="login-footer">
                LaundryKuy &copy; <?php echo date('Y'); ?> &nbsp;·&nbsp;
                <strong>Sistem Manajemen Laundry</strong>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle show/hide password
        function togglePw() {
            const inp = document.getElementById('inputPassword');
            const ico = document.getElementById('pwToggle');
            if (inp.type === 'password') {
                inp.type = 'text';
                ico.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                inp.type = 'password';
                ico.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        // Submit dengan animasi loading
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            e.preventDefault();
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.remove('d-none');
            overlay.classList.add('d-flex');
            let inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'btn_login'; inp.value = '1';
            this.appendChild(inp);
            setTimeout(() => { this.submit(); }, 1500);
        });

        // Fix bug BFCache
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                const overlay = document.getElementById('loadingOverlay');
                overlay.classList.remove('d-flex');
                overlay.classList.add('d-none');
            }
        });
    </script>

    <?php if ($pesan_error != ""): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Akses Ditolak!',
            text: '<?php echo addslashes($pesan_error); ?>',
            confirmButtonColor: '#1e2d40',
            confirmButtonText: 'Coba Lagi'
        });
    </script>
    <?php endif; ?>

</body>
</html>