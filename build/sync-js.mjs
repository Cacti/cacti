// Copies the npm-managed JavaScript libraries into include/js/ so the tree
// ships with prebuilt assets. Run `npm ci && npm run build:js` after any change
// to the pinned versions in package.json. pace-js carries a one-line local fix
// applied via patch-package (patches/pace-js+1.2.4.patch) before this runs.
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const map = {
	'node_modules/jquery/dist/jquery.js':                             'include/js/jquery.js',
	'node_modules/jquery-ui/dist/jquery-ui.js':                       'include/js/jquery-ui.js',
	'node_modules/jstree/dist/jstree.js':                             'include/js/jstree.js',
	'node_modules/billboard.js/dist/billboard.js':                    'include/js/billboard.js',
	'node_modules/pace-js/pace.js':                                   'include/js/pace.js',
	'node_modules/tablesorter/dist/js/jquery.tablesorter.js':         'include/js/jquery.tablesorter.js',
	'node_modules/tablesorter/dist/js/jquery.tablesorter.widgets.js': 'include/js/jquery.tablesorter.widgets.js',
	'node_modules/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js': 'include/js/jquery.tablesorter.pager.js',
	'node_modules/jquery-validation/dist/jquery.validate.js':         'include/js/jquery.validate/jquery.validate.js',
	'node_modules/jquery-validation/dist/jquery.validate.min.js':     'include/js/jquery.validate/jquery.validate.min.js',
	'node_modules/jquery-validation/dist/additional-methods.js':      'include/js/jquery.validate/additional-methods.js',
	'node_modules/jquery-validation/dist/additional-methods.min.js':  'include/js/jquery.validate/additional-methods.min.js',
};

for (const [src, dest] of Object.entries(map)) {
	mkdirSync(dirname(dest), { recursive: true });
	copyFileSync(src, dest);
	console.log(`synced ${dest}`);
}
