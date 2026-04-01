<?php
// grading.php - ระบบบันทึกผลการเรียน (ตัดเกรด)
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

/**
 * ฟังก์ชันสำหรับคำนวณเกรดจากคะแนนดิบ (Grade Calculation)
 * @param int $score คะแนน (0-100)
 * @return int เกรด (0, 1, 2, 3, 4)
 */
function calculateGradeFromScore($score) {
    if ($score >= 80) return 4;
    if ($score >= 70) return 3;
    if ($score >= 60) return 2;
    if ($score >= 50) return 1;
    return 0;
}

/**
 * ฟังก์ชันสำหรับคืนค่า Class ของ Bootstrap ตามลำดับเกรด (เพื่อแสดงสีที่แตกต่างกัน)
 */
function gradeColorClass($grade) {
    if ($grade == 4) return 'bg-success';
    if ($grade == 3) return 'bg-info';
    if ($grade == 2) return 'bg-warning text-dark';
    if ($grade == 1) return 'bg-orange';
    if ($grade == 0) return 'bg-danger';
    return 'bg-secondary';
}

// --- ส่วนจัดการมุมมองของครูและผู้ดูแลระบบ (Teacher/Admin View) ---
if ($role === 'admin' || $role === 'teacher') {
    $teacher_id = 0;
    if ($role === 'teacher') {
        // ถ้าเป็นครู: ค้นหา teacher_id เพื่อใช้ในการกรองวิชาที่ตัวเองเขาสอน
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $teacher = $stmt->fetch();
        if ($teacher) $teacher_id = $teacher['id'];
    }

    // ตัวแปรสำหรับการกรอง (วิชา, ชั้นเรียน, ปีการศึกษา, เทอม)
    $filter_subject_id = $_GET['subject_id'] ?? '';
    $filter_class_id = $_GET['class_id'] ?? '';
    $filter_academic_year = $_GET['academic_year'] ?? '2567';
    $filter_semester = $_GET['semester'] ?? '1';

    // ดึงรายชื่อวิชามาแสดงใน Dropdown
    if ($role === 'teacher') {
        // ถ้าเป็นครู: แสดงเฉพาะวิชาที่มีชื่อตัวเองในตารางสอน
        $stmtSubjects = $pdo->prepare("
            SELECT DISTINCT s.id, s.subject_code, s.name 
            FROM subjects s 
            JOIN teaching_schedule ts ON ts.subject_id = s.id 
            WHERE ts.teacher_id = ? 
            ORDER BY s.subject_code ASC
        ");
        $stmtSubjects->execute([$teacher_id]);
    } else {
        // ถ้าเป็น Admin: แสดงวิชาทั้งหมด
        $stmtSubjects = $pdo->query("SELECT id, subject_code, name FROM subjects ORDER BY subject_code ASC");
    }
    $allSubjects = $stmtSubjects->fetchAll();

    // ดึงรายชื่อชั้นเรียนมาแสดงใน Dropdown
    if ($role === 'teacher') {
        // ถ้าเป็นครู: แสดงเฉพาะห้องที่ตัวเองไปสอน
        $stmtClasses = $pdo->prepare("
            SELECT DISTINCT cl.id, cl.level_name 
            FROM classes cl 
            JOIN teaching_schedule ts ON ts.class_id = cl.id 
            WHERE ts.teacher_id = ? 
            ORDER BY cl.level_name ASC
        ");
        $stmtClasses->execute([$teacher_id]);
    } else {
        // ถ้าเป็น Admin: แสดงทุกห้องเรียน
        $stmtClasses = $pdo->query("SELECT id, level_name FROM classes ORDER BY level_name ASC");
    }
    $allClasses = $stmtClasses->fetchAll();

    // --- ส่วนดึงข้อมูลนักเรียนและคะแนน (เมื่อเลือกวิชาและห้องเรียนแล้ว) ---
    $students = [];
    $subjectInfo = null;
    $classInfo = null;
    
    if (!empty($filter_subject_id) && !empty($filter_class_id)) {
        // ดึงข้อมูลวิชาที่เลือก
        $stmtSub = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmtSub->execute([$filter_subject_id]);
        $subjectInfo = $stmtSub->fetch();
        
        // ดึงข้อมูลห้องเรียนที่เลือก
        $stmtCl = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmtCl->execute([$filter_class_id]);
        $classInfo = $stmtCl->fetch();
        
        // ดึงรายชื่อนักเรียนในห้องนั้น พร้อม Join กับตารางเกรด (ถ้ามี) เพื่อแสดงคะแนนเดิม
        $stmtStudents = $pdo->prepare("
            SELECT st.id as student_id, st.student_code, u.prefix, u.first_name, u.last_name,
                   g.raw_score, g.grade_level, g.id as grade_id
            FROM students st
            JOIN users u ON st.user_id = u.id
            LEFT JOIN grades g ON st.id = g.student_id 
                 AND g.subject_id = ? AND g.academic_year = ? AND g.semester = ?
            WHERE st.class_id = ?
            ORDER BY st.student_code ASC
        ");
        $stmtStudents->execute([
            $filter_subject_id, 
            $filter_academic_year, 
            $filter_semester, 
            $filter_class_id
        ]);
        $students = $stmtStudents->fetchAll();
    }
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">บันทึกผลการเรียน</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">บันทึกผลการเรียน</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (isset($_GET['status'])): ?>
    <?php if ($_GET['status'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'บันทึกคะแนนเรียบร้อยแล้ว') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($_GET['status'] == 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($_GET['msg'] ?? 'เกิดข้อผิดพลาด') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Selection Panel -->
<div class="card shadow-sm-light mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="m-0 fw-bold text-primary"><i class="fas fa-filter me-2"></i>เลือกวิชาและชั้นเรียน</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="grading.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">รายวิชา *</label>
                <select class="form-select bg-light" name="subject_id" id="subjectSelect" required>
                    <option value="">-- เลือกรายวิชา --</option>
                    <?php foreach ($allSubjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($filter_subject_id == $s['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['subject_code'] . ' - ' . $s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">ชั้นเรียน *</label>
                <select class="form-select bg-light" name="class_id" id="classSelect" required>
                    <option value="">-- เลือกชั้นเรียน --</option>
                    <?php foreach ($allClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filter_class_id == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['level_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">ปีการศึกษา</label>
                <input type="text" name="academic_year" class="form-control bg-light" value="<?= htmlspecialchars($filter_academic_year) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">ภาคเรียน</label>
                <select class="form-select bg-light" name="semester" required>
                    <option value="1" <?= ($filter_semester == '1') ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                    <option value="2" <?= ($filter_semester == '2') ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 shadow-sm" style="background-color: var(--accent-color); border: none;">
                    <i class="fas fa-search me-1"></i>ดึงรายชื่อ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Grading criteria info -->
<div class="card shadow-sm-light mb-4 border-0">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-3 justify-content-center align-items-center">
            <small class="text-muted fw-bold">เกณฑ์การตัดเกรด:</small>
            <span class="badge bg-success px-3 py-2">80-100 = เกรด 4</span>
            <span class="badge bg-info px-3 py-2">70-79 = เกรด 3</span>
            <span class="badge bg-warning text-dark px-3 py-2">60-69 = เกรด 2</span>
            <span class="badge bg-secondary px-3 py-2">50-59 = เกรด 1</span>
            <span class="badge bg-danger px-3 py-2">0-49 = เกรด 0</span>
        </div>
    </div>
</div>

<!-- Grading Table -->
<?php if (!empty($filter_subject_id) && !empty($filter_class_id) && $subjectInfo && $classInfo): ?>
<div class="card shadow-sm-light mb-4">
    <form action="api/grading_actions.php" method="POST">
        <input type="hidden" name="action" value="save_grades">
        <input type="hidden" name="subject_id" value="<?= $filter_subject_id ?>">
        <input type="hidden" name="class_id" value="<?= $filter_class_id ?>">
        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($filter_academic_year) ?>">
        <input type="hidden" name="semester" value="<?= htmlspecialchars($filter_semester) ?>">
        
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 border-bottom">
            <div>
                <h6 class="m-0 fw-bold text-dark">
                    <i class="fas fa-clipboard-list me-2 text-primary"></i>
                    <?= htmlspecialchars($subjectInfo['subject_code']) ?> - <?= htmlspecialchars($subjectInfo['name']) ?>
                </h6>
                <small class="text-muted">ชั้น <?= htmlspecialchars($classInfo['level_name']) ?> | ปีการศึกษา <?= htmlspecialchars($filter_academic_year) ?>/<?= htmlspecialchars($filter_semester) ?> | จำนวน <?= count($students) ?> คน</small>
            </div>
            <button type="submit" class="btn btn-success shadow-sm">
                <i class="fas fa-save me-1"></i>บันทึกคะแนนทั้งหมด
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th width="8%" class="text-center">ลำดับ</th>
                            <th width="15%">รหัสนักเรียน</th>
                            <th width="32%">ชื่อ - นามสกุล</th>
                            <th width="20%" class="text-center">คะแนนดิบ (เต็ม 100)</th>
                            <th width="15%" class="text-center">เกรด</th>
                            <th width="10%" class="text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php $i = 1; foreach ($students as $st): ?>
                            <?php
                                $existingScore = $st['raw_score'];
                                $existingGrade = $st['grade_level'];
                                $gradeDisplay = ($existingGrade !== null) ? intval($existingGrade) : '-';
                                $badgeClass = ($existingGrade !== null) ? gradeColorClass(intval($existingGrade)) : 'bg-secondary';
                                $statusIcon = ($existingGrade !== null) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-minus-circle text-muted"></i>';
                            ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td><span class="fw-medium"><?= htmlspecialchars($st['student_code']) ?></span></td>
                                <td><?= htmlspecialchars($st['prefix'] . $st['first_name'] . ' ' . $st['last_name']) ?></td>
                                <td class="text-center">
                                    <input type="hidden" name="student_ids[]" value="<?= $st['student_id'] ?>">
                                    <input type="number" 
                                           name="raw_scores[<?= $st['student_id'] ?>]" 
                                           class="form-control text-center mx-auto fw-bold score-input" 
                                           value="<?= htmlspecialchars($existingScore ?? '') ?>" 
                                           min="0" max="100" step="1"
                                           style="width: 100px;" 
                                           placeholder="0-100"
                                           oninput="calculateGrade(this)">
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $badgeClass ?> fs-6 px-3 py-2 rounded-pill shadow-sm grade-display"><?= $gradeDisplay ?></span>
                                </td>
                                <td class="text-center status-cell"><?= $statusIcon ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-users" style="font-size:2.5rem;"></i>
                                        <p class="mt-2 mb-0">ไม่พบนักเรียนในชั้นเรียนนี้</p>
                                        <small>กรุณาตรวจสอบว่ามีนักเรียนอยู่ในชั้นเรียน "<?= htmlspecialchars($classInfo['level_name']) ?>" แล้วหรือไม่</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (count($students) > 0): ?>
        <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>กรอกคะแนนดิบ 0-100 ระบบจะคำนวณเกรดให้อัตโนมัติ</small>
            <button type="submit" class="btn btn-success shadow-sm">
                <i class="fas fa-save me-1"></i>บันทึกคะแนนทั้งหมด
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php elseif (empty($filter_subject_id) || empty($filter_class_id)): ?>
<div class="card shadow-sm-light mb-4">
    <div class="card-body text-center py-5">
        <div class="mb-3 text-muted" style="font-size: 3rem;">
            <i class="fas fa-edit"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">กรุณาเลือกรายวิชาและชั้นเรียน</h5>
        <p class="text-muted mb-0">เลือกจากเมนูด้านบนเพื่อดึงรายชื่อนักเรียนและเริ่มบันทึกคะแนน</p>
    </div>
</div>
<?php endif; ?>

<script>
function calculateGrade(input) {
    let score = parseInt(input.value);
    let grade = '-';
    let badgeClass = 'bg-secondary';
    
    if (!isNaN(score) && input.value !== '') {
        if (score > 100) { input.value = 100; score = 100; }
        if (score < 0) { input.value = 0; score = 0; }
        
        if (score >= 80) { grade = '4'; badgeClass = 'bg-success'; }
        else if (score >= 70) { grade = '3'; badgeClass = 'bg-info'; }
        else if (score >= 60) { grade = '2'; badgeClass = 'bg-warning text-dark'; }
        else if (score >= 50) { grade = '1'; badgeClass = 'bg-secondary'; }
        else { grade = '0'; badgeClass = 'bg-danger'; }
    }
    
    let row = input.closest('tr');
    let badge = row.querySelector('.grade-display');
    badge.textContent = grade;
    badge.className = 'badge ' + badgeClass + ' fs-6 px-3 py-2 rounded-pill shadow-sm grade-display';
    
    // Update status icon
    let statusCell = row.querySelector('.status-cell');
    if (input.value !== '' && !isNaN(score)) {
        statusCell.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
    } else {
        statusCell.innerHTML = '<i class="fas fa-minus-circle text-muted"></i>';
    }
}
</script>

<?php 
} else { 
?>
<!-- Student View -->
<?php
$stmtStu = $pdo->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$stmtStu->execute([$user_id]);
$stu = $stmtStu->fetch();
$student_id = $stu ? $stu['id'] : 0;

$grades = [];
$totalScore = 0;
$totalCredits = 0;
$gpa = 0.00;

if ($student_id) {
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
        $credit = floatval($g['credit'] ?? 1.5);
        $gradeVal = floatval($g['grade_level'] ?? 0);
        $totalCredits += $credit;
        $totalScore += ($gradeVal * $credit);
    }
    
    if ($totalCredits > 0) {
        $gpa = round($totalScore / $totalCredits, 2);
    }
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="text-dark fw-bold mb-0">ผลการเรียนของฉัน</h3>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 py-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">ผลการเรียน</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card shadow-sm-light h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div>
                     <h6 class="m-0 fw-bold text-dark"><i class="fas fa-user-graduate me-2"></i>รายงานผลการเรียน</h6>
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
                                <?php foreach ($grades as $g): ?>
                                <tr>
                                    <td><?= htmlspecialchars($g['academic_year']) ?>/<?= htmlspecialchars($g['semester']) ?></td>
                                    <td><?= htmlspecialchars($g['subject_code']) ?></td>
                                    <td><span class="fw-medium"><?= htmlspecialchars($g['subject_name']) ?></span></td>
                                    <td class="text-center"><?= htmlspecialchars($g['raw_score']) ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $gl = intval($g['grade_level']);
                                        $bg = gradeColorClass($gl);
                                        ?>
                                        <span class="badge <?= $bg ?> fs-6 px-3 py-2 rounded-pill shadow-sm"><?= $gl ?></span>
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
            <div class="card-footer bg-white py-2 border-top">
                <div class="d-flex flex-wrap gap-3 justify-content-center align-items-center">
                    <small class="text-muted fw-bold">เกณฑ์เกรด:</small>
                    <span class="badge bg-success px-2 py-1">80-100 = เกรด 4</span>
                    <span class="badge bg-info px-2 py-1">70-79 = เกรด 3</span>
                    <span class="badge bg-warning text-dark px-2 py-1">60-69 = เกรด 2</span>
                    <span class="badge bg-secondary px-2 py-1">50-59 = เกรด 1</span>
                    <span class="badge bg-danger px-2 py-1">0-49 = เกรด 0</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var selectedClassId = '<?= $filter_class_id ?>';
    
    $('#subjectSelect').on('change', function() {
        var subjectId = $(this).val();
        var $classSelect = $('#classSelect');
        
        if (!subjectId) {
            $classSelect.html('<option value="">-- เลือกรายวิชาก่อน --</option>');
            return;
        }
        
        $classSelect.html('<option value="">กำลังโหลด...</option>');
        
        $.getJSON('api/grading_classes.php', { subject_id: subjectId }, function(data) {
            var html = '<option value="">-- เลือกชั้นเรียน --</option>';
            if (data.success && data.classes.length > 0) {
                data.classes.forEach(function(c) {
                    var sel = (c.id == selectedClassId) ? ' selected' : '';
                    html += '<option value="' + c.id + '"' + sel + '>' + c.level_name + '</option>';
                });
            } else {
                html = '<option value="">-- ไม่มีชั้นเรียนสำหรับวิชานี้ --</option>';
            }
            $classSelect.html(html);
        });
    });

    // Trigger on page load if subject is already selected
    if ($('#subjectSelect').val()) {
        $('#subjectSelect').trigger('change');
    }
});
</script>
