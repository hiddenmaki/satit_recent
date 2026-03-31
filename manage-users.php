<?php
// manage-users.php - Admin User & Account Management
require_once 'includes/auth.php';
requireRole('admin');
include 'includes/header.php';
require_once 'includes/db.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">จัดการบัญชีผู้ใช้งาน</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page"><a href="manage-users.php"
                        class="text-dark text-decoration-none">จัดการบัญชีผู้ใช้งาน</a></li>
            </ol>
        </nav>
    </div>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;"
            data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fas fa-user-shield me-2"></i>เพิ่มผู้ดูแลระบบ
        </button>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success_add'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> เพิ่มบัญชีผู้ดูแลระบบสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_edit'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-edit me-2"></i> อัปเดตข้อมูลบัญชีสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_toggle'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-toggle-on me-2"></i> เปลี่ยนสถานะบัญชีสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_reset'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-key me-2"></i> รีเซ็ตรหัสผ่านสำเร็จ (รหัสผ่านใหม่: <b>Password1</b>)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_role'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-user-tag me-2"></i> เปลี่ยนสิทธิ์ผู้ใช้งานสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_duplicate'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_self'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> ไม่สามารถระงับหรือเปลี่ยนสิทธิ์บัญชีตัวเองได้
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_delete'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-trash-alt me-2"></i> ลบบัญชีผู้ใช้งานออกจากระบบเรียบร้อยแล้ว
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'err_delete'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> ไม่สามารถลบบัญชีนี้ได้ อาจมีการผูกข้อมูลสำคัญไว้ในระบบ
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
// Pagination and Filter
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['account_status'] ?? '';

$whereClause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    $whereClause .= " AND (u.username LIKE :search1 OR u.first_name LIKE :search2 OR u.last_name LIKE :search3)";
    $params[':search1'] = $searchParam;
    $params[':search2'] = $searchParam;
    $params[':search3'] = $searchParam;
}

if (!empty($filter_role)) {
    $whereClause .= " AND u.role = :role";
    $params[':role'] = $filter_role;
}

if (!empty($filter_status)) {
    $whereClause .= " AND u.status = :status";
    $params[':status'] = $filter_status;
}

// Count total records
$countSql = "SELECT COUNT(*) as total FROM users u" . $whereClause;
$stmtCount = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $stmtCount->bindValue($key, $val, PDO::PARAM_STR);
}
$stmtCount->execute();
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch data
$sql = "SELECT u.* FROM users u" . $whereClause . " ORDER BY u.role ASC, u.username ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

// Summary counts
$stmtSummary = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
$roleCounts = $stmtSummary->fetchAll(PDO::FETCH_KEY_PAIR);
$stmtActiveCount = $pdo->query("SELECT status, COUNT(*) as cnt FROM users GROUP BY status");
$statusCounts = $stmtActiveCount->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm-light h-100">
            <div class="card-body d-flex align-items-center py-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                    <i class="fas fa-users text-primary fs-5"></i>
                </div>
                <div>
                    <small class="text-muted">ทั้งหมด</small>
                    <h5 class="fw-bold mb-0"><?= $totalRecords ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm-light h-100">
            <div class="card-body d-flex align-items-center py-3">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                    <i class="fas fa-user-shield text-danger fs-5"></i>
                </div>
                <div>
                    <small class="text-muted">ผู้ดูแลระบบ</small>
                    <h5 class="fw-bold mb-0"><?= $roleCounts['admin'] ?? 0 ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm-light h-100">
            <div class="card-body d-flex align-items-center py-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                    <i class="fas fa-check-circle text-success fs-5"></i>
                </div>
                <div>
                    <small class="text-muted">ใช้งานอยู่</small>
                    <h5 class="fw-bold mb-0"><?= $statusCounts['active'] ?? 0 ?></h5>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-3">
        <div class="card border-0 shadow-sm-light h-100">
            <div class="card-body d-flex align-items-center py-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                    <i class="fas fa-ban text-warning fs-5"></i>
                </div>
                <div>
                    <small class="text-muted">ถูกระงับ</small>
                    <h5 class="fw-bold mb-0"><?= $statusCounts['inactive'] ?? 0 ?></h5>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Table -->
<div class="card shadow-sm-light">
    <div
        class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom gap-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users-cog me-2"></i>รายชื่อบัญชีผู้ใช้งานทั้งหมด
        </h6>

        <form method="GET" action="manage-users.php" class="d-flex align-items-center gap-2 flex-wrap">
            <select name="role" class="form-select form-select-sm bg-light border-0 w-auto"
                onchange="this.form.submit()">
                <option value="">ทุกสิทธิ์</option>
                <option value="admin" <?= ($filter_role == 'admin') ? 'selected' : '' ?>>ผู้ดูแลระบบ</option>
                <option value="teacher" <?= ($filter_role == 'teacher') ? 'selected' : '' ?>>ครู</option>
                <option value="student" <?= ($filter_role == 'student') ? 'selected' : '' ?>>นักเรียน</option>
            </select>
            <select name="account_status" class="form-select form-select-sm bg-light border-0 w-auto"
                onchange="this.form.submit()">
                <option value="">ทุกสถานะ</option>
                <option value="active" <?= ($filter_status == 'active') ? 'selected' : '' ?>>ใช้งานอยู่</option>
                <option value="inactive" <?= ($filter_status == 'inactive') ? 'selected' : '' ?>>ถูกระงับ</option>
            </select>
            <div class="input-group" style="width: 220px;">
                <input type="text" name="search" class="form-control form-control-sm bg-light border-0"
                    placeholder="ค้นหา username, ชื่อ..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-sm btn-light border-0" type="submit"><i
                        class="fas fa-search text-muted"></i></button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="15%">Username</th>
                        <th width="22%">ชื่อ - นามสกุล</th>
                        <th width="10%">สิทธิ์</th>
                        <th width="12%">สถานะ</th>
                        <th width="16%">เข้าใช้ล่าสุด</th>
                        <th width="20%" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php $i = $offset + 1;
                        foreach ($users as $u):
                            $roleBadge = '';
                            if ($u['role'] === 'admin') {
                                $roleBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1"><i class="fas fa-shield-alt me-1"></i>Admin</span>';
                            } elseif ($u['role'] === 'teacher') {
                                $roleBadge = '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1"><i class="fas fa-chalkboard-teacher me-1"></i>ครู</span>';
                            } else {
                                $roleBadge = '<span class="badge bg-info bg-opacity-10 text-info border border-info px-2 py-1"><i class="fas fa-user-graduate me-1"></i>นักเรียน</span>';
                            }

                            $statusBadge = '';
                            if ($u['status'] === 'active') {
                                $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1"><i class="fas fa-check-circle me-1"></i>Active</span>';
                            } else {
                                $statusBadge = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary px-2 py-1"><i class="fas fa-ban me-1"></i>Inactive</span>';
                            }

                            $lastLogin = $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '<span class="text-muted">ยังไม่เคยเข้าใช้</span>';
                            $isSelf = ($u['id'] == $_SESSION['user_id']);
                            ?>
                            <tr class="<?= $u['status'] === 'inactive' ? 'table-secondary opacity-75' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($u['username']) ?></span>
                                    <?php if ($isSelf): ?>
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">คุณ</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(($u['prefix'] ?? '') . $u['first_name'] . ' ' . $u['last_name']) ?>
                                </td>
                                <td><?= $roleBadge ?></td>
                                <td><?= $statusBadge ?></td>
                                <td><small><?= $lastLogin ?></small></td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        <!-- Toggle Status -->
                                        <?php if (!$isSelf): ?>
                                            <form action="api/user_actions.php" method="POST" class="d-inline"
                                                onsubmit="return confirm('<?= $u['status'] === 'active' ? 'ยืนยันระงับบัญชีนี้?' : 'ยืนยันเปิดใช้งานบัญชีนี้?' ?>');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <?php if ($u['status'] === 'active'): ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="ระงับบัญชี"><i
                                                            class="fas fa-ban"></i></button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="เปิดใช้งาน"><i
                                                            class="fas fa-check"></i></button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Reset Password -->
                                        <form action="api/user_actions.php" method="POST" class="d-inline"
                                            onsubmit="return confirm('รีเซ็ตรหัสผ่านเป็น Password123 ?');">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                title="รีเซ็ตรหัสผ่าน"><i class="fas fa-key"></i></button>
                                        </form>

                                        <!-- Change Role -->
                                        <?php if (!$isSelf): ?>
                                            <button class="btn btn-sm btn-outline-primary" title="เปลี่ยนสิทธิ์"
                                                data-bs-toggle="modal" data-bs-target="#changeRoleModal"
                                                onclick="openRoleModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= $u['role'] ?>')">
                                                <i class="fas fa-user-tag"></i>
                                            </button>
                                            
                                            <!-- Delete User -->
                                            <form action="api/user_actions.php" method="POST" class="d-inline"
                                                  onsubmit="return confirm('คำเตือน: การลบบัญชีนี้จะส่งผลให้ข้อมูลโปรไฟล์ (ครู/นักเรียน) \nและข้อมูลที่เกี่ยวข้อง (เช่น ตารางสอน, เกรด, การมาเรียน) หายไปทั้งหมด!\nคุณยืนยันที่จะลบผู้ใช้งานนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบบัญชี"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-info-circle mb-2"
                                    style="font-size: 24px; opacity: 0.5;"></i><br>ไม่พบข้อมูลผู้ใช้งาน
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
        <?php
        $startItem = ($totalRecords > 0) ? $offset + 1 : 0;
        $endItem = min($offset + $limit, $totalRecords);
        ?>
        <small class="text-muted">แสดง <?= $startItem ?> ถึง <?= $endItem ?> จาก <?= $totalRecords ?> บัญชี</small>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <?php
                function buildUserParams($p)
                {
                    $q = $_GET;
                    $q['page'] = $p;
                    return '?' . http_build_query($q);
                }
                ?>
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($page <= 1) ? '#' : buildUserParams($page - 1) ?>">ก่อนหน้า</a>
                </li>
                <?php
                $maxPagesToShow = 5;
                $startPage = max(1, $page - floor($maxPagesToShow / 2));
                $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                if ($endPage - $startPage + 1 < $maxPagesToShow) {
                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                }
                for ($pg = $startPage; $pg <= $endPage; $pg++):
                    ?>
                    <li class="page-item <?= ($page == $pg) ? 'active' : '' ?>" <?= ($page == $pg) ? 'style="--bs-pagination-active-bg: var(--accent-color); --bs-pagination-active-border-color: var(--accent-color);"' : '' ?>>
                        <a class="page-link" href="<?= buildUserParams($pg) ?>"><?= $pg ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link"
                        href="<?= ($page >= $totalPages) ? '#' : buildUserParams($page + 1) ?>">ถัดไป</a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="addAdminModalLabel"><i
                        class="fas fa-user-shield me-2 text-danger"></i>เพิ่มผู้ดูแลระบบใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="api/user_actions.php" method="POST">
                <input type="hidden" name="action" value="create_admin">
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small border-0">
                        <i class="fas fa-info-circle me-1"></i> บัญชีผู้ดูแลระบบจะมีสิทธิ์เข้าถึงข้อมูลทั้งหมดในระบบ
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">ชื่อผู้ใช้งาน (Username) *</label>
                        <input type="text" name="username" class="form-control bg-light" placeholder="เช่น admin2"
                            required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">รหัสผ่าน *</label>
                        <input type="password" name="password" class="form-control bg-light"
                            placeholder="ตัวพิมพ์ใหญ่ + ตัวพิมพ์เล็ก + ตัวเลข"
                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[A-Za-z0-9]+"
                            title="ต้องมีตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก และตัวเลข" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small text-muted">คำนำหน้า *</label>
                            <select name="prefix" class="form-select bg-light" required>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                                <option value="ดร.">ดร.</option>
                                <option value="ผศ.">ผศ.</option>
                                <option value="รศ.">รศ.</option>
                                <option value="ศ.">ศ.</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small text-muted">ชื่อจริง *</label>
                            <input type="text" name="first_name" class="form-control bg-light" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small text-muted">นามสกุล *</label>
                            <input type="text" name="last_name" class="form-control bg-light" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger"><i
                            class="fas fa-user-shield me-1"></i>สร้างบัญชีผู้ดูแล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="changeRoleModalLabel"><i
                        class="fas fa-user-tag me-2"></i>เปลี่ยนสิทธิ์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="api/user_actions.php" method="POST">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" id="role_user_id">
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">เปลี่ยนสิทธิ์ของ: <strong id="role_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label small text-muted">สิทธิ์ใหม่ *</label>
                        <select name="new_role" id="role_select" class="form-select bg-light" required>
                            <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                            <option value="teacher">ครู (Teacher)</option>
                            <option value="student">นักเรียน (Student)</option>
                        </select>
                    </div>
                    <div class="alert alert-warning py-2 small border-0 mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        การเปลี่ยนสิทธิ์อาจทำให้ผู้ใช้เข้าถึงข้อมูลบางส่วนไม่ได้
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary btn-sm"
                        style="background-color: var(--accent-color); border:none;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openRoleModal(userId, username, currentRole) {
        document.getElementById('role_user_id').value = userId;
        document.getElementById('role_username').textContent = username;
        document.getElementById('role_select').value = currentRole;
    }
</script>

<?php include 'includes/footer.php'; ?>