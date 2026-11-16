<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$dir = "uploads/";
$files = [];

// Buka folder uploads secara manual (lebih aman daripada GLOB_BRACE di Linux)
if (is_dir($dir)) {
    foreach (scandir($dir) as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $files[] = $dir . $file;
        }
    }
}

// Jika ada foto di dalam folder uploads, urutkan dari yang paling baru
if (!empty($files)) {
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Langsung arahkan (Redirect) browser ke file gambar aslinya!
    $latest = $files[0];
    header("Location: /" . $latest . "?t=" . filemtime($latest));
    exit;
}

// Jika folder uploads benar-benar kosong, arahkan ke model default
header("Location: /model-default.png");
exit;
?>