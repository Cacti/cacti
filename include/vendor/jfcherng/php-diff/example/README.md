# Demo

Note that

- All `composer`-related commands are run in this project's root directory.
  That is php-diff directory rather than this `example` directory.
- You can change differ/renderer options in `demo_base.php`.
- Change contents of `old_file.txt` and `new_file.txt` to test different text.

To run demo, you have to first install dependencies via `composer install`.

## Web Environment

To run `demo_web.php` on your local machine, you can follow steps below.

1. Start PHP development server via `composer run-script server`.
1. Visit `http://localhost:12388/demo_web.php` with a web browser.

## Cli Environment

Just run `php demo_cli.php`.
