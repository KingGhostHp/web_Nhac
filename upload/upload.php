<?php
session_start();

// Kết nối CSDL bằng PDO
$config = include __DIR__. '/../config/db.php';
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Kết nối CSDL thất bại: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nhập liệu đầu vào
    $songName   = trim($_POST['song_name']   ?? '');
    $artistName = trim($_POST['artist_name'] ?? '');
    $category   = trim($_POST['category']    ?? '');

    if (!$songName || !$artistName || !$category) {
        die('Vui lòng điền đầy đủ thông tin bài hát, tác giả và thể loại.');
    }

    // Kiểm tra file upload
    if (!isset($_FILES['music_file'], $_FILES['image_file'])) {
        die('Vui lòng chọn cả file nhạc và file ảnh.');
    }

    // Xử lý lỗi upload
    if ($_FILES['music_file']['error'] !== UPLOAD_ERR_OK ||
        $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        die('Có lỗi xảy ra khi upload file.');
    }

    // Giới hạn kích thước
    $maxMusic = 10 * 1024 * 1024; // 10MB
    $maxImage = 2  * 1024 * 1024; // 2MB

    if ($_FILES['music_file']['size'] > $maxMusic ||
        $_FILES['image_file']['size'] > $maxImage) {
        die('Kích thước file quá lớn. Nhạc tối đa 10MB, ảnh tối đa 2MB.');
    }

    // Kiểm tra MIME-type thực tế
    $mimeMusic = mime_content_type($_FILES['music_file']['tmp_name']);
    $mimeImage = mime_content_type($_FILES['image_file']['tmp_name']);
    $allowedMusic = ['audio/mpeg', 'audio/wav'];
    $allowedImage = ['image/jpeg', 'image/png', 'image/gif'];

    if (!in_array($mimeMusic, $allowedMusic)) {
        die('Định dạng file nhạc không hợp lệ. Chỉ MP3 và WAV.');
    }
    if (!in_array($mimeImage, $allowedImage)) {
        die('Định dạng file ảnh không hợp lệ. Chỉ JPEG, PNG, GIF.');
    }

    // Tạo tên file duy nhất
    $uniq = bin2hex(random_bytes(8));
    $extM = strtolower(pathinfo($_FILES['music_file']['name'], PATHINFO_EXTENSION));
    $extI = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    $fileM = "$uniq.$extM";
    $fileI = "$uniq.$extI";

    // Thư mục lưu trữ
    $dirM = __DIR__ . '/../storage/ms/';
    $dirI = __DIR__ . '/../storage/avatar/';
    if (!is_dir($dirM)) mkdir($dirM, 0755, true);
    if (!is_dir($dirI)) mkdir($dirI, 0755, true);

    $pathM = $dirM . $fileM;
    $pathI = $dirI . $fileI;

    // Di chuyển file lên server
    if (!move_uploaded_file($_FILES['music_file']['tmp_name'], $pathM) ||
        !move_uploaded_file($_FILES['image_file']['tmp_name'], $pathI)) {
        die('Không thể lưu file lên server.');
    }

    // Lưu thông tin vào CSDL
    try {
        $stmt = $pdo->prepare("
            INSERT INTO songs 
                (song_name, artist_name, category, music_file_path, image_file_path, created_at)
            VALUES 
                (:song, :artist, :category, :music_path, :image_path, NOW())
        ");
        $stmt->execute([
            ':song'       => htmlspecialchars($songName,   ENT_QUOTES, 'UTF-8'),
            ':artist'     => htmlspecialchars($artistName, ENT_QUOTES, 'UTF-8'),
            ':category'   => htmlspecialchars($category,   ENT_QUOTES, 'UTF-8'),
            ':music_path' => "storage/ms/$fileM",
            ':image_path' => "storage/avatar/$fileI"
        ]);
    } catch (PDOException $e) {
        // Xóa file đã upload nếu lỗi CSDL
        @unlink($pathM);
        @unlink($pathI);
        die('Lỗi khi lưu vào CSDL: ' . $e->getMessage());
    }

    // Chuyển hướng về trang chính
    header('Location: ../index.php');
    exit;
}

// Nếu không phải POST, có thể redirect hoặc hiển thị 404
header('HTTP/1.1 404 Not Found');
echo 'Trang không tồn tại.';