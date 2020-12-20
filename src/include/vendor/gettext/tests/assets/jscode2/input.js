gettext("some message");
pgettext("some context", "some \"message\" in \na context");
ngettext("%s message", "%s messages", 2);

(function(){
    var foo = ["string1", "string2", "string3"];

    return __('my translate 3');
})();