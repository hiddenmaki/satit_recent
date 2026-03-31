$(document).ready(function () {
    // Restore sidebar state from localStorage BEFORE anything else
    if (localStorage.getItem('sidebarState') === 'collapsed') {
        $('#sidebar').addClass('active');
    } else {
        $('#sidebar').removeClass('active');
    }

    // Toggle Sidebar
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
        
        // Save state
        if ($('#sidebar').hasClass('active')) {
            localStorage.setItem('sidebarState', 'collapsed');
            document.cookie = "sidebarState=collapsed; path=/; max-age=31536000";
            
            // Handle dropdowns when sidebar collapses
            $('.sub-menu.collapse.show').removeClass('show');
            $('.dropdown-toggle').attr('aria-expanded', 'false');
        } else {
            localStorage.setItem('sidebarState', 'expanded');
            document.cookie = "sidebarState=expanded; path=/; max-age=31536000";
        }
    });

    // Mobile Sidebar Handling (Initialize only on load)
    if ($(window).width() <= 768) {
        $('#sidebar').addClass('active');
    }

    // Removing the aggressive window.resize function because it causes the sidebar 
    // to unexpectedly disappear when users try to resize their browser window on desktop or rotate their phone.

    // Set Active Menu Target based on current URL
    var currentUrl = window.location.pathname.split('/').pop();
    if(currentUrl === '') currentUrl = 'index.php'; // Default fallback
    
    $('.sidebar ul li a').each(function() {
        var href = $(this).attr('href');
        if (href === currentUrl) {
            $(this).parent('li').addClass('active');
            
            // If it's inside a submenu, open the parent submenu
            var submenu = $(this).closest('.sub-menu');
            if(submenu.length > 0) {
                submenu.addClass('show');
                submenu.prev('.dropdown-toggle').attr('aria-expanded', 'true');
            }
        } else {
             $(this).parent('li').removeClass('active');
        }
    });
});
