Summary: The complete RRDTool-based graphing solution.
Name: cacti
Version: 0.8.6e
Release: 1
License: GPL
Group: Application/System
Source0: cacti-%{version}.tar.gz
URL: http://www.cacti.net/
BuildRoot: %{_tmppath}/%{name}-root
Requires: php, php-mysql, mysql, webserver, rrdtool, net-snmp, php-snmp

%description
Cacti is a complete frontend to RRDTool. It stores all of the necessary
information to create graphs and populate them with data in a MySQL database.
The frontend is completely PHP driven. Along with being able to maintain graphs,
data sources, and round robin archives in a database, Cacti also handles the data
gathering. There is SNMP support for those used to creating traffic graphs with
MRTG.

%prep
%setup
echo -e "*/5 * * * *\tcactiuser\tphp %{_localstatedir}/www/html/cacti/poller.php > /dev/null 2>&1" >cacti.crontab

%build

%install
rm -rf %{buildroot}
%{__install} -d -m0755 %{buildroot}%{_localstatedir}/www/html/cacti/
%{__install} -m0644 *.php cacti.sql %{buildroot}%{_localstatedir}/www/html/cacti/
%{__cp} -avx docs/ images/ include/ install/ lib/ log/ resource/ rra/ scripts/ %{buildroot}%{_localstatedir}/www/html/cacti/
%{__install} -D -m0644 cacti.crontab %{buildroot}%{_sysconfdir}/cron.d/cacti

%clean
rm -rf %{buildroot}

%pre
useradd -d %{_localstatedir}/www/html/cacti cactiuser > /dev/null 2>&1 || true

%post
echo "Be sure to follow steps 2 through 5 in the install guide for new Cacti installations."

%postun
if [ $1 = 0 ]; then
	userdel cactiuser > /dev/null 2>&1 || true
fi

%files
%defattr(-, root, root, 0755)
%doc LICENSE README
%config %{_localstatedir}/www/html/cacti/include/config.php
%config %{_sysconfdir}/cron.d/*
%dir %{_localstatedir}/www/html/cacti/
%{_localstatedir}/www/html/cacti/*.php
%{_localstatedir}/www/html/cacti/cacti.sql
%{_localstatedir}/www/html/cacti/docs/
%{_localstatedir}/www/html/cacti/images/
%{_localstatedir}/www/html/cacti/include/
%{_localstatedir}/www/html/cacti/install/
%{_localstatedir}/www/html/cacti/lib/
%{_localstatedir}/www/html/cacti/resource/
%{_localstatedir}/www/html/cacti/scripts/
%defattr(-, cactiuser, cactiuser, 0755 )
%{_localstatedir}/www/html/cacti/log/
%{_localstatedir}/www/html/cacti/rra/

%changelog
* Mon Jun 20 2005 Ian Berry <iberry@raxnet.net> - 0.8.6e-1
- Updated to release 0.8.6e.

* Wed Apr 26 2005 Ian Berry <iberry@raxnet.net> - 0.8.6d-1
- Updated to release 0.8.6d.

* Wed Dec 12 2004 Ian Berry <iberry@raxnet.net> - 0.8.6c-1
- Updated to release 0.8.6c.

* Wed Oct 5 2004 Ian Berry <iberry@raxnet.net> - 0.8.6b-1
- Updated to release 0.8.6b.

* Sun Oct 3 2004 Ian Berry <iberry@raxnet.net> - 0.8.6a-1
- Updated to release 0.8.6a.

* Sat Sep 11 2004 Ian Berry <iberry@raxnet.net> - 0.8.6-1
- Updated to release 0.8.6.
- Broke cactid into its own package.

* Thu Apr 4 2004 Ian Berry <iberry@raxnet.net> - 0.8.5a-1
- Initial package.
