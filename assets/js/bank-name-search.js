(function () {
    'use strict';

    function key(value) {
        return String(value || '').toLowerCase().replace(/[’`]/g, "'").replace(/[^a-z0-9]+/g, '');
    }

    function banks() {
        return Array.isArray(window.SRI_LANKA_BANKS) ? window.SRI_LANKA_BANKS : [];
    }

    function matches(bank, query) {
        var needle = key(query);
        if (needle === '') {
            return true;
        }
        if (key(bank.name).indexOf(needle) !== -1) {
            return true;
        }
        var aliases = bank.aliases || [];
        for (var i = 0; i < aliases.length; i++) {
            if (key(aliases[i]).indexOf(needle) !== -1 || needle.indexOf(key(aliases[i])) !== -1) {
                return true;
            }
        }
        return false;
    }

    function bind(root) {
        var query = root.querySelector('[data-bank-query]');
        var value = root.querySelector('[data-bank-value]');
        var list = root.querySelector('[data-bank-list]');
        if (!query || !value || !list || query.dataset.bankBound === '1') {
            return;
        }
        query.dataset.bankBound = '1';
        var active = -1;

        function choose(name) {
            query.value = name;
            value.value = name;
            close();
        }

        function close() {
            list.hidden = true;
            list.innerHTML = '';
            query.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function paint() {
            var found = banks().filter(function (bank) {
                return matches(bank, query.value);
            });
            list.innerHTML = '';
            active = -1;
            if (!found.length) {
                var empty = document.createElement('li');
                empty.className = 'bank-search-empty';
                empty.textContent = 'No matching bank. Choose one from the list.';
                list.appendChild(empty);
            }
            found.forEach(function (bank) {
                var item = document.createElement('li');
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = bank.name;
                button.setAttribute('role', 'option');
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(bank.name);
                });
                item.appendChild(button);
                list.appendChild(item);
            });
            list.hidden = false;
            query.setAttribute('aria-expanded', 'true');
        }

        function move(step) {
            var buttons = list.querySelectorAll('button');
            if (!buttons.length) {
                return;
            }
            active = (active + step + buttons.length) % buttons.length;
            buttons.forEach(function (button, index) {
                button.setAttribute('aria-selected', index === active ? 'true' : 'false');
            });
            buttons[active].scrollIntoView({ block: 'nearest' });
        }

        query.addEventListener('focus', paint);
        query.addEventListener('input', function () {
            value.value = '';
            paint();
        });
        query.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (list.hidden) {
                    paint();
                }
                move(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                move(-1);
            } else if (event.key === 'Enter' && active >= 0) {
                var buttons = list.querySelectorAll('button');
                if (buttons[active]) {
                    event.preventDefault();
                    choose(buttons[active].textContent || '');
                }
            } else if (event.key === 'Escape') {
                close();
            }
        });
        query.addEventListener('blur', function () {
            window.setTimeout(close, 120);
        });
        var form = root.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (value.value === '' && query.value !== '') {
                    var typed = key(query.value);
                    banks().some(function (bank) {
                        var names = [bank.name].concat(bank.aliases || []);
                        var hit = names.some(function (name) {
                            return key(name) === typed;
                        });
                        if (hit) {
                            value.value = bank.name;
                            query.value = bank.name;
                        }
                        return hit;
                    });
                }
            });
        }
    }

    function mount() {
        document.querySelectorAll('[data-bank-search]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }
}());
