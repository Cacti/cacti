%define _localdatadir %{_var}/www/html/cacti

Summary: The complete RRDTool-based graphing solution.
Name: cacti
Version: 0.8.5a
Release: 1
License: GPL
Group: Application/System
Source0: cacti-%{version}.tar.gz
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

%prep
%setup

%build
cd cactid
./configure
make

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}/var/www/html/cacti
mkdir -p %{buildroot}/usr/bin
mkdir -p %{buildroot}/etc

cp -f cactid/cactid %{buildroot}/usr/bin/cactid
cp -f cactid/cactid.conf %{buildroot}/etc/cactid.conf
cp -f *.php README LICENSE cacti.sql %{buildroot}%{_localdatadir}
cp -fR docs/ %{buildroot}%{_localdatadir}
cp -fR images/ %{buildroot}%{_localdatadir}
cp -fR include/ %{buildroot}%{_localdatadir}
cp -fR install/ %{buildroot}%{_localdatadir}
cp -fR lib/ %{buildroot}%{_localdatadir}
cp -fR log/ %{buildroot}%{_localdatadir}
cp -fR resource/ %{buildroot}%{_localdatadir}
cp -fR rra/ %{buildroot}%{_localdatadir}
cp -fR scripts/ %{buildroot}%{_localdatadir}

%clean
rm -rf %{buildroot}

%pre
useradd -d %{_localdatadir} cacti > /dev/null 2>&1 || true

%post
echo "Cacti installation complete. Please add the following line to your /etc/crontab file:"
echo
echo "*/5 * * * * cacti php /var/www/html/cacti/cmd.php > /dev/null 2>&1"
echo
echo "Make sure to import the default database at /var/www/html/cacti/cacti.sql and edit"
echo "the database configuration in /var/www/html/cacti/include/config.php."

%postun
userdel cacti > /dev/null 2>&1 || true

%files
%defattr(-, root, root)
%config /etc/cactid.conf
%config %{_localdatadir}/include/config.php
%{_localdatadir}/*.php
%{_localdatadir}/cacti.sql
%{_localdatadir}/README
%{_localdatadir}/LICENSE
%{_localdatadir}/rra/.placeholder
%{_localdatadir}/docs/*
%{_localdatadir}/images/*
%{_localdatadir}/include/*
%{_localdatadir}/install/*
%{_localdatadir}/lib/*
%attr(-, cacti, cacti) %{_localdatadir}/log/*
%{_localdatadir}/resource/*
%attr(-, cacti, cacti) %dir %{_localdatadir}/rra/
%{_localdatadir}/scripts/*
/usr/bin/cactid

%changelog
