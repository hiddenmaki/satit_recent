<?php
// master-student.php - Student Management
require_once 'includes/auth.php';
requireRole('admin');
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">ข้อมูลนักเรียน</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item text-muted">ข้อมูลพื้นฐาน</li>
                <li class="breadcrumb-item active" aria-current="page">ข้อมูลนักเรียน</li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus me-2"></i>เพิ่มข้อมูลนักเรียน
        </button>
    </div>
</div>

<div class="card shadow-sm-light">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <div class="d-flex align-items-center gap-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users me-2"></i>รายชื่อนักเรียน</h6>
            <!-- Filter Dropdown -->
            <select class="form-select form-select-sm bg-light border-0 w-auto">
                <option value="">ทุกระดับชั้น</option>
                <option value="ม.1">ม.1</option>
                <option value="ม.4">ม.4</option>
            </select>
            <select class="form-select form-select-sm bg-light border-0 w-auto">
                <option value="">ทุกห้อง</option>
                <option value="1">ห้อง 1</option>
                <option value="2">ห้อง 2</option>
            </select>
        </div>
        <div class="input-group" style="width: 250px;">
            <input type="text" class="form-control form-control-sm bg-light border-0" placeholder="รหัสนักเรียน, ชื่อ..." aria-label="Search">
            <button class="btn btn-sm btn-light border-0" type="button"><i class="fas fa-search text-muted"></i></button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="15%">รหัสนักเรียน</th>
                        <th width="30%">ชื่อ - นามสกุล</th>
                        <th width="15%">ชั้น/ห้อง</th>
                        <th width="20%">บัญชีผู้ใช้</th>
                        <th width="20%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    require_once 'includes/db.php';
                    $stmt = $pdo->query("
                        SELECT s.*, u.prefix, u.first_name, u.last_name, u.username, c.room_name 
                        FROM students s 
                        JOIN users u ON s.user_id = u.id 
                        LEFT JOIN classrooms c ON s.classroom_id = c.id
                        ORDER BY c.room_name ASC, s.student_code ASC
                    ");
                    $students = $stmt->fetchAll();
                    
                    if (count($students) > 0) {
                        foreach ($students as $stu) {
                            $fullName = $stu['prefix'] . $stu['first_name'] . ' ' . $stu['last_name'];
                    ?>
                     <tr>
                         <td><?= htmlspecialchars($stu['student_code']) ?></td>
                         <td><?= htmlspecialchars($fullName) ?></td>
                         <td><span class="badge bg-info-light border"><?= htmlspecialchars($stu['room_name'] ?? 'ยังไม่ระบุ') ?></span></td>
                         <td><?= htmlspecialchars($stu['username']) ?></td>
                         <td class="text-center">
                             <form action="api/student_actions.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบนักเรียนคนนี้และการเข้าถึงระบบของนักเรียนคนนี้?');">
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="id" value="<?= $stu['id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                             </form>
                         </td>
                     </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>ไม่มีข้อมูลนักเรียน</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="addStudentModalLabel">เพิ่มข้อมูลนักเรียนใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/student_actions.php" method="POST">
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label small text-muted">รหัสนักเรียน (ห้ามซ้ำ) *ใช้เป็น Username ด้วย</label>
                  <input type="text" name="student_code" class="form-control bg-light" required>
              </div>
              <div class="row">
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">คำนำหน้า</label>
                      <select name="prefix" class="form-select bg-light">
                          <option value="ด.ช.">ด.ช.</option>
                          <option value="ด.ญ.">ด.ญ.</option>
                          <option value="นาย">นาย</option>
                          <option value="นางสาว">นางสาว</option>
                      </select>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">ชื่อจริง</label>
                      <input type="text" name="first_name" class="form-control bg-light" required>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">นามสกุล</label>
                      <input type="text" name="last_name" class="form-control bg-light" required>
                  </div>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">ห้องเรียน</label>
                  <select name="classroom_id" class="form-select bg-light" required>
                      <option value="">เลือกห้องเรียน...</option>
                      <?php
                      $rooms = $pdo->query("SELECT id, room_name FROM classrooms ORDER BY room_name ASC")->fetchAll();
                      foreach($rooms as $r) {
                          echo "<option value='{$r['id']}'>{$r['room_name']}</option>";
                      }
                      ?>
                  </select>
              </div>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border:none;">บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
