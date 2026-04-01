<?php
// master-student.php - จัดการข้อมูลนักเรียน (สำหรับ Admin เท่านั้น)
require_once 'includes/auth.php';
// บังคับให้เฉพาะนักเรียนที่มีบทบาท 'admin' เข้าถึงหน้านี้ได้
requireRole('admin');
include 'includes/header.php';
require_once 'includes/db.php';
?>

<!-- ส่วนหัวของหน้า (Page Header) -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">ข้อมูลนักเรียน</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item text-muted">ข้อมูลพื้นฐาน</li>
                <li class="breadcrumb-item active" aria-current="page"><a href="master-student.php" class="text-dark text-decoration-none">ข้อมูลนักเรียน</a></li> 
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus me-2"></i>เพิ่มข้อมูลนักเรียน
        </button>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success_add'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> เพิ่มข้อมูลนักเรียนสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_delete'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-trash-alt me-2"></i> ลบข้อมูลนักเรียนสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_edit'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-edit me-2"></i> บันทึกการแก้ไขข้อมูลนักเรียนสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_duplicate'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> ไม่สามารถเพิ่มได้: รหัสนักเรียนนี้มีในระบบแล้ว
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_delete_fk'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> ไม่สามารถลบได้: ข้อมูลนักเรียนนี้ถูกผูกเข้ากับข้อมููลอื่นในระบบ (เช่นผลการเรียน)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_password'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> รหัสผ่านต้องผสมกันระหว่าง <b>ตัวพิมพ์ใหญ่</b>, <b>ตัวพิมพ์เล็ก</b> และ <b>ตัวเลข</b> เท่านั้น
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php else: ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-times-circle me-2"></i> เกิดข้อผิดพลาดในระบบฐานข้อมูล
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
// Pagination and Filter Logic
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$filter_class = $_GET['class_id'] ?? '';

$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $searchParam = $search . '%';
    $whereClause .= " AND (s.student_code LIKE :search1 OR u.first_name LIKE :search2 OR u.last_name LIKE :search3)";
    $params[':search1'] = $searchParam;
    $params[':search2'] = $searchParam;
    $params[':search3'] = $searchParam;
}

if (!empty($filter_class)) {
    $whereClause .= " AND s.class_id = :class_id";
    $params[':class_id'] = $filter_class;
}

// Count total for pagination
$countSql = "SELECT COUNT(*) as total FROM students s 
             JOIN users u ON s.user_id = u.id 
             LEFT JOIN classes cl ON s.class_id = cl.id" . $whereClause;
$stmtCount = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    if (in_array($key, [':class_id'])) {
        $stmtCount->bindValue($key, $val, PDO::PARAM_INT);
    } else {
        $stmtCount->bindValue($key, $val, PDO::PARAM_STR);
    }
}
$stmtCount->execute();
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch data
$sql = "SELECT s.*, u.prefix, u.first_name, u.last_name, u.username, cl.level_name, COALESCE(u.profile_picture, s.profile_picture) as profile_picture 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN classes cl ON s.class_id = cl.id
        " . $whereClause . "
        ORDER BY s.student_code ASC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    if (in_array($key, [':class_id'])) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();
?>

<div class="card shadow-sm-light">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom gap-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-graduate me-2"></i>รายชื่อนักเรียนทั้งหมด</h6>
        
        <form method="GET" action="master-student.php" class="d-flex align-items-center gap-2 flex-wrap">
            <select name="class_id" class="form-select form-select-sm bg-light border-0 w-auto" onchange="this.form.submit()">
                <option value="">ทุกชั้นเรียน</option>
                <?php
                $stmtCls = $pdo->query("SELECT id, level_name FROM classes ORDER BY level_name ASC");
                while($cls = $stmtCls->fetch()) {
                    $selected = ($filter_class == $cls['id']) ? 'selected' : '';
                    echo "<option value='{$cls['id']}' $selected>{$cls['level_name']}</option>";
                }
                ?>
            </select>
            
            <div class="input-group" style="width: 250px;">
                <input type="text" name="search" class="form-control form-control-sm bg-light border-0" placeholder="รหัสนักเรียน, ชื่อ..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button class="btn btn-sm btn-light border-0" type="submit"><i class="fas fa-search text-muted"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="15%">รหัสประจำตัว</th>
                        <th width="40%">ชื่อ - นามสกุล</th>
                        <th width="15%">วัน/เดือน/ปีเกิด</th>
                        <th width="15%">ชั้นเรียน</th>
                        <th width="15%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($students) > 0) {
                        foreach ($students as $stu) {
                            $fullName = $stu['prefix'] . $stu['first_name'] . ' ' . $stu['last_name'];
                            $avatarInitial = mb_substr($stu['first_name'], 0, 1, 'UTF-8');
                            $profilePic = !empty($stu['profile_picture']) ? 'uploads/profiles/' . htmlspecialchars($stu['profile_picture']) : null;
                            $dobText = !empty($stu['dob']) ? date('d/m/Y', strtotime($stu['dob'])) : '-';
                    ?>
                     <tr>
                         <td><span class="fw-medium text-dark"><?= htmlspecialchars($stu['student_code']) ?></span></td>
                         <td>
                             <div class="d-flex align-items-center">
                                 <?php if ($profilePic): ?>
                                     <img src="<?= $profilePic ?>" alt="Profile" class="rounded-circle me-3 border" style="width:35px;height:35px;object-fit:cover;">
                                 <?php else: ?>
                                     <?php
                                     $colors = ['bg-primary-light', 'bg-info-light', 'bg-success-light', 'bg-warning-light'];
                                     $randomColor = $colors[array_rand($colors)];
                                     ?>
                                     <div class="avatar-circle <?= $randomColor ?> me-3" style="width:35px;height:35px;font-size:0.9rem;">
                                         <?= htmlspecialchars($avatarInitial) ?>
                                     </div>
                                 <?php endif; ?>
                                 <div>
                                     <span class="fw-medium d-block text-dark"><?= htmlspecialchars($fullName) ?></span>
                                     <small class="text-muted">Username: <?= htmlspecialchars($stu['username']) ?></small>
                                 </div>
                             </div>
                         </td>
                         <td><?= $dobText ?></td>
                         <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($stu['level_name'] ?? 'ยังไม่ระบุ') ?></span></td>
                         <td class="text-center">
                             <button class="btn btn-sm btn-light text-primary me-1" title="แก้ไข" data-bs-toggle="modal" data-bs-target="#editStudentModal" onclick='openEditModal(<?= json_encode($stu) ?>)'><i class="fas fa-edit"></i></button>
                             <form action="api/student_actions.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบนักเรียนคนนี้ บัญชีผู้ใช้จะถูกลบไปด้วย?');">
                                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="id" value="<?= $stu['id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-light text-danger" title="ลบ"><i class="fas fa-trash"></i></button>
                             </form>
                         </td>
                     </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='fas fa-info-circle mb-2' style='font-size: 24px; opacity: 0.5;'></i><br>ไม่มีข้อมูลนักเรียนในระบบ</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
        <?php
        $startItem = ($totalRecords > 0) ? $offset + 1 : 0;
        $endItem = min($offset + $limit, $totalRecords);
        ?>
        <small class="text-muted">แสดง <?= $startItem ?> ถึง <?= $endItem ?> จาก <?= $totalRecords ?> รายการ</small>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <?php
                function buildParams($p) {
                    $q = $_GET;
                    $q['page'] = $p;
                    return '?' . http_build_query($q);
                }
                ?>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($page <= 1) ? '#' : buildParams($page - 1) ?>">ก่อนหน้า</a>
                </li>
                
                <?php 
                $maxPagesToShow = 5;
                $startPage = max(1, $page - floor($maxPagesToShow / 2));
                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                
                if ($endPage - $startPage + 1 < $maxPagesToShow) {
                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                }
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>" <?= ($page == $i) ? 'style="--bs-pagination-active-bg: var(--accent-color); --bs-pagination-active-border-color: var(--accent-color);"' : '' ?>>
                        <a class="page-link" href="<?= buildParams($i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($page >= $totalPages) ? '#' : buildParams($page + 1) ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="addStudentModalLabel">เพิ่มข้อมูลนักเรียนใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/student_actions.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="action" value="create">
          <div class="modal-body p-4">
              
              <div class="mb-3">
                  <label class="form-label small text-muted">รูปโปรไฟล์ (ไม่บังคับ)</label>
                  <input type="file" name="profile_picture" class="form-control bg-light" accept="image/*">
              </div>

              <h6 class="text-primary mb-3">บัญชีผู้ใช้</h6>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">รหัสนักเรียน (ห้ามซ้ำ) *ใช้เป็น Username</label>
                      <input type="text" name="student_code" class="form-control bg-light" placeholder="เช่น s6701001" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">รหัสผ่านสำหรับลงชื่อเข้าใช้ระบบ *</label>
                      <input type="password" name="password" class="form-control bg-light" placeholder="ระบุรหัสผ่าน" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[A-Za-z0-9]+" title="ต้องมีตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก และตัวเลขผสมกัน" required>
                  </div>
              </div>

              <h6 class="text-primary mt-3 mb-3">ข้อมูลส่วนตัวนักเรียน</h6>
              <div class="row">
                  <div class="col-md-3 mb-3">
                      <label class="form-label small text-muted">คำนำหน้า *</label> 
                      <select name="prefix" class="form-select bg-light" required>
                          <option value="ด.ช.">ด.ช.</option>
                          <option value="ด.ญ.">ด.ญ.</option>
                          <option value="นาย">นาย</option>
                          <option value="นางสาว">นางสาว</option>
                      </select>
                  </div>
                  <div class="col-md-5 mb-3">
                      <label class="form-label small text-muted">ชื่อจริง *</label>
                      <input type="text" name="first_name" class="form-control bg-light" required>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">นามสกุล *</label>
                      <input type="text" name="last_name" class="form-control bg-light" required>
                  </div>
              </div>

              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">วัน/เดือน/ปีเกิด</label>
                      <input type="date" name="dob" class="form-control bg-light">
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">ชั้นเรียน (เช่น ม.1/2) *</label>
                      <select name="class_id" class="form-select bg-light" required>
                          <option disabled selected value="">เลือกชั้นเรียน...</option>
                          <?php
                          $allClasses = $pdo->query("SELECT id, level_name FROM classes ORDER BY level_name ASC")->fetchAll();
                          foreach($allClasses as $c) {
                              echo "<option value='{$c['id']}'>{$c['level_name']}</option>";
                          }
                          ?>
                      </select>
                  </div>
              </div>
          </div>
          <div class="modal-footer border-top-0 pt-0 bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border:none;">บันทึกข้อมูลนักเรียน</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="editStudentModalLabel">แก้ไขข้อมูลนักเรียน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/student_actions.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="student_id" id="edit_student_id">
          <input type="hidden" name="user_id" id="edit_user_id">
          
          <div class="modal-body p-4">
              
              <div class="mb-3 text-center">
                  <img id="edit_preview_img" src="" alt="Profile Preview" class="rounded-circle mb-2 d-none"
                      style="width:80px;height:80px;object-fit:cover;border:2px solid var(--accent-color);">
                  <div>
                      <label class="form-label small text-muted">อัปเดตรูปโปรไฟล์</label>
                      <input type="file" name="profile_picture" class="form-control form-control-sm bg-light" accept="image/*">
                      <div class="form-check text-start mt-2">
                          <input class="form-check-input" type="checkbox" name="remove_profile_picture" id="edit_student_remove_pic" value="1">
                          <label class="form-check-label text-danger small" for="edit_student_remove_pic">
                              ลบรูปโปรไฟล์ปัจจุบัน
                          </label>
                      </div>
                  </div>
              </div>

              <h6 class="text-primary mb-3">บัญชีผู้ใช้</h6>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">รหัสนักเรียน (Username)</label>
                      <input type="text" id="edit_student_code" class="form-control bg-light" readonly>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">รหัสผ่านใหม่ <span class="text-danger">(เว้นว่างไว้หากไม่เปลี่ยน)</span></label>
                      <input type="password" name="password" class="form-control bg-light" placeholder="••••••••" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[A-Za-z0-9]+" title="ต้องมีตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก และตัวเลขผสมกัน">
                  </div>
              </div>

              <h6 class="text-primary mt-3 mb-3">ข้อมูลส่วนตัวนักเรียน</h6>
              <div class="row">
                  <div class="col-md-3 mb-3">
                      <label class="form-label small text-muted">คำนำหน้า *</label>
                      <select name="prefix" id="edit_prefix" class="form-select bg-light" required>
                          <option value="ด.ช.">ด.ช.</option>
                          <option value="ด.ญ.">ด.ญ.</option>
                          <option value="นาย">นาย</option>
                          <option value="นางสาว">นางสาว</option>
                      </select>
                  </div>
                  <div class="col-md-5 mb-3">
                      <label class="form-label small text-muted">ชื่อจริง *</label>
                      <input type="text" name="first_name" id="edit_first_name" class="form-control bg-light" required>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">นามสกุล *</label>
                      <input type="text" name="last_name" id="edit_last_name" class="form-control bg-light" required>
                  </div>
              </div>

              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">วัน/เดือน/ปีเกิด</label>
                      <input type="date" name="dob" id="edit_dob" class="form-control bg-light">
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">ชั้นเรียน (เช่น ม.1/2) *</label>
                      <select name="class_id" id="edit_class_id" class="form-select bg-light" required>
                          <option disabled value="">เลือกชั้นเรียน...</option>
                          <?php
                          foreach($allClasses as $c) {
                              echo "<option value='{$c['id']}'>{$c['level_name']}</option>";
                          }
                          ?>
                      </select>
                  </div>
              </div>

          </div>
          <div class="modal-footer border-top-0 pt-0 bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border:none;">อัปเดตข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
    function openEditModal(student) {
        document.getElementById('edit_student_id').value = student.id;
        document.getElementById('edit_user_id').value = student.user_id;
        document.getElementById('edit_student_code').value = student.student_code;
        document.getElementById('edit_prefix').value = student.prefix;
        document.getElementById('edit_first_name').value = student.first_name;
        document.getElementById('edit_last_name').value = student.last_name;
        document.getElementById('edit_class_id').value = student.class_id;
        document.getElementById('edit_dob').value = student.dob || '';
        
        let previewImg = document.getElementById('edit_preview_img');
        if (student.profile_picture) {
            previewImg.src = 'uploads/profiles/' + student.profile_picture;
            previewImg.classList.remove('d-none');
        } else {
            previewImg.src = '';
            previewImg.classList.add('d-none');
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
