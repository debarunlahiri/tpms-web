<?php
$menuItems = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-line', 'permission' => 'dashboard'],
    ['id' => 'notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'permission' => 'dashboard'],
    ['id' => 'profile', 'label' => 'My Profile', 'icon' => 'fa-user-circle', 'permission' => 'dashboard'],
    ['id' => 'client_portal', 'label' => 'Client Portal', 'icon' => 'fa-user-shield', 'permission' => 'client_access'],
    ['id' => 'projects', 'label' => 'Projects', 'icon' => 'fa-project-diagram', 'permission' => 'projects'],
    ['id' => 'leads', 'label' => 'Leads', 'icon' => 'fa-filter', 'permission' => 'leads'],
    ['id' => 'contacts', 'label' => 'Contacts', 'icon' => 'fa-address-book', 'permission' => 'contacts'],
    ['id' => 'deals', 'label' => 'Deals', 'icon' => 'fa-handshake', 'permission' => 'deals'],
    ['id' => 'tasks', 'label' => 'Tasks', 'icon' => 'fa-tasks', 'permission' => 'tasks'],
    ['id' => 'media', 'label' => 'Media', 'icon' => 'fa-images', 'permission' => 'media'],
    ['id' => 'invoices', 'label' => 'Invoices', 'icon' => 'fa-file-invoice-dollar', 'permission' => 'invoices'],
    ['id' => 'reports', 'label' => 'Reports', 'icon' => 'fa-chart-pie', 'permission' => 'reports'],
    ['id' => 'logs', 'label' => 'Activity Logs', 'icon' => 'fa-clipboard-list', 'permission' => 'roles'],
    ['id' => 'storage', 'label' => 'Storage', 'icon' => 'fa-hdd', 'permission' => 'storage'],
    ['id' => 'settings', 'label' => 'Settings', 'icon' => 'fa-cog', 'permission' => 'settings'],
];
?>
<aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-secondary-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 z-40 shadow-2xl">
    <div class="h-full flex flex-col">
        <div class="p-6 border-b border-gray-700">
            <a href="dashboard.php" class="block hover:opacity-90 transition-opacity" aria-label="TPMS Dashboard">
                <div class="h-11 w-32 rounded-lg bg-white overflow-hidden flex items-center justify-center px-2">
                    <img src="assets/images/logo.png" alt="TPMS" class="w-full h-full object-cover scale-[1.3]">
                </div>
            </a>
        </div>
        
        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1 px-3">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (isAdmin() || hasPermission($item['permission'])): ?>
                    <li>
                        <a href="<?php echo $item['id']; ?>.php" 
                           class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 group <?php echo $currentPage === $item['id'] ? 'bg-primary-600 text-white shadow-lg' : 'text-gray-300 hover:bg-gray-800 hover:text-white'; ?>">
                            <i class="fas <?php echo $item['icon']; ?> w-5 text-center group-hover:scale-110 transition-transform"></i>
                            <span><?php echo $item['label']; ?></span>
                            <?php if ($currentPage === $item['id']): ?>
                                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
        
        <div class="p-4 border-t border-gray-700">
            <a href="?action=logout" data-confirm="Are you sure you want to log out?" data-confirm-title="Log out" data-confirm-style="warning" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-300 hover:bg-red-600 hover:text-white transition-all duration-200 group">
                <i class="fas fa-sign-out-alt w-5 text-center group-hover:rotate-180 transition-transform duration-300"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</aside>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>
