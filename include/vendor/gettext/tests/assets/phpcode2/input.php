<div>
    <p><?php __ ( 'plain' ); ?></p>
    <p><?php gettext('DATE \a\t TIME'); ?></p>
    <p><?php __("DATE \a\\t TIME"); ?></p>
    <p><?php __("DATE \\a\\t TIME"); ?></p>
    <p><?php __("FIELD\tFIELD"); ?></p>
    <p><?php __(
        "text "
        // test
        .'concatenated'.
        /* test*/ " with 'comments'"
    ); ?></p>
    <p><?php __($avoid['me']); ?>
    <p><?php __('Stop at the variable'.$var.'!'); ?>
</div>

<?php

__('No comments');

/* All comments */
p__(CONTEXT, 'All comments');

/* Invalid i18n Tagged comment */

__('i18n Tagged comment');

gettext(
    /* i18n Tagged comment inside */
    'i18n Tagged comment inside'
);

dn__(null, 'One comment', 'Many comments', 2);

/* i18n Tagged comment on the line before */
sprintf( __('i18n tagged %s'), '$var');

/*
 * Translators: This is a
 * multi-line comment.
 */
__( 'foo' );

/* translators: this should get extracted. */ $foo = __( 'bar' );

function foo() {
	/*
	 * translators: this comment is
	 * indented with a tab.
	 */
	__( 'foo bar' );
}
