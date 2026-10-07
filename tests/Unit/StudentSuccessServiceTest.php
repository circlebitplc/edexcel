<?php
declare(strict_types=1);

namespace Edexcel\Tests;

use Edexcel\Services\StudentSuccessService;
use PHPUnit\Framework\TestCase;

final class StudentSuccessServiceTest extends TestCase
{
    /** @dataProvider scoreLevels */
    public function testScoreClassificationIsExplainable(float $score,string $expected):void
    { self::assertSame($expected,StudentSuccessService::classifyScore($score)); }

    /** @return array<string,array{float,string}> */
    public static function scoreLevels():array
    { return ['healthy'=>[0,'HEALTHY'],'watch'=>[25,'WATCH'],'at risk'=>[50,'AT_RISK'],'critical'=>[70,'CRITICAL']]; }
}
