<?php
// schedule.php - Schedule Management (Enhanced)
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Filters
$filter_class_id = $_GET['class_id'] ?? '';
$filter_classroom_id = $_GET['classroom_id'] ?? '';

// Fetch all master data for dropdowns
$allSubjects = $pdo->query("SELECT id, subject_code, name FROM subjects ORDER BY subject_code ASC")->fetchAll();
$allTeachers = $pdo->query("SELECT t.id, t.teacher_code, u.prefix, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY u.first_name ASC")->fetchAll();
$allClasses = $pdo->query("SELECT id, class_code, level_name FROM classes ORDER BY level_name ASC")->fetchAll();
$allRooms = $pdo->query("SELECT id, room_code, room_number, room_name FROM classrooms ORDER BY room_code ASC")->fetchAll();

// Determine what to show based on role
$showSchedule = false;
$scheduleData = [];
$filterLabel = "";

if ($role === 'student') {
    $stmt = $pdo->prepare("SELECT class_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    if ($student && $student['class_id']) {
        $filter_class_id = $student['class_id'];
        $showSchedule = true;
    }
} else if ($role === 'teacher') {
    $showSchedule = true;
} else if ($role === 'admin') {
    if (!empty($filter_class_id) || !empty($filter_classroom_id)) {
        $showSchedule = true;
    }
}

// Fetch Schedule Data
if ($showSchedule && (!empty($filter_class_id) || !empty($filter_classroom_id))) {
    $whereField = '';
    $whereValue = '';

    if (!empty($filter_class_id)) {
        $whereField = 'ts.class_id';
        $whereValue = $filter_class_id;
        // Get class label
        $sl = $pdo->prepare("SELECT level_name FROM classes WHERE id = ?");
        $sl->execute([$filter_class_id]);
        $filterLabel = $sl->fetchColumn() ?: '';
    } elseif (!empty($filter_classroom_id)) {
        $whereField = 'ts.classroom_id';
        $whereValue = $filter_classroom_id;
        $sl = $pdo->prepare("SELECT room_name FROM classrooms WHERE id = ?");
        $sl->execute([$filter_classroom_id]);
        $filterLabel = $sl->fetchColumn() ?: '';
    }

    $stmt = $pdo->prepare("
        SELECT ts.*, 
               sub.name as subject_name, sub.subject_code, 
               u.first_name as teacher_fname, u.last_name as teacher_lname, u.prefix as teacher_prefix,
               cl.level_name,
               cr.room_name, cr.room_code
        FROM teaching_schedule ts
        JOIN subjects sub ON ts.subject_id = sub.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN classes cl ON ts.class_id = cl.id
        LEFT JOIN classrooms cr ON ts.classroom_id = cr.id
        WHERE {$whereField} = ?
        ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), ts.start_time ASC
    ");
    $stmt->execute([$whereValue]);
    $scheduleDataRaw = $stmt->fetchAll();

    // Group by Day
    foreach ($scheduleDataRaw as $row) {
        $day = $row['day_of_week'];
        if (!isset($scheduleData[$day]))
            $scheduleData[$day] = [];
        $scheduleData[$day][] = $row;
    }
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">ตารางเรียน / ตารางสอน</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">จัดการตารางเรียน</li>
            </ol>
        </nav>
    </div>
    <?php if ($role === 'admin'): ?>
        <div>
            <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;"
                data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-2"></i>จัดตารางสอนใหม่
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'ดำเนินการสำเร็จ') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_delete'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-trash-alt me-2"></i> ลบข้อมูลตารางสอนสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'success_edit'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-edit me-2"></i> แก้ไขตารางสอนสำเร็จ
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'เกิดข้อผิดพลาด') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Filters -->
<?php if ($role !== 'student'): ?>
    <div class="card shadow-sm-light mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="m-0 font-weight-bold text-primary"><i
                    class="fas fa-filter me-2"></i>เลือกดูตารางเรียนตามชั้นเรียนหรือห้องเรียน</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="schedule.php" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">ชั้นเรียน</label>
                    <select class="form-select bg-light" name="class_id">
                        <option value="">-- เลือกชั้นเรียน --</option>
                        <?php foreach ($allClasses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($filter_class_id == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['level_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">หรือ ห้องเรียน</label>
                    <select class="form-select bg-light" name="classroom_id">
                        <option value="">-- เลือกห้องเรียน --</option>
                        <?php foreach ($allRooms as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($filter_classroom_id == $r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($r['room_code'] ? $r['room_code'] . ' - ' : '') . $r['room_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary shadow-sm"
                        style="background-color: var(--accent-color); border: none;">
                        <i class="fas fa-search me-2"></i>แสดงตาราง
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($showSchedule && (!empty($filter_class_id) || !empty($filter_classroom_id))): ?>
    <div class="card shadow-sm-light border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>ตารางเรียน:
                <?= htmlspecialchars($filterLabel) ?></h6>
            
            <style>
                @media print {
                    /* Hide everything not related to the table */
                    body * {
                        visibility: hidden;
                    }
                    /* Only show the print area */
                    .card.shadow-sm-light.border-0.mb-4, 
                    .card.shadow-sm-light.border-0.mb-4 * {
                        visibility: visible;
                    }
                    /* Absolute position the print area to the top left of the page */
                    .card.shadow-sm-light.border-0.mb-4 {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        border: none !important;
                        box-shadow: none !important;
                    }
                    /* Hide the print button itself */
                    .btn-outline-primary, .card-header .btn {
                        display: none !important;
                    }
                }
            </style>
            
            <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i
                    class="fas fa-print me-1"></i>พิมพ์</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive text-center">
                <table class="table table-bordered mb-0 table-custom">
                    <thead class="bg-light">
                        <tr>
                            <th width="10%">วัน/เวลา</th>
                            <th width="18%">08:30 - 10:10<br><small class="text-muted">(คาบ 1-2)</small></th>
                            <th width="18%">10:10 - 12:00<br><small class="text-muted">(คาบ 3-4)</small></th>
                            <th width="10%">12:00 - 13:00</th>
                            <th width="18%">13:00 - 14:40<br><small class="text-muted">(คาบ 5-6)</small></th>
                            <th width="18%">14:40 - 16:20<br><small class="text-muted">(คาบ 7-8)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $days = ['Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์'];
                        $colors = ['#3498db', '#2c3e50', '#e67e22', '#27ae60', '#8e44ad', '#e74c3c', '#16a085'];
                        $colorIndex = 0;

                        foreach ($days as $engDay => $thaiDay) {
                            echo "<tr>";
                            echo "<td class='align-middle fw-bold bg-light border-end'>{$thaiDay}</td>";

                            $slots = [
                                ['08:30:00', '10:10:00'],
                                ['10:10:00', '12:00:00'],
                                ['12:00:00', '13:00:00', 'break'],
                                ['13:00:00', '14:40:00'],
                                ['14:40:00', '16:20:00']
                            ];

                            $dayData = $scheduleData[$engDay] ?? [];

                            foreach ($slots as $idx => $slot) {
                                if (isset($slot[2]) && $slot[2] == 'break') {
                                    if ($engDay == 'Monday') {
                                        echo "<td class='align-middle bg-light text-muted fw-bold' rowspan='5'>🍽<br>พักกลางวัน</td>";
                                    }
                                    continue;
                                }

                                $found = false;
                                foreach ($dayData as $d) {
                                    if ($d['start_time'] == $slot[0] && $d['end_time'] == $slot[1]) {
                                        $bg = $colors[$colorIndex % count($colors)];
                                        $colorIndex++;

                                        $deleteBtn = '';
                                        $editBtn = '';
                                        if ($role === 'admin') {
                                            $deleteBtn = "<form action='api/schedule_actions.php' method='POST' class='d-inline' onsubmit=\"return confirm('ยืนยันลบตารางสอนนี้?');\">
                                            <input type='hidden' name='action' value='delete'>
                                            <input type='hidden' name='id' value='{$d['id']}'>
                                            <input type='hidden' name='redirect_class_id' value='{$filter_class_id}'>
                                            <input type='hidden' name='redirect_classroom_id' value='{$filter_classroom_id}'>
                                            <button type='submit' class='btn btn-sm p-0 text-danger' title='ลบ' style='font-size:0.7rem;'><i class='fas fa-times'></i></button>
                                        </form>";
                                            $editBtn = "<button type='button' class='btn btn-sm p-0 text-primary me-1' title='แก้ไข' style='font-size:0.7rem;' onclick='openEditSchedule(" . json_encode($d) . ")' data-bs-toggle='modal' data-bs-target='#editScheduleModal'><i class='fas fa-edit'></i></button>";
                                        }

                                        echo "<td class='align-middle p-1'>
                                        <div class='p-2 border rounded shadow-sm bg-white position-relative' style='border-left: 4px solid {$bg} !important;'>
                                            <div class='fw-bold text-dark mb-1' style='font-size:0.85rem;'>" . htmlspecialchars($d['subject_code']) . " " . htmlspecialchars($d['subject_name']) . "</div>
                                            <div class='text-muted mb-1' style='font-size:0.75rem;'><i class='fas fa-user-circle me-1'></i>" . htmlspecialchars($d['teacher_prefix'] . $d['teacher_fname'] . ' ' . $d['teacher_lname']) . "</div>
                                            <div class='text-muted' style='font-size:0.75rem;'><i class='fas fa-door-open me-1'></i>" . htmlspecialchars($d['room_name'] ?? '-') . "</div>";
                                        if ($role === 'admin') {
                                            echo "<div class='position-absolute top-0 end-0 p-1'>{$editBtn}{$deleteBtn}</div>";
                                        }
                                        echo "</div></td>";
                                        $found = true;
                                        break;
                                    }
                                }

                                if (!$found) {
                                    echo "<td class='align-middle'><div class='p-2' style='min-height:70px;'></div></td>";
                                }
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Schedule List Table with Pagination -->
    <?php if ($role === 'admin'): ?>
        <?php
        $listLimit = 5;
        $listPage = isset($_GET['spage']) && is_numeric($_GET['spage']) ? (int) $_GET['spage'] : 1;
        $listOffset = ($listPage - 1) * $listLimit;

        $listWhere = !empty($filter_class_id) ? "ts.class_id = :fid" : "ts.classroom_id = :fid";
        $listFid = !empty($filter_class_id) ? $filter_class_id : $filter_classroom_id;

        $countSql = "SELECT COUNT(*) FROM teaching_schedule ts WHERE " . $listWhere;
        $stmtC = $pdo->prepare($countSql);
        $stmtC->bindValue(':fid', $listFid, PDO::PARAM_INT);
        $stmtC->execute();
        $listTotal = $stmtC->fetchColumn();
        $listTotalPages = ceil($listTotal / $listLimit);

        $listSql = "SELECT ts.*, sub.subject_code, sub.name as subject_name, u.prefix as tprefix, u.first_name as tfname, u.last_name as tlname, cl.level_name, cr.room_name 
    FROM teaching_schedule ts 
    JOIN subjects sub ON ts.subject_id = sub.id 
    JOIN teachers t ON ts.teacher_id = t.id 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN classes cl ON ts.class_id = cl.id 
    LEFT JOIN classrooms cr ON ts.classroom_id = cr.id 
    WHERE " . $listWhere . " 
    ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), ts.start_time ASC 
    LIMIT :lim OFFSET :off";
        $stmtL = $pdo->prepare($listSql);
        $stmtL->bindValue(':fid', $listFid, PDO::PARAM_INT);
        $stmtL->bindValue(':lim', $listLimit, PDO::PARAM_INT);
        $stmtL->bindValue(':off', $listOffset, PDO::PARAM_INT);
        $stmtL->execute();
        $listItems = $stmtL->fetchAll();

        $daysTH = ['Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์', 'Sunday' => 'อาทิตย์'];
        ?>

        <div class="card shadow-sm-light mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>รายการตารางสอนทั้งหมด (<?= $listTotal ?>
                    รายการ)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="15%">วัน</th>
                                <th width="15%">เวลา</th>
                                <th width="20%">วิชา</th>
                                <th width="20%">ครูผู้สอน</th>
                                <th width="10%">ชั้น</th>
                                <th width="10%">ห้อง</th>
                                <th width="10%" class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($listItems) > 0): ?>
                                <?php foreach ($listItems as $item): ?>
                                    <tr>
                                        <td><?= $daysTH[$item['day_of_week']] ?? $item['day_of_week'] ?></td>
                                        <td><?= substr($item['start_time'], 0, 5) ?> - <?= substr($item['end_time'], 0, 5) ?></td>
                                        <td><span class="fw-medium"><?= htmlspecialchars($item['subject_code']) ?></span>
                                            <?= htmlspecialchars($item['subject_name']) ?></td>
                                        <td><?= htmlspecialchars($item['tprefix'] . $item['tfname'] . ' ' . $item['tlname']) ?></td>
                                        <td><span
                                                class="badge bg-light text-dark border"><?= htmlspecialchars($item['level_name'] ?? '-') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($item['room_name'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-light text-primary me-1"
                                                onclick='openEditSchedule(<?= json_encode($item) ?>)' data-bs-toggle="modal"
                                                data-bs-target="#editScheduleModal" title="แก้ไข"><i class="fas fa-edit"></i></button>
                                            <form action="api/schedule_actions.php" method="POST" class="d-inline"
                                                onsubmit="return confirm('ยืนยันลบ?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="redirect_class_id" value="<?= $filter_class_id ?>">
                                                <input type="hidden" name="redirect_classroom_id" value="<?= $filter_classroom_id ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="ลบ"><i
                                                        class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">ไม่มีข้อมูลตารางสอน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($listTotalPages > 1): ?>
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">หน้า <?= $listPage ?> / <?= $listTotalPages ?></small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            function buildSchedParams($p)
                            {
                                $q = $_GET;
                                $q['spage'] = $p;
                                return '?' . http_build_query($q);
                            }
                            ?>
                            <li class="page-item <?= ($listPage <= 1) ? 'disabled' : '' ?>"><a class="page-link"
                                    href="<?= ($listPage <= 1) ? '#' : buildSchedParams($listPage - 1) ?>">ก่อนหน้า</a></li>
                            <?php for ($i = 1; $i <= $listTotalPages; $i++): ?>
                                <li class="page-item <?= ($listPage == $i) ? 'active' : '' ?>"
                                    <?= ($listPage == $i) ? 'style="--bs-pagination-active-bg:var(--accent-color);--bs-pagination-active-border-color:var(--accent-color);"' : '' ?>><a class="page-link" href="<?= buildSchedParams($i) ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($listPage >= $listTotalPages) ? 'disabled' : '' ?>"><a class="page-link"
                                    href="<?= ($listPage >= $listTotalPages) ? '#' : buildSchedParams($listPage + 1) ?>">ถัดไป</a></li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php elseif ($role !== 'student'): ?>
    <div class="alert alert-info border-0 shadow-sm"><i
            class="fas fa-info-circle me-2"></i>กรุณาเลือกชั้นเรียนหรือห้องเรียนเพื่อแสดงตารางสอน</div>
<?php else: ?>
    <div class="alert alert-warning border-0 shadow-sm"><i
            class="fas fa-exclamation-triangle me-2"></i>ไม่พบข้อมูลตารางเรียนของคุณ กรุณาติดต่อผู้ดูแลระบบ</div>
<?php endif; ?>

<!-- Add Schedule Modal -->
<?php if ($role === 'admin'): ?>
    <div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">จัดตารางสอนใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="api/schedule_actions.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">ปีการศึกษา *</label>
                                <input type="text" name="academic_year" class="form-control bg-light" value="2567" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">ภาคเรียน *</label>
                                <select name="semester" class="form-select bg-light" required>
                                    <option value="1">ภาคเรียนที่ 1</option>
                                    <option value="2">ภาคเรียนที่ 2</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">วิชา *</label>
                            <select name="subject_id" class="form-select bg-light" required>
                                <option disabled selected value="">-- เลือกรายวิชา --</option>
                                <?php foreach ($allSubjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_code'] . ' - ' . $s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">ครูผู้สอน *</label>
                            <select name="teacher_id" class="form-select bg-light" required>
                                <option disabled selected value="">-- เลือกครูผู้สอน --</option>
                                <?php foreach ($allTeachers as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['teacher_code'] . ' - ' . $t['prefix'] . $t['first_name'] . ' ' . $t['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">สอนให้ชั้นเรียน *</label>
                                <select name="class_id" class="form-select bg-light" required>
                                    <option disabled selected value="">-- เลือกชั้นเรียน --</option>
                                    <?php foreach ($allClasses as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['level_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">ห้องเรียน (สถานที่สอน) *</label>
                                <select name="classroom_id" class="form-select bg-light" required>
                                    <option disabled selected value="">-- เลือกห้องเรียน --</option>
                                    <?php foreach ($allRooms as $r): ?>
                                        <option value="<?= $r['id'] ?>">
                                            <?= htmlspecialchars(($r['room_code'] ? $r['room_code'] . ' - ' : '') . $r['room_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted">วัน *</label>
                                <select name="day_of_week" class="form-select bg-light" required>
                                    <option value="Monday">จันทร์</option>
                                    <option value="Tuesday">อังคาร</option>
                                    <option value="Wednesday">พุธ</option>
                                    <option value="Thursday">พฤหัสบดี</option>
                                    <option value="Friday">ศุกร์</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted">เวลาเริ่ม *</label>
                                <select name="start_time" class="form-select bg-light" required>
                                    <option value="08:30:00">08:30 (คาบ 1)</option>
                                    <option value="10:10:00">10:10 (คาบ 3)</option>
                                    <option value="13:00:00">13:00 (คาบ 5)</option>
                                    <option value="14:40:00">14:40 (คาบ 7)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted">เวลาสิ้นสุด *</label>
                                <select name="end_time" class="form-select bg-light" required>
                                    <option value="10:10:00">10:10 (คาบ 2)</option>
                                    <option value="12:00:00">12:00 (คาบ 4)</option>
                                    <option value="14:40:00">14:40 (คาบ 6)</option>
                                    <option value="16:20:00">16:20 (คาบ 8)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary"
                            style="background-color: var(--accent-color); border:none;">บันทึกตารางสอน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">แก้ไขตารางสอน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="api/schedule_actions.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">
                    <input type="hidden" name="redirect_class_id" value="<?= $filter_class_id ?>">
                    <input type="hidden" name="redirect_classroom_id" value="<?= $filter_classroom_id ?>">
                    <div class="modal-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">ปีการศึกษา *</label>
                                <input type="text" name="academic_year" id="edit_academic_year"
                                    class="form-control bg-light" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">ภาคเรียน *</label>
                                <select name="semester" id="edit_semester" class="form-select bg-light" required>
                                    <option value="1">ภาคเรียนที่ 1</option>
                                    <option value="2">ภาคเรียนที่ 2</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">วิชา *</label>
                            <select name="subject_id" id="edit_subject_id" class="form-select bg-light" required>
                                <?php foreach ($allSubjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_code'] . ' - ' . $s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">ครูผู้สอน *</label>
                            <select name="teacher_id" id="edit_teacher_id" class="form-select bg-light" required>
                                <?php foreach ($allTeachers as $t): ?>
                                    <option value="<?= $t['id'] ?>">
                                        <?= htmlspecialchars($t['teacher_code'] . ' - ' . $t['prefix'] . $t['first_name'] . ' ' . $t['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">สอนให้ชั้นเรียน *</label>
                                <select name="class_id" id="edit_class_id" class="form-select bg-light" required>
                                    <?php foreach ($allClasses as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['level_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">ห้องเรียน (สถานที่สอน) *</label>
                                <select name="classroom_id" id="edit_classroom_id" class="form-select bg-light" required>
                                    <?php foreach ($allRooms as $r): ?>
                                        <option value="<?= $r['id'] ?>">
                                            <?= htmlspecialchars(($r['room_code'] ? $r['room_code'] . ' - ' : '') . $r['room_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted">วัน *</label>
                                <select name="day_of_week" id="edit_day_of_week" class="form-select bg-light" required>
                                    <option value="Monday">จันทร์</option>
                                    <option value="Tuesday">อังคาร</option>
                                    <option value="Wednesday">พุธ</option>
                                    <option value="Thursday">พฤหัสบดี</option>
                                    <option value="Friday">ศุกร์</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted">เวลาเริ่ม *</label>
                                <select name="start_time" id="edit_start_time" class="form-select bg-light" required>
                                    <option value="08:30:00">08:30 (คาบ 1)</option>
                                    <option value="10:10:00">10:10 (คาบ 3)</option>
                                    <option value="13:00:00">13:00 (คาบ 5)</option>
                                    <option value="14:40:00">14:40 (คาบ 7)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small text-muted">เวลาสิ้นสุด *</label>
                                <select name="end_time" id="edit_end_time" class="form-select bg-light" required>
                                    <option value="10:10:00">10:10 (คาบ 2)</option>
                                    <option value="12:00:00">12:00 (คาบ 4)</option>
                                    <option value="14:40:00">14:40 (คาบ 6)</option>
                                    <option value="16:20:00">16:20 (คาบ 8)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary"
                            style="background-color: var(--accent-color); border:none;">อัปเดตตารางสอน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    function openEditSchedule(s) {
        document.getElementById('edit_schedule_id').value = s.id;
        document.getElementById('edit_academic_year').value = s.academic_year;
        document.getElementById('edit_semester').value = s.semester;
        document.getElementById('edit_subject_id').value = s.subject_id;
        document.getElementById('edit_teacher_id').value = s.teacher_id;
        document.getElementById('edit_class_id').value = s.class_id || '';
        document.getElementById('edit_classroom_id').value = s.classroom_id;
        document.getElementById('edit_day_of_week').value = s.day_of_week;
        document.getElementById('edit_start_time').value = s.start_time;
        document.getElementById('edit_end_time').value = s.end_time;
    }

    // Auto-sync end_time with start_time
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[name="start_time"]').forEach(sel => {
            sel.addEventListener('change', function () {
                const endSel = this.closest('form').querySelector('select[name="end_time"]');
                if (endSel) endSel.selectedIndex = this.selectedIndex;
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>