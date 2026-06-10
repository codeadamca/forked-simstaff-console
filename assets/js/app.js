// Auto-dismiss alerts after 4 seconds
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert');

    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.4s';

            setTimeout(function() {
                alert.remove();
            }, 400);
        }, 4000);
    });
});
