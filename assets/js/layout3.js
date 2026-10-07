document.addEventListener('DOMContentLoaded', function () {
    var cards = document.querySelectorAll('.teacher-profile-card[data-teacher-profile]');
    cards.forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            var url = card.getAttribute('data-teacher-profile');
            if (url) window.location.href = url;
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var url = card.getAttribute('data-teacher-profile');
                if (url) window.location.href = url;
            }
        });
    });
});
