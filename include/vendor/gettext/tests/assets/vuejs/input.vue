<template>
    <div>
        {{gettext('t-text')}}
        <div :title="condition ? gettext('t-cond-attribute') : 'lol'">
            <input :placeholder="gettext('t-attribute')" type="text"><span v-bind:title=" n__('t-same-line-s','t-same-line-p', 2) "></span>
        </div>
    </div>

    <span :title="__('multi-occurrence')">
        {{__('multi-occurrence')}}
        {{gettext('multi-occurrence')}}
    </span>

    {{ngettext("t-singular", "t-plural", someProperty)}}

    <div :title='ngettext("t-singular2", "t-plural2", someProperty)'></div>

    <span>{{gettext('t-tag')}}</span>{{gettext('t-tag-sibling')}}

    {{true || false ? gettext('t-expr1') : gettext('t-expr2')}}

    {{gettext('t-"quotes"')}} {{gettext("t-\"quotes\"-2")}}
    {{gettext("t-'quotes'")}} {{gettext('t-\'quotes\'-2')}}

    {{ gettext("t-spaces-expr") || e }} {{ gettext("t-spaces-expr2") || expression }}

    regular text
    {{ngettext('t-p1(parentheses)', 't-p2(parentheses)', (true || false ? 1 : (1+1)))}}
    regular text { test {something} test }

    <a v-on:click='alert(gettext("t-v-bind"))'></a>

    <a v-bind:title='pgettext("context", "t-action")'></a>

    <div>{{pgettext('context2', 't-action2')}}</div>

    <span :title="__(`back-tick-in-tag`)">
        {{__(`back-tick-in-mustache`)}}
    </span>

    <translate>t-tag-2</translate>

    <span v-translate>v-translate-attribute</span>

    <span v-translate translate-plural="v-translate-attribute-plural">v-translate-attribute-single</span>

    <label :v-text="__('t-v-text')"></label>

</template>

<script>
    export default {
        computed: {
            buttonText() {
                true || false ? this.gettext('js-expr1') : this.gettext('js-expr2') + 'random';

                alert(this.noop('js-alert'));

                if (this.delay > 0) {
                    return this.ngettext('js-single', 'js-plural', this.delay + 10);
                }

                var test = {
                    key: this.n__('js-obj-single', 'js-obj-plural', this.randomMethod())
                };

                var string = 'something' + this.p__('some-context', 'js-action');

                return this.gettext('<span>js-return</span><br>');
            }
        },
    }
</script>
