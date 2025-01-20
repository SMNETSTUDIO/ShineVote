<?php
require_once 'config.php';

// 验证token
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    header('Location: login.php');
    exit;
}

// 通过token获取用户信息
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
    header('Location: login.php');
    exit;
}

// 获取用户列表
$stmt = $pdo->query("SELECT * FROM users WHERE is_admin = 0");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>用户管理</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">添加用户</button>
        
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= $user['created_at'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="resetPassword(<?= $user['id'] ?>)">重置密码</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>)">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 添加用户模态框 -->
    <div class="modal fade" id="addUserModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="mb-3">
                            <label>用户名</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>密码</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 添加用户
    document.getElementById('addUserForm').onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('add_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    };

    // 删除用户
    function deleteUser(userId) {
        if (confirm('确定要删除该用户吗？')) {
            fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }

    // 重置密码
    function resetPassword(userId) {
        const newPassword = prompt('请输入新密码：');
        if (newPassword) {
            fetch('reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `user_id=${userId}&password=${newPassword}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('密码已重置');
                }
            });
        }
    }
    </script>
</body>
</html> 