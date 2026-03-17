<?php
// attendance.php - Attendance Tracking
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Default filters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_schedule_id = $_GET['schedule_id'] ?? '';

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

    // Fetch schedules that match this teacher AND this day of week
    $sqlSched = "
        SELECT ts.id as schedule_id, ts.start_time, ts.end_time,
               s.subject_code, s.name as subject_name, c.room_name, cl.level_name
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN classrooms c ON ts.classroom_id = c.id
        JOIN classes cl ON c.class_id = cl.id
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
            SELECT ts.*, s.subject_code, s.name as subject_name, c.room_name, cl.level_name, 
                   u.first_name as teacher_fname, u.last_name as teacher_lname
            FROM teaching_schedule ts
            JOIN subjects s ON ts.subject_id = s.id
            JOIN classrooms c ON ts.classroom_id = c.id
            JOIN classes cl ON c.class_id = cl.id
            JOIN teachers t ON ts.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE ts.id = ?
        ");
        $stmtSelected->execute([$filter_schedule_id]);
        $selectedSchedule = $stmtSelected->fetch();
        
        if ($selectedSchedule) {
            // Fetch students in this classroom
            $stmtStudents = $pdo->prepare("
                SELECT st.id as student_id, st.student_code, u.first_name, u.last_name, u.prefix
                FROM students st
                JOIN users u ON st.user_id = u.id
                WHERE st.classroom_id = ?
                ORDER BY st.student_code ASC
            ");
            $stmtStudents->execute([$selectedSchedule['classroom_id']]);
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">บันทึกเวลาเรียน</h3>
        <p class="text-muted mb-0">เช็คชื่อนักเรียนรายคาบเรียน</p>
    </div>
</div>

<div class="row">
    <!-- Filter Panel -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light border-0 py-2">
            <div class="card-body">
                <form class="row g-3 align-items-center" method="GET" action="attendance.php">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">วันที่</label>
                        <input type="date" name="date" class="form-control bg-light" value="<?= htmlspecialchars($filter_date) ?>" required onchange="this.form.schedule_id.value=''; this.form.submit();">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted mb-1">รายวิชาในวันที่เลือก (<?= htmlspecialchars($dayOfWeek) ?>)</label>
                        <select class="form-select bg-light" name="schedule_id" required>
                            <option value="">เลือกวิชาที่สอน...</option>
                            <?php foreach($schedulesDay as $sc): ?>
                                <?php 
                                    $timeStr = substr($sc['start_time'], 0, 5) . " - " . substr($sc['end_time'], 0, 5);
                                    $label = "{$sc['subject_code']} {$sc['level_name']}-{$sc['room_name']} ({$timeStr})";
                                    $sel = ($sc['schedule_id'] == $filter_schedule_id) ? 'selected' : '';
                                ?>
                                <option value="<?= $sc['schedule_id'] ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                         <label class="form-label small text-muted mb-1">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100 shadow-sm" style="background-color: var(--accent-color); border: none;">
                            ดึงรายชื่อ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light h-100">
            <?php if ($selectedSchedule): ?>
            <form id="attendanceForm" action="api/attendance_actions.php" method="POST">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="schedule_id" value="<?= $filter_schedule_id ?>">
                <input type="hidden" name="date" value="<?= $filter_date ?>">
                
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                         <h6 class="m-0 font-weight-bold text-dark">เช็คชื่อ: <?= htmlspecialchars($selectedSchedule['subject_code']) ?> <?= htmlspecialchars($selectedSchedule['subject_name']) ?> (<?= htmlspecialchars($selectedSchedule['room_name']) ?>)</h6>
                         <small class="text-muted">วันที่ <?= date('d/m/Y', strtotime($filter_date)) ?> | เวลา <?= substr($selectedSchedule['start_time'], 0, 5) ?> - <?= substr($selectedSchedule['end_time'], 0, 5) ?></small>
                    </div>
                    <div>
                        <!-- Action Bar to switch all to present -->
                        <button type="button" class="btn btn-sm btn-outline-success me-2" id="markAllPresent"><i class="fas fa-check-double me-1"></i>มาเรียนทั้งหมด</button>
                        <button type="submit" class="btn btn-sm btn-primary shadow-sm" style="background-color: var(--primary-color); border: none;"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="10%" class="text-center">เลขที่</th>
                                    <th width="15%">รหัสนักเรียน</th>
                                    <th width="30%">ชื่อ - นามสกุล</th>
                                    <th width="45%" class="text-center">สถานะการเข้าเรียน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php $i=1; foreach($students as $st): 
                                        $sid = $st['student_id'];
                                        $status = $existingAttendance[$sid] ?? 'present'; // Default to present
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($st['student_code']) ?></td>
                                        <td><?= htmlspecialchars($st['prefix'] . $st['first_name'] . ' ' . $st['last_name']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group attendance-btn-group" role="group" data-student-id="<?= $sid ?>">
                                                <input type="radio" class="btn-check" name="status[<?= $sid ?>]" id="att_<?= $sid ?>_present" value="present" <?= $status == 'present' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-success px-4" for="att_<?= $sid ?>_present">มาเรียน</label>

                                                <input type="radio" class="btn-check" name="status[<?= $sid ?>]" id="att_<?= $sid ?>_late" value="late" <?= $status == 'late' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-warning text-dark px-4" for="att_<?= $sid ?>_late">สาย</label>

                                                <input type="radio" class="btn-check" name="status[<?= $sid ?>]" id="att_<?= $sid ?>_leave" value="leave" <?= $status == 'leave' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-info text-dark px-4" for="att_<?= $sid ?>_leave">ลา</label>

                                                <input type="radio" class="btn-check" name="status[<?= $sid ?>]" id="att_<?= $sid ?>_absent" value="absent" <?= $status == 'absent' ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-danger px-4" for="att_<?= $sid ?>_absent">ขาด</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">ไม่พบข้อมูลนักเรียนในห้องนี้</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3 border-top">
                    <div class="row align-items-center">
                        <div class="col-md-6 d-flex gap-4" id="attendanceSummary">
                            <span class="text-success fw-bold"><i class="fas fa-circle me-1 small"></i> มาเรียน: <?= $counts['present'] ?></span>
                            <span class="text-warning fw-bold"><i class="fas fa-circle me-1 small"></i> สาย: <?= $counts['late'] ?></span>
                            <span class="text-info fw-bold"><i class="fas fa-circle me-1 small"></i> ลา: <?= $counts['leave'] ?></span>
                            <span class="text-danger fw-bold"><i class="fas fa-circle me-1 small"></i> ขาด: <?= $counts['absent'] ?></span>
                        </div>
                    </div>
                </div>
            </form>
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
    // Fetch last 14 days of attendance for this student
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
    
    // Calculate overall stats for displaying progress bars or badges
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
