<?php
require_once 'config.php';

$error = '';
$success = '';

try {
    $tables = ['candidates', 'settings', 'user_tokens', 'users', 'votes'];
    $existing_tables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            $existing_tables[] = $table;
        }
    }
    
    if (count($existing_tables) === count($tables)) {
        header('Location: /api/login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $sql = "
        DROP TABLE IF EXISTS `candidates`;
        DROP TABLE IF EXISTS `settings`;
        DROP TABLE IF EXISTS `user_tokens`;
        DROP TABLE IF EXISTS `users`;
        DROP TABLE IF EXISTS `votes`;

        CREATE TABLE `candidates` (
            `id` int NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        );

        CREATE TABLE `settings` (
            `id` int NOT NULL,
            `voting_name` varchar(255) NOT NULL DEFAULT '投票系统',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `max_votes_per_user` int NOT NULL DEFAULT '1',
            `voting_enabled` tinyint(1) NOT NULL DEFAULT '1',
            `voting_start_time` datetime DEFAULT NULL,
            `voting_end_time` datetime DEFAULT NULL,
            `show_results` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`)
        );

        CREATE TABLE `users` (
            `id` int NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `is_admin` tinyint(1) DEFAULT '0',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
        );

        CREATE TABLE `user_tokens` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `token` varchar(36) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        );

        CREATE TABLE `votes` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int DEFAULT NULL,
            `candidate_id` int DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `candidate_id` (`candidate_id`),
            CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
            CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`)
        )";

        $pdo->exec($sql);
        $admin_password = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)");
        $stmt->execute(['admin', $admin_password]);
        $stmt = $pdo->prepare("INSERT INTO settings (id, voting_name) VALUES (1, '投票系统')");
        $stmt->execute();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $success = '安装成功！默认管理员账号：admin，密码：admin';
    }

} catch(PDOException $e) {
    $error = '安装过程出错：' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>投票系统 - 安装</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(120deg, #84fab0 0%, #8fd3f4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .container {
            padding: 2rem 1rem;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background: linear-gradient(120deg, #2b4c7d 0%, #1e88e5 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 2rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            border: none;
        }
        .card-body {
            padding: 2rem;
        }
        .btn-primary {
            background: linear-gradient(120deg, #2b4c7d 0%, #1e88e5 100%);
            border: none;
            padding: 1rem 2rem;
            font-weight: 600;
            width: 100%;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-success {
            background: linear-gradient(120deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 1rem 2rem;
            font-weight: 600;
            width: 100%;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .logo-text {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2b4c7d;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="logo-container">
                            <div class="logo-text">投票系统</div>
                        </div>
                        系统安装向导
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?= htmlspecialchars($success) ?>
                                <br><br>
                                <a href="/api/login.php" class="btn btn-success">立即登录</a>
                            </div>
                        <?php else: ?>
                            <p class="mb-4 text-center">欢迎使用投票系统，点击下方按钮开始安装：</p>
                            <form method="POST">
                                <button type="submit" class="btn btn-primary">开始安装</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 