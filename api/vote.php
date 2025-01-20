<?php
require_once 'config.php';

header('Content-Type: application/json');

// 验证token
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 通过token获取用户信息
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_tokens t ON u.id = t.user_id 
    WHERE t.token = ?
    LIMIT 1
");
$stmt->execute([$auth_token]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户验证失败']);
    exit;
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取候选人ID
$candidate_id = $_POST['candidate_id'] ?? '';
if (!$candidate_id) {
    echo json_encode(['success' => false, 'message' => '请选择候选人']);
    exit;
}

// 检查用户是否已经投票
$stmt = $pdo->prepare("SELECT id FROM votes WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '您已经投过票了']);
    exit;
}

// 验证候选人是否存在
$stmt = $pdo->prepare("SELECT id FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '无效的候选人']);
    exit;
}

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 记录投票
    $stmt = $pdo->prepare("INSERT INTO votes (user_id, candidate_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user['id'], $candidate_id]);
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '投票成功']);
} catch (Exception $e) {
    // 回滚事务
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '系统错误，请稍后重试']);
} 