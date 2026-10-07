document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('campus-panel');
    var body = panel ? panel.querySelector('[data-campus-body]') : null;
    if (!panel || !body) return;
    function openCampus(key, el) {
        var tpl = document.getElementById('campus-' + key);
        if (!tpl) return;
        document.querySelectorAll('.l5-bldg').forEach(function (b) { b.classList.remove('is-on'); });
        if (el) el.classList.add('is-on');
        body.innerHTML = tpl.innerHTML;
        panel.hidden = false;
    }
    document.querySelectorAll('.l5-bldg, .l5-pin').forEach(function (el) {
        el.addEventListener('click', function () {
            var key = el.getAttribute('data-campus');
            var match = document.querySelector('.l5-bldg[data-campus="' + key + '"]');
            openCampus(key, match);
        });
    });
    document.querySelector('[data-close-campus]')?.addEventListener('click', function () {
        panel.hidden = true;
    });
});
