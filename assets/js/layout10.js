document.addEventListener('DOMContentLoaded', function () {
    var out = document.getElementById('node-out');
    document.querySelectorAll('.l10-node[data-node]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.l10-node').forEach(function (n) { n.classList.remove('is-on'); });
            btn.classList.add('is-on');
            document.querySelector('.l10-core')?.classList.add('is-on');
            if (out) {
                var name = btn.textContent.trim();
                out.textContent = name === 'Student'
                    ? 'Student hub online. Linked to ICT, Mathematics, Physics, Science and Business pathways.'
                    : 'Student connected to ' + name + '. Open the student portal for timetable and recordings.';
            }
        });
    });
});
