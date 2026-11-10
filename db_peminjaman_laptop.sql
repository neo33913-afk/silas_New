-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 10 Nov 2026 pada 07.18
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_peminjaman_laptop`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `laptops`
--

CREATE TABLE `laptops` (
  `id_laptop` int(11) NOT NULL,
  `kode_laptop` varchar(100) NOT NULL,
  `merk` varchar(20) NOT NULL,
  `tipe` varchar(10) DEFAULT NULL,
  `kondisi` varchar(255) DEFAULT NULL,
  `status` varchar(35) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `laptops`
--

INSERT INTO `laptops` (`id_laptop`, `kode_laptop`, `merk`, `tipe`, `kondisi`, `status`) VALUES
(1, 'LTP-001', 'Dell Inspiron', '7460', 'Baik', 'tersedia'),
(2, 'LTP-002', 'Assus TUF', 'Gaming 16', 'Baik', 'tersedia'),
(3, 'LTP-003', 'Axio', 'Hype 5', 'Baik', 'tersedia'),
(4, 'LTP-004', 'Axio', 'Pongo 755', 'Baik', 'tersedia'),
(5, 'LTP-005', 'Acer Predator Helios', '18 AI', 'Baik', 'dipinjam');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_peminjaman` int(11) NOT NULL,
  `id_users` int(11) NOT NULL,
  `id_laptop` int(11) NOT NULL,
  `id_petugas` int(11) DEFAULT NULL,
  `tanggal_pinjam` date NOT NULL,
  `akan_kembali` date NOT NULL,
  `status` varchar(35) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peminjaman`
--

INSERT INTO `peminjaman` (`id_peminjaman`, `id_users`, `id_laptop`, `id_petugas`, `tanggal_pinjam`, `akan_kembali`, `status`) VALUES
(1, 4, 5, 2, '2026-11-10', '2026-11-17', 'dipinjam');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id_pengembalian` int(11) NOT NULL,
  `id_peminjaman` int(11) NOT NULL,
  `id_petugas` int(11) NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `kondisi_kembali` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_users` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','petugas','siswa') NOT NULL DEFAULT 'siswa',
  `kelas` varchar(20) DEFAULT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_users`, `nama`, `username`, `password`, `role`, `kelas`, `no_hp`, `email`) VALUES
(1, 'Andi Darmawan', 'admin', '$2b$10$TMvc9CraYPIxi0S9bb8Tvuax4fup36qKd43o5f3KDb4LDG4iRImf.', 'admin', NULL, NULL, NULL),
(2, 'Budi Santoso', 'petugas', '$2b$10$vfsFbEOW3UBxwS7EmU.mRest8i5RtqMb9iDKqLCYaF5qRbqZyDv4m', 'petugas', NULL, NULL, NULL),
(3, 'Citra Lestari', 'siswa', '$2b$10$S2YEEewz5EpvqXsZNFnIb.IZm1qtIQBbQiBF.176C3qoBTbkMDba6', 'siswa', 'XII RPL 2', NULL, NULL),
(4, 'Amfidza neo danra', 'Danara', '$2y$10$YoPxB/o/K7An9r6zMGqIrOr/EfrynljcHEEzZYTbFMXiHq3UgWkkO', 'siswa', 'XII RPL 2', '085784691260', 'neo33913@gmail.com');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `laptops`
--
ALTER TABLE `laptops`
  ADD PRIMARY KEY (`id_laptop`),
  ADD UNIQUE KEY `kode_laptop` (`kode_laptop`);

--
-- Indeks untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_peminjaman`),
  ADD KEY `id_users` (`id_users`),
  ADD KEY `id_laptop` (`id_laptop`),
  ADD KEY `fk_peminjaman_petugas` (`id_petugas`);

--
-- Indeks untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id_pengembalian`),
  ADD KEY `id_peminjaman` (`id_peminjaman`),
  ADD KEY `fk_pengembalian_petugas` (`id_petugas`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_users`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `laptops`
--
ALTER TABLE `laptops`
  MODIFY `id_laptop` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id_peminjaman` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id_pengembalian` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_users` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `fk_peminjaman_petugas` FOREIGN KEY (`id_petugas`) REFERENCES `users` (`id_users`),
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_users`) REFERENCES `users` (`id_users`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`id_laptop`) REFERENCES `laptops` (`id_laptop`);

--
-- Ketidakleluasaan untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `fk_pengembalian_petugas` FOREIGN KEY (`id_petugas`) REFERENCES `users` (`id_users`),
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id_peminjaman`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
