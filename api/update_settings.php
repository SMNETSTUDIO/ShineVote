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
$admin = $stmt->fetch();

if (!$admin) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 获取JSON格式的POST数据
$data = json_decode(file_get_contents('php://input'), true);

// 验证必要字段
if (empty($data['voting_name'])) {
    echo json_encode(['success' => false, 'message' => '投票系统名称不能为空']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO settings (
            id, 
            voting_name, 
            max_votes_per_user, 
            voting_enabled,
            voting_start_time,
            voting_end_time,
            show_results
        ) VALUES (
            1, ?, ?, ?, ?, ?, ?
        ) ON DUPLICATE KEY UPDATE 
            voting_name = ?,
            max_votes_per_user = ?,
            voting_enabled = ?,
            voting_start_time = ?,
            voting_end_time = ?,
            show_results = ?
    ");
    
    $params = [
        $data['voting_name'],
        (int)$data['max_votes_per_user'],
        (int)$data['voting_enabled'],
        $data['voting_start_time'],
        $data['voting_end_time'],
        (int)$data['show_results'],
        // 重复参数用于UPDATE部分
        $data['voting_name'],
        (int)$data['max_votes_per_user'],
        (int)$data['voting_enabled'],
        $data['voting_start_time'],
        $data['voting_end_time'],
        (int)$data['show_results']
    ];
    
    $stmt->execute($params);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误']);
} 