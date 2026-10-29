<?php
/**
 * tests/Unit/IcsGeneratorTest.php
 *
 * RFC 5545 compliance checks for the calendar invite generator. The
 * lines we assert against are the structural guarantees the spec is
 * picky about — bare LF, text escaping, METHOD/SEQUENCE pairing.
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\IcsGenerator;
use Tests\TestCase;

final class IcsGeneratorTest extends TestCase
{
    /** Collapse RFC 5545 line folds so substring assertions are sane. */
    private function unfold(string $ics): string
    {
        return preg_replace("/\r\n /", '', $ics);
    }

    public function test_it_uses_crlf_line_endings(): void
    {
        $appt = $this->createAppointment();
        $ics  = (new IcsGenerator())->generateForAppointment($appt);

        $this->assertStringContainsString("\r\n", $ics);
        // Any bare LF (LF not preceded by CR) is a spec violation.
        $bareLfCount = preg_match_all('/(?<!\r)\n/', $ics);
        $this->assertSame(0, $bareLfCount, 'Output contains bare LF without preceding CR');
    }

    public function test_it_escapes_commas_and_semicolons_in_text_fields(): void
    {
        $appt = $this->createAppointment([
            'notes' => 'one, two; three',
        ]);
        $ics      = (new IcsGenerator())->generateForAppointment($appt);
        $unfolded = $this->unfold($ics);

        // After escaping: `,` → `\,` and `;` → `\;` (literal backslash + char).
        $this->assertStringContainsString('one\\, two\\; three', $unfolded);
    }

    public function test_it_escapes_backslashes_and_newlines(): void
    {
        $appt = $this->createAppointment([
            'notes' => "back\\slash\nnewline",
        ]);
        $ics      = (new IcsGenerator())->generateForAppointment($appt);
        $unfolded = $this->unfold($ics);

        // backslashes doubled, real newlines become literal "\n".
        $this->assertStringContainsString('back\\\\slash\\nnewline', $unfolded);
    }

    public function test_it_generates_a_unique_uid_per_appointment(): void
    {
        $a = $this->createAppointment(['cancel_token' => str_repeat('a', 32)]);
        $b = $this->createAppointment(['cancel_token' => str_repeat('b', 32)]);

        $gen = new IcsGenerator();
        preg_match('/UID:([^\r\n]+)/', $gen->generateForAppointment($a), $mA);
        preg_match('/UID:([^\r\n]+)/', $gen->generateForAppointment($b), $mB);

        $this->assertNotEmpty($mA[1] ?? null);
        $this->assertNotEmpty($mB[1] ?? null);
        $this->assertNotSame($mA[1], $mB[1]);

        // UID format: <token>@<host> — both UIDs end with the same @host.
        $this->assertStringContainsString('@', $mA[1]);
    }

    public function test_cancellation_uses_method_cancel_with_incremented_sequence(): void
    {
        $appt = $this->createAppointment();
        $gen  = new IcsGenerator();

        $req    = $gen->generateForAppointment($appt, 0);
        $cancel = $gen->generateForCancellation($appt, 1);

        $this->assertStringContainsString("METHOD:REQUEST\r\n",   $req);
        $this->assertStringContainsString("STATUS:CONFIRMED\r\n", $req);
        $this->assertStringContainsString("SEQUENCE:0\r\n",       $req);

        $this->assertStringContainsString("METHOD:CANCEL\r\n",    $cancel);
        $this->assertStringContainsString("STATUS:CANCELLED\r\n", $cancel);
        $this->assertStringContainsString("SEQUENCE:1\r\n",       $cancel);

        // Same UID — that's the whole point of CANCEL semantics.
        preg_match('/UID:([^\r\n]+)/', $req,    $mReq);
        preg_match('/UID:([^\r\n]+)/', $cancel, $mCancel);
        $this->assertSame($mReq[1], $mCancel[1]);
    }

    public function test_dates_are_emitted_in_utc(): void
    {
        // Toronto in May is UTC-4 with DST. 14:30 Toronto → 18:30 UTC.
        $appt = $this->createAppointment([
            'starts_at' => '2026-05-20 14:30:00',
            'ends_at'   => '2026-05-20 15:30:00',
        ]);
        $ics = (new IcsGenerator())->generateForAppointment($appt);

        $this->assertStringContainsString("DTSTART:20260520T183000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20260520T193000Z\r\n",   $ics);
    }
}
