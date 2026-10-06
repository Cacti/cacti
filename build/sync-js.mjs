// Copies the npm-managed JavaScript libraries into include/js/ so the tree
// ships with prebuilt assets. Run `npm ci && npm run build:js` after any change
// to the pinned versions in package.json. pace-js and tablesorter each carry a
// local fix applied via patch-package (patches/pace-js+1.2.4.patch,
// patches/tablesorter+2.32.0.patch) before this runs. screenfull ships ESM-only
// since v6, so its copy is rewritten below (see postCopyTransforms) instead of
// via patch-package, since node_modules/screenfull/index.js is itself valid.
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
	.replace(/\$\.trim\(((?:[^()]|\([^()]*\))*)\)/g, '($1).trim()')
	.replace(/\$\.type\(\s*([^()]+?)\s*\)\s*===\s*'string'/g, "typeof $1 === 'string'")
	.replace(/\$\.type\(\s*([^()]+?)\s*\)\s*===\s*'object'/g, '$$.isPlainObject($1)')
	.replace(/\$\.isWindow\(\s*([^()]+?)\s*\)/g, '($1 != null && $1 === $1.window)')
	.replace(/\$\.isArray\(/g, 'Array.isArray(')
	.replace(/\$\.parseJSON\(/g, 'JSON.parse(');

const postCopyTransforms = Object.freeze({
	'include/js/screenfull.js': content => {
		const transformed = content.replace(/\nexport default screenfull;\s*$/, '\nwindow.screenfull = screenfull;\n');

		if (transformed === content) {
			throw new Error("sync-js: expected 'export default screenfull;' in node_modules/screenfull/index.js; upstream may have changed format");
		}

		return transformed;
	},
	'include/js/jquery.tablesorter.js': tablesorterJquery4,
	'include/js/jquery.tablesorter.widgets.js': tablesorterJquery4,
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
