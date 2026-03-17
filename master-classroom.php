<?php
// master-classroom.php - Classroom Management
require_once 'includes/auth.php';
requireRole('admin');
require_once 'includes/db.php';

// Get Optional class_id filter if coming from master-class.php
$class_id_filter = $_GET['class_id'] ?? null;
$class_name = "";

if ($class_id_filter) {
    $stmtClass = $pdo->prepare("SELECT level_name FROM classes WHERE id = ?");
    $stmtClass->execute([$class_id_filter]);
    $classDetails = $stmtClass->fetch();
    if($classDetails) {
        $class_name = " (" . htmlspecialchars($classDetails['level_name']) . ")";
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">ข้อมูลห้องเรียนย่อย<?= $class_name ?></h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="master-class.php" class="text-decoration-none text-muted">ระดับชั้น</a></li>
                <li class="breadcrumb-item active" aria-current="page">ห้องเรียนย่อย</li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addClassroomModal">
            <i class="fas fa-plus me-2"></i>เพิ่มห้องเรียนย่อย
        </button>
    </div>
</div>

<div class="card shadow-sm-light">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-door-open me-2"></i>รายชื่อห้องเรียนทั้งหมด<?= $class_name ?></h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="30%">ระดับชั้น</th>
                        <th width="50%">ชื่อห้องเรียน (ex. ม.1/1)</th>
                        <th width="20%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT c.id, c.room_name, cl.level_name 
                            FROM classrooms c 
                            JOIN classes cl ON c.class_id = cl.id ";
                    if ($class_id_filter) {
                        $sql .= " WHERE c.class_id = :cid ";
                    }
                    $sql .= " ORDER BY cl.level_name ASC, c.room_name ASC";
                    
                    $stmt = $pdo->prepare($sql);
                    if ($class_id_filter) {
                        $stmt->bindParam(':cid', $class_id_filter);
                    }
                    $stmt->execute();
                    $rooms = $stmt->fetchAll();
                    
                    if (count($rooms) > 0) {
                        foreach ($rooms as $room) {
                    ?>
                     <tr>
                         <td><span class="badge bg-info-light border"><?= htmlspecialchars($room['level_name']) ?></span></td>
                         <td><span class="fw-bold"><?= htmlspecialchars($room['room_name']) ?></span></td>
                         <td class="text-center">
                             <form action="api/classroom_actions.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบห้องเรียนนี้? (นักเรียนในห้องจะได้รับผลกระทบ)');">
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="id" value="<?= $room['id'] ?>">
                                 <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                             </form>
                         </td>
                     </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='3' class='text-center py-4 text-muted'>ไม่มีข้อมูลห้องเรียนย่อย</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Classroom Modal -->
<div class="modal fade" id="addClassroomModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">เพิ่มห้องเรียนย่อยใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/classroom_actions.php" method="POST">
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label small text-muted">ระดับชั้น</label>
                  <select name="class_id" class="form-select bg-light" required>
                      <option value="">เลือกระดับชั้น...</option>
                      <?php
                      $allClasses = $pdo->query("SELECT id, level_name FROM classes ORDER BY level_name ASC")->fetchAll();
                      foreach($allClasses as $c) {
                          $selected = ($class_id_filter == $c['id']) ? 'selected' : '';
                          echo "<option value='{$c['id']}' {$selected}>{$c['level_name']}</option>";
                      }
                      ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">ชื่อห้องเรียน</label>
                  <input type="text" name="room_name" class="form-control bg-light" placeholder="เช่น ม.1/1 หรือ 1/1" required>
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
