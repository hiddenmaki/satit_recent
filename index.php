<?php
// index.php - Dashboard
require_once 'includes/auth.php';
// Allows all roles to access, we'll customize view based on role
include 'includes/header.php';
require_once 'includes/db.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Initial count vars
$num_teachers = 0;
$num_students = 0;
$num_classrooms = 0;
$num_subjects = 0;

if ($role === 'admin' || $role === 'teacher') {
    // Basic stats
    $num_teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $num_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $num_classrooms = $pdo->query("SELECT COUNT(*) FROM classrooms")->fetchColumn();
    $num_subjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();

    $teacher_id = 0;
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        if ($teacher)
            $teacher_id = $teacher['id'];
    }

    // Today's schedule
    $dayOfWeek = date('l');
    $sqlSched = "
        SELECT ts.start_time, ts.end_time, s.subject_code, s.name as subject_name,
               c.room_name, cl.level_name, u.first_name as t_fname, u.last_name as t_lname
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN classrooms c ON ts.classroom_id = c.id
        JOIN classes cl ON c.class_id = cl.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ts.day_of_week = :dow
    ";
    if ($role === 'teacher') {
        $sqlSched .= " AND ts.teacher_id = :tid";
    }
    $sqlSched .= " ORDER BY ts.start_time ASC LIMIT 5";
    $stmtSched = $pdo->prepare($sqlSched);
    $stmtSched->bindParam(':dow', $dayOfWeek);
    if ($role === 'teacher')
        $stmtSched->bindParam(':tid', $teacher_id);
    $stmtSched->execute();
    $todaySchedules = $stmtSched->fetchAll();

    // For Donut Chart: Get overall grade distribution
    $stmtGrades = $pdo->query("SELECT grade_level, COUNT(*) as g_count FROM grades GROUP BY grade_level ORDER BY grade_level DESC");
    $gradeDist = $stmtGrades->fetchAll(PDO::FETCH_KEY_PAIR); // ['4.0' => 50, ...]
    $donutData = [
        intval($gradeDist['4.0'] ?? 0) + intval($gradeDist['4'] ?? 0),
        intval($gradeDist['3.5'] ?? 0) + intval($gradeDist['3.0'] ?? 0) + intval($gradeDist['3'] ?? 0),
        intval($gradeDist['2.5'] ?? 0) + intval($gradeDist['2.0'] ?? 0) + intval($gradeDist['2'] ?? 0),
        intval($gradeDist['1.5'] ?? 0) + intval($gradeDist['1.0'] ?? 0) + intval($gradeDist['1'] ?? 0),
        intval($gradeDist['0'] ?? 0)
    ];
} else if ($role === 'student') {
    $stmtStu = $pdo->prepare("SELECT st.id, st.classroom_id, c.room_name, cl.level_name FROM students st JOIN classrooms c ON st.classroom_id = c.id JOIN classes cl ON c.class_id = cl.id WHERE st.user_id = ?");
    $stmtStu->execute([$user_id]);
    $stu = $stmtStu->fetch();
    $student_id = $stu ? $stu['id'] : 0;

    // Today's schedule for student
    $dayOfWeek = date('l');
    $stmtSched = $pdo->prepare("
        SELECT ts.start_time, ts.end_time, s.subject_code, s.name as subject_name,
               c.room_name, cl.level_name, u.first_name as t_fname, u.last_name as t_lname
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN classrooms c ON ts.classroom_id = c.id
        JOIN classes cl ON c.class_id = cl.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE ts.day_of_week = ? AND ts.classroom_id = ?
        ORDER BY ts.start_time ASC
    ");
    $stmtSched->execute([$dayOfWeek, $stu['classroom_id'] ?? 0]);
    $todaySchedules = $stmtSched->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">ภาพรวมระบบ (Overview)</h3>
        <p class="text-muted mb-0">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['username']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" style="background-color: var(--accent-color); border: none;"><i
                class="fas fa-calendar-day me-1"></i> วันนี้: <?= date('d/m/Y') ?></button>
    </div>
</div>

<?php if ($role === 'admin' || $role === 'teacher'): ?>
    <!-- Stats Cards Row -->
    <div class="row g-4 mb-4">
        <!-- Teacher Stat -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">จำนวนครูทั้งหมด</p>
                        <h2 class="fw-bold mb-0"><?= number_format($num_teachers) ?></h2>
                    </div>
                    <div class="stat-icon bg-primary-light">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Stat -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">จำนวนนักเรียนทั้งหมด</p>
                        <h2 class="fw-bold mb-0"><?= number_format($num_students) ?></h2>
                    </div>
                    <div class="stat-icon bg-info-light">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classrooms Stat -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">จำนวนห้องเรียน</p>
                        <h2 class="fw-bold mb-0"><?= number_format($num_classrooms) ?></h2>
                    </div>
                    <div class="stat-icon bg-success-light">
                        <i class="fas fa-door-open"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Stat -->
        <div class="col-xl-3 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">รายวิชาทั้งหมด</p>
                        <h2 class="fw-bold mb-0"><?= number_format($num_subjects) ?></h2>
                    </div>
                    <div class="stat-icon bg-warning-light">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Attendance Line Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-0">
                    <h5 class="mb-0 fw-semibold">สถิติการมาเรียน (จำลอง)</h5>
                </div>
                <div class="card-body">
                    <div id="attendanceChart" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution Donut Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-semibold">ภาพรวมผลการเรียนเฉลี่ยในระบบ</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div id="gradeChart" style="height: 280px; width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($role === 'student'): ?>
    <!-- Student Stats Row -->
    <div class="row g-4 mb-4">
        <!-- Student Class Stat -->
        <div class="col-xl-4 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">ชั้นเรียนของฉัน</p>
                        <h3 class="fw-bold mb-0 text-primary">
                            <?= htmlspecialchars($stu['level_name'] . '/' . $stu['room_name']) ?></h3>
                    </div>
                    <div class="stat-icon bg-primary-light">
                        <i class="fas fa-door-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Student Attendance Mock Stat -->
        <div class="col-xl-4 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">สถิติมาเรียน</p>
                        <h3 class="fw-bold mb-0 text-success">95%</h3>
                    </div>
                    <div class="stat-icon bg-success-light">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Student Grade Mock Stat -->
        <div class="col-xl-4 col-md-6">
            <div class="card stat-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 fs-6">เกรดเฉลี่ยสะสม</p>
                        <h3 class="fw-bold mb-0 text-info">3.85</h3>
                    </div>
                    <div class="stat-icon bg-info-light">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Quick Access Tables -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">ตารางเรียน/สอนวันนี้ (<?= htmlspecialchars($dayOfWeek) ?>)</h5>
                <a href="schedule.php" class="btn btn-sm btn-light">ดูทั้งหมด</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom table-hover mb-0">
                        <thead>
                            <tr>
                                <th>เวลา</th>
                                <th>รายวิชา</th>
                                <th>ครูผู้สอน</th>
                                <th>ชั้นเรียน</th>
                                <th>ห้อง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($todaySchedules)): ?>
                                <?php foreach ($todaySchedules as $sc): ?>
                                    <tr>
                                        <td><?= substr($sc['start_time'], 0, 5) ?> - <?= substr($sc['end_time'], 0, 5) ?></td>
                                        <td>
                                            <div class="fw-medium">
                                                <?= htmlspecialchars($sc['subject_code'] . ' ' . $sc['subject_name']) ?></div>
                                        </td>
                                        <td>ท.<?= htmlspecialchars($sc['t_fname'] . ' ' . $sc['t_lname']) ?></td>
                                        <td><?= htmlspecialchars($sc['level_name'] . '/' . $sc['room_name']) ?></td>
                                        <td><?= htmlspecialchars($sc['room_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">ไม่มีตารางในวันนี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Scripts for Charts -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        <?php if ($role === 'admin' || $role === 'teacher'): ?>
            // 1. Attendance Area Chart (ApexCharts) - Mocked for demo
            var attendanceOptions = {
                series: [{
                    name: 'มาเรียน',
                    data: [2750, 2800, 2790, 2810, 2820]
                }, {
                    name: 'ขาด/ลา',
                    data: [95, 45, 55, 35, 25]
                }],
                chart: {
                    height: 300,
                    type: 'area',
                    fontFamily: 'Inter, sans-serif',
                    toolbar: { show: false },
                    zoom: { enabled: false }
                },
                colors: ['#3498db', '#e74c3c'],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                xaxis: {
                    categories: ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัส', 'ศุกร์'],
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: function (val) { return val; }
                    }
                },
                legend: { position: 'top', horizontalAlign: 'right' }
            };
            var attendanceChart = new ApexCharts(document.querySelector("#attendanceChart"), attendanceOptions);
            attendanceChart.render();

            // 2. Grade Distribution Donut Chart (Dynamic)
            var gradeOptions = {
                series: <?= json_encode(array_values($donutData)) ?>,
                labels: ['เกรด 4', 'เกรด 3', 'เกรด 2', 'เกรด 1', 'เกรด 0'],
                chart: {
                    type: 'donut',
                    height: 280,
                    fontFamily: 'Inter, sans-serif',
                },
                colors: ['#2ecc71', '#3498db', '#f1c40f', '#e67e22', '#e74c3c'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                name: { show: true },
                                value: { show: true, formatter: function (val) { return val } },
                                total: {
                                    show: true, label: 'ทั้งหมด', formatter: function (w) {
                                        return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                stroke: { show: false },
                legend: { position: 'bottom' }
            };
            var gradeChart = new ApexCharts(document.querySelector("#gradeChart"), gradeOptions);
            gradeChart.render();
        <?php endif; ?>
    });
</script>

<?php include 'includes/footer.php'; ?>