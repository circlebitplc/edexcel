/* ============================================================
   EDEXCEL COLLEGE PORTAL
   dashboard.js

   Consolidated portal JavaScript.

   Handles:
   - Dark / light mode
   - Toast notifications
   - CSRF setup
   - Bootstrap components
   - Safe AJAX helpers
   - Confirm dialogs
   - Mobile navigation
   - Table helpers
   - Timetable helpers
   - Form protection
   - No global buffering/loading overlay
============================================================ */

(function () {

    'use strict';


    /* ========================================================
       DOM READY
    ======================================================== */

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            initDarkMode();

            initBootstrapComponents();

            initCSRF();

            initConfirmActions();

            initMobileNavigation();

            initTooltips();

            initPopovers();

            initAutoDismissAlerts();

            initTableSearch();

            initCopyButtons();

            initFormProtection();

            initDateTimeFields();

            initSidebarState();

            initToastFromSession();

        }
    );


    /* ========================================================
       DARK / LIGHT MODE
    ======================================================== */

    function initDarkMode() {

        const toggle =
            document.getElementById(
                'darkModeToggle'
            );

        const icon =
            document.getElementById(
                'darkModeIcon'
            );

        const html =
            document.documentElement;


        if (!toggle) {
            return;
        }


        function applyTheme(
            dark
        ) {

            const theme =
                dark
                    ? 'dark'
                    : 'light';


            html.setAttribute(
                'data-bs-theme',
                theme
            );


            document.cookie =
                'dark_mode=' +
                (dark ? 'true' : 'false') +
                ';path=/;max-age=31536000;SameSite=Lax';


            if (icon) {

                icon.classList.remove(
                    'bi-moon-fill',
                    'bi-sun-fill'
                );


                icon.classList.add(
                    dark
                        ? 'bi-sun-fill'
                        : 'bi-moon-fill'
                );

            }


            toggle.checked =
                dark;
        }


        /*
         * Read the current HTML theme first.
         * This prevents a flash/change when the page loads.
         */

        const initialDark =
            html.getAttribute(
                'data-bs-theme'
            ) === 'dark';


        toggle.checked =
            initialDark;


        if (icon) {

            icon.classList.remove(
                'bi-moon-fill',
                'bi-sun-fill'
            );


            icon.classList.add(
                initialDark
                    ? 'bi-sun-fill'
                    : 'bi-moon-fill'
            );

        }


        toggle.addEventListener(
            'change',
            function () {

                applyTheme(
                    this.checked
                );

            }
        );

    }


    /* ========================================================
       BOOTSTRAP COMPONENTS
    ======================================================== */

    function initBootstrapComponents() {

        if (
            typeof bootstrap ===
            'undefined'
        ) {

            return;

        }


        /*
         * Bootstrap dropdowns.
         */

        document
            .querySelectorAll(
                '[data-bs-toggle="dropdown"]'
            )
            .forEach(
                function (element) {

                    try {

                        bootstrap.Dropdown
                            .getOrCreateInstance(
                                element
                            );

                    } catch (error) {

                        console.warn(
                            'Dropdown initialisation failed:',
                            error
                        );

                    }

                }
            );


        /*
         * Bootstrap collapses.
         */

        document
            .querySelectorAll(
                '[data-bs-toggle="collapse"]'
            )
            .forEach(
                function (element) {

                    try {

                        bootstrap.Collapse
                            .getOrCreateInstance(
                                element,
                                {
                                    toggle: false
                                }
                            );

                    } catch (error) {

                        console.warn(
                            'Collapse initialisation failed:',
                            error
                        );

                    }

                }
            );


        /*
         * Bootstrap modals.
         */

        document
            .querySelectorAll(
                '.modal'
            )
            .forEach(
                function (element) {

                    try {

                        bootstrap.Modal
                            .getOrCreateInstance(
                                element,
                                {
                                    backdrop: true
                                }
                            );

                    } catch (error) {

                        console.warn(
                            'Modal initialisation failed:',
                            error
                        );

                    }

                }
            );

    }


    /* ========================================================
       CSRF
    ======================================================== */

    function initCSRF() {

        const tokenElement =
            document.querySelector(
                'meta[name="csrf-token"]'
            );


        if (!tokenElement) {

            return;

        }


        const token =
            tokenElement.getAttribute(
                'content'
            );


        if (!token) {

            return;

        }


        /*
         * Make the token available globally.
         */

        window.EDX_CSRF_TOKEN =
            token;


        /*
         * Automatically add CSRF token to
         * same-origin POST forms that do not
         * already contain one.
         */

        document
            .querySelectorAll(
                'form'
            )
            .forEach(
                function (form) {

                    const method =
                        (
                            form.getAttribute(
                                'method'
                            ) || 'get'
                        ).toLowerCase();


                    if (
                        method !== 'post'
                    ) {

                        return;

                    }


                    if (
                        form.querySelector(
                            'input[name="csrf_token"]'
                        )
                    ) {

                        return;

                    }


                    const input =
                        document.createElement(
                            'input'
                        );


                    input.type =
                        'hidden';

                    input.name =
                        'csrf_token';

                    input.value =
                        token;


                    form.appendChild(
                        input
                    );

                }
            );

    }


    /* ========================================================
       GLOBAL FETCH HELPER
    ======================================================== */

    window.edxFetch =
        async function (
            url,
            options = {}
        ) {

            const requestOptions =
                {
                    credentials:
                        'same-origin',
                    ...options
                };


            requestOptions.headers =
                {
                    ...(options.headers || {})
                };


            /*
             * Add CSRF header for unsafe requests.
             */

            const method =
                (
                    requestOptions.method ||
                    'GET'
                ).toUpperCase();


            if (
                ['POST', 'PUT', 'PATCH', 'DELETE']
                    .includes(method)
            ) {

                const token =
                    window.EDX_CSRF_TOKEN;


                if (
                    token &&
                    !requestOptions.headers[
                        'X-CSRF-Token'
                    ]
                ) {

                    requestOptions.headers[
                        'X-CSRF-Token'
                    ] =
                        token;

                }

            }


            const response =
                await fetch(
                    url,
                    requestOptions
                );


            /*
             * Do not silently swallow HTTP errors.
             */

            if (
                !response.ok
            ) {

                throw new Error(
                    'Request failed: HTTP ' +
                    response.status
                );

            }


            return response;

        };


    /* ========================================================
       CONFIRM ACTIONS
    ======================================================== */

    function initConfirmActions() {

        document.addEventListener(
            'click',
            function (event) {

                const target =
                    event.target.closest(
                        '[data-confirm]'
                    );


                if (!target) {

                    return;

                }


                const message =
                    target.getAttribute(
                        'data-confirm'
                    );


                if (
                    !message
                ) {

                    return;

                }


                const confirmed =
                    window.confirm(
                        message
                    );


                if (!confirmed) {

                    event.preventDefault();

                    event.stopPropagation();

                }

            }
        );

    }


    /* ========================================================
       MOBILE NAVIGATION
    ======================================================== */

    function initMobileNavigation() {

        const navbar =
            document.querySelector(
                '.portal-navbar'
            );


        if (!navbar) {

            return;

        }


        /*
         * Close mobile Bootstrap navigation
         * after clicking a normal navigation link.
         */

        navbar
            .querySelectorAll(
                '.nav-link:not(.dropdown-toggle)'
            )
            .forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        function () {

                            const collapse =
                                navbar.querySelector(
                                    '.navbar-collapse'
                                );


                            if (
                                !collapse ||
                                typeof bootstrap ===
                                'undefined'
                            ) {

                                return;

                            }


                            if (
                                !collapse.classList
                                    .contains(
                                        'show'
                                    )
                            ) {

                                return;

                            }


                            try {

                                bootstrap.Collapse
                                    .getOrCreateInstance(
                                        collapse
                                    )
                                    .hide();

                            } catch (error) {

                                console.warn(
                                    'Mobile navigation close failed:',
                                    error
                                );

                            }

                        }
                    );

                }
            );

    }


    /* ========================================================
       TOOLTIPS
    ======================================================== */

    function initTooltips() {

        if (
            typeof bootstrap ===
            'undefined'
        ) {

            return;

        }


        document
            .querySelectorAll(
                '[data-bs-toggle="tooltip"]'
            )
            .forEach(
                function (element) {

                    try {

                        bootstrap.Tooltip
                            .getOrCreateInstance(
                                element
                            );

                    } catch (error) {

                        console.warn(
                            'Tooltip initialisation failed:',
                            error
                        );

                    }

                }
            );

    }


    /* ========================================================
       POPOVERS
    ======================================================== */

    function initPopovers() {

        if (
            typeof bootstrap ===
            'undefined'
        ) {

            return;

        }


        document
            .querySelectorAll(
                '[data-bs-toggle="popover"]'
            )
            .forEach(
                function (element) {

                    try {

                        bootstrap.Popover
                            .getOrCreateInstance(
                                element
                            );

                    } catch (error) {

                        console.warn(
                            'Popover initialisation failed:',
                            error
                        );

                    }

                }
            );

    }


    /* ========================================================
       AUTO DISMISS ALERTS
    ======================================================== */

    function initAutoDismissAlerts() {

        document
            .querySelectorAll(
                '[data-auto-dismiss]'
            )
            .forEach(
                function (alert) {

                    const delay =
                        parseInt(
                            alert.getAttribute(
                                'data-auto-dismiss'
                            ),
                            10
                        );


                    if (
                        !Number.isFinite(
                            delay
                        ) ||
                        delay <= 0
                    ) {

                        return;

                    }


                    window.setTimeout(
                        function () {

                            if (
                                typeof bootstrap !==
                                'undefined'
                            ) {

                                try {

                                    const instance =
                                        bootstrap.Alert
                                            .getOrCreateInstance(
                                                alert
                                            );

                                    instance.close();

                                    return;

                                } catch (error) {

                                    /*
                                     * Fall through to
                                     * normal removal.
                                     */

                                }

                            }


                            alert.remove();

                        },
                        delay
                    );

                }
            );

    }


    /* ========================================================
       TOASTS
    ======================================================== */

    window.showToast =
        function (
            message,
            type = 'info',
            duration = 4500
        ) {

            const container =
                document.getElementById(
                    'toastContainer'
                );


            if (!container) {

                /*
                 * Fallback for pages without
                 * the common toast container.
                 */

                console.log(
                    message
                );

                return;

            }


            const toast =
                document.createElement(
                    'div'
                );


            const typeMap = {

                success:
                    'text-bg-success',

                danger:
                    'text-bg-danger',

                warning:
                    'text-bg-warning',

                info:
                    'text-bg-primary'

            };


            const toastClass =
                typeMap[type] ||
                typeMap.info;


            toast.className =
                'toast align-items-center border-0 ' +
                toastClass;


            toast.setAttribute(
                'role',
                'alert'
            );


            toast.setAttribute(
                'aria-live',
                'assertive'
            );


            toast.setAttribute(
                'aria-atomic',
                'true'
            );


            toast.innerHTML =

                '<div class="d-flex">' +

                    '<div class="toast-body">' +

                        escapeHtml(
                            String(
                                message
                            )
                        ) +

                    '</div>' +

                    '<button ' +

                        'type="button" ' +

                        'class="btn-close ' +
                        'btn-close-white ' +
                        'me-2 m-auto" ' +

                        'data-bs-dismiss="toast" ' +

                        'aria-label="Close">' +

                    '</button>' +

                '</div>';


            container.appendChild(
                toast
            );


            if (
                typeof bootstrap !==
                'undefined'
            ) {

                try {

                    const instance =
                        bootstrap.Toast
                            .getOrCreateInstance(
                                toast,
                                {
                                    delay:
                                        duration
                                }
                            );


                    instance.show();


                    toast.addEventListener(
                        'hidden.bs.toast',
                        function () {

                            toast.remove();

                        }
                    );


                    return;

                } catch (error) {

                    console.warn(
                        'Toast initialisation failed:',
                        error
                    );

                }

            }


            toast.classList.add(
                'show'
            );


            window.setTimeout(
                function () {

                    toast.remove();

                },
                duration
            );

        };


    /* ========================================================
       SESSION TOAST
    ======================================================== */

    function initToastFromSession() {

        const element =
            document.querySelector(
                '[data-session-toast]'
            );


        if (!element) {

            return;

        }


        const message =
            element.getAttribute(
                'data-session-toast'
            );


        const type =
            element.getAttribute(
                'data-toast-type'
            ) ||
            'info';


        if (message) {

            window.showToast(
                message,
                type
            );

        }


        element.remove();

    }


    /* ========================================================
       SAFE HTML ESCAPE
    ======================================================== */

    function escapeHtml(
        value
    ) {

        const div =
            document.createElement(
                'div'
            );


        div.textContent =
            value;


        return div.innerHTML;

    }


    /* ========================================================
       TABLE SEARCH
    ======================================================== */

    function initTableSearch() {

        document
            .querySelectorAll(
                '[data-table-search]'
            )
            .forEach(
                function (input) {

                    const selector =
                        input.getAttribute(
                            'data-table-search'
                        );


                    const table =
                        document.querySelector(
                            selector
                        );


                    if (
                        !table
                    ) {

                        return;

                    }


                    const tbody =
                        table.querySelector(
                            'tbody'
                        );


                    if (
                        !tbody
                    ) {

                        return;

                    }


                    input.addEventListener(
                        'input',
                        function () {

                            const term =
                                this.value
                                    .trim()
                                    .toLowerCase();


                            tbody
                                .querySelectorAll(
                                    'tr'
                                )
                                .forEach(
                                    function (row) {

                                        const text =
                                            row
                                                .textContent
                                                .toLowerCase();


                                        row.style.display =
                                            (
                                                !term ||
                                                text.includes(
                                                    term
                                                )
                                            )
                                                ? ''
                                                : 'none';

                                    }
                                );

                        }
                    );

                }
            );

    }


    /* ========================================================
       COPY BUTTONS
    ======================================================== */

    function initCopyButtons() {

        document.addEventListener(
            'click',
            async function (event) {

                const button =
                    event.target.closest(
                        '[data-copy]'
                    );


                if (!button) {

                    return;

                }


                const selector =
                    button.getAttribute(
                        'data-copy'
                    );


                if (!selector) {

                    return;

                }


                const element =
                    document.querySelector(
                        selector
                    );


                if (!element) {

                    return;

                }


                const value =
                    (
                        'value' in element
                    )
                        ? element.value
                        : element.textContent;


                if (
                    !value
                ) {

                    return;

                }


                try {

                    await navigator
                        .clipboard
                        .writeText(
                            value.trim()
                        );


                    const original =
                        button.innerHTML;


                    button.innerHTML =
                        '<i class="bi bi-check2"></i> Copied';


                    window.setTimeout(
                        function () {

                            button.innerHTML =
                                original;

                        },
                        1200
                    );


                } catch (error) {

                    console.warn(
                        'Copy failed:',
                        error
                    );

                    window.showToast(
                        'Unable to copy the text.',
                        'danger'
                    );

                }

            }
        );

    }


    /* ========================================================
       FORM PROTECTION
    ======================================================== */

    function initFormProtection() {

        document
            .querySelectorAll(
                'form[data-prevent-double-submit]'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function () {

                            if (
                                form.dataset.submitted ===
                                'true'
                            ) {

                                return;

                            }


                            form.dataset.submitted =
                                'true';


                            const submitButtons =
                                form.querySelectorAll(
                                    'button[type="submit"], input[type="submit"]'
                                );


                            submitButtons
                                .forEach(
                                    function (button) {

                                        button.disabled =
                                            true;


                                        if (
                                            button.tagName
                                                .toLowerCase() ===
                                            'button'
                                        ) {

                                            button.dataset
                                                .originalText =
                                                button.innerHTML;


                                            button.innerHTML =
                                                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';

                                        }

                                    }
                                );

                        }
                    );

                }
            );

    }


    /* ========================================================
       DATE / TIME FIELDS
    ======================================================== */

    function initDateTimeFields() {

        const today =
            new Date();


        const localDate =
            today.getFullYear() +
            '-' +
            String(
                today.getMonth() + 1
            ).padStart(
                2,
                '0'
            ) +
            '-' +
            String(
                today.getDate()
            ).padStart(
                2,
                '0'
            );


        document
            .querySelectorAll(
                'input[type="date"][data-default-today]'
            )
            .forEach(
                function (input) {

                    if (
                        !input.value
                    ) {

                        input.value =
                            localDate;

                    }

                }
            );


        /*
         * End date fields can use:
         *
         * data-default-tomorrow
         */

        const tomorrow =
            new Date(
                today
            );


        tomorrow.setDate(
            tomorrow.getDate() + 1
        );


        const tomorrowDate =
            tomorrow.getFullYear() +
            '-' +
            String(
                tomorrow.getMonth() + 1
            ).padStart(
                2,
                '0'
            ) +
            '-' +
            String(
                tomorrow.getDate()
            ).padStart(
                2,
                '0'
            );


        document
            .querySelectorAll(
                'input[type="date"][data-default-tomorrow]'
            )
            .forEach(
                function (input) {

                    if (
                        !input.value
                    ) {

                        input.value =
                            tomorrowDate;

                    }

                }
            );

    }


    /* ========================================================
       SIDEBAR STATE
    ======================================================== */

    function initSidebarState() {

        const sidebar =
            document.querySelector(
                '.sidebar'
            );


        if (!sidebar) {

            return;

        }


        /*
         * Keep the active sidebar item visible
         * when the sidebar contains a long menu.
         */

        const active =
            sidebar.querySelector(
                '.nav-link.active'
            );


        if (
            !active
        ) {

            return;

        }


        /*
         * Only scroll if the active item is outside
         * the current visible sidebar area.
         */

        const sidebarRect =
            sidebar.getBoundingClientRect();


        const activeRect =
            active.getBoundingClientRect();


        if (
            activeRect.top <
            sidebarRect.top
        ) {

            try {

                active.scrollIntoView({
                    block: 'nearest'
                });

            } catch (error) {

                /* Ignore */

            }

        }

    }


    /* ========================================================
       TIMETABLE HELPERS
    ======================================================== */

    window.EdexcelTimetable = {

        /*
         * Format a time string.
         *
         * Example:
         * 15:30 -> 3:30 PM
         */

        formatTime:
            function (
                value
            ) {

                if (
                    !value
                ) {

                    return '';

                }


                const parts =
                    String(
                        value
                    ).split(
                        ':'
                    );


                if (
                    parts.length <
                    2
                ) {

                    return value;

                }


                let hour =
                    parseInt(
                        parts[0],
                        10
                    );


                const minute =
                    parts[1];


                if (
                    !Number.isFinite(
                        hour
                    )
                ) {

                    return value;

                }


                const suffix =
                    hour >= 12
                        ? 'PM'
                        : 'AM';


                hour =
                    hour % 12 ||
                    12;


                return (
                    hour +
                    ':' +
                    minute +
                    ' ' +
                    suffix
                );

            },


        /*
         * Highlight today's column in a weekly timetable.
         */

        highlightToday:
            function () {

                const day =
                    new Date()
                        .getDay();


                /*
                 * JS:
                 * Sunday = 0
                 * Monday = 1
                 *
                 * Convert to Monday = 0.
                 */

                const mondayIndex =
                    (
                        day + 6
                    ) % 7;


                document
                    .querySelectorAll(
                        '[data-week-day]'
                    )
                    .forEach(
                        function (element) {

                            const index =
                                parseInt(
                                    element.getAttribute(
                                        'data-week-day'
                                    ),
                                    10
                                );


                            if (
                                index ===
                                mondayIndex
                            ) {

                                element.classList.add(
                                    'today'
                                );

                            } else {

                                element.classList.remove(
                                    'today'
                                );

                            }

                        }
                    );

            }

    };


    /*
     * Run timetable highlighting if such elements
     * exist on the current page.
     */

    if (
        document.querySelector(
            '[data-week-day]'
        )
    ) {

        window.EdexcelTimetable
            .highlightToday();

    }


    /* ========================================================
       AJAX JSON HELPER
    ======================================================== */

    window.edxJson =
        async function (
            url,
            options = {}
        ) {

            const response =
                await window.edxFetch(
                    url,
                    options
                );


            const contentType =
                response.headers.get(
                    'content-type'
                ) || '';


            if (
                !contentType.includes(
                    'application/json'
                )
            ) {

                throw new Error(
                    'Server returned a non-JSON response.'
                );

            }


            return response.json();

        };


    /* ========================================================
       GLOBAL ERROR LOGGING
    ======================================================== */

    window.addEventListener(
        'error',
        function (event) {

            /*
             * Do not display intrusive error messages
             * to users. Log useful information instead.
             */

            console.error(
                'JavaScript error:',
                event.error ||
                event.message
            );

        }
    );


    window.addEventListener(
        'unhandledrejection',
        function (event) {

            console.error(
                'Unhandled promise rejection:',
                event.reason
            );

        }
    );


})();