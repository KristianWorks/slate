<?php
/**
 * app/core/Validator.php
 *
 * Tiny rule-based validator. Built so new controllers can drop input
 * validation in one expression instead of a sprawl of if/else.
 *
 * Usage:
 *
 *   $v = Validator::make($_POST, [
 *       'name'  => 'required|min:2|max:150',
 *       'email' => 'required|email',
 *       'phone' => 'required|phone',
 *       'date'  => 'required|date',
 *       'time'  => 'required|time',
 *       'notes' => 'max:500',
 *       'status'=> 'required|in:pending,confirmed,cancelled',
 *       'count' => 'required|integer|min:1|max:99',
 *   ]);
 *
 *   if ($v->fails()) {
 *       errors_set($v->errors());
 *       old_set($_POST);
 *       redirect('/somewhere');
 *   }
 *   $clean = $v->validated();   // trimmed + type-coerced values
 *
 * Rules:
 *   required           value must be non-empty after trim
 *   email              filter_var FILTER_VALIDATE_EMAIL
 *   min:N              N chars for strings, N value for numerics
 *   max:N              N chars for strings, N value for numerics
 *   in:a,b,c           value must be one of the listed strings
 *   date               'YYYY-MM-DD' and checkdate() passes
 *   time               'HH:MM' or 'HH:MM:SS', 24-hour
 *   phone              7–15 digits, allows + ( ) - . and spaces
 *   regex:/pattern/    user-supplied pattern
 *   integer            filter_var FILTER_VALIDATE_INT
 *   numeric            is_numeric
 *
 * Sanitization (applied unconditionally before rules run):
 *   - trim() on every string value
 *   - For specifically-named "untrusted prose" fields, callers can apply
 *     Validator::stripTagsFrom($data, ['notes', 'description', ...])
 *     before passing data in.
 */

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /**
     * @param array<string,mixed>         $data
     * @param array<string,string|list<string>> $rules
     */
    public function __construct(
        public readonly array $data,
        public readonly array $rules,
    ) {
        $this->run();
    }

    /** Build + run in one call. */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool { return $this->errors === []; }
    public function fails():  bool { return $this->errors !== []; }

    /** @return array<string,string> field => message (first failure per field) */
    public function errors(): array { return $this->errors; }

    /** @return array<string,mixed> sanitized values for fields that passed */
    public function validated(): array { return $this->validated; }

    /**
     * Strip HTML tags from named fields BEFORE validation.
     * Returns a new array — does not mutate the caller's.
     *
     * @param  array<string,mixed> $data
     * @param  list<string> $fields
     * @return array<string,mixed>
     */
    public static function stripTagsFrom(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = strip_tags($data[$field]);
            }
        }
        return $data;
    }

    /* ============================================================
     * Internals
     * ============================================================ */

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleStr) {
            $raw   = $this->data[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;

            $rules      = is_array($ruleStr) ? $ruleStr : explode('|', (string) $ruleStr);
            $isRequired = in_array('required', $rules, true);
            $isEmpty    = $value === null || $value === '' || $value === [];

            // Optional + empty → skip rule checks, carry forward as null.
            if (!$isRequired && $isEmpty) {
                $this->validated[$field] = null;
                continue;
            }

            foreach ($rules as $rule) {
                $error = $this->apply((string) $rule, $field, $value);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function apply(string $rule, string $field, mixed $value): ?string
    {
        $param = null;
        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule, 2);
        }
        $label = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {
            'required' => ($value === null || $value === '')
                ? "{$label} is required."
                : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : "{$label} must be a valid email.",
            'min'     => $this->minMaxRule($value, (int) $param, $label, false),
            'max'     => $this->minMaxRule($value, (int) $param, $label, true),
            'in'      => in_array((string) $value, explode(',', (string) $param), true)
                ? null
                : "{$label} is not a permitted value.",
            'date'    => $this->dateRule((string) $value, $label),
            'time'    => preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)
                ? null
                : "{$label} must be a valid time.",
            'phone'   => $this->phoneRule((string) $value, $label),
            'regex'   => @preg_match((string) $param, (string) $value) === 1
                ? null
                : "{$label} format is invalid.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? null
                : "{$label} must be a whole number.",
            'numeric' => is_numeric($value)
                ? null
                : "{$label} must be a number.",
            default   => null, // unknown rule is a no-op
        };
    }

    private function minMaxRule(mixed $value, int $bound, string $label, bool $isMax): ?string
    {
        $unit = is_numeric($value) ? '' : ' characters';
        $cmp  = is_numeric($value) ? (float) $value : mb_strlen((string) $value);
        if ($isMax && $cmp > $bound) return "{$label} must be at most {$bound}{$unit}.";
        if (!$isMax && $cmp < $bound) return "{$label} must be at least {$bound}{$unit}.";
        return null;
    }

    private function dateRule(string $value, string $label): ?string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return "{$label} must be a valid date (YYYY-MM-DD).";
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? null
            : "{$label} must be a valid date.";
    }

    private function phoneRule(string $value, string $label): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (!preg_match('/^[\d\s+().\-]{7,40}$/', $value)) {
            return "{$label} must be a valid phone number.";
        }
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return "{$label} must be a valid phone number.";
        }
        return null;
    }
}
