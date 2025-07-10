<?php
// Hata mesajlarını göster
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// GitHub API ayarları
$githubRepo = 'gaffar-dulkadir/test'; // Örn: kullanıcı/repo
$githubBranch = 'main'; // Hedef branch
$uploadDir = 'uploading/'; // Repo içindeki klasör

$uploadedFile = '';
$tempFilePath = '';

// Yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && !isset($_POST['save'])) {
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('Dosya yükleme hatası: ' . $file['error']);
    }

    $allowedTypes = ['image/png', 'image/jpeg', 'application/pdf'];
    $maxFileSize = 5 * 1024 * 1024;

    if (!in_array($file['type'], $allowedTypes)) {
        die('Geçersiz dosya türü. Sadece PNG, JPG ve PDF destekleniyor.');
    }

    if ($file['size'] > $maxFileSize) {
        die('Dosya çok büyük. Maksimum 5MB olmalı.');
    }

    $uniqueId = uniqid() . '-' . time();
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = $uniqueId . '.' . $extension;
    $tempFilePath = sys_get_temp_dir() . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
        die('Dosya geçici dizine kaydedilemedi.');
    }

    $uploadedFile = $newFileName;
    echo 'Dosya yüklendi: ' . htmlspecialchars($newFileName) . '. Kaydetmek için "Save" butonuna basın.<br>';
}

// Kaydetme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && isset($_POST['filename'])) {
    $filename = basename($_POST['filename']);
    $tempFilePath = sys_get_temp_dir() . '/' . $filename;

    if (!file_exists($tempFilePath)) {
        die('Hata: Dosya bulunamadı: ' . htmlspecialchars($filename));
    }

    $content = base64_encode(file_get_contents($tempFilePath));
    $apiUrl = "https://api.github.com/repos/{$githubRepo}/contents/{$uploadDir}{$filename}";
    $data = [
        'message' => "Upload file: $filename",
        'content' => $content,
        'branch'  => $githubBranch
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token $githubToken",
        'User-Agent: PHP-Curl',
        'Accept: application/vnd.github.v3+json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Dosya sil
    if (file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }

    if ($httpCode === 201 || $httpCode === 200) {
        echo "✅ Dosya GitHub'a başarıyla yüklendi: <a href='https://github.com/{$githubRepo}/tree/{$githubBranch}/{$uploadDir}' target='_blank'>{$filename}</a><br>";
    } else {
        echo "❌ GitHub API hatası: HTTP $httpCode<br>";
        echo "Yanıt: <pre>" . htmlspecialchars($response) . "</pre>";
        echo "CURL Hatası: " . htmlspecialchars($error);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Dosya Yükleme</title>
</head>
<body>
    <h2>Dosya Yükleme</h2>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">Yükle</button>
    </form>

    <?php if ($uploadedFile): ?>
        <form action="upload.php" method="post">
            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($uploadedFile); ?>">
            <button type="submit" name="save">Save</button>
        </form>
    <?php endif; ?>
</body>
</html>
