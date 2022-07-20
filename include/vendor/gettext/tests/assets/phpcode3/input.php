<?php

__(
/*allowed1 Comment 1 */
/*allowed2 Comment 2 */
/* Comment 4 */
/*not-allowed Comment 3 */
	'Translation with comments'
);

/* allowed1: boo */ /* allowed2: only this should get extracted. */ /* some other comment */ $bar = strtolower( __( 'Foo' ) );