<?php
// Cegah cache browser mati-matian
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$dir = "uploads/";

// Cari SEMUA jenis gambar di dalam folder uploads (jpg, jpeg, png)
$files = glob($dir . "*.{jpg,jpeg,png,JPG,JPEG,PNG}", GLOB_BRACE);

if (!$files || count($files) == 0) {
    // Jika belum ada foto sama sekali, tampilkan default
    header("Content-Type: image/png");
    readfile("model-default.png");
    exit;
}

// Urutkan file berdasarkan waktu dibuat/diubah yang PALING TERBARU
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Ambil file urutan pertama (paling baru)
$latest_file = $files[0];

// Cek format aslinya (agar browser tidak bingung antara PNG atau JPG)
$mime = mime_content_type($latest_file);

// Kirim ke layar billboard
header("Content-Type: " . $mime);
readfile($latest_file);
?>