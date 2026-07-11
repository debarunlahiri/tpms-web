<?php
$pageTitle = 'Projects';
require_once 'includes/header.php';
requirePermission('projects');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$canAssign = hasPermission('assign_records') || isAdmin();

$action = $_GET['action'] ?? 'board';
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'status' => $_POST['status'] ?? 'planning',
            'priority' => $_POST['priority'] ?? 'medium',
            'start_date' => $_POST['start_date'] ?: null,
            'end_date' => $_POST['end_date'] ?: null,
            'budget' => floatval($_POST['budget'] ?? 0),
            'contact_id' => $_POST['contact_id'] ?: null,
            'assigned_to' => $_POST['assigned_to'] ?: $userId,
        ];

        try {
            if (isset($_POST['id']) && $_POST['id']) {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign projects to yourself.';
                } else {
                    $stmt = $db->prepare("UPDATE projects SET title=?, description=?, status=?, priority=?, start_date=?, end_date=?, budget=?, contact_id=?, assigned_to=? WHERE id=?");
                    $stmt->execute(array_values(array_merge($data, ['id' => $_POST['id']])));
                    logActivity('update', "Updated project: {$data['title']}", 'project', $_POST['id']);
                    redirect('projects.php', 'Project updated successfully.', 'success');
                }
            } else {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign projects to yourself.';
                } else {
                    $stmt = $db->prepare("INSERT INTO projects (title, description, status, priority, start_date, end_date, budget, contact_id, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$data['title'], $data['description'], $data['status'], $data['priority'], $data['start_date'], $data['end_date'], $data['budget'], $data['contact_id'], $data['assigned_to'], $userId]);
                    $projectId = $db->lastInsertId();
                    logActivity('create', "Created project: {$data['title']}", 'project', $projectId);
                    if ((int) $data['assigned_to'] !== (int) $userId) {
                        createNotification($data['assigned_to'], 'New project assigned', "You were assigned the project: {$data['title']}", 'info', "projects.php?action=edit&id=$projectId");
                    }
                    redirect('projects.php', 'Project created successfully.', 'success');
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $id = $_GET['delete'];
        if (!$viewAll) {
            $stmt = $db->prepare("DELETE FROM projects WHERE id=? AND assigned_to=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM projects WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted project #$id", 'project', $id);
            redirect('projects.php', 'Project deleted successfully.', 'success');
        } else {
            $error = 'Project not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting project: ' . $e->getMessage();
    }
}

// Get project for edit
$editProject = null;
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id=? " . ($viewAll ? '' : 'AND assigned_to=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $editProject = $stmt->fetch();
    if (!$editProject) {
        $error = 'Project not found.';
        $action = 'board';
    }
}

// Get project detail
$projectDetail = null;
if ($action === 'view' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT p.*, c.first_name, c.last_name, c.company, u.name as assigned_name 
                         FROM projects p 
                         LEFT JOIN contacts c ON p.contact_id = c.id 
                         LEFT JOIN users u ON p.assigned_to = u.id 
                         WHERE p.id=? " . ($viewAll ? '' : 'AND p.assigned_to=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $projectDetail = $stmt->fetch();
    if ($projectDetail) {
        $projectTasks = $db->prepare("SELECT t.*, u.name as assigned_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ? ORDER BY t.status, t.due_date");
        $projectTasks->execute([$_GET['id']]);
        $projectTasks = $projectTasks->fetchAll();
    } else {
        $error = 'Project not found.';
        $action = 'board';
    }
}

// Get all projects for board
$where = $viewAll ? '' : "WHERE p.assigned_to = $userId";
$projects = $db->query("SELECT p.*, c.first_name, c.last_name, c.company, u.name as assigned_name,
                        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
                        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks
                        FROM projects p 
                        LEFT JOIN contacts c ON p.contact_id = c.id 
                        LEFT JOIN users u ON p.assigned_to = u.id 
                        $where 
                        ORDER BY p.created_at DESC")->fetchAll();

$statuses = [
    'planning' => ['label' => 'Planning', 'color' => 'gray'],
    'in_progress' => ['label' => 'In Progress', 'color' => 'blue'],
    'on_hold' => ['label' => 'On Hold', 'color' => 'yellow'],
    'completed' => ['label' => 'Completed', 'color' => 'green'],
    'cancelled' => ['label' => 'Cancelled', 'color' => 'red'],
];

$projectsByStatus = [];
foreach ($statuses as $key => $info) {
    $projectsByStatus[$key] = array_filter($projects, fn($p) => $p['status'] === $key);
}

$contacts = $db->query("SELECT id, first_name, last_name, company FROM contacts ORDER BY first_name, last_name")->fetchAll();
$users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Projects</h1>
                <p class="text-gray-500 mt-1">Track projects and their tasks with Kanban board</p>
            </div>
            <div class="mt-4 sm:mt-0 flex gap-2">
                <a href="projects.php" class="px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors <?php echo $action === 'board' ? 'bg-gray-100' : ''; ?>"><i class="fas fa-columns mr-2"></i>Board</a>
                <a href="projects.php?action=add" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium"><i class="fas fa-plus"></i> Add Project</a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $editProject)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up max-w-4xl">
            <h2 class="text-xl font-bold text-secondary-900 mb-6"><?php echo $action === 'edit' ? 'Edit Project' : 'Add New Project'; ?></h2>
            <form method="POST" action="projects.php" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <?php if ($editProject): ?>
                <input type="hidden" name="id" value="<?php echo $editProject['id']; ?>">
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Title *</label>
                    <input type="text" name="title" required value="<?php echo sanitize($editProject['title'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editProject['description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach ($statuses as $key => $info): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($editProject['status'] ?? 'planning') === $key ? 'selected' : ''; ?>><?php echo $info['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select name="priority" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['low','medium','high'] as $priority): ?>
                        <option value="<?php echo $priority; ?>" <?php echo ($editProject['priority'] ?? 'medium') === $priority ? 'selected' : ''; ?>><?php echo ucfirst($priority); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $editProject['start_date'] ?? ''; ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $editProject['end_date'] ?? ''; ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Budget</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"><?php echo setting('currency', '₹'); ?></span>
                        <input type="number" step="0.01" name="budget" value="<?php echo $editProject['budget'] ?? '0.00'; ?>" class="w-full pl-8 pr-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact</label>
                    <select name="contact_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <option value="">-- Select Contact --</option>
                        <?php foreach ($contacts as $contact): ?>
                        <option value="<?php echo $contact['id']; ?>" <?php echo ($editProject['contact_id'] ?? '') == $contact['id'] ? 'selected' : ''; ?>><?php echo sanitize($contact['first_name'] . ' ' . $contact['last_name'] . ' - ' . $contact['company']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                    <select name="assigned_to" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($editProject['assigned_to'] ?? $userId) == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2 flex items-center gap-3">
                    <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium"><?php echo $action === 'edit' ? 'Update Project' : 'Create Project'; ?></button>
                    <a href="projects.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>

        <?php elseif ($action === 'view' && $projectDetail): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-secondary-900"><?php echo sanitize($projectDetail['title']); ?></h2>
                    <p class="text-gray-500 mt-1"><?php echo sanitize($projectDetail['description'] ?: 'No description'); ?></p>
                </div>
                <div class="mt-4 sm:mt-0 flex gap-2">
                    <a href="tasks.php?action=add&project_id=<?php echo $projectDetail['id']; ?>" class="btn-primary px-4 py-2 text-white rounded-lg text-sm"><i class="fas fa-plus mr-2"></i>Add Task</a>
                    <a href="projects.php?action=edit&id=<?php echo $projectDetail['id']; ?>" class="px-4 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 text-sm"><i class="fas fa-edit mr-2"></i>Edit</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase">Status</p>
                    <span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($projectDetail['status']); ?>"><?php echo ucwords(str_replace('_', ' ', $projectDetail['status'])); ?></span>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase">Priority</p>
                    <span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($projectDetail['priority']); ?>"><?php echo ucfirst($projectDetail['priority']); ?></span>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase">Budget</p>
                    <p class="font-bold text-secondary-900"><?php echo formatCurrency($projectDetail['budget']); ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase">Assigned</p>
                    <p class="font-medium text-secondary-900"><?php echo sanitize($projectDetail['assigned_name'] ?? 'Unassigned'); ?></p>
                </div>
            </div>

            <h3 class="text-lg font-bold text-secondary-900 mb-4">Project Tasks</h3>
            <?php if (empty($projectTasks)): ?>
                <p class="text-gray-500 text-center py-8">No tasks found. <a href="tasks.php?action=add&project_id=<?php echo $projectDetail['id']; ?>" class="text-primary-600 hover:underline">Add a task</a>.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Task</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Assigned</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Due Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($projectTasks as $task): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><a href="tasks.php?action=edit&id=<?php echo $task['id']; ?>" class="font-medium text-primary-600 hover:underline"><?php echo sanitize($task['title']); ?></a></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo sanitize($task['assigned_name'] ?? 'Unassigned'); ?></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full <?php echo statusColor($task['status']); ?>"><?php echo ucwords(str_replace('_', ' ', $task['status'])); ?></span></td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?php echo $task['due_date'] ? formatDate($task['due_date'], 'M d, Y') : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- Kanban Board -->
        <div class="overflow-x-auto pb-4 animate-slide-up">
            <div class="flex gap-4 min-w-max">
                <?php foreach ($statuses as $statusKey => $statusInfo): 
                    $statusProjects = $projectsByStatus[$statusKey];
                ?>
                <div class="w-80 flex-shrink-0">
                    <div class="bg-gray-100 rounded-t-lg p-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-<?php echo $statusInfo['color']; ?>-500"></span>
                            <h3 class="font-semibold text-sm text-secondary-900"><?php echo $statusInfo['label']; ?></h3>
                            <span class="bg-white text-gray-600 text-xs px-2 py-0.5 rounded-full"><?php echo count($statusProjects); ?></span>
                        </div>
                    </div>
                    <div class="pipeline-column bg-gray-50 rounded-b-lg p-3 space-y-3 border-2 border-dashed border-transparent" 
                         data-status="<?php echo $statusKey; ?>" 
                         ondrop="drop(event)" 
                         ondragover="allowDrop(event)"
                         ondragleave="dragLeave(event)">
                        <?php foreach ($statusProjects as $project): 
                            $progress = $project['task_count'] > 0 ? round(($project['completed_tasks'] / $project['task_count']) * 100) : 0;
                        ?>
                        <div class="deal-card bg-white rounded-lg p-4 shadow-sm border border-gray-200" draggable="true" ondragstart="drag(event)" data-project-id="<?php echo $project['id']; ?>">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="font-semibold text-sm text-secondary-900"><a href="projects.php?action=view&id=<?php echo $project['id']; ?>" class="hover:text-primary-600"><?php echo sanitize($project['title']); ?></a></h4>
                                <span class="text-xs font-medium text-<?php echo $project['priority'] === 'high' ? 'red' : ($project['priority'] === 'medium' ? 'yellow' : 'green'); ?>-600"><?php echo ucfirst($project['priority']); ?></span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3"><?php echo sanitize($project['company'] ?? ($project['first_name'] . ' ' . $project['last_name']) ?? 'No Contact'); ?></p>
                            <div class="mb-3">
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="text-gray-500">Progress</span>
                                    <span class="text-gray-700"><?php echo $progress; ?>%</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-<?php echo $statusInfo['color']; ?>-500 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs text-gray-500"><?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?> tasks</span>
                                <div class="flex gap-1">
                                    <a href="projects.php?action=edit&id=<?php echo $project['id']; ?>" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors"><i class="fas fa-edit text-xs"></i></a>
                                    <a href="projects.php?delete=<?php echo $project['id']; ?>" class="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors" data-confirm="Delete this project?"><i class="fas fa-trash text-xs"></i></a>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                                <div class="w-5 h-5 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-[10px] font-bold">
                                    <?php echo getInitials($project['assigned_name'] ?? 'UN'); ?>
                                </div>
                                <span class="text-xs text-gray-600 truncate"><?php echo sanitize($project['assigned_name'] ?? 'Unassigned'); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<meta name="csrf-token" content="<?php echo csrfToken(); ?>">

<script>
// Prevent duplicate form submissions by disabling submit buttons on click
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
