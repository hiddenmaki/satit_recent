<?php
// master-teacher.php - Teacher Management
require_once 'includes/auth.php';
requireRole('admin');
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">ข้อมูลบุคลากรครู</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item text-muted">ข้อมูลพื้นฐาน</li>
                <li class="breadcrumb-item active" aria-current="page">ข้อมูลครู</li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="fas fa-plus me-2"></i>เพิ่มข้อมูลครู
        </button>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success_add'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> เพิ่มข้อมูลบุคลากรครูสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_delete'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-trash-alt me-2"></i> ลบข้อมูลบุคลากรครูสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_edit'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-edit me-2"></i> บันทึกการแก้ไขข้อมูลบุคลากรครูสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_duplicate'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> ไม่สามารถเพิ่มได้: รหัสประจำตัวครูนี้มีในระบบแล้ว
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_delete_fk'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> ไม่สามารถลบได้: ข้อมูลบุคลากรนี้ถูกผูกเข้ากับข้อมููลอื่นในระบบ (เช่น ตารางสอน)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php else: ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-times-circle me-2"></i> เกิดข้อผิดพลาดในระบบฐานข้อมูล
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card shadow-sm-light">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list me-2"></i>รายชื่อครูทั้งหมด</h6>
        <form method="GET" action="master-teacher.php" class="input-group" style="width: 300px;">
            <input type="text" name="search" class="form-control form-control-sm bg-light border-0" placeholder="ค้นหาชื่อ, รหัส..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button class="btn btn-sm btn-light border-0" type="submit"><i class="fas fa-search text-muted"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="10%">รหัส</th>
                        <th width="25%">ชื่อ - นามสกุล</th>
                        <th width="20%">หมวดวิชา</th>
                        <th width="20%">เบอร์โทรศัพท์</th>
                        <th width="15%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
<?php
                    require_once 'includes/db.php';
                    // Pull data safely using join with users table
                    $search = $_GET['search'] ?? '';
                    
                    $sql = "SELECT t.*, d.name AS dept_name, u.first_name, u.last_name, u.prefix, u.username, u.status 
                            FROM teachers t 
                            LEFT JOIN departments d ON t.department_id = d.id
                            INNER JOIN users u ON t.user_id = u.id";
                    
                    if (!empty($search)) {
                         $sql .= " WHERE t.teacher_code LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR t.line_id LIKE :search";
                    }
                    
                    $sql .= " ORDER BY t.teacher_code ASC";

                    $stmt = $pdo->prepare($sql);
                    if (!empty($search)) {
                         $stmt->bindValue(':search', '%' . $search . '%');
                    }
                    $stmt->execute();
                    $teachers = $stmt->fetchAll();

                    if (count($teachers) === 0) {
                        echo "<tr><td colspan='6' class='text-center py-4 text-muted'>ไม่พบข้อมูลบุคลากร</td></tr>";
                    } else {
                        foreach ($teachers as $t): 
                            $fullName = htmlspecialchars($t['prefix'] . $t['first_name'] . ' ' . $t['last_name']);
                            $avatarInitial = mb_substr($t['first_name'], 0, 1, 'UTF-8');
                            
                            $profilePic = $t['profile_picture'] ? 'uploads/profiles/' . htmlspecialchars($t['profile_picture']) : null;
                    ?>
                    <tr>
                         <td><?= htmlspecialchars($t['teacher_code']) ?></td>
                         <td>
                             <div class="d-flex align-items-center">
                                 <?php if($profilePic): ?>
                                     <img src="<?= $profilePic ?>" alt="Profile" class="rounded-circle me-3" style="width:30px;height:30px;object-fit:cover;">
                                 <?php else: ?>
                                     <?php 
                                     $colors = ['bg-primary-light', 'bg-info-light', 'bg-success-light', 'bg-warning-light'];
                                     $randomColor = $colors[array_rand($colors)]; 
                                     ?>
                                     <div class="avatar-circle <?= $randomColor ?> me-3" style="width:30px;height:30px;font-size:0.8rem;"><?= htmlspecialchars($avatarInitial) ?></div>
                                 <?php endif; ?>
                                 <div>
                                     <span class="fw-medium d-block"><?= $fullName ?></span>
                                     <?php if(!empty($t['line_id'])): ?>
                                         <small class="text-success"><i class="fab fa-line"></i> <?= htmlspecialchars($t['line_id']) ?></small>
                                     <?php endif; ?>
                                 </div>
                             </div>
                         </td>
                         <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['dept_name']) ?></span></td>
                         <td><?= htmlspecialchars($t['phone'] ?: '-') ?></td>
                         <td class="text-center">
                             <button class="btn btn-sm btn-light text-primary me-1" title="แก้ไข" data-bs-toggle="modal" data-bs-target="#editTeacherModal" onclick='openEditModal(<?= json_encode($t) ?>)'><i class="fas fa-edit"></i></button>
                             <form action="api/teacher_actions.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบข้อมูลบุคลากรนี้ ข้อมูลผู้ใช้จะถูกลบไปด้วย?');">
                                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="user_id" value="<?= $t['user_id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-light text-danger" title="ลบ"><i class="fas fa-trash"></i></button>
                             </form>
                         </td>
                    </tr>
                    <?php 
                        endforeach; 
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
        <small class="text-muted">แสดง 1 ถึง 3 จาก 124 รายการ</small>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item disabled"><a class="page-link" href="#">ก่อนหน้า</a></li>
                <li class="page-item active" style="--bs-pagination-active-bg: var(--accent-color); --bs-pagination-active-border-color: var(--accent-color);"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">ถัดไป</a></li>
            </ul>
        </nav>
    </div>
</div>

<!-- Modal For Adding Teacher -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title fw-bold" id="addTeacherModalLabel">เพิ่มข้อมูลบุคลากรครู</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form action="api/teacher_actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="create">

            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">รูปโปรไฟล์ (ไม่บังคับ)</label>
                <input type="file" name="profile_picture" class="form-control bg-light" accept="image/*">
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">รหัสประจำตัวครู (Username) *</label>
                <input type="text" name="teacher_code" class="form-control bg-light" placeholder="เช่น T1004" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">รหัสผ่านเริ่มต้น *</label>
                <input type="password" name="password" class="form-control bg-light" placeholder="รหัสผ่านสำหรับเข้าสู่ระบบ" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">คำนำหน้า *</label>
                <select name="prefix" class="form-select bg-light" required>
                    <option value="นาย">นาย</option>
                    <option value="นาง">นาง</option>
                    <option value="นางสาว">นางสาว</option>
                    <option value="ดร.">ดร.</option>
                    <option value="ผศ.">ผศ.</option>
                    <option value="รศ.">รศ.</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">ชื่อ *</label>
                    <input type="text" name="first_name" class="form-control bg-light" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">นามสกุล *</label>
                    <input type="text" name="last_name" class="form-control bg-light" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">หมวดวิชาที่สังกัด *</label>
                <select name="department_id" class="form-select bg-light" required>
                    <option selected disabled value="">-- เลือกหมวดวิชา --</option>
                    <?php
                        $stmtDept = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                        $allDepts = $stmtDept->fetchAll();
                        foreach($allDepts as $dept) {
                            echo '<option value="'.$dept['id'].'">'.htmlspecialchars($dept['name']).'</option>';
                        }
                    ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">เบอร์โทรศัพท์ (ไม่บังคับ)</label>
                    <input type="tel" name="phone" class="form-control bg-light" placeholder="08x-xxx-xxxx">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">LINE ID (ไม่บังคับ)</label>
                    <input type="text" name="line_id" class="form-control bg-light" placeholder="ไอดีไลน์">
                </div>
            </div>
      </div>
      <div class="modal-footer border-top-0 bg-light">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border: none;">บันทึกข้อมูล</button>
      </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal For Editing Teacher -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-bottom-0">
        <h5 class="modal-title fw-bold" id="editTeacherModalLabel">แก้ไขข้อมูลบุคลากรครู</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form action="api/teacher_actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="teacher_id" id="edit_teacher_id">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="mb-3 text-center">
                <img id="edit_preview_img" src="" alt="Profile Preview" class="rounded-circle mb-2 d-none" style="width:80px;height:80px;object-fit:cover;border:2px solid var(--accent-color);">
                <div>
                    <label class="form-label fw-medium text-muted small">อัปเดตรูปโปรไฟล์</label>
                    <input type="file" name="profile_picture" class="form-control form-control-sm bg-light" accept="image/*">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">รหัสประจำตัวครู (Username)</label>
                <input type="text" name="teacher_code" id="edit_teacher_code" class="form-control bg-light" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">รหัสผ่านใหม่ (ปล่อยว่างถ้าไม่เปลี่ยน)</label>
                <input type="password" name="password" class="form-control bg-light" placeholder="••••••••">
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">คำนำหน้า *</label>
                <select name="prefix" id="edit_prefix" class="form-select bg-light" required>
                    <option value="นาย">นาย</option>
                    <option value="นาง">นาง</option>
                    <option value="นางสาว">นางสาว</option>
                    <option value="ดร.">ดร.</option>
                    <option value="ผศ.">ผศ.</option>
                    <option value="รศ.">รศ.</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">ชื่อ *</label>
                    <input type="text" name="first_name" id="edit_first_name" class="form-control bg-light" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">นามสกุล *</label>
                    <input type="text" name="last_name" id="edit_last_name" class="form-control bg-light" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium text-muted small">หมวดวิชาที่สังกัด *</label>
                <select name="department_id" id="edit_department_id" class="form-select bg-light" required>
                    <option disabled value="">-- เลือกหมวดวิชา --</option>
                    <?php
                        foreach($allDepts as $dept) {
                            echo '<option value="'.$dept['id'].'">'.htmlspecialchars($dept['name']).'</option>';
                        }
                    ?>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">เบอร์โทรศัพท์</label>
                    <input type="tel" name="phone" id="edit_phone" class="form-control bg-light">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-muted small">LINE ID</label>
                    <input type="text" name="line_id" id="edit_line_id" class="form-control bg-light">
                </div>
            </div>
      </div>
      <div class="modal-footer border-top-0 bg-light">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border: none;">อัปเดตข้อมูล</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(teacher) {
    document.getElementById('edit_teacher_id').value = teacher.id;
    document.getElementById('edit_user_id').value = teacher.user_id;
    document.getElementById('edit_teacher_code').value = teacher.teacher_code;
    document.getElementById('edit_prefix').value = teacher.prefix;
    document.getElementById('edit_first_name').value = teacher.first_name;
    document.getElementById('edit_last_name').value = teacher.last_name;
    document.getElementById('edit_department_id').value = teacher.department_id;
    document.getElementById('edit_phone').value = teacher.phone || '';
    document.getElementById('edit_line_id').value = teacher.line_id || '';
    
    let previewImg = document.getElementById('edit_preview_img');
    if (teacher.profile_picture) {
        previewImg.src = 'uploads/profiles/' + teacher.profile_picture;
        previewImg.classList.remove('d-none');
    } else {
        previewImg.src = '';
        previewImg.classList.add('d-none');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
