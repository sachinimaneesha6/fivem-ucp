<?php
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$auth->requireAdmin();

// Get analytics data
$analytics_query = "SELECT 
    DATE(last_updated) as date,
    COUNT(DISTINCT citizenid) as active_players,
    AVG(JSON_EXTRACT(money, '$.bank') + JSON_EXTRACT(money, '$.cash')) as avg_money
    FROM players 
    WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(last_updated)
    ORDER BY date DESC
    LIMIT 30";
$analytics_stmt = $db->prepare($analytics_query);
$analytics_stmt->execute();
$daily_stats = $analytics_stmt->fetchAll(PDO::FETCH_ASSOC);

// Job distribution
$job_query = "SELECT 
    JSON_EXTRACT(job, '$.name') as job_name,
    JSON_EXTRACT(job, '$.label') as job_label,
    COUNT(*) as player_count
    FROM players 
    GROUP BY JSON_EXTRACT(job, '$.name')
    ORDER BY player_count DESC";
$job_stmt = $db->prepare($job_query);
$job_stmt->execute();
$job_distribution = $job_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top players by money
$rich_players_query = "SELECT 
    citizenid,
    JSON_EXTRACT(charinfo, '$.firstname') as firstname,
    JSON_EXTRACT(charinfo, '$.lastname') as lastname,
    (JSON_EXTRACT(money, '$.bank') + JSON_EXTRACT(money, '$.cash')) as total_money
    FROM players 
    ORDER BY total_money DESC 
    LIMIT 10";
$rich_players_stmt = $db->prepare($rich_players_query);
$rich_players_stmt->execute();
$rich_players = $rich_players_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Server Analytics';
include '../includes/header.php';
?>

<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <h1 class="text-xl font-bold text-red-400">Admin Panel</h1>
                </div>
                <div class="hidden md:ml-6 md:flex md:space-x-8">
                    <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="users.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="tickets.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-ticket-alt mr-2"></i>Tickets
                    </a>
                    <a href="analytics.php" class="text-red-300 hover:text-red-200 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                    <a href="server.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-server mr-2"></i>Server
                    </a>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="../dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to UCP
                </a>
                <a href="../logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Server Analytics</h1>
        <p class="text-gray-400">Detailed server statistics and trends</p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Player Activity Chart -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">
                <i class="fas fa-chart-line text-blue-400 mr-2"></i>Daily Player Activity
            </h2>
            <canvas id="playerActivityChart" width="400" height="200"></canvas>
        </div>
        
        <!-- Job Distribution -->
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
            <h2 class="text-xl font-bold text-white mb-4">
                <i class="fas fa-briefcase text-green-400 mr-2"></i>Job Distribution
            </h2>
            <canvas id="jobDistributionChart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <!-- Top Players -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-xl font-bold text-white mb-6">
            <i class="fas fa-trophy text-fivem-primary mr-2"></i>Wealthiest Players
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Character</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Citizen ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Total Money</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach ($rich_players as $index => $player): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if ($index < 3): ?>
                                        <i class="fas fa-medal text-<?php echo ['yellow', 'gray', 'yellow'][$index]; ?>-400 mr-2"></i>
                                    <?php endif; ?>
                                    <span class="text-white font-bold">#<?php echo $index + 1; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-white font-medium">
                                    <?php echo htmlspecialchars(trim($player['firstname'], '"') . ' ' . trim($player['lastname'], '"')); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-300">
                                <?php echo htmlspecialchars($player['citizenid']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-green-400 font-bold text-lg">$<?php echo number_format($player['total_money']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Player Activity Chart
const activityCtx = document.getElementById('playerActivityChart').getContext('2d');
const activityChart = new Chart(activityCtx, {
    type: 'line',
    data: {
        labels: [<?php echo "'" . implode("','", array_reverse(array_column($daily_stats, 'date'))) . "'"; ?>],
        datasets: [{
            label: 'Active Players',
            data: [<?php echo implode(',', array_reverse(array_column($daily_stats, 'active_players'))); ?>],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: {
                    color: '#d1d5db'
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: '#9ca3af'
                },
                grid: {
                    color: '#374151'
                }
            },
            y: {
                ticks: {
                    color: '#9ca3af'
                },
                grid: {
                    color: '#374151'
                }
            }
        }
    }
});

// Job Distribution Chart
const jobCtx = document.getElementById('jobDistributionChart').getContext('2d');
const jobChart = new Chart(jobCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo "'" . implode("','", array_map(function($job) { return trim($job['job_label'], '"'); }, $job_distribution)) . "'"; ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($job_distribution, 'player_count')); ?>],
            backgroundColor: [
                '#f39c12', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6',
                '#1abc9c', '#f1c40f', '#e67e22', '#34495e', '#95a5a6'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#d1d5db',
                    padding: 20
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>