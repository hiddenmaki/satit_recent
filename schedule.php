<?php
// schedule.php - จัดการตารางเรียนและตารางสอน (เวอร์ชันปรับปรุง)
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

// ดึงข้อมูลพื้นฐานจาก Session
$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// ตัวแปรสำหรับเก็บค่าการกรอง (Filter)
$filter_class_id = $_GET['class_id'] ?? '';
$filter_classroom_id = $_GET['classroom_id'] ?? '';

// ดึงข้อมูล Master Data สำหรับแสดงใน Dropdown (วิชา, ครู, ชั้นเรียน, ห้องเรียน)
$allSubjects = $pdo->query("SELECT id, subject_code, name FROM subjects ORDER BY subject_code ASC")->fetchAll();
$allTeachers = $pdo->query("SELECT t.id, t.teacher_code, u.prefix, u.first_name, u.last_name, d.name AS dept_name FROM teachers t JOIN users u ON t.user_id = u.id LEFT JOIN departments d ON t.department_id = d.id ORDER BY u.first_name ASC")->fetchAll();
$allClasses = $pdo->query("SELECT id, class_code, level_name FROM classes ORDER BY level_name ASC")->fetchAll();
$allRooms = $pdo->query("SELECT id, room_code, room_number, room_name FROM classrooms ORDER BY room_code ASC")->fetchAll();

// --- ส่วนกำหนดโลจิกการแสดงผลตามบทบาท (Role-based Logic) ---
$showSchedule = false;
$scheduleData = [];
$filterLabel = "";
$current_teacher_id = 0;

if ($role === 'student') {
    // ถ้านักเรียนเข้าดู: ให้ดึง class_id ของนักเรียนคนนั้นมาแสดงตารางของห้องตัวเองทันที
    $stmt = $pdo->prepare("SELECT class_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    if ($student && $student['class_id']) {
        $filter_class_id = $student['class_id'];
        $showSchedule = true;
    }
} else if ($role === 'teacher') {
    // ถ้าครูเข้าดู: ค้นหา teacher_id และตั้งค่าให้แสดง "ตารางสอนส่วนตัว" เป็นค่าเริ่มต้น
    $stmtT = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmtT->execute([$user_id]);
    $teacherData = $stmtT->fetch();
    $current_teacher_id = $teacherData ? $teacherData['id'] : 0;
    $showSchedule = true;
    $filterLabel = "ตารางสอนส่วนตัว";
} else if ($role === 'admin') {
    // ถ้า Admin เข้าดู: ต้องเลือกชั้นเรียนหรือห้องเรียนก่อนถึงจะแสดงตาราง
    if (!empty($filter_class_id) || !empty($filter_classroom_id)) {
        $showSchedule = true;
    }
}

// --- ส่วนการดึงข้อมูลตารางเรียน (Fetch Schedule Data) ---
if ($showSchedule) {
    $whereParts = [];
    $params = [];

    // ถ้าเป็นครู: บังคับให้กรองดึงเฉพาะวิชาที่ตัวเองสอน (เพื่อลดความสับสนตามที่คุณแจ้งมา)
    if ($role === 'teacher' && $current_teacher_id) {
        $whereParts[] = "ts.teacher_id = ?";
        $params[] = $current_teacher_id;
    }

    // กรองตามชั้นเรียน (ถ้ามีการเลือก)
    if (!empty($filter_class_id)) {
        $whereParts[] = "ts.class_id = ?";
        $params[] = $filter_class_id;
        
        $sl = $pdo->prepare("SELECT level_name FROM classes WHERE id = ?");
        $sl->execute([$filter_class_id]);
        $classLabel = $sl->fetchColumn() ?: '';
        $filterLabel = ($role === 'teacher' ? "ตารางสอนส่วนตัว - " : "") . $classLabel;
    } 
    // กรองตามห้องเรียน (สำหรับ Admin)
    elseif (!empty($filter_classroom_id)) {
        $whereParts[] = "ts.classroom_id = ?";
        $params[] = $filter_classroom_id;
        
        $sl = $pdo->prepare("SELECT room_name FROM classrooms WHERE id = ?");
        $sl->execute([$filter_classroom_id]);
        $roomLabel = $sl->fetchColumn() ?: '';
        $filterLabel = ($role === 'teacher' ? "ตารางสอนส่วนตัว - " : "") . $roomLabel;
    }

    // ตรวจสอบว่ามีเงื่อนไขการกรองหรือไม่ (ครูจะผ่านเงื่อนไขนี้ได้โดยไม่ต้องเลือกชั้นเรียน)
    if (count($whereParts) > 0) {
        $whereSql = "WHERE " . implode(" AND ", $whereParts);

        // คำสั่ง SQL หลักในการดึงข้อมูลตารางสอน พร้อม Join ตารางที่เกี่ยวข้อง
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
            {$whereSql}
            ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), ts.start_time ASC
        ");
        $stmt->execute($params);
        $scheduleDataRaw = $stmt->fetchAll();

        // นำข้อมูลที่ดึงมาได้ มาจัดกลุ่มรายวัน (Monday, Tuesday, ...) เพื่อให้ง่ายต่อการวน Loop แสดงผล
        foreach ($scheduleDataRaw as $row) {
            $day = $row['day_of_week'];
            if (!isset($scheduleData[$day]))
                $scheduleData[$day] = [];
            $scheduleData[$day][] = $row;
        }
    } else {
        $showSchedule = false; // ถ้าไม่มีเงื่อนไขใดๆ เลย ก็ไม่ต้องแสดงตาราง
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
                    class="fas fa-filter me-2"></i>เลือกดูตารางเรียนตามชั้นเรียน</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="schedule.php" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label small text-muted">ชั้นเรียน</label>
                    <select class="form-select bg-light" name="class_id" required>
                        <option value="">-- เลือกชั้นเรียน --</option>
                        <?php foreach ($allClasses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($filter_class_id == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['level_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary shadow-sm w-100"
                        style="background-color: var(--accent-color); border: none;">
                        <i class="fas fa-search me-2"></i>แสดงตาราง
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($showSchedule): ?>
    <div class="card shadow-sm-light border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>ตารางเรียน:
                <?= htmlspecialchars($filterLabel) ?></h6>
            
            <style>
                @media print {
                    @page { 
                        size: A4 landscape; 
                        margin: 5mm; 
                    }
                    /* Reset everything */
                    html, body, .wrapper, .main-panel, .content { 
                        margin: 0 !important; 
                        padding: 0 !important; 
                        display: block !important; 
                        width: 100% !important; 
                        height: auto !important;
                        min-height: 0 !important;
                    }
                    /* Hide Sidebar and other UI */
                    .sidebar, .topbar, .footer, .btn, .no-print, .breadcrumb, h3 { 
                        display: none !important; 
                    }
                    /* Container styling for print */
                    #printArea { 
                        display: block !important; 
                        width: 100% !important; 
                        margin: 0 !important;
                        padding: 0 !important;
                        background: transparent !important;
                    }
                    .card { border: none !important; box-shadow: none !important; }
                    .card-body { padding: 0 !important; }
                    /* Table styling to fit on a single page */
                    .table { 
                        width: 100% !important; 
                        table-layout: fixed !important; 
                        font-size: 8px !important; 
                        border-collapse: collapse !important;
                        border: 1px solid #000 !important;
                    }
                    .table th, .table td { 
                        padding: 1px !important; 
                        line-height: 1 !important;
                        border: 1px solid #333 !important;
                        height: auto !important;
                    }
                    .schedule-cell { 
                        min-height: 40px !important; 
                        padding: 1px !important; 
                        margin: 0 !important;
                        border-left-width: 1px !important; 
                        box-shadow: none !important;
                    }
                    .sc-code { font-size: 7.5px !important; }
                    .sc-name { font-size: 7px !important; }
                    .sc-teacher, .sc-room { font-size: 6.5px !important; }
                }
                .schedule-cell {
                    padding: 4px !important;
                    border-left: 3px solid #3498db !important;
                    min-height: 60px;
                    font-size: 0.7rem;
                    text-align: left;
                    background: #fff;
                    border-radius: 4px;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                }
                .schedule-cell .sc-code { font-weight: 700; color: #2c3e50; font-size: 0.72rem; }
                .schedule-cell .sc-name { color: #555; font-size: 0.65rem; }
                .schedule-cell .sc-teacher { color: #888; font-size: 0.62rem; }
                .schedule-cell .sc-room { color: #3498db; font-size: 0.62rem; }
            </style>
            
            <button class="btn btn-sm btn-outline-primary no-print" onclick="window.print()"><i
                    class="fas fa-print me-1"></i>พิมพ์</button>
        </div>
        <div class="card-body p-0" id="printArea">
            <!-- Print Header -->
            <div class="d-none d-print-block text-center mb-3">
                <h4 class="fw-bold mb-1">ตารางเรียน / ตารางสอน - โรงเรียนสาธิตวิทยา</h4>
                <p class="mb-0">ระดับชั้น/ครูผู้สอน: <?= htmlspecialchars($filterLabel) ?> | ปีการศึกษา 2567 เทอม 1</p>
            </div>
            <div class="table-responsive text-center">
                <table class="table table-bordered mb-0" style="table-layout:fixed; font-size:0.8rem;">
                    <thead>
                        <tr class="bg-light">
                            <th width="6%" class="align-middle py-2" style="font-size:0.75rem;">วัน</th>
                            <th width="10.5%">08:30-09:20<br><small class="text-muted">คาบ 1</small></th>
                            <th width="10.5%">09:20-10:10<br><small class="text-muted">คาบ 2</small></th>
                            <th width="10.5%">10:20-11:10<br><small class="text-muted">คาบ 3</small></th>
                            <th width="10.5%">11:10-12:00<br><small class="text-muted">คาบ 4</small></th>
                            <th width="5%" class="bg-warning bg-opacity-10">พัก<br>🍽</th>
                            <th width="10.5%">13:00-13:50<br><small class="text-muted">คาบ 5</small></th>
                            <th width="10.5%">13:50-14:40<br><small class="text-muted">คาบ 6</small></th>
                            <th width="10.5%">14:50-15:40<br><small class="text-muted">คาบ 7</small></th>
                            <th width="10.5%">15:40-16:30<br><small class="text-muted">คาบ 8</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $days = ['Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์'];
                        $cellColors = ['#3498db', '#8e44ad', '#e67e22', '#27ae60', '#e74c3c', '#16a085', '#2c3e50', '#f39c12'];
                        $cIdx = 0;

                        // กำหนดเวลาเริ่มและเวลาเลิกของแต่ละคาบ (คาบ 1-8)
                        $periodStarts = ['08:30:00', '09:20:00', '10:20:00', '11:10:00', '13:00:00', '13:50:00', '14:50:00', '15:40:00'];
                        $periodEnds   = ['09:20:00', '10:10:00', '11:10:00', '12:00:00', '13:50:00', '14:40:00', '15:40:00', '16:30:00'];

                        // วนลูปแสดงผลรายวัน (จันทร์-ศุกร์)
                        foreach ($days as $engDay => $thaiDay) {
                            echo "<tr>";
                            echo "<td class='align-middle fw-bold bg-light text-center py-2' style='font-size:0.8rem;'>{$thaiDay}</td>";

                            $dayData = $scheduleData[$engDay] ?? [];

                            // นำข้อมูลวิชาในวันนั้นๆ มาจับคู่กับเลขคาบ (Period Mapping)
                            $periodMap = [];
                            $periodSpan = [];
                            foreach ($dayData as $d) {
                                $si = array_search($d['start_time'], $periodStarts); // หาว่าเริ่มคาบไหน
                                $ei = array_search($d['end_time'], $periodEnds);     // หาว่าจบคาบไหน
                                if ($si !== false && $ei !== false) {
                                    $periodMap[$si] = $d;
                                    $periodSpan[$si] = $ei - $si + 1; // คำนวณว่าเรียนกี่คาบติดกัน (Colspan)
                                    for ($k = $si + 1; $k <= $ei; $k++) $periodMap[$k] = 'skip'; // ถ้าเรียนยาว ให้ข้ามช่องถัดไป
                                }
                            }

                            for ($p = 0; $p < 8; $p++) {
                                // Lunch column after period 4
                                if ($p == 4) {
                                    if ($engDay == 'Monday') {
                                        echo "<td class='align-middle bg-warning bg-opacity-10 text-muted text-center' rowspan='5' style='font-size:0.7rem;'>พักกลางวัน<br>12:00-13:00</td>";
                                    }
                                }

                                if (isset($periodMap[$p])) {
                                    if ($periodMap[$p] === 'skip') continue;
                                    $d = $periodMap[$p];
                                    $span = $periodSpan[$p] ?? 1;
                                    $bg = $cellColors[$cIdx % count($cellColors)];
                                    $cIdx++;
                                    $colAttr = $span > 1 ? " colspan='{$span}'" : "";

                                    $adminBtns = '';
                                    if ($role === 'admin') {
                                        $adminBtns = "<div class='position-absolute top-0 end-0 p-1 no-print'>
                                            <button type='button' class='btn btn-sm p-0 text-primary me-1' title='แก้ไข' style='font-size:0.6rem;' onclick='openEditSchedule(" . json_encode($d) . ")' data-bs-toggle='modal' data-bs-target='#editScheduleModal'><i class='fas fa-edit'></i></button>
                                            <form action='api/schedule_actions.php' method='POST' class='d-inline' onsubmit=\"return confirm('ลบ?');\">
                                                <input type='hidden' name='action' value='delete'>
                                                <input type='hidden' name='id' value='{$d['id']}'>
                                                <input type='hidden' name='redirect_class_id' value='{$filter_class_id}'>
                                                <input type='hidden' name='redirect_classroom_id' value='{$filter_classroom_id}'>
                                                <button type='submit' class='btn btn-sm p-0 text-danger' style='font-size:0.6rem;'><i class='fas fa-times'></i></button>
                                            </form>
                                        </div>";
                                    }

                                    echo "<td class='align-middle p-1'{$colAttr}>
                                        <div class='schedule-cell position-relative' style='border-left-color:{$bg} !important;'>
                                            <div class='sc-code'>" . htmlspecialchars($d['subject_code']) . "</div>
                                            <div class='sc-name'>" . htmlspecialchars($d['subject_name']) . "</div>
                                            <div class='sc-teacher'><i class='fas fa-user me-1'></i>" . htmlspecialchars($d['teacher_prefix'] . $d['teacher_fname'] . ' ' . $d['teacher_lname']) . "</div>
                                            <div class='sc-room'><i class='fas fa-door-open me-1'></i>" . htmlspecialchars($d['room_name'] ?? '-') . "</div>
                                            {$adminBtns}
                                        </div>
                                    </td>";
                                } else {
                                    echo "<td class='align-middle p-1'><div style='min-height:60px;background:#f8f9fa;border-radius:4px;'></div></td>";
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
    <?php if ($role === 'admin' || $role === 'teacher'): ?>
        <?php
        $listLimit = 10; // More items for teachers
        $listPage = isset($_GET['spage']) && is_numeric($_GET['spage']) ? (int) $_GET['spage'] : 1;
        $listOffset = ($listPage - 1) * $listLimit;

        $listWhereParts = [];
        $listParams = [];

        if ($role === 'teacher' && $current_teacher_id) {
            $listWhereParts[] = "ts.teacher_id = :tid";
            $listParams['tid'] = $current_teacher_id;
        }

        if (!empty($filter_class_id)) {
            $listWhereParts[] = "ts.class_id = :fid";
            $listParams['fid'] = $filter_class_id;
        } elseif (!empty($filter_classroom_id)) {
            $listWhereParts[] = "ts.classroom_id = :fid";
            $listParams['fid'] = $filter_classroom_id;
        }

        $listWhereSql = count($listWhereParts) > 0 ? "WHERE " . implode(" AND ", $listWhereParts) : "";

        $countSql = "SELECT COUNT(*) FROM teaching_schedule ts " . $listWhereSql;
        $stmtC = $pdo->prepare($countSql);
        foreach ($listParams as $k => $v) $stmtC->bindValue(":{$k}", $v, is_numeric($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
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
            " . $listWhereSql . " 
            ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday'), ts.start_time ASC 
            LIMIT :lim OFFSET :off";
        $stmtL = $pdo->prepare($listSql);
        foreach ($listParams as $k => $v) $stmtL->bindValue(":{$k}", $v, is_numeric($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmtL->bindValue(':lim', $listLimit, PDO::PARAM_INT);
        $stmtL->bindValue(':off', $listOffset, PDO::PARAM_INT);
        $stmtL->execute();
        $listItems = $stmtL->fetchAll();

        $daysTH = ['Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์', 'Saturday' => 'เสาร์', 'Sunday' => 'อาทิตย์'];
        ?>

        <div class="card shadow-sm-light mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="m-0 fw-bold text-primary"><i class="fas fa-list me-2"></i><?= $role === 'teacher' ? 'รายการสอนของคุณ' : 'รายการตารางสอนทั้งหมด' ?> (<?= $listTotal ?> รายการ)</h6>
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
                                            <?php if ($role === 'admin'): ?>
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
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
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
                                        <?= htmlspecialchars($t['teacher_code'] . ' - ' . $t['prefix'] . $t['first_name'] . ' ' . $t['last_name'] . (!empty($t['dept_name']) ? ' (' . $t['dept_name'] . ')' : '')) ?>
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
                                        <?= htmlspecialchars($t['teacher_code'] . ' - ' . $t['prefix'] . $t['first_name'] . ' ' . $t['last_name'] . (!empty($t['dept_name']) ? ' (' . $t['dept_name'] . ')' : '')) ?>
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