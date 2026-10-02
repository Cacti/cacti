// A production Cacti checkout should not ship a node_modules folder, but CI
// jobs run tests against it after `npm ci` triggers this script via
// postinstall, so removal is skipped whenever the standard CI=true env var
// (set by GitHub Actions and effectively every other CI provider) is present.
import { rmSync } from 'node:fs';

if (!process.env.CI) {
	rmSync('node_modules', { recursive: true, force: true });
}
