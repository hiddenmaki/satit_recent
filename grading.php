<?php
// grading.php - Grading System
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handling Teacher/Admin View
if ($role === 'admin' || $role === 'teacher') {
    $teacher_id = 0;
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        if ($teacher) $teacher_id = $teacher['id'];
    }

    $filter_schedule_id = $_GET['schedule_id'] ?? '';
    
    // Fetch unique classes taught by this teacher (or all for admin)
    $sqlSchedules = "
        SELECT DISTINCT ts.id as schedule_id, ts.academic_year, ts.semester, 
               s.subject_code, s.name as subject_name, c.room_name, cl.level_name
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN classrooms c ON ts.classroom_id = c.id
        JOIN classes cl ON c.class_id = cl.id
    ";
    if ($role === 'teacher') {
        $sqlSchedules .= " WHERE ts.teacher_id = :tid";
    }
    $sqlSchedules .= " ORDER BY ts.academic_year DESC, ts.semester DESC, cl.level_name ASC, c.room_name ASC";
    
    $stmtSched = $pdo->prepare($sqlSchedules);
    if ($role === 'teacher') $stmtSched->bindParam(':tid', $teacher_id);
    $stmtSched->execute();
    $schedules = $stmtSched->fetchAll();

    $selectedSchedule = null;
    $students = [];
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
            // Fetch students in this classroom and their grades for this subject/term
            $stmtStudents = $pdo->prepare("
                SELECT st.id as student_id, st.student_code, u.first_name, u.last_name, u.prefix,
                       g.raw_score, g.grade_level, g.id as grade_id
                FROM students st
                JOIN users u ON st.user_id = u.id
                LEFT JOIN grades g ON st.id = g.student_id 
                     AND g.subject_id = ? AND g.academic_year = ? AND g.semester = ?
                WHERE st.classroom_id = ?
                ORDER BY st.student_code ASC
            ");
            $stmtStudents->execute([
                $selectedSchedule['subject_id'], 
                $selectedSchedule['academic_year'], 
                $selectedSchedule['semester'], 
                $selectedSchedule['classroom_id']
            ]);
            $students = $stmtStudents->fetchAll();
        }
    }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">บันทึกผลการเรียน</h3>
        <p class="text-muted mb-0">กรอกคะแนนดิบและคำนวณเกรดอัตโนมัติ</p>
    </div>
</div>

<div class="row">
    <!-- Selection Panel -->
    <div class="col-lg-3 mb-4">
        <div class="card shadow-sm-light">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">เลือกรายวิชาและห้องเรียน</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="grading.php">
                    <div class="mb-4">
                        <label class="form-label small text-muted">วิชาที่สอน</label>
                        <select class="form-select bg-light" name="schedule_id" required onchange="this.form.submit()">
                            <option value="">เลือกวิชา-ห้องเรียน...</option>
                            <?php foreach($schedules as $sc): ?>
                                <?php 
                                    $label = "({$sc['academic_year']}/{$sc['semester']}) {$sc['subject_code']} {$sc['level_name']}-{$sc['room_name']}";
                                    $sel = ($sc['schedule_id'] == $filter_schedule_id) ? 'selected' : '';
                                ?>
                                <option value="<?= $sc['schedule_id'] ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Grading Table -->
    <div class="col-lg-9 mb-4">
        <div class="card shadow-sm-light h-100">
            <?php if ($selectedSchedule): ?>
            <form action="api/grading_actions.php" method="POST">
                <input type="hidden" name="action" value="save_grades">
                <input type="hidden" name="schedule_id" value="<?= $selectedSchedule['id'] ?>">
                <input type="hidden" name="subject_id" value="<?= $selectedSchedule['subject_id'] ?>">
                <input type="hidden" name="academic_year" value="<?= $selectedSchedule['academic_year'] ?>">
                <input type="hidden" name="semester" value="<?= $selectedSchedule['semester'] ?>">
                
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                         <h6 class="m-0 font-weight-bold text-dark">รายวิชา: <?= htmlspecialchars($selectedSchedule['subject_code']) ?> <?= htmlspecialchars($selectedSchedule['subject_name']) ?> (<?= htmlspecialchars($selectedSchedule['room_name']) ?>)</h6>
                         <small class="text-muted">ครูผู้สอน: ท.<?= htmlspecialchars($selectedSchedule['teacher_fname']) ?> <?= htmlspecialchars($selectedSchedule['teacher_lname']) ?></small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-success shadow-sm"><i class="fas fa-save me-1"></i> บันทึกคะแนน</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="10%" class="text-center">ลำดับ</th>
                                    <th width="20%">รหัสนักเรียน</th>
                                    <th width="35%">ชื่อ - นามสกุล</th>
                                    <th width="20%" class="text-center">คะแนนดิบ (100)</th>
                                    <th width="15%" class="text-center">เกรดที่ได้</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php $i=1; foreach($students as $st): ?>
                                    <tr>
                                        <td class="text-center"><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($st['student_code']) ?></td>
                                        <td><?= htmlspecialchars($st['prefix'] . $st['first_name'] . ' ' . $st['last_name']) ?></td>
                                        <td>
                                            <input type="hidden" name="student_ids[]" value="<?= $st['student_id'] ?>">
                                            <input type="number" name="raw_scores[<?= $st['student_id'] ?>]" class="form-control text-center mx-auto fw-bold text-primary" value="<?= htmlspecialchars($st['raw_score'] ?? '') ?>" min="0" max="100" style="width: 80px;" oninput="calculateGrade(this)">
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            // Helper to display badge based on grade
                                            $grade = $st['grade_level'] ?? '-';
                                            $badgeClass = 'bg-secondary';
                                            if ($grade == '4' || $grade == '4.0') $badgeClass = 'bg-success';
                                            else if ($grade == '3' || $grade == '3.5' || $grade == '2.5' || $grade == '2') $badgeClass = 'bg-info';
                                            else if ($grade == '0') $badgeClass = 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badgeClass ?> fs-6 px-3 py-2 rounded-pill shadow-sm grade-display"><?= htmlspecialchars($grade) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูลนักเรียนในห้องนี้</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3 border-top">
                    <div class="d-flex justify-content-end align-items-center">
                       <small class="text-muted me-3">เกณฑ์: 80-100 (4), 75-79 (3.5), 70-74 (3), 65-69 (2.5), 60-64 (2), 55-59 (1.5), 50-54 (1), 0-49 (0)</small>
                    </div>
                </div>
            </form>
            <?php else: ?>
                 <div class="card-body text-center py-5">
                    <div class="mb-3 text-muted" style="font-size: 3rem;">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">กรุณาเลือกรายวิชาและห้องเรียน</h5>
                    <p class="text-muted mb-0">เลือกจากเมนูด้านซ้ายเพื่อเริ่มบันทึกคะแนน</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function calculateGrade(input) {
    let score = parseFloat(input.value);
    let grade = '-';
    let badgeClass = 'bg-secondary';
    
    if (!isNaN(score)) {
        if (score >= 80) { grade = '4.0'; badgeClass = 'bg-success'; }
        else if (score >= 75) { grade = '3.5'; badgeClass = 'bg-info'; }
        else if (score >= 70) { grade = '3.0'; badgeClass = 'bg-info'; }
        else if (score >= 65) { grade = '2.5'; badgeClass = 'bg-info'; }
        else if (score >= 60) { grade = '2.0'; badgeClass = 'bg-warning text-dark'; }
        else if (score >= 55) { grade = '1.5'; badgeClass = 'bg-warning text-dark'; }
        else if (score >= 50) { grade = '1.0'; badgeClass = 'bg-warning text-dark'; }
        else { grade = '0'; badgeClass = 'bg-danger'; }
    }
    
    let row = input.closest('tr');
    let badge = row.querySelector('.grade-display');
    badge.textContent = grade;
    badge.className = 'badge ' + badgeClass + ' fs-6 px-3 py-2 rounded-pill shadow-sm grade-display';
}
</script>

<?php 
} else { 
?>
<!-- Student View -->
<?php
$stmtStu = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmtStu->execute([$user_id]);
$stu = $stmtStu->fetch();
$student_id = $stu ? $stu['id'] : 0;

$grades = [];
$totalScore = 0;
$totalCredits = 0;
$gpa = 0.00;

if ($student_id) {
    $stmtGrades = $pdo->prepare("
        SELECT g.*, s.subject_code, s.name as subject_name, s.credit, 
               u.first_name as teacher_fname, u.last_name as teacher_lname
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        JOIN teaching_schedule ts ON ts.subject_id = g.subject_id AND ts.academic_year = g.academic_year AND ts.semester = g.semester
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE g.student_id = ?
        ORDER BY g.academic_year DESC, g.semester DESC, s.subject_code ASC
    ");
    $stmtGrades->execute([$student_id]);
    // The JOIN with teaching_schedule might return multiple rows if multiple teachers teach the same subject to different classes.
    // To be precise, we should join through classrooms as well.
    // Simplifying: just fetch grades and subjects. Teacher info can be omitted or fetched by another subquery to keep it clean.
    
    $stmtGradesSimple = $pdo->prepare("
        SELECT g.*, s.subject_code, s.name as subject_name, s.credit
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        WHERE g.student_id = ?
        ORDER BY g.academic_year DESC, g.semester DESC, s.subject_code ASC
    ");
    $stmtGradesSimple->execute([$student_id]);
    $grades = $stmtGradesSimple->fetchAll();
    
    foreach ($grades as $g) {
        $credit = floatval($g['credit'] ?? 1.5); // Fallback to 1.5 if not set in DB
        $gradeVal = floatval($g['grade_level'] ?? 0);
        $totalCredits += $credit;
        $totalScore += ($gradeVal * $credit);
    }
    
    if ($totalCredits > 0) {
        $gpa = round($totalScore / $totalCredits, 2);
    }
}
?>
<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                     <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-user-graduate me-2"></i>รายงานผลการเรียนของฉัน</h6>
                     <small class="text-muted">ผลการเรียนทั้งหมด</small>
                </div>
                <span class="badge bg-primary fs-6 shadow-sm px-3 py-2">GPAX: <?= number_format($gpa, 2) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th width="15%">ปีการศึกษา/เทอม</th>
                                <th width="15%">รหัสวิชา</th>
                                <th width="35%">ชื่อรายวิชา</th>
                                <th width="10%" class="text-center">คะแนนดิบ</th>
                                <th width="10%" class="text-center">เกรด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($grades) > 0): ?>
                                <?php foreach($grades as $g): ?>
                                <tr>
                                    <td><?= htmlspecialchars($g['academic_year']) ?>/<?= htmlspecialchars($g['semester']) ?></td>
                                    <td><?= htmlspecialchars($g['subject_code']) ?></td>
                                    <td><span class="fw-medium"><?= htmlspecialchars($g['subject_name']) ?></span></td>
                                    <td class="text-center"><?= htmlspecialchars($g['raw_score']) ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $gl = $g['grade_level'];
                                        $bg = 'bg-secondary';
                                        if ($gl == '4.0' || $gl == '4') $bg = 'bg-success';
                                        else if ($gl == '0') $bg = 'bg-danger';
                                        else $bg = 'bg-info';
                                        ?>
                                        <span class="badge <?= $bg ?> fs-6 px-3 py-2 rounded-pill shadow-sm"><?= htmlspecialchars($gl) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลผลการเรียน</td></tr>
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
