// Copies the npm-managed JavaScript libraries into include/js/ so the tree
// ships with prebuilt assets. Run `npm ci && npm run build:js` after any change
// to the pinned versions in package.json. Local compatibility and security
// fixes are applied to generated assets below, leaving npm sources unchanged.
// screenfull ships ESM-only since v6, so its copy is rewritten below.
// tablesorter is also rewritten below to drop jQuery-4-incompatible utility
// calls ($.isFunction, $.trim, $.isArray, ...) that its latest release still ships.
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

export const assetMap = Object.freeze({
	'node_modules/jquery/dist/jquery.js':                             'include/js/jquery.js',
	'node_modules/htmx.org/dist/htmx.js':                             'include/js/htmx.js',
	'node_modules/d3/dist/d3.js':                                     'include/js/d3.js',
	'node_modules/big.js/big.js':                                     'include/js/big.js',
	'node_modules/lzjs/lzjs.js':                                      'include/js/lzjs.js',
	'node_modules/screenfull/index.js':                               'include/js/screenfull.js',
	'node_modules/dompurify/dist/purify.js':                          'include/js/purify.js',
	'node_modules/dompurify/dist/purify.js.map':                      'include/js/purify.js.map',
	'node_modules/jquery-ui/dist/jquery-ui.js':                       'include/js/jquery-ui.js',
	'node_modules/jquery-ui-touch-punch/jquery.ui.touch-punch.js':     'include/js/jquery.ui.touch.punch.js',
	'node_modules/chart.js/dist/Chart.js':                            'include/js/Chart.js',
	'node_modules/moment/min/moment.min.js':                          'include/js/vendor/moment/moment.min.js',
	'node_modules/moment/LICENSE':                                   'include/js/vendor/moment/LICENSE',
	'node_modules/daterangepicker/daterangepicker.js':               'include/js/vendor/daterangepicker/daterangepicker.js',
	'node_modules/hotkeys-js/dist/hotkeys-js.umd.cjs':                 'include/js/vendor/hotkeys/hotkeys.js',
	'node_modules/hotkeys-js/dist/hotkeys-js.min.js':                  'include/js/vendor/hotkeys/hotkeys.min.js',
	'node_modules/hotkeys-js/LICENSE':                               'include/js/vendor/hotkeys/LICENSE',
	'node_modules/mark.js/dist/jquery.mark.js':                       'include/js/vendor/mark/jquery.mark.js',
	'node_modules/jstree/dist/jstree.js':                             'include/js/jstree.js',
	'node_modules/billboard.js/dist/billboard.js':                    'include/js/billboard.js',
	'node_modules/pace-js/pace.js':                                   'include/js/pace.js',
	'node_modules/select2/dist/js/select2.full.js':                   'include/js/select2.js',
	'node_modules/tablesorter/dist/js/jquery.tablesorter.js':         'include/js/jquery.tablesorter.js',
	'node_modules/tablesorter/dist/js/jquery.tablesorter.widgets.js': 'include/js/jquery.tablesorter.widgets.js',
	'node_modules/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js': 'include/js/jquery.tablesorter.pager.js',
	'node_modules/jquery-validation/dist/jquery.validate.js':         'include/js/jquery.validate/jquery.validate.js',
	'node_modules/jquery-validation/dist/jquery.validate.min.js':     'include/js/jquery.validate/jquery.validate.min.js',
	'node_modules/jquery-validation/dist/additional-methods.js':      'include/js/jquery.validate/additional-methods.js',
	'node_modules/jquery-validation/dist/additional-methods.min.js':  'include/js/jquery.validate/additional-methods.min.js',
});

// screenfull 6+ dropped its UMD build and ships only `export default screenfull;`,
// which throws "export declarations may only appear at top level of a module"
// when loaded as a classic <script>. Rewrite that line to a global assignment.
// Throws if the export line isn't found, so an upstream format change fails the
// build instead of silently shipping a broken asset.
// tablesorter 2.32.0 (latest release) still calls several jQuery utilities that
// jQuery 4 removed ($.isFunction, $.trim, $.isArray, $.type, $.isWindow,
// $.parseJSON); rewrite them natively at sync time for both the core and widgets
// files. No-ops once the upstream fixes are released and the pin is bumped.
const tablesorterJquery4 = content => content
	.replace(/\$\.isFunction\(\s*([^()]+?)\s*\)/g, "typeof $1 === 'function'")
	.replace(/\$\.trim\(((?:[^()]|\([^()]*\))*)\)/g, "String(($1) ?? '').trim()")
	.replace(/\$\.type\(\s*([^()]+?)\s*\)\s*===\s*'string'/g, "typeof $1 === 'string'")
	.replace(/\$\.type\(\s*([^()]+?)\s*\)\s*===\s*'object'/g, '$$.isPlainObject($1)')
	.replace(/\$\.isWindow\(\s*([^()]+?)\s*\)/g, '($1 != null && $1 === $1.window)')
	.replace(/\$\.isArray\(/g, 'Array.isArray(')
	.replace(/\$\.parseJSON\(/g, 'JSON.parse(')
	// jQuery 4 no longer exposes Array.prototype.sort on collections.
	.replace(/\$input\.sort\(/g, 'Array.prototype.sort.call($input, ')
	// the bare `if ($.parseJSON)` feature-check references an API jQuery 4 removed; test native JSON.parse
	.replace(/if \(\$\.parseJSON\)/g, 'if (window.JSON && window.JSON.parse)')
	// drop the redundant `str &&` guard inside the already-truthy `str ?` branch that CodeQL flags
	.replace(/\( str && table\.config\.ignoreCase \?/g, '( table.config.ignoreCase ?');

const postCopyTransforms = Object.freeze({
	'include/js/pace.js': content => {
		const ownProperty = 'if (!__hasProp.call(source, key)) continue;';
		const bodyClass = "replace(/(pace-done )|/, 'pace-running ')";
		if (!content.includes(ownProperty) || !content.includes(bodyClass)) {
			throw new Error('sync-js: pace source changed; review the prototype and body-class fixes');
		}
		return content.replace(ownProperty, ownProperty + "\n\t\t\t\t\tif (key === '__proto__' || key === 'constructor' || key === 'prototype') continue;")
			.replace(bodyClass, "replace(/(pace-done )/, 'pace-running ')");
	},
	'include/js/screenfull.js': content => {
		const transformed = content.replace(/\nexport default screenfull;\s*$/, '\nwindow.screenfull = screenfull;\n');

		if (transformed === content) {
			throw new Error("sync-js: expected 'export default screenfull;' in node_modules/screenfull/index.js; upstream may have changed format");
		}

		return transformed;
	},
	'include/js/jquery.tablesorter.js': tablesorterJquery4,
	'include/js/jquery.tablesorter.widgets.js': tablesorterJquery4,
	'include/js/jquery.tablesorter.pager.js': content => {
		const transformed = content.replace(/\bE\.isArray\(/g, 'Array.isArray(')
			.replace(/\bE\.trim\(([^()]*)\)/g, "String(($1) ?? '').trim()");
		if (/\bE\.(?:isArray|trim|isFunction|type|isWindow|parseJSON)\s*\(/.test(transformed)) {
			throw new Error('sync-js: tablesorter pager uses an unsupported jQuery API');
		}
		return transformed;
	},
});

export function syncAssets(root = process.cwd(), log = console.log) {
	for (const [src, dest] of Object.entries(assetMap)) {
		const source = resolve(root, src);
		const destination = resolve(root, dest);

		mkdirSync(dirname(destination), { recursive: true });
		copyFileSync(source, destination);

		const transform = postCopyTransforms[dest];
		if (transform) {
			writeFileSync(destination, transform(readFileSync(destination, 'utf8')));
		}

		log(`synced ${dest}`);
	}
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
	syncAssets();
}
