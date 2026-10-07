/**
 * Shared live search/filter for cards, rows, and lists.
 *
 * Scope: [data-live-scope]
 * Search: [data-live-search]
 * Items: [data-live-item]
 * Haystack: data-search (fallback: textContent)
 * Extra select: [data-live-select] with data-live-key="enrolled"
 *   matches item dataset[key]
 * Chip buttons: [data-live-chip] data-live-key data-live-value
 * Empty: [data-live-empty]
 * Count: [data-live-count]
 */
(function () {
    'use strict';

    function tokens(query) {
        return String(query || '')
            .trim()
            .toLowerCase()
            .split(/\s+/)
            .filter(Boolean);
    }

    function haystack(item) {
        return String(item.getAttribute('data-search') || item.textContent || '')
            .toLowerCase()
            .replace(/\s+/g, ' ');
    }

    function matchesTokens(text, toks) {
        if (!toks.length) {
            return true;
        }
        return toks.every(function (token) {
            return text.indexOf(token) !== -1;
        });
    }

    function initScope(scope) {
        const input = scope.querySelector('[data-live-search]');
        const items = Array.from(scope.querySelectorAll('[data-live-item]'));
        const empty = scope.querySelector('[data-live-empty]');
        const count = scope.querySelector('[data-live-count]');
        const selects = Array.from(scope.querySelectorAll('[data-live-select]'));
        const chips = Array.from(scope.querySelectorAll('[data-live-chip]'));
        const clearBtn = scope.querySelector('[data-live-clear]');

        if (!items.length) {
            return;
        }

        let timer = 0;

        function activeChipFilters() {
            const filters = {};
            chips.forEach(function (chip) {
                if (!chip.classList.contains('active')) {
                    return;
                }
                const key = chip.getAttribute('data-live-key');
                const value = chip.getAttribute('data-live-value') || '';
                if (key) {
                    filters[key] = value;
                }
            });
            return filters;
        }

        function apply() {
            const toks = tokens(input ? input.value : '');
            const chipFilters = activeChipFilters();
            let visible = 0;

            items.forEach(function (item) {
                if (!item.isConnected) {
                    return;
                }
                let show = matchesTokens(haystack(item), toks);

                selects.forEach(function (select) {
                    const key = select.getAttribute('data-live-key');
                    const value = String(select.value || '').toLowerCase();
                    if (!key || !value || value === 'all') {
                        return;
                    }
                    const itemVal = String(item.getAttribute('data-' + key) || '').toLowerCase();
                    if (itemVal.indexOf(value) === -1) {
                        show = false;
                    }
                });

                Object.keys(chipFilters).forEach(function (key) {
                    const wanted = chipFilters[key];
                    if (wanted === '' || wanted === 'all') {
                        return;
                    }
                    const itemVal = String(item.getAttribute('data-' + key) || '');
                    if (itemVal !== wanted) {
                        show = false;
                    }
                });

                item.classList.toggle('d-none', !show);
                item.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (empty) {
                empty.classList.toggle('d-none', visible !== 0);
                empty.hidden = visible !== 0;
                if (visible) {
                    empty.style.display = 'none';
                } else {
                    empty.style.removeProperty('display');
                }
            }

            if (count) {
                count.textContent = String(visible);
            }

            const totalEl = scope.querySelector('[data-live-total]');
            if (totalEl) {
                const totalCount = items.filter(function (it) { return it.isConnected; }).length;
                totalEl.textContent = String(totalCount);
            }
            const pluralEl = scope.querySelector('[data-live-plural]');
            if (pluralEl) {
                const totalCount = items.filter(function (it) { return it.isConnected; }).length;
                pluralEl.textContent = totalCount === 1 ? '' : 's';
            }
        }

        scope.addEventListener('live-filter:refresh', apply);

        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(apply, 60);
        }

        if (input) {
            input.addEventListener('input', schedule);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    input.value = '';
                    apply();
                }
            });
        }

        selects.forEach(function (select) {
            select.addEventListener('change', apply);
        });

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                const key = chip.getAttribute('data-live-key');
                const group = chip.closest('.btn-group');
                chips.forEach(function (other) {
                    const sameKey = other.getAttribute('data-live-key') === key;
                    const sameGroup = group && other.closest('.btn-group') === group;
                    if (sameKey || sameGroup) {
                        other.classList.remove('active');
                    }
                });
                chip.classList.add('active');
                apply();
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (input) {
                    input.value = '';
                }
                selects.forEach(function (select) {
                    select.value = '';
                });
                chips.forEach(function (chip) {
                    chip.classList.toggle('active', (chip.getAttribute('data-live-value') || 'all') === 'all');
                });
                apply();
                if (input) {
                    input.focus();
                }
            });
        }

        apply();
    }

    function boot() {
        document.querySelectorAll('[data-live-scope]').forEach(initScope);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
