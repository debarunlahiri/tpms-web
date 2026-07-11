<?php
$pageTitle = 'My Profile';
require_once 'includes/header.php';
requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $db->prepare("SELECT id, name, email, phone, role, avatar, status, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch();
if (!$profile) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || !isValidEmail($email)) {
            $error = 'Enter a valid name and email address.';
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = 'That email address is already in use.';
            } else {
                $avatar = $profile['avatar'];
                if (!empty($_FILES['avatar']['name'])) {
                    $file = $_FILES['avatar'];
                    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
                        $error = 'The profile image must be smaller than 2 MB.';
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $file['tmp_name']);
                        finfo_close($finfo);
                        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (!isset($extensions[$mime])) {
                            $error = 'Upload a JPG, PNG, or WebP image.';
                        } else {
                            $uploadDir = __DIR__ . '/uploads/avatars';
                            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                            $filename = uuidV7() . '.' . $extensions[$mime];
                            if (!is_writable($uploadDir)) {
                                $error = 'The avatar upload directory is not writable by the web server.';
                            } elseif (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                                $error = 'The profile image could not be saved.';
                            } else {
                                if ($avatar && str_starts_with($avatar, 'uploads/avatars/')) {
                                    $oldFile = __DIR__ . '/' . $avatar;
                                    if (is_file($oldFile)) unlink($oldFile);
                                }
                                $avatar = 'uploads/avatars/' . $filename;
                            }
                        }
                    }
                }
                if (!$error) {
                    $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?")->execute([$name, $email, $phone ?: null, $avatar, $userId]);
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_avatar'] = $avatar;
                    logActivity('update', 'Updated personal profile', 'user', $userId);
                    $success = 'Your profile has been updated.';
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $passwordHash = $stmt->fetchColumn();
        if (!password_verify($currentPassword, $passwordHash)) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'The new password must contain at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'The new passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            if (function_exists('ensureRememberTokensTable')) {
                ensureRememberTokensTable($db);
                $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
            }
            logActivity('update', 'Changed account password', 'user', $userId);
            $success = 'Your password has been changed. Other remembered sessions were signed out.';
        }
    }
    $stmt = $db->prepare("SELECT id, name, email, phone, role, avatar, status, created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
}

include 'includes/sidebar.php';
?>
<div class="lg:ml-64 min-h-screen transition-all duration-300">
    <?php include 'includes/topbar.php'; ?>
    <main class="p-6 pt-20">
        <div class="mb-8 animate-fade-in"><h1 class="text-3xl font-bold text-secondary-900">My Profile</h1><p class="text-gray-500 mt-1">Manage your personal details and account security.</p></div>
        <?php if ($error): ?><div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700"><i class="fas fa-exclamation-circle mr-2"></i><?php echo sanitize($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700"><i class="fas fa-check-circle mr-2"></i><?php echo sanitize($success); ?></div><?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 xl:self-start animate-slide-up">
                <div class="text-center">
                    <div class="w-28 h-28 mx-auto rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-3xl font-bold overflow-hidden ring-4 ring-primary-50 shadow-lg">
                        <?php if ($profile['avatar']): ?><img src="<?php echo sanitize($profile['avatar']); ?>" class="w-full h-full object-cover" alt="Profile photo"><?php else: ?><?php echo getInitials($profile['name']); ?><?php endif; ?>
                    </div>
                    <h2 class="text-xl font-bold text-secondary-900 mt-4"><?php echo sanitize($profile['name']); ?></h2>
                    <p class="text-gray-500 capitalize"><?php echo sanitize(str_replace('_', ' ', $profile['role'])); ?></p>
                    <span class="inline-flex mt-3 px-3 py-1 rounded-full text-xs font-medium <?php echo $profile['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>"><?php echo ucfirst($profile['status']); ?></span>
                </div>
                <div class="border-t border-gray-100 mt-6 pt-5 space-y-3 text-sm"><p class="flex justify-between"><span class="text-gray-500">Member since</span><span class="font-medium text-secondary-900"><?php echo formatDate($profile['created_at'], 'M Y'); ?></span></p><p class="flex justify-between"><span class="text-gray-500">Account role</span><span class="font-medium text-secondary-900 capitalize"><?php echo sanitize(str_replace('_', ' ', $profile['role'])); ?></span></p></div>
            </div>

            <div class="xl:col-span-2 space-y-6">
                <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>"><input type="hidden" name="action" value="update_profile">
                    <h2 class="text-lg font-bold text-secondary-900 mb-6"><i class="fas fa-user-edit text-primary-600 mr-2"></i>Personal Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label><input name="name" required value="<?php echo sanitize($profile['name']); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label><input type="email" name="email" required value="<?php echo sanitize($profile['email']); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label><input name="phone" value="<?php echo sanitize($profile['phone']); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" placeholder="Optional"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-gray-500 file:mr-3 file:px-4 file:py-2.5 file:border-0 file:rounded-lg file:bg-primary-50 file:text-primary-700 file:font-medium hover:file:bg-primary-100"><p class="text-xs text-gray-400 mt-1">JPG, PNG, or WebP. Maximum 2 MB.</p></div>
                    </div>
                    <button type="submit" class="mt-6 px-5 py-2.5 bg-primary-600 text-white rounded-lg font-medium hover:bg-primary-700"><i class="fas fa-save mr-2"></i>Save Profile</button>
                </form>

                <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 animate-slide-up animate-delay-100">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>"><input type="hidden" name="action" value="change_password">
                    <h2 class="text-lg font-bold text-secondary-900 mb-6"><i class="fas fa-shield-alt text-primary-600 mr-2"></i>Change Password</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5"><div><label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label><input type="password" name="current_password" required autocomplete="current-password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500"></div><div><label class="block text-sm font-medium text-gray-700 mb-2">New Password</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500"></div><div><label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-primary-500"></div></div>
                    <button type="submit" class="mt-6 px-5 py-2.5 bg-secondary-900 text-white rounded-lg font-medium hover:bg-secondary-800"><i class="fas fa-key mr-2"></i>Update Password</button>
                </form>
            </div>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
