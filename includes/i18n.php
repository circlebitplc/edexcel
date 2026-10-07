<?php
declare(strict_types=1);

function eck_lang(): string
{
    $posted = strtolower(trim((string)($_GET['lang'] ?? '')));
    if (in_array($posted, ['en', 'si'], true)) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        if (!headers_sent()) {
            setcookie('eck_lang', $posted, [
                'expires' => time() + 86400 * 400,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['eck_lang'] = $posted;
        return $posted;
    }
    $cookie = strtolower(trim((string)($_COOKIE['eck_lang'] ?? 'en')));
    return $cookie === 'si' ? 'si' : 'en';
}

function eck_t(string $key, ?string $lang = null): string
{
    $lang = $lang ?? eck_lang();
    static $map = null;
    if ($map === null) {
        $map = [
            'en' => [
                'lang.en' => 'English',
                'lang.si' => 'සිංහල',
                'fees.title' => 'Balance & pay',
                'fees.intro' => 'Wallet (counter), class fees (card, bank slip, or cash), and receipts in one place.',
                'fees.intro_clear' => 'Two kinds of fees: monthly wallet (office counter) and per-class fees that unlock live class + recordings.',
                'fees.due' => 'Amount due',
                'fees.paid' => 'Paid',
                'fees.next' => 'Next instalment',
                'fees.none' => 'None',
                'fees.wallet' => 'Monthly wallet',
                'fees.wallet_help' => 'Pay at the college counter after attendance.',
                'fees.lessons' => 'Class fees (live & recordings)',
                'fees.lessons_help' => 'Pay online, by bank slip, or cash to unlock join + replay.',
                'fees.pay_next' => 'Pay next class fee',
                'fees.pay_counter' => 'Pay wallet at the counter',
                'fees.pay_counter_help' => 'Show this screen at the office.',
                'fees.blocked' => '%d class fee(s) still block live join or recordings.',
                'fees.unblocked' => 'No class fees blocking live join or recordings.',
                'fees.how_title' => 'How fees work',
                'fees.how_1' => 'Monthly wallet = office/counter billing.',
                'fees.how_2' => 'Class fee = unlocks that lesson’s live room + recording.',
                'fees.how_3' => 'Use Pay now on any unpaid class row.',
                'fees.receipts' => 'Receipts',
                'fees.gateway.cash' => 'Cash',
                'fees.gateway.onepay' => 'OnePay',
                'fees.gateway.bank' => 'Bank transfer',
                'fees.gateway.wallet' => 'Wallet',
                'fees.status.paid' => 'Paid',
                'fees.status.pending' => 'Pending',
                'fees.status.due' => 'Due',
                'fees.status.waived' => 'Waived',
                'fees.status.unpaid' => 'Unpaid',
                'fees.pay_now' => 'Pay now',
                'fees.already_paid' => 'Already paid',
                'fees.empty_wallet' => 'Nothing due on the monthly wallet. A line appears after you are marked present or late.',
                'fees.empty_lessons' => 'No class-fee rows yet.',
                'fees.empty_receipts' => 'No receipts yet.',
                'att.title' => 'My attendance',
                'att.intro' => 'This month. Teachers mark present or absent in class; parents get a WhatsApp note for absences.',
                'att.present' => 'Present',
                'att.absent' => 'Absent',
                'att.late' => 'Late',
                'att.month' => 'This month',
                'att.date' => 'Date',
                'att.class' => 'Class',
                'att.status' => 'Status',
                'att.empty' => 'No attendance marked this month yet.',
                'parent.brand' => 'Edexcel College',
                'parent.view' => 'Parent view',
                'parent.today' => 'Today',
                'parent.fees' => 'Fees due',
                'parent.attendance' => 'This month',
                'parent.invalid' => 'This link is not valid',
                'parent.invalid_help' => 'Sign in with the parent WhatsApp number, or ask your child to copy a new link from Student portal → Settings.',
                'parent.login' => 'Parent sign-in',
                'parent.login_help' => 'We send a 6-digit code to WhatsApp. You will see every child linked to this number.',
                'parent.phone' => 'WhatsApp number',
                'parent.send' => 'Send code',
                'parent.code' => 'Code',
                'parent.verify' => 'Sign in',
                'parent.children' => 'Your children',
                'parent.logout' => 'Sign out',
                'parent.no_classes' => 'No classes today.',
            ],
            'si' => [
                'lang.en' => 'English',
                'lang.si' => 'සිංහල',
                'fees.title' => 'ශේෂය සහ ගෙවීම',
                'fees.intro' => 'මාසික පසුම්බිය, පන්ති ගාස්තු (කාඩ්පත, බැංකු තැන්පත, හෝ මුදල්) සහ රිසිට්පත් එකම තැනක.',
                'fees.intro_clear' => 'ගාස්තු වර්ග දෙකක්: මාසික පසුම්බිය (කාර්යාලයේ) සහ සජීවී පන්තිය/පටිගත කිරීම් අගුළු හරින පන්ති ගාස්තු.',
                'fees.due' => 'ගෙවිය යුතු මුදල',
                'fees.paid' => 'ගෙවා ඇත',
                'fees.next' => 'ඊළඟ වාරිකය',
                'fees.none' => 'නැත',
                'fees.wallet' => 'මාසික පසුම්බිය',
                'fees.wallet_help' => 'පැමිණීමෙන් පසු කාර්යාලයේ ගෙවන්න.',
                'fees.lessons' => 'පන්ති ගාස්තු (සජීවී සහ පටිගත)',
                'fees.lessons_help' => 'සජීවී පන්තිය සහ පටිගත කිරීම් සඳහා මාර්ගගතව, බැංකු තැන්පතින් හෝ මුදලින් ගෙවන්න.',
                'fees.pay_next' => 'ඊළඟ පන්ති ගාස්තුව ගෙවන්න',
                'fees.pay_counter' => 'පසුම්බිය කාර්යාලයේ ගෙවන්න',
                'fees.pay_counter_help' => 'මෙම තිරය කාර්යාලයේ පෙන්වන්න.',
                'fees.blocked' => 'පන්ති ගාස්තු %d ක් තවමත් සජීවී පන්තිය හෝ පටිගත කිරීම් අවහිර කරයි.',
                'fees.unblocked' => 'සජීවී පන්තිය හෝ පටිගත කිරීම් අවහිර කරන ගාස්තුවක් නැත.',
                'fees.how_title' => 'ගාස්තු ක්‍රමය',
                'fees.how_1' => 'මාසික පසුම්බිය = කාර්යාල ගාස්තු.',
                'fees.how_2' => 'පන්ති ගාස්තු = එම පාඩමේ සජීවී කාමරය සහ පටිගත කිරීම.',
                'fees.how_3' => 'නොගෙවූ පේළියක Pay now භාවිතා කරන්න.',
                'fees.receipts' => 'රිසිට්පත්',
                'fees.gateway.cash' => 'මුදල්',
                'fees.gateway.onepay' => 'OnePay',
                'fees.gateway.bank' => 'බැංකු තැන්පත',
                'fees.gateway.wallet' => 'පසුම්බිය',
                'fees.status.paid' => 'ගෙවා ඇත',
                'fees.status.pending' => 'බලාපොරොත්තුවෙන්',
                'fees.status.due' => 'ගෙවිය යුතුයි',
                'fees.status.unpaid' => 'නොගෙවූ',
                'fees.status.waived' => 'නිදහස් කළ',
                'fees.pay_now' => 'දැන් ගෙවන්න',
                'fees.already_paid' => 'දැනටමත් ගෙවා ඇත',
                'fees.empty_wallet' => 'මාසික පසුම්බියේ ගෙවිය යුතු මුදලක් නැත. පැමිණ සිටියේ යැයි සලකුණු කළ පසු පේළියක් පෙනේ.',
                'fees.empty_lessons' => 'පන්ති ගාස්තු තවම නැත.',
                'fees.empty_receipts' => 'රිසිට්පත් තවම නැත.',
                'att.title' => 'මගේ පැමිණීම',
                'att.intro' => 'මෙම මාසය. ගුරුවරු පන්තියේදී පැමිණීම සලකුණු කරයි; නොපැමිණි විට දෙමාපියන්ට WhatsApp පණිවිඩයක් යයි.',
                'att.present' => 'පැමිණියේය',
                'att.absent' => 'නොපැමිණියේය',
                'att.late' => 'ප්‍රමාදයි',
                'att.month' => 'මෙම මාසය',
                'att.date' => 'දිනය',
                'att.class' => 'පන්තිය',
                'att.status' => 'තත්ත්වය',
                'att.empty' => 'මෙම මාසයේ පැමිණීම තවම සලකුණු කර නැත.',
                'parent.brand' => 'Edexcel College',
                'parent.view' => 'දෙමාපිය දසුන',
                'parent.today' => 'අද',
                'parent.fees' => 'ගෙවිය යුතු ගාස්තු',
                'parent.attendance' => 'මෙම මාසය',
                'parent.invalid' => 'මෙම සබැඳිය වලංගු නොවේ',
                'parent.invalid_help' => 'දෙමාපිය WhatsApp අංකයෙන් පිවිසෙන්න, නැතහොත් දරුවාගෙන් Student portal → Settings හි නව සබැඳියක් ඉල්ලන්න.',
                'parent.login' => 'දෙමාපිය පිවිසුම',
                'parent.login_help' => 'WhatsApp වෙත ඉලක්කම් 6ක කේතයක් යවමු. මෙම අංකයට සම්බන්ධ සෑම දරුවෙකුම පෙනේ.',
                'parent.phone' => 'WhatsApp අංකය',
                'parent.send' => 'කේතය යවන්න',
                'parent.code' => 'කේතය',
                'parent.verify' => 'පිවිසෙන්න',
                'parent.children' => 'ඔබේ දරුවන්',
                'parent.logout' => 'ඉවත් වන්න',
                'parent.no_classes' => 'අද පන්ති නැත.',
            ],
        ];
    }
    $table = $map[$lang] ?? $map['en'];
    return $table[$key] ?? ($map['en'][$key] ?? $key);
}

function eck_lang_toggle(string $extraQuery = ''): string
{
    $lang = eck_lang();
    $base = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    $qs = $_GET;
    unset($qs['lang']);
    parse_str(ltrim($extraQuery, '?&'), $more);
    $qs = array_merge($qs, $more);
    $en = $base . '?' . http_build_query(array_merge($qs, ['lang' => 'en']));
    $si = $base . '?' . http_build_query(array_merge($qs, ['lang' => 'si']));
    $enClass = $lang === 'en' ? 'fw-bold' : '';
    $siClass = $lang === 'si' ? 'fw-bold' : '';
    return '<div class="small mb-3">'
        . '<a class="' . $enClass . '" href="' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '">English</a>'
        . ' · '
        . '<a class="' . $siClass . '" href="' . htmlspecialchars($si, ENT_QUOTES, 'UTF-8') . '">සිංහල</a>'
        . '</div>';
}
