<?php
$sidebarClass = 'sidebar';
if (isset($_COOKIE['sidebarState']) && $_COOKIE['sidebarState'] === 'collapsed') {
    $sidebarClass .= ' active';
}
?>
<nav id="sidebar" class="<?= $sidebarClass ?>">
    <div class="sidebar-header d-flex flex-column align-items-center justify-content-center py-4">
        <div class="logo-icon mb-3">
            <img src="assets/images/logo.png" alt="Satit School Logo" class="img-fluid rounded-circle shadow-sm"
                style="max-width: 80px; border: 3px solid rgba(255,255,255,0.2);">
        </div>
        <h5 class="mb-0 fw-bold sidebar-text text-white text-center" style="letter-spacing: 1px;">สาธิตวิทยา</h5>
        <small class="text-white-50 sidebar-text" style="font-size: 0.75rem; letter-spacing: 1px;">ELITE ACADEMY</small>
    </div>

    <ul class="list-unstyled components mb-5">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="active">
                <a href="index.php" class="nav-link" title="Dashboard">
                    <i class="fas fa-chart-pie me-3"></i><span class="sidebar-text">Dashboard</span>
                </a>
            </li>

            <!-- Module 1: Master Data -->
            <li>
                <a href="#masterSubmenu" data-bs-toggle="collapse" aria-expanded="false"
                    class="dropdown-toggle nav-link collapse-link" title="ข้อมูลพื้นฐาน">
                    <i class="fas fa-database me-3"></i><span class="sidebar-text flex-grow-1 text-start">ข้อมูลพื้นฐาน</span>
                    <i class="fas fa-chevron-down sidebar-text ms-auto" style="font-size: 0.8rem; transition: transform 0.3s;"></i>
                </a>
                <ul class="collapse list-unstyled sub-menu" id="masterSubmenu">
                    <li><a href="master-teacher.php" class="nav-link" title="ข้อมูลครู"><i
                                class="fas fa-chalkboard-teacher me-2"></i><span class="sidebar-text">ข้อมูลครู</span></a></li>
                    <li><a href="master-student.php" class="nav-link" title="ข้อมูลนักเรียน"><i
                                class="fas fa-user-graduate me-2"></i><span class="sidebar-text">ข้อมูลนักเรียน</span></a></li>
                    <li><a href="master-subject.php" class="nav-link" title="ข้อมูลรายวิชา"><i class="fas fa-book me-2"></i><span class="sidebar-text">ข้อมูลรายวิชา</span></a></li>
                    <li><a href="master-class.php" class="nav-link" title="ข้อมูลระดับชั้น"><i
                                class="fas fa-layer-group me-2"></i><span class="sidebar-text">ข้อมูลระดับชั้น</span></a></li>
                    <li><a href="master-classroom.php" class="nav-link" title="ข้อมูลห้องเรียน"><i
                                class="fas fa-door-open me-2"></i><span class="sidebar-text">ข้อมูลห้องเรียน</span></a></li>
                </ul>
            </li>
        <?php endif; ?>

        <!-- Module 2: Schedule -->
        <li>
            <a href="schedule.php" class="nav-link" title="จัดการตารางเรียน">
                <i class="fas fa-calendar-alt me-3"></i><span class="sidebar-text">จัดการตารางเรียน</span>
            </a>
        </li>

        <!-- Module 3: Grading -->
        <li>
            <a href="grading.php" class="nav-link" title="บันทึกผลการเรียน">
                <i class="fas fa-star-half-alt me-3"></i><span class="sidebar-text">บันทึกผลการเรียน</span>
            </a>
        </li>

        <!-- Module 5: Attendance -->
        <li>
            <a href="attendance.php" class="nav-link" title="บันทึกเวลาเรียน">
                <i class="fas fa-clipboard-user me-3"></i><span class="sidebar-text">บันทึกเวลาเรียน</span>
            </a>
        </li>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <!-- Module 6: User Account Management (Admin Only) -->
        <li>
            <a href="manage-users.php" class="nav-link" title="จัดการบัญชีผู้ใช้">
                <i class="fas fa-users-cog me-3"></i><span class="sidebar-text">จัดการบัญชีผู้ใช้</span>
            </a>
        </li>
        <?php endif; ?>

    </ul>
</nav>