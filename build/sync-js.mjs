// Copies the npm-managed JavaScript libraries into include/js/ so the tree
// ships with prebuilt assets. Run `npm ci && npm run build:js` after any change
// to the pinned versions in package.json. pace-js and tablesorter each carry a
// local fix applied via patch-package (patches/pace-js+1.2.4.patch,
// patches/tablesorter+2.32.0.patch) before this runs. On the 1.2.x branch
// screenfull is pinned to the last non-ESM release (5.2.0), whose UMD build
// (node_modules/screenfull/dist/screenfull.js) loads as a classic <script>
// without any rewrite, so no post-copy transform is required.
//
// jquery and jquery-ui are deliberately NOT synced here: include/js/jquery.js
// is left on the committed 3.7.1 build, and include/js/jquery-ui.js keeps its
// `$.uiBackCompat = true;` build that the upstream dist omits. jquery is still
// pinned in package.json so npm can resolve select2/jstree peer dependencies.
import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

export const assetMap = Object.freeze({
	'node_modules/d3/dist/d3.js':                                     'include/js/d3.js',
	'node_modules/screenfull/dist/screenfull.js':                     'include/js/screenfull.js',
	'node_modules/dompurify/dist/purify.js':                          'include/js/purify.js',
	'node_modules/dompurify/dist/purify.js.map':                      'include/js/purify.js.map',
	'node_modules/jstree/dist/jstree.js':                             'include/js/jstree.js',
	'node_modules/billboard.js/dist/billboard.js':                    'include/js/billboard.js',
	'node_modules/pace-js/pace.js':                                   'include/js/pace.js',
	'node_modules/select2/dist/js/select2.full.js':                   'include/js/select2.js',
	'node_modules/tablesorter/dist/js/jquery.tablesorter.js':         'include/js/jquery.tablesorter.js',
	'node_modules/tablesorter/dist/js/jquery.tablesorter.widgets.js': 'include/js/jquery.tablesorter.widgets.js',
	'node_modules/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js': 'include/js/jquery.tablesorter.pager.js',
});

// No post-copy transforms are needed on 1.2.x; the hook is kept so a future
// pinned version that requires one can be added without reshaping the loop.
const postCopyTransforms = Object.freeze({});

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
