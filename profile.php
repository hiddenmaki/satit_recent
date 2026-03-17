<?php
// profile.php - User Profile & Settings
require_once 'includes/auth.php';
include 'includes/header.php';

// If form is submitted
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
          require_once 'includes/db.php';
          $newPass = $_POST['new_password'];
          $confirmPass = $_POST['confirm_password'];
          
          if (!empty($newPass)) {
               if ($newPass === $confirmPass) {
                    $hashedPass = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if($stmt->execute([$hashedPass, $_SESSION['user_id']])) {
                         $successMsg = "อัปเดตรหัสผ่านใหม่เรียบร้อยแล้ว";
                    } else {
                         $errorMsg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                    }
               } else {
                   $errorMsg = "รหัสผ่านใหม่ไม่ตรงกัน กรุณาลองอีกครั้ง";
               }
          }
     } else {
          $errorMsg = "ข้อมูลไม่ปลอดภัย (CSRF Token ไม่ถูกต้อง)";
     }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">โปรไฟล์ส่วนตัว</h3>
        <p class="text-muted mb-0">จัดการบัญชีผู้ใช้และตั้งค่าความปลอดภัย</p>
    </div>
</div>

<?php if($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm-light border-0 text-center py-4">
             <div class="card-body">
                  <div class="avatar-circle mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem; background: linear-gradient(135deg, var(--primary-color), var(--accent-color));">
                       <?= htmlspecialchars(mb_substr($_SESSION['full_name'], 0, 1, 'UTF-8')) ?>
                  </div>
                  <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($_SESSION['full_name']) ?></h5>
                  <p class="text-muted mb-3"><i class="fas fa-id-badge me-2"></i><?= htmlspecialchars($_SESSION['username']) ?></p>
                  
                  <?php 
                  $roleBadgeOpts = [
                      'admin' => ['bg-danger', 'ผู้ดูแลระบบ (Admin)'],
                      'teacher' => ['bg-info', 'บุคลากรครู (Teacher)'],
                      'student' => ['bg-success', 'นักเรียน (Student)']
                  ];
                  $roleKey = $_SESSION['role'];
                  $badgeCls = $roleBadgeOpts[$roleKey][0] ?? 'bg-secondary';
                  $badgeTxt = $roleBadgeOpts[$roleKey][1] ?? 'ผู้ใช้งานทั่วไป';
                  ?>
                  <span class="badge <?= $badgeCls ?> px-3 py-2 rounded-pill shadow-sm"><?= $badgeTxt ?></span>
             </div>
        </div>
    </div>
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm-light border-0">
             <div class="card-header bg-white py-3 border-bottom">
                  <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-lock me-2"></i>ตั้งค่าความปลอดภัย</h6>
             </div>
             <div class="card-body p-4">
                  <form action="profile.php" method="POST">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <div class="mb-3">
                          <label class="form-label small text-muted fw-medium">ชื่อผู้ใช้งาน (รหัสประจำตัว)</label>
                          <input type="text" class="form-control bg-light text-muted" value="<?= htmlspecialchars($_SESSION['username']) ?>" readonly disabled>
                          <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle me-1"></i>ไม่สามารถเปลี่ยนชื่อผู้ใช้งานได้</small>
                      </div>
                      
                      <hr class="my-4">
                      <h6 class="fw-bold mb-3">เปลี่ยนรหัสผ่าน</h6>
                      
                      <div class="row">
                          <div class="col-md-6 mb-3">
                              <label class="form-label small text-muted fw-medium">รหัสผ่านใหม่</label>
                              <input type="password" name="new_password" class="form-control bg-light" placeholder="กรอกรหัสผ่านใหม่" minlength="8">
                          </div>
                          <div class="col-md-6 mb-4">
                              <label class="form-label small text-muted fw-medium">ยืนยันรหัสผ่านใหม่</label>
                              <input type="password" name="confirm_password" class="form-control bg-light" placeholder="ยืนยันรหัสผ่านใหม่อีกครั้ง" minlength="8">
                          </div>
                      </div>
                      
                      <div class="text-end border-top pt-3">
                          <button type="submit" class="btn btn-primary px-4 shadow-sm" style="background-color: var(--accent-color); border:none;">
                              <i class="fas fa-save me-2"></i>บันทึกการเปลี่ยนแปลง
                          </button>
                      </div>
                  </form>
             </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
