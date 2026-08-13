(function () {
    'use strict';

    /* ============================================================
       ELEMENTS
    ============================================================ */

    const root = document.documentElement;

    const menu = document.getElementById('bottomNavbarMenu');

    const selector = menu
        ? menu.querySelector('.nav-selector')
        : null;

    const navItems = menu
        ? Array.from(
            menu.querySelectorAll('.bottom-nav-item')
        )
        : [];

    const navLinks = menu
        ? Array.from(
            menu.querySelectorAll(
                '.bottom-nav-link[data-target]'
            )
        )
        : [];

    const sections = Array.from(
        document.querySelectorAll('.app-section')
    );

    const brandLink = document.querySelector(
        '.bottom-navbar-brand[data-target]'
    );

    const themeToggle =
        document.getElementById(
            'standaloneThemeToggle'
        );

    const storageKey =
        'edexcel-theme';

    const reducedMotion =
        window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        );


    /* ============================================================
       THEME
    ============================================================ */

    function updateThemeButton() {

        if (!themeToggle) {
            return;
        }

        const icon =
            themeToggle.querySelector('i');

        if (!icon) {
            return;
        }

        const theme =
            root.getAttribute(
                'data-theme'
            );

        if (theme === 'light') {

            icon.classList.remove(
                'fa-moon'
            );

            icon.classList.add(
                'fa-sun'
            );

            themeToggle.setAttribute(
                'aria-label',
                'Switch to dark mode'
            );

            themeToggle.setAttribute(
                'title',
                'Switch to dark mode'
            );

        } else {

            icon.classList.remove(
                'fa-sun'
            );

            icon.classList.add(
                'fa-moon'
            );

            themeToggle.setAttribute(
                'aria-label',
                'Switch to light mode'
            );

            themeToggle.setAttribute(
                'title',
                'Switch to light mode'
            );
        }
    }


    function applyTheme(theme) {

        const activeTheme =
            theme === 'light'
                ? 'light'
                : 'dark';

        root.setAttribute(
            'data-theme',
            activeTheme
        );

        try {

            localStorage.setItem(
                storageKey,
                activeTheme
            );

        } catch (error) {
            // Ignore localStorage errors.
        }

        updateThemeButton();
    }


    function toggleTheme() {

        const currentTheme =
            root.getAttribute(
                'data-theme'
            );

        applyTheme(
            currentTheme === 'dark'
                ? 'light'
                : 'dark'
        );

        const activeItem =
            menu
                ? menu.querySelector(
                    '.bottom-nav-item.active'
                )
                : null;

        if (activeItem) {

            requestAnimationFrame(
                function () {

                    setSelectorTo(
                        activeItem
                    );

                }
            );
        }
    }


    function loadTheme() {

        let savedTheme = null;

        try {

            savedTheme =
                localStorage.getItem(
                    storageKey
                );

        } catch (error) {

            savedTheme = null;
        }


        if (
            savedTheme === 'light' ||
            savedTheme === 'dark'
        ) {

            applyTheme(
                savedTheme
            );

            return;
        }


        const prefersDark =
            window.matchMedia(
                '(prefers-color-scheme: dark)'
            ).matches;


        applyTheme(
            prefersDark
                ? 'dark'
                : 'light'
        );
    }


    /* ============================================================
       BOTTOM NAVIGATION SELECTOR
    ============================================================ */

    function setSelectorTo(item) {

        if (
            !menu ||
            !selector ||
            !item
        ) {
            return;
        }


        const menuRect =
            menu.getBoundingClientRect();

        const itemRect =
            item.getBoundingClientRect();


        const left =
            itemRect.left -
            menuRect.left +
            menu.scrollLeft;


        const top =
            itemRect.top -
            menuRect.top +
            menu.scrollTop;


        selector.style.width =
            itemRect.width + 'px';

        selector.style.height =
            itemRect.height + 'px';

        selector.style.left =
            left + 'px';

        selector.style.top =
            top + 'px';
    }


    function activateNavItem(item) {

        if (!item) {
            return;
        }


        navItems.forEach(
            function (navItem) {

                navItem.classList.remove(
                    'active'
                );

            }
        );


        navLinks.forEach(
            function (link) {

                link.removeAttribute(
                    'aria-current'
                );

            }
        );


        item.classList.add(
            'active'
        );


        const link =
            item.querySelector(
                '.bottom-nav-link'
            );


        if (link) {

            link.setAttribute(
                'aria-current',
                'page'
            );
        }


        setSelectorTo(
            item
        );
    }


    /* ============================================================
       SECTION NAVIGATION
    ============================================================ */

    function showSection(
        sectionId,
        updateHash
    ) {

        let targetSection =
            sections.find(
                function (section) {

                    return section.id ===
                        sectionId;

                }
            );


        /*
         * Invalid section:
         * fall back to Home.
         */
        if (!targetSection) {

            targetSection =
                sections.find(
                    function (section) {

                        return section.id ===
                            'home';

                    }
                );
        }


        if (!targetSection) {
            return;
        }


        const targetId =
            targetSection.id;


        /*
         * IMPORTANT:
         *
         * Only one section is visible.
         */
        sections.forEach(
            function (section) {

                const active =
                    section.id ===
                    targetId;


                section.hidden =
                    !active;


                section.classList.toggle(
                    'active-section',
                    active
                );
            }
        );


        /*
         * Activate corresponding
         * bottom navigation item.
         */
        const activeLink =
            navLinks.find(
                function (link) {

                    return (
                        link.dataset.target ===
                        targetId
                    );

                }
            );


        if (activeLink) {

            const navItem =
                activeLink.closest(
                    '.bottom-nav-item'
                );


            if (navItem) {

                activateNavItem(
                    navItem
                );
            }
        }


        /*
         * Update URL.
         */
        if (
            updateHash &&
            window.location.hash !==
                '#' + targetId
        ) {

            history.pushState(
                {
                    section: targetId
                },
                '',
                '#' + targetId
            );
        }


        /*
         * Return to top.
         */
        window.scrollTo(
            {
                top: 0,

                behavior:
                    reducedMotion.matches
                        ? 'auto'
                        : 'smooth'
            }
        );


        /*
         * Home slider may need
         * initialization after Home
         * becomes visible.
         */
        if (
            targetId === 'home' &&
            !sliderInitialized
        ) {

            requestAnimationFrame(
                function () {

                    requestAnimationFrame(
                        initSlider
                    );

                }
            );
        }
    }


    function syncFromHash() {

        let sectionId =
            window.location.hash
                .replace(
                    '#',
                    ''
                );


        if (!sectionId) {
            sectionId = 'home';
        }


        showSection(
            sectionId,
            false
        );
    }


    /* ============================================================
       WHY CHOOSE US SLIDER
    ============================================================ */

    let sliderInitialized =
        false;


    let slider = null;

    let sliderTrack = null;

    let sliderViewport = null;

    let sliderSlides = [];

    let totalSlides = 0;

    let sliderPosition = 1;

    let sliderTransitioning =
        false;

    let autoplayTimer = null;

    let autoplayInterval =
        4000;

    let sliderPaused = false;

    let touchStartX = 0;


    function initSlider() {

        if (sliderInitialized) {
            return;
        }


        slider =
            document.querySelector(
                '.why-slider'
            );


        sliderTrack =
            document.querySelector(
                '.why-slider-track'
            );


        const previous =
            document.querySelector(
                '.why-slider-prev'
            );


        const next =
            document.querySelector(
                '.why-slider-next'
            );


        const dots =
            document.querySelector(
                '.why-slider-dots'
            );


        if (
            !slider ||
            !sliderTrack ||
            !previous ||
            !next ||
            !dots
        ) {

            return;
        }


        sliderViewport =
            sliderTrack.parentElement;


        if (!sliderViewport) {
            return;
        }


        sliderSlides =
            Array.from(
                sliderTrack.querySelectorAll(
                    '.why-slide'
                )
            );


        totalSlides =
            sliderSlides.length;


        if (
            totalSlides === 0
        ) {
            return;
        }


        /*
         * Do not initialize a hidden
         * slider because its width will
         * be zero.
         */
        if (
            sliderViewport.offsetWidth === 0
        ) {

            return;
        }


        /*
         * Clone first and last slides
         * for infinite looping.
         */
        const firstClone =
            sliderSlides[0].cloneNode(
                true
            );


        const lastClone =
            sliderSlides[
                totalSlides - 1
            ].cloneNode(
                true
            );


        firstClone.setAttribute(
            'aria-hidden',
            'true'
        );


        lastClone.setAttribute(
            'aria-hidden',
            'true'
        );


        sliderTrack.appendChild(
            firstClone
        );


        sliderTrack.insertBefore(
            lastClone,
            sliderSlides[0]
        );


        /*
         * Create dots.
         */
        dots.innerHTML = '';


        for (
            let i = 0;
            i < totalSlides;
            i++
        ) {

            const dot =
                document.createElement(
                    'button'
                );


            dot.type =
                'button';


            dot.className =
                'why-slider-dot';


            dot.setAttribute(
                'role',
                'tab'
            );


            dot.setAttribute(
                'aria-label',
                'Go to slide ' +
                    (i + 1) +
                    ' of ' +
                    totalSlides
            );


            dot.addEventListener(
                'click',
                function () {

                    goToSlide(
                        i
                    );

                    restartAutoplay();

                }
            );


            dots.appendChild(
                dot
            );
        }


        previous.addEventListener(
            'click',
            function () {

                previousSlide();

                restartAutoplay();

            }
        );


        next.addEventListener(
            'click',
            function () {

                nextSlide();

                restartAutoplay();

            }
        );


        /*
         * Mouse pause.
         */
        slider.addEventListener(
            'mouseenter',
            function () {

                sliderPaused = true;

            }
        );


        slider.addEventListener(
            'mouseleave',
            function () {

                sliderPaused = false;

            }
        );


        /*
         * Touch pause.
         */
        slider.addEventListener(
            'touchstart',
            function () {

                sliderPaused = true;

            },
            {
                passive: true
            }
        );


        slider.addEventListener(
            'touchend',
            function () {

                sliderPaused = false;

            },
            {
                passive: true
            }
        );


        /*
         * Touch swipe.
         */
        sliderTrack.addEventListener(
            'touchstart',
            function (event) {

                if (
                    event.changedTouches &&
                    event.changedTouches.length
                ) {

                    touchStartX =
                        event
                            .changedTouches[0]
                            .screenX;
                }

            },
            {
                passive: true
            }
        );


        sliderTrack.addEventListener(
            'touchend',
            function (event) {

                if (
                    !event.changedTouches ||
                    !event.changedTouches.length
                ) {
                    return;
                }


                const touchEndX =
                    event
                        .changedTouches[0]
                        .screenX;


                const distance =
                    touchStartX -
                    touchEndX;


                if (
                    Math.abs(distance) <
                    50
                ) {
                    return;
                }


                if (distance > 0) {

                    nextSlide();

                } else {

                    previousSlide();
                }


                restartAutoplay();

            },
            {
                passive: true
            }
        );


        /*
         * Keyboard navigation.
         */
        slider.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'ArrowLeft'
                ) {

                    event.preventDefault();

                    previousSlide();

                    restartAutoplay();

                }


                if (
                    event.key ===
                    'ArrowRight'
                ) {

                    event.preventDefault();

                    nextSlide();

                    restartAutoplay();
                }
            }
        );


        /*
         * Resize.
         */
        window.addEventListener(
            'resize',
            resizeSlider
        );


        /*
         * Pause when browser tab
         * becomes hidden.
         */
        document.addEventListener(
            'visibilitychange',
            function () {

                if (
                    document.hidden
                ) {

                    stopAutoplay();

                } else {

                    restartAutoplay();
                }
            }
        );


        setSliderPosition(
            1,
            false
        );


        updateSliderDots();


        startAutoplay();


        sliderInitialized =
            true;
    }


    function getSlideWidth() {

        if (
            !sliderViewport
        ) {
            return 0;
        }


        return sliderViewport
            .getBoundingClientRect()
            .width;
    }


    function setSliderPosition(
        position,
        animate
    ) {

        const width =
            getSlideWidth();


        if (
            width <= 0
        ) {
            return;
        }


        sliderPosition =
            position;


        sliderTrack.style.transition =
            animate
                ? (
                    reducedMotion.matches
                        ? 'none'
                        : 'transform 550ms cubic-bezier(.4,0,.2,1)'
                )
                : 'none';


        sliderTrack.style.transform =
            'translate3d(-' +
            (
                position *
                width
            ) +
            'px, 0, 0)';
    }


    function getRealIndex() {

        return (
            (
                sliderPosition -
                1 +
                totalSlides
            ) %
            totalSlides
        );
    }


    function updateSliderDots() {

        if (!slider) {
            return;
        }


        const dots =
            slider.querySelectorAll(
                '.why-slider-dot'
            );


        const current =
            getRealIndex();


        dots.forEach(
            function (dot, index) {

                dot.setAttribute(
                    'aria-selected',
                    index === current
                        ? 'true'
                        : 'false'
                );

                dot.classList.toggle(
                    'active',
                    index === current
                );

            }
        );
    }


    function nextSlide() {

        if (
            sliderTransitioning ||
            totalSlides < 2
        ) {
            return;
        }


        sliderTransitioning =
            true;


        const newPosition =
            sliderPosition + 1;


        setSliderPosition(
            newPosition,
            true
        );


        updateSliderDots();


        setTimeout(
            function () {

                if (
                    newPosition >=
                    totalSlides + 1
                ) {

                    setSliderPosition(
                        1,
                        false
                    );
                }


                sliderTransitioning =
                    false;

            },
            reducedMotion.matches
                ? 50
                : 560
        );
    }


    function previousSlide() {

        if (
            sliderTransitioning ||
            totalSlides < 2
        ) {
            return;
        }


        sliderTransitioning =
            true;


        const newPosition =
            sliderPosition - 1;


        setSliderPosition(
            newPosition,
            true
        );


        updateSliderDots();


        setTimeout(
            function () {

                if (
                    newPosition <= 0
                ) {

                    setSliderPosition(
                        totalSlides,
                        false
                    );
                }


                sliderTransitioning =
                    false;

            },
            reducedMotion.matches
                ? 50
                : 560
        );
    }


    function goToSlide(index) {

        if (
            sliderTransitioning ||
            index < 0 ||
            index >= totalSlides
        ) {
            return;
        }


        const current =
            getRealIndex();


        if (
            index === current
        ) {
            return;
        }


        /*
         * For a small number of slides,
         * simply calculate the shortest
         * direction.
         */
        const forward =
            (
                index -
                current +
                totalSlides
            ) %
            totalSlides;


        const backward =
            (
                current -
                index +
                totalSlides
            ) %
            totalSlides;


        sliderTransitioning =
            true;


        let target;


        if (
            forward <= backward
        ) {

            target =
                sliderPosition +
                forward;

        } else {

            target =
                sliderPosition -
                backward;
        }


        setSliderPosition(
            target,
            true
        );


        updateSliderDots();


        setTimeout(
            function () {

                /*
                 * If we crossed a clone,
                 * silently jump to the real slide.
                 */
                if (
                    target >
                    totalSlides
                ) {

                    setSliderPosition(
                        target -
                        totalSlides,
                        false
                    );

                } else if (
                    target <= 0
                ) {

                    setSliderPosition(
                        target +
                        totalSlides,
                        false
                    );
                }


                sliderTransitioning =
                    false;

            },
            reducedMotion.matches
                ? 50
                : 560
        );
    }


    function getAutoplayInterval() {

        const width =
            window.innerWidth;


        if (
            width <= 480
        ) {

            return 8000;
        }


        if (
            width <= 768
        ) {

            return 6000;
        }


        return 4000;
    }


    function startAutoplay() {

        stopAutoplay();


        autoplayInterval =
            getAutoplayInterval();


        autoplayTimer =
            setInterval(
                function () {

                    if (
                        !sliderPaused &&
                        !document.hidden
                    ) {

                        nextSlide();
                    }

                },
                autoplayInterval
            );
    }


    function stopAutoplay() {

        if (
            autoplayTimer
        ) {

            clearInterval(
                autoplayTimer
            );

            autoplayTimer =
                null;
        }
    }


    function restartAutoplay() {

        stopAutoplay();

        startAutoplay();
    }


    function resizeSlider() {

        if (
            !sliderInitialized
        ) {
            return;
        }


        clearTimeout(
            resizeSlider.timeout
        );


        resizeSlider.timeout =
            setTimeout(
                function () {

                    setSliderPosition(
                        sliderPosition,
                        false
                    );


                    const newInterval =
                        getAutoplayInterval();


                    if (
                        newInterval !==
                        autoplayInterval
                    ) {

                        restartAutoplay();
                    }

                },
                150
            );
    }


    /* ============================================================
       DOM READY
    ============================================================ */

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            /*
             * Theme.
             */
            loadTheme();


            if (
                themeToggle
            ) {

                themeToggle.addEventListener(
                    'click',
                    toggleTheme
                );

                updateThemeButton();
            }


            /*
             * Initial section.
             */
            syncFromHash();


            /*
             * Bottom navigation links.
             */
            navLinks.forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        function (event) {

                            event.preventDefault();


                            showSection(
                                link.dataset.target,
                                true
                            );
                        }
                    );
                }
            );


            /*
             * Bottom navigation brand.
             */
            if (
                brandLink
            ) {

                brandLink.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();


                        showSection(
                            brandLink.dataset.target,
                            true
                        );
                    }
                );
            }


            /*
             * Recalculate selector while
             * bottom navigation is scrolling.
             */
            if (
                menu
            ) {

                menu.addEventListener(
                    'scroll',
                    function () {

                        const active =
                            menu.querySelector(
                                '.bottom-nav-item.active'
                            );


                        if (active) {

                            setSelectorTo(
                                active
                            );
                        }

                    },
                    {
                        passive: true
                    }
                );
            }


            /*
             * Start slider after layout.
             */
            requestAnimationFrame(
                function () {

                    requestAnimationFrame(
                        function () {

                            initSlider();

                        }
                    );
                }
            );
        }
    );


    /* ============================================================
       HASH CHANGE
    ============================================================ */

    window.addEventListener(
        'hashchange',
        function () {

            syncFromHash();

        }
    );


    /* ============================================================
       BROWSER BACK / FORWARD
    ============================================================ */

    window.addEventListener(
        'popstate',
        function () {

            syncFromHash();

        }
    );


    /* ============================================================
       WINDOW RESIZE
    ============================================================ */

    window.addEventListener(
        'resize',
        function () {

            const active =
                menu
                    ? menu.querySelector(
                        '.bottom-nav-item.active'
                    )
                    : null;


            if (active) {

                setSelectorTo(
                    active
                );
            }

        }
    );

})();