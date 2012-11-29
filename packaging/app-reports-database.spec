
Name: app-reports-database
Epoch: 1
Version: 1.4.5
Release: 1%{dist}
Summary: Reports Database - Core
License: LGPLv3
Group: ClearOS/Libraries
Source: app-reports-database-%{version}.tar.gz
Buildarch: noarch

%description
The Reports Database provides a common set of tools for managing database-driven reports.

%package core
Summary: Reports Database - Core
Requires: app-base-core
Requires: app-reports
Requires: app-system-database-core >= 1:1.2.4
Requires: webconfig-php-mysql

%description core
The Reports Database provides a common set of tools for managing database-driven reports.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/reports_database
cp -r * %{buildroot}/usr/clearos/apps/reports_database/

install -d -m 0755 %{buildroot}/var/clearos/reports_database
install -d -m 0755 %{buildroot}/var/clearos/reports_database/cache
install -D -m 0755 packaging/initialize-report-tables %{buildroot}/usr/sbin/initialize-report-tables

%post core
logger -p local6.notice -t installer 'app-reports-database-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/reports_database/deploy/install ] && /usr/clearos/apps/reports_database/deploy/install
fi

[ -x /usr/clearos/apps/reports_database/deploy/upgrade ] && /usr/clearos/apps/reports_database/deploy/upgrade

exit 0

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-reports-database-core - uninstalling'
    [ -x /usr/clearos/apps/reports_database/deploy/uninstall ] && /usr/clearos/apps/reports_database/deploy/uninstall
fi

exit 0

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/reports_database/packaging
%exclude /usr/clearos/apps/reports_database/tests
%dir /usr/clearos/apps/reports_database
%dir /var/clearos/reports_database
%dir /var/clearos/reports_database/cache
/usr/clearos/apps/reports_database/deploy
/usr/clearos/apps/reports_database/language
/usr/clearos/apps/reports_database/libraries
/usr/sbin/initialize-report-tables
