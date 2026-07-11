<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';
requirePermission('dashboard');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$isClient = hasPermission('client_access') && !hasPermission('leads');

// Statistics
$stats = [
    'contacts' => $db->query("SELECT COUNT(*) FROM contacts " . ($viewAll ? '' : "WHERE assigned_to = $userId" . ($isClient ? " OR user_id = $userId" : "")))->fetchColumn(),
    'leads' => $db->query("SELECT COUNT(*) FROM leads " . ($viewAll ? '' : "WHERE assigned_to = $userId"))->fetchColumn(),
    'deals' => $db->query("SELECT COUNT(*) FROM deals " . ($viewAll ? '' : "WHERE assigned_to = $userId"))->fetchColumn(),
    'tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed' " . ($viewAll ? '' : "AND assigned_to = $userId"))->fetchColumn(),
];

$pipelineValue = $db->query("SELECT COALESCE(SUM(value), 0) FROM deals WHERE stage NOT IN ('closed_won', 'closed_lost') " . ($viewAll ? '' : "AND assigned_to = $userId"))->fetchColumn();
$revenue = $db->query("SELECT COALESCE(SUM(value), 0) FROM deals WHERE stage = 'closed_won' " . ($viewAll ? '' : "AND assigned_to = $userId"))->fetchColumn();

// Recent activities
$activities = $db->query("SELECT a.*, u.name as user_name FROM activities a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 10")->fetchAll();

// Recent leads
$recentLeads = $db->query("SELECT l.*, c.first_name, c.last_name, c.company FROM leads l LEFT JOIN contacts c ON l.contact_id = c.id " . ($viewAll ? '' : "WHERE l.assigned_to = $userId") . " ORDER BY l.created_at DESC LIMIT 5")->fetchAll();

// Upcoming tasks
$upcomingTasks = $db->query("SELECT t.*, c.first_name, c.last_name FROM tasks t LEFT JOIN contacts c ON t.related_to = 'contact' AND t.related_id = c.id WHERE t.status != 'completed' " . ($viewAll ? '' : "AND t.assigned_to = $userId") . " ORDER BY t.due_date ASC LIMIT 5")->fetchAll();

// Deals by stage for chart
$dealsByStageRaw = $db->query("SELECT stage, COUNT(*) as count, COALESCE(SUM(value), 0) as value FROM deals " . ($viewAll ? '' : "WHERE assigned_to = $userId") . " GROUP BY stage")->fetchAll();
$dealsByStage = [];
foreach ($dealsByStageRaw as $row) {
    $dealsByStage[$row['stage']] = $row;
}

$stageLabels = ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won', 'closed_lost'];
$stageData = [];
foreach ($stageLabels as $stage) {
    $stageData[$stage] = $dealsByStage[$stage] ?? ['count' => 0, 'value' => 0];
}

// Real chart data (last six calendar months)
$monthLabels = [];
$monthlyLeads = [];
$monthlyDeals = [];
$monthlyRevenue = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i months"));
    $monthLabels[$monthKey] = date('M Y', strtotime($monthKey . '-01'));
    $monthlyLeads[$monthKey] = 0;
    $monthlyDeals[$monthKey] = 0;
    $monthlyRevenue[$monthKey] = 0;
}

$leadTrend = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') month_key, COUNT(*) total FROM leads WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') " . ($viewAll ? '' : "AND assigned_to = $userId ") . "GROUP BY month_key")->fetchAll();
foreach ($leadTrend as $row) {
    if (array_key_exists($row['month_key'], $monthlyLeads)) $monthlyLeads[$row['month_key']] = (int) $row['total'];
}

$dealTrend = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') month_key, COUNT(*) total, COALESCE(SUM(CASE WHEN stage = 'closed_won' THEN value ELSE 0 END), 0) revenue FROM deals WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01') " . ($viewAll ? '' : "AND assigned_to = $userId ") . "GROUP BY month_key")->fetchAll();
foreach ($dealTrend as $row) {
    if (array_key_exists($row['month_key'], $monthlyDeals)) {
        $monthlyDeals[$row['month_key']] = (int) $row['total'];
        $monthlyRevenue[$row['month_key']] = (float) $row['revenue'];
    }
}

$taskStatusData = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
$taskStatusRaw = $db->query("SELECT status, COUNT(*) total FROM tasks " . ($viewAll ? '' : "WHERE assigned_to = $userId ") . "GROUP BY status")->fetchAll();
foreach ($taskStatusRaw as $row) {
    $taskStatusData[$row['status']] = (int) $row['total'];
}

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <!-- Page Header -->
        <div class="mb-8 animate-fade-in">
            <h1 class="text-3xl font-bold text-secondary-900">Dashboard</h1>
            <p class="text-gray-500 mt-1">Welcome back, <?php echo sanitize($_SESSION['user_name']); ?>. Here's what's happening today.</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Total Contacts</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo $stats['contacts']; ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-address-book text-blue-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-blue-600 font-medium"><i class="fas fa-database mr-1"></i><?php echo $stats['contacts']; ?></span>
                    <span class="text-gray-400 ml-2">contact records</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Active Leads</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo $stats['leads']; ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-50 flex items-center justify-center">
                        <i class="fas fa-filter text-purple-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-purple-600 font-medium"><i class="fas fa-chart-line mr-1"></i><?php echo $stats['deals']; ?></span>
                    <span class="text-gray-400 ml-2">deals in CRM</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Pipeline Value</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo round($pipelineValue); ?>" data-prefix="<?php echo setting('currency', '$'); ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-green-600 font-medium"><i class="fas fa-handshake mr-1"></i>Open</span>
                    <span class="text-gray-400 ml-2">opportunity value</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Revenue Won</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo round($revenue); ?>" data-prefix="<?php echo setting('currency', '$'); ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-orange-50 flex items-center justify-center">
                        <i class="fas fa-trophy text-orange-600 text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm">
                    <span class="text-orange-600 font-medium"><i class="fas fa-check-circle mr-1"></i>Closed won</span>
                    <span class="text-gray-400 ml-2">all-time revenue</span>
                </div>
            </div>
        </div>

        <!-- Live Analytics -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-secondary-900">Sales Activity</h2>
                    <p class="text-sm text-gray-500">Leads and deals created during the last six months</p>
                </div>
                <div class="relative h-80"><canvas id="salesTrendChart"></canvas></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-100">
                    <h2 class="text-lg font-bold text-secondary-900">Deals by Stage</h2>
                    <p class="text-sm text-gray-500 mb-4">Current pipeline distribution</p>
                    <div class="relative h-64"><canvas id="dealStageChart"></canvas></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-200">
                    <h2 class="text-lg font-bold text-secondary-900">Task Status</h2>
                    <p class="text-sm text-gray-500 mb-4">Workload completion overview</p>
                    <div class="relative h-64"><canvas id="taskStatusChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Charts & Pipeline -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-200">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-secondary-900">Sales Pipeline</h2>
                    <a href="deals.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
                </div>
                <div class="space-y-4">
                    <?php foreach ($stageLabels as $index => $stage): 
                        $count = $stageData[$stage]['count'];
                        $value = $stageData[$stage]['value'];
                        $maxValue = max(array_merge(array_column($stageData, 'value'), [1]));
                        $width = ($value / $maxValue) * 100;
                        $colors = ['bg-gray-500', 'bg-blue-500', 'bg-yellow-500', 'bg-orange-500', 'bg-green-500', 'bg-red-500'];
                    ?>
                    <div class="group">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700 capitalize"><?php echo str_replace('_', ' ', $stage); ?></span>
                            <span class="text-gray-500"><?php echo $count; ?> deals - <?php echo formatCurrency($value); ?></span>
                        </div>
                        <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full <?php echo $colors[$index]; ?> rounded-full transition-all duration-1000 ease-out" style="width: 0%" data-width="<?php echo $width; ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-300">
                <h2 class="text-lg font-bold text-secondary-900 mb-4">Pending Tasks</h2>
                <div class="space-y-3">
                    <?php if (empty($upcomingTasks)): ?>
                        <p class="text-gray-500 text-center py-8">No pending tasks</p>
                    <?php else: ?>
                        <?php foreach ($upcomingTasks as $task): ?>
                        <div class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-8 h-8 rounded-full bg-<?php echo $task['priority'] === 'high' ? 'red' : ($task['priority'] === 'medium' ? 'yellow' : 'green'); ?>-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-<?php echo $task['type'] === 'call' ? 'phone' : ($task['type'] === 'email' ? 'envelope' : ($task['type'] === 'meeting' ? 'calendar' : 'check')); ?> text-xs text-<?php echo $task['priority'] === 'high' ? 'red' : ($task['priority'] === 'medium' ? 'yellow' : 'green'); ?>-600"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-secondary-900 truncate"><?php echo sanitize($task['title']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $task['due_date'] ? formatDate($task['due_date'], 'M d, Y') : 'No due date'; ?></p>
                            </div>
                            <a href="tasks.php?complete=<?php echo $task['id']; ?>" class="text-green-500 hover:text-green-700" data-confirm="Mark this task as completed?"><i class="fas fa-check-circle"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a href="tasks.php" class="block mt-4 text-center text-sm text-primary-600 hover:text-primary-700 font-medium">View All Tasks</a>
            </div>
        </div>

        <!-- Recent Leads & Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-400">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-secondary-900">Recent Leads</h2>
                    <a href="leads.php" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                                <th class="pb-3">Lead</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($recentLeads)): ?>
                                <tr><td colspan="3" class="py-8 text-center text-gray-500">No leads found</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLeads as $lead): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-700">
                                                <?php echo getInitials(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? '')); ?>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-secondary-900"><?php echo sanitize($lead['title']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo sanitize($lead['company'] ?? 'No Company'); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3"><span class="px-2 py-1 text-xs rounded-full <?php echo statusColor($lead['status']); ?>"><?php echo ucfirst($lead['status']); ?></span></td>
                                    <td class="py-3 text-sm font-medium text-secondary-900"><?php echo formatCurrency($lead['estimated_value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-500">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-secondary-900">Recent Activity</h2>
                    <a href="#" class="text-primary-600 hover:text-primary-700 text-sm font-medium">View All</a>
                </div>
                <div class="space-y-4 max-h-80 overflow-y-auto">
                    <?php if (empty($activities)): ?>
                        <p class="text-gray-500 text-center py-8">No recent activity</p>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-<?php echo $activity['action'] === 'login' ? 'sign-in-alt' : ($activity['action'] === 'create' ? 'plus' : ($activity['action'] === 'update' ? 'edit' : 'trash')); ?> text-xs text-gray-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-secondary-900"><?php echo sanitize($activity['description']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo sanitize($activity['user_name'] ?? 'System'); ?> &bull; <?php echo formatDate($activity['created_at'], 'M d, H:i'); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
// Animate progress bars
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('[data-width]').forEach(bar => {
            bar.style.width = bar.dataset.width + '%';
        });
    }, 500);

    const chartText = '#64748b';
    const gridColor = 'rgba(148, 163, 184, 0.16)';
    Chart.defaults.color = chartText;
    Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';

    new Chart(document.getElementById('salesTrendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_values($monthLabels)); ?>,
            datasets: [
                {
                    label: 'Leads',
                    data: <?php echo json_encode(array_values($monthlyLeads)); ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.12)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#8b5cf6'
                },
                {
                    label: 'Deals',
                    data: <?php echo json_encode(array_values($monthlyDeals)); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#2563eb'
                },
                {
                    type: 'bar',
                    label: 'Won Revenue',
                    data: <?php echo json_encode(array_values($monthlyRevenue)); ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.22)',
                    borderColor: '#22c55e',
                    borderWidth: 1,
                    borderRadius: 6,
                    yAxisID: 'yRevenue'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor }, title: { display: true, text: 'Records' } },
                yRevenue: { beginAtZero: true, position: 'right', grid: { display: false }, title: { display: true, text: 'Revenue' } }
            }
        }
    });

    const doughnutOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } },
            tooltip: { displayColors: true }
        }
    };

    new Chart(document.getElementById('dealStageChart'), {
        type: 'doughnut',
        data: {
            labels: ['Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Won', 'Lost'],
            datasets: [{
                data: <?php echo json_encode(array_map('intval', array_column($stageData, 'count'))); ?>,
                backgroundColor: ['#64748b', '#3b82f6', '#eab308', '#f97316', '#22c55e', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 7
            }]
        },
        options: doughnutOptions
    });

    new Chart(document.getElementById('taskStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'In Progress', 'Completed', 'Cancelled'],
            datasets: [{
                data: <?php echo json_encode(array_values($taskStatusData)); ?>,
                backgroundColor: ['#f59e0b', '#3b82f6', '#22c55e', '#94a3b8'],
                borderWidth: 0,
                hoverOffset: 7
            }]
        },
        options: doughnutOptions
    });
});
</script>

<?php include 'includes/footer.php'; ?>
