document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('[data-l8-play]');
    var video = document.getElementById('l8-video');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (video) {
            if (video.paused) { video.play(); btn.innerHTML = '<i class="fas fa-pause"></i>'; }
            else { video.pause(); btn.innerHTML = '<i class="fas fa-play"></i>'; }
            return;
        }
        document.getElementById('hero')?.scrollIntoView({ behavior: 'smooth' });
    });
});
