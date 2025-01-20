<?php
require_once 'config.php';

// 验证管理员权限
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_tokens t ON u.id = t.user_id 
    WHERE t.token = ? AND u.is_admin = 1
    LIMIT 1
");
$stmt->execute([$auth_token]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 处理上传的图片
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '图片上传失败']);
    exit;
}

$candidate_id = $_POST['candidate_id'] ?? '';
if (!$candidate_id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

// 处理图片上传
$upload_dir = '../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => '不支持的文件格式']);
    exit;
}

$filename = uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $filename;

if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
    // 获取旧图片路径
    $stmt = $pdo->prepare("SELECT image_url FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    $old_image = $stmt->fetchColumn();
    
    // 更新数据库
    $image_url = '/uploads/' . $filename;
    $stmt = $pdo->prepare("UPDATE candidates SET image_url = ? WHERE id = ?");
    $result = $stmt->execute([$image_url, $candidate_id]);
    
    if ($result) {
        // 删除旧图片
        $old_image_path = '..' . $old_image;
        if (file_exists($old_image_path)) {
            unlink($old_image_path);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '更新失败']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '图片保存失败']);
} 