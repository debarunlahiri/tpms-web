<?php
$pageTitle = 'Reports';
require_once 'includes/header.php';
requirePermission('reports');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$isAdminUser = isAdmin();

// Date range
$range = $_GET['range'] ?? '30';
$dateFilter = date('Y-m-d', strtotime("-$range days"));

// Overview metrics
$whereDate = $viewAll ? '' : "AND assigned_to = $userId";
$totalContacts = $db->query("SELECT COUNT(*) FROM contacts WHERE created_at >= '$dateFilter' $whereDate")->fetchColumn();
$totalLeads = $db->query("SELECT COUNT(*) FROM leads WHERE created_at >= '$dateFilter' $whereDate")->fetchColumn();
$totalDeals = $db->query("SELECT COUNT(*) FROM deals WHERE created_at >= '$dateFilter' $whereDate")->fetchColumn();
$wonRevenue = $db->query("SELECT COALESCE(SUM(value), 0) FROM deals WHERE stage='closed_won' AND (actual_close_date >= '$dateFilter' OR updated_at >= '$dateFilter') $whereDate")->fetchColumn();

// Leads by status
$leadsByStatus = $db->query("SELECT status, COUNT(*) as count FROM leads WHERE 1=1 $whereDate GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Deals by stage
$dealsByStage = $db->query("SELECT stage, COUNT(*) as count, COALESCE(SUM(value), 0) as value FROM deals WHERE 1=1 $whereDate GROUP BY stage")->fetchAll();

// Revenue by month
$revenueByMonth = $db->query("SELECT DATE_FORMAT(actual_close_date, '%Y-%m') as month, COALESCE(SUM(value), 0) as revenue FROM deals WHERE stage='closed_won' AND actual_close_date IS NOT NULL $whereDate GROUP BY month ORDER BY month LIMIT 12")->fetchAll();

// Top performers (admin only)
$topPerformers = [];
if ($isAdminUser) {
    $topPerformers = $db->query("SELECT u.name, COUNT(d.id) as deals_won, COALESCE(SUM(d.value), 0) as revenue FROM deals d JOIN users u ON d.assigned_to = u.id WHERE d.stage='closed_won' GROUP BY d.assigned_to ORDER BY revenue DESC LIMIT 5")->fetchAll();
}

// Recent conversion
$conversionRate = $totalLeads > 0 ? round(($db->query("SELECT COUNT(*) FROM leads WHERE status='won' AND created_at >= '$dateFilter' $whereDate")->fetchColumn() / $totalLeads) * 100, 1) : 0;

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Reports & Analytics</h1>
                <p class="text-gray-500 mt-1">Insights into your sales performance</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <form method="GET" action="" class="flex gap-2">
                    <label class="flex flex-col gap-1 text-xs font-medium text-gray-600">Date Range
                    <select name="range" onchange="this.form.submit()" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-900 font-normal focus:outline-none focus:border-primary-500">
                        <option value="7" <?php echo $range === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $range === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $range === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="365" <?php echo $range === '365' ? 'selected' : ''; ?>>Last Year</option>
                    </select>
                    </label>
                </form>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">New Contacts</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo $totalContacts; ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center"><i class="fas fa-user-plus text-blue-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">New Leads</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo $totalLeads; ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-purple-50 flex items-center justify-center"><i class="fas fa-filter text-purple-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Revenue Won</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo round($wonRevenue); ?>" data-prefix="<?php echo setting('currency', '$'); ?>">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-50 flex items-center justify-center"><i class="fas fa-dollar-sign text-green-600 text-xl"></i></div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-xl p-6 shadow-sm border border-gray-100 animate-slide-up stagger-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Conversion Rate</p>
                        <h3 class="text-3xl font-bold text-secondary-900" data-counter="<?php echo $conversionRate; ?>" data-suffix="%">0</h3>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-orange-50 flex items-center justify-center"><i class="fas fa-percentage text-orange-600 text-xl"></i></div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-200">
                <h2 class="text-lg font-bold text-secondary-900 mb-6">Revenue Trend</h2>
                <div class="h-64 flex items-end justify-between gap-2">
                    <?php 
                    $maxRevenue = max(array_merge(array_column($revenueByMonth, 'revenue'), [1]));
                    foreach ($revenueByMonth as $month): 
                        $height = ($month['revenue'] / $maxRevenue) * 100;
                    ?>
                    <div class="flex-1 flex flex-col items-center gap-2 group">
                        <div class="relative w-full flex items-end justify-center">
                            <div class="w-full max-w-[40px] bg-gradient-to-t from-primary-600 to-primary-400 rounded-t-lg transition-all duration-500 group-hover:from-primary-700 group-hover:to-primary-500" style="height: 0px" data-height="<?php echo $height; ?>"></div>
                            <div class="absolute -top-8 opacity-0 group-hover:opacity-100 transition-opacity bg-gray-800 text-white text-xs px-2 py-1 rounded whitespace-nowrap"><?php echo formatCurrency($month['revenue']); ?></div>
                        </div>
                        <span class="text-xs text-gray-500 rotate-0"><?php echo date('M', strtotime($month['month'] . '-01')); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($revenueByMonth)): ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-400">No revenue data available</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-300">
                <h2 class="text-lg font-bold text-secondary-900 mb-6">Deals by Stage</h2>
                <div class="space-y-4">
                    <?php foreach ($dealsByStage as $stage): 
                        $totalDealsValue = max(array_sum(array_column($dealsByStage, 'value')), 1);
                        $width = ($stage['value'] / $totalDealsValue) * 100;
                        $colors = [
                            'prospecting' => 'bg-gray-500',
                            'qualification' => 'bg-blue-500',
                            'proposal' => 'bg-yellow-500',
                            'negotiation' => 'bg-orange-500',
                            'closed_won' => 'bg-green-500',
                            'closed_lost' => 'bg-red-500'
                        ];
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-gray-700"><?php echo ucwords(str_replace('_', ' ', $stage['stage'])); ?></span>
                            <span class="text-gray-500"><?php echo $stage['count']; ?> deals - <?php echo formatCurrency($stage['value']); ?></span>
                        </div>
                        <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full <?php echo $colors[$stage['stage']] ?? 'bg-gray-500'; ?> rounded-full transition-all duration-1000" style="width: 0%" data-width="<?php echo $width; ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($dealsByStage)): ?>
                        <p class="text-gray-400 text-center py-8">No deal data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Lead Status & Top Performers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-400">
                <h2 class="text-lg font-bold text-secondary-900 mb-6">Leads by Status</h2>
                <div class="space-y-3">
                    <?php foreach (['new','contacted','qualified','proposal','negotiation','won','lost'] as $status): 
                        $count = $leadsByStatus[$status] ?? 0;
                        $total = max(array_sum($leadsByStatus), 1);
                        $width = ($count / $total) * 100;
                    ?>
                    <div class="flex items-center gap-3">
                        <span class="w-24 text-sm text-gray-600 capitalize"><?php echo $status; ?></span>
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full <?php echo statusColor($status); ?> rounded-full transition-all duration-1000" style="width: 0%" data-width="<?php echo $width; ?>"></div>
                        </div>
                        <span class="w-10 text-right text-sm font-medium text-secondary-900"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($isAdminUser): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-500">
                <h2 class="text-lg font-bold text-secondary-900 mb-6">Top Performers</h2>
                <div class="space-y-4">
                    <?php if (empty($topPerformers)): ?>
                        <p class="text-gray-400 text-center py-8">No performance data available</p>
                    <?php else: ?>
                        <?php foreach ($topPerformers as $index => $performer): ?>
                        <div class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold text-sm"><?php echo $index + 1; ?></div>
                            <div class="flex-1">
                                <p class="font-medium text-secondary-900"><?php echo sanitize($performer['name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $performer['deals_won']; ?> deals won</p>
                            </div>
                            <span class="font-bold text-green-600"><?php echo formatCurrency($performer['revenue']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('[data-height]').forEach(bar => {
            bar.style.height = bar.dataset.height + '%';
        });
        document.querySelectorAll('[data-width]').forEach(bar => {
            bar.style.width = bar.dataset.width + '%';
        });
    }, 500);
});
</script>

<?php include 'includes/footer.php'; ?>
