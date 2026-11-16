<?php
$target_dir = "uploads/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

if (isset($_FILES["foto_audiens"])) {
    // Timpa file terus-menerus dengan nama yang sama agar hosting tidak penuh
    $target_file =$target_dir . "terbaru.jpg";
    
    if (move_uploaded_file($_FILES["foto_audiens"]["tmp_name"], $target_file)) {
        // Update waktu di status.txt agar laptop tahu ada foto baru
        file_put_contents("status.txt", time());
        echo "sukses"; // Balasan teks biasa (tanpa JSON)
    } else {
        echo "gagal";
    }
}
?>