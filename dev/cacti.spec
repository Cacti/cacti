Summary: The complete RRDTool-based graphing solution.
Name: cacti
Version: 0.8.6
Release: 1
License: GPL
Group: Application/System
Source0: cacti-%{version}.tar.gz
Source1: cacti-cactid-%{version}.tar.gz
URL: http://www.raxnet.net/products/cacti/
BuildRoot: %{_tmppath}/%{name}-root
Requires: php, php-mysql, mysql, webserver, rrdtool, net-snmp, php-snmp
BuildRequires: mysql-devel, net-snmp-devel

%description
Cacti is a complete frontend to RRDTool. It stores all of the necessary 
information to create graphs and populate them with data in a MySQL database. 
The frontend is completely PHP driven. Along with being able to maintain graphs, 
data sources, and round robin archives in a database, Cacti also handles the data 
gathering. There is SNMP support for those used to creating traffic graphs with 
MRTG.

%package cactid
Summary: Fast c-based poller for package %{name}
Group: Application/System
Requires: cacti = %{version}

%description cactid
Cactid is a supplemental poller for Cacti that makes use of pthreads to achieve
excellent performance.

%prep
%setup
echo -e "*/5 * * * *\tcacti\tphp %{_localstatedir}/www/cacti/poller.php &>/dev/null" >cacti.crontab

%build
tar xzf %{SOURCE1}
cd cacti-cactid-%{version}
./configure
%{__make} %{?_smp_mflags}

%install
rm -rf %{buildroot}
%{__install} -d -m0755 %{buildroot}%{_localstatedir}/www/cacti/
%{__install} -m0644 *.php cacti.sql %{buildroot}%{_localstatedir}/www/cacti/
%{__cp} -avx docs/ images/ include/ install/ lib/ log/ resource/ rra/ scripts/ %{buildroot}%{_localstatedir}/www/cacti/

%{__install} -D -m0755 cacti-cactid-%{version}/cactid %{buildroot}%{_bindir}/cactid
%{__install} -D -m0644 cacti-cactid-%{version}/cactid.conf %{buildroot}%{_sysconfdir}/cactid.conf
%{__install} -D -m0644 cacti.crontab %{buildroot}%{_sysconfdir}/cron.d/cacti

%clean
rm -rf %{buildroot}

%pre
useradd -d %{_localstatedir}/www/cacti cacti > /dev/null 2>&1 || true

%post
echo "Make sure to import the default database at /var/www/html/cacti/cacti.sql and edit"
echo "the database configuration in /var/www/html/cacti/include/config.php."

%postun
userdel cacti > /dev/null 2>&1 || true

%files
%defattr(-, root, root, 0755)
%doc LICENSE README
%config %{_localstatedir}/www/cacti/include/config.php
%config %{_sysconfdir}/cron.d/*
%dir %{_localstatedir}/www/cacti/
%{_localstatedir}/www/cacti/*.php
%{_localstatedir}/www/cacti/cacti.sql
%{_localstatedir}/www/cacti/docs/
%{_localstatedir}/www/cacti/images/
%{_localstatedir}/www/cacti/include/
%{_localstatedir}/www/cacti/install/
%{_localstatedir}/www/cacti/lib/
%{_localstatedir}/www/cacti/resource/
%{_localstatedir}/www/cacti/scripts/
%defattr(-, cacti, cacti, 0755 )
%{_localstatedir}/www/cacti/log/
%{_localstatedir}/www/cacti/rra/

%files cactid
%defattr(-, root, root, 0755)
%doc cacti-cactid-%{version}/AUTHORS cacti-cactid-%{version}/ChangeLog cacti-cactid-%{version}/COPYING cacti-cactid-%{version}/INSTALL cacti-cactid-%{version}/NEWS
%config %{_sysconfdir}/cactid.conf
%{_bindir}/*

%changelog
* Sat Sep 11 2004 Ian Berry <iberry@raxnet.net> - 0.8.6-1
- Updated to release 0.8.6.
- Broke cactid into its own package.

* Thu Apr 4 2004 Ian Berry <iberry@raxnet.net> - 0.8.5a-1
- Initial package.
