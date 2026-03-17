<?php
// master-class.php - Class Level Management
require_once 'includes/auth.php';
requireRole('admin');
include 'includes/header.php';
?>

<div class="row">
    <!-- Class Level Table -->
    <div class="col-md-6 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-dark fw-bold mb-0">ข้อมูลระดับชั้นเรียน</h3>
            </div>
            <button class="btn btn-sm shadow-sm text-white" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="fas fa-plus me-1"></i>เพิ่มชั้นเรียน
            </button>
        </div>
        
        <div class="card shadow-sm-light h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-layer-group me-2"></i>ระดับชั้นทั้งหมด</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-custom table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="30%">รหัส</th>
                            <th width="50%">ชื่อระดับชั้น</th>
                            <th width="20%" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        require_once 'includes/db.php';
                        $stmt = $pdo->query("SELECT * FROM classes ORDER BY level_name ASC");
                        $classes = $stmt->fetchAll();
                        
                        if (count($classes) > 0) {
                            foreach ($classes as $cls) {
                        ?>
                         <tr>
                             <td><span class="badge bg-light text-dark border">C-<?= $cls['id'] ?></span></td>
                             <td class="fw-medium"><?= htmlspecialchars($cls['level_name']) ?></td>
                             <td class="text-center d-flex justify-content-center gap-1">
                                 <a href="master-classroom.php?class_id=<?= $cls['id'] ?>" class="btn btn-sm btn-light text-primary" title="จัดการห้องเรียนย่อย"><i class="fas fa-cog"></i></a>
                                 <form action="api/class_actions.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบระดับชั้นนี้? (ห้องเรียนย่อยจะถูกลบไปด้วย)');">
                                     <input type="hidden" name="action" value="delete">
                                     <input type="hidden" name="id" value="<?= $cls['id'] ?>">
                                     <button type="submit" class="btn btn-sm btn-light text-danger"><i class="fas fa-trash"></i></button>
                                 </form>
                             </td>
                         </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='3' class='text-center py-4 text-muted'>ไม่มีข้อมูลระดับชั้น</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Class Room Details -->
    <div class="col-md-6 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                 <h3 class="text-dark fw-bold mb-0 text-transparent" style="opacity:0;">ห้องเรียนย่อย</h3>
            </div>
        </div>

        <div class="card shadow-sm-light h-100 border-start border-4" style="border-left-color: var(--accent-color) !important;">
            <div class="card-body text-center d-flex flex-column justify-content-center align-items-center py-5">
                <div class="mb-3 text-muted" style="font-size: 3rem;">
                    <i class="fas fa-hand-pointer"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">เลือกระดับชั้นเพื่อดูห้องเรียนย่อย</h5>
                <p class="text-muted mb-0">คลิกที่ "จัดการ (รูปเฟือง)" ของระดับชั้นทางด้านซ้ายเพื่อเข้าไปจัดการห้องเรียนย่อย (เช่น ม.1/1, ม.1/2)</p>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">เพิ่มระดับชั้นเรียนใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/class_actions.php" method="POST">
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label small text-muted">ชื่อระดับชั้น (ห้ามซ้ำ)</label>
                  <input type="text" name="level_name" class="form-control bg-light" placeholder="เช่น ม.1" required>
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
