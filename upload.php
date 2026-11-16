<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// KONFIGURASI OPENAI API
$api_key = "ISI_MANUAL_DI_HOSTING";
$model = "gpt-image-2.5-sunburst";
$endpoint = "https://api.openai.com/v1/chat/completions";

$target_dir = "uploads/";
if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

if (isset($_FILES["foto_audiens"])) {
    $temp_file = $_FILES["foto_audiens"]["tmp_name"];
    $mentah_file = $target_dir . "mentah.jpg";
    $hasil_file = $target_dir . "hasil.jpg";

    // 1. Simpan foto mentah dari HP
    if (move_uploaded_file($temp_file, $mentah_file)) {
        
        // 2. Siapkan foto (Base64) untuk dikirim ke OpenAI
        $imageData = base64_encode(file_get_contents($mentah_file));
        $base64_img = 'data:image/jpeg;base64,' . $imageData;

        // 3. PROMPT SUPER UNTUK AI
        $prompt = "You are an expert AI photo editor. I am providing a portrait photo. Please remove the background completely and replace it with a solid dark blue background (hex color #012A54). Enhance the lighting with a professional 'sunburst' effect. CRITICAL: You must strictly preserve the original facial features, identity, and expression of the person. Do not change their face. Return ONLY the direct URL or raw Base64 string of the generated image, without any markdown, explanation, or extra text.";

        $data = [
            "model" => $model,
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You only output the raw image URL or Base64 string."
                ],
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "text", "text" => $prompt],
                        ["type" => "image_url", "image_url" => ["url" => $base64_img]]
                    ]
                ]
            ],
            "max_tokens" => 1500
        ];

        // 4. Tembak ke API OpenAI
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Beri waktu 30 detik untuk AI berpikir

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // 5. Tangkap dan Simpan Hasilnya
        $sukses_ai = false;
        if (!$err) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                $ai_output = trim($result['choices'][0]['message']['content']);
                
                // Cek apakah output AI berupa URL gambar atau Base64
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

        // FALLBACK: Jika API OpenAI error atau gagal, tetap gunakan foto mentah agar Billboard tidak blank!
        if (!$sukses_ai) {
            copy($mentah_file, $hasil_file);
        }

        // 6. Beri sinyal ke Layar Laptop (display.html) bahwa foto baru sudah matang
        file_put_contents("status.txt", time());
        
        echo json_encode(["status" => "success", "message" => "Diproses AI"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal simpan mentahan."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Tidak ada file."]);
}
?>