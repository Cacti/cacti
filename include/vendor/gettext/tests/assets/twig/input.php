<header>
    <h1>{% trans 'text 1' %}</h1>
</header>

<div>
    <p>{% trans 'text 2' %}</p>
    <p>{% trans 'text 3 (with parenthesis)' %}</p>
    <p>{% trans 'text 4 "with double quotes"' %}</p>
    <p>{% trans 'text 5 \'with escaped single quotes\'' %}</p>
</div>

<div>
    <p>{% trans "text 6" %}</p>
    <p>{% trans "text 7 (with parenthesis)" %}</p>
    <p>{% trans "text 8 \"with escaped double quotes\"" %}</p>
    <p>{% trans "text 9 'with single quotes'" %}</p>
    <p>
    {% trans %}
        text 10 with plural
    {% plural 5 %}
        The plural form
    {% notes %}
        This is an actual note for translators.
    {% endtrans %}
    </p>
</div>
