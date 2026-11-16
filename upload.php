<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// =========================================================================
// 1. PROMPT AI (JAS ALMAMATER UCIC & HAPUS BACKGROUND RUANGAN)
// =========================================================================
$prompt_ai = "Transform this photo into a high-resolution commercial studio portrait for Universitas Catur Insan Cendekia (UCIC). "
           . "CRITICAL FACE & POSE RULE: Strictly preserve the exact facial features, face identity, skin tone, glasses, hair/hijab, and exact hand gesture/pose of the person. "
           . "OUTFIT RULE: Replace the person's clothes with an official university almamater blazer jacket in dark navy blue (#012A54) worn open over a clean, neat white inner shirt. "
           . "Add a small square white emblem patch featuring a blue and yellow university shield logo on the left chest of the navy blazer. "
           . "BACKGROUND RULE: Completely remove the original room, walls, and objects behind the person. Isolate the subject on a pure, solid, flat white background (#FFFFFF) with clean studio rim lighting.";

// =========================================================================
// 2. BACA API KEY DARI SERVER (Prioritas: folder /api lalu /photobooth)
// =========================================================================
$settings_files = ["../api/settings.json", "../photobooth/settings.json"];
$api_key = "";
$base_url = "https://api.openai.com/v1";
$model = "gpt-image-2.5-sunburst";

foreach ($settings_files as $path) {
    if (file_exists($path)) {
        $cfg = json_decode(file_get_contents($path), true);
        if (!empty($cfg['apiKey'])) {
            $api_key = $cfg['apiKey'];
            if (!empty($cfg['apiBaseUrl'])) $base_url = rtrim($cfg['apiBaseUrl'], '/');
            if (!empty($cfg['aiModel'])) $model = $cfg['aiModel'];
            break;
        }
    }
}

$endpoint = $base_url . "/images/edits";
$target_dir = "uploads/";

if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

if (isset($_FILES["foto_audiens"])) {
    $temp_file = $_FILES["foto_audiens"]["tmp_name"];
    $mentah_png = $target_dir . "mentah.png";
    $hasil_file = $target_dir . "hasil.jpg";

    // 3. KONVERSI KE PNG MURNI AGAR TIDAK DITOLAK OLEH /images/edits OPENAI
    $img_data = file_get_contents($temp_file);
    $src_img = @imagecreatefromstring($img_data);
    if ($src_img !== false) {
        imagepng($src_img, $mentah_png);
        imagedestroy($src_img);
    } else {
        move_uploaded_file($temp_file, $mentah_png);
    }

    if (file_exists($mentah_png)) {
        $cfile = new CURLFile(realpath($mentah_png), 'image/png', 'image.png');
        $post_fields = [
            'image' => $cfile,
            'prompt' => $prompt_ai,
            'model' => $model,
            'n' => '1'
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $sukses_ai = false;
        $ai_error_msg = "";

        if (!$err && $response) {
            $data = json_decode($response, true);
            if (isset($data['data'][0]['b64_json'])) {
                $img_binary = base64_decode($data['data'][0]['b64_json']);
                if ($img_binary) {
                    file_put_contents($hasil_file, $img_binary);
                    $sukses_ai = true;
                }
            } elseif (isset($data['data'][0]['url'])) {
                $img_binary = @file_get_contents($data['data'][0]['url']);
                if ($img_binary) {
                    file_put_contents($hasil_file, $img_binary);
                    $sukses_ai = true;
                }
            } elseif (isset($data['error']['message'])) {
                $ai_error_msg = $data['error']['message'];
            } else {
                $ai_error_msg = "Format respon tidak dikenali: " . substr($response, 0, 200);
            }
        } else {
            $ai_error_msg = $err;
        }

        // Catat di ai_log.txt untuk memantau status AI
        file_put_contents("ai_log.txt", date("Y-m-d H:i:s") . " | Model: $model | Sukses: " . ($sukses_ai ? "YA" : "TIDAK") . " | Info: " . $ai_error_msg . "\n", FILE_APPEND);

        if (!$sukses_ai) {
            copy($mentah_png, $hasil_file);
        }

        $waktu = time();
        file_put_contents("status.txt", $waktu);

        echo json_encode([
            "status" => "success",
            "ai_processed" => $sukses_ai,
            "timestamp" => $waktu,
            "ai_note" => $ai_error_msg
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyiapkan file PNG di server."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Tidak ada file foto yang diterima."]);
}
?>