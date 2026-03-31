<?php
// api/dashboard_data.php - Real-time Dashboard Data API
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// === สถิติจำนวนบุคลากรและรายวิชา ===
if ($action === 'summary_counts') {
    $teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $classes = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $classrooms = $pdo->query("SELECT COUNT(*) FROM classrooms")->fetchColumn();
    $subjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'teachers' => (int)$teachers,
        'students' => (int)$students,
        'classes' => (int)$classes,
        'classrooms' => (int)$classrooms,
        'subjects' => (int)$subjects,
        'users' => (int)$users
    ]);
    exit();
}

// === สถิติการมาเรียนรายวัน (7 วันล่าสุด) ===
if ($action === 'attendance_weekly') {
    $stmt = $pdo->query("
        SELECT 
            al.date,
            SUM(CASE WHEN al.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN al.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN al.status = 'leave' THEN 1 ELSE 0 END) as leave_count,
            SUM(CASE WHEN al.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            COUNT(*) as total
        FROM attendance_log al
        WHERE al.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY al.date
        ORDER BY al.date ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $present = [];
    $late = [];
    $leave = [];
    $absent = [];
    
    // Thai day names
    $thaiDays = ['Sun'=>'อา.','Mon'=>'จ.','Tue'=>'อ.','Wed'=>'พ.','Thu'=>'พฤ.','Fri'=>'ศ.','Sat'=>'ส.'];
    
    foreach ($rows as $r) {
        $dayAbbr = date('D', strtotime($r['date']));
        $dateStr = date('d/m', strtotime($r['date']));
        $labels[] = ($thaiDays[$dayAbbr] ?? $dayAbbr) . ' ' . $dateStr;
        $present[] = (int)$r['present_count'];
        $late[] = (int)$r['late_count'];
        $leave[] = (int)$r['leave_count'];
        $absent[] = (int)$r['absent_count'];
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'present' => $present,
        'late' => $late,
        'leave' => $leave,
        'absent' => $absent
    ]);
    exit();
}

// === สถิติการมาเรียนรวมทั้งหมด (สำหรับ Donut) ===
if ($action === 'attendance_summary') {
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            COUNT(*) as total
        FROM attendance_log
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'present' => (int)($row['present_count'] ?? 0),
        'late' => (int)($row['late_count'] ?? 0),
        'leave' => (int)($row['leave_count'] ?? 0),
        'absent' => (int)($row['absent_count'] ?? 0),
        'total' => (int)($row['total'] ?? 0)
    ]);
    exit();
}

// === ภาพรวมผลการเรียน (Grade Distribution) ===
if ($action === 'grade_distribution') {
    $stmt = $pdo->query("
        SELECT grade_level, COUNT(*) as cnt 
        FROM grades 
        WHERE grade_level IS NOT NULL 
        GROUP BY grade_level 
        ORDER BY grade_level DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $distribution = [
        'grade_4' => (int)($rows[4] ?? 0),
        'grade_3' => (int)($rows[3] ?? 0),
        'grade_2' => (int)($rows[2] ?? 0),
        'grade_1' => (int)($rows[1] ?? 0),
        'grade_0' => (int)($rows[0] ?? 0)
    ];
    $distribution['total'] = array_sum($distribution);
    
    echo json_encode([
        'success' => true,
        'distribution' => $distribution
    ]);
    exit();
}

// === ผลการเรียนเฉลี่ยรายวิชา (Bar chart) ===
if ($action === 'grade_by_subject') {
    $stmt = $pdo->query("
        SELECT s.subject_code, s.name as subject_name,
               ROUND(AVG(g.grade_level), 2) as avg_grade,
               COUNT(g.id) as student_count
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        WHERE g.grade_level IS NOT NULL
        GROUP BY g.subject_id, s.subject_code, s.name
        ORDER BY s.subject_code ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $averages = [];
    $counts = [];
    
    foreach ($rows as $r) {
        $labels[] = $r['subject_code'];
        $averages[] = (float)$r['avg_grade'];
        $counts[] = (int)$r['student_count'];
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'averages' => $averages,
        'counts' => $counts
    ]);
    exit();
}

// === กิจกรรมล่าสุด (Recent Activity) ===
if ($action === 'recent_activity') {
    // Last 5 attendance records
    $stmt = $pdo->query("
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
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
