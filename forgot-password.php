<?php
session_start();
require_once 'includes/db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "เกิดข้อผิดพลาดด้านความปลอดภัย กรุณาลองใหม่";
    } else {
        $username = trim($_POST['username']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);

        if (empty($username) || empty($first_name) || empty($last_name)) {
            $error = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
        } else {
            // Check if user exists with matching details
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND first_name = ? AND last_name = ? LIMIT 1");
            $stmt->execute([$username, $first_name, $last_name]);
            $userData = $stmt->fetch();

            if ($userData) {
                // Reset password to "Password123"
                $newPassword = password_hash('Password123', PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newPassword, $userData['id']]);
                
                $success = "สำเร็จ! ระบบได้รีเซ็ตรหัสผ่านของคุณเป็น <b>Password123</b> แล้ว";
            } else {
                $error = "ข้อมูลไม่ถูกต้อง (ไม่พบชื่อผู้ใช้งานนี้ หรือชื่อ-นามสกุลไม่ตรงกับฐานข้อมูล)";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - โรงเรียนสาธิตวิทยา</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts (Prompt & Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --bg-color: #f4f6f9;
        }
        body {
            font-family: 'Prompt', 'Inter', sans-serif;
            background-color: var(--bg-color);
            background-image: linear-gradient(135deg, rgba(52, 152, 219, 0.05) 0%, rgba(44, 62, 80, 0.05) 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            background: rgba(44, 62, 80, 0.03);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control {
            background: var(--bg-color);
            border: 1px solid rgba(0,0,0,0.05);
            padding: 12px 15px;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: var(--accent-color);
            background: #fff;
        }
        .btn-login {
            background-color: var(--accent-color);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-login:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="login-card">
        <div class="login-header">
            <h4 class="fw-bold text-dark mb-1" style="letter-spacing: 1px;">สาธิตวิทยา</h4>
            <p class="text-muted small mb-0">ระบบบริหารจัดการสถานศึกษา (Elite Academy)</p>
        </div>
        <div class="login-body">
            <div class="text-center mb-4">
                <i class="fas fa-unlock-alt text-primary mb-3" style="font-size: 2.5rem; opacity: 0.8;"></i>
                <h5 class="fw-semibold">กู้คืนรหัสผ่าน</h5>
                <p class="text-muted small">กรอกข้อมูลยืนยันตัวตนเพื่อรีเซ็ตรหัสผ่านกลับเป็นค่าเริ่มต้น</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small fw-medium" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success py-3 small fw-medium text-center" role="alert">
                    <i class="fas fa-check-circle me-2" style="font-size: 1.5rem;"></i><br>
                    <?php echo $success; ?>
                </div>
                <div class="d-grid mt-4">
                    <a href="login.php" class="btn btn-primary w-100 shadow-sm text-white py-2 fw-medium" style="border-radius: 8px;">
                        กลับไปหน้าเข้าสู่ระบบ
                    </a>
                </div>
            <?php else: ?>
                <form action="forgot-password.php" method="POST">
                    <!-- CSRF Token Hidden Input -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">ชื่อผู้ใช้งาน (Username) *</label>
                        <input type="text" name="username" class="form-control" placeholder="เช่น S6701001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium">ชื่อจริง (ไม่ต้องระบุคำนำหน้า) *</label>
                        <input type="text" name="first_name" class="form-control" placeholder="เช่น สมภพ" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted small fw-medium">นามสกุล *</label>
                        <input type="text" name="last_name" class="form-control" placeholder="เช่น เรียนดี" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-login shadow-sm text-white mb-3">
                        ยืนยันการตั้งรหัสผ่านใหม่ <i class="fas fa-redo ms-1"></i>
                    </button>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none small text-muted hover-primary">
                            <i class="fas fa-arrow-left me-1"></i> กลับไปหน้าเข้าสู่ระบบ
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
