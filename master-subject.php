<?php
// master-subject.php - Subject Management
require_once 'includes/auth.php';
requireRole('admin');
include 'includes/header.php';
require_once 'includes/db.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">ข้อมูลรายวิชา</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item text-muted">ข้อมูลพื้นฐาน</li>
                <li class="breadcrumb-item active" aria-current="page"><a href="master-subject.php" class="text-dark text-decoration-none">ข้อมูลรายวิชา</a></li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fas fa-plus me-2"></i>เพิ่มข้อมูลรายวิชา
        </button>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success_add'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> เพิ่มข้อมูลรายวิชาสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_delete'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-trash-alt me-2"></i> ลบข้อมูลรายวิชาสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_edit'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-edit me-2"></i> บันทึกการแก้ไขข้อมูลรายวิชาสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_duplicate'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> ไม่สามารถเพิ่มหรือแก้ไขได้: รหัสวิชานี้ถูกใช้ในระบบแล้ว
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_delete_fk'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> ไม่สามารถลบได้: ข้อมูลวิชานี้ถูกอ้างอิงและใช้งานอยู่ (เช่นผลการเรียน)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php else: ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-times-circle me-2"></i> เกิดข้อผิดพลาดในระบบ
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

$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    $whereClause .= " AND (subject_code LIKE :search1 OR name LIKE :search2)";
    $params[':search1'] = $searchParam;
    $params[':search2'] = $searchParam;
}

// Count total
$countSql = "SELECT COUNT(*) FROM subjects" . $whereClause;
$stmtCount = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $stmtCount->bindValue($key, $val, PDO::PARAM_STR);
}
$stmtCount->execute();
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch data
$sql = "SELECT * FROM subjects " . $whereClause . " ORDER BY subject_code ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$subjects = $stmt->fetchAll();
?>

<div class="card shadow-sm-light">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom gap-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-book me-2"></i>รายวิชาทั้งหมด</h6>
        
        <form method="GET" action="master-subject.php" class="d-flex align-items-center gap-2">
            <div class="input-group" style="width: 250px;">
                <input type="text" name="search" class="form-control form-control-sm bg-light border-0" placeholder="รหัสวิชา, ชื่อวิชา..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button class="btn btn-sm btn-light border-0" type="submit"><i class="fas fa-search text-muted"></i></button>
            </div>
        </form>
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
                    if (count($subjects) > 0) {
                        foreach ($subjects as $sub) {
                    ?>
                     <tr>
                         <td><span class="fw-medium text-dark"><?= htmlspecialchars($sub['subject_code']) ?></span></td>
                         <td><?= htmlspecialchars($sub['name']) ?></td>
                         <td class="text-center"><span class="badge bg-light text-dark border rounded-pill px-3"><?= htmlspecialchars($sub['credit']) ?></span></td>
                         <td><?= $sub['type'] === 'core' ? 'วิชาพื้นฐาน (Core)' : 'วิชาเพิ่มเติม (Elective)' ?></td>
                         <td class="text-center">
                             <button type="button" class="btn btn-sm btn-light text-primary me-1" onclick='openEditModal(<?= json_encode($sub) ?>)' data-bs-toggle="modal" data-bs-target="#editSubjectModal"><i class="fas fa-edit"></i></button>
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
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'><i class='fas fa-info-circle mb-2' style='font-size: 24px; opacity: 0.5;'></i><br>ไม่มีข้อมูลรายวิชา</td></tr>";
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
                $endPage = min($totalPages, max(1, $startPage + $maxPagesToShow - 1));
                
                if ($endPage - $startPage + 1 < $maxPagesToShow) {
                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                }
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>" <?= ($page == $i) ? 'style="--bs-pagination-active-bg: var(--accent-color); --bs-pagination-active-border-color: var(--accent-color);"' : '' ?>>
                        <a class="page-link" href="<?= buildParams($i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= ($page >= $totalPages || $totalPages == 0) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($page >= $totalPages || $totalPages == 0) ? '#' : buildParams($page + 1) ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
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
          <div class="modal-body p-4">
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
          <div class="modal-footer border-top-0 pt-0 bg-light">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border:none;">บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="editSubjectModalLabel">แก้ไขข้อมูลรายวิชา</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/subject_actions.php" method="POST">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="subject_id" id="edit_subject_id">
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label small text-muted">รหัสวิชา (ห้ามซ้ำ)</label>
                  <input type="text" name="subject_code" id="edit_subject_code" class="form-control bg-light" required>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">ชื่อวิชา</label>
                  <input type="text" name="name" id="edit_name" class="form-control bg-light" required>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">หน่วยกิต</label>
                      <input type="number" name="credit" id="edit_credit" class="form-control bg-light" step="0.5" min="0.5" max="3.0" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label small text-muted">ประเภทวิชา</label>
                      <select name="type" id="edit_type" class="form-select bg-light">
                          <option value="core">วิชาพื้นฐาน</option>
                          <option value="elective">วิชาเพิ่มเติม</option>
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
    function openEditModal(subject) {
        document.getElementById('edit_subject_id').value = subject.id;
        document.getElementById('edit_subject_code').value = subject.subject_code;
        document.getElementById('edit_name').value = subject.name;
        document.getElementById('edit_credit').value = subject.credit;
        document.getElementById('edit_type').value = subject.type;
    }
</script>

<?php include 'includes/footer.php'; ?>
