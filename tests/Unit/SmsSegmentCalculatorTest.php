<?php

namespace Tests\Unit;

use App\Domains\Communication\Services\SmsSegmentCalculator;
use Tests\TestCase;

class SmsSegmentCalculatorTest extends TestCase
{
    private function calc(): SmsSegmentCalculator
    {
        return new SmsSegmentCalculator();
    }

    public function test_empty_message_is_zero_segments(): void
    {
        $this->assertSame(0, $this->calc()->segmentsFor(''));
    }

    public function test_short_gsm7_message_is_one_segment(): void
    {
        $this->assertSame(1, $this->calc()->segmentsFor('Service starts at 9am this Sunday.'));
    }

    public function test_gsm7_message_at_the_160_char_boundary_is_still_one_segment(): void
    {
        $message = str_repeat('a', 160);
        $this->assertSame(1, $this->calc()->segmentsFor($message));
    }

    public function test_gsm7_message_over_160_chars_splits_into_153_char_segments(): void
    {
        $message = str_repeat('a', 161);
        $this->assertSame(2, $this->calc()->segmentsFor($message)); // ceil(161/153) = 2

        $message = str_repeat('a', 306); // 2 * 153
        $this->assertSame(2, $this->calc()->segmentsFor($message));

        $message = str_repeat('a', 307);
        $this->assertSame(3, $this->calc()->segmentsFor($message));
    }

    public function test_non_gsm7_characters_use_ucs2_segment_sizes(): void
    {
        $message = str_repeat('あ', 70); // Japanese — outside GSM-7
        $this->assertSame(1, $this->calc()->segmentsFor($message));

        $message = str_repeat('あ', 71);
        $this->assertSame(2, $this->calc()->segmentsFor($message)); // ceil(71/67) = 2
    }
}
