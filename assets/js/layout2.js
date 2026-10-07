document.addEventListener('DOMContentLoaded', function () {
    var links = document.querySelectorAll('.l2-side nav a[href^="#"]');
    links.forEach(function (a) {
        a.addEventListener('click', function () {
            links.forEach(function (x) { x.classList.remove('is-on'); });
            a.classList.add('is-on');
        });
    });
    var search = document.querySelector('[data-l2-search]');
    var people = document.querySelectorAll('[data-l2-name]');
    if (search && people.length) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim();
            people.forEach(function (el) {
                el.hidden = q !== '' && (el.getAttribute('data-l2-name') || '').indexOf(q) === -1;
            });
        });
    }
});
