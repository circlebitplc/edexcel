document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.l4-tile').forEach(function (tile, i) {
        tile.style.transitionDelay = (i * 40) + 'ms';
        tile.classList.add('is-in');
    });
});
