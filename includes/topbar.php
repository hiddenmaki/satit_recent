<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm-light py-2 px-4 topbar">
    <div class="container-fluid px-0">
        <button type="button" id="sidebarCollapse"
            class="btn btn-light d-flex align-items-center justify-content-center sidebar-toggle-btn shadow-sm">
            <i class="fas fa-bars text-secondary"></i>
        </button>

        <!-- Search or contextual info -->
        <div class="d-none d-md-flex ms-4">
            <h5 class="mb-0 text-dark fw-semibold page-title">
                <?php
                if (isset($_SESSION['role'])) {
                    if ($_SESSION['role'] === 'admin')
                        echo 'ระบบผู้ดูแล (Admin Dashboard)';
                    elseif ($_SESSION['role'] === 'teacher')
                        echo 'ระบบครูผู้สอน (Teacher Portal)';
                    elseif ($_SESSION['role'] === 'student')
                        echo 'ระบบนักเรียน (Student Portal)';
                } else {
                    echo 'ระบบบริหารจัดการสถานศึกษา';
                }
                ?>
            </h5>
        </div>

        <div class="collapse navbar-collapse justify-content-end" id="navbarSupportedContent">
            <ul class="nav navbar-nav ms-auto align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center profile-dropdown" href="#"
                        id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle bg-primary-light text-primary fw-bold me-2">
                            <?= isset($_SESSION['full_name']) ? mb_substr($_SESSION['full_name'], 0, 1, 'UTF-8') : 'U' ?>
                        </div>
                        <span class="d-none d-md-block text-dark fw-medium">
                            <?= htmlspecialchars($_SESSION['full_name'] ?? 'ผู้ใช้งาน') ?>
                            <span class="badge bg-secondary ms-1 fw-light" style="font-size: 0.7em;">
                                <?= ucfirst($_SESSION['role'] ?? '') ?>
                            </span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item py-2 text-muted" href="profile.php"><i
                                    class="fas fa-user-circle me-2 text-primary"></i> โปรไฟล์ส่วนตัว</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i
                                    class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>