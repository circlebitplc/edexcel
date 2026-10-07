<?php
homepage_render_head($activeLayout);
$teachers = $hp['teachers'] ?? [];
$programmes = $hp['programmes'] ?? [];
$events = array_slice($hp['news'] ?? [], 0, 4);
$lessons = array_slice($hp['upcomingLessons'] ?? [], 0, 8);
$institute = (string)($hp['institute'] ?? 'Edexcel College');
$apply = rtrim((string)BASE_URL, '/') . '/student/register.php';
$loginUrl = rtrim((string)BASE_URL, '/') . '/portal/login.php';
$enquire = rtrim((string)BASE_URL, '/') . '/admissions/enquire.php';
$seoBase = rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/');
$contact = college_contact(($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null);
$mapsUrl = (string)($contact['maps_url'] ?? 'https://maps.app.goo.gl/1FJE2mQ5eR1HijsbA');
$mapsEmbed = (string)($contact['maps_embed_url'] ?? 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3957.45!2d80.6346098!3d7.3050896!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae367d64dd20f49%3A0x3539c01e3bc33f01!2s83%20Katugastota%20Rd%2C%20Kandy!5e0!3m2!1sen!2slk!4v1720000000000!5m2!1sen!2slk');

// Categorised FAQs grounded in verified institution information
$allFaqCategories = function_exists('seo_faq_categories')
    ? seo_faq_categories(($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null)
    : [];

$faqGroups = [
    'admissions' => [
        'title' => 'Admissions & Enrolment',
        'icon' => 'fa-id-card',
        'items' => [
            [
                'q' => 'How can a student enquire or enrol for Edexcel classes?',
                'a' => 'You can begin by submitting an enquiry through our Admissions page, contacting our academic office via phone or WhatsApp, or registering directly on our student portal. We guide families through subject selection based on target Pearson examination windows.',
            ],
            [
                'q' => 'How do students register for official Pearson Edexcel examinations?',
                'a' => 'Official examination entries are submitted through Pearson-authorised centres (such as the British Council or affiliated schools). Edexcel College provides full academic preparation and syllabus guidance, helping candidates navigate deadlines and specification codes.',
            ],
            [
                'q' => 'Can private candidates enrol for tuition and exam preparation?',
                'a' => 'Yes. Many students joining our classes sit examinations as private candidates through approved centres. We ensure your coursework, syllabus coverage, and past-paper practice strictly align with current Pearson Edexcel criteria.',
            ],
            [
                'q' => 'What payment options are available for tuition fees?',
                'a' => 'Enrolled students can settle tuition fees securely through online card payments (via OnePay), bank transfer with uploaded slip verification, or in-person at our Kandy campus.',
            ],
        ],
    ],
    'programmes' => [
        'title' => 'Programmes & Qualifications',
        'icon' => 'fa-graduation-cap',
        'items' => [
            [
                'q' => 'Which Pearson Edexcel programmes are offered at Edexcel College?',
                'a' => 'We specialize exclusively in Pearson Edexcel International GCSE (IGCSE) and International Advanced Level (IAL) pathways, including modular AS and A2 unit teaching across Science, Mathematics, Commerce, and Technology streams.',
            ],
            [
                'q' => 'What is the difference between Edexcel O Level and IGCSE?',
                'a' => 'In international education, “Edexcel O Level” commonly refers to the Pearson Edexcel International GCSE (IGCSE). Our programmes follow the modern 9–1 grading specifications recognized by prestigious universities worldwide.',
            ],
            [
                'q' => 'How does the modular International A Level (IAL) structure work?',
                'a' => 'Pearson Edexcel IAL allows students to take modular examinations in January, June, and October. Students can sit AS units first and complete A2 units subsequently, offering flexibility to master specific units and retake single papers if needed.',
            ],
        ],
    ],
    'examinations' => [
        'title' => 'Examinations & Results',
        'icon' => 'fa-file-lines',
        'items' => [
            [
                'q' => 'How are past papers incorporated into the curriculum?',
                'a' => 'Past-paper practice is embedded into weekly teaching. Students solve topical questions after each concept and complete full-length timed mock examinations evaluated against authentic Pearson mark schemes.',
            ],
            [
                'q' => 'How do students receive their Pearson examination results?',
                'a' => 'Official results and physical certificates are released by Pearson through the candidate\'s registered examination centre. Edexcel College tracks student trial marks and published classroom assessments internally through our portal.',
            ],
            [
                'q' => 'Does Edexcel College host the actual Pearson examination venue?',
                'a' => 'Edexcel College is an independent academic preparation college, not an exam hall venue. Candidates sit their final written papers at official Pearson-authorised testing venues.',
            ],
        ],
    ],
    'classes' => [
        'title' => 'Classes & Faculty',
        'icon' => 'fa-chalkboard-user',
        'items' => [
            [
                'q' => 'Who teaches the core subjects at Edexcel College?',
                'a' => 'Our faculty comprises specialist Edexcel educators with verified experience in the Pearson syllabus, including Kasun Batuwanthudawa (Mathematics), Enidu Batuwanthudawe (ICT & Computer Science), Mahesh Wijesekara (Chemistry), Dinuka Dissanayake (Physics), and Asanka Illangakoon (Economics & Business).',
            ],
            [
                'q' => 'Are classes conducted in-person, online, or hybrid?',
                'a' => 'Around 90% of classes are live and online for students worldwide. Physical classes are at the Kandy campus for selected subjects, teachers, and times. Some lessons are also available later as recordings in the student portal.',
            ],
            [
                'q' => 'How are class sizes managed for individual attention?',
                'a' => 'We maintain structured class batches to ensure every student can ask questions, participate in problem-solving discussions, and receive personalized feedback on their work.',
            ],
        ],
    ],
    'support' => [
        'title' => 'Student & Parent Support',
        'icon' => 'fa-hands-holding-child',
        'items' => [
            [
                'q' => 'How can parents monitor their child’s attendance and progress?',
                'a' => 'Parents receive access to our dedicated Parent Portal, where they can review real-time class attendance logs, published assessment scores, and upcoming class schedules at any time.',
            ],
            [
                'q' => 'What digital tools are provided to enrolled students?',
                'a' => 'Students gain access to our secure Student Portal featuring digital lecture notes, revision archives, timetable management, video recordings of past sessions, and AI learning support.',
            ],
            [
                'q' => 'Can students based outside Sri Lanka join Edexcel College?',
                'a' => 'Yes. We support overseas students across Italy, the UAE, Qatar, Oman, the Maldives, and the UK through our online learning infrastructure and flexible lesson schedules.',
            ],
        ],
    ],
];
?>
<body class="hp hp-l1">
<?php homepage_render_preview_banner(); ?>

<!-- 1. STICKY INSTITUTIONAL HEADER -->
<header class="l1-nav" id="l1-navbar" data-l1-nav>
    <div class="l1-nav-container">
        <a class="l1-brand" href="<?= e(homepage_url()) ?>" aria-label="Edexcel College Homepage">
            <span class="l1-brand-crest"><i class="fas fa-landmark"></i></span>
            <div class="l1-brand-text">
                <strong><?= e($institute) ?></strong>
                <span class="l1-brand-sub">Pearson Edexcel International Centre</span>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <nav class="l1-links" data-l1-links aria-label="Main Navigation">
            <a href="#about" class="l1-nav-item">About</a>
            <a href="#why" class="l1-nav-item">Why Us</a>
            <a href="#journey" class="l1-nav-item">System</a>
            <a href="#programmes" class="l1-nav-item">Programmes</a>
            <a href="#teachers" class="l1-nav-item">Faculty</a>
            <a href="#students" class="l1-nav-item">Student Life</a>
            <a href="#parents" class="l1-nav-item">Parents</a>
            <a href="#timetable" class="l1-nav-item">Timetable</a>
            <a href="#faq" class="l1-nav-item">FAQ</a>
            <a href="#contact" class="l1-nav-item">Contact</a>
        </nav>

        <div class="l1-nav-actions">
            <a class="l1-btn-portal" href="<?= e($loginUrl) ?>">
                <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                <span>Login</span>
            </a>
            <a class="l1-apply-btn" href="<?= e($apply) ?>">Sign Up</a>
        </div>
    </div>
</header>

<main id="main-content">

    <!-- 2. EMOTIONAL ACADEMIC HERO -->
    <section class="l1-hero" id="home">
        <div class="l1-hero-shell">
            <div class="l1-hero-copy">
                <div class="l1-pill-kicker">
                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                    <span>PEARSON EDEXCEL IGCSE &amp; INTERNATIONAL A LEVEL</span>
                </div>
                <h1 class="l1-hero-title">
                    Edexcel IGCSE &amp; IAL Classes<br>
                    <span class="l1-highlight">Online Worldwide</span>
                </h1>
                <p class="l1-hero-lead">
                    Learn with teachers through live online classes from anywhere in the world. Around 90% of classes are online. Physical classes are also available at the Kandy campus in Sri Lanka, when the subject, teacher, and timetable offer them.
                </p>
                <div class="l1-hero-cta-group">
                    <a class="l1-btn l1-btn-gold" href="<?= e($loginUrl) ?>">
                        <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                        <span>Login</span>
                    </a>
                    <a class="l1-btn l1-btn-ghost-light" href="<?= e($apply) ?>">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        <span>Sign Up</span>
                    </a>
                    <a class="l1-btn l1-btn-ghost-light" href="<?= e($seoBase) ?>/online-classes">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <span>Online classes</span>
                    </a>
                    <a class="l1-btn l1-btn-ghost-light" href="<?= e($seoBase) ?>/subjects">
                        <i class="fas fa-book" aria-hidden="true"></i>
                        <span>Subjects</span>
                    </a>
                    <a class="l1-btn l1-btn-ghost-light" href="<?= e($seoBase) ?>/locations/kandy">
                        <i class="fas fa-location-dot" aria-hidden="true"></i>
                        <span>Kandy campus</span>
                    </a>
                </div>
                <div class="l1-hero-trust-row">
                    <div class="l1-hero-trust-item">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                        <span>Independent Pearson Curriculum</span>
                    </div>
                    <div class="l1-hero-trust-divider" aria-hidden="true"></div>
                    <div class="l1-hero-trust-item">
                        <i class="fas fa-location-dot" aria-hidden="true"></i>
                        <span>Worldwide online · Physical classes in Kandy</span>
                    </div>
                </div>
            </div>

            <div class="l1-hero-visual">
                <div class="l1-visual-wrapper">
                    <img class="l1-hero-img"
                         src="<?= e(homepage_stock('hero-student')) ?>"
                         alt="Students joining Edexcel College live online classes, with a physical campus in Kandy, Sri Lanka"
                         width="820"
                         height="680"
                         fetchpriority="high"
                         decoding="async">
                    <div class="l1-visual-overlay" aria-hidden="true"></div>

                    <!-- Floating Academic Credibility Badges -->
                    <div class="l1-float-card l1-float-syllabus">
                        <div class="l1-float-icon"><i class="fas fa-certificate"></i></div>
                        <div>
                            <strong>Pearson Edexcel Aligned</strong>
                            <span>Rigorous syllabus &amp; mark schemes</span>
                        </div>
                    </div>

                    <div class="l1-float-card l1-float-metric">
                        <div class="l1-float-stat">
                            <strong data-count="<?= homepage_stat($hp, 'students') ?>"><?= homepage_stat($hp, 'students') ?></strong>
                            <span>Active Learners</span>
                        </div>
                        <div class="l1-float-avatars" aria-hidden="true">
                            <span class="l1-av l1-av-1"></span>
                            <span class="l1-av l1-av-2"></span>
                            <span class="l1-av l1-av-3"></span>
                            <span class="l1-av-plus">+</span>
                        </div>
                    </div>

                    <div class="l1-float-card l1-float-pathway">
                        <i class="fas fa-arrow-trend-up"></i>
                        <span>Concept &rarr; Practice &rarr; Exam Success</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. AUTHORITATIVE TRUST STRIP -->
    <section class="l1-trust-strip" id="about" aria-label="Institutional Standards">
        <div class="l1-strip-container">
            <div class="l1-strip-item">
                <div class="l1-strip-icon"><i class="fas fa-award"></i></div>
                <div class="l1-strip-content">
                    <strong>PEARSON EDEXCEL CURRICULUM</strong>
                    <span>Official specifications and structured syllabi</span>
                </div>
            </div>
            <div class="l1-strip-item">
                <div class="l1-strip-icon"><i class="fas fa-layer-group"></i></div>
                <div class="l1-strip-content">
                    <strong>IGCSE &amp; INTERNATIONAL A LEVEL</strong>
                    <span>Secondary to university-entrance pathways</span>
                </div>
            </div>
            <div class="l1-strip-item">
                <div class="l1-strip-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="l1-strip-content">
                    <strong>SPECIALIST SUBJECT TEACHERS</strong>
                    <span>Experienced educators with deep curriculum mastery</span>
                </div>
            </div>
            <div class="l1-strip-item">
                <div class="l1-strip-icon"><i class="fas fa-bullseye"></i></div>
                <div class="l1-strip-content">
                    <strong>EXAM-FOCUSED LEARNING</strong>
                    <span>Past papers, timing discipline &amp; mark schemes</span>
                </div>
            </div>
        </div>
    </section>

    <!-- 3B. STUDENT COMMUNITY SECTION -->
    <section class="l1-section l1-community-section" id="community" aria-labelledby="community-heading">
        <div class="l1-container">
            <div class="l1-community-card">
                <div class="l1-community-grid">
                    <div class="l1-community-content">
                        <div class="l1-community-badge-row">
                            <span class="l1-badge-kicker l1-kicker-student">
                                <i class="fas fa-graduation-cap" aria-hidden="true"></i> FOR STUDENTS
                            </span>
                            <span class="l1-community-status-pill">
                                <span class="l1-pulse-dot" aria-hidden="true"></span>
                                <span>Official Community Channel</span>
                            </span>
                        </div>

                        <h2 id="community-heading" class="l1-community-heading">
                            Join the Edexcel College Student Community
                        </h2>

                        <p class="l1-community-subtitle">
                            Stay connected with Edexcel College and never miss important student updates.
                        </p>

                        <div class="l1-community-list-block">
                            <p class="l1-community-list-intro">
                                Join our official WhatsApp Community to receive:
                            </p>
                            <ul class="l1-community-benefits" role="list">
                                <li class="l1-cb-item">
                                    <span class="l1-cb-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></span>
                                    <span class="l1-cb-text">Important announcements</span>
                                </li>
                                <li class="l1-cb-item">
                                    <span class="l1-cb-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></span>
                                    <span class="l1-cb-text">Class and timetable updates</span>
                                </li>
                                <li class="l1-cb-item">
                                    <span class="l1-cb-icon"><i class="fas fa-file-signature" aria-hidden="true"></i></span>
                                    <span class="l1-cb-text">Examination and academic notices</span>
                                </li>
                                <li class="l1-cb-item">
                                    <span class="l1-cb-icon"><i class="fas fa-award" aria-hidden="true"></i></span>
                                    <span class="l1-cb-text">Events and activities</span>
                                </li>
                                <li class="l1-cb-item">
                                    <span class="l1-cb-icon"><i class="fas fa-bell" aria-hidden="true"></i></span>
                                    <span class="l1-cb-text">Student information and reminders</span>
                                </li>
                            </ul>
                        </div>

                        <div class="l1-community-actions">
                            <a href="https://chat.whatsapp.com/JjVSFWUIro19KTtDRRsc8W"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="l1-btn l1-btn-whatsapp"
                               aria-label="Join official Edexcel College WhatsApp Community (opens in a new tab)">
                                <i class="fab fa-whatsapp" aria-hidden="true"></i>
                                <span>Join Student Community</span>
                            </a>
                            <div class="l1-community-secure-note">
                                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                                <span>Verified college community &middot; Free to join</span>
                            </div>
                        </div>
                    </div>

                    <div class="l1-community-card-aside" aria-hidden="true">
                        <div class="l1-community-hub-box">
                            <div class="l1-chb-header">
                                <div class="l1-chb-crest">
                                    <i class="fas fa-landmark"></i>
                                </div>
                                <div class="l1-chb-titles">
                                    <strong>Edexcel College</strong>
                                    <span>Pearson Edexcel International Centre</span>
                                </div>
                                <div class="l1-chb-wa-badge">
                                    <i class="fab fa-whatsapp"></i>
                                </div>
                            </div>

                            <div class="l1-chb-preview-list">
                                <div class="l1-chb-preview-item">
                                    <span class="l1-chb-bullet"></span>
                                    <div>
                                        <strong>Live Timetables &amp; Session Alerts</strong>
                                        <p>Weekly schedules &amp; room links delivered directly</p>
                                    </div>
                                </div>
                                <div class="l1-chb-preview-item">
                                    <span class="l1-chb-bullet"></span>
                                    <div>
                                        <strong>Pearson Exam Registration</strong>
                                        <p>Key dates for January, June &amp; October sessions</p>
                                    </div>
                                </div>
                                <div class="l1-chb-preview-item">
                                    <span class="l1-chb-bullet"></span>
                                    <div>
                                        <strong>Academic Events &amp; Notices</strong>
                                        <p>Revision clinics, workshops &amp; student reminders</p>
                                    </div>
                                </div>
                            </div>

                            <div class="l1-chb-footer">
                                <div class="l1-chb-stat">
                                    <i class="fas fa-users-viewfinder"></i>
                                    <span>Live updates for IGCSE &amp; IAL students worldwide</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. WHY STUDENTS CHOOSE EDEXCEL COLLEGE (6 Curated Academic Blocks) -->
    <section class="l1-section l1-why-section" id="why">
        <div class="l1-container">
            <div class="l1-section-header">
                <span class="l1-badge-kicker">Academic Philosophy</span>
                <h2>Why Students Choose Edexcel College</h2>
                <p>A structured approach to learning that connects strong concepts with consistent exam preparation.</p>
            </div>

            <div class="l1-feature-grid">
                <!-- Block 1: Expert Teachers -->
                <article class="l1-feature-card l1-card-accent">
                    <div class="l1-card-top">
                        <span class="l1-feature-num">01</span>
                        <div class="l1-feature-icon"><i class="fas fa-chalkboard-user"></i></div>
                    </div>
                    <h3>Expert Teachers</h3>
                    <p>Learn from subject specialists who understand the Edexcel curriculum, marking criteria, and examiner expectations inside out.</p>
                    <div class="l1-feature-tag">Subject Specialization</div>
                </article>

                <!-- Block 2: Structured Learning -->
                <article class="l1-feature-card">
                    <div class="l1-card-top">
                        <span class="l1-feature-num">02</span>
                        <div class="l1-feature-icon"><i class="fas fa-diagram-project"></i></div>
                    </div>
                    <h3>Structured Learning</h3>
                    <p>Follow a clear academic pathway from fundamental concepts to examination readiness without gaps or skipped syllabus units.</p>
                    <div class="l1-feature-tag">Curriculum Progression</div>
                </article>

                <!-- Block 3: Exam-Focused Practice -->
                <article class="l1-feature-card l1-card-accent">
                    <div class="l1-card-top">
                        <span class="l1-feature-num">03</span>
                        <div class="l1-feature-icon"><i class="fas fa-file-signature"></i></div>
                    </div>
                    <h3>Exam-Focused Practice</h3>
                    <p>Develop genuine examination confidence through extensive past-paper drills, timed mock tests, and examiner report analysis.</p>
                    <div class="l1-feature-tag">Past Paper Mastery</div>
                </article>

                <!-- Block 4: Individual Attention -->
                <article class="l1-feature-card">
                    <div class="l1-card-top">
                        <span class="l1-feature-num">04</span>
                        <div class="l1-feature-icon"><i class="fas fa-users-viewfinder"></i></div>
                    </div>
                    <h3>Individual Attention</h3>
                    <p>Keep learning focused with appropriately sized classes where educators know every student’s strengths and address specific misconceptions.</p>
                    <div class="l1-feature-tag">Student-Centered Guidance</div>
                </article>

                <!-- Block 5: Progress Visibility -->
                <article class="l1-feature-card">
                    <div class="l1-card-top">
                        <span class="l1-feature-num">05</span>
                        <div class="l1-feature-icon"><i class="fas fa-chart-line"></i></div>
                    </div>
                    <h3>Progress Visibility</h3>
                    <p>Give students and parents a clearer, transparent view of academic progress, attendance records, and ongoing assessment milestones.</p>
                    <div class="l1-feature-tag">Performance Analytics</div>
                </article>

                <!-- Block 6: Modern Learning Tools -->
                <article class="l1-feature-card l1-card-accent">
                    <div class="l1-card-top">
                        <span class="l1-feature-num">06</span>
                        <div class="l1-feature-icon"><i class="fas fa-laptop-code"></i></div>
                    </div>
                    <h3>Modern Learning Tools</h3>
                    <p>Benefit from digital lecture notes, replayable lesson recordings, exam planners, and AI revision companion tools in our student portal.</p>
                    <div class="l1-feature-tag">Digital Learning Campus</div>
                </article>
            </div>
        </div>
    </section>

    <!-- 5. EMOTIONAL "LEARNING JOURNEY" SECTION -->
    <section class="l1-section l1-journey-section" id="journey">
        <div class="l1-container">
            <div class="l1-section-header l1-header-light">
                <span class="l1-badge-kicker l1-kicker-gold">The Academic Methodology</span>
                <h2>The Edexcel College Learning System</h2>
                <p>Academic excellence is not accidental. We organize education around four disciplined milestones designed to build unstoppable competence.</p>
            </div>

            <div class="l1-journey-track">
                <!-- Step 1 -->
                <div class="l1-step-card">
                    <div class="l1-step-badge">01</div>
                    <div class="l1-step-indicator">
                        <span class="l1-step-dot"></span>
                        <span class="l1-step-line"></span>
                    </div>
                    <div class="l1-step-content">
                        <span class="l1-step-phase">FOUNDATION</span>
                        <h3>UNDERSTAND</h3>
                        <p>Build strong conceptual foundations through comprehensive syllabus coverage and clear explanation of underlying principles.</p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="l1-step-card">
                    <div class="l1-step-badge">02</div>
                    <div class="l1-step-indicator">
                        <span class="l1-step-dot"></span>
                        <span class="l1-step-line"></span>
                    </div>
                    <div class="l1-step-content">
                        <span class="l1-step-phase">APPLICATION</span>
                        <h3>PRACTICE</h3>
                        <p>Apply knowledge continuously through structured problem-solving, topic-by-topic question banks, and authentic past-paper questions.</p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="l1-step-card">
                    <div class="l1-step-badge">03</div>
                    <div class="l1-step-indicator">
                        <span class="l1-step-dot"></span>
                        <span class="l1-step-line"></span>
                    </div>
                    <div class="l1-step-content">
                        <span class="l1-step-phase">REFINEMENT</span>
                        <h3>IMPROVE</h3>
                        <p>Identify individual weaknesses, correct common exam pitfalls, and fine-tune answering technique against official mark schemes.</p>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="l1-step-card">
                    <div class="l1-step-badge">04</div>
                    <div class="l1-step-indicator">
                        <span class="l1-step-dot"></span>
                    </div>
                    <div class="l1-step-content">
                        <span class="l1-step-phase">OUTCOME</span>
                        <h3>ACHIEVE</h3>
                        <p>Enter Pearson examinations with calm confidence, proven exam timing, and the academic preparation necessary for top grades.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. PROGRAMMES SECTION (University-Style Cards) -->
    <section class="l1-section l1-programmes-section" id="programmes">
        <div class="l1-container">
            <div class="l1-section-header">
                <span class="l1-badge-kicker">Qualification Pathways</span>
                <h2>Edexcel Programmes &amp; Academic Streams</h2>
                <p>Comprehensive subject tuition structured specifically for Pearson Edexcel international qualifications.</p>
            </div>

            <!-- Programme Filter Pills -->
            <div class="l1-filter-row" role="tablist" aria-label="Programme filters">
                <button type="button" class="l1-filter-btn is-active" data-filter="all" role="tab" aria-selected="true">All Pathways</button>
                <button type="button" class="l1-filter-btn" data-filter="igcse" role="tab" aria-selected="false">IGCSE (O Level)</button>
                <button type="button" class="l1-filter-btn" data-filter="ial" role="tab" aria-selected="false">International A Level (IAL)</button>
                <button type="button" class="l1-filter-btn" data-filter="revision" role="tab" aria-selected="false">Exam Preparation</button>
            </div>

            <div class="l1-programmes-grid">
                <!-- Tier 1: IGCSE Pathway -->
                <article class="l1-prog-card" data-category="igcse">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level">IGCSE &middot; 9–1 Grading</span>
                        <span class="l1-prog-icon"><i class="fas fa-square-root-variable"></i></span>
                    </div>
                    <h3>Pearson Edexcel IGCSE Mathematics</h3>
                    <p>Rigorous coverage of Pure Math, Number, Algebra, Geometry, and Statistics tailored for Pearson Edexcel Specification A &amp; B.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Foundation &amp; Higher Tier Guidance</li>
                        <li><i class="fas fa-check"></i> Topical past-paper question packs</li>
                        <li><i class="fas fa-check"></i> Step-by-step mark scheme evaluation</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/edexcel-o-level') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <article class="l1-prog-card" data-category="igcse">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level">IGCSE &middot; Sciences</span>
                        <span class="l1-prog-icon"><i class="fas fa-flask-vial"></i></span>
                    </div>
                    <h3>Pearson Edexcel IGCSE Sciences</h3>
                    <p>Specialist teaching in Physics, Chemistry, and Biology. Deep conceptual mastery with complete practical investigation question prep.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Alternative to Practical examination skills</li>
                        <li><i class="fas fa-check"></i> Experimental data and calculation training</li>
                        <li><i class="fas fa-check"></i> Syllabus milestone assessments</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/edexcel-o-level') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <article class="l1-prog-card" data-category="igcse">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level">IGCSE &middot; Computing</span>
                        <span class="l1-prog-icon"><i class="fas fa-microchip"></i></span>
                    </div>
                    <h3>Pearson Edexcel IGCSE ICT &amp; Computer Science</h3>
                    <p>Algorithmic thinking, software applications, database querying, and digital communication aligned with latest specifications.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Practical computer lab &amp; paper 2 preparation</li>
                        <li><i class="fas fa-check"></i> Theory concepts clarified with real-world context</li>
                        <li><i class="fas fa-check"></i> Regular coding and logic drills</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/edexcel-o-level') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <!-- Tier 2: International A Level Pathway -->
                <article class="l1-prog-card" data-category="ial">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level l1-level-ial">IAL &middot; Modular</span>
                        <span class="l1-prog-icon"><i class="fas fa-infinity"></i></span>
                    </div>
                    <h3>Pearson Edexcel IAL Pure Mathematics &amp; Mechanics</h3>
                    <p>Comprehensive unit-by-unit teaching across P1, P2, P3, P4, M1, and S1 for the prestigious International Advanced Level diploma.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Modular exam series prep (Jan / Jun / Oct)</li>
                        <li><i class="fas fa-check"></i> Proof, calculus, and mathematical modeling</li>
                        <li><i class="fas fa-check"></i> Full mock papers under timed conditions</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/edexcel-a-level') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <article class="l1-prog-card" data-category="ial">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level l1-level-ial">IAL &middot; STEM</span>
                        <span class="l1-prog-icon"><i class="fas fa-atom"></i></span>
                    </div>
                    <h3>Pearson Edexcel IAL Physics &amp; Chemistry</h3>
                    <p>Advanced physical sciences covering mechanics, thermodynamics, quantum phenomena, organic reaction pathways, and spectroscopic analysis.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Unit 3 &amp; Unit 6 practical paper workshops</li>
                        <li><i class="fas fa-check"></i> Mathematical problem solving and error analysis</li>
                        <li><i class="fas fa-check"></i> University-prep depth and rigor</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/edexcel-a-level') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <article class="l1-prog-card" data-category="ial">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level l1-level-ial">IAL &middot; Commerce</span>
                        <span class="l1-prog-icon"><i class="fas fa-chart-pie"></i></span>
                    </div>
                    <h3>Pearson Edexcel IAL Economics &amp; Business</h3>
                    <p>Macroeconomic policies, global markets, corporate finance, and strategic case analysis trained to meet examiner evaluation criteria.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Essay structure, analysis, and evaluation chains</li>
                        <li><i class="fas fa-check"></i> Data response question mastery</li>
                        <li><i class="fas fa-check"></i> Contemporary global economic case studies</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/edexcel-a-level') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <!-- Tier 3: Exam Preparation & Revision -->
                <article class="l1-prog-card" data-category="revision">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level l1-level-rev">Revision Series</span>
                        <span class="l1-prog-icon"><i class="fas fa-stopwatch"></i></span>
                    </div>
                    <h3>Intensive Past Paper Bootcamps</h3>
                    <p>Pre-exam series crash revision workshops targeting high-yield examination topics, difficult units, and time-management strategies.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Focused on upcoming Jan &amp; Jun series</li>
                        <li><i class="fas fa-check"></i> Examiner report review to avoid common mistakes</li>
                        <li><i class="fas fa-check"></i> Personalized grade boundary targeting</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/exam-preparation') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>

                <article class="l1-prog-card" data-category="revision">
                    <div class="l1-prog-head">
                        <span class="l1-prog-level l1-level-rev">Candidate Support</span>
                        <span class="l1-prog-icon"><i class="fas fa-user-check"></i></span>
                    </div>
                    <h3>Private Candidate Exam Mentorship</h3>
                    <p>Academic navigation, specification checks, and timeline management for independent and homeschooling students sitting Pearson exams.</p>
                    <ul class="l1-prog-features">
                        <li><i class="fas fa-check"></i> Specification code and syllabus verification</li>
                        <li><i class="fas fa-check"></i> Complete timetable synchronization</li>
                        <li><i class="fas fa-check"></i> Comprehensive candidate readiness assessments</li>
                    </ul>
                    <div class="l1-prog-footer">
                        <a href="<?= e($seoBase . '/exam-preparation') ?>" class="l1-prog-cta">
                            <span>Explore Pathway</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            </div>

            <div class="l1-section-actions">
                <a href="<?= e($seoBase . '/edexcel-classes') ?>" class="l1-btn l1-btn-navy">
                    <span>View All Subject Offerings</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- 7. TEACHER SECTION — HIGH-TRUST FACULTY -->
    <section class="l1-section l1-teachers-section" id="teachers">
        <div class="l1-container">
            <div class="l1-section-header">
                <span class="l1-badge-kicker">Academic Faculty</span>
                <h2>Learn From Teachers Who Know the Curriculum</h2>
                <p>Meet the educators guiding students through IGCSE and International A Level learning with verified subject expertise.</p>
            </div>

            <div class="l1-teachers-grid">
                <?php foreach ($teachers as $teacher): ?>
                <?php
                    $tName = (string)($teacher['name'] ?? 'Teacher');
                    $tSubjects = $teacher['subject_list'] ?? [];
                    $tBio = trim((string)($teacher['bio'] ?? ''));
                    if ($tBio === '' && function_exists('seo_teacher_fallback_intro')) {
                        $tBio = seo_teacher_fallback_intro($tName, $tSubjects);
                    }
                    $tBioExcerpt = homepage_clip($tBio, 120);
                    $tExperience = (int)($teacher['experience_years'] ?? 0);
                    $tQuals = trim((string)($teacher['qualifications'] ?? ''));
                    $profileUrl = (string)($teacher['profile_url'] ?? '#');
                ?>
                <article class="l1-teacher-card">
                    <div class="l1-teacher-top">
                        <div class="l1-teacher-avatar-wrap">
                            <?php homepage_teacher_avatar($teacher, 'l1-teacher-avatar'); ?>
                            <span class="l1-teacher-status" title="Active Specialist Faculty"><i class="fas fa-check"></i></span>
                        </div>
                        <div class="l1-teacher-meta">
                            <h3><a href="<?= e($profileUrl) ?>" class="l1-teacher-name-link"><?= e($tName) ?></a></h3>
                            <div class="l1-teacher-subject-tag">
                                <i class="fas fa-book" aria-hidden="true"></i>
                                <span><?= e(implode(' · ', array_slice($tSubjects, 0, 3)) ?: 'Pearson Edexcel Specialist') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="l1-teacher-body">
                        <?php if ($tQuals !== '' || $tExperience > 0): ?>
                        <div class="l1-teacher-credentials">
                            <?php if ($tExperience > 0): ?>
                            <span class="l1-teacher-pill"><i class="fas fa-clock-rotate-left"></i> <?= $tExperience ?>+ Years Exp.</span>
                            <?php endif; ?>
                            <?php if ($tQuals !== ''): ?>
                            <span class="l1-teacher-pill"><i class="fas fa-award"></i> <?= e(homepage_clip($tQuals, 30)) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <p class="l1-teacher-bio"><?= e($tBioExcerpt) ?></p>
                    </div>

                    <div class="l1-teacher-footer">
                        <a href="<?= e($profileUrl) ?>" class="l1-t-btn l1-t-btn-primary">
                            <span>View Profile</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a href="#timetable" class="l1-t-btn l1-t-btn-ghost">
                            <i class="fas fa-calendar-check"></i>
                            <span>Classes</span>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="l1-teachers-cta">
                <p>Looking for a specific subject tutor or guidance on course schedules?</p>
                <div class="l1-hero-actions">
                    <a href="<?= e($seoBase . '/teachers/') ?>" class="l1-btn l1-btn-navy">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <span>Browse Full Faculty Directory</span>
                    </a>
                    <a href="<?= e($enquire) ?>" class="l1-btn l1-btn-outline">
                        <i class="fas fa-envelope-open-text" aria-hidden="true"></i>
                        <span>Enquire About a Class</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. "FOR STUDENTS" SECTION (Student Experience Ecosystem) -->
    <section class="l1-section l1-students-section" id="students">
        <div class="l1-container">
            <div class="l1-section-header l1-header-light">
                <span class="l1-badge-kicker l1-kicker-gold">Student Experience</span>
                <h2>Everything Students Need to Stay on Track</h2>
                <p>A modern learning environment engineered to eliminate friction, keep your study materials organized, and prepare you methodically for exam success.</p>
            </div>

            <div class="l1-student-grid">
                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-calendar-days"></i></div>
                    <h4>Live Class Timetable</h4>
                    <p>Real-time weekly schedule for classroom lessons and live interactive video sessions.</p>
                </div>

                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-file-pdf"></i></div>
                    <h4>Curated Study Materials</h4>
                    <p>Download syllabus summaries, topic notes, and practical guides prepared by our teachers.</p>
                </div>

                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-book-bookmark"></i></div>
                    <h4>Past Paper Archive</h4>
                    <p>Instant access to past examination questions categorized by topic and Pearson series code.</p>
                </div>

                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-video"></i></div>
                    <h4>Lesson Recordings</h4>
                    <p>Never fall behind. Revisit difficult explanations and complex derivations anytime.</p>
                </div>

                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-clipboard-check"></i></div>
                    <h4>Attendance Accountability</h4>
                    <p>Keep track of your punctuality, classroom attendance, and syllabus session completion.</p>
                </div>

                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-chart-pie"></i></div>
                    <h4>Assessment Progress</h4>
                    <p>Monitor your performance on class tests, topic assessments, and mock examinations.</p>
                </div>

                <div class="l1-student-card">
                    <div class="l1-st-icon"><i class="fas fa-hourglass-half"></i></div>
                    <h4>Official Exam Planning</h4>
                    <p>Synchronize your revision targets with Pearson Jan, May/Jun, and Oct exam session timetables.</p>
                </div>

                <div class="l1-student-card l1-st-card-ai">
                    <div class="l1-st-icon"><i class="fas fa-wand-magic-sparkles"></i></div>
                    <h4>AI Learning Support</h4>
                    <p>Use “Talk with AI” inside your student portal for instant revision assistance and problem hints.</p>
                </div>
            </div>

            <div class="l1-student-portal-banner">
                <div class="l1-spb-copy">
                    <h3>Ready to log in to your student workspace?</h3>
                    <p>Access your enrolled courses, lesson notes, and upcoming timetable right now.</p>
                </div>
                <div class="l1-spb-action">
                    <button type="button" class="l1-btn l1-btn-gold" data-open-auth="student">
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        <span>Open Student Portal</span>
                    </button>
                    <a href="https://chat.whatsapp.com/JjVSFWUIro19KTtDRRsc8W"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="l1-btn l1-btn-whatsapp-outline"
                       aria-label="Join official Edexcel College WhatsApp Community (opens in a new tab)">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                        <span>WhatsApp Community</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. PARENT CONFIDENCE SECTION -->
    <section class="l1-section l1-parents-section" id="parents">
        <div class="l1-container">
            <div class="l1-parent-wrapper">
                <div class="l1-parent-copy">
                    <span class="l1-badge-kicker">For Families &amp; Guardians</span>
                    <h2>A Learning Environment Parents Can Follow</h2>
                    <p class="l1-parent-tagline">“You don't have to wonder how your child is progressing.”</p>
                    <p class="l1-parent-desc">
                        As a parent supporting a child through demanding IGCSE or International A Level qualifications, clarity is everything. We provide a transparent framework that keeps you informed every step of the way.
                    </p>

                    <div class="l1-parent-checklist">
                        <div class="l1-chk-item">
                            <div class="l1-chk-icon"><i class="fas fa-circle-check"></i></div>
                            <div>
                                <strong>Real-Time Attendance Visibility</strong>
                                <p>Know immediately when your child arrives for class or attends live online sessions.</p>
                            </div>
                        </div>
                        <div class="l1-chk-item">
                            <div class="l1-chk-icon"><i class="fas fa-circle-check"></i></div>
                            <div>
                                <strong>Published Academic Assessments</strong>
                                <p>Review official trial scores and teacher evaluations so there are no surprises at exam time.</p>
                            </div>
                        </div>
                        <div class="l1-chk-item">
                            <div class="l1-chk-icon"><i class="fas fa-circle-check"></i></div>
                            <div>
                                <strong>Direct Academic Communication</strong>
                                <p>Communicate easily with our administration and teachers via dedicated WhatsApp and campus channels.</p>
                            </div>
                        </div>
                        <div class="l1-chk-item">
                            <div class="l1-chk-icon"><i class="fas fa-circle-check"></i></div>
                            <div>
                                <strong>Clear Class &amp; Fee Schedules</strong>
                                <p>Always have foresight of timetable changes, exam entry windows, and tuition payments.</p>
                            </div>
                        </div>
                    </div>

                    <div class="l1-parent-actions">
                        <button type="button" class="l1-btn l1-btn-navy" data-open-auth="parent">
                            <i class="fas fa-users" aria-hidden="true"></i>
                            <span>Sign In to Parent Portal</span>
                        </button>
                        <a href="#contact" class="l1-btn l1-btn-outline">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <span>Speak to Our Office</span>
                        </a>
                    </div>
                </div>

                <div class="l1-parent-visual">
                    <div class="l1-parent-card">
                        <div class="l1-pc-header">
                            <div class="l1-pc-status-dot"></div>
                            <span>Parent Portal &middot; Academic Overview</span>
                        </div>
                        <div class="l1-pc-body">
                            <div class="l1-pc-row">
                                <span class="l1-pc-label">Class Attendance</span>
                                <span class="l1-pc-val l1-pc-success">Verified Present &check;</span>
                            </div>
                            <div class="l1-pc-row">
                                <span class="l1-pc-label">Latest Chemistry Test</span>
                                <span class="l1-pc-val">A (88%)</span>
                            </div>
                            <div class="l1-pc-row">
                                <span class="l1-pc-label">Mathematics Unit P1 Mock</span>
                                <span class="l1-pc-val">A* (92%)</span>
                            </div>
                            <div class="l1-pc-row">
                                <span class="l1-pc-label">Upcoming Exam Series</span>
                                <span class="l1-pc-val">Pearson IAL &middot; Scheduled</span>
                            </div>
                        </div>
                        <div class="l1-pc-footer">
                            <i class="fas fa-shield-halved"></i>
                            <span>Secure family accountability system</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 10. TIMETABLE / UPCOMING CLASSES -->
    <section class="l1-section l1-timetable-section" id="timetable">
        <div class="l1-container">
            <div class="l1-timetable-header">
                <div>
                    <span class="l1-badge-kicker">Schedule of Classes</span>
                    <h2>Upcoming Academic Timetable</h2>
                    <p>Live timetable of online classes for students worldwide, plus physical classes at the Kandy campus when scheduled.</p>
                </div>
                <div class="l1-week-nav-box">
                    <span class="l1-week-label">Navigate Week:</span>
                    <div class="l1-week-nav">
                        <a class="l1-week-btn" href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] - 1], 'timetable')) ?>">
                            <i class="fas fa-chevron-left"></i> Previous Week
                        </a>
                        <a class="l1-week-btn<?= (int)$hp['weekOffset'] === 0 ? ' is-current' : '' ?>" href="<?= e(homepage_url(['timetable_week' => 0], 'timetable')) ?>">
                            This Week
                        </a>
                        <a class="l1-week-btn" href="<?= e(homepage_url(['timetable_week' => (int)$hp['weekOffset'] + 1], 'timetable')) ?>">
                            Next Week <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="l1-timetable-wrapper">
                <?php if (!empty($lessons)): ?>
                <div class="l1-timetable-grid">
                    <?php foreach ($lessons as $lesson): ?>
                    <article class="l1-lesson-card">
                        <div class="l1-lesson-timebox">
                            <span class="l1-lesson-day"><?= e(homepage_format_date($lesson['date'] ?? '', 'D')) ?></span>
                            <strong class="l1-lesson-date"><?= e(homepage_format_date($lesson['date'] ?? '', 'd M')) ?></strong>
                            <span class="l1-lesson-hours"><i class="fas fa-clock"></i> <?= e(homepage_format_time($lesson['start_time'] ?? '')) ?></span>
                        </div>
                        <div class="l1-lesson-details">
                            <span class="l1-lesson-class-tag"><?= e($lesson['class_name'] ?? 'Class') ?></span>
                            <h4 class="l1-lesson-subj"><?= e($lesson['subject_name'] ?? 'Subject') ?></h4>
                            <div class="l1-lesson-instructor">
                                <i class="fas fa-chalkboard-user"></i>
                                <span><?= e($lesson['teacher_name'] ?? 'Faculty Member') ?></span>
                                <?php if (!empty($lesson['room_name'])): ?>
                                <span class="l1-lesson-room">&middot; <i class="fas fa-door-open"></i> <?= e($lesson['room_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="l1-lesson-action">
                            <a href="<?= e($apply) ?>" class="l1-lesson-btn" title="Reserve a place in this class">
                                <span>Join Class</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="l1-timetable-empty">
                    <i class="fas fa-calendar-xmark"></i>
                    <h3>No scheduled classes in this date window</h3>
                    <p>Please use the week selector above to view other timetable sessions, or contact our admissions office for individual scheduling.</p>
                    <a href="<?= e(homepage_url(['timetable_week' => 0], 'timetable')) ?>" class="l1-btn l1-btn-navy">Return to Current Week</a>
                </div>
                <?php endif; ?>
            </div>

            <div class="l1-timetable-foot">
                <div class="l1-tf-info">
                    <i class="fas fa-info-circle"></i>
                    <span>All times are displayed in Sri Lanka Standard Time (GMT+5:30). Online students receive session links via their student portal.</span>
                </div>
                <a href="<?= e($seoBase . '/edexcel-classes') ?>" class="l1-btn l1-btn-ghost-navy">
                    <span>View Complete Timetable Archive</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- 11. FUTURE CTA SECTION -->
    <section class="l1-future-cta">
        <div class="l1-container">
            <div class="l1-future-card">
                <div class="l1-future-glow" aria-hidden="true"></div>
                <div class="l1-future-copy">
                    <span class="l1-badge-kicker l1-kicker-gold">Admissions Open</span>
                    <h2>Your Next Academic Step Starts Here.</h2>
                    <p>Explore our programmes, meet our teachers and find the right learning pathway for your academic ambitions.</p>
                    <div class="l1-future-buttons">
                        <a href="#programmes" class="l1-btn l1-btn-gold">
                            <i class="fas fa-compass" aria-hidden="true"></i>
                            <span>Explore Programmes</span>
                        </a>
                        <a href="<?= e($enquire) ?>" class="l1-btn l1-btn-ghost-light">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            <span>Enquire Now</span>
                        </a>
                        <a href="<?= e($apply) ?>" class="l1-btn l1-btn-solid-light">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            <span>Apply for Admission</span>
                        </a>
                    </div>
                </div>
                <div class="l1-future-meta">
                    <div class="l1-fm-item">
                        <strong><?= homepage_stat($hp, 'teachers') ?>+</strong>
                        <span>Specialist Teachers</span>
                    </div>
                    <div class="l1-fm-divider" aria-hidden="true"></div>
                    <div class="l1-fm-item">
                        <strong><?= homepage_stat($hp, 'students') ?>+</strong>
                        <span>Enrolled Students</span>
                    </div>
                    <div class="l1-fm-divider" aria-hidden="true"></div>
                    <div class="l1-fm-item">
                        <strong>100%</strong>
                        <span>Pearson Edexcel Focused</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 12. CATEGORIZED FAQ ACCORDION -->
    <section class="l1-section l1-faq-section" id="faq">
        <div class="l1-container">
            <div class="l1-section-header">
                <span class="l1-badge-kicker">Help &amp; Information</span>
                <h2>Frequently Asked Questions</h2>
                <p>Straightforward answers regarding our Edexcel curriculum, student enrollment, exams, and teaching methods.</p>
            </div>

            <!-- FAQ Category Tabs -->
            <div class="l1-faq-tabs" role="tablist" aria-label="FAQ categories">
                <button type="button" class="l1-faq-tab is-active" data-faq-tab="all" role="tab" aria-selected="true">
                    <span>All Questions</span>
                </button>
                <?php foreach ($faqGroups as $groupKey => $group): ?>
                <button type="button" class="l1-faq-tab" data-faq-tab="<?= e($groupKey) ?>" role="tab" aria-selected="false">
                    <i class="fas <?= e($group['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($group['title']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Accordion Items -->
            <div class="l1-faq-accordion" data-faq-accordion>
                <?php foreach ($faqGroups as $groupKey => $group): ?>
                    <?php foreach ($group['items'] as $item): ?>
                    <details class="l1-faq-item" data-faq-cat="<?= e($groupKey) ?>">
                        <summary class="l1-faq-summary">
                            <span class="l1-faq-question"><?= e($item['q']) ?></span>
                            <span class="l1-faq-icon" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                        </summary>
                        <div class="l1-faq-answer">
                            <p><?= e($item['a']) ?></p>
                        </div>
                    </details>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <div class="l1-faq-contact-prompt">
                <p>Have a question not answered here? Our admissions coordinators are happy to assist you.</p>
                <a href="#contact" class="l1-btn l1-btn-ghost-navy">Contact Admissions Office</a>
            </div>
        </div>
    </section>

    <!-- 13. CAMPUS LOCATION & DIRECT CONTACT -->
    <section class="l1-section l1-contact-section" id="contact">
        <div class="l1-container">
            <div class="l1-contact-card">
                <div class="l1-contact-grid">
                    <div class="l1-contact-details">
                        <span class="l1-badge-kicker l1-kicker-gold">Campus &amp; Admissions</span>
                        <h2>Visit Edexcel College in Kandy</h2>
                        <p class="l1-contact-intro">
                            We welcome parents and prospective students to visit our college campus to discuss academic pathways, inspect study spaces, and consult with our coordinators.
                        </p>

                        <div class="l1-contact-info-list">
                            <div class="l1-cil-item">
                                <div class="l1-cil-icon"><i class="fas fa-location-dot"></i></div>
                                <div>
                                    <strong>Campus Address</strong>
                                    <p><a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer"><?= nl2br(e($contact['address'])) ?></a></p>
                                </div>
                            </div>
                            <div class="l1-cil-item">
                                <div class="l1-cil-icon"><i class="fas fa-clock"></i></div>
                                <div>
                                    <strong>Office &amp; Consultation Hours</strong>
                                    <p><?= e($contact['hours']) ?></p>
                                </div>
                            </div>
                            <div class="l1-cil-item">
                                <div class="l1-cil-icon"><i class="fas fa-phone"></i></div>
                                <div>
                                    <strong>Telephone &amp; WhatsApp</strong>
                                    <p>
                                        <a href="tel:<?= e($contact['phone_tel']) ?>"><?= e($contact['phone']) ?></a>
                                        &middot;
                                        <a href="https://wa.me/<?= e($contact['whatsapp']) ?>" target="_blank" rel="noopener noreferrer" class="l1-wa-link">
                                            <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                                        </a>
                                    </p>
                                </div>
                            </div>
                            <div class="l1-cil-item">
                                <div class="l1-cil-icon"><i class="fas fa-envelope"></i></div>
                                <div>
                                    <strong>Email Inquiries</strong>
                                    <p><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></p>
                                </div>
                            </div>
                        </div>

                        <div class="l1-contact-btns">
                            <a class="l1-btn l1-btn-gold" href="<?= e($apply) ?>">Apply for Admission</a>
                            <a class="l1-btn l1-btn-ghost-light" href="<?= e($seoBase . '/contact') ?>">Full Contact Details</a>
                            <button class="l1-btn l1-btn-portal-light" type="button" data-open-auth="login">Sign In to Portals</button>
                        </div>
                    </div>

                    <div class="l1-contact-map-col">
                        <div class="l1-map-frame-wrapper">
                            <iframe
                                title="Edexcel College Location on Google Maps"
                                src="<?= e($mapsEmbed) ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                            ></iframe>
                        </div>
                        <a class="l1-map-open-link" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-arrow-up-right-from-square"></i>
                            <span>Open in Google Maps</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- 14. INSTITUTIONAL FOOTER -->
<footer class="l1-site-footer">
    <div class="l1-container">
        <div class="l1-footer-grid">
            <!-- Brand Column -->
            <div class="l1-footer-col l1-col-brand">
                <div class="l1-footer-brand">
                    <span class="l1-brand-crest"><i class="fas fa-landmark"></i></span>
                    <strong><?= e($institute) ?></strong>
                </div>
                <p class="l1-footer-tagline">
                    Pearson Edexcel IGCSE and International A Level classes. Around 90% are live online for students worldwide. Physical classes are at the Kandy campus in Sri Lanka.
                </p>
                <div class="l1-footer-contact-brief">
                    <p><i class="fas fa-location-dot"></i> <?= e(preg_replace('/\s+/', ' ', trim((string)($contact['address'] ?? '')))) ?></p>
                    <p><i class="fas fa-phone"></i> <a href="tel:<?= e($contact['phone_tel']) ?>"><?= e($contact['phone']) ?></a></p>
                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></p>
                </div>
            </div>

            <!-- Col 2: Pathways -->
            <div class="l1-footer-col">
                <h4>Academic Pathways</h4>
                <nav class="l1-footer-nav" aria-label="Academic Pathways">
                    <a href="<?= e($seoBase . '/online-classes') ?>">Online Classes Worldwide</a>
                    <a href="<?= e($seoBase . '/subjects') ?>">Subjects</a>
                    <a href="<?= e($seoBase . '/edexcel-classes') ?>">Edexcel Classes Overview</a>
                    <a href="<?= e($seoBase . '/edexcel-o-level') ?>">Pearson Edexcel IGCSE</a>
                    <a href="<?= e($seoBase . '/edexcel-a-level') ?>">International A Level (IAL)</a>
                    <a href="<?= e($seoBase . '/exam-preparation') ?>">Past Paper Exam Preparation</a>
                    <a href="<?= e($seoBase . '/pearson-exams') ?>">Pearson Exam Guide</a>
                    <a href="<?= e($seoBase . '/resources') ?>">Academic Resources</a>
                </nav>
            </div>

            <!-- Col 3: College Info -->
            <div class="l1-footer-col">
                <h4>College &amp; Campus</h4>
                <nav class="l1-footer-nav" aria-label="College and Campus">
                    <a href="<?= e($seoBase . '/about') ?>">About Edexcel College</a>
                    <a href="<?= e($seoBase . '/teachers/') ?>">Faculty Directory</a>
                    <a href="#journey">The Learning System</a>
                    <a href="#timetable">Upcoming Timetable</a>
                    <a href="<?= e($seoBase . '/locations/kandy') ?>">Kandy Campus</a>
                    <a href="<?= e($seoBase . '/faq') ?>">Help &amp; FAQ</a>
                    <a href="<?= e($seoBase . '/contact') ?>">Contact &amp; Visits</a>
                </nav>
            </div>

            <!-- Col 4: Portals & Admissions -->
            <div class="l1-footer-col">
                <h4>Portals &amp; Admissions</h4>
                <div class="l1-footer-portal-box">
                    <a href="<?= e($apply) ?>" class="l1-f-portal-link is-featured">
                        <i class="fas fa-user-plus"></i>
                        <span>Apply for Admission</span>
                    </a>
                    <button type="button" data-open-auth="student" class="l1-f-portal-link">
                        <i class="fas fa-user-graduate"></i>
                        <span>Student Portal Sign In</span>
                    </button>
                    <button type="button" data-open-auth="parent" class="l1-f-portal-link">
                        <i class="fas fa-users"></i>
                        <span>Parent Portal Sign In</span>
                    </button>
                    <button type="button" data-open-auth="teacher" class="l1-f-portal-link">
                        <i class="fas fa-user-tie"></i>
                        <span>Staff Portal Sign In</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Official Disclaimer -->
        <div class="l1-footer-disclaimer">
            <div class="l1-disclaimer-badge"><i class="fas fa-shield-halved"></i> Official Notice</div>
            <p>
                <strong>Independent Institution:</strong> Edexcel College is an independent educational tuition centre preparing students for Pearson Edexcel examinations. We are not owned by, affiliated with, or endorsed by Pearson Education Ltd. Pearson and Edexcel are registered trademarks of Pearson Education Ltd. Official exam specifications and centre regulations can be verified on <a href="https://qualifications.pearson.com/" target="_blank" rel="noopener noreferrer">qualifications.pearson.com</a>.
            </p>
        </div>

        <!-- Bottom Bar -->
        <div class="l1-footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e($institute) ?>. All rights reserved.</p>
            <nav class="l1-footer-legal-nav" aria-label="Legal policies">
                <a href="<?= e($seoBase . '/terms') ?>">Terms &amp; Conditions</a>
                <span class="l1-legal-sep">&middot;</span>
                <a href="<?= e($seoBase . '/privacy-policy') ?>">Privacy Policy</a>
                <span class="l1-legal-sep">&middot;</span>
                <a href="<?= e($seoBase . '/refund-policy') ?>">Refund Policy</a>
            </nav>
        </div>
    </div>
</footer>

<!-- 15. FLOATING BOTTOM NAVIGATION BAR (Desktop Dock + Mobile Navigation) -->
<nav class="l1-mobile-bottom-nav" id="l1-mobile-bottom-nav" aria-label="Bottom Navigation">
    <a href="#home" class="l1-mn-item is-active" data-mn="home" aria-label="Home">
        <div class="l1-mn-icon-wrap"><i class="fas fa-house" aria-hidden="true"></i></div>
        <span class="l1-mn-label">Home</span>
    </a>
    <a href="#programmes" class="l1-mn-item" data-mn="programmes" aria-label="Programmes">
        <div class="l1-mn-icon-wrap"><i class="fas fa-graduation-cap" aria-hidden="true"></i></div>
        <span class="l1-mn-label">Programmes</span>
    </a>
    <a href="#teachers" class="l1-mn-item" data-mn="teachers" aria-label="Teachers">
        <div class="l1-mn-icon-wrap"><i class="fas fa-chalkboard-user" aria-hidden="true"></i></div>
        <span class="l1-mn-label">Teachers</span>
    </a>
    <a href="#timetable" class="l1-mn-item" data-mn="timetable" aria-label="Timetable">
        <div class="l1-mn-icon-wrap"><i class="fas fa-calendar-days" aria-hidden="true"></i></div>
        <span class="l1-mn-label">Timetable</span>
    </a>
    <button type="button" class="l1-mn-item l1-mn-more" id="l1-more-toggle" aria-haspopup="dialog" aria-expanded="false" aria-controls="l1-more-sheet" aria-label="More navigation options">
        <div class="l1-mn-icon-wrap"><i class="fas fa-shapes" aria-hidden="true"></i></div>
        <span class="l1-mn-label">More</span>
    </button>
</nav>

<!-- MODERN "MORE" BOTTOM SHEET POPUP -->
<div class="l1-sheet-backdrop" id="l1-sheet-backdrop" hidden></div>
<aside class="l1-bottom-sheet" id="l1-more-sheet" role="dialog" aria-label="Secondary Navigation" aria-modal="true" hidden>
    <div class="l1-sheet-handle-bar" aria-hidden="true">
        <span class="l1-sheet-handle"></span>
    </div>
    <div class="l1-sheet-head">
        <div class="l1-sheet-title-box">
            <span class="l1-sheet-crest"><i class="fas fa-landmark"></i></span>
            <div>
                <h3 class="l1-sheet-title">Edexcel College</h3>
                <span class="l1-sheet-sub">Academic Navigation</span>
            </div>
        </div>
        <button type="button" class="l1-sheet-close" id="l1-sheet-close" aria-label="Close menu">&times;</button>
    </div>
    <div class="l1-sheet-body">
        <div class="l1-sheet-grid">
            <a href="#about" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-landmark"></i></div>
                <div class="l1-si-text">
                    <strong>About College</strong>
                    <span>Academic standards</span>
                </div>
            </a>
            <a href="#why" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-award"></i></div>
                <div class="l1-si-text">
                    <strong>Why Choose Us</strong>
                    <span>6 key advantages</span>
                </div>
            </a>
            <a href="#journey" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-route"></i></div>
                <div class="l1-si-text">
                    <strong>Learning System</strong>
                    <span>4-step method</span>
                </div>
            </a>
            <a href="#students" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="l1-si-text">
                    <strong>Student Experience</strong>
                    <span>Portal tools &amp; notes</span>
                </div>
            </a>
            <a href="#parents" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-hands-holding-child"></i></div>
                <div class="l1-si-text">
                    <strong>For Parents</strong>
                    <span>Attendance &amp; reports</span>
                </div>
            </a>
            <a href="#faq" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-circle-question"></i></div>
                <div class="l1-si-text">
                    <strong>Questions &amp; FAQ</strong>
                    <span>Exams &amp; admissions</span>
                </div>
            </a>
            <a href="#contact" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-map-location-dot"></i></div>
                <div class="l1-si-text">
                    <strong>Campus &amp; Visits</strong>
                    <span>Kandy location &amp; hours</span>
                </div>
            </a>
            <a href="<?= e($enquire) ?>" class="l1-sheet-item">
                <div class="l1-si-icon"><i class="fas fa-paper-plane"></i></div>
                <div class="l1-si-text">
                    <strong>Enquire Now</strong>
                    <span>Admissions team</span>
                </div>
            </a>
        </div>

        <!-- Portals Quick Access -->
        <div class="l1-sheet-portals">
            <span class="l1-sp-title">Access Secure Portals</span>
            <div class="l1-sp-buttons">
                <button type="button" data-open-auth="student" class="l1-sp-btn">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Portal</span>
                </button>
                <button type="button" data-open-auth="parent" class="l1-sp-btn">
                    <i class="fas fa-users"></i>
                    <span>Parent Portal</span>
                </button>
                <button type="button" data-open-auth="teacher" class="l1-sp-btn">
                    <i class="fas fa-user-tie"></i>
                    <span>Staff Portal</span>
                </button>
            </div>
        </div>

        <div class="l1-sheet-cta">
            <a href="<?= e($loginUrl) ?>" class="l1-btn l1-btn-navy l1-btn-block">
                <i class="fas fa-right-to-bracket"></i>
                <span>Login</span>
            </a>
            <a href="<?= e($apply) ?>" class="l1-btn l1-btn-gold l1-btn-block">
                <i class="fas fa-user-plus"></i>
                <span>Sign Up</span>
            </a>
        </div>
    </div>
</aside>

<!-- 16. SYSTEM INTEGRATIONS PRESERVED -->
<?php homepage_render_bottom_nav('home', 'layout1'); ?>
<?php homepage_render_auth('modal'); ?>
<?php homepage_render_foot($activeLayout); ?>
