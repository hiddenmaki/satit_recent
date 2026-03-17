<?php
session_start();
// Generate CSRF Token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "เกิดข้อผิดพลาดด้านความปลอดภัย (CSRF Token Mismatch) กรุณาลองใหม่";
    } else {
        require_once 'includes/db.php';
        $user = trim($_POST['username']);
        $pass = $_POST['password'];

        if (empty($user) || empty($pass)) {
            $error = "กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน";
        } else {
            // 2. Simple Rate Limiting (Prevent Brute Force)
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 0;
            }
            if (!isset($_SESSION['last_attempt_time'])) {
                $_SESSION['last_attempt_time'] = time();
            }

            // Reset attempts after 5 minutes lockout
            if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt_time'] > 300)) {
                $_SESSION['login_attempts'] = 0;
            }

            if ($_SESSION['login_attempts'] >= 5) {
                $error = "คุณพยายามเข้าสู่ระบบผิดพลาดหลายครั้งเกินไป กรุณารอ 5 นาที";
            } else {
                // 3. Prevent SQL Injection using PDO Prepared Statements
                $stmt = $pdo->prepare("SELECT id, username, password, role, prefix, first_name, last_name, status FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$user]);
                $userData = $stmt->fetch();

                if ($userData) {
                    if ($userData['status'] === 'inactive') {
                        $error = "บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อ Admin";
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                    } 
                    // 4. Secure Password Verification (using BCRYPT)
                    else if (password_verify($pass, $userData['password'])) {
                        // 5. Prevent Session Fixation attacks
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $userData['id'];
                        $_SESSION['username'] = $userData['username'];
                        $_SESSION['role'] = $userData['role'];
                        $_SESSION['full_name'] = $userData['prefix'] . $userData['first_name'] . ' ' . $userData['last_name'];
                        $_SESSION['login_attempts'] = 0; // Reset
                        
                        // Update Last Login Time
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$userData['id']]);

                        // Role-Based Redirection
                        if ($userData['role'] === 'admin') {
                            header("Location: index.php");
                        } else if ($userData['role'] === 'teacher') {
                            header("Location: schedule.php");
                        } else {
                            // Student can view schedule for now
                            header("Location: schedule.php"); 
                        }
                        exit();
                    } else {
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                        $error = "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง (รหัสผ่านผิด)";
                    }
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    // Do not disclose if username exists or not for tighter security
                    $error = "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง";
                }
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
    <title>เข้าสู่ระบบ - โรงเรียนสาธิตวิทยา</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: var(--surface-color);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
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
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="assets/images/logo.png" alt="Satit School Logo" class="img-fluid rounded-circle shadow-sm mb-3" style="max-width: 90px; border: 3px solid #fff;">
        <h4 class="fw-bold text-dark mb-1" style="letter-spacing: 1px;">สาธิตวิทยา</h4>
        <p class="text-muted small mb-0">ระบบบริหารจัดการสถานศึกษา (Elite Academy)</p>
    </div>
    <div class="login-body">
        <h5 class="fw-semibold mb-4 text-center">ลงชื่อเข้าใช้งาน</h5>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small fw-medium" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <!-- CSRF Token Hidden Input -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="mb-3">
                <label class="form-label text-muted small fw-medium">ชื่อผู้ใช้งาน (รหัสประจำตัว)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="form-control border-start-0" placeholder="ระบุชื่อผู้ใช้งาน" required autocomplete="username">
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between">
                    <label class="form-label text-muted small fw-medium">รหัสผ่าน</label>
                    <a href="#" class="small text-decoration-none" style="color: var(--accent-color);">ลืมรหัสผ่าน?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" placeholder="ระบุรหัสผ่าน" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login shadow-sm text-white">เข้าสู่ระบบ</button>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted"><i class="fas fa-shield-alt me-1"></i> เชื่อมต่อผ่านระบบรักษาความปลอดภัย 256-bit</small>
        </div>
    </div>
</div>

</body>
</html>
