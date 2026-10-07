<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Detect English / Sinhala / Tamil and fold incoming chat into English for intent matching.
 * Outgoing replies keep names, times, and URLs; only the wrapper phrases are translated.
 */
final class WhatsAppI18n
{
    public static function detect(string $text): string
    {
        if (preg_match('/\p{Tamil}/u', $text)) {
            return 'ta';
        }
        if (preg_match('/\p{Sinhala}/u', $text)) {
            return 'si';
        }
        $lower = mb_strtolower($text);
        $taHits = 0;
        foreach (['naalai', 'naalaikku', 'inru', 'indru', 'eppothu', 'enga', 'yaar', 'irukka', 'irukkaa', 'vakuppu', 'aasaan', 'aashiriyar'] as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $lower)) {
                $taHits++;
            }
        }
        $siHits = 0;
        foreach (['thiyenawada', 'thiyenawa', 'koheda', 'kawda', 'denna', 'mage', 'kohomada', 'ada', 'heta', 'ayubowan'] as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $lower)) {
                $siHits++;
            }
        }
        if ($taHits > $siHits && $taHits > 0) {
            return 'ta';
        }
        if ($siHits > 0) {
            return 'si';
        }
        return 'en';
    }

    public static function fold(string $text): string
    {
        $text = mb_strtolower($text);
        foreach (self::incomingMap() as $from => $to) {
            $quoted = preg_quote($from, '/');
            if (preg_match('/^[a-z0-9\']+$/i', $from)) {
                $text = preg_replace('/\b' . $quoted . '\b/u', $to, $text) ?? $text;
            } else {
                $text = str_replace($from, ' ' . $to . ' ', $text);
            }
        }
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function localize(string $reply, string $lang): string
    {
        if ($lang === 'en' || $reply === '') {
            return $reply;
        }
        $map = $lang === 'ta' ? self::taPhrases() : self::siPhrases();
        uksort($map, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($map as $en => $local) {
            $reply = str_replace($en, $local, $reply);
        }
        return $reply;
    }

    /**
     * @return array<string,string>
     */
    private static function incomingMap(): array
    {
        return [
            'අද' => 'today',
            'හෙට' => 'tomorrow',
            'ඊයේ' => 'yesterday',
            'පන්තිය' => 'class',
            'පන්ති' => 'class',
            'ක්ලාස්' => 'class',
            'කාලසටහන' => 'timetable',
            'ටයිම්ටේබල්' => 'timetable',
            'කවදාද' => 'when',
            'කවදා' => 'when',
            'කොහෙද' => 'where',
            'කොහේද' => 'where',
            'කවුද' => 'who',
            'ගුරුවරයා' => 'teacher',
            'ගුරු' => 'teacher',
            'සර්' => 'teacher',
            'මිස්' => 'teacher',
            'ලියාපදිංචි' => 'register',
            'ගාස්තු' => 'fees',
            'ගෙවීම' => 'payment',
            'පැමිණීම' => 'attendance',
            'විභාග' => 'exam',
            'නිවාඩු' => 'holiday',
            'තියෙනවද' => 'class',
            'තියෙනවාද' => 'class',
            'තියෙනවා' => 'class',
            'මගේ' => 'my',
            'ඊළඟ' => 'next',
            'ඊලග' => 'next',
            'ගණන්' => 'number',
            'දෙන්න' => 'number',
            'இன்று' => 'today',
            'இன்னிக்கு' => 'today',
            'நாளை' => 'tomorrow',
            'நேற்று' => 'yesterday',
            'வகுப்பு' => 'class',
            'கிளாஸ்' => 'class',
            'நேர அட்டவணை' => 'timetable',
            'கால அட்டவணை' => 'timetable',
            'டைம்டேபிள்' => 'timetable',
            'எப்போது' => 'when',
            'எங்கே' => 'where',
            'எங்க' => 'where',
            'யார்' => 'who',
            'ஆசிரியர்' => 'teacher',
            'சார்' => 'teacher',
            'பதிவு' => 'register',
            'சேர்க்கை' => 'register',
            'கட்டணம்' => 'fees',
            'வருகை' => 'attendance',
            'தேர்வு' => 'exam',
            'விடுமுறை' => 'holiday',
            'இருக்கா' => 'class',
            'இருக்கிறதா' => 'class',
            'இருக்காதா' => 'class',
            'அடுத்த' => 'next',
            'என்' => 'my',
            'naalai' => 'tomorrow',
            'naalaikku' => 'tomorrow',
            'inru' => 'today',
            'indru' => 'today',
            'eppothu' => 'when',
            'enga' => 'where',
            'yaar' => 'who',
            'irukka' => 'class',
            'irukkaa' => 'class',
            'vakuppu' => 'class',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function siPhrases(): array
    {
        return [
            'Yes — we offer' => 'ඔව් — අපි මේ පාඨමාලාව උගන්වනවා',
            'Yes — we offer ICT' => 'ඔව් — අපි ICT උගන්වනවා',
            'To join:' => 'එකතු වෙන්න:',
            'Register if you do not have an account:' => 'ගිණුමක් නැත්නම් ලියාපදිංචි වෙන්න:',
            'The class is currently scheduled.' => 'මේ පන්තිය දැනට කාලසටහනේ තියෙනවා.',
            '🚫 Cancelled' => '🚫 අවලංගුයි',
            'Reason:' => 'හේතුව:',
            'Join:' => 'එකතු වෙන්න:',
            'Register:' => 'ලියාපදිංචි:',
            'Or reply *7* for the office.' => 'කාර්යාලයට *7* ටයිප් කරන්න.',
            "I couldn't find a timetable entry for" => 'මේ සඳහා කාලසටහනක් හම්බුණේ නැහැ:',
            "I couldn't find that information in the institute database." => 'ආයතන දත්ත ගබඩාවේ ඒ තොරතුරු නැහැ.',
            'teachers' => 'ගුරුවරු',
            'Available classes' => 'තියෙන පන්ති',
            'Your Enrolled Classes' => 'ඔයා එකතු වෙලා ඉන්න පන්ති',
            'Welcome to Edexcel College!' => 'Edexcel College වෙත සාදරයෙන් පිළිගනිමු!',
            'Welcome back!' => 'ආයෙත් ආවාට සතුටුයි!',
            "I'm the Edexcel College assistant for *students and parents*. Ask in English, Sinhala, or Tamil about registration, login, classes, timetable, fees, attendance, exams, or parent WhatsApp updates." =>
                'මම Edexcel College WhatsApp සහායකයා. සිංහල, දෙමළ හෝ ඉංග්‍රීසියෙන් ලියාපදිංචිය, පන්ති, කාලසටහන, ගාස්තු, පැමිණීම, විභාග ගැන අහන්න.',
            'For example:' => 'උදාහරණ:',
            'How do I register?' => 'ලියාපදිංචි වෙන්නේ කොහොමද?',
            'What do I have today?' => 'අද මගේ පන්ති මොනවද?',
            'How much fees are due?' => 'ගාස්තු කොච්චරක් ඉතුරුද?',
            'How do parents get updates?' => 'දෙමාපියන්ට updates එනවද?',
            '*Shortcuts:* _1_ today · _2_ tomorrow · _3_ my classes · _4_ teachers · _5_ this week · _6_ register · _7_ admin · _8_ exams' =>
                '*කෙටි මාර්ග:* _1_ අද · _2_ හෙට · _3_ මගේ පන්ති · _4_ ගුරුවරු · _5_ මේ සතිය · _6_ ලියාපදිංචි · _7_ කාර්යාලය · _8_ විභාග',
            'Type *menu* anytime to see this again.' => 'මේ ලැයිස්තුවට *menu* ටයිප් කරන්න.',
            'Today' => 'අද',
            'Tomorrow' => 'හෙට',
            'This week' => 'මේ සතිය',
            'Next 14 days' => 'ඊළඟ දින 14',
            'Upcoming' => 'ඊළඟ',
            'timetable' => 'කාලසටහන',
            'full / waitlist' => 'පිරිලා / waitlist',
            'enrolled' => 'එකතු වෙලා',
            "Sorry, I couldn't retrieve that information right now. Please try again in a moment." =>
                'දැන් ඒ තොරතුරු ගන්න බැරි වුණා. මඳ වෙලාවකින් ආයෙත් උත්සාහ කරන්න.',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function taPhrases(): array
    {
        return [
            'Yes — we offer' => 'ஆம் — இந்த பாடத்தை நாங்கள் நடத்துகிறோம்',
            'To join:' => 'சேர:',
            'Register if you do not have an account:' => 'கணக்கு இல்லையென்றால் பதிவு செய்யுங்கள்:',
            'The class is currently scheduled.' => 'இந்த வகுப்பு தற்போது அட்டவணையில் உள்ளது.',
            '🚫 Cancelled' => '🚫 ரத்து',
            'Reason:' => 'காரணம்:',
            'Join:' => 'சேர:',
            'Register:' => 'பதிவு:',
            'Or reply *7* for the office.' => 'அலுவலகத்திற்கு *7* அனுப்புங்கள்.',
            "I couldn't find a timetable entry for" => 'இதற்கான நேர அட்டவணை இல்லை:',
            "I couldn't find that information in the institute database." => 'நிறுவன தரவுத்தளத்தில் அந்தத் தகவல் இல்லை.',
            'teachers' => 'ஆசிரியர்கள்',
            'Available classes' => 'கிடைக்கும் வகுப்புகள்',
            'Your Enrolled Classes' => 'நீங்கள் சேர்ந்துள்ள வகுப்புகள்',
            'Welcome to Edexcel College!' => 'Edexcel College-க்கு வரவேற்கிறோம்!',
            'Welcome back!' => 'மீண்டும் வரவேற்கிறோம்!',
            "I'm the Edexcel College assistant for *students and parents*. Ask in English, Sinhala, or Tamil about registration, login, classes, timetable, fees, attendance, exams, or parent WhatsApp updates." =>
                'நான் Edexcel College WhatsApp உதவியாளர். ஆங்கிலம், சிங்களம் அல்லது தமிழில் பதிவு, வகுப்பு, நேர அட்டவணை, கட்டணம், வருகை, தேர்வு பற்றி கேளுங்கள்.',
            'For example:' => 'உதாரணம்:',
            'How do I register?' => 'எப்படி பதிவு செய்வது?',
            'What do I have today?' => 'இன்று எனக்கு என்ன வகுப்பு?',
            'How much fees are due?' => 'எவ்வளவு கட்டணம் நிலுவையில் உள்ளது?',
            'How do parents get updates?' => 'பெற்றோருக்கு அறிவிப்பு எப்படி வரும்?',
            '*Shortcuts:* _1_ today · _2_ tomorrow · _3_ my classes · _4_ teachers · _5_ this week · _6_ register · _7_ admin · _8_ exams' =>
                '*சுருக்குகள்:* _1_ இன்று · _2_ நாளை · _3_ என் வகுப்புகள் · _4_ ஆசிரியர்கள் · _5_ இந்த வாரம் · _6_ பதிவு · _7_ அலுவலகம் · _8_ தேர்வு',
            'Type *menu* anytime to see this again.' => 'மீண்டும் பார்க்க *menu* அனுப்புங்கள்.',
            'Today' => 'இன்று',
            'Tomorrow' => 'நாளை',
            'This week' => 'இந்த வாரம்',
            'Next 14 days' => 'அடுத்த 14 நாட்கள்',
            'Upcoming' => 'அடுத்தது',
            'timetable' => 'நேர அட்டவணை',
            'full / waitlist' => 'நிரம்பியது / waitlist',
            'enrolled' => 'சேர்ந்துள்ளார்',
            "Sorry, I couldn't retrieve that information right now. Please try again in a moment." =>
                'இப்போது அந்தத் தகவலை எடுக்க முடியவில்லை. சிறிது நேரம் கழித்து முயலுங்கள்.',
        ];
    }
}
