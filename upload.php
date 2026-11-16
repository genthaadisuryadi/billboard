<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// =========================================================================
// 1. PROMPT AI (JAS ALMAMATER UCIC BIRU DONGKER & HAPUS BACKGROUND)
// =========================================================================
$prompt_ai = "Transform this photo into a high-resolution studio portrait for Universitas Catur Insan Cendekia (UCIC). "
           . "CRITICAL FACE RULE: Strictly preserve the exact facial features, face identity, glasses, hair/hijab, and expression of the person. "
           . "OUTFIT RULE: Dress the person in an official university almamater blazer jacket in dark navy blue (#012A54) worn over a neat white inner shirt, featuring a small square white emblem patch with a blue and yellow university shield logo on the left chest of the navy blazer. "
           . "BACKGROUND RULE: Completely remove the original background and walls. Isolate the subject on a pure, solid, flat white background (#FFFFFF) with clean studio lighting.";

// =========================================================================
// 2. BACA API KEY DARI SERVER (Otomatis dari /api atau /photobooth)
// =========================================================================
$settings_files = ["../api/settings.json", "../photobooth/settings.json"];
$api_key = "";
$base_url = "https://api.openai.com/v1";
$model_utama = "gpt-image-2.5-sunburst";

foreach ($settings_files as $path) {
    if (file_exists($path)) {
        $cfg = json_decode(file_get_contents($path), true);
        if (!empty($cfg['apiKey'])) {
            $api_key = $cfg['apiKey'];
            if (!empty($cfg['apiBaseUrl'])) $base_url = rtrim($cfg['apiBaseUrl'], '/');
            if (!empty($cfg['aiModel'])) $model_utama = $cfg['aiModel'];
            break;
        }
    }
}

$endpoint = $base_url . "/images/edits";
$target_dir = "uploads/";

if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Fungsi menembak API OpenAI /images/edits
function tembakOpenAI($endpoint, $api_key, $file_path, $prompt, $model) {
    $cfile = new CURLFile(realpath($file_path), 'image/png', 'image.png');
    $post_fields = [
        'image' => $cfile,
        'prompt' => $prompt,
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

    return [$response, $err];
}

if (isset($_FILES["foto_audiens"])) {
    $temp_file = $_FILES["foto_audiens"]["tmp_name"];
    $mentah_png = $target_dir . "mentah.png";
    $hasil_file = $target_dir . "hasil.jpg";

    $img_data = file_get_contents($temp_file);
    $src_img = @imagecreatefromstring($img_data);
    if ($src_img !== false) {
        imagepng($src_img, $mentah_png);
        imagedestroy($src_img);
    } else {
        move_uploaded_file($temp_file, $mentah_png);
    }

    if (file_exists($mentah_png)) {
        // Percobaan 1: Gunakan model dari settings.json (gpt-image-2.5-sunburst)
        list($response, $err) = tembakOpenAI($endpoint, $api_key, $mentah_png, $prompt_ai, $model_utama);
        $data = json_decode($response, true);

        // Percobaan 2 (Auto-Fallback): Jika model utama error/tidak dikenali, otomatis coba model 'gpt-image-1'
        if (isset($data['error']) && $model_utama !== 'gpt-image-1') {
            list($response, $err) = tembakOpenAI($endpoint, $api_key, $mentah_png, $prompt_ai, 'gpt-image-1');
            $data = json_decode($response, true);
        }

        $sukses_ai = false;
        $ai_error_msg = "";

        if (!$err && is_array($data)) {
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
            }
        } else {
            $ai_error_msg = $err ?: "Respon kosong dari server AI";
        }

        file_put_contents("ai_log.txt", date("Y-m-d H:i:s") . " | Sukses: " . ($sukses_ai ? "YA" : "TIDAK") . " | Info: " . $ai_error_msg . "\n", FILE_APPEND);

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