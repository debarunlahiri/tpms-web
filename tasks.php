<?php
$pageTitle = 'Tasks';
require_once 'includes/header.php';
requirePermission('tasks');

$db = getDB();
$userId = $_SESSION['user_id'];
$viewAll = canViewAll();
$canAssign = hasPermission('assign_records') || isAdmin();

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// Handle complete task
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    try {
        $id = $_GET['complete'];
        if (!$viewAll) {
            $stmt = $db->prepare("UPDATE tasks SET status='completed', completed_at=NOW() WHERE id=? AND assigned_to=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("UPDATE tasks SET status='completed', completed_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('complete', "Completed task #$id", 'task', $id);
            $success = 'Task marked as completed.';
        }
    } catch (Exception $e) {
        $error = 'Error completing task: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'type' => $_POST['type'] ?? 'other',
            'status' => $_POST['status'] ?? 'pending',
            'priority' => $_POST['priority'] ?? 'medium',
            'due_date' => $_POST['due_date'] ?: null,
            'related_to' => $_POST['related_to'] ?: null,
            'related_id' => $_POST['related_id'] ?: null,
            'project_id' => $_POST['project_id'] ?: null,
            'assigned_to' => $_POST['assigned_to'] ?: $userId,
        ];

        try {
            if (isset($_POST['id']) && $_POST['id']) {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign tasks to yourself.';
                } else {
                    $stmt = $db->prepare("UPDATE tasks SET title=?, description=?, type=?, status=?, priority=?, due_date=?, related_to=?, related_id=?, project_id=?, assigned_to=? WHERE id=?");
                    $stmt->execute(array_values(array_merge($data, ['id' => $_POST['id']])));
                    logActivity('update', "Updated task: {$data['title']}", 'task', $_POST['id']);
                    redirect('tasks.php', 'Task updated successfully.', 'success');
                }
            } else {
                if (!$canAssign && $data['assigned_to'] != $userId) {
                    $error = 'You can only assign tasks to yourself.';
                } else {
                    $stmt = $db->prepare("INSERT INTO tasks (title, description, type, status, priority, due_date, related_to, related_id, project_id, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$data['title'], $data['description'], $data['type'], $data['status'], $data['priority'], $data['due_date'], $data['related_to'], $data['related_id'], $data['project_id'], $data['assigned_to'], $userId]);
                    $taskId = $db->lastInsertId();
                    logActivity('create', "Created task: {$data['title']}", 'task', $taskId);
                    if ((int) $data['assigned_to'] !== (int) $userId) {
                        createNotification($data['assigned_to'], 'New task assigned', "You were assigned the task: {$data['title']}", $data['priority'] === 'high' ? 'warning' : 'info', "tasks.php?action=edit&id=$taskId");
                    }
                    redirect('tasks.php', 'Task created successfully.', 'success');
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
            $stmt = $db->prepare("DELETE FROM tasks WHERE id=? AND assigned_to=?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM tasks WHERE id=?");
            $stmt->execute([$id]);
        }
        if ($stmt->rowCount() > 0) {
            logActivity('delete', "Deleted task #$id", 'task', $id);
            redirect('tasks.php', 'Task deleted successfully.', 'success');
        } else {
            $error = 'Task not found or access denied.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting task: ' . $e->getMessage();
    }
}

// Get task for edit
$editTask = null;
if ($action === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id=? " . ($viewAll ? '' : 'AND assigned_to=?'));
    $stmt->execute($viewAll ? [$_GET['id']] : [$_GET['id'], $userId]);
    $editTask = $stmt->fetch();
    if (!$editTask) {
        $error = 'Task not found.';
        $action = 'list';
    }
}

// Get all tasks
$where = $viewAll ? '' : "WHERE t.assigned_to = $userId";
$tasks = $db->query("SELECT t.*, u.name as assigned_name, c.first_name, c.last_name, p.title as project_title 
                     FROM tasks t 
                     LEFT JOIN users u ON t.assigned_to = u.id 
                     LEFT JOIN contacts c ON t.related_to = 'contact' AND t.related_id = c.id
                     LEFT JOIN projects p ON t.project_id = p.id
                     $where 
                     ORDER BY FIELD(t.status, 'pending', 'in_progress', 'completed', 'cancelled'), t.due_date ASC, t.created_at DESC")->fetchAll();

$users = $db->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();
$contacts = $db->query("SELECT id, first_name, last_name FROM contacts ORDER BY first_name, last_name")->fetchAll();
$leads = $db->query("SELECT id, title FROM leads ORDER BY title")->fetchAll();
$deals = $db->query("SELECT id, title FROM deals ORDER BY title")->fetchAll();
$projects = $db->query("SELECT id, title FROM projects ORDER BY title")->fetchAll();

include 'includes/sidebar.php';
?>

<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    
    <main class="p-6 pt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-secondary-900">Tasks</h1>
                <p class="text-gray-500 mt-1">Track activities, calls, meetings and follow-ups</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="tasks.php?action=add" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-white rounded-lg font-medium"><i class="fas fa-plus"></i> Add Task</a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 animate-fade-in"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 animate-fade-in"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && $editTask)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up max-w-3xl">
            <h2 class="text-xl font-bold text-secondary-900 mb-6"><?php echo $action === 'edit' ? 'Edit Task' : 'Add New Task'; ?></h2>
            <form method="POST" action="tasks.php" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <?php if ($editTask): ?>
                <input type="hidden" name="id" value="<?php echo $editTask['id']; ?>">
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Task Title *</label>
                    <input type="text" name="title" required value="<?php echo sanitize($editTask['title'] ?? ''); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['call','email','meeting','follow_up','demo','other'] as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($editTask['type'] ?? 'other') === $type ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select name="priority" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['low','medium','high'] as $priority): ?>
                        <option value="<?php echo $priority; ?>" <?php echo ($editTask['priority'] ?? 'medium') === $priority ? 'selected' : ''; ?>><?php echo ucfirst($priority); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach (['pending','in_progress','completed','cancelled'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo ($editTask['status'] ?? 'pending') === $status ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                    <input type="datetime-local" name="due_date" value="<?php echo $editTask['due_date'] ? date('Y-m-d\TH:i', strtotime($editTask['due_date'])) : ''; ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Related To</label>
                    <select name="related_to" id="related_to" onchange="updateRelatedOptions()" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <option value="">-- None --</option>
                        <option value="contact" <?php echo ($editTask['related_to'] ?? '') === 'contact' ? 'selected' : ''; ?>>Contact</option>
                        <option value="lead" <?php echo ($editTask['related_to'] ?? '') === 'lead' ? 'selected' : ''; ?>>Lead</option>
                        <option value="deal" <?php echo ($editTask['related_to'] ?? '') === 'deal' ? 'selected' : ''; ?>>Deal</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Related Record</label>
                    <select name="related_id" id="related_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <option value="">-- Select --</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                    <select name="project_id" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <option value="">-- None --</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo (($editTask['project_id'] ?? $_GET['project_id'] ?? '') == $project['id']) ? 'selected' : ''; ?>><?php echo sanitize($project['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                    <select name="assigned_to" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($editTask['assigned_to'] ?? $userId) == $u['id'] ? 'selected' : ''; ?>><?php echo sanitize($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"><?php echo sanitize($editTask['description'] ?? ''); ?></textarea>
                </div>

                <div class="md:col-span-2 flex items-center gap-3">
                    <button type="submit" class="btn-primary px-6 py-2.5 text-white rounded-lg font-medium"><?php echo $action === 'edit' ? 'Update Task' : 'Create Task'; ?></button>
                    <a href="tasks.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">Cancel</a>
                </div>
            </form>
        </div>

        <script>
        const relatedData = {
            contact: <?php echo json_encode(array_map(fn($c) => ['id' => $c['id'], 'label' => $c['first_name'] . ' ' . $c['last_name']], $contacts)); ?>,
            lead: <?php echo json_encode(array_map(fn($l) => ['id' => $l['id'], 'label' => $l['title']], $leads)); ?>,
            deal: <?php echo json_encode(array_map(fn($d) => ['id' => $d['id'], 'label' => $d['title']], $deals)); ?>
        };
        const currentRelatedId = <?php echo $editTask['related_id'] ?? 'null'; ?>;

        function updateRelatedOptions() {
            const type = document.getElementById('related_to').value;
            const select = document.getElementById('related_id');
            select.innerHTML = '<option value="">-- Select --</option>';
            if (type && relatedData[type]) {
                relatedData[type].forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.label;
                    if (currentRelatedId && currentRelatedId == item.id) option.selected = true;
                    select.appendChild(option);
                });
            }
        }
        updateRelatedOptions();
        </script>

        <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-slide-up">
            <div class="p-4 border-b border-gray-100">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" data-search=".task-row" placeholder="Search tasks..." class="pl-10 pr-4 py-2 w-full sm:w-64 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Related</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Assigned</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">No tasks found. <a href="tasks.php?action=add" class="text-primary-600 hover:underline">Create your first task</a>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $index => $task): ?>
                            <tr class="task-row searchable-row hover:bg-gray-50 transition-colors table-row-animate <?php echo $task['status'] === 'completed' ? 'opacity-60' : ''; ?>" style="animation-delay: <?php echo $index * 50; ?>ms">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-secondary-900 <?php echo $task['status'] === 'completed' ? 'line-through' : ''; ?>"><?php echo sanitize($task['title']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $task['status'] === 'completed' ? 'Completed ' . formatDate($task['completed_at']) : ucwords(str_replace('_', ' ', $task['status'])); ?></p>
                                </td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full bg-gray-100 text-gray-700"><i class="fas fa-<?php echo $task['type'] === 'call' ? 'phone' : ($task['type'] === 'email' ? 'envelope' : ($task['type'] === 'meeting' ? 'calendar' : 'check')); ?> mr-1"></i><?php echo ucwords(str_replace('_', ' ', $task['type'])); ?></span></td>
                                <td class="px-6 py-4"><span class="px-2.5 py-1 text-xs rounded-full <?php echo statusColor($task['priority']); ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo $task['due_date'] ? formatDate($task['due_date'], 'M d, Y H:i') : '-'; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo $task['related_to'] ? ucfirst($task['related_to']) . ($task['first_name'] ? ': ' . sanitize($task['first_name'] . ' ' . $task['last_name']) : '') : '-'; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo $task['project_title'] ? '<a href="projects.php?action=view&id=' . $task['project_id'] . '" class="text-primary-600 hover:underline">' . sanitize($task['project_title']) . '</a>' : '-'; ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo sanitize($task['assigned_name'] ?? 'Unassigned'); ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($task['status'] !== 'completed'): ?>
                                        <a href="tasks.php?complete=<?php echo $task['id']; ?>" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" data-confirm="Mark as completed?"><i class="fas fa-check"></i></a>
                                        <?php endif; ?>
                                        <a href="tasks.php?action=edit&id=<?php echo $task['id']; ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="fas fa-edit"></i></a>
                                        <a href="tasks.php?delete=<?php echo $task['id']; ?>" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" data-confirm="Are you sure you want to delete this task?"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

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
