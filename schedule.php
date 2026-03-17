<?php
// schedule.php - Schedule Management
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Default Filters
$filter_class_id = $_GET['class_id'] ?? '';
$filter_classroom_id = $_GET['classroom_id'] ?? '';

// Determine what to show based on role
$showSchedule = false;
$scheduleData = [];
$targetClassroomName = "";

if ($role === 'student') {
    // Determine student's classroom
    $stmt = $pdo->prepare("SELECT classroom_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    if ($student && $student['classroom_id']) {
        $filter_classroom_id = $student['classroom_id'];
        $showSchedule = true;
    }
} else if ($role === 'teacher') {
    // Determine teacher's schedule (we'll show all classes they teach for now, filterable later if needed)
    $showSchedule = true;
    // For simplicity in this UI, we might still force them to pick a class if they want a specific room view.
    // Or we show *their* master schedule. Let's stick to the classroom view for consistency, but pre-fill if they have one.
} else if ($role === 'admin') {
    if (!empty($filter_classroom_id)) {
        $showSchedule = true;
    }
}

// Fetch Schedule Data if needed
if ($showSchedule && !empty($filter_classroom_id)) {
    $stmt = $pdo->prepare("
        SELECT ts.*, s.name as subject_name, s.subject_code, 
               t.teacher_code, u.first_name, u.last_name, 
               c.room_name 
        FROM teaching_schedule ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN teachers t ON ts.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN classrooms c ON ts.classroom_id = c.id
        WHERE ts.classroom_id = ?
        ORDER BY FIELD(ts.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), ts.start_time ASC
    ");
    $stmt->execute([$filter_classroom_id]);
    $scheduleDataRaw = $stmt->fetchAll();
    
    // Get Classroom Name for Header
    $stmtC = $pdo->prepare("SELECT room_name FROM classrooms WHERE id = ?");
    $stmtC->execute([$filter_classroom_id]);
    $targetClassroomName = $stmtC->fetchColumn();
    
    // Group by Day -> Array of Slots
    foreach($scheduleDataRaw as $row) {
        $day = $row['day_of_week'];
        if(!isset($scheduleData[$day])) $scheduleData[$day] = [];
        $scheduleData[$day][] = $row;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-dark fw-bold mb-0">ตารางเรียน / ตารางสอน</h3>
        <p class="text-muted mb-0">ดูหรือกำหนดตารางเวลาเรียน (ภาคเรียนที่ 1/2567)</p>
    </div>
    <?php if ($role === 'admin') : ?>
    <div>
        <button class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class="fas fa-plus me-2"></i>จัดตารางสอนใหม่
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Filters (Visible to Admin/Teacher to select a specific class to view) -->
<?php if ($role !== 'student') : ?>
<div class="card shadow-sm-light mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i>เลือกห้องเรียนเพื่อดูตาราง</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="schedule.php" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">ห้องเรียน</label>
                <select class="form-select bg-light" name="classroom_id" required>
                    <option value="">เลือกห้องเรียน...</option>
                    <?php
                    $rooms = $pdo->query("SELECT c.id, c.room_name, cl.level_name FROM classrooms c JOIN classes cl ON c.class_id = cl.id ORDER BY cl.level_name ASC, c.room_name ASC")->fetchAll();
                    foreach ($rooms as $r) {
                        $sel = ($r['id'] == $filter_classroom_id) ? 'selected' : '';
                        echo "<option value='{$r['id']}' {$sel}>{$r['level_name']} - {$r['room_name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary shadow-sm" style="background-color: var(--accent-color); border: none;">
                    <i class="fas fa-search me-2"></i>แสดงตาราง
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($showSchedule && !empty($filter_classroom_id)) : ?>
<div class="card shadow-sm-light border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-dark">ตารางเรียน: <?= htmlspecialchars($targetClassroomName) ?></h6>
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>พิมพ์ตาราง</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive text-center">
            <table class="table table-bordered mb-0 table-custom">
                <thead class="bg-light">
                    <tr>
                        <th width="10%">วัน/เวลา</th>
                        <th width="15%">08:30 - 10:10<br>(คาบ 1-2)</th>
                        <th width="15%">10:10 - 12:00<br>(คาบ 3-4)</th>
                        <th width="10%">12:00 - 13:00</th>
                        <th width="15%">13:00 - 14:40<br>(คาบ 5-6)</th>
                        <th width="15%">14:40 - 16:20<br>(คาบ 7-8)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days = ['Monday' => 'จันทร์', 'Tuesday' => 'อังคาร', 'Wednesday' => 'พุธ', 'Thursday' => 'พฤหัสบดี', 'Friday' => 'ศุกร์'];
                    
                    foreach ($days as $engDay => $thaiDay) {
                        echo "<tr>";
                        echo "<td class='align-middle fw-bold bg-light'>{$thaiDay}</td>";
                        
                        // Just a simplified 4-slot mockup logic for presentation based on our DB times
                        // In a real complex app, you'd iterate through strict time slots and find overlapping DB entries
                        $slots = [
                            ['08:30:00', '10:10:00'],
                            ['10:10:00', '12:00:00'],
                            ['12:00:00', '13:00:00', 'break'], // Lunch
                            ['13:00:00', '14:40:00'],
                            ['14:40:00', '16:20:00']
                        ];
                        
                        $dayData = $scheduleData[$engDay] ?? [];
                        
                        foreach ($slots as $idx => $slot) {
                            if (isset($slot[2]) && $slot[2] == 'break') {
                                if($idx == 2) echo "<td class='align-middle bg-light text-muted fw-bold' rowspan='5' style='writing-mode: vertical-rl; text-orientation: mixed;'>พักกลางวัน</td>"; // Only print once, CSS handles rowspans usually differently, but for this simple table we'll just put it. Wait, rowspan on a td across trs needs it on the first tr only. Let's simplify.
                                if($engDay == 'Monday') echo "<td class='align-middle bg-light text-muted fw-bold' rowspan='5'>พัก<br>กลาง<br>วัน</td>";
                                continue;
                            }
                            
                            $found = false;
                            foreach($dayData as $d) {
                                if($d['start_time'] == $slot[0] && $d['end_time'] == $slot[1]) {
                                    $bg = 'var(--accent-color)';
                                    if(strpos($d['subject_code'], 'ค') !== false) $bg = 'var(--bs-primary)';
                                    if(strpos($d['subject_code'], 'อ') !== false) $bg = 'var(--bs-warning)';
                                    
                                    echo "<td>
                                        <div class='p-2 border rounded bg-white shadow-sm' style='border-left: 3px solid {$bg} !important;'>
                                            <div class='fw-bold text-dark small'>" . htmlspecialchars($d['subject_code']) . " " . htmlspecialchars($d['subject_name']) . "</div>
                                            <div class='text-muted' style='font-size:0.75rem;'>ครู" . htmlspecialchars($d['first_name']) . "</div>
                                        </div>
                                    </td>";
                                    $found = true;
                                    break;
                                }
                            }
                            
                            if(!$found) {
                                echo "<td><div class='p-2 text-muted small border-0'></div></td>";
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
<?php elseif($role !== 'student') : ?>
<div class="alert alert-info border-0 shadow-sm"><i class="fas fa-info-circle me-2"></i>กรุณาเลือกห้องเรียนเพื่อตรวจสอบตารางสอน</div>
<?php else : ?>
<div class="alert alert-warning border-0 shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i>ไม่พบข้อมูลตารางเรียนของคุณ กรุณาติดต่อผู้ดูแลระบบ</div>
<?php endif; ?>

<!-- Admin Add Schedule Modal -->
<?php if ($role === 'admin') : ?>
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold">จัดตารางสอนใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="api/schedule_actions.php" method="POST">
          <input type="hidden" name="action" value="create">
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label small text-muted">ปีการศึกษา / เทอม</label>
                  <div class="input-group">
                      <input type="text" name="academic_year" class="form-control bg-light" value="2567" required>
                      <span class="input-group-text">/</span>
                      <input type="number" name="semester" class="form-control bg-light" value="1" min="1" max="2" required>
                  </div>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">วิชา</label>
                  <select name="subject_id" class="form-select bg-light" required>
                      <?php
                      $subs = $pdo->query("SELECT id, subject_code, name FROM subjects")->fetchAll();
                      foreach($subs as $s) echo "<option value='{$s['id']}'>{$s['subject_code']} {$s['name']}</option>";
                      ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">ครูผู้สอน</label>
                  <select name="teacher_id" class="form-select bg-light" required>
                      <?php
                      $techs = $pdo->query("SELECT t.id, u.first_name, u.last_name FROM teachers t JOIN users u ON t.user_id = u.id")->fetchAll();
                      foreach($techs as $t) echo "<option value='{$t['id']}'>ครู{$t['first_name']} {$t['last_name']}</option>";
                      ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label small text-muted">ห้องเรียน</label>
                  <select name="classroom_id" class="form-select bg-light" required>
                      <?php
                      $allRooms = $pdo->query("SELECT c.id, c.room_name FROM classrooms c")->fetchAll();
                      foreach($allRooms as $r) echo "<option value='{$r['id']}'>{$r['room_name']}</option>";
                      ?>
                  </select>
              </div>
              <div class="row">
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">วัน</label>
                      <select name="day_of_week" class="form-select bg-light" required>
                          <option value="Monday">จันทร์</option>
                          <option value="Tuesday">อังคาร</option>
                          <option value="Wednesday">พุธ</option>
                          <option value="Thursday">พฤหัสบดี</option>
                          <option value="Friday">ศุกร์</option>
                      </select>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">เริ่ม</label>
                      <select name="start_time" class="form-select bg-light" required>
                          <option value="08:30:00">08:30 (คาบ 1)</option>
                          <option value="10:10:00">10:10 (คาบ 3)</option>
                          <option value="13:00:00">13:00 (คาบ 5)</option>
                          <option value="14:40:00">14:40 (คาบ 7)</option>
                      </select>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label small text-muted">สิ้นสุด</label>
                       <select name="end_time" class="form-select bg-light" required>
                          <option value="10:10:00">10:10 (คาบ 2)</option>
                          <option value="12:00:00">12:00 (คาบ 4)</option>
                          <option value="14:40:00">14:40 (คาบ 6)</option>
                          <option value="16:20:00">16:20 (คาบ 8)</option>
                      </select>
                  </div>
              </div>
          </div>
          <div class="modal-footer border-top-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary" style="background-color: var(--accent-color); border:none;">บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
