<?php
/**
 * tests/Unit/ValidatorTest.php
 *
 * Happy + unhappy path for each rule supported by the Validator.
 * Pure unit tests — no DB required, but we still extend TestCase to keep
 * the env / autoload bootstrap consistent.
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    /* ---- required ---- */

    public function test_required_passes_when_value_present(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
        $this->assertTrue($v->passes());
    }

    public function test_required_fails_when_empty(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertFalse($v->passes());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_required_fails_when_missing(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertFalse($v->passes());
        $this->assertArrayHasKey('name', $v->errors());
    }

    /* ---- email ---- */

    public function test_email_passes_for_valid(): void
    {
        $v = Validator::make(['email' => 'sarah.chen@example.com'], ['email' => 'required|email']);
        $this->assertTrue($v->passes());
    }

    public function test_email_fails_for_garbage(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'required|email']);
        $this->assertFalse($v->passes());
    }

    /* ---- min / max for strings ---- */

    public function test_min_passes_for_long_enough_string(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'min:2']);
        $this->assertTrue($v->passes());
    }

    public function test_min_fails_for_too_short_string(): void
    {
        $v = Validator::make(['name' => 'A'], ['name' => 'min:2']);
        $this->assertFalse($v->passes());
    }

    public function test_max_passes_for_short_enough_string(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'max:10']);
        $this->assertTrue($v->passes());
    }

    public function test_max_fails_for_too_long_string(): void
    {
        $v = Validator::make(['name' => str_repeat('x', 20)], ['name' => 'max:10']);
        $this->assertFalse($v->passes());
    }

    /* ---- min / max for numerics ---- */

    public function test_min_passes_for_large_enough_number(): void
    {
        $v = Validator::make(['n' => '15'], ['n' => 'numeric|min:5']);
        $this->assertTrue($v->passes());
    }

    public function test_max_fails_for_too_large_number(): void
    {
        $v = Validator::make(['n' => '500'], ['n' => 'numeric|max:100']);
        $this->assertFalse($v->passes());
    }

    /* ---- in ---- */

    public function test_in_passes_for_allowed_value(): void
    {
        $v = Validator::make(['status' => 'confirmed'], ['status' => 'in:pending,confirmed,cancelled']);
        $this->assertTrue($v->passes());
    }

    public function test_in_fails_for_disallowed_value(): void
    {
        $v = Validator::make(['status' => 'sneaky'], ['status' => 'in:pending,confirmed,cancelled']);
        $this->assertFalse($v->passes());
    }

    /* ---- date ---- */

    public function test_date_passes_for_valid_iso_date(): void
    {
        $v = Validator::make(['d' => '2026-05-15'], ['d' => 'date']);
        $this->assertTrue($v->passes());
    }

    public function test_date_fails_for_impossible_date(): void
    {
        $v = Validator::make(['d' => '2026-02-30'], ['d' => 'date']);
        $this->assertFalse($v->passes());
    }

    public function test_date_fails_for_wrong_format(): void
    {
        $v = Validator::make(['d' => '15/05/2026'], ['d' => 'date']);
        $this->assertFalse($v->passes());
    }

    /* ---- time ---- */

    public function test_time_passes_for_valid_hhmm(): void
    {
        $v = Validator::make(['t' => '14:30'], ['t' => 'time']);
        $this->assertTrue($v->passes());
    }

    public function test_time_passes_for_valid_hhmmss(): void
    {
        $v = Validator::make(['t' => '14:30:00'], ['t' => 'time']);
        $this->assertTrue($v->passes());
    }

    public function test_time_fails_for_out_of_range_hour(): void
    {
        $v = Validator::make(['t' => '25:00'], ['t' => 'time']);
        $this->assertFalse($v->passes());
    }

    /* ---- phone ---- */

    public function test_phone_passes_for_international_format(): void
    {
        $v = Validator::make(['p' => '+1 (555) 123-4567'], ['p' => 'phone']);
        $this->assertTrue($v->passes());
    }

    public function test_phone_passes_for_plain_digits(): void
    {
        $v = Validator::make(['p' => '5551234567'], ['p' => 'phone']);
        $this->assertTrue($v->passes());
    }

    public function test_phone_fails_for_too_few_digits(): void
    {
        $v = Validator::make(['p' => '+1 234'], ['p' => 'phone']);
        $this->assertFalse($v->passes());
    }

    public function test_phone_fails_for_letters(): void
    {
        $v = Validator::make(['p' => 'call-me-maybe'], ['p' => 'phone']);
        $this->assertFalse($v->passes());
    }

    /* ---- optional fields are skipped when empty ---- */

    public function test_optional_field_is_skipped_when_empty(): void
    {
        $v = Validator::make(['notes' => ''], ['notes' => 'max:500']);
        $this->assertTrue($v->passes());
    }

    /* ---- multi-rule pipelines short-circuit on first failure ---- */

    public function test_first_failure_wins_on_multi_rule(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'required|email']);
        $this->assertFalse($v->passes());
        // Only one error, and it should be the 'required' one (runs first).
        $this->assertCount(1, $v->errors());
    }

    public function test_validated_returns_trimmed_values(): void
    {
        $v = Validator::make(['name' => '  Alice  '], ['name' => 'required|min:2']);
        $this->assertTrue($v->passes());
        $this->assertSame('Alice', $v->validated()['name']);
    }
}
