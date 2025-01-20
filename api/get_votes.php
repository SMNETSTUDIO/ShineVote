<?php
require_once 'config.php';

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