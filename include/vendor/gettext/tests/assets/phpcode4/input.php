<?php

dgettext('domain1', 'matching 1');
dngettext('domain1', 'matching 2 singular', 'matching 2 plural', 1);
dnp__('domain1', 'context', 'matching 3 context singular', 'matching 3 context plural', 123);
d__('domain1', 'matching 4');

dngettext('domain2', 'skip singular', 'skip plural', 2);
dgettext('domain2', 'skip');

__('skip global 1');
gettext('skip global 2');