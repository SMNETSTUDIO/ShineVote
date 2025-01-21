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

// 获取候选人数据，包括准确的票数统计
$stmt = $pdo->query("
    SELECT c.*, 
           COALESCE((SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id), 0) as vote_count 
    FROM candidates c
");
$candidates = $stmt->fetchAll();

// 在获取候选人数据之后添加获取系统设置的代码
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .navbar {
            background-color: #1a1a1a;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
            border: none;
            padding: 1rem 1.5rem;
            transition: all 0.3s;
        }
        .nav-tabs .nav-link:hover {
            border: none;
            color: #0056b3;
        }
        .nav-tabs .nav-link.active {
            color: #0056b3;
            background: none;
            border: none;
            border-bottom: 2px solid #0056b3;
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #1a1a1a;
            color: white;
            border: none;
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }
        .btn-primary {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-primary:hover {
            background-color: #003d82;
            border-color: #003d82;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .form-select {
            border-radius: 6px;
            padding: 0.5rem;
        }
        .form-control {
            border-radius: 6px;
        }
        .form-control:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.25);
        }
        .modal-header {
            background-color: #1a1a1a;
            color: white;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .tab-pane {
            padding: 2rem;
            background: white;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .btn {
            border-radius: 6px;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <span class="navbar-brand">投票系统管理后台</span>
            <div>
                <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal">修改密码</button>
                <a href="logout.php" class="btn btn-outline-light">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container">
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
                            <th>名称</th>
                            <th>当前票数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td><?= $candidate['id'] ?></td>
                            <td>
                                <input type="text" class="form-control form-control-sm" 
                                       value="<?= htmlspecialchars($candidate['name']) ?>"
                                       onchange="updateCandidateName(<?= $candidate['id'] ?>, this.value)">
                            </td>
                            <td><?= $candidate['vote_count'] ?? 0 ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="resetVotes(<?= $candidate['id'] ?>)">重置票数</button>
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
                        <label class="form-label">投票系统名称</label>
                        <input type="text" class="form-control" id="votingSystemName" 
                               value="<?= htmlspecialchars($settings['voting_name'] ?? '投票系统') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">每人最多可投票数</label>
                        <input type="number" class="form-control" id="maxVotesPerUser" min="1"
                               value="<?= htmlspecialchars($settings['max_votes_per_user'] ?? '1') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">投票状态</label>
                        <select class="form-select" id="votingStatus">
                            <option value="1" <?= ($settings['voting_enabled'] ?? 1) ? 'selected' : '' ?>>开启投票</option>
                            <option value="0" <?= ($settings['voting_enabled'] ?? 1) ? '' : 'selected' ?>>暂停投票</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">投票开始时间</label>
                        <input type="datetime-local" class="form-control" id="votingStartTime"
                               value="<?= date('Y-m-d\TH:i', strtotime($settings['voting_start_time'] ?? 'now')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">投票结束时间</label>
                        <input type="datetime-local" class="form-control" id="votingEndTime"
                               value="<?= date('Y-m-d\TH:i', strtotime($settings['voting_end_time'] ?? '+7 days')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">显示实时票数</label>
                        <select class="form-select" id="showRealTimeResults">
                            <option value="1" <?= ($settings['show_results'] ?? 1) ? 'selected' : '' ?>>显示</option>
                            <option value="0" <?= ($settings['show_results'] ?? 1) ? '' : 'selected' ?>>隐藏</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <button class="btn btn-primary" onclick="updateSettings()">保存设置</button>
                        <button class="btn btn-danger ms-2" onclick="resetAllVotes()">重置所有票数</button>
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
                        <button type="submit" class="btn btn-primary">添加</button>
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

    <!-- 在body结束标签前添加重置票数的模态框 -->
    <div class="modal fade" id="resetVotesModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">重置票数</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="resetVotesForm">
                        <input type="hidden" name="candidate_id" id="resetVotesCandidateId">
                        <button type="submit" class="btn btn-primary">重置</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 在其他模态框后添加修改密码模态框 -->
    <div class="modal fade" id="changePasswordModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">修改密码</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="mb-3">
                            <label>当前密码</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>新密码</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>确认新密码</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">修改</button>
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
        document.getElementById('resetVotesCandidateId').value = candidateId;
        new bootstrap.Modal(document.getElementById('resetVotesModal')).show();
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

    // 处理重置票数表单提交
    document.getElementById('resetVotesForm').onsubmit = function(e) {
        e.preventDefault();
        const candidateId = document.getElementById('resetVotesCandidateId').value;
        
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
            } else {
                alert('重置票数失败：' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            alert('重置票数失败：' + error);
        });
    };

    // 添加修改密码的处理函数
    document.getElementById('changePasswordForm').onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // 验证两次输入的密码是否一致
        if (formData.get('new_password') !== formData.get('confirm_password')) {
            alert('两次输入的新密码不一致');
            return;
        }
        
        fetch('change_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('密码修改成功');
                this.reset();
                bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
            } else {
                alert(data.message || '密码修改失败');
            }
        })
        .catch(error => {
            alert('密码修改失败：' + error);
        });
    };

    // 添加更新设置的函数
    function updateSettings() {
        const settings = {
            voting_name: document.getElementById('votingSystemName').value,
            max_votes_per_user: document.getElementById('maxVotesPerUser').value,
            voting_enabled: document.getElementById('votingStatus').value,
            voting_start_time: document.getElementById('votingStartTime').value,
            voting_end_time: document.getElementById('votingEndTime').value,
            show_results: document.getElementById('showRealTimeResults').value
        };
        
        fetch('update_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(settings)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('设置已更新');
                document.querySelector('.navbar-brand').textContent = settings.voting_name;
            } else {
                alert('更新设置失败：' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            alert('更新设置失败：' + error);
        });
    }
    </script>
</body>
</html> 