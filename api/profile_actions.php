<?php
// api/profile_actions.php - Handle profile updates
session_start();
require_once '../includes/db.php';

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access Denied');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_POST['action'] ?? '';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../profile.php");
    exit();
}

try {
    // ==========================================
    // ACTION: UPDATE AVATAR (PROFILE PICTURE)
    // ==========================================
    if ($action === 'update_avatar') {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            header("Location: ../profile.php?status=err_upload");
            exit();
        }

        $file = $_FILES['profile_picture'];

        // 1. Validate Extension
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'jfif', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            header("Location: ../profile.php?status=err_upload");
            exit();
        }

        // 2. Validate Size (Max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            header("Location: ../profile.php?status=err_upload");
            exit();
        }

        // 3. Prevent XSS payload via MIME type or dummy extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/pipeg'])) {
            header("Location: ../profile.php?status=err_upload");
            exit();
        }
        finfo_close($finfo);

        // Upload Process
        $uploadDir = '../uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = $role . '_' . $user_id . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Enforce unified storage in users table regardless of role
            $s = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $s->execute([$user_id]);
            $oldPic = $s->fetchColumn();
            
            $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?")->execute([$newFileName, $user_id]);
            // Clear legacy shadow tables to force unified loading
            if ($role === 'teacher') {
                $pdo->prepare("UPDATE teachers SET profile_picture = NULL WHERE user_id = ?")->execute([$user_id]);
            } else if ($role === 'student') {
                $pdo->prepare("UPDATE students SET profile_picture = NULL WHERE user_id = ?")->execute([$user_id]);
            }
            $_SESSION['profile_picture'] = $newFileName;

            if ($oldPic && file_exists($uploadDir . $oldPic)) {
                @unlink($uploadDir . $oldPic);
            }
            header("Location: ../profile.php?status=success");
        } else {
            header("Location: ../profile.php?status=err_upload");
        }
        exit();
    }

    // ==========================================
    // ACTION: DELETE AVATAR (PROFILE PICTURE)
    // ==========================================
    if ($action === 'delete_avatar') {
        // Unified delete mechanism
        $s = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $s->execute([$user_id]);
        $oldPic = $s->fetchColumn();
        
        $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?")->execute([$user_id]);
        if ($role === 'teacher') {
            $pdo->prepare("UPDATE teachers SET profile_picture = NULL WHERE user_id = ?")->execute([$user_id]);
        } else if ($role === 'student') {
            $pdo->prepare("UPDATE students SET profile_picture = NULL WHERE user_id = ?")->execute([$user_id]);
        }
        $_SESSION['profile_picture'] = null;

        $uploadDir = '../uploads/profiles/';
        if ($oldPic && file_exists($uploadDir . $oldPic)) {
            @unlink($uploadDir . $oldPic);
        }

        header("Location: ../profile.php?status=success_delete_pic");
        exit();
    }

    // ==========================================
    // ACTION: UPDATE INFO
    // ==========================================
    if ($action === 'update_info') {
        if ($role === 'admin') {
            $prefix = trim($_POST['prefix']);
            $fname = trim($_POST['first_name']);
            $lname = trim($_POST['last_name']);

            $stmt = $pdo->prepare("UPDATE users SET prefix = ?, first_name = ?, last_name = ? WHERE id = ?");
            $stmt->execute([$prefix, $fname, $lname, $user_id]);

            // Update session
            $_SESSION['full_name'] = $prefix . $fname . ' ' . $lname;

        } elseif ($role === 'teacher') {
            $phone = trim($_POST['phone'] ?? '');
            $line_id = trim($_POST['line_id'] ?? '');
            $stmt = $pdo->prepare("UPDATE teachers SET phone = ?, line_id = ? WHERE user_id = ?");
            $stmt->execute([$phone, $line_id, $user_id]);

        } elseif ($role === 'student') {
            $address = trim($_POST['address'] ?? '');
            $stmt = $pdo->prepare("UPDATE students SET address = ? WHERE user_id = ?");
            $stmt->execute([$address, $user_id]);
        }

        header("Location: ../profile.php?status=success");
        exit();
    }

    // ==========================================
    // ACTION: UPDATE PASSWORD
    // ==========================================
    if ($action === 'update_password') {
        $old_pwd = $_POST['old_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';

        if ($new_pwd !== $confirm_pwd) {
            header("Location: ../profile.php?status=err_pwd_match");
            exit();
        }

        // Verify Old Password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $currentHashed = $stmt->fetchColumn();

        if (!password_verify($old_pwd, $currentHashed)) {
            header("Location: ../profile.php?status=err_pwd_wrong");
            exit();
        }

        // Hash & Update New Password
        $newHash = password_hash($new_pwd, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$newHash, $user_id]);

        header("Location: ../profile.php?status=success_pwd");
        exit();
    }

} catch (PDOException $e) {
    header("Location: ../profile.php?status=err");
    exit();
}
?>