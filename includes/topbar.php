<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm-light py-2 px-4 topbar">
    <div class="container-fluid px-0">
        <button type="button" id="sidebarCollapse"
            class="btn btn-light d-flex align-items-center justify-content-center sidebar-toggle-btn shadow-sm">
            <i class="fas fa-bars text-secondary"></i>
        </button>

        <!-- Search or contextual info -->
        <div class="d-none d-md-flex ms-4">
            <h5 class="mb-0 text-dark fw-semibold page-title">ระบบผู้ดูแล (Admin Dashboard)</h5>
        </div>

        <div class="collapse navbar-collapse justify-content-end" id="navbarSupportedContent">
            <ul class="nav navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 dropdown notifications">
                    <a class="nav-link text-secondary position-relative" href="#" id="alertsDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fs-5"></i>
                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white"
                            style="font-size: 0.6rem;">
                            3
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center profile-dropdown" href="#"
                        id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle bg-primary-light text-primary fw-bold me-2">อ</div>
                        <span class="d-none d-md-block text-dark fw-medium">ผู้ดูแลระบบ</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item py-2 text-muted" href="profile.php"><i
                                    class="fas fa-user-circle me-2 text-primary"></i> โปรไฟล์ส่วนตัว</a></li>
                        <li><a class="dropdown-item py-2 text-muted" href="#"><i
                                    class="fas fa-cogs me-2 text-primary"></i> ตั้งค่า</a></li>
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