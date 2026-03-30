<nav id="sidebar" class="sidebar">
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
                <a href="index.php" class="nav-link">
                    <i class="fas fa-chart-pie me-3"></i><span class="sidebar-text">Dashboard</span>
                </a>
            </li>

            <!-- Module 1: Master Data -->
            <li>
                <a href="#masterSubmenu" data-bs-toggle="collapse" aria-expanded="false"
                    class="dropdown-toggle nav-link w-100 d-flex justify-content-between align-items-center">
                    <div><i class="fas fa-database me-3"></i><span class="sidebar-text">ข้อมูลพื้นฐาน</span></div>
                    <i class="fas fa-chevron-down ms-auto sidebar-text"
                        style="font-size: 0.8rem; transition: transform 0.3s;"></i>
                </a>
                <ul class="collapse list-unstyled sub-menu" id="masterSubmenu">
                    <li><a href="master-teacher.php" class="nav-link"><i
                                class="fas fa-chalkboard-teacher me-2"></i>ข้อมูลครู</a></li>
                    <li><a href="master-student.php" class="nav-link"><i
                                class="fas fa-user-graduate me-2"></i>ข้อมูลนักเรียน</a></li>
                    <li><a href="master-subject.php" class="nav-link"><i class="fas fa-book me-2"></i>ข้อมูลรายวิชา</a></li>
                    <li><a href="master-class.php" class="nav-link"><i
                                class="fas fa-layer-group me-2"></i>ข้อมูลระดับชั้น</a></li>
                    <li><a href="master-classroom.php" class="nav-link"><i
                                class="fas fa-door-open me-2"></i>ข้อมูลห้องเรียน</a></li>
                </ul>
            </li>
        <?php endif; ?>

        <!-- Module 2: Schedule -->
        <li>
            <a href="schedule.php" class="nav-link">
                <i class="fas fa-calendar-alt me-3"></i><span class="sidebar-text">จัดการตารางเรียน</span>
            </a>
        </li>

        <!-- Module 3: Grading -->
        <li>
            <a href="grading.php" class="nav-link">
                <i class="fas fa-star-half-alt me-3"></i><span class="sidebar-text">บันทึกผลการเรียน</span>
            </a>
        </li>

        <!-- Module 5: Attendance -->
        <li>
            <a href="attendance.php" class="nav-link">
                <i class="fas fa-clipboard-user me-3"></i><span class="sidebar-text">บันทึกเวลาเรียน</span>
            </a>
        </li>

    </ul>
</nav>