$(document).ready(function () {
    // Toggle Sidebar
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
        
        // Handle dropdowns when sidebar collapses
        if ($('#sidebar').hasClass('active')) {
            $('.sub-menu.collapse.show').removeClass('show');
            $('.dropdown-toggle').attr('aria-expanded', 'false');
        }
    });

    // Mobile Sidebar Handling
    if ($(window).width() <= 768) {
        $('#sidebar').addClass('active');
    }

    $(window).resize(function() {
        if ($(window).width() <= 768) {
            $('#sidebar').addClass('active');
        } else {
            $('#sidebar').removeClass('active');
        }
    });

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
