<?php
// index.php - Dashboard (Real-time Data)
require_once 'includes/auth.php';
include 'includes/header.php';
require_once 'includes/db.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// ========== Admin/Teacher Data ==========
if ($role === 'admin' || $role === 'teacher') {
    // Basic counts
    $num_teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $num_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $num_classes = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $num_subjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();

    $teacher_id = 0;
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        if ($teacher) $teacher_id = $teacher['id'];
    }

    // Today's schedule
    $dayOfWeek = date('l');
    $thaiDays = [
        'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
        'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์'
    ];
    $thaiDay = $thaiDays[$dayOfWeek] ?? $dayOfWeek;

    $sqlSched = "
        SELECT ts.start_time, ts.end_time, s.subject_code, s.name as subject_name,
               cr.room_name, cl.level_name, u.first_name as t_fname, u.last_name as t_lname
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        LEFT JOIN classrooms cr ON ts.classroom_id = cr.id
        LEFT JOIN classes cl ON ts.class_id = cl.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ts.day_of_week = :dow
    ";
    if ($role === 'teacher') {
        $sqlSched .= " AND ts.teacher_id = :tid";
    }
    $sqlSched .= " ORDER BY ts.start_time ASC LIMIT 8";
    $stmtSched = $pdo->prepare($sqlSched);
    $stmtSched->bindParam(':dow', $dayOfWeek);
    if ($role === 'teacher') $stmtSched->bindParam(':tid', $teacher_id);
    $stmtSched->execute();
    $todaySchedules = $stmtSched->fetchAll();

    // Recent Activity (last 8 attendance records)
    $stmtActivity = $pdo->query("
        SELECT al.date, al.status, 
               u.first_name, u.last_name,
               s.subject_code
        FROM attendance_log al
        JOIN students st ON al.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN teaching_schedule ts ON al.schedule_id = ts.id
        JOIN subjects s ON ts.subject_id = s.id
        ORDER BY al.id DESC
        LIMIT 8
    ");
    $recentActivities = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);

} else if ($role === 'student') {
    // Student data
    $stmtStu = $pdo->prepare("
        SELECT st.id, st.class_id, cl.level_name
        FROM students st 
        LEFT JOIN classes cl ON st.class_id = cl.id 
        WHERE st.user_id = ?
    ");
    $stmtStu->execute([$user_id]);
    $stu = $stmtStu->fetch();
    $student_id = $stu ? $stu['id'] : 0;

    $dayOfWeek = date('l');
    $thaiDays = [
        'Sunday'=>'อาทิตย์','Monday'=>'จันทร์','Tuesday'=>'อังคาร',
        'Wednesday'=>'พุธ','Thursday'=>'พฤหัสบดี','Friday'=>'ศุกร์','Saturday'=>'เสาร์'
    ];
    $thaiDay = $thaiDays[$dayOfWeek] ?? $dayOfWeek;

    // Today's schedule for student's class
    $stmtSched = $pdo->prepare("
        SELECT ts.start_time, ts.end_time, s.subject_code, s.name as subject_name,
               cr.room_name, cl.level_name, u.first_name as t_fname, u.last_name as t_lname
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        LEFT JOIN classrooms cr ON ts.classroom_id = cr.id
        LEFT JOIN classes cl ON ts.class_id = cl.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ts.day_of_week = ? AND ts.class_id = ?
        ORDER BY ts.start_time ASC
    ");
    $stmtSched->execute([$dayOfWeek, $stu['class_id'] ?? 0]);
    $todaySchedules = $stmtSched->fetchAll();

    // Student attendance stats
    $stmtStats = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance_log WHERE student_id = ? GROUP BY status");
    $stmtStats->execute([$student_id]);
    $attStats = $stmtStats->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalAtt = array_sum($attStats);
    $presentPct = $totalAtt > 0 ? round(((($attStats['present'] ?? 0) + ($attStats['late'] ?? 0)) / $totalAtt) * 100) : 0;

    // Student GPA
    $stmtGpa = $pdo->prepare("SELECT AVG(grade_level) as gpa FROM grades WHERE student_id = ? AND grade_level IS NOT NULL");
    $stmtGpa->execute([$student_id]);
    $gpaResult = $stmtGpa->fetch();
    $gpa = $gpaResult['gpa'] ? number_format($gpaResult['gpa'], 2) : '-';
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h3 class="text-dark fw-bold mb-0">ภาพรวมระบบ (Dashboard)</h3>
        <p class="text-muted mb-0">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <span class="btn btn-primary btn-sm" style="background-color: var(--accent-color); border: none; cursor: default;">
            <i class="fas fa-calendar-day me-1"></i> วัน<?= $thaiDay ?> <?= date('d/m/Y') ?>
        </span>
    </div>
</div>

<?php if ($role === 'admin' || $role === 'teacher'): ?>
<!-- ========== ADMIN/TEACHER DASHBOARD ========== -->

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">จำนวนครูทั้งหมด</p>
                    <h2 class="fw-bold mb-0"><?= number_format($num_teachers) ?></h2>
                    <a href="master-teacher.php" class="small text-decoration-none" style="color:var(--accent-color);">ดูรายละเอียด →</a>
                </div>
                <div class="stat-icon bg-primary-light">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">จำนวนนักเรียนทั้งหมด</p>
                    <h2 class="fw-bold mb-0"><?= number_format($num_students) ?></h2>
                    <a href="master-student.php" class="small text-decoration-none" style="color:var(--accent-color);">ดูรายละเอียด →</a>
                </div>
                <div class="stat-icon bg-info-light">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">จำนวนชั้นเรียน</p>
                    <h2 class="fw-bold mb-0"><?= number_format($num_classes) ?></h2>
                    <a href="master-class.php" class="small text-decoration-none" style="color:var(--accent-color);">ดูรายละเอียด →</a>
                </div>
                <div class="stat-icon bg-success-light">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">รายวิชาทั้งหมด</p>
                    <h2 class="fw-bold mb-0"><?= number_format($num_subjects) ?></h2>
                    <a href="master-subject.php" class="small text-decoration-none" style="color:var(--accent-color);">ดูรายละเอียด →</a>
                </div>
                <div class="stat-icon bg-warning-light">
                    <i class="fas fa-book"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 1: Attendance -->
<div class="row g-4 mb-4">
    <!-- Attendance Bar Chart (Weekly Real-time) -->
    <div class="col-xl-8 col-lg-7">
        <div class="card h-100 border-0 shadow-sm-light">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-bar me-2 text-primary"></i>สถิติการมาเรียน (7 วันล่าสุด)</h6>
                    <small class="text-muted">ข้อมูลจากระบบบันทึกเวลาเรียน (Real-time)</small>
                </div>
                <a href="attendance.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-clipboard-check me-1"></i>เช็คชื่อ
                </a>
            </div>
            <div class="card-body">
                <div id="attendanceChart" style="height: 320px;"></div>
            </div>
        </div>
    </div>

    <!-- Attendance Summary Donut -->
    <div class="col-xl-4 col-lg-5">
        <div class="card h-100 border-0 shadow-sm-light">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-info"></i>สรุปสถานะการมาเรียน</h6>
                <small class="text-muted">สัดส่วนรวมทั้งหมดในระบบ</small>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <div id="attendanceSummaryChart" style="height: 280px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2: Grades -->
<div class="row g-4 mb-4">
    <!-- Grade Distribution Donut -->
    <div class="col-xl-4 col-lg-5">
        <div class="card h-100 border-0 shadow-sm-light">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-medal me-2 text-warning"></i>ภาพรวมผลการเรียน</h6>
                <small class="text-muted">การกระจายเกรดทั้งระบบ</small>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <div id="gradeChart" style="height: 300px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Grade Average by Subject Bar -->
    <div class="col-xl-8 col-lg-7">
        <div class="card h-100 border-0 shadow-sm-light">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-line me-2 text-success"></i>เกรดเฉลี่ยรายวิชา</h6>
                    <small class="text-muted">เปรียบเทียบผลการเรียนเฉลี่ย (Real-time)</small>
                </div>
                <a href="grading.php" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-star me-1"></i>บันทึกเกรด
                </a>
            </div>
            <div class="card-body">
                <div id="gradeBySubjectChart" style="height: 320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Schedule + Recent Activity -->
<div class="row g-4 mb-4">
    <!-- Today's Schedule -->
    <div class="col-xl-7">
        <div class="card h-100 border-0 shadow-sm-light">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>ตารางเรียน/สอนวันนี้ (วัน<?= $thaiDay ?>)</h6>
                </div>
                <a href="schedule.php" class="btn btn-sm btn-outline-primary">ดูทั้งหมด</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="20%">เวลา</th>
                                <th width="30%">รายวิชา</th>
                                <th width="20%">ครูผู้สอน</th>
                                <th width="15%">ชั้น</th>
                                <th width="15%">ห้อง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($todaySchedules)): ?>
                                <?php foreach ($todaySchedules as $sc): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1">
                                                <?= substr($sc['start_time'], 0, 5) ?> - <?= substr($sc['end_time'], 0, 5) ?>
                                            </span>
                                        </td>
                                        <td><span class="fw-medium"><?= htmlspecialchars($sc['subject_code'] . ' ' . $sc['subject_name']) ?></span></td>
                                        <td><?= htmlspecialchars($sc['t_fname'] . ' ' . $sc['t_lname']) ?></td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary px-2 py-1"><?= htmlspecialchars($sc['level_name'] ?? '-') ?></span></td>
                                        <td><?= htmlspecialchars($sc['room_name'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-coffee me-1"></i> ไม่มีตารางสอนในวันนี้
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-xl-5">
        <div class="card h-100 border-0 shadow-sm-light">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-clock me-2 text-secondary"></i>กิจกรรมล่าสุด</h6>
                <small class="text-muted">การเช็คชื่อล่าสุดในระบบ</small>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $act): 
                            $statusIcon = '';
                            $statusText = '';
                            $statusColor = '';
                            switch($act['status']) {
                                case 'present': $statusIcon = 'check-circle'; $statusText = 'มาเรียน'; $statusColor = 'success'; break;
                                case 'late': $statusIcon = 'clock'; $statusText = 'มาสาย'; $statusColor = 'warning'; break;
                                case 'leave': $statusIcon = 'envelope'; $statusText = 'ลา'; $statusColor = 'info'; break;
                                case 'absent': $statusIcon = 'times-circle'; $statusText = 'ขาดเรียน'; $statusColor = 'danger'; break;
                            }
                        ?>
                        <div class="list-group-item border-0 px-4 py-2">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <span class="rounded-circle bg-<?= $statusColor ?> bg-opacity-10 d-flex align-items-center justify-content-center" style="width:35px;height:35px;">
                                        <i class="fas fa-<?= $statusIcon ?> text-<?= $statusColor ?>"></i>
                                    </span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-medium small"><?= htmlspecialchars($act['first_name'] . ' ' . $act['last_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($act['subject_code']) ?> • <?= $statusText ?></small>
                                </div>
                                <small class="text-muted"><?= date('d/m', strtotime($act['date'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox mb-2" style="font-size:2rem;"></i>
                            <p class="mb-0 small">ยังไม่มีกิจกรรม</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($role === 'student'): ?>
<!-- ========== STUDENT DASHBOARD ========== -->
<div class="row g-4 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">ชั้นเรียนของฉัน</p>
                    <h3 class="fw-bold mb-0 text-primary"><?= htmlspecialchars($stu['level_name'] ?? 'ยังไม่ระบุ') ?></h3>
                </div>
                <div class="stat-icon bg-primary-light"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">อัตราการเข้าเรียน</p>
                    <h3 class="fw-bold mb-0 text-success"><?= $presentPct ?>%</h3>
                </div>
                <div class="stat-icon bg-success-light"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="card stat-card h-100 p-3 border-0 shadow-sm-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1 small">เกรดเฉลี่ยสะสม (GPA)</p>
                    <h3 class="fw-bold mb-0 text-info"><?= $gpa ?></h3>
                </div>
                <div class="stat-icon bg-info-light"><i class="fas fa-graduation-cap"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Student Schedule -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm-light">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-calendar-alt me-2 text-primary"></i>ตารางเรียนวันนี้ (วัน<?= $thaiDay ?>)</h6>
                <a href="schedule.php" class="btn btn-sm btn-outline-primary">ดูตารางทั้งหมด</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover align-middle mb-0">
                        <thead><tr><th>เวลา</th><th>รายวิชา</th><th>ครูผู้สอน</th><th>ห้อง</th></tr></thead>
                        <tbody>
                            <?php if (!empty($todaySchedules)): ?>
                                <?php foreach ($todaySchedules as $sc): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border px-2 py-1"><?= substr($sc['start_time'], 0, 5) ?> - <?= substr($sc['end_time'], 0, 5) ?></span></td>
                                    <td class="fw-medium"><?= htmlspecialchars($sc['subject_code'] . ' ' . $sc['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($sc['t_fname'] . ' ' . $sc['t_lname']) ?></td>
                                    <td><?= htmlspecialchars($sc['room_name'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted"><i class="fas fa-coffee me-1"></i> ไม่มีตารางเรียนในวันนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<?php if ($role === 'admin' || $role === 'teacher'): ?>
<script>
$(document).ready(function() {
    // =========================================================
    // 1. กราฟสถิติการมาเรียนรายวัน (Stacked Bar - 7 วันล่าสุด)
    // =========================================================
    $.getJSON('api/dashboard_data.php?action=attendance_weekly', function(data) {
        if (data.success) {
            var options = {
                series: [
                    { name: 'มาเรียน', data: data.present },
                    { name: 'มาสาย', data: data.late },
                    { name: 'ลา', data: data.leave },
                    { name: 'ขาด', data: data.absent }
                ],
                chart: {
                    type: 'bar', height: 320, stacked: true,
                    fontFamily: 'Prompt, Inter, sans-serif',
                    toolbar: { show: false }, zoom: { enabled: false }
                },
                colors: ['#2ecc71', '#f39c12', '#3498db', '#e74c3c'],
                plotOptions: {
                    bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 }
                },
                dataLabels: { enabled: false },
                xaxis: { categories: data.labels, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { 
                    labels: { formatter: function(val) { return Math.floor(val); } },
                    tickAmount: 5,
                    decimalsInFloat: 0
                },
                legend: { position: 'top', horizontalAlign: 'right' },
                fill: { opacity: 1 },
                tooltip: {
                    y: { formatter: function(val) { return val + ' ครั้ง'; } }
                },
                grid: { borderColor: '#f1f1f1' }
            };
            new ApexCharts(document.querySelector("#attendanceChart"), options).render();
        } else {
            $('#attendanceChart').html('<div class="text-center text-muted py-5"><i class="fas fa-info-circle mb-2" style="font-size:2rem;"></i><p>ยังไม่มีข้อมูลการมาเรียน</p></div>');
        }
    });

    // =========================================================
    // 2. Donut สรุปสถานะการมาเรียนรวม
    // =========================================================
    $.getJSON('api/dashboard_data.php?action=attendance_summary', function(data) {
        if (data.success && data.total > 0) {
            var options = {
                series: [data.present, data.late, data.leave, data.absent],
                labels: ['มาเรียน', 'มาสาย', 'ลา', 'ขาด'],
                chart: { type: 'donut', height: 280, fontFamily: 'Prompt, Inter, sans-serif' },
                colors: ['#2ecc71', '#f39c12', '#3498db', '#e74c3c'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: { show: true },
                                value: { show: true, formatter: function(val) { return val + ' ครั้ง'; } },
                                total: {
                                    show: true, label: 'ทั้งหมด',
                                    formatter: function(w) { return w.globals.seriesTotals.reduce((a, b) => a + b, 0) + ' ครั้ง'; }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                stroke: { show: false },
                legend: { position: 'bottom', fontSize: '12px' }
            };
            new ApexCharts(document.querySelector("#attendanceSummaryChart"), options).render();
        } else {
            $('#attendanceSummaryChart').html('<div class="text-center text-muted py-5"><p class="small">ยังไม่มีข้อมูล</p></div>');
        }
    });

    // =========================================================
    // 3. Donut ภาพรวมผลการเรียน (Grade Distribution)
    // =========================================================
    $.getJSON('api/dashboard_data.php?action=grade_distribution', function(data) {
        if (data.success && data.distribution.total > 0) {
            var d = data.distribution;
            var options = {
                series: [d.grade_4, d.grade_3, d.grade_2, d.grade_1, d.grade_0],
                labels: ['เกรด 4', 'เกรด 3', 'เกรด 2', 'เกรด 1', 'เกรด 0'],
                chart: { type: 'donut', height: 300, fontFamily: 'Prompt, Inter, sans-serif' },
                colors: ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#e74c3c'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                name: { show: true },
                                value: { show: true, formatter: function(val) { return val + ' คน'; } },
                                total: {
                                    show: true, label: 'ทั้งหมด',
                                    formatter: function(w) { return w.globals.seriesTotals.reduce((a, b) => a + b, 0) + ' รายการ'; }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                stroke: { show: false },
                legend: { position: 'bottom', fontSize: '12px' }
            };
            new ApexCharts(document.querySelector("#gradeChart"), options).render();
        } else {
            $('#gradeChart').html('<div class="text-center text-muted py-5"><p class="small">ยังไม่มีข้อมูลเกรด</p></div>');
        }
    });

    // =========================================================
    // 4. Bar Chart เกรดเฉลี่ยรายวิชา
    // =========================================================
    $.getJSON('api/dashboard_data.php?action=grade_by_subject', function(data) {
        if (data.success && data.labels.length > 0) {
            var options = {
                series: [{ name: 'เกรดเฉลี่ย', data: data.averages }],
                chart: {
                    type: 'bar', height: 320,
                    fontFamily: 'Prompt, Inter, sans-serif',
                    toolbar: { show: false }
                },
                colors: ['#3498db'],
                plotOptions: {
                    bar: { horizontal: false, columnWidth: '50%', borderRadius: 6,
                        distributed: true
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) { return val.toFixed(1); },
                    style: { fontSize: '11px', fontWeight: 600 }
                },
                xaxis: { categories: data.labels, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { min: 0, max: 4, tickAmount: 4, labels: { formatter: function(val) { return val.toFixed(0); } } },
                colors: ['#2ecc71', '#3498db', '#9b59b6', '#e67e22', '#e74c3c', '#1abc9c', '#f39c12', '#2c3e50'],
                legend: { show: false },
                grid: { borderColor: '#f1f1f1' },
                tooltip: {
                    y: { formatter: function(val, opts) { return 'เฉลี่ย: ' + val.toFixed(2) + ' (' + data.counts[opts.dataPointIndex] + ' คน)'; } }
                }
            };
            new ApexCharts(document.querySelector("#gradeBySubjectChart"), options).render();
        } else {
            $('#gradeBySubjectChart').html('<div class="text-center text-muted py-5"><p class="small">ยังไม่มีข้อมูลเกรดรายวิชา</p></div>');
        }
    });
});
</script>
<?php endif; ?>