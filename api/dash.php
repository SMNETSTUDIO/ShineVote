<?php
require_once 'config.php';

$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    header('Location: /api/login.php');
    exit;
}

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
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

$now = new DateTime();
$startTime = new DateTime($settings['voting_start_time']);
$endTime = new DateTime($settings['voting_end_time']);
$votingEnabled = $settings['voting_enabled'] && $now >= $startTime && $now <= $endTime;

$stmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userVoteCount = $stmt->fetch()['vote_count'];
$canVote = $userVoteCount < $settings['max_votes_per_user'];

$stmt = $pdo->query("
    SELECT c.*, COUNT(v.id) as vote_count 
    FROM candidates c 
    LEFT JOIN votes v ON c.id = v.candidate_id 
    GROUP BY c.id 
    ORDER BY COUNT(v.id) DESC
");
$candidates = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($settings['voting_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f0f2f5;
        }
        .navbar {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .user-info {
            color: #fff;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .card-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            color: white;
            font-weight: 600;
            padding: 1.2rem 1.5rem;
            border: none;
        }
        .card-body {
            padding: 1.5rem;
            background: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0056b3 0%, #0088ff 100%);
            border: none;
            font-weight: 500;
            color: white !important;
        }
        .btn-primary:hover {
            background-color: #003d82;
            border-color: #003d82;
        }
        .btn-outline-danger {
            color: #fff;
            border-color: #fff;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #fff;
        }
        .vote-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0056b3;
            margin-bottom: 1rem;
        }
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            border: none;
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.8) 100%);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .card-text {
            color: #2c3e50;
        }
        .btn-primary[disabled] {
            background: #6c757d;
            color: rgba(255,255,255,0.8) !important;
            opacity: 0.8;
        }
        #votes-count {
            color: #2c3e50;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }
            
            .navbar {
                padding: 0.5rem;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .user-info {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 0.5rem;
            }
            
            .card {
                margin-bottom: 1rem;
            }
            
            .card-header {
                padding: 0.8rem 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .vote-count {
                font-size: 1.2rem;
            }
            
            .alert {
                padding: 0.8rem 1rem;
                margin-bottom: 1rem;
            }
            
            .chart-container {
                padding: 0.8rem;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <span class="navbar-brand"><?= htmlspecialchars($settings['voting_name']) ?></span>
            <div class="user-info">
                <span class="me-3">欢迎，<?= htmlspecialchars($user['username']) ?></span>
                <a href="logout.php" class="btn btn-outline-danger">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div id="vote-status-alert" class="alert 
            <?php if (!$votingEnabled): ?>
                alert-danger
            <?php elseif ($userVoteCount >= $settings['max_votes_per_user']): ?>
                alert-info
            <?php else: ?>
                alert-warning
            <?php endif; ?>">
            <?php if (!$votingEnabled): ?>
                <?php if (!$settings['voting_enabled']): ?>
                    投票已暂停
                <?php elseif ($now < $startTime): ?>
                    投票还未开始，开始时间：<?= $startTime->format('Y-m-d H:i') ?>
                <?php else: ?>
                    投票已结束
                <?php endif; ?>
            <?php elseif ($userVoteCount >= $settings['max_votes_per_user']): ?>
                您已完成所有可投票数（<?= $settings['max_votes_per_user'] ?>票），感谢参与！
            <?php else: ?>
                您还可以投<?= $settings['max_votes_per_user'] - $userVoteCount ?>票
            <?php endif; ?>
        </div>

        <?php if ($settings['show_results'] || $userVoteCount >= $settings['max_votes_per_user']): ?>
            <div class="card mb-4">
                <div class="card-header">实时投票结果</div>
                <div class="card-body chart-container">
                    <canvas id="voteBarChart" style="height: 300px;"></canvas>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php foreach ($candidates as $candidate): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header"><?= htmlspecialchars($candidate['name']) ?></div>
                    <div class="card-body">
                        <p class="card-text vote-count">当前票数: <span id="votes-<?= $candidate['id'] ?>"><?= $candidate['vote_count'] ?></span></p>
                        <button class="btn btn-primary vote-btn w-100" 
                                data-id="<?= $candidate['id'] ?>"
                                <?= $userVoteCount >= $settings['max_votes_per_user'] ? 'disabled' : '' ?>>
                            <?= $userVoteCount >= $settings['max_votes_per_user'] ? '已投票' : '投票' ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    const chart = new Chart(document.getElementById('voteBarChart'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            animation: {
                duration: 1000
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    function updateChart(data) {
        data.sort((a, b) => b.vote_count - a.vote_count);
        chart.data.labels = data.map(c => c.name);
        chart.data.datasets[0].data = data.map(c => c.vote_count);
        chart.update();
    }

    function updateVotes() {
        fetch('get_votes.php')
            .then(response => response.json())
            .then(data => {
                data.forEach(candidate => {
                    document.getElementById(`votes-${candidate.id}`).textContent = candidate.vote_count;
                });
                updateChart(data);
            });
    }

    setInterval(updateVotes, 500);
    document.querySelectorAll('.vote-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('确定要投票给这位候选人吗？投票后将无法更改！')) {
                return;
            }
            
            const candidateId = this.dataset.id;
            fetch('vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `candidate_id=${candidateId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('投票成功！');
                    const alertDiv = document.getElementById('vote-status-alert');
                    alertDiv.className = 'alert alert-info';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> 您已经完成投票，感谢参与！';
                    document.querySelectorAll('.vote-btn').forEach(b => {
                        b.disabled = true;
                        b.textContent = '已投票';
                    });
                    updateVotes();
                } else {
                    alert(data.message || '投票失败，请稍后重试');
                }
            })
            .catch(error => {
                alert('系统错误，请稍后重试');
            });
        });
    });

    updateVotes();
    </script>
</body>
</html>
