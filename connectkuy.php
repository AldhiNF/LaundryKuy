<?php
/**
 * File: connectkuy.php
 * Fungsi: Menghubungkan aplikasi web dengan database MySQL 'db_laundry'
 */

// 1. Pengaturan Konfigurasi Database (Sesuaikan dengan server lokal Laragon Anda)
$host     = "localhost";
$user     = "root";
$password = "";
$database = "db_laundry";

// 2. Membuka Koneksi
$koneksi = mysqli_connect($host, $user, $password, $database);

// 3. Pengecekan Keamanan Koneksi
if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// [FIX] Set charset utf8mb4 agar karakter unicode tersimpan benar
// dan mencegah encoding-based injection di MySQL versi lama.
mysqli_set_charset($koneksi, "utf8mb4");