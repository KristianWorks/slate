# Internationalization

Slate ships in English. Other languages can be added without touching
application code — every user-facing string lives in this directory.

## Adding a language

1. **Copy `en.php`** to a new file named after the locale code:
   - Spanish → `es.php`
   - French → `fr.php`
   - Brazilian Portuguese → `pt-BR.php`
   - Simplified Chinese → `zh-CN.php`
2. **Translate the *values*.** Leave the keys and `:placeholder` tokens
   exactly as they are.
3. **Set the locale** in `.env`:
   ```dotenv
   APP_LOCALE=es
   ```
4. Reload — every `__('key')` call now returns the translated value.

## Using the helper

In any view:

```php
<?= e(__('auth.login_title')) ?>
```

With placeholders:

```php
<?= e(__('auth.welcome_back_flash', ['name' => $userName])) ?>
```

The helper replaces `:name` (and any other `:foo` tokens) with the values
you pass. Output is **not** HTML-escaped — wrap the call in `e()` at the
template so escaping stays explicit at the boundary.

## Fallback behaviour

| Situation | What happens |
|---|---|
| `APP_LOCALE=xx` but `xx.php` doesn't exist | Falls back to `en.php` silently. |
| Key not found in the loaded file | Returns the key as-is (e.g. `auth.missing`) so the gap is loud during dev. |
| Value isn't a string (e.g. nested array path that resolved to an array) | Returns the key as-is. |

## Conventions for translators

- Keys are **snake_case**.
- Sections are flat top-level keys: `auth`, `booking`, `admin`, `nav`, `home`, `emails`, `errors`, `common`.
- **Strings use sentence case**, except button labels which mirror native UX (Title Case in English, language-natural in others).
- Placeholders use **:name** style, not `{name}` or `%s` — easier for non-developer translators to spot and preserve.
- Punctuation, en-dashes, and curly quotes are part of the value, not the
  English spelling — translators are expected to use locale-natural punctuation.

## What's currently translated

Demo coverage in the views — replacing every hardcoded string is a sweep, not
a one-time job. The high-traffic views below already use `__()`:

- `app/views/auth/login.php`
- `app/views/auth/register.php`
- `app/views/booking/confirmation.php`
- `app/views/booking/cancel_confirm.php`
- `app/views/home.php` (hero + how-it-works section)

Other views still inline English strings; converting them is mechanical:
1. Pull the visible text out into the appropriate section of `en.php`.
2. Replace the inline text with `<?= e(__('section.key')) ?>`.

A future "i18n sweep" PR can finish the job in one pass.

## Why `__()` and not `t()` or `trans()`?

The double-underscore name is borrowed from gettext and is the de-facto
convention in PHP. It's two characters to type, never collides with a
user-defined function, and reads cleanly in templates.
