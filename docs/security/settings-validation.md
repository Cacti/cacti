# Settings Validation

## Why this exists

Cacti settings are written from several places: the web UI (`settings.php`),
CLI utilities under `cli/`, and (planned) JSON-RPC endpoints. Each of those
paths previously carried its own ad-hoc checks for the same setting, and the
checks drifted apart as code was added. The intent of `CactiSettings::validate()`
is to put the constraint definition next to the setting itself so every write
path uses the same rules.

A setting in `include/global_settings.php` declares its constraints alongside
the existing keys (`method`, `default`, `max_length`, ...). The validator runs
the entire posted set in one pass and returns a `{name => message}` map. An
empty map means the input is valid.

### Why constraints are declared as closures

`include/global_settings.php` is loaded from `include/global.php` *before*
`include/vendor/autoload.php`. Eagerly instantiating `new Assert\NotBlank()`
inside the array literal would fatal at file-load time because the Symfony
Validator classes are not yet loadable. Wrapping each constraint in an arrow
function (`fn() => new Assert\NotBlank()`) defers the instantiation until
`CactiSettings::validate()` runs the closures. The `use Symfony\Component\Validator\Constraints as Assert;`
import at the top of the file is a compile-time alias and does not trigger
autoloading.

## How `CactiSettings::validate` is used in `settings.php`

`save_settings()` builds a snapshot of posted values via Cacti's request-var
helpers (`gnrv`) and calls the validator before writing any rows:

```php
require_once(CACTI_PATH_LIBRARY . '/CactiSettings.php');

$snapshot = [];
foreach ($settings[grv('tab')] as $field_name => $field_array) {
    $snapshot[$field_name] = gnrv($field_name);
}

$violations = CactiSettings::validate($snapshot, $settings);

if (cacti_sizeof($violations) > 0) {
    foreach ($violations as $name => $message) {
        $_SESSION['sess_error_fields'][$name] = $name;
        $_SESSION['sess_field_values'][$name] = $snapshot[$name] ?? '';
        raise_message('cacti_settings_' . $name, ..., MESSAGE_LEVEL_ERROR);
    }
    header('Location: settings.php?tab=...');
    exit;
}
```

When violations are present the request is rejected before any `REPLACE INTO settings`
runs. The user is redirected back to the same tab with the error highlighted.

## How to add constraints to a setting

1. Open `include/global_settings.php` and find the setting definition.
2. Add a `'constraints'` key holding an array of closures, each returning a
   Symfony constraint instance.
3. The `use Symfony\Component\Validator\Constraints as Assert;` alias is
   already imported at the top of the file, so constraints read as
   `fn() => new Assert\Range(...)`.
4. Wrap any custom `message:` argument in `__()` so the message participates
   in Cacti i18n.

Example:

```php
'snmp_timeout' => [
    'friendly_name' => __('Timeout'),
    'method'        => 'textbox',
    'default'       => '500',
    'max_length'    => '10',
    'constraints'   => [
        fn() => new Assert\Regex(pattern: '/^\d+$/', message: __('must be a positive integer (milliseconds).')),
        fn() => new Assert\Range(min: 1, max: 600000),
    ],
],
```

When a `Choice` list duplicates a canonical array from `global_arrays.php`,
derive the choices from that array inside the closure rather than restating
the values:

```php
'constraints' => [
    fn() => new Assert\Choice(choices: array_merge(
        array_keys($GLOBALS['poller_intervals']),
        array_map('strval', array_keys($GLOBALS['poller_intervals']))
    )),
],
```

Both string and integer forms of the keys are accepted because `$_POST`
values arrive as strings while the canonical keys are integers.

## Constraint types used in the pilot

See the [Symfony Validator reference](https://symfony.com/doc/6.4/validation.html)
for the full catalog. The pilot uses:

- `Assert\NotBlank` -- value is present and non-empty.
- `Assert\Length` -- string length within `min`/`max`.
- `Assert\Range` -- numeric value within `min`/`max`.
- `Assert\Choice` -- value is one of an enumerated list. Use this for any
  setting that is rendered as a `drop_array`.
- `Assert\Regex` -- value matches a pattern. Useful for "is this an integer
  string" since `$_POST` values arrive as strings.
- `Assert\Positive` -- value is strictly greater than zero.

## Migration template for the next batch

For each setting you want to constrain:

1. Identify the setting's existing implicit rule (e.g. `method` is `drop_array`
   with a fixed key set, or `max_length` is `'255'`).
2. Translate that rule to a constraint object. `drop_array` becomes
   `Assert\Choice` over the array keys. `max_length` becomes `Assert\Length`.
   Numeric textboxes typically need `Assert\Regex` plus `Assert\Range`.
3. Add a unit test in `tests/Unit/Security/InputValidation/CactiSettingsTest.php` that exercises the
   new constraint with a synthetic definition (do not depend on
   `include/global_settings.php` from the test).
4. Run `composer test` and confirm the new case passes.

## Translator wiring (known limitation)

`CactiSettings::validator()` builds the validator with
`Validation::createValidator()` and does not pass a `TranslatorInterface`.
Symfony's default English messages render regardless of the active Cacti
locale. Custom messages declared in `global_settings.php` should be wrapped
in `__()` so the operator-facing text is translatable through Cacti's own
i18n stack. Wiring a `TranslatorInterface` so that the framework's built-in
constraint messages also translate is a planned follow-up.

## Defense in depth

The constraint layer supplements existing checks rather than replacing them.

- The form-render method (`drop_array`, `dirpath`, `filepath`, `textbox_password`)
  still enforces its implicit rules in `save_settings()`. For example,
  `dirpath` continues to verify the directory exists before storing.
- Any post-save handler (cache-clear, poller restart, log rotation) keeps
  whatever validation it already performs.
- The constraint check runs first and short-circuits the write, so downstream
  handlers see only values that already cleared the declared rules.
