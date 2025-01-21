<?php
require_once 'config.php';

// 验证token
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode([]);
    exit;
}

// 获取用户信息
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
    echo json_encode([]);
    exit;
}

// 获取系统设置
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

// 检查用户投票次数
$stmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userVoteCount = $stmt->fetch()['vote_count'];

// 如果不显示实时结果且用户未达到最大投票次数，返回空数据
if (!$settings['show_results'] && $userVoteCount < $settings['max_votes_per_user']) {
    echo json_encode([]);
    exit;
}

// 获取所有候选人及其投票数
$stmt = $pdo->query("
    SELECT 
        c.id,
        c.name,
        COUNT(v.id) as vote_count 
    FROM candidates c 
    LEFT JOIN votes v ON c.id = v.candidate_id 
    GROUP BY c.id 
    ORDER BY vote_count DESC
");

$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 设置响应头为JSON
header('Content-Type: application/json');

// 输出JSON格式的数据
echo json_encode($candidates); 