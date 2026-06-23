<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keluar - LaundryKuy</title>
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy-900: #111d2b;
            --navy-800: #1e2d40;
            --navy-600: #2e4d6e;
            --cream-50:  #fdf8f0;
            --cream-200: #e8d5b7;
            --gold:      #c9a96e;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            background: var(--navy-900);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Lingkaran dekorasi latar */
        body::before {
            content: '';
            position: fixed;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,169,110,0.08) 0%, transparent 70%);
            top: -150px; left: -150px;
            pointer-events: none;
        }
        body::after {
            content: '';
            position: fixed;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(46,77,110,0.3) 0%, transparent 70%);
            bottom: -100px; right: -100px;
            pointer-events: none;
        }

        /* Kartu tengah */
        .logout-card {
            position: relative;
            z-index: 1;
            background: var(--navy-800);
            border: 1px solid rgba(232,213,183,0.12);
            border-radius: 24px;
            padding: 52px 48px;
            text-align: center;
            width: 360px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        @keyframes popIn {
            0%   { transform: scale(0.85); opacity: 0; }
            100% { transform: scale(1);    opacity: 1; }
        }

        /* Animasi mesin cuci */
        .mesin-wrap {
            width: 90px; height: 108px;
            background: var(--cream-50);
            border: 2.5px solid var(--cream-200);
            border-radius: 14px;
            position: relative;
            margin: 0 auto 28px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            animation: floatUp 2.5s ease-in-out infinite;
        }
        .mesin-wrap::before {
            content: '';
            position: absolute;
            top: 10px; left: 50%; transform: translateX(-50%);
            width: 60px; height: 18px;
            background: var(--cream-200);
            border-radius: 4px;
            opacity: 0.7;
        }
        .pintu {
            width: 60px; height: 60px;
            background: #fff;
            border: 4px solid var(--cream-200);
            border-radius: 50%;
            position: absolute;
            top: 38px; left: 50%; transform: translateX(-50%);
            overflow: hidden;
        }
        /* Warna gold = sukses logout */
        .pintu::after {
            content: '';
            position: absolute;
            width: 150%; height: 150%;
            background: var(--gold);
            top: 50%; left: -25%;
            border-radius: 40%;
            animation: muter 2s infinite ease-in-out;
            opacity: 0.9;
        }

        @keyframes muter {
            0%   { transform: rotate(0deg);   top: 50%; }
            50%  { top: 35%; }
            100% { transform: rotate(360deg); top: 50%; }
        }
        @keyframes floatUp {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-7px); }
        }

        .logout-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }

        .logout-sub {
            font-size: 13.5px;
            color: rgba(232,213,183,0.65);
            line-height: 1.6;
            margin-bottom: 28px;
        }

        /* Progress bar redirect */
        .redirect-bar-wrap {
            background: rgba(232,213,183,0.1);
            border-radius: 99px;
            height: 4px;
            overflow: hidden;
            margin-bottom: 12px;
        }
        .redirect-bar {
            height: 100%;
            background: var(--gold);
            border-radius: 99px;
            width: 0%;
            animation: fillBar 2.5s linear forwards;
        }
        @keyframes fillBar {
            0%   { width: 0%; }
            100% { width: 100%; }
        }

        .redirect-text {
            font-size: 11.5px;
            color: rgba(232,213,183,0.4);
            font-weight: 500;
        }

        /* Tombol manual jika tidak redirect */
        .btn-balik {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 20px;
            padding: 10px 22px;
            background: rgba(201,169,110,0.15);
            color: var(--gold);
            border: 1px solid rgba(201,169,110,0.3);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
        }
        .btn-balik:hover {
            background: rgba(201,169,110,0.25);
            color: var(--cream-200);
            border-color: rgba(201,169,110,0.5);
        }
    </style>
</head>
<body>

    <div class="logout-card">

        <!-- Animasi mesin cuci -->
        <div class="mesin-wrap">
            <div class="pintu"></div>
        </div>

        <div class="logout-title">Sampai Jumpa! 👋</div>
        <div class="logout-sub">
            Anda telah berhasil keluar dari sistem LaundryKuy.<br>
            Terima kasih telah menggunakan layanan kami.
        </div>

        <!-- Progress bar redirect -->
        <div class="redirect-bar-wrap">
            <div class="redirect-bar"></div>
        </div>
        <div class="redirect-text">Mengalihkan ke halaman login...</div>

        <a href="loginkuy.php" class="btn-balik">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
            </svg>
            Kembali ke Login
        </a>

    </div>

    <script>
        setTimeout(function() {
            window.location.href = 'loginkuy.php';
        }, 2500);
    </script>

</body>
</html>