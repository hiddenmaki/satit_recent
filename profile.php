<?php
// profile.php - จัดการข้อมูลโปรไฟล์ส่วนตัวของผู้ใช้งาน
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// --- ส่วนปรับปรุงฐานข้อมูลอัตโนมัติ (รองรับรูปโปรไฟล์สำหรับทุกบทบาท) ---
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER status");
} catch (PDOException $e) {
    // ถ้ามีคอลัมน์อยู่แล้วให้ข้ามไป (Safe ignore)
}

// --- ส่วนดึงข้อมูลผู้ใช้งานตามบทบาท (Role-based Data Fetching) ---
$userData = [];
if ($role === 'admin') {
    // ถ้าเป็น Admin: ดึงข้อมูลจากตาราง users ตรงๆ
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch();
} else if ($role === 'teacher') {
    // ถ้าเป็นครู: Join ตาราง users กับ teachers เพื่อเอาข้อมูลเบอร์โทรและรหัสครู
    $stmt = $pdo->prepare("SELECT u.*, t.phone, t.line_id, t.profile_picture as t_pic, t.teacher_code FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch();
} else if ($role === 'student') {
    // ถ้าเป็นนักเรียน: Join ตาราง users, students และ classes เพื่อเอาข้อมูลห้องเรียนและรหัสนักเรียน
    $stmt = $pdo->prepare("SELECT u.*, s.student_code, s.dob, s.address, s.profile_picture as s_pic, c.level_name 
                           FROM users u 
                           JOIN students s ON u.id = s.user_id 
                           LEFT JOIN classes c ON s.class_id = c.id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch();
}

// --- ส่วนจัดการรูปโปรไฟล์ (Profile Picture Logic) ---
$fullName = trim(($userData['prefix'] ?? '') . ' ' . $userData['first_name'] . ' ' . $userData['last_name']);
$currentPic = 'https://ui-avatars.com/api/?name=' . urlencode($userData['first_name']) . '&background=random&size=150'; // ค่าเริ่มต้นถ้าไม่มีรูป
$hasCustomPic = false;

// ตรวจสอบลำดับการแสดงรูป (ลำดับความสำคัญ: ตาราง users > ตารางเฉพาะบทบาท)
if (!empty($userData['profile_picture'])) {
    $currentPic = 'uploads/profiles/' . $userData['profile_picture'];
    $hasCustomPic = true;
} elseif ($role === 'teacher' && !empty($userData['t_pic'])) {
    $currentPic = 'uploads/profiles/' . $userData['t_pic'];
    $hasCustomPic = true;
} elseif ($role === 'student' && !empty($userData['s_pic'])) {
    $currentPic = 'uploads/profiles/' . $userData['s_pic'];
    $hasCustomPic = true;
}

// เตรียมชื่อเต็มสำหรับแสดงผล
$fullName = trim(($userData['prefix'] ?? '') . ' ' . $userData['first_name'] . ' ' . $userData['last_name']);
?>

<!-- ส่วนแสดงผล UI (User Interface) -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">โปรไฟล์ส่วนตัว</h3>
... (เนื้อหา Breadcrumb) ...
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">โปรไฟล์ส่วนตัว</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0"><i class="fas fa-check-circle me-2"></i>อัปเดตข้อมูลไฟล์ส่วนตัวสำเร็จ<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($_GET['status'] === 'success_delete_pic'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0"><i class="fas fa-trash-alt me-2"></i>ลบรูปโปรไฟล์สำเร็จ<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($_GET['status'] === 'success_pwd'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0"><i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่านสำเร็จ<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($_GET['status'] === 'err_pwd_wrong'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i>รหัสผ่านเดิมไม่ถูกต้อง<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($_GET['status'] === 'err_pwd_match'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0"><i class="fas fa-exclamation-circle me-2"></i>รหัสผ่านใหม่และการยืนยันไม่ตรงกัน<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php elseif ($_GET['status'] === 'err_upload'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0"><i class="fas fa-times-circle me-2"></i>เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (รองรับเฉพาะ JPG, PNG, WEBP, GIF, JFIF ขนาดไม่เกิน 5MB)<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php else: ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0"><i class="fas fa-times-circle me-2"></i>เกิดข้อผิดพลาด กรุณาลองใหม่<button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Profile Card -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center py-5">
                <form action="api/profile_actions.php" method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="update_avatar">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= htmlspecialchars($currentPic) ?>" alt="Profile" class="rounded-circle img-thumbnail shadow-sm" style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--accent-color);" id="previewAvatar">
                        <label for="avatarInput" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow" style="cursor: pointer; transform: translate(10%, 10%);" title="เปลี่ยนรูปประจำตัว">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="avatarInput" name="profile_picture" class="d-none" accept=".jpg, .jpeg, .png, .webp, .jfif, .gif">
                    </div>
                </form>

                <?php if ($hasCustomPic): ?>
                <form action="api/profile_actions.php" method="POST" class="mb-3" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบรูปโปรไฟล์นี้?');">
                    <input type="hidden" name="action" value="delete_avatar">
                    <button type="submit" class="btn btn-sm btn-outline-danger px-3 rounded-pill"><i class="fas fa-trash-alt me-1"></i> ลบรูปโปรไฟล์</button>
                </form>
                <?php endif; ?>
                
                <h5 class="fw-bold text-dark mt-2 mb-1"><?= $fullName ?></h5>
                <p class="text-muted small mb-2">@<?= htmlspecialchars($userData['username']) ?></p>
                <div class="badge bg-primary bg-opacity-10 text-primary border border-primary px-3 py-1 mb-3 rounded-pill">
                    <?= strtoupper($role) ?>
                </div>

                <div class="mt-3 text-start bg-light p-3 rounded text-muted small">
                    <p class="mb-1"><i class="fas fa-calendar-alt me-2"></i> เข้าระบบล่าสุด: <?= $userData['last_login'] ? date('d/m/Y H:i', strtotime($userData['last_login'])) : '-' ?></p>
                    <?php if ($role === 'teacher'): ?>
                        <p class="mb-1"><i class="fas fa-id-badge me-2"></i> รหัสประจำตัว: <?= htmlspecialchars($userData['teacher_code']) ?></p>
                    <?php elseif ($role === 'student'): ?>
                        <p class="mb-1"><i class="fas fa-id-badge me-2"></i> รหัสประจำตัว: <?= htmlspecialchars($userData['student_code']) ?></p>
                        <p class="mb-0"><i class="fas fa-school me-2"></i> ระดับชั้น: <?= htmlspecialchars($userData['level_name'] ?? 'ยังไม่มีห้อง') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Edit Forms -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <ul class="nav nav-pills card-header-pills" id="profileTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="info-tab" data-bs-toggle="tab" href="#info" role="tab"><i class="fas fa-user-edit me-1"></i> ข้อมูลส่วนตัว</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" id="password-tab" data-bs-toggle="tab" href="#password" role="tab"><i class="fas fa-key me-1"></i> เปลี่ยนรหัสผ่าน</a>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-4">
                <div class="tab-content" id="profileTabsContent">
                    
                    <!-- INFO TAB -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <form action="api/profile_actions.php" method="POST">
                            <input type="hidden" name="action" value="update_info">
                            
                            <h6 class="fw-bold text-dark mb-3">ข้อมูลพื้นฐาน</h6>
                            <div class="row g-3">
                                <?php if ($role === 'admin'): ?>
                                    <!-- Admin can edit names -->
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted">คำนำหน้า</label>
                                        <input type="text" name="prefix" class="form-control" value="<?= htmlspecialchars($userData['prefix']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">ชื่อจริง</label>
                                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($userData['first_name']) ?>" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small text-muted">นามสกุล</label>
                                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($userData['last_name']) ?>" required>
                                    </div>
                                <?php else: ?>
                                    <!-- Teacher/Student names are read-only to prevent self-renaming without admin approval -->
                                    <div class="col-md-12 mb-2">
                                        <label class="form-label small text-muted">ชื่อ - นามสกุล (ติดต่อ Admin หากต้องการแก้ไข)</label>
                                        <input type="text" class="form-control bg-light" value="<?= $fullName ?>" readonly>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($role === 'teacher'): ?>
                                <hr class="my-4 text-muted">
                                <h6 class="fw-bold text-dark mb-3">ข้อมูลติดต่อ (เฉพาะครู)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">เบอร์โทรศัพท์</label>
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Line ID</label>
                                        <input type="text" name="line_id" class="form-control" value="<?= htmlspecialchars($userData['line_id'] ?? '') ?>">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($role === 'student'): ?>
                                <hr class="my-4 text-muted">
                                <h6 class="fw-bold text-dark mb-3">ข้อมูลติดต่อ (เฉพาะนักเรียน)</h6>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label small text-muted">ที่อยู่ปัจจุบัน</label>
                                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($userData['address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                            </div>
                        </form>
                    </div>

                    <!-- PASSWORD TAB -->
                    <div class="tab-pane fade" id="password" role="tabpanel">
                        <form action="api/profile_actions.php" method="POST">
                            <input type="hidden" name="action" value="update_password">
                            
                            <h6 class="fw-bold text-danger mb-3">เปลี่ยนรหัสผ่านใหม่</h6>
                            <p class="small text-muted mb-4">เพื่อความปลอดภัย รหัสผ่านใหม่ควรประกอบด้วยตัวอักษรพิมพ์ใหญ่ พิมพ์เล็ก และตัวเลข</p>

                            <div class="mb-3">
                                <label class="form-label small text-muted">รหัสผ่านเดิม *</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">รหัสผ่านใหม่ *</label>
                                    <input type="password" name="new_password" class="form-control" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="ต้องมีตัวพิมพ์เล็ก พิมพ์ใหญ่ และตัวเลขอย่างน้อย 6 ตัวอักษร">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">ยืนยันรหัสผ่านใหม่ *</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-danger px-4"><i class="fas fa-key me-1"></i> เปลี่ยนรหัสผ่าน</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto submit form when avatar is selected
document.getElementById('avatarInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        // Simple preview
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewAvatar').src = e.target.result;
        }
        reader.readAsDataURL(this.files[0]);
        
        // Submit form
        document.getElementById('avatarForm').submit();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
