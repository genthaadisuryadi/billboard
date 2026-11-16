<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// BACA: KOSONGKAN SAAT DI PUSH KE GITHUB LALU ISI MANUAL DI HOSTING
$api_key = "ISI_MANUAL_DI_HOSTING";
$model = "gpt-image-2.5-sunburst";
$endpoint = "https://api.openai.com/v1/chat/completions";

$target_dir = "uploads/";

// Deteksi otomatis jika folder gagal dibuat
if (!file_exists($target_dir)) { 
    if (!mkdir($target_dir, 0777, true)) {
        echo json_encode(["status" => "error", "message" => "Gagal membuat folder uploads di server. Cek permissions."]);
        exit;
    }
}

if (isset($_FILES["foto_audiens"])) {
    $temp_file = $_FILES["foto_audiens"]["tmp_name"];
    $mentah_file = $target_dir . "mentah.jpg";
    $hasil_file = $target_dir . "hasil.jpg";

    if (move_uploaded_file($temp_file, $mentah_file)) {
        
        $imageData = base64_encode(file_get_contents($mentah_file));
        $base64_img = 'data:image/jpeg;base64,' . $imageData;

        $prompt = "You are an expert AI photo editor. I am providing a portrait photo. Please remove the background completely and replace it with a solid dark blue background (hex color #012A54). Enhance the lighting with a professional 'sunburst' effect. CRITICAL: You must strictly preserve the original facial features, identity, and expression of the person. Do not change their face. Return ONLY the direct URL or raw Base64 string of the generated image, without any markdown, explanation, or extra text.";

        $data = [
            "model" => $model,
            "messages" => [
                [ "role" => "system", "content" => "You only output the raw image URL or Base64 string." ],
                [ "role" => "user", "content" => [
                        ["type" => "text", "text" => $prompt],
                        ["type" => "image_url", "image_url" => ["url" => $base64_img]]
                    ]
                ]
            ],
            "max_tokens" => 1500
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); 

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $sukses_ai = false;
        if (!$err) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $ai_output = trim($result['choices'][0]['message']['content']);
                if (filter_var($ai_output, FILTER_VALIDATE_URL)) {
                    $image_content = file_get_contents($ai_output);
                    if ($image_content) {
                        file_put_contents($hasil_file, $image_content);
                        $sukses_ai = true;
                    }
                } else if (strpos($ai_output, 'base64') !== false) {
                    $ai_output = preg_replace('#^data:image/\w+;base64,#i', '', $ai_output);
                    file_put_contents($hasil_file, base64_decode($ai_output));
                    $sukses_ai = true;
                }
            }
        }

        // Jika API error (misal token habis atau API Key salah), jadikan mentahan sebagai hasil agar tidak blank
        if (!$sukses_ai) {
            copy($mentah_file, $hasil_file);
        }

        // Pancing Halaman 2 Laptop untuk nge-refresh foto
        file_put_contents("status.txt", time());
        
        echo json_encode(["status" => "success", "message" => "Proses server selesai."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal memindahkan file di server. Cek folder permissions."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "File terlalu besar atau tidak ada foto yang terkirim."]);
}
?>