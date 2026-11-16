<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// 1. BACA KONFIGURASI LANGSUNG DARI FOLDER PHOTOBOOTH / API (Tanpa Hardcode API Key!)
$settings_path = "../photobooth/settings.json";
if (!file_exists($settings_path)) {
    $settings_path = "../api/settings.json";
}

$api_key = "";
$base_url = "https://api.openai.com/v1";
$model = "gpt-image-2.5-sunburst";

if (file_exists($settings_path)) {
    $cfg = json_decode(file_get_contents($settings_path), true);
    if (!empty($cfg['apiKey'])) $api_key = $cfg['apiKey'];
    if (!empty($cfg['apiBaseUrl'])) $base_url = rtrim($cfg['apiBaseUrl'], '/');
    if (!empty($cfg['aiModel'])) $model = $cfg['aiModel'];
}

$endpoint = $base_url . "/images/edits";
$target_dir = "uploads/";

if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

if (isset($_FILES["foto_audiens"])) {
    $temp_file = $_FILES["foto_audiens"]["tmp_name"];
    $mentah_file = $target_dir . "mentah.png";
    $hasil_file = $target_dir . "hasil.jpg";

    if (move_uploaded_file($temp_file, $mentah_file)) {
        
        // 2. PROMPT KHUSUS BILLBOARD STAGE UCIC
        $prompt = "Professional commercial studio portrait for a university billboard campaign. Strictly preserve the exact facial features, identity, glasses, headscarf/hijab, and expression of the person in the photo. Remove the original background completely and replace it with a clean, solid very light studio grey-white background (#F8FAFC) so the subject blends seamlessly into a bright billboard layout. Enhance the subject with vibrant, heroic rim lighting, warm golden-yellow and deep blue cinematic studio highlights, sharp DSLR focus, and glowing skin tones.";

        // 3. KIRIM MENGGUNAKAN MULTIPART/FORM-DATA KE /images/edits (Persis seperti app.js Photobooth)
        $cfile = new CURLFile(realpath($mentah_file), 'image/png', 'image.png');
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $sukses_ai = false;
        $ai_error_msg = "";

        if (!$err && $response) {
            $data = json_decode($response, true);
            
            // Tangkap b64_json atau url persis seperti baris 530-533 di app.js Photobooth
            if (isset($data['data'][0]['b64_json'])) {
                $img_binary = base64_decode($data['data'][0]['b64_json']);
                if ($img_binary) {
                    file_put_contents($hasil_file, $img_binary);
                    $sukses_ai = true;
                }
            } elseif (isset($data['data'][0]['url'])) {
                $img_binary = file_get_contents($data['data'][0]['url']);
                if ($img_binary) {
                    file_put_contents($hasil_file, $img_binary);
                    $sukses_ai = true;
                }
            } elseif (isset($data['error']['message'])) {
                $ai_error_msg = $data['error']['message'];
            }
        } else {
            $ai_error_msg = $err;
        }

        // Simpan catatan log jika sewaktu-waktu kita ingin mengecek respon AI
        file_put_contents("ai_log.txt", date("Y-m-d H:i:s") . " | Sukses: " . ($sukses_ai ? "YA" : "TIDAK") . " | Error: " . $ai_error_msg . "\n", FILE_APPEND);

        // Fallback jika AI gagal: tetap tampilkan foto mentah agar layar Stage tidak pernah kosong
        if (!$sukses_ai) {
            copy($mentah_file, $hasil_file);
        }

        // Update timestamp agar display.html langsung menampilkan Halaman 2
        $waktu = time();
        file_put_contents("status.txt", $waktu);

        echo json_encode([
            "status" => "success",
            "ai_processed" => $sukses_ai,
            "timestamp" => $waktu,
            "ai_note" => $ai_error_msg
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan file mentah di server."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Tidak ada file foto yang diterima."]);
}
?>