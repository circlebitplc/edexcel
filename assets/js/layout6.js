document.addEventListener('DOMContentLoaded', function () {
    var panels = {
        home: document.getElementById('home'),
        courses: document.getElementById('courses'),
        timetable: document.getElementById('timetable'),
        teachers: document.getElementById('teachers')
    };
    var tabs = document.querySelectorAll('[data-l6-tab]');
    function show(name) {
        Object.keys(panels).forEach(function (key) {
            if (panels[key]) panels[key].hidden = key !== name;
        });
        tabs.forEach(function (t) {
            t.classList.toggle('is-on', t.getAttribute('data-l6-tab') === name);
        });
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function (e) {
            var name = t.getAttribute('data-l6-tab');
            if (!name || !panels[name]) return;
            e.preventDefault();
            show(name);
        });
    });
    var hash = (window.location.hash || '#home').slice(1);
    if (panels[hash]) show(hash);
});
