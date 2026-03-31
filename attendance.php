<?php
// attendance.php - Attendance Tracking (AJAX Toggle)
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Default filters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_schedule_id = $_GET['schedule_id'] ?? '';

// Thai day names mapping
$thaiDays = [
    'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
    'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์'
];

// Handling Teacher/Admin View
if ($role === 'admin' || $role === 'teacher') {
    $teacher_id = 0;
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        if ($teacher) $teacher_id = $teacher['id'];
    }

    // Determine Day of week from filter_date
    $dayOfWeek = date('l', strtotime($filter_date));
    $thaiDay = $thaiDays[$dayOfWeek] ?? $dayOfWeek;

    // Fetch schedules that match this teacher AND this day of week
    // ใช้ ts.class_id เพื่อดึงชั้นเรียนที่สอนจากตารางสอนโดยตรง
    $sqlSched = "
        SELECT ts.id as schedule_id, ts.start_time, ts.end_time, ts.class_id,
               s.subject_code, s.name as subject_name, 
               cl.level_name,
               cr.room_name
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        LEFT JOIN classes cl ON ts.class_id = cl.id
        LEFT JOIN classrooms cr ON ts.classroom_id = cr.id
        WHERE ts.day_of_week = :dow
    ";
    
    if ($role === 'teacher') {
        $sqlSched .= " AND ts.teacher_id = :tid";
    }
    $sqlSched .= " ORDER BY ts.start_time ASC";
    
    $stmtSched = $pdo->prepare($sqlSched);
    $stmtSched->bindParam(':dow', $dayOfWeek);
    if ($role === 'teacher') $stmtSched->bindParam(':tid', $teacher_id);
    $stmtSched->execute();
    $schedulesDay = $stmtSched->fetchAll();

    $selectedSchedule = null;
    $students = [];
    $existingAttendance = [];
    
    if ($filter_schedule_id) {
        $stmtSelected = $pdo->prepare("
            SELECT ts.*, s.subject_code, s.name as subject_name, 
                   cl.level_name, cr.room_name,
                   u.prefix as teacher_prefix, u.first_name as teacher_fname, u.last_name as teacher_lname
            FROM teaching_schedule ts
            JOIN subjects s ON ts.subject_id = s.id
            LEFT JOIN classes cl ON ts.class_id = cl.id
            LEFT JOIN classrooms cr ON ts.classroom_id = cr.id
            JOIN teachers t ON ts.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE ts.id = ?
        ");
        $stmtSelected->execute([$filter_schedule_id]);
        $selectedSchedule = $stmtSelected->fetch();
        
        if ($selectedSchedule) {
            // ดึงนักเรียนตาม class_id ของตารางสอน (ชั้นเรียนที่สอน)
            $stmtStudents = $pdo->prepare("
                SELECT st.id as student_id, st.student_code, u.prefix, u.first_name, u.last_name
                FROM students st
                JOIN users u ON st.user_id = u.id
                WHERE st.class_id = ?
                ORDER BY st.student_code ASC
            ");
            $stmtStudents->execute([$selectedSchedule['class_id']]);
            $students = $stmtStudents->fetchAll();
            
            // Fetch existing attendance for this date and schedule
            $stmtAtt = $pdo->prepare("SELECT student_id, status FROM attendance_log WHERE schedule_id = ? AND date = ?");
            $stmtAtt->execute([$filter_schedule_id, $filter_date]);
            $attRecords = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
            foreach($attRecords as $ar) {
                $existingAttendance[$ar['student_id']] = $ar['status'];
            }
        }
    }
    
    // Summary
    $counts = ['present' => 0, 'late' => 0, 'leave' => 0, 'absent' => 0];
    foreach($students as $st) {
        $status = $existingAttendance[$st['student_id']] ?? 'present';
        $counts[$status]++;
    }
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">บันทึกเวลาเรียน</h3>
        <p class="text-muted mb-0">เช็คชื่อนักเรียนรายคาบเรียน</p>
    </div>
</div>

<!-- Status Alert -->
<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'บันทึกข้อมูลเรียบร้อยแล้ว') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'เกิดข้อผิดพลาด') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Toast notification for AJAX -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="ajaxToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2000">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">
                <i class="fas fa-check-circle me-1"></i> บันทึกสำเร็จ
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<div class="row">
    <!-- Filter Panel -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light border-0 py-2">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET" action="attendance.php">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">วันที่</label>
                        <input type="date" name="date" class="form-control bg-light" value="<?= htmlspecialchars($filter_date) ?>" required onchange="this.form.schedule_id.value=''; this.form.submit();">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted mb-1">
                            รายวิชาในวัน<?= $thaiDay ?> 
                            <span class="badge bg-light text-dark border"><?= count($schedulesDay) ?> คาบ</span>
                        </label>
                        <select class="form-select bg-light" name="schedule_id" required>
                            <option value="">-- เลือกวิชาที่สอน --</option>
                            <?php foreach($schedulesDay as $sc): ?>
                                <?php 
                                    $timeStr = substr($sc['start_time'], 0, 5) . " - " . substr($sc['end_time'], 0, 5);
                                    $label = "{$sc['subject_code']} {$sc['subject_name']} | {$sc['level_name']} ({$timeStr})";
                                    $sel = ($sc['schedule_id'] == $filter_schedule_id) ? 'selected' : '';
                                ?>
                                <option value="<?= $sc['schedule_id'] ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 shadow-sm" style="background-color: var(--accent-color); border: none;">
                            <i class="fas fa-search me-1"></i>ดึงรายชื่อ
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="attendance.php?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-calendar-day me-1"></i>วันนี้
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light h-100">
            <?php if ($selectedSchedule): ?>
                <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                         <h6 class="m-0 fw-bold text-dark">
                            <i class="fas fa-clipboard-check me-2 text-primary"></i>
                            เช็คชื่อ: <?= htmlspecialchars($selectedSchedule['subject_code']) ?> <?= htmlspecialchars($selectedSchedule['subject_name']) ?>
                         </h6>
                         <small class="text-muted">
                            ชั้น <?= htmlspecialchars($selectedSchedule['level_name'] ?? '-') ?> |
                            ห้อง <?= htmlspecialchars($selectedSchedule['room_name'] ?? '-') ?> |
                            วันที่ <?= date('d/m/Y', strtotime($filter_date)) ?> (<?= $thaiDay ?>) |
                            เวลา <?= substr($selectedSchedule['start_time'], 0, 5) ?> - <?= substr($selectedSchedule['end_time'], 0, 5) ?> |
                            ครู <?= htmlspecialchars(($selectedSchedule['teacher_prefix'] ?? '') . $selectedSchedule['teacher_fname'] . ' ' . $selectedSchedule['teacher_lname']) ?>
                         </small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success" id="markAllPresent">
                            <i class="fas fa-check-double me-1"></i>มาเรียนทั้งหมด
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="markAllAbsent">
                            <i class="fas fa-times me-1"></i>ขาดทั้งหมด
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="8%" class="text-center">เลขที่</th>
                                    <th width="14%">รหัสนักเรียน</th>
                                    <th width="28%">ชื่อ - นามสกุล</th>
                                    <th width="50%" class="text-center">
                                        สถานะการเข้าเรียน 
                                        <small class="text-muted fw-normal">(กดเปลี่ยนสถานะได้ทันที)</small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php $i=1; foreach($students as $st): 
                                        $sid = $st['student_id'];
                                        $currentStatus = $existingAttendance[$sid] ?? 'present';
                                    ?>
                                    <tr data-student-id="<?= $sid ?>">
                                        <td class="text-center fw-medium"><?= $i++ ?></td>
                                        <td><span class="fw-medium text-dark"><?= htmlspecialchars($st['student_code']) ?></span></td>
                                        <td><?= htmlspecialchars($st['prefix'] . $st['first_name'] . ' ' . $st['last_name']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group attendance-toggle" role="group">
                                                <button type="button" class="btn btn-sm att-btn <?= $currentStatus == 'present' ? 'btn-success' : 'btn-outline-success' ?>" 
                                                        data-status="present" data-sid="<?= $sid ?>">
                                                    <i class="fas fa-check me-1"></i>มาเรียน
                                                </button>
                                                <button type="button" class="btn btn-sm att-btn <?= $currentStatus == 'late' ? 'btn-warning text-dark' : 'btn-outline-warning text-dark' ?>" 
                                                        data-status="late" data-sid="<?= $sid ?>">
                                                    <i class="fas fa-clock me-1"></i>สาย
                                                </button>
                                                <button type="button" class="btn btn-sm att-btn <?= $currentStatus == 'leave' ? 'btn-info text-white' : 'btn-outline-info text-dark' ?>" 
                                                        data-status="leave" data-sid="<?= $sid ?>">
                                                    <i class="fas fa-envelope me-1"></i>ลา
                                                </button>
                                                <button type="button" class="btn btn-sm att-btn <?= $currentStatus == 'absent' ? 'btn-danger' : 'btn-outline-danger' ?>" 
                                                        data-status="absent" data-sid="<?= $sid ?>">
                                                    <i class="fas fa-times me-1"></i>ขาด
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-users" style="font-size:2.5rem;"></i>
                                                <p class="mt-2 mb-0">ไม่พบนักเรียนในชั้นเรียนนี้</p>
                                                <small>กรุณาตรวจสอบว่ามีนักเรียนในชั้น "<?= htmlspecialchars($selectedSchedule['level_name'] ?? '-') ?>"</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if (count($students) > 0): ?>
                <div class="card-footer bg-white py-3 border-top">
                    <div class="d-flex flex-wrap gap-4 justify-content-center" id="attendanceSummary">
                        <span class="d-flex align-items-center gap-1">
                            <span class="badge bg-success rounded-circle p-1" style="width:12px;height:12px;"></span>
                            <span class="fw-bold text-success">มาเรียน: <span id="countPresent"><?= $counts['present'] ?></span></span>
                        </span>
                        <span class="d-flex align-items-center gap-1">
                            <span class="badge bg-warning rounded-circle p-1" style="width:12px;height:12px;"></span>
                            <span class="fw-bold text-warning">สาย: <span id="countLate"><?= $counts['late'] ?></span></span>
                        </span>
                        <span class="d-flex align-items-center gap-1">
                            <span class="badge bg-info rounded-circle p-1" style="width:12px;height:12px;"></span>
                            <span class="fw-bold text-info">ลา: <span id="countLeave"><?= $counts['leave'] ?></span></span>
                        </span>
                        <span class="d-flex align-items-center gap-1">
                            <span class="badge bg-danger rounded-circle p-1" style="width:12px;height:12px;"></span>
                            <span class="fw-bold text-danger">ขาด: <span id="countAbsent"><?= $counts['absent'] ?></span></span>
                        </span>
                        <span class="text-muted">| รวม: <?= count($students) ?> คน</span>
                    </div>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="card-body text-center py-5">
                    <div class="mb-3 text-muted" style="font-size: 3rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">กรุณาเลือกรายวิชาที่ต้องการเช็คชื่อ</h5>
                    <p class="text-muted mb-0">เลือกวันที่และรายวิชาด้านบนเพื่อดึงรายชื่อนักเรียน</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php 
} else { 
?>
<!-- Student View -->
<?php
$stmtStu = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmtStu->execute([$user_id]);
$stu = $stmtStu->fetch();
$student_id = $stu ? $stu['id'] : 0;

$attendanceRecords = [];
$totalClasses = 0;
$presentCount = 0;
$absentCount = 0;
$lateCount = 0;
$leaveCount = 0;

if ($student_id) {
    // Fetch last 30 records of attendance for this student
    $stmtAtt = $pdo->prepare("
        SELECT al.*, ts.start_time, ts.end_time, s.subject_code, s.name as subject_name,
               u.first_name as teacher_fname, u.last_name as teacher_lname
        FROM attendance_log al
        JOIN teaching_schedule ts ON al.schedule_id = ts.id
        JOIN subjects s ON ts.subject_id = s.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE al.student_id = ?
        ORDER BY al.date DESC, ts.start_time DESC
        LIMIT 30
    ");
    $stmtAtt->execute([$student_id]);
    $attendanceRecords = $stmtAtt->fetchAll();
    
    // Calculate overall stats
    $stmtStats = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance_log WHERE student_id = ? GROUP BY status");
    $stmtStats->execute([$student_id]);
    $stats = $stmtStats->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $presentCount = ($stats['present'] ?? 0);
    $lateCount = ($stats['late'] ?? 0);
    $leaveCount = ($stats['leave'] ?? 0);
    $absentCount = ($stats['absent'] ?? 0);
    
    $totalClasses = $presentCount + $lateCount + $leaveCount + $absentCount;
    $presentPercentage = $totalClasses > 0 ? round((($presentCount + $lateCount) / $totalClasses) * 100) : 0;
    $absentPercentage = $totalClasses > 0 ? round(($absentCount / $totalClasses) * 100) : 0;
}
?>
<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                     <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-calendar-check me-2"></i>ประวัติการมาเรียนของฉัน</h6>
                     <small class="text-muted">ข้อมูล 30 คาบล่าสุด</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success shadow-sm px-3 py-2">เข้าเรียน: <?= $presentPercentage ?>%</span>
                    <span class="badge bg-danger shadow-sm px-3 py-2">ขาด: <?= $absentPercentage ?>%</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="20%">วันที่ / เวลา</th>
                                <th width="35%">วิชา</th>
                                <th width="25%">ครูผู้สอน</th>
                                <th width="20%" class="text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendanceRecords) > 0): ?>
                                <?php foreach($attendanceRecords as $ar): 
                                    $dateStr = date('d/m/Y', strtotime($ar['date']));
                                    $timeStr = substr($ar['start_time'], 0, 5) . ' น.';
                                    
                                    $statusBadge = '';
                                    if ($ar['status'] === 'present') {
                                        $statusBadge = '<span class="badge bg-success fs-6 px-3 py-1 rounded w-75 shadow-sm border">มาเรียน</span>';
                                    } elseif ($ar['status'] === 'late') {
                                        $statusBadge = '<span class="badge bg-warning text-dark fs-6 px-3 py-1 rounded w-75 shadow-sm border">มาสาย</span>';
                                    } elseif ($ar['status'] === 'leave') {
                                        $statusBadge = '<span class="badge bg-info text-dark fs-6 px-3 py-1 rounded w-75 shadow-sm border">ลา</span>';
                                    } else {
                                        $statusBadge = '<span class="badge bg-danger fs-6 px-3 py-1 rounded w-75 shadow-sm border">ขาดเรียน</span>';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($dateStr) ?><br><small class="text-muted"><?= htmlspecialchars($timeStr) ?></small></td>
                                    <td><span class="fw-medium"><?= htmlspecialchars($ar['subject_code'] . ' ' . $ar['subject_name']) ?></span></td>
                                    <td>ท.<?= htmlspecialchars($ar['teacher_fname'] . ' ' . $ar['teacher_lname']) ?></td>
                                    <td class="text-center"><?= $statusBadge ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">ยังไม่มีข้อมูลการเข้าเรียน</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php include 'includes/footer.php'; ?>

<?php // === AJAX Script MUST be AFTER footer.php because jQuery loads there === ?>
<?php if (($role === 'admin' || $role === 'teacher') && isset($selectedSchedule) && $selectedSchedule): ?>
<script>
$(document).ready(function() {
    const scheduleId = '<?= $filter_schedule_id ?>';
    const dateVal = '<?= $filter_date ?>';
    
    // กำหนด CSS class สำหรับแต่ละสถานะ (active = ถูกเลือก, inactive = ไม่ถูกเลือก)
    const statusStyles = {
        present: { active: 'btn-success', inactive: 'btn-outline-success' },
        late:    { active: 'btn-warning text-dark', inactive: 'btn-outline-warning text-dark' },
        leave:   { active: 'btn-info text-white', inactive: 'btn-outline-info text-dark' },
        absent:  { active: 'btn-danger', inactive: 'btn-outline-danger' }
    };

    // ฟังก์ชันอัปเดตตัวนับสรุปสถานะด้านล่าง
    function updateSummary() {
        let counts = { present: 0, late: 0, leave: 0, absent: 0 };
        $('tr[data-student-id]').each(function() {
            let activeBtn = $(this).find('.att-btn').filter(function() {
                let s = $(this).data('status');
                return $(this).hasClass(statusStyles[s].active.split(' ')[0]);
            });
            if (activeBtn.length) {
                counts[activeBtn.first().data('status')]++;
            }
        });
        $('#countPresent').text(counts.present);
        $('#countLate').text(counts.late);
        $('#countLeave').text(counts.leave);
        $('#countAbsent').text(counts.absent);
    }

    // ฟังก์ชันเปลี่ยนสถานะ + ส่ง AJAX ไปบันทึกทันที
    function setStatus(btn, showToast) {
        if (typeof showToast === 'undefined') showToast = true;
        const $btn = $(btn);
        const studentId = $btn.data('sid');
        const newStatus = $btn.data('status');
        const $group = $btn.closest('.attendance-toggle');
        
        // 1. อัปเดต UI ทันที (ไม่ต้องรอ server ตอบ)
        $group.find('.att-btn').each(function() {
            let s = $(this).data('status');
            let activeClasses = statusStyles[s].active.split(' ');
            let inactiveClasses = statusStyles[s].inactive.split(' ');
            $(this).removeClass(activeClasses.join(' ')).addClass(inactiveClasses.join(' '));
        });
        let newActiveClasses = statusStyles[newStatus].active.split(' ');
        let newInactiveClasses = statusStyles[newStatus].inactive.split(' ');
        $btn.removeClass(newInactiveClasses.join(' ')).addClass(newActiveClasses.join(' '));
        
        // 2. อัปเดตสรุป
        updateSummary();
        
        // 3. ส่ง AJAX ไปบันทึกลงฐานข้อมูล
        $.ajax({
            url: 'api/attendance_actions.php',
            method: 'POST',
            data: {
                action: 'toggle_status',
                schedule_id: scheduleId,
                student_id: studentId,
                date: dateVal,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && showToast) {
                    showNotification('บันทึก: ' + response.student_name + ' → ' + response.status_label, 'success');
                } else if (!response.success) {
                    showNotification('เกิดข้อผิดพลาด: ' + (response.message || ''), 'error');
                }
            },
            error: function() {
                showNotification('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            }
        });
    }
    
    // ฟังก์ชันแสดง Toast notification
    function showNotification(msg, type) {
        const $toast = $('#ajaxToast');
        $toast.removeClass('bg-success bg-danger').addClass(type === 'success' ? 'bg-success' : 'bg-danger');
        $('#toastMessage').html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-1"></i> ' + msg);
        var toast = new bootstrap.Toast($toast[0]);
        toast.show();
    }

    // Event: คลิกปุ่มสถานะแต่ละคน
    $(document).on('click', '.att-btn', function() {
        setStatus(this, true);
    });

    // Event: มาเรียนทั้งหมด
    $('#markAllPresent').click(function() {
        $('tr[data-student-id]').each(function(index) {
            var presBtn = $(this).find('.att-btn[data-status="present"]');
            setTimeout(function() { setStatus(presBtn[0], false); }, index * 80);
        });
        setTimeout(function() { showNotification('ตั้งค่า "มาเรียน" ทั้งหมดแล้ว', 'success'); }, 300);
    });

    // Event: ขาดทั้งหมด
    $('#markAllAbsent').click(function() {
        if (!confirm('ยืนยันตั้งค่า "ขาดเรียน" ให้นักเรียนทั้งหมด?')) return;
        $('tr[data-student-id]').each(function(index) {
            var absBtn = $(this).find('.att-btn[data-status="absent"]');
            setTimeout(function() { setStatus(absBtn[0], false); }, index * 80);
        });
        setTimeout(function() { showNotification('ตั้งค่า "ขาดเรียน" ทั้งหมดแล้ว', 'success'); }, 300);
    });
});
</script>
<?php endif; ?>
