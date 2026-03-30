<?php
// api/student_actions.php
require_once '../includes/auth.php';
requireRole('admin');
require_once '../includes/db.php';

// Helper for Profile Picture
function uploadProfilePicture($file, $oldFile = null) {
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $newName = uniqid('stu_') . '.' . $ext;
            $destination = '../uploads/profiles/' . $newName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                if ($oldFile && file_exists('../uploads/profiles/' . $oldFile)) {
                    unlink('../uploads/profiles/' . $oldFile);
                }
                return $newName;
            }
        }
    }
    return $oldFile;
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

// Validate CSRF token (If you add an AJAX token check)
if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: ../master-student.php?status=error&msg=Invalid CSRF Token");
    exit();
}

$action = $_POST['action'] ?? '';

// === CREATE Student ===
if ($action === 'create') {
    $studentCode = trim($_POST['student_code']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $prefix = trim($_POST['prefix']);
    $class_id = trim($_POST['class_id']);
    $dob = trim($_POST['dob'] ?? null);
    
    // Process profile picture
    $profilePic = uploadProfilePicture($_FILES['profile_picture'] ?? null);
    
    try {
        $pdo->beginTransaction();
        
        $rawPassword = $_POST['password'];
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])[a-zA-Z0-9]+$/', $rawPassword)) {
            header("Location: ../master-student.php?status=err_password");
            exit();
        }
        $password = password_hash($rawPassword, PASSWORD_BCRYPT);
        
        // 1. Insert into Users
        $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role, prefix, first_name, last_name) VALUES (?, ?, 'student', ?, ?, ?)");
        $stmtUser->execute([$studentCode, $password, $prefix, $first_name, $last_name]);
        $userId = $pdo->lastInsertId();
        
        // 2. Insert into Students
        $stmtStudent = $pdo->prepare("INSERT INTO students (student_code, user_id, class_id, dob, profile_picture) VALUES (?, ?, ?, ?, ?)");
        $stmtStudent->execute([$studentCode, $userId, $class_id, $dob, $profilePic]);
        
        $pdo->commit();
        header("Location: ../master-student.php?status=success_add");
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            header("Location: ../master-student.php?status=err_duplicate");
        } else {
            header("Location: ../master-student.php?status=err_db");
        }
    }
    exit();
}

// === UPDATE Student ===
if ($action === 'update') {
    $student_id = $_POST['student_id'];
    $user_id = $_POST['user_id'];
    
    $prefix = trim($_POST['prefix']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $class_id = trim($_POST['class_id']);
    $dob = trim($_POST['dob'] ?? null);

    try {
        $pdo->beginTransaction();

        // Handle password update if provided
        if (!empty($_POST['password'])) {
            $rawPassword = $_POST['password'];
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])[a-zA-Z0-9]+$/', $rawPassword)) {
                header("Location: ../master-student.php?status=err_password");
                exit();
            }
            $password = password_hash($rawPassword, PASSWORD_BCRYPT);
            $stmtPwd = $pdo->prepare("UPDATE users SET password = ?, prefix = ?, first_name = ?, last_name = ? WHERE id = ?");
            $stmtPwd->execute([$password, $prefix, $first_name, $last_name, $user_id]);
        } else {
            $stmtUser = $pdo->prepare("UPDATE users SET prefix = ?, first_name = ?, last_name = ? WHERE id = ?");
            $stmtUser->execute([$prefix, $first_name, $last_name, $user_id]);
        }

        // Handle profile picture
        $stmtGetPic = $pdo->prepare("SELECT profile_picture FROM students WHERE id = ?");
        $stmtGetPic->execute([$student_id]);
        $oldPic = $stmtGetPic->fetchColumn();

        $removePic = $_POST['remove_profile_picture'] ?? '0';
        if ($removePic == '1') {
            if ($oldPic && file_exists('../uploads/profiles/' . $oldPic)) {
                unlink('../uploads/profiles/' . $oldPic);
            }
            $profilePic = null;
        } else {
            $profilePic = uploadProfilePicture($_FILES['profile_picture'] ?? null, $oldPic);
        }

        // Update Student Details
        $stmtStudent = $pdo->prepare("UPDATE students SET class_id = ?, dob = ?, profile_picture = ? WHERE id = ?");
        $stmtStudent->execute([$class_id, $dob, $profilePic, $student_id]);

        $pdo->commit();
        header("Location: ../master-student.php?status=success_edit");
    } catch(PDOException $e) {
        $pdo->rollBack();
        header("Location: ../master-student.php?status=err_db");
    }
    exit();
}

// === DELETE Student ===
if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, profile_picture FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        
        if($student) {
             // Let's get the profile picture first to unlink it
             $oldPic = $student['profile_picture'];

             $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
             $stmtDel->execute([$student['user_id']]);

             if ($oldPic && file_exists('../uploads/profiles/' . $oldPic)) {
                 unlink('../uploads/profiles/' . $oldPic);
             }

             header("Location: ../master-student.php?status=success_delete");
        } else {
             header("Location: ../master-student.php?status=err_db");
        }
    } catch (PDOException $e) {
        header("Location: ../master-student.php?status=err_delete_fk");
    }
    exit();
}
?>
