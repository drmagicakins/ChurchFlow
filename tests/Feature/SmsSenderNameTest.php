<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmsSenderNameTest extends TestCase
{
    private function resolver(): \App\Domains\Communication\Services\SmsSenderName
    {
        return new \App\Domains\Communication\Services\SmsSenderName;
    }

    /** The deployment that HAS per-church headers approved. */
    private function approvedForChurchNames(): \App\Domains\Communication\Services\SmsSenderName
    {
        return new \App\Domains\Communication\Services\SmsSenderName(useChurchName: true);
    }

    /**
     * THE DEFAULT (2026-11-17): the account's own approved header is used, and a
     * church name is NOT sent — Termii would refuse it before "ChurchFlow" was
     * approved as the account's header.
     */
    public function test_by_default_a_church_name_is_not_used_as_the_sender(): void
    {
        $church = new \App\Models\Church(['name' => 'Grace Chapel']);
        $parish = new \App\Models\OrganizationalUnit(['name' => 'Lekki']);

        $this->assertNull($this->resolver()->for($church));
        $this->assertNull($this->resolver()->for($church, $parish));
    }

    public function test_an_explicit_name_is_still_honoured_by_default(): void
    {
        // An explicit name is the operator's deliberate choice, so the switch
        // does not override it.
        $this->assertSame('ChurchFlow', $this->resolver()->for(null, null, 'ChurchFlow'));
    }

    public function test_the_church_name_is_used_when_the_message_is_church_wide(): void
    {
        $church = new \App\Models\Church(['name' => 'Grace Chapel']);

        $this->assertSame('Grace Chapel', $this->approvedForChurchNames()->for($church));
    }

    public function test_a_parish_message_says_which_parish_it_is_from(): void
    {
        $church = new \App\Models\Church(['name' => 'Grace Chapel']);
        $parish = new \App\Models\OrganizationalUnit(['name' => 'Lekki']);

        // 10 alphanumeric characters (space and '·' do not count): the whole
        // name fits, so a parish message says both.
        $this->assertSame('Lekki · Grace', $this->approvedForChurchNames()->for($church, $parish));
    }

    public function test_an_explicit_name_wins(): void
    {
        $church = new \App\Models\Church(['name' => 'Grace Chapel']);

        $this->assertSame('Grace', $this->resolver()->for($church, null, 'Grace'));
    }

    public function test_no_church_at_all_yields_null_so_the_provider_falls_back(): void
    {
        $this->assertNull($this->resolver()->for(null, null));
    }

    public function test_a_name_with_no_church_but_a_parish_still_resolves(): void
    {
        $parish = new \App\Models\OrganizationalUnit(['name' => 'Lekki']);

        $this->assertSame('Lekki', $this->approvedForChurchNames()->for(null, $parish));
    }

    public function test_a_pasted_newline_does_not_become_a_sender_id(): void
    {
        // Termii refuses a `from` containing control characters, and the error
        // blames the configuration rather than the invisible character.
        // The newline becomes a space; "GraceChapel" is 11 alphanumeric letters
        // and therefore fits. The point is that control characters never reach
        // Termii, and that the cap is counted on letters/digits.
        $this->assertSame('Grace Chapel', $this->resolver()->clean("Grace\nChapel"));
        $this->assertSame('Lekki · Grace', $this->resolver()->clean("Lekki\r\n·\tGrace"));
        $this->assertNull($this->resolver()->clean("  \n\t "));
    }

    public function test_a_long_name_is_shortened_at_a_word_boundary(): void
    {
        // Counted on letters/digits, then cut at a word boundary: "Grace Chapel
        // International" keeps as many whole words as fit, never "Grace Chape".
        $this->assertSame('Grace Chapel', $this->resolver()->clean('Grace Chapel International'));
        $this->assertSame('Chapel', $this->resolver()->clean('Chapel'));
        $this->assertSame('GraceChapel', $this->resolver()->clean('GraceChapel'));
        $this->assertSame('Grace Chapel', $this->resolver()->clean('Grace Chapel'));
    }

    public function test_a_single_long_word_is_cut_rather_than_dropped(): void
    {
        $this->assertSame('Abcdefghijk', $this->resolver()->clean('Abcdefghijklmnop'));
    }
}