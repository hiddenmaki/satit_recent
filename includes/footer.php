</div> <!-- สิ้นสุดเนื้อหาหลัก (Page Content) -->

<!-- ส่วนท้ายหน้า (Footer Section) -->
<footer class="footer mt-auto py-3 bg-white border-top">
    <div class="container-fluid">
        <div class="row text-muted">
            <div class="col-6 text-start">
                <p class="mb-0">
                    <a href="#" class="text-muted text-decoration-none"><strong>โรงเรียนสาธิตวิทยา</strong></a> &copy;
                    2024
                </p>
            </div>
            <div class="col-6 text-end">
                <!-- ตรวจสอบบทบาทผู้ใช้งาน: ถ้าเป็นครู ให้แสดงสังกัดหมวดวิชา -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher'): ?>
                    <?php
                    // ดึงชื่อหมวดวิชาจากฐานข้อมูล โดยจอยตาราง teachers และ departments
                    $stmtDeptInfo = $pdo->prepare("
                        SELECT d.name as dept_name 
                        FROM teachers t 
                        JOIN departments d ON t.department_id = d.id 
                        WHERE t.user_id = ?
                    ");
                    $stmtDeptInfo->execute([$_SESSION['user_id']]);
                    $deptInfo = $stmtDeptInfo->fetch();
                    ?>
                    <span class="text-muted small">
                        <i class="fas fa-chalkboard-teacher me-1"></i>
                        สังกัด: <strong><?= htmlspecialchars($deptInfo['dept_name'] ?? 'ไม่ระบุ') ?></strong>
                    </span>
                <?php else: ?>
                    <!-- ถ้าไม่ใช่ครู ให้แสดงเวอร์ชันของระบบแทน -->
                    <span class="text-muted small">ระบบจัดการโรงเรียน v1.0</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

</div> <!-- ปิด Main Panel (จาก header.php) -->
</div> <!-- ปิด Wrapper (จาก header.php) -->

<!-- นำเข้าไลบรารี JavaScript ที่จำเป็น -->
<!-- 1. jQuery สำหรับจัดการ DOM และ AJAX -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- 2. Bootstrap 5 JS สำหรับ UI Component (เช่น Modal, Dropdown) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- 3. ไฟล์ Script หลักของโปรเจคเราเอง -->
<script src="assets/js/script.js?v=<?= time() ?>"></script>

</body>
</html>