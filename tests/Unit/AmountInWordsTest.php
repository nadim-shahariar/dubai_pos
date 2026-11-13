<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_aed_amounts_include_rounded_fils_and_large_values(): void
    {
        foreach ([
            '0.00' => 'Zero Dirhams Only.',
            '1.00' => 'One Dirham Only.',
            '483.00' => 'Four Hundred and Eighty-three Dirhams Only.',
            '0.05' => 'Zero Dirhams and Five Fils Only.',
            '1001.01' => 'One Thousand and One Dirhams and One Fil Only.',
            '999.999' => 'One Thousand Dirhams Only.',
            '1000000000.25' => 'One Billion Dirhams and Twenty-five Fils Only.',
        ] as $amount => $expected) {
            $this->assertSame($expected, AmountInWords::aed($amount));
        }
    }
}
