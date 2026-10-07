(function () {
    'use strict';

    if (window.__ECK_APP_RAIL_BOUND__) return;
    window.__ECK_APP_RAIL_BOUND__ = true;

    var STORAGE_KEY = 'eck-app-rail-open';
    var body = document.body;
    var rail = document.getElementById('appRail');
    var brand = document.getElementById('appRailBrand');
    var mark = document.getElementById('appRailMark');
    var toggle = document.getElementById('appRailToggle');

    if (!body || !body.classList.contains('has-app-rail') || !rail) {
        return;
    }

    function isOpen() {
        return body.classList.contains('app-rail-open');
    }

    function setOpen(open) {
        body.classList.toggle('app-rail-open', open);
        if (mark) {
            mark.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Collapse menu' : 'Expand menu');
        }
        try {
            localStorage.setItem(STORAGE_KEY, open ? '1' : '0');
        } catch (e) {
            /* ignore quota / private mode */
        }
        if (open) {
            scrollActiveIntoView();
        }
    }

    function toggleOpen() {
        setOpen(!isOpen());
    }

    function scrollActiveIntoView() {
        var active = rail.querySelector('.app-rail-link.is-active');
        if (!active || typeof active.scrollIntoView !== 'function') {
            return;
        }
        try {
            active.scrollIntoView({ block: 'nearest' });
        } catch (e) {
            /* ignore */
        }
    }

    function readSaved() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    var saved = readSaved();
    if (saved === '1') {
        setOpen(true);
    } else if (saved === '0') {
        setOpen(false);
    } else if (rail.classList.contains('app-rail-student') && window.matchMedia('(min-width: 768px)').matches) {
        setOpen(true);
    }

    if (brand) {
        brand.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleOpen();
        });
    }

    if (toggle) {
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleOpen();
        });
    }

    rail.addEventListener('click', function (event) {
        if (isOpen()) {
            return;
        }
        if (event.target.closest('a.app-rail-link')) {
            return;
        }
        setOpen(true);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            if (toggle) toggle.focus();
        }
    });

    document.addEventListener('click', function (event) {
        if (!isOpen() || !window.matchMedia('(max-width: 575.98px)').matches) {
            return;
        }
        if (rail.contains(event.target) || (toggle && toggle.contains(event.target))) {
            return;
        }
        setOpen(false);
    });
})();
