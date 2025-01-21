<?php
require_once 'config.php';

$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    header('Location: login.php');
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
    header('Location: /api/login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM users WHERE is_admin = 0");
$users = $stmt->fetchAll();
$stmt = $pdo->query("
    SELECT c.*, 
           COALESCE((SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id), 0) as vote_count 
    FROM candidates c
");
$candidates = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

// 获取今日活跃用户数
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT user_id) as active_users 
    FROM votes 
    WHERE DATE(created_at) = ?
");
$stmt->execute([$today]);
$activeUsers = $stmt->fetch()['active_users'];

// 获取总投票数
$stmt = $pdo->query("SELECT COUNT(*) as total_votes FROM votes");
$totalVotes = $stmt->fetch()['total_votes'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .nav-tabs {
            border-bottom: none;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        .nav-tabs .nav-link {
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: #495057;
            border-radius: 12px 12px 0 0;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            background: rgba(0,0,0,0.05);
        }
        .nav-tabs .nav-link.active {
            background: white;
            color: #0056b3;
            position: relative;
        }
        .nav-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #0056b3 0%, #00a0ff 100%);
            border-radius: 3px 3px 0 0;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1.5rem;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-left: 4px solid #0056b3;
            padding: 1.5rem;
        }
        .stats-card h6 {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .stats-card h3 {
            color: #2c3e50;
            font-weight: 700;
            margin: 0;
        }
        .table {
            margin: 0;
        }
        .table thead th {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem 1.5rem;
        }
        .table tbody td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0056b3 0%, #0088ff 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #003d82 0%, #0056b3 100%);
            box-shadow: 0 4px 12px rgba(0,86,179,0.2);
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #ff4d5b 100%);
            border: none;
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffdb4d 100%);
            border: none;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 0.7rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.2rem rgba(0,86,179,0.25);
        }
        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            border: none;
            padding: 1.5rem;
        }
        .modal-header .modal-title {
            color: white;
            font-weight: 600;
        }
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }
        .modal-body {
            padding: 2rem;
        }
        .tab-content {
            background: white;
            border-radius: 0 0 16px 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .tab-pane h3 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
            }
            .stats-card {
                margin-bottom: 1rem;
            }
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
            <div class="tab-pane active" id="users">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h6 class="text-muted">总用户数</h6>
                                <h3><?= count($users) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h6 class="text-muted">今日活跃用户</h6>
                                <h3><?= $activeUsers ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h6 class="text-muted">总投票数</h6>
                                <h3><?= $totalVotes ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
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

    function resetVotes(candidateId) {
        document.getElementById('resetVotesCandidateId').value = candidateId;
        new bootstrap.Modal(document.getElementById('resetVotesModal')).show();
    }

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

    document.getElementById('changePasswordForm').onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
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