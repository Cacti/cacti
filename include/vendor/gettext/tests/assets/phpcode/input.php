<header>
    <h1><?php __('text 1'); ?></h1>
</header>

<div>
    <p><?php __($var); ?></p>
    <p><?php p__('context', 'text 1 with context'); ?></p>
    <p><?php noop__('text 2'); ?></p>
    <p><?php __('text 3 (with parenthesis)'); ?></p>
    <p><?php __('text 4 "with double quotes"'); ?></p>
    <p><?php __('text 5 \'with escaped single quotes\''); ?></p>
</div>

<div>
    <p><?php __('text 6'); ?></p>
    <p><?php __('text 7 (with parenthesis)'); ?></p>
    <p><?php __('text 8 "with escaped double quotes"'); ?></p>
    <p><?php __("text 9 'with single quotes'"); ?></p>
    <p><?php echo n__('text 10 with plural', 'The plural form', 5); ?></p>
</div>

<?php __("<div id=\"blog\" class=\"container\">
    <div class=\"row\">
        <div class=\"col-md-12\">
            <div id=\"content\">
                <div class=\"page_post\">
                    <div class=\"container\">
                        <div class=\"margin-top-40\"></div>
                        <div class=\"col-sm-3 col-md-2 centered-xs an-number\">4</div>
                    </div>
                </div>
                <div class=\"container\">
                    <h1 class=\"text-center margin-top-10\">Sorry, but we couldn't find this page</h1>
                    <div id=\"body-div\">
                        <div id=\"main-div\">
                            <div class=\"text-404\">
                                <div>
                                    <p>Maybe you have entered an incorrect URL of the page or page moved to another section or just page is temporarily unavailable.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>");
?>
<p><?php __(''); ?></p>
