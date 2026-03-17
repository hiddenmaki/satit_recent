<?php
// master-subject.php - Subject Management
require_once 'includes/auth.php';
requireRole('admin');
include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">ข้อมูลรายวิชา</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item text-muted">ข้อมูลพื้นฐาน</li>
                <li class="breadcrumb-item active" aria-current="page">ข้อมูลรายวิชา</li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fas fa-plus me-2"></i>เพิ่มข้อมูลรายวิชา
        </button>
    </div>
</div>

<div class="card shadow-sm-light">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-book me-2"></i>รายวิชาทั้งหมด</h6>
        <div class="input-group" style="width: 250px;">
            <input type="text" class="form-control form-control-sm bg-light border-0" placeholder="รหัสวิชา, ชื่อวิชา..." aria-label="Search">
            <button class="btn btn-sm btn-light border-0" type="button"><i class="fas fa-search text-muted"></i></button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="15%">รหัสวิชา</th>
                        <th width="35%">ชื่อวิชา</th>
                        <th width="15%" class="text-center">หน่วยกิต</th>
                        <th width="20%">ประเภทวิชา</th>
                        <th width="15%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    require_once 'includes/db.php';
                    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY subject_code ASC");
                    $subjects = $stmt->fetchAll();
                    
                    if (count($subjects) > 0) {
                        foreach ($subjects as $sub) {
                    ?>
                     <tr>
                         <td><span class="fw-medium"><?= htmlspecialchars($sub['subject_code']) ?></span></td>
                         <td><?= htmlspecialchars($sub['name']) ?></td>
                         <td class="text-center"><span class="badge bg-light text-dark border rounded-pill px-3"><?= htmlspecialchars($sub['credit']) ?></span></td>
                         <td><?= $sub['type'] === 'core' ? 'วิชาพื้นฐาน (Core)' : 'วิชาเพิ่มเติม (Elective)' ?></td>
                         <td class="text-center">
                             <form action="api/subject_actions.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบรายวิชานี้?');">
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                             </form>
                         </td>
                     </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>ไม่มีข้อมูลรายวิชา</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="addSubjectModalLabel">เพิ่มข้อมูลรายวิชาใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/subject_actions.php" method="POST">
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label small text-muted">รหัสวิชา (ห้ามซ้ำ)</label>
                  <input type="text" name="subject_code" class="form-control bg-light" placeholder="เช่น ว31101" required>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">ชื่อวิชา</label>
                  <input type="text" name="name" class="form-control bg-light" placeholder="เช่น ฟิสิกส์ 1" required>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">หน่วยกิต</label>
                      <input type="number" name="credit" class="form-control bg-light" step="0.5" min="0.5" max="3.0" value="1.0" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">ประเภทวิชา</label>
                      <select name="type" class="form-select bg-light">
                          <option value="core">วิชาพื้นฐาน</option>
                          <option value="elective">วิชาเพิ่มเติม</option>
                      </select>
                  </div>
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
