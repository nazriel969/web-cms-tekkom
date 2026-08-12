-- ============================================================
--  CMS Sekolah — Database Setup
--  Database : db_cms_sekolah
--  Dibuat   : 2024
--  Cara pakai:
--    1. Buka phpMyAdmin → New → buat database "db_cms_sekolah"
--    2. Klik database → tab Import → pilih file ini → Go
--    atau via CLI: mysql -u root -p db_cms_sekolah < db_cms_sekolah.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
-- 1. USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_lengkap` VARCHAR(150) NOT NULL,
  `username`     VARCHAR(80)  NOT NULL UNIQUE,
  `email`        VARCHAR(150) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `role`         ENUM('admin','editor') NOT NULL DEFAULT 'editor',
  `foto`         VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. KATEGORI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategori` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `slug`          VARCHAR(120) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. BERITA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `berita` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      DEFAULT NULL,
  `kategori_id` INT(11)      DEFAULT NULL,
  `judul`       VARCHAR(255) NOT NULL,
  `slug`        VARCHAR(270) NOT NULL UNIQUE,
  `isi_berita`  LONGTEXT     NOT NULL,
  `gambar`      VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('published','draft') NOT NULL DEFAULT 'draft',
  `views`       INT(11)      NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`      (`status`),
  KEY `idx_created_at`  (`created_at`),
  KEY `idx_kategori_id` (`kategori_id`),
  KEY `idx_user_id`     (`user_id`),
  CONSTRAINT `fk_berita_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_berita_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. PENGUMUMAN
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengumuman` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `judul`           VARCHAR(255) NOT NULL,
  `slug`            VARCHAR(270) NOT NULL UNIQUE,
  `isi_pengumuman`  LONGTEXT     NOT NULL,
  `file_lampiran`   VARCHAR(255) DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. GALERI
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `galeri` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `judul`       VARCHAR(255) NOT NULL,
  `foto`        VARCHAR(255) DEFAULT NULL,
  `keterangan`  TEXT         DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. PROFIL SEKOLAH
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profil_sekolah` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_sekolah`     VARCHAR(200) NOT NULL DEFAULT '',
  `npsn`             VARCHAR(20)  DEFAULT NULL,
  `sambutan_kepsek`  LONGTEXT     DEFAULT NULL,
  `foto_kepsek`      VARCHAR(255) DEFAULT NULL,
  `sejarah`          LONGTEXT     DEFAULT NULL,
  `visi`             TEXT         DEFAULT NULL,
  `misi`             TEXT         DEFAULT NULL,
  `alamat`           TEXT         DEFAULT NULL,
  `telepon`          VARCHAR(30)  DEFAULT NULL,
  `email`            VARCHAR(150) DEFAULT NULL,
  `map_embed`        TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. PESAN KONTAK
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pesan_kontak` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `nama`       VARCHAR(150) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `subjek`     VARCHAR(255) NOT NULL,
  `pesan`      TEXT         NOT NULL,
  `dibaca`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dibaca`     (`dibaca`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DATA AWAL (Seed)
-- ============================================================

-- ── Admin default ────────────────────────────────────────────
--  username : admin
--  password : Admin@1234   (bcrypt, cost 12)
--  GANTI PASSWORD setelah pertama kali login!
INSERT INTO `users` (`nama_lengkap`, `username`, `email`, `password`, `role`) VALUES
('Administrator', 'admin', 'admin@sekolah.sch.id',
 '$2y$12$kclT7g78mCbDl1MQPW/SwOZ6OfXCJEVXccDuA2y5AJfbwDy.0/qaG', 'admin');
-- Hash di atas adalah bcrypt (cost=12) dari string: "Admin@1234"

-- ── Kategori awal ────────────────────────────────────────────
INSERT INTO `kategori` (`nama_kategori`, `slug`) VALUES
('Akademik',         'akademik'),
('Ekstrakurikuler',  'ekstrakurikuler'),
('Prestasi',         'prestasi'),
('Pengumuman',       'pengumuman-kategori'),
('Kegiatan Sekolah', 'kegiatan-sekolah'),
('Informasi Umum',   'informasi-umum');

-- ── Profil sekolah (1 row) ───────────────────────────────────
INSERT INTO `profil_sekolah`
  (`nama_sekolah`, `npsn`, `visi`, `misi`, `sejarah`, `sambutan_kepsek`, `alamat`, `telepon`, `email`)
VALUES (
  'SMA Negeri 1 Contoh',
  '12345678',
  'Menjadi sekolah unggulan yang menghasilkan lulusan cerdas, berkarakter, dan berdaya saing global.',
  '1. Menyelenggarakan pembelajaran berkualitas dan inovatif.\n2. Mengembangkan potensi siswa melalui kegiatan akademik dan non-akademik.\n3. Membentuk karakter siswa yang berakhlak mulia dan bertanggung jawab.\n4. Menjalin kemitraan yang harmonis dengan masyarakat dan dunia usaha.',
  'SMA Negeri 1 Contoh didirikan pada tahun 1970 atas prakarsa pemerintah daerah untuk memenuhi kebutuhan pendidikan menengah atas di wilayah ini. Sejak berdiri, sekolah ini telah meluluskan ribuan alumni yang kini berperan aktif di berbagai bidang.',
  'Puji syukur kami panjatkan kepada Tuhan Yang Maha Esa atas segala nikmat dan karunia-Nya. Selamat datang di website resmi SMA Negeri 1 Contoh. Kami berkomitmen untuk terus meningkatkan kualitas pendidikan demi masa depan generasi bangsa yang lebih cerah.',
  'Jl. Pendidikan No. 1, Kota Contoh, Provinsi Contoh 12345',
  '(021) 1234-5678',
  'info@sman1contoh.sch.id'
);

-- ── Contoh berita ─────────────────────────────────────────────
INSERT INTO `berita` (`user_id`, `kategori_id`, `judul`, `slug`, `isi_berita`, `status`, `views`, `created_at`) VALUES
(1, 3, 'Siswa SMA Negeri 1 Contoh Raih Juara OSN Tingkat Nasional',
 'siswa-raih-juara-osn-nasional',
 '<p>Selamat kepada tim siswa SMA Negeri 1 Contoh yang berhasil meraih juara pertama pada Olimpiade Sains Nasional (OSN) tahun ini. Prestasi membanggakan ini merupakan hasil kerja keras seluruh siswa dan bimbingan guru-guru kami.</p><p>Kompetisi yang berlangsung selama tiga hari ini diikuti oleh lebih dari 500 peserta dari seluruh Indonesia. Tim kami berhasil unggul di bidang Matematika dan Fisika.</p>',
 'published', 128, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 'Pengumuman Jadwal Ujian Akhir Semester Ganjil',
 'jadwal-ujian-akhir-semester-ganjil',
 '<p>Berikut adalah jadwal pelaksanaan Ujian Akhir Semester (UAS) Ganjil Tahun Pelajaran 2024/2025. Seluruh siswa diharapkan mempersiapkan diri dengan sebaik-baiknya.</p><ul><li>Kelas X: 16 - 20 Desember 2024</li><li>Kelas XI: 16 - 20 Desember 2024</li><li>Kelas XII: 9 - 13 Desember 2024</li></ul>',
 'published', 95, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 5, 'Peringatan Hari Pahlawan: Upacara dan Pentas Seni',
 'peringatan-hari-pahlawan',
 '<p>Dalam rangka memperingati Hari Pahlawan, SMA Negeri 1 Contoh mengadakan upacara bendera yang khidmat dilanjutkan dengan pentas seni budaya. Kegiatan ini bertujuan untuk menumbuhkan rasa nasionalisme di kalangan siswa.</p>',
 'published', 67, DATE_SUB(NOW(), INTERVAL 10 DAY));

-- ── Contoh pengumuman ────────────────────────────────────────
INSERT INTO `pengumuman` (`judul`, `slug`, `isi_pengumuman`, `created_at`) VALUES
('Pendaftaran Peserta Didik Baru (PPDB) Tahun Ajaran 2025/2026',
 'ppdb-2025-2026',
 '<p>Pendaftaran Peserta Didik Baru (PPDB) SMA Negeri 1 Contoh untuk tahun ajaran 2025/2026 akan segera dibuka. Berikut informasi lengkapnya:</p><ul><li><strong>Tanggal Pendaftaran:</strong> 1 – 15 Juni 2025</li><li><strong>Kuota:</strong> 360 siswa (10 rombel)</li><li><strong>Jalur:</strong> Zonasi, Afirmasi, Prestasi, Perpindahan Tugas Orang Tua</li></ul><p>Pendaftaran dilakukan secara online melalui portal PPDB Dinas Pendidikan setempat.</p>',
 DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Libur Semester Ganjil TA 2024/2025',
 'libur-semester-ganjil-2024',
 '<p>Diberitahukan kepada seluruh siswa, orang tua/wali murid bahwa libur semester ganjil tahun ajaran 2024/2025 akan dilaksanakan pada tanggal <strong>23 Desember 2024 – 5 Januari 2025</strong>.</p><p>Kegiatan belajar mengajar kembali dimulai pada <strong>Senin, 6 Januari 2025</strong>.</p>',
 DATE_SUB(NOW(), INTERVAL 7 DAY)),
('Sosialisasi Program Studi Pilihan Kelas XII',
 'sosialisasi-program-studi-kelas-xii',
 '<p>Kepada seluruh siswa kelas XII dan orang tua/wali murid, akan diadakan sosialisasi pemilihan program studi perguruan tinggi pada:</p><ul><li><strong>Hari, Tanggal:</strong> Sabtu, 14 Desember 2024</li><li><strong>Waktu:</strong> 08.00 – 12.00 WIB</li><li><strong>Tempat:</strong> Aula SMA Negeri 1 Contoh</li></ul>',
 DATE_SUB(NOW(), INTERVAL 14 DAY));

-- ── Contoh galeri ─────────────────────────────────────────────
INSERT INTO `galeri` (`judul`, `foto`, `keterangan`, `created_at`) VALUES
('Upacara Peringatan HUT RI ke-79', NULL, 'Upacara bendera dalam rangka HUT Kemerdekaan RI', DATE_SUB(NOW(), INTERVAL 3 DAY)),
('Lomba Kebersihan Antar Kelas', NULL, 'Kegiatan kebersihan lingkungan sekolah', DATE_SUB(NOW(), INTERVAL 8 DAY)),
('Workshop Kewirausahaan Siswa', NULL, 'Pelatihan kewirausahaan bersama alumni sukses', DATE_SUB(NOW(), INTERVAL 15 DAY)),
('Pentas Seni Akhir Tahun', NULL, 'Penampilan tari dan musik oleh siswa berbakat', DATE_SUB(NOW(), INTERVAL 20 DAY));

SET foreign_key_checks = 1;

-- ============================================================
--  SELESAI
--  Akun Admin Default:
--    Username : admin
--    Password : Admin@1234
--  >> SEGERA GANTI PASSWORD setelah login pertama kali! <<
-- ============================================================
