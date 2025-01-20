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
    header('Location: /api/login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM users WHERE is_admin = 0");
$users = $stmt->fetchAll();

// 获取候选人数据
$stmt = $pdo->query("SELECT * FROM candidates");
$candidates = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#users">用户管理</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#candidates">候选人管理</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#settings">投票设置</a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- 用户管理面板 -->
            <div class="tab-pane active" id="users">
                <h3>用户管理</h3>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">添加用户</button>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>权限</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <select class="form-select form-select-sm" onchange="updateUserRole(<?= $user['id'] ?>, this.value)">
                                    <option value="0" <?= $user['is_admin'] ? '' : 'selected' ?>>普通用户</option>
                                    <option value="1" <?= $user['is_admin'] ? 'selected' : '' ?>>管理员</option>
                                </select>
                            </td>
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

            <!-- 候选人管理面板 -->
            <div class="tab-pane" id="candidates">
                <h3>候选人管理</h3>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addCandidateModal">添加候选人</button>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>图片</th>
                            <th>名称</th>
                            <th>当前票数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td><?= $candidate['id'] ?></td>
                            <td><img src="<?= htmlspecialchars($candidate['image_url']) ?>" height="50"></td>
                            <td>
                                <input type="text" class="form-control form-control-sm" 
                                       value="<?= htmlspecialchars($candidate['name']) ?>"
                                       onchange="updateCandidateName(<?= $candidate['id'] ?>, this.value)">
                            </td>
                            <td><?= $candidate['vote_count'] ?? 0 ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="resetVotes(<?= $candidate['id'] ?>)">重置票数</button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                        data-bs-target="#editImageModal" 
                                        onclick="setEditImageId(<?= $candidate['id'] ?>)">更换图片</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCandidate(<?= $candidate['id'] ?>)">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 投票设置面板 -->
            <div class="tab-pane" id="settings">
                <h3>投票设置</h3>
                <div class="card p-3">
                    <div class="mb-3">
                        <button class="btn btn-danger" onclick="resetAllVotes()">重置所有票数</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加候选人模态框 -->
    <div class="modal fade" id="addCandidateModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">添加候选人</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCandidateForm">
                        <div class="mb-3">
                            <label>名称</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>图片</label>
                            <input type="file" name="image" class="form-control" required accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">添加</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 更换图片模态框 -->
    <div class="modal fade" id="editImageModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">更换图片</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editImageForm">
                        <input type="hidden" name="candidate_id" id="editImageCandidateId">
                        <div class="mb-3">
                            <label>新图片</label>
                            <input type="file" name="image" class="form-control" required accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary">更新</button>
                    </form>
                </div>
            </div>
        </div>
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

    // 更新用户权限
    function updateUserRole(userId, isAdmin) {
        fetch('update_user_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&is_admin=${isAdmin}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('用户权限已更新');
            }
        });
    }

    // 更新候选人名称
    function updateCandidateName(id, name) {
        fetch('update_candidate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}&name=${encodeURIComponent(name)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('名称已更新');
            }
        });
    }

    // 重置候选人票数
    function resetVotes(candidateId) {
        if (confirm('确定要重置该候选人的票数吗？')) {
            fetch('reset_votes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `candidate_id=${candidateId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }

    // 重置所有票数
    function resetAllVotes() {
        if (confirm('确定要重置所有候选人的票数吗？')) {
            fetch('reset_all_votes.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }

    // 删除候选人
    function deleteCandidate(id) {
        if (confirm('确定要删除该候选人吗？')) {
            fetch('delete_candidate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }

    // 添加候选人
    document.getElementById('addCandidateForm').onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('add_candidate.php', {
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

    // 设置要编辑的候选人ID
    function setEditImageId(id) {
        document.getElementById('editImageCandidateId').value = id;
    }

    // 更新候选人图片
    document.getElementById('editImageForm').onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('update_candidate_image.php', {
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
    </script>
</body>
</html> 