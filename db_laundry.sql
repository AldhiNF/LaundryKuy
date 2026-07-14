-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 26, 2026 at 01:11 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_laundry`
--
CREATE DATABASE IF NOT EXISTS `db_laundry` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `db_laundry`;

-- --------------------------------------------------------

--
-- Table structure for table `t_biaya_op`
--

DROP TABLE IF EXISTS `t_biaya_op`;
CREATE TABLE `t_biaya_op` (
  `id_biaya` int NOT NULL,
  `tgl` date NOT NULL,
  `ket` text,
  `kategori` varchar(50) DEFAULT NULL,
  `jumlah` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `t_biaya_op`
--

INSERT INTO `t_biaya_op` (`id_biaya`, `tgl`, `ket`, `kategori`, `jumlah`) VALUES
(4, '2026-01-20', 'beli sabun cuci cair 5l', 'bahan baku', 75000),
(5, '2026-03-05', 'Bayar Listrik Bulan Februari', 'Listrik & Air', 320000),
(6, '2026-03-06', 'Bayar Air PDAM', 'Listrik & Air', 110000),
(9, '2026-04-05', 'Bayar Listrik Bulan Maret', 'Listrik & Air', 340000),
(10, '2026-04-06', 'Bayar Air PDAM', 'Listrik & Air', 115000),
(11, '2026-04-12', 'Beli Plastik Packing & Lakban', 'bahan baku', 90000),
(14, '2026-05-05', 'Bayar Listrik Bulan April', 'Listrik & Air', 360000),
(15, '2026-05-06', 'Bayar Air PDAM', 'Listrik & Air', 120000),
(19, '2026-06-05', 'Bayar Listrik Bulan Mei', 'Listrik & Air', 375000),
(20, '2026-06-06', 'Bayar Air PDAM', 'Listrik & Air', 125000);

-- --------------------------------------------------------

--
-- Table structure for table `t_layanan`
--

DROP TABLE IF EXISTS `t_layanan`;
CREATE TABLE `t_layanan` (
  `id_layanan` int NOT NULL,
  `nama_paket` varchar(100) NOT NULL,
  `harga_fix` int NOT NULL,
  `estimasi_jam` int DEFAULT '24' COMMENT 'Estimasi selesai dalam jam'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `t_layanan`
--

INSERT INTO `t_layanan` (`id_layanan`, `nama_paket`, `harga_fix`, `estimasi_jam`) VALUES
(1, 'cuci komplit reguler', 6000, 24),
(2, 'cuci kering kilat', 8000, 8),
(3, 'Cuci Kering (Setrika)', 7000, 24),
(4, 'Cuci Basah (Tanpa Setrika)', 5000, 24),
(5, 'Cuci Komplit Express (1 Hari)', 12000, 12),
(6, 'Cuci Bed Cover / Selimut', 15000, 24);

-- --------------------------------------------------------

--
-- Table structure for table `t_pelanggan`
--

DROP TABLE IF EXISTS `t_pelanggan`;
CREATE TABLE `t_pelanggan` (
  `id_pel` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `hp` varchar(20) DEFAULT NULL,
  `alamat` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `t_pelanggan`
--

INSERT INTO `t_pelanggan` (`id_pel`, `nama`, `hp`, `alamat`) VALUES
(1, 'Andi Saputra', '081234567890', 'Jl. Merdeka No. 1'),
(2, 'Budi Santoso', '089876543210', 'Jl. Melati No. 45'),
(3, 'Citra Lestari', '085611223344', 'Perumahan Indah Blok C'),
(4, 'Paijo', '081258957342', 'Jl. Manggis'),
(5, 'Siti Aminah', '081234567111', 'Jl. Pahlawan No. 10'),
(6, 'Rudi Hermawan', '085712345678', 'Perumahan Griya Asri Blok A2'),
(7, 'Diana Putri', '081998877665', 'Jl. Kenanga 3'),
(8, 'Ahmad Fauzi', '082233445566', 'Kosan Bu Sri, Kamar 4'),
(9, 'Maya Sari', '081344556677', 'Jl. Sudirman Gg. 2'),
(10, 'Bambang Pamungkas', '085677889900', 'Apartemen Sentral Lt 5'),
(11, 'Rina Nose', '081299887766', 'Jl. Diponegoro No 45'),
(12, 'Dimas Anggara', '087811223344', 'Perum Bukit Indah Blok C12'),
(13, 'Siska Kohl', '081122334455', 'Jl. Raya Darmo'),
(14, 'Tono Supriyanto', '089655443322', 'Jl. Kelinci No 8'),
(15, 'Lina Marlina', '082133221100', 'Kost Putri Mawar'),
(16, 'Kevin Sanjaya', '085766554433', 'Jl. Pemuda No 99'),
(17, 'Nia Ramadhani', '081399881122', 'Perum Elit Indah Blok VVIP'),
(18, 'Joko Anwar', '081988776655', 'Jl. Merak No 2'),
(19, 'Putri Tanjung', '082211223344', 'Jl. Kutilang 5'),
(20, 'Reza Rahadian', '085611335577', 'Kawasan Industri Rungkut'),
(21, 'Sukimin', '081122345678', 'surabaya'),
(24, 'Kutisari', '08914141414', 'Surabaya'),
(25, 'sungai', '0812222222', 'Surabaya'),
(26, 'Rara', '08585747474', '');

-- --------------------------------------------------------

--
-- Table structure for table `t_transaksi`
--

DROP TABLE IF EXISTS `t_transaksi`;
CREATE TABLE `t_transaksi` (
  `id_trans` int NOT NULL,
  `id_pel` int DEFAULT NULL,
  `id_layanan` int DEFAULT NULL,
  `id_user` int DEFAULT NULL,
  `tgl` datetime NOT NULL,
  `estimasi_selesai` datetime DEFAULT NULL,
  `id_voucher` int DEFAULT NULL,
  `diskon` decimal(10,2) DEFAULT '0.00',
  `total_sebelum_diskon` decimal(10,2) DEFAULT '0.00',
  `berat` decimal(10,2) NOT NULL,
  `harga_saat_transaksi` int NOT NULL,
  `total` int NOT NULL,
  `st_bayar` enum('belum lunas','lunas') DEFAULT 'belum lunas',
  `st_cuci` enum('diterima','proses','selesai','dikirim') NOT NULL DEFAULT 'diterima'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `t_transaksi`
--

INSERT INTO `t_transaksi` (`id_trans`, `id_pel`, `id_layanan`, `id_user`, `tgl`, `estimasi_selesai`, `id_voucher`, `diskon`, `total_sebelum_diskon`, `berat`, `harga_saat_transaksi`, `total`, `st_bayar`, `st_cuci`) VALUES
(1, 1, 1, NULL, '2026-03-01 08:30:00', '2026-03-02 08:30:00', NULL, '0.00', '18000.00', '3.00', 6000, 18000, 'lunas', 'dikirim'),
(2, 2, 2, NULL, '2026-03-02 09:15:00', '2026-03-03 09:15:00', 1, '3200.00', '32000.00', '4.00', 8000, 28800, 'lunas', 'dikirim'),
(3, 3, 3, NULL, '2026-03-04 10:00:00', '2026-03-05 10:00:00', NULL, '0.00', '17500.00', '2.50', 7000, 17500, 'lunas', 'dikirim'),
(4, 4, 4, NULL, '2026-03-05 14:20:00', '2026-03-06 14:20:00', 1, '2500.00', '25000.00', '5.00', 5000, 22500, 'lunas', 'dikirim'),
(5, 5, 5, NULL, '2026-03-07 11:10:00', '2026-03-08 11:10:00', 2, '5000.00', '36000.00', '3.00', 12000, 31000, 'lunas', 'dikirim'),
(6, 6, 6, NULL, '2026-03-09 13:00:00', '2026-03-10 13:00:00', NULL, '0.00', '30000.00', '2.00', 15000, 30000, 'lunas', 'dikirim'),
(7, 7, 5, NULL, '2026-03-11 16:30:00', '2026-03-12 16:30:00', 3, '9000.00', '60000.00', '5.00', 12000, 51000, 'lunas', 'dikirim'),
(8, 8, 1, NULL, '2026-03-14 09:00:00', '2026-03-15 09:00:00', NULL, '0.00', '24000.00', '4.00', 6000, 24000, 'lunas', 'dikirim'),
(9, 9, 2, NULL, '2026-03-16 08:45:00', '2026-03-17 08:45:00', NULL, '0.00', '16000.00', '2.00', 8000, 16000, 'lunas', 'dikirim'),
(10, 10, 3, NULL, '2026-03-18 10:20:00', '2026-03-19 10:20:00', NULL, '0.00', '21000.00', '3.00', 7000, 21000, 'lunas', 'dikirim'),
(11, 11, 4, NULL, '2026-03-19 15:30:00', '2026-03-20 15:30:00', NULL, '0.00', '15000.00', '3.00', 5000, 15000, 'lunas', 'dikirim'),
(12, 12, 5, NULL, '2026-03-21 11:00:00', '2026-03-22 11:00:00', 1, '2400.00', '24000.00', '2.00', 12000, 21600, 'lunas', 'dikirim'),
(13, 13, 6, NULL, '2026-03-24 14:15:00', '2026-03-25 14:15:00', 2, '5000.00', '45000.00', '3.00', 15000, 40000, 'lunas', 'dikirim'),
(14, 14, 1, NULL, '2026-03-26 09:30:00', '2026-03-27 09:30:00', NULL, '0.00', '15000.00', '2.50', 6000, 15000, 'lunas', 'dikirim'),
(15, 15, 2, NULL, '2026-03-28 16:00:00', '2026-03-29 16:00:00', 1, '4000.00', '40000.00', '5.00', 8000, 36000, 'lunas', 'dikirim'),
(16, 16, 3, NULL, '2026-03-30 08:10:00', '2026-03-31 08:10:00', NULL, '0.00', '28000.00', '4.00', 7000, 28000, 'lunas', 'dikirim'),
(17, 17, 4, NULL, '2026-04-01 10:45:00', '2026-04-02 10:45:00', NULL, '0.00', '10000.00', '2.00', 5000, 10000, 'lunas', 'dikirim'),
(18, 18, 5, NULL, '2026-04-03 13:20:00', '2026-04-04 13:20:00', 2, '5000.00', '48000.00', '4.00', 12000, 43000, 'lunas', 'dikirim'),
(19, 19, 6, NULL, '2026-04-05 09:50:00', '2026-04-06 09:50:00', 3, '9000.00', '60000.00', '4.00', 15000, 51000, 'lunas', 'dikirim'),
(20, 20, 1, NULL, '2026-04-07 15:10:00', '2026-04-08 15:10:00', NULL, '0.00', '18000.00', '3.00', 6000, 18000, 'lunas', 'dikirim'),
(21, 1, 2, NULL, '2026-04-09 11:30:00', '2026-04-10 11:30:00', 1, '2400.00', '24000.00', '3.00', 8000, 21600, 'lunas', 'dikirim'),
(22, 2, 3, NULL, '2026-04-11 08:00:00', '2026-04-12 08:00:00', NULL, '0.00', '14000.00', '2.00', 7000, 14000, 'lunas', 'dikirim'),
(23, 3, 4, NULL, '2026-04-13 14:40:00', '2026-04-14 14:40:00', NULL, '0.00', '17500.00', '3.50', 5000, 17500, 'lunas', 'dikirim'),
(24, 4, 5, NULL, '2026-04-15 16:15:00', '2026-04-16 16:15:00', 2, '5000.00', '30000.00', '2.50', 12000, 25000, 'lunas', 'dikirim'),
(25, 5, 6, NULL, '2026-04-17 09:20:00', '2026-04-18 09:20:00', NULL, '0.00', '30000.00', '2.00', 15000, 30000, 'lunas', 'dikirim'),
(26, 6, 1, NULL, '2026-04-19 11:50:00', '2026-04-20 11:50:00', NULL, '0.00', '24000.00', '4.00', 6000, 24000, 'lunas', 'dikirim'),
(27, 7, 2, NULL, '2026-04-21 13:30:00', '2026-04-22 13:30:00', 1, '3200.00', '32000.00', '4.00', 8000, 28800, 'lunas', 'dikirim'),
(28, 8, 3, NULL, '2026-04-23 08:45:00', '2026-04-24 08:45:00', NULL, '0.00', '21000.00', '3.00', 7000, 21000, 'lunas', 'dikirim'),
(29, 9, 4, NULL, '2026-04-25 15:00:00', '2026-04-26 15:00:00', NULL, '0.00', '20000.00', '4.00', 5000, 20000, 'lunas', 'dikirim'),
(30, 10, 5, NULL, '2026-04-27 10:10:00', '2026-04-28 10:10:00', 3, '10800.00', '72000.00', '6.00', 12000, 61200, 'lunas', 'dikirim'),
(31, 11, 6, NULL, '2026-04-28 14:25:00', '2026-04-29 14:25:00', 2, '5000.00', '45000.00', '3.00', 15000, 40000, 'lunas', 'dikirim'),
(32, 12, 1, NULL, '2026-04-30 09:40:00', '2026-05-01 09:40:00', NULL, '0.00', '15000.00', '2.50', 6000, 15000, 'lunas', 'dikirim'),
(33, 13, 2, NULL, '2026-05-02 11:15:00', '2026-05-03 11:15:00', 1, '2400.00', '24000.00', '3.00', 8000, 21600, 'lunas', 'dikirim'),
(34, 14, 3, NULL, '2026-05-04 16:30:00', '2026-05-05 16:30:00', NULL, '0.00', '28000.00', '4.00', 7000, 28000, 'lunas', 'dikirim'),
(35, 15, 4, NULL, '2026-05-06 08:20:00', '2026-05-07 08:20:00', NULL, '0.00', '10000.00', '2.00', 5000, 10000, 'lunas', 'dikirim'),
(36, 16, 5, NULL, '2026-05-08 13:50:00', '2026-05-09 13:50:00', 2, '5000.00', '36000.00', '3.00', 12000, 31000, 'lunas', 'dikirim'),
(37, 17, 6, NULL, '2026-05-10 10:40:00', '2026-05-11 10:40:00', 3, '11250.00', '75000.00', '5.00', 15000, 63750, 'lunas', 'dikirim'),
(38, 18, 1, NULL, '2026-05-12 15:00:00', '2026-05-13 15:00:00', NULL, '0.00', '12000.00', '2.00', 6000, 12000, 'lunas', 'dikirim'),
(39, 19, 2, NULL, '2026-05-14 09:10:00', '2026-05-15 09:10:00', 1, '4000.00', '40000.00', '5.00', 8000, 36000, 'lunas', 'dikirim'),
(40, 20, 3, NULL, '2026-05-15 14:25:00', '2026-05-16 14:25:00', NULL, '0.00', '17500.00', '2.50', 7000, 17500, 'lunas', 'dikirim'),
(41, 1, 4, NULL, '2026-05-17 11:30:00', '2026-05-18 11:30:00', NULL, '0.00', '25000.00', '5.00', 5000, 25000, 'lunas', 'dikirim'),
(42, 3, 5, NULL, '2026-05-19 08:45:00', '2026-05-20 08:45:00', 2, '5000.00', '48000.00', '4.00', 12000, 43000, 'lunas', 'dikirim'),
(43, 5, 6, NULL, '2026-05-21 16:10:00', '2026-05-22 16:10:00', NULL, '0.00', '30000.00', '2.00', 15000, 30000, 'lunas', 'dikirim'),
(44, 7, 1, NULL, '2026-05-23 10:20:00', '2026-05-24 10:20:00', NULL, '0.00', '18000.00', '3.00', 6000, 18000, 'lunas', 'dikirim'),
(45, 9, 2, NULL, '2026-05-25 13:40:00', '2026-05-26 13:40:00', 1, '2400.00', '24000.00', '3.00', 8000, 21600, 'lunas', 'dikirim'),
(46, 11, 3, NULL, '2026-05-27 09:50:00', '2026-05-28 09:50:00', NULL, '0.00', '14000.00', '2.00', 7000, 14000, 'lunas', 'dikirim'),
(47, 13, 4, NULL, '2026-05-29 15:00:00', '2026-05-30 15:00:00', NULL, '0.00', '15000.00', '3.00', 5000, 15000, 'lunas', 'dikirim'),
(48, 15, 5, NULL, '2026-05-31 11:15:00', '2026-06-01 11:15:00', 3, '9000.00', '60000.00', '5.00', 12000, 51000, 'lunas', 'dikirim'),
(49, 17, 6, NULL, '2026-06-02 08:30:00', '2026-06-03 08:30:00', 2, '5000.00', '45000.00', '3.00', 15000, 40000, 'lunas', 'dikirim'),
(50, 19, 1, NULL, '2026-06-04 14:45:00', '2026-06-05 14:45:00', NULL, '0.00', '24000.00', '4.00', 6000, 24000, 'lunas', 'dikirim'),
(51, 2, 2, NULL, '2026-06-06 10:00:00', '2026-06-07 10:00:00', 1, '3200.00', '32000.00', '4.00', 8000, 28800, 'lunas', 'dikirim'),
(52, 4, 3, NULL, '2026-06-08 16:20:00', '2026-06-09 16:20:00', NULL, '0.00', '21000.00', '3.00', 7000, 21000, 'lunas', 'dikirim'),
(53, 6, 4, NULL, '2026-06-10 09:10:00', '2026-06-11 09:10:00', NULL, '0.00', '20000.00', '4.00', 5000, 20000, 'lunas', 'dikirim'),
(54, 8, 5, NULL, '2026-06-13 13:30:00', '2026-06-14 13:30:00', 2, '5000.00', '36000.00', '3.00', 12000, 31000, 'lunas', 'dikirim'),
(55, 21, 1, 4, '2026-06-20 14:03:32', '2026-06-21 14:03:32', NULL, '0.00', '35400.00', '5.90', 6000, 35400, 'lunas', 'dikirim'),
(58, 24, 5, 4, '2026-06-23 05:03:43', '2026-06-23 17:03:43', NULL, '0.00', '106800.00', '8.90', 12000, 106800, 'lunas', 'dikirim'),
(59, 20, 5, 4, '2026-06-23 05:40:03', '2026-06-23 17:40:03', 4, '120000.00', '600000.00', '50.00', 12000, 480000, 'lunas', 'dikirim'),
(60, 25, 5, 4, '2026-06-23 07:20:05', '2026-06-23 19:20:05', NULL, '0.00', '64800.00', '5.40', 12000, 64800, 'lunas', 'dikirim'),
(61, 19, 6, 4, '2026-06-23 07:21:16', '2026-06-24 07:21:16', NULL, '0.00', '37500.00', '2.50', 15000, 37500, 'lunas', 'dikirim'),
(62, 26, 2, 4, '2026-06-24 07:58:28', '2026-06-24 15:58:28', 4, '24000.00', '120000.00', '15.00', 8000, 96000, 'lunas', 'dikirim');

-- --------------------------------------------------------

--
-- Table structure for table `t_user`
--

DROP TABLE IF EXISTS `t_user`;
CREATE TABLE `t_user` (
  `id_user` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('kasir','owner') NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `t_user`
--

INSERT INTO `t_user` (`id_user`, `username`, `password`, `role`, `status`) VALUES
(3, 'owner', '$2y$10$lbnnTePByzs4cHuC7igVN.cKx55Dhn5E.0j5lItTLlv/xUaCBwIXm', 'owner', 'aktif'),
(4, 'Kasir_pagi', '$2y$10$lmw8eGCakMmEBXkCdJo2X.s6rTGW9CdBFcEnuaEEALNtML0UjWT2m', 'kasir', 'aktif'),
(5, 'Kasir_malam', '$2y$10$VjTb.aZlqwzmNhEDbsTy/.N5OibOtx.Jr.xKNHj6mfwiHpDa9cQlu', 'kasir', 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `t_voucher`
--

DROP TABLE IF EXISTS `t_voucher`;
CREATE TABLE `t_voucher` (
  `id_voucher` int NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `tipe` enum('persen','nominal') NOT NULL,
  `nilai` decimal(10,2) NOT NULL,
  `min_transaksi` decimal(10,2) DEFAULT '0.00',
  `max_diskon` decimal(10,2) DEFAULT '0.00' COMMENT '0 = tidak ada batas',
  `kuota` int DEFAULT '0' COMMENT '0 = tidak terbatas',
  `terpakai` int DEFAULT '0',
  `aktif` tinyint(1) DEFAULT '1',
  `tgl_mulai` date DEFAULT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `t_voucher`
--

INSERT INTO `t_voucher` (`id_voucher`, `kode`, `nama`, `tipe`, `nilai`, `min_transaksi`, `max_diskon`, `kuota`, `terpakai`, `aktif`, `tgl_mulai`, `tgl_selesai`, `created_at`) VALUES
(1, 'NEW10', 'Diskon Pengguna Baru 10%', 'persen', '10.00', '20000.00', '10000.00', 50, 10, 1, '2026-01-01', '2026-12-31', '2026-01-01 03:00:00'),
(2, 'HEMAT5K', 'Potongan Harga 5 Ribu', 'nominal', '5000.00', '30000.00', '5000.00', 100, 9, 1, '2026-03-01', '2026-07-31', '2026-02-28 03:05:00'),
(3, 'GAJIAN', 'Diskon Gajian 15%', 'persen', '15.00', '50000.00', '20000.00', 30, 5, 1, '2026-01-25', '2026-12-31', '2026-01-20 01:00:00'),
(4, 'YUKLAUNDRY', 'Voucher Keloyalan Pelanggan', 'persen', '20.00', '50000.00', '0.00', 50, 2, 1, '2025-01-01', '2026-12-31', '2026-06-23 05:38:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `t_biaya_op`
--
ALTER TABLE `t_biaya_op`
  ADD PRIMARY KEY (`id_biaya`);

--
-- Indexes for table `t_layanan`
--
ALTER TABLE `t_layanan`
  ADD PRIMARY KEY (`id_layanan`);

--
-- Indexes for table `t_pelanggan`
--
ALTER TABLE `t_pelanggan`
  ADD PRIMARY KEY (`id_pel`),
  ADD UNIQUE KEY `idx_hp_unique` (`hp`);

--
-- Indexes for table `t_transaksi`
--
ALTER TABLE `t_transaksi`
  ADD PRIMARY KEY (`id_trans`),
  ADD KEY `id_pel` (`id_pel`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `fk_transaksi_voucher` (`id_voucher`),
  ADD KEY `fk_transaksi_layanan` (`id_layanan`);

--
-- Indexes for table `t_user`
--
ALTER TABLE `t_user`
  ADD PRIMARY KEY (`id_user`);

--
-- Indexes for table `t_voucher`
--
ALTER TABLE `t_voucher`
  ADD PRIMARY KEY (`id_voucher`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `t_biaya_op`
--
ALTER TABLE `t_biaya_op`
  MODIFY `id_biaya` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `t_layanan`
--
ALTER TABLE `t_layanan`
  MODIFY `id_layanan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `t_pelanggan`
--
ALTER TABLE `t_pelanggan`
  MODIFY `id_pel` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `t_transaksi`
--
ALTER TABLE `t_transaksi`
  MODIFY `id_trans` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `t_user`
--
ALTER TABLE `t_user`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `t_voucher`
--
ALTER TABLE `t_voucher`
  MODIFY `id_voucher` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `t_transaksi`
--
ALTER TABLE `t_transaksi`
  ADD CONSTRAINT `fk_transaksi_layanan` FOREIGN KEY (`id_layanan`) REFERENCES `t_layanan` (`id_layanan`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_voucher` FOREIGN KEY (`id_voucher`) REFERENCES `t_voucher` (`id_voucher`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `t_transaksi_ibfk_1` FOREIGN KEY (`id_pel`) REFERENCES `t_pelanggan` (`id_pel`) ON DELETE SET NULL,
  ADD CONSTRAINT `t_transaksi_ibfk_3` FOREIGN KEY (`id_user`) REFERENCES `t_user` (`id_user`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
