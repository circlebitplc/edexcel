<?php
declare(strict_types=1);

namespace Edexcel\Services;

/**
 * Offline Pearson-style practice items used when the AI provider is unavailable,
 * and as a fallback if generated JSON cannot be parsed.
 */
final class CoursoQuizBank
{
    /**
     * @return list<array{prompt:string,choices:list<string>,correct_index:int,explanation:string,example:string,difficulty:string,topics:list<string>}>
     */
    public static function all(): array
    {
        return [
            [
                'topics' => ['maths', 'mathematics', 'pure', 'ial'],
                'difficulty' => 'foundation',
                'prompt' => 'A taxi charges a Rs 200 flag fall plus Rs 40 per kilometre. What is the cost of a 7 km journey?',
                'choices' => ['Rs 280', 'Rs 440', 'Rs 480', 'Rs 1,400'],
                'correct_index' => 2,
                'explanation' => 'Cost = 200 + 40×7 = 200 + 280 = 480. The common mistake is forgetting the flag fall (Rs 280) or multiplying 200 by 7.',
                'example' => 'Kandy three-wheelers often use a standing charge plus a per-km rate — same linear model as y = mx + c.',
            ],
            [
                'topics' => ['maths', 'mathematics', 'algebra'],
                'difficulty' => 'core',
                'prompt' => 'Solve 3(x − 2) = 2x + 5.',
                'choices' => ['x = 11', 'x = 1', 'x = −1', 'x = 11/5'],
                'correct_index' => 0,
                'explanation' => 'Expand: 3x − 6 = 2x + 5. Subtract 2x: x − 6 = 5. Add 6: x = 11. Check: 3(9) = 27 and 22+5 = 27.',
                'example' => 'If three identical revision packs cost the same as two packs plus a Rs 5 booklet, you are solving this kind of equation.',
            ],
            [
                'topics' => ['maths', 'mathematics', 'quadratic'],
                'difficulty' => 'stretch',
                'prompt' => 'The graph of y = x² − 6x + 8 crosses the x-axis at',
                'choices' => ['x = 2 and x = 4', 'x = −2 and x = −4', 'x = 1 and x = 8', 'x = 6 and x = 8'],
                'correct_index' => 0,
                'explanation' => 'Factor: (x−2)(x−4)=0 so roots are 2 and 4. Completing the square also works: (x−3)² − 1 = 0.',
                'example' => 'Projectile height against time is a downward parabola; the roots are launch and landing times.',
            ],
            [
                'topics' => ['physics'],
                'difficulty' => 'foundation',
                'prompt' => 'A bus travels 12 km in 15 minutes. Its average speed is',
                'choices' => ['12 km/h', '15 km/h', '48 km/h', '180 km/h'],
                'correct_index' => 2,
                'explanation' => '15 minutes = 0.25 h. Speed = distance/time = 12 / 0.25 = 48 km/h. Do not divide by 15 minutes as if it were hours.',
                'example' => 'The Kandy–Peradeniya run is often quoted in minutes; convert to hours before using v = s/t.',
            ],
            [
                'topics' => ['physics'],
                'difficulty' => 'core',
                'prompt' => 'Which pair is a Newton third-law interaction while you sit on a chair?',
                'choices' => [
                    'Your weight and the normal force from the chair',
                    'The Earth pulling you down and you pulling the Earth up',
                    'Friction and air resistance',
                    'Your weight and friction',
                ],
                'correct_index' => 1,
                'explanation' => 'Third-law pairs act on different objects. Weight (Earth on you) pairs with you attracting the Earth. The normal force is a different interaction with the chair.',
                'example' => 'When you jump, you push the floor down; the floor pushes you up — that pair is why you leave the ground.',
            ],
            [
                'topics' => ['chemistry'],
                'difficulty' => 'core',
                'prompt' => 'Which is the best description of a covalent bond?',
                'choices' => [
                    'Transfer of electrons from metal to non-metal',
                    'Shared pair of electrons between atoms',
                    'Attraction between oppositely charged ions',
                    'A sea of delocalised electrons around metal ions',
                ],
                'correct_index' => 1,
                'explanation' => 'Covalent = shared pair. Ionic is transfer/attraction of ions. Metallic is delocalised electrons. Mixing these up is the most common exam slip.',
                'example' => 'The O–H bonds in water are covalent; that is why water is a molecule, not a lattice of H⁺ and O²⁻ in the liquid.',
            ],
            [
                'topics' => ['biology'],
                'difficulty' => 'core',
                'prompt' => 'In the human breathing system, gas exchange happens mainly in the',
                'choices' => ['trachea', 'bronchi', 'alveoli', 'diaphragm'],
                'correct_index' => 2,
                'explanation' => 'Alveoli give a large surface area, thin walls, and a moist surface next to capillaries. The diaphragm is a muscle that changes volume, not the exchange surface.',
                'example' => 'Asthma narrows airways before air reaches alveoli, so less oxygen reaches blood even if you are trying to breathe harder.',
            ],
            [
                'topics' => ['ict', 'computer', 'computing', 'it'],
                'difficulty' => 'foundation',
                'prompt' => 'Which is the most appropriate backup for a student laptop before an exam week?',
                'choices' => [
                    'Copy files only to the same laptop’s second folder',
                    'Cloud or an external drive kept in a different place',
                    'Email one screenshot to yourself',
                    'Rely on the Recycle Bin',
                ],
                'correct_index' => 1,
                'explanation' => 'A backup must survive loss or failure of the original device. Same-disk copies and Recycle Bin fail together with the laptop.',
                'example' => 'If a Kandy boarding-house laptop is stolen, a Google Drive or USB at home still holds the coursework.',
            ],
            [
                'topics' => ['ict', 'computer', 'computing'],
                'difficulty' => 'stretch',
                'prompt' => 'A hashing algorithm is used when storing passwords mainly because',
                'choices' => [
                    'It encrypts the password so it can be decrypted later',
                    'It produces a one-way value that can be compared without storing the password',
                    'It compresses the password to save disk space',
                    'It sorts users alphabetically',
                ],
                'correct_index' => 1,
                'explanation' => 'Hashing is one-way. On login, the typed password is hashed and compared. Encryption would be reversible and is the wrong model for password storage.',
                'example' => 'This college portal stores password hashes, not the password itself — staff cannot “look up” what you typed.',
            ],
            [
                'topics' => ['english', 'literature'],
                'difficulty' => 'core',
                'prompt' => 'In analytical writing, the most useful next sentence after a quotation is usually',
                'choices' => [
                    'Another quotation from a different page',
                    'A retell of the plot in your own words only',
                    'A comment on method and effect (how the language works)',
                    'A dictionary definition of a common word',
                ],
                'correct_index' => 2,
                'explanation' => 'PEE/PEEL: after Evidence, Explain the writer’s method and the effect on the reader. Plot retell and extra quotes without analysis score lower.',
                'example' => 'If a poem uses a storm image, say what feeling it creates — do not only repeat “there is a storm”.',
            ],
            [
                'topics' => ['economics'],
                'difficulty' => 'core',
                'prompt' => 'A rise in the market price of rice, other things equal, is most likely to',
                'choices' => [
                    'Increase quantity demanded',
                    'Decrease quantity demanded',
                    'Shift the demand curve right',
                    'Make rice a public good',
                ],
                'correct_index' => 1,
                'explanation' => 'Movement along the demand curve: higher price → lower quantity demanded. A shift needs a change in income, tastes, or related goods — not the good’s own price.',
                'example' => 'When imported rice becomes dearer in Colombo, households buy less of that grade or switch to another staple.',
            ],
            [
                'topics' => ['accounting', 'accounts'],
                'difficulty' => 'foundation',
                'prompt' => 'The accounting equation is',
                'choices' => [
                    'Assets = Liabilities − Capital',
                    'Assets = Liabilities + Capital',
                    'Assets + Liabilities = Capital',
                    'Income = Assets + Liabilities',
                ],
                'correct_index' => 1,
                'explanation' => 'Assets are financed by what the business owes (liabilities) and what the owner has invested (capital). Rearrange any way you like, but keep that balance.',
                'example' => 'A shop van (asset) might be partly a bank loan (liability) and partly the owner’s savings (capital).',
            ],
            [
                'topics' => ['business', 'commerce'],
                'difficulty' => 'core',
                'prompt' => 'Market research that uses existing published data is called',
                'choices' => ['Primary research', 'Secondary research', 'Sampling', 'Branding'],
                'correct_index' => 1,
                'explanation' => 'Secondary = already collected (census, news, past sales). Primary = you collect it (surveys, interviews). Sampling is a method inside primary research.',
                'example' => 'A Kandy café checking TripAdvisor reviews before changing the menu is using secondary research.',
            ],
            [
                'topics' => ['study', 'exam', 'revision'],
                'difficulty' => 'foundation',
                'prompt' => 'The most effective last 20 minutes before a paper is usually',
                'choices' => [
                    'Reading a brand-new chapter for the first time',
                    'Cramming unmarked past papers without checking answers',
                    'A short recap of formulas/quotes you already practised, then calm breathing',
                    'Switching subjects every 60 seconds',
                ],
                'correct_index' => 2,
                'explanation' => 'New material right before an exam raises anxiety and rarely sticks. Retrieval of practised items plus settling your nerves is higher yield.',
                'example' => 'Athletes warm up skills they already have; they do not learn a new serve in the tunnel.',
            ],
        ];
    }

    /**
     * @param list<string> $needles
     * @return list<array<string,mixed>>
     */
    public static function pick(array $needles, string $difficulty, int $limit = 5): array
    {
        $all = self::all();
        $needles = array_map('strtolower', $needles);
        $scored = [];
        foreach ($all as $i => $item) {
            $score = 0;
            foreach ($item['topics'] as $topic) {
                foreach ($needles as $n) {
                    if ($n !== '' && (str_contains($n, $topic) || str_contains($topic, $n))) {
                        $score += 3;
                    }
                }
            }
            if ($item['difficulty'] === $difficulty) {
                $score += 2;
            }
            $scored[] = ['i' => $i, 'score' => $score, 'item' => $item];
        }
        usort($scored, static function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $a['i'] <=> $b['i'];
        });
        if (($scored[0]['score'] ?? 0) < 1) {
            shuffle($all);
            return array_slice($all, 0, $limit);
        }
        $out = [];
        foreach ($scored as $row) {
            $out[] = $row['item'];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }
}
