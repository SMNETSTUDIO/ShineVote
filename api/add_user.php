<?php
require_once 'config.php';

// 通过token验证管理员权限
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 验证管理员权限
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_tokens t ON u.id = t.user_id 
    WHERE t.token = ? AND u.is_admin = 1
    LIMIT 1
");
$stmt->execute([$auth_token]);
$admin = $stmt->fetch();

if (!$admin) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取并验证输入
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
    exit;
}

// 检查用户名是否已存在
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => '用户名已存在']);
    exit;
}

try {
    // 创建新用户
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, is_admin, created_at) 
        VALUES (?, ?, 0, NOW())
    ");
    
    $stmt->execute([$username, $hashedPassword]);
    echo json_encode(['success' => true, 'message' => '用户添加成功']);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => '系统错误']);
} 