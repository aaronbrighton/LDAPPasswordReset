include stdlib

# Variables to be used throughout the manifest, dependent on each environment.
$primaryIp = ''
$ntp = ''
$smtpRelay = ''
$httpProxy = ''
$ldapServerPrimary = ''
$ldapServerSecondary = ''
$sshAllowedGroups = '' # Space seperated list
$dns = ''
$alertsEmail = ''
$alertsFromEmail = ''
$alertsFromEmailDomain = ''
$ldapAdminUser = ''
$ldapAdminPass = ''
$tz = ''
$envFullName = ''
$fqdnHostname = ''

# Before we do apt-get's we'll want to make sure our web proxy is setup.
file { '/etc/apt/apt.conf':
	ensure => file,
	content => epp('/root/automation/files/etc/apt/apt.epp', { 'httpProxy' => $httpProxy }),
	mode => '644',
	owner => 'root',
	group => 'root',
}

# Run apt-get update, if sources file was modified above
exec { 'apt-get update':
        command => '/usr/bin/apt-get update',
	require => File['/etc/apt/apt.conf'],
	subscribe => File['/etc/apt/apt.conf'],
	refreshonly => true,
	notify => Exec['aptitude -y upgrade'],
	timeout => 1200,
}

# Run apt-get upgrade if apt-get update was run above
exec { 'aptitude -y upgrade':
        command => '/usr/bin/aptitude -y upgrade',
	require => Exec['apt-get update'],
	before => File['/etc/ldap.conf'],
	refreshonly => true,
	timeout => 1200,
}

package { 'libpam-ldap':
	ensure => present,
	require => Exec['aptitude -y upgrade'],
	before => Package['libnss-ldap'],
}

package { 'libnss-ldap':
	ensure => present,
	require => Package['libpam-ldap'],
	before => Package['nscd'],
}

package { 'nscd':
	ensure => present,
	require => Package['libnss-ldap'],
	before => File['/etc/ldap.conf'],
}

# Fix the LDAP configuration, as it's broken post softom-custom in atleast staging.
file { '/etc/ldap.conf':
        path => '/etc/ldap.conf',
        content => epp('/root/automation/files/etc/ldap.conf.epp', { 'ldapServerPrimary' => $ldapServerPrimary, 'ldapServerSecondary' => $ldapServerSecondary }),
	require => Package['libnss-ldap'],
	before => File_line['/etc/ldap/ldap.conf'],
}

# Fix more LDAP
file_line { '/etc/ldap/ldap.conf':
	path => '/etc/ldap/ldap.conf',
	line => 'TLS_REQCERT	allow',
	require => File['/etc/ldap.conf'],
}

file { '/etc/libnss-ldap.conf':
	ensure => 'link',
	target => './ldap.conf',
	require => File_line['/etc/ldap/ldap.conf'],
}

file { '/etc/pam_ldap.conf':
	ensure => 'link',
	target => './ldap.conf',
	require => File_line['/etc/ldap/ldap.conf'],
}

file { '/etc/nsswitch.conf':
	ensure => file,
	source => '/root/automation/files/etc/nsswitch.conf',
	owner => 'root',
	group => 'root',
	mode => '644',
	require => File_line['/etc/ldap/ldap.conf'],
}

file { '/etc/pam.d/common-account':
	ensure => file,
        source => '/root/automation/files/etc/pam.d/common-account',
        owner => 'root',
        group => 'root',
        mode => '644',
        require => File_line['/etc/ldap/ldap.conf'],
}

file { '/etc/pam.d/common-auth':
	ensure => file,
        source => '/root/automation/files/etc/pam.d/common-auth',
        owner => 'root',
        group => 'root',
        mode => '644',
        require => File_line['/etc/ldap/ldap.conf'],
}

file { '/etc/pam.d/common-password':
        ensure => file,
        source => '/root/automation/files/etc/pam.d/common-password',
        owner => 'root',
        group => 'root',
        mode => '644',
        require => File_line['/etc/ldap/ldap.conf'],
}

file { '/etc/pam.d/common-session':
        ensure => file,
        source => '/root/automation/files/etc/pam.d/common-session',
        owner => 'root',
        group => 'root',
        mode => '644',
        require => File_line['/etc/ldap/ldap.conf'],
}

# Disable IPv6
file { '/etc/sysctl.conf':
        ensure => file,
        source => '/root/automation/files/etc/sysctl.conf',
	mode => '644',
	owner => 'root',
	group => 'root',
}

# Install open-vm-tools
package { 'open-vm-tools':
        ensure => present,
}

# Configure SSH configuration
file { '/etc/ssh/sshd_config':
	ensure => file,
	content => epp('/root/automation/files/etc/ssh/sshd_config.epp', { 'primaryIp' => $primaryIp, 'sshAllowedGroups' => $sshAllowedGroups }),
	mode => '644',
	owner => 'root',
	group => 'root',
	notify => Service['ssh'],
        before => Service['ssh'],
}

# Reload sshd on configuration file changes, and make sure service is enabled
service { 'ssh':
        ensure => running,
        enable => true,
        subscribe => File['/etc/ssh/sshd_config'],
	require => File['/etc/ssh/sshd_config'],
}

# Add allowed sudoers group to sudoers
file { '/etc/sudoers':
	ensure => file,
	source => '/root/automation/files/etc/sudoers',
	mode => '440',
	owner => 'root',
	group => 'root', 
}

# Grub patch to fix issue described here: https://askubuntu.com/questions/468466/why-this-occurs-error-diskfilter-writes-are-not-supported
file { '/etc/grub.d/00_header':
        ensure => file,
        source => '/root/automation/files/etc/grub.d/00_header',
	mode => '755',
	owner => 'root',
	group => 'root',
	before => Exec['/usr/sbin/update-grub'],
	notify => Exec['/usr/sbin/update-grub'],	
}

# Update grub, if we just previously patched the header
exec { '/usr/sbin/update-grub':
        command => '/usr/sbin/update-grub',
        subscribe => File['/etc/grub.d/00_header'],
	require => File['/etc/grub.d/00_header'],
	refreshonly => true,	
}

# Let's install clamav
package { 'clamav':
        ensure => present,
	before => Package['clamav-daemon'],
}

package { 'clamav-daemon':
        ensure => present,
	require => Package['clamav'],
	before => File['/etc/clamav/freshclam.conf'],
}

# Let's configure clamav update configuration
file { '/etc/clamav/freshclam.conf':
        ensure => file,
        content => epp('/root/automation/files/etc/clamav/freshclam.conf.epp', { 'httpProxy' => $httpProxy }),
        require => Package['clamav-daemon'],
	before => Exec['/usr/bin/freshclam'],
	notify => Exec['/usr/bin/freshclam'],
}

# Run clamav database update
exec { '/usr/bin/freshclam':
        command => '/usr/bin/freshclam',
        require => File['/etc/clamav/freshclam.conf'],
	refreshonly => true,
	timeout => 1200,
	subscribe => File['/etc/clamav/freshclam.conf'],
}

# Let's start clamav
service { 'clamav-daemon':
        ensure => running,
        enable => true,
        subscribe => Exec['/usr/bin/freshclam'],
	require => Exec['/usr/bin/freshclam'],
	before => Service['clamav-freshclam'],
}

service { 'clamav-freshclam':
        ensure => running,
        enable => true,
        subscribe => Service['clamav-daemon'],
	require => Service['clamav-daemon'],
	before => File['/etc/cron.daily/clamscan'],
}

# Configure a cron daily script to run a scan and send results via email
file { '/etc/cron.daily/clamscan':
        ensure => file,
        content => epp('/root/automation/files/etc/cron.daily/clamscan.epp', { 'alertsEmail' => $alertsEmail, 'alertsFromEmail' => $alertsFromEmail }),
        mode => '0755',
	owner => 'root',
	group => 'root',
        require => Service['clamav-freshclam'],
	before => Package['shorewall'],
}

# Disable avahi
file { '/etc/init/avahi-daemon.override':
        ensure => file,
	content => 'manual',
	mode => '644',
	owner => 'root',
	group => 'root',
}

# Install rootkit hunter
package { 'rkhunter':
        ensure => present,
	require => File['/etc/cron.daily/clamscan'],
	before => Package['mailutils'],
}

# Install mail
package { 'mailutils':
        ensure => present,
	require => Package['rkhunter'],
	before => File['/etc/default/rkhunter'], 
}

# Tell apt to run rkhunter when new packages are installed
file { '/etc/default/rkhunter':
        ensure => file,
        content => 'APT_AUTOGEN="yes"',
        require => Package['rkhunter'],
	mode => '644',
	owner => 'root',
	group => 'root',
	before => File['/etc/rkhunter.conf'],
	notify => Exec['/usr/bin/rkhunter --update'],
}

# Configure rkhunter config file
file { '/etc/rkhunter.conf':
        ensure => file,
        content => epp('/root/automation/files/etc/rkhunter.conf.epp', { 'alertsEmail' => $alertsEmail, 'alertsFromEmail' => $alertsFromEmail, 'httpProxy' => $httpProxy }),
        require => File['/etc/default/rkhunter'],
	mode => '644',
	owner => 'root',
	group => 'root',
	before => Exec['/usr/bin/rkhunter --update'],
	notify => Exec['/usr/bin/rkhunter --update'],
}

# Let's update rkhunter
exec { '/usr/bin/rkhunter --update':
        command => '/usr/bin/rkhunter --update',
        require => File['/etc/rkhunter.conf'],
	before => Exec['/usr/bin/rkhunter --propupd'],
	refreshonly => true,
	notify => Exec['/usr/bin/rkhunter --propupd'],
}

# Let's get the baseline for our system with rkhunter
exec { '/usr/bin/rkhunter --propupd':
        command => '/usr/bin/rkhunter --propupd',
        require => File['/etc/rkhunter.conf'],
	refreshonly => true,
	before => File['/etc/crontab'],
}

# Create the cronjob for rkhunter
file { '/etc/crontab':
        source => '/root/automation/files/etc/crontab',
	mode => '644',
	owner => 'root',
	group => 'root',
        require => File['/etc/rkhunter.conf'],
}

# Postfix was installed with rkhunter, let's configure it to relay through xinternal01.
file_line { '/etc/postfix/main.cf':
        path => '/etc/postfix/main.cf',
        line => "relayhost = $smtpRelay",
        match => '^relayhost.*',
        require => File['/etc/rkhunter.conf'],
	notify => Service['postfix'],
	before => Service['postfix'],
}

# Restart postfix now that we've modified the configuration
service { 'postfix':
        ensure => running,
        enable => true,
        subscribe => File_Line['/etc/postfix/main.cf'],
	require => File_Line['/etc/postfix/main.cf'],
	before => Package['shorewall'],
}

# Install shorewall if not installed
package { 'shorewall':
        ensure => present,
        require => File_line['/etc/ldap/ldap.conf'],
        before => File['/etc/shorewall/interfaces'],
}

# Create shorewall interfaces file
file { '/etc/shorewall/interfaces':
        ensure => file,
        source => '/root/automation/files/etc/shorewall/interfaces',
        mode => '644',
        owner => 'root',
        group => 'root',
        require => Package['shorewall'],
        before => File['/etc/shorewall/zones'],
        notify => Exec['/sbin/shorewall start'],
}

# Create shorewall zones file
file { '/etc/shorewall/zones':
        ensure => file,
        source => '/root/automation/files/etc/shorewall/zones',
        mode => '644',
        owner => 'root',
        group => 'root',
        require => File['/etc/shorewall/interfaces'],
        before => File['/etc/shorewall/policy'],
        notify => Exec['/sbin/shorewall start'],
}

# Create shorewall policy file
file { '/etc/shorewall/policy':
        ensure => file,
        source => '/root/automation/files/etc/shorewall/policy',
        mode => '644',
        owner => 'root',
        group => 'root',
        require => File['/etc/shorewall/zones'],
        before => File['/etc/shorewall/rules'],
        notify => Exec['/sbin/shorewall start'],
}

# Create shorewall rules file
file { '/etc/shorewall/rules':
        ensure => file,
        content => epp('/root/automation/files/etc/shorewall/rules.epp', { 'ntp' => $ntp, 'smtpRelay' => $smtpRelay, 'httpProxy' => $httpProxy, 'ldapServerPrimary' => $ldapServerPrimary, 'ldapServerSecondary' => $ldapServerSecondary, 'dns' => $dns }),
        mode => '644',
        owner => 'root',
        group => 'root',
        require => File['/etc/shorewall/policy'],
        before => Exec['/sbin/shorewall start'],
        notify => Exec['/sbin/shorewall start'],
}

# Start shorewall
exec { '/sbin/shorewall start':
        command => '/sbin/shorewall start',
        subscribe => File['/etc/shorewall/rules'],
        require => File['/etc/shorewall/rules'],
}

# Install NTP, if not installed.
package { 'ntp':
	ensure => present,
}
	

# Configure NTP client with NTP server
file_line { '/etc/ntp.conf':
        path => '/etc/ntp.conf',
        line => "server $ntp",
	match => '^server pool.ntp.org',
	before => Service['ntp'],
	notify => Service['ntp'],
}

# Restart the NTP service on NTP file change
service { 'ntp':
        ensure => running,
        enable => true,
        subscribe => File_Line['/etc/ntp.conf'],
	require => File_Line['/etc/ntp.conf'],
}

# Install fail2ban
package { 'fail2ban':
        ensure => present,
	require => File_line['/etc/ldap/ldap.conf'],
	before => File['/etc/fail2ban/jail.local'],
}

# Copy the config file to .local
file { '/etc/fail2ban/jail.local':
        source => '/etc/fail2ban/jail.conf',
	require => Package['fail2ban'],
	before => Service['fail2ban'],
	notify => Service['fail2ban'],
	mode => '644',
	owner => 'root',
	group => 'root',
}

# Start/enable the service
service { 'fail2ban':
        ensure => running,
        enable => true,
        subscribe => File['/etc/fail2ban/jail.local'],
	require => File['/etc/fail2ban/jail.local'],
}

# Setup the from mailname in /etc/mailname
file { 'mailname':
        path => '/etc/mailname',
        content => "$alertsFromEmailDomain",
	require => Package['rkhunter'],
	mode => '644',
	owner => 'root',
	group => 'root',
}

# Fix permissions on postfix directory & files
file { '/etc/postfix': 
	ensure => directory,
	mode => '755',
	owner => 'root',
	group => 'root',
	require => File_line['/etc/postfix/main.cf'],
}
file { '/etc/postfix/dynamicmaps.cf':
	ensure => file,
	mode => '644',
	owner => 'root',
	group => 'root',
	require => File_line['/etc/postfix/main.cf'],
}
file { '/etc/postfix/main.cf': 
	ensure => file,
	mode => '644',
	owner => 'root',
        group => 'root',
        require => File_line['/etc/postfix/main.cf'],
}
file { '/etc/postfix/master.cf': 
	ensure => file,
	mode => '644',
	owner => 'root',
        group => 'root',
        require => File_line['/etc/postfix/main.cf'],
	
}
file { '/etc/postfix/postfix-script':
	ensure => file,
	mode => '755',
	owner => 'root',
        group => 'root',
        require => File_line['/etc/postfix/main.cf'],
}
file { '/var/spool/postfix': 
	ensure => directory,
	mode => '755',
	owner => 'root',
        group => 'root',
        require => File_line['/etc/postfix/main.cf'],
}
file { '/var/log/mail.err': 
	ensure => file,
	mode => '600',
	owner => 'root',
        group => 'root',
        require => File_line['/etc/postfix/main.cf'],
}
file { '/var/log/mail.log': 
	ensure => file,
	mode => '600',
	owner => 'root',
        group => 'root',
        require => File_line['/etc/postfix/main.cf'],
}

# Install apache
package { 'apache2':
        ensure => present,
	require => File_line['/etc/ldap/ldap.conf'],
	before => Package['php5'],
}

# Install php5, libapache2-mod-php5, php5-ldap, php5-sasl, libldap-2.4-2, libexpect-php5, php5-curl
package { 'php5': 
	ensure => present, 
	require => Package['apache2'],
	before => Package['libapache2-mod-php5'],
}
package { 'libapache2-mod-php5': 
	ensure => present, 
	require => Package['php5'],
	before => Package['php5-ldap'],
}
package { 'php5-ldap': 
	ensure => present,
	require => Package['libapache2-mod-php5'],
	before => Package['php5-sasl'],
}
package { 'php5-sasl': 
	ensure => present,
	require => Package['php5-ldap'], 
	before => Package['libldap-2.4-2'],
}
package { 'libldap-2.4-2': 
	ensure => present,
	require => Package['php5-sasl'],
	before => Package['php5-curl'],
}
package { 'php5-curl': 
	ensure => present, 
	require => Package['libldap-2.4-2'],
	before => Exec['/usr/sbin/a2dismod userdir include autoindex status negotiation version auth_digest authnz_ldap ldap dav_module dav_fs_module info speling proxy proxy_balancer'], 
	notify => Exec['/usr/sbin/a2dismod userdir include autoindex status negotiation version auth_digest authnz_ldap ldap dav_module dav_fs_module info speling proxy proxy_balancer'],
}

exec { '/usr/sbin/a2dismod userdir include autoindex status negotiation version auth_digest authnz_ldap ldap dav_module dav_fs_module info speling proxy proxy_balancer':
        command => '/usr/sbin/a2dismod userdir include autoindex status negotiation version auth_digest authnz_ldap ldap dav_module dav_fs_module info speling proxy proxy_balancer',
        require => Package['php5-curl'],
	refreshonly => true,
	before => Exec['/usr/sbin/a2enmod ssl'],
	notify => Exec['/usr/sbin/a2enmod ssl'],
	returns => [0, 1],
}

exec { '/usr/sbin/a2enmod ssl':
        command => '/usr/sbin/a2enmod ssl',
        require => Exec['/usr/sbin/a2dismod userdir include autoindex status negotiation version auth_digest authnz_ldap ldap dav_module dav_fs_module info speling proxy proxy_balancer'],
	refreshonly => true,
	before => File['/etc/apache2/ssl'],
	returns => [0, 1],
}

file { '/etc/apache2/ssl': 
	ensure => directory, 
	group => 'root', 
	owner => 'root', 
	mode => '700', 
	require => Exec['/usr/sbin/a2enmod ssl'],
	before => File['/etc/apache2/apache2.conf'],
}

# Restrict apache2 configuration, and configure vhost.
file { '/etc/apache2/apache2.conf':
        source => '/root/automation/files/etc/apache2/apache2.conf',
	require => File['/etc/apache2/ssl'],
	before => File['/etc/apache2/conf-enabled/security.conf'],
	mode => '640',
	owner => 'root',
	group => 'www-data',
}

file { '/etc/apache2/conf-enabled/security.conf':
        source => '/root/automation/files/etc/apache2/conf-enabled/security.conf',
	require => File['/etc/apache2/apache2.conf'],
        before => File['/etc/apache2/sites-available/default-ssl.conf'],
	mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/etc/apache2/sites-available/default-ssl.conf':
        path => '/etc/apache2/sites-available/default-ssl.conf',
        content => epp('/root/automation/files/etc/apache2/sites-available/default-ssl.conf.epp', { 'alertsEmail' => $alertsEmail }),
        require => File['/etc/apache2/conf-enabled/security.conf'],
	before => File['/etc/apache2/ssl/ldaprst.crt'],
	mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/etc/apache2/ssl/ldaprst.crt':
        source => '/root/automation/files/etc/apache2/ssl/ldaprst.crt',
        require => File['/etc/apache2/sites-available/default-ssl.conf'],
        mode => '600',
        owner => 'root',
        group => 'root',
	before => File['/etc/apache2/ssl/ldaprst.intermediate.crt'],
}

file { '/etc/apache2/ssl/ldaprst.intermediate.crt':
        source => '/root/automation/files/etc/apache2/ssl/ldaprst.intermediate.crt',
        require => File['/etc/apache2/ssl/ldaprst.crt'],
        mode => '600',
        owner => 'root',
        group => 'root',
	before => File['/etc/apache2/ssl/ldaprst.key'],

}

file { '/etc/apache2/ssl/ldaprst.key':
        source => '/root/automation/files/etc/apache2/ssl/ldaprst.key',
        require => File['/etc/apache2/ssl/ldaprst.intermediate.crt'],
        mode => '600',
        owner => 'root',
        group => 'root',
	before => Exec['/usr/sbin/a2ensite default-ssl.conf'],
	notify => Exec['/usr/sbin/a2ensite default-ssl.conf'],
}

exec { '/usr/sbin/a2ensite default-ssl.conf':
        command => '/usr/sbin/a2ensite default-ssl.conf',
        require => File['/etc/apache2/ssl/ldaprst.key'],
	refreshonly => true,
}

file { '/usr/sbin/apache2': 
	ensure => file, 
	mode => '511',
	owner => 'root',
	group => 'root',
	require => Package['apache2'], 
}
file { '/var/log/apache2': 
	ensure => directory, 
	mode => '750',
	owner => 'root',
	group => 'adm',
	require => Package['apache2'],
}

file { '/etc/apache2':
	ensure => directory,
	mode => '750',
	owner => 'root',
	group => 'www-data',
	require => Package['apache2'],
	before => File['/etc/apache2/ssl'],
	subscribe => Exec['/usr/sbin/a2enmod ssl'],
}

# Restrict php5 apache configureation
file { '/etc/php5/apache2/php.ini':
        content => epp('/root/automation/files/etc/php5/apache2/php.ini.epp', { 'tz' => $tz }),
        require => Package['libapache2-mod-php5'],
	mode => '644',
	owner => 'root',
	group => 'root',
}

# Restrict php5 cli configuration
file { '/etc/php5/cli/php.ini':
        content => epp('/root/automation/files/etc/php5/cli/php.ini.epp', { 'tz' => $tz }),
        require => Package['libapache2-mod-php5'],
        mode => '644',
        owner => 'root',
        group => 'root',
}

# Start to build to the lprt unpriv application.
file { '/var/www/var':
	ensure => directory,
	owner => 'root',
	group => 'www-data',
	mode => '750',
}

file { '/var/www/var/www': 
	ensure => directory, 
	source => '/root/automation/files/var/www/var/www',
	owner => 'root',
	group => 'www-data',
	mode => '750',
}

file { '/var/www/var/www/includes':
        ensure => directory,
        owner => 'root',
        group => 'www-data',
        mode => '750',
}

file { '/var/www/var/www/includes/Message.class.php':
        ensure => file,
	source => '/root/automation/files/var/www/var/www/includes/Message.class.php',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/includes/ppublic_key.pem':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/includes/ppublic_key.pem',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/includes/uprivate_key.pem':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/includes/uprivate_key.pem',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/includes/upriv-lprt.log':
	ensure => file,
	mode => '660',
	owner => 'root',
	group => 'www-data',
}

file { '/var/log/lprt.log':
        ensure => file,
        mode => '600',
        owner => 'root',
        group => 'root',
}

file { '/var/www/var/www/includes/upublic_key.pem':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/includes/upublic_key.pem',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/includes/messages':
        ensure => directory,
        owner => 'root',
        group => 'www-data',
        mode => '750',
}

file { '/var/www/var/www/includes/messages/rpupdate.enc':
        ensure => file,
        mode => '660',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/includes/messages/rpustatusr.enc':
        ensure => file,
        mode => '660',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/includes/messages/rrequests.enc':
        ensure => file,
        mode => '660', 
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html':
        ensure => directory,
        owner => 'root',
        group => 'www-data',
        mode => '750',
}

file { '/var/www/var/www/html/favicon.ico':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/favicon.ico',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/ad.html':
        ensure => file,
        content => epp('/root/automation/files/var/www/var/www/html/ad.html.epp', { 'envFullName' => $envFullName } ),
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/ad_reset.php':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/ad_reset.php',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/index.html':
        ensure => file,
        content => epp('/root/automation/files/var/www/var/www/html/index.html.epp', { 'envFullName' => $envFullName } ),
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/legacy.html':
        ensure => file,
        content => epp('/root/automation/files/var/www/var/www/html/legacy.html.epp', { 'envFullName' => $envFullName } ),
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/ptsans.woff':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/ptsans.woff',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/request.php':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/request.php',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/reset.php':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/reset.php',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}  

file { '/var/www/var/www/html/status.php':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/status.php',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/update.php':
        ensure => file,
        content => epp('/root/automation/files/var/www/var/www/html/update.php.epp', { 'envFullName' => $envFullName } ),
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/css':
        ensure => directory,
        owner => 'root',
        group => 'www-data',
        mode => '750',
}

file { '/var/www/var/www/html/css/styles.css':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/css/styles.css',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/images':
        ensure => directory,
        owner => 'root',
        group => 'www-data',
        mode => '750',
}


file { '/var/www/var/www/html/images/ldap-bg.png':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/images/ldap-bg.png',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/images/ldap-worm.png':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/images/ldap-worm.png',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/images/loading-bar.png':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/images/loading-bar.png',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/var/www/html/js':
        ensure => directory,
        owner => 'root',
        group => 'www-data',
        mode => '750',
}

file { '/var/www/var/www/html/js/scripts.js':
        ensure => file,
        source => '/root/automation/files/var/www/var/www/html/js/scripts.js',
        mode => '640',
        owner => 'root',
        group => 'www-data',
}

file { '/var/www/usr': 
	ensure => directory, 
	owner => 'root', 	
	group => 'www-data', 
	mode => '550', 
}
file { '/var/www/usr/share':
	ensure => directory, 
	owner => 'root', 
	group => 'www-data',
	mode => '550', 
}
file { '/var/www/usr/share/zoneinfo':
	ensure => directory,
	owner => 'root', 
	group => 'www-data', 
	mode => '550', 
}

file { '/var/www/usr/share/zoneinfo/Europe':
	ensure => directory, 
	owner => 'root', 
	group => 'www-data', 
	mode => '550',
}

file { '/var/www/usr/share/zoneinfo/Europe/Amsterdam': 
	ensure => file, 
	source => '/root/automation/files/var/www/usr/share/zoneinfo/Europe/Amsterdam', 
	owner => 'root', 
	group => 'www-data', 
	mode => '440', 
}

file { '/var/www/dev':
	ensure => directory,
	owner => 'root',
	group => 'www-data',
	mode => '550',
	before => Exec['/bin/mknod -m 0440 /var/www/dev/random c 1 8'],
}

exec { '/bin/mknod -m 0440 /var/www/dev/random c 1 8':
        command => '/bin/mknod -m 0440 /var/www/dev/random c 1 8',
	creates => '/var/www/dev/random',
	require => File['/var/www/dev'],
}

exec { '/bin/mknod -m 0440 /var/www/dev/urandom c 1 9':
        command => '/bin/mknod -m 0440 /var/www/dev/urandom c 1 9',
	creates => '/var/www/dev/urandom',
	require => File['/var/www/dev'],
}

# Start to build the lprt priv application.
file { '/opt/bin':
	ensure => directory,
	owner => 'root',
	group => 'root',
	mode => '700',
	before => File['/opt/bin/ldapreset.php'],
}

file { '/opt/bin/ldapreset.php':
	ensure => file, 
	source => '/root/automation/files/opt/bin/ldapreset.php', 
	owner => 'root', 
	group => 'root', 
	mode => '600',
	require => File['/opt/bin'],
}

file { '/opt/ldapreset':
	ensure => directory,
	source => '/root/automation/files/opt/ldapreset',
	owner => 'root',
        group => 'root',
        mode => '700',
}

file { '/opt/ldapreset/Authorize.class.php':
        ensure => file,
        content => epp('/root/automation/files/opt/ldapreset/Authorize.class.php.epp', { 'envFullName' => $envFullName, 'alertsFromEmail' => $alertsFromEmail } ),
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/opt/ldapreset/ActiveDirectory.class.php':
        ensure => file,
        source => '/root/automation/files/opt/ldapreset/ActiveDirectory.class.php',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/opt/ldapreset/Ldap.class.php':
        ensure => file,
        source => '/root/automation/files/opt/ldapreset/Ldap.class.php',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/opt/ldapreset/Logging.class.php':
        ensure => file,
        source => '/root/automation/files/opt/ldapreset/Logging.class.php',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/opt/ldapreset/MessagePriv.class.php':
        ensure => file,
        source => '/root/automation/files/opt/ldapreset/MessagePriv.class.php',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/var/opt/ldapreset': 
	ensure => directory, 
	source => '/root/automation/files/var/opt/ldapreset', 
	owner => 'root',
	group => 'root', 
	mode => '700',
	recurse => true,
	purge => true,
}

file { '/var/opt/ldapreset/keyStore.db':
        ensure => file,
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/etc/opt/ldapreset':
	ensure => directory,
	owner => 'root',
	group => 'root',
	mode => '700',
}

file { '/etc/opt/ldapreset/config.inc.php':
	ensure => file,
        content => epp('/root/automation/files/etc/opt/ldapreset/config.inc.php.epp', { 'httpProxy' => $httpProxy, 'primaryLdapServer' => $primaryLdapServer, 'ldapAdminUser' => $ldapAdminUser, 'ldapAdminPass' => $ldapAdminPass }),
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/etc/opt/ldapreset/email_template.tpl':
        ensure => file,
	content => epp('/root/automation/files/etc/opt/ldapreset/email_template.tpl.epp', { 'fqdnHostname' => $fqdnHostname }),
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/etc/opt/ldapreset/cacert.pem':
        ensure => file,
        source => '/root/automation/files/etc/opt/ldapreset/cacert.pem',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/etc/opt/ldapreset/pprivate_key.pem':
        ensure => file,
        source => '/root/automation/files/etc/opt/ldapreset/pprivate_key.pem',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/etc/opt/ldapreset/ppublic_key.pem':
        ensure => file,
        source => '/root/automation/files/etc/opt/ldapreset/ppublic_key.pem',
        owner => 'root',
        group => 'root',
        mode => '600',
}

file { '/etc/opt/ldapreset/upublic_key.pem':
        ensure => file,
        source => '/root/automation/files/etc/opt/ldapreset/upublic_key.pem',
        owner => 'root',
        group => 'root',
        mode => '600',
}

# Create log rotate script for lprt tool
file { '/etc/logrotate.d/lprt':
        ensure => file,
        source => '/root/automation/files/etc/logrotate.d/lprt',
	owner => 'root',
	group => 'root',
	mode => '644',
	require => File['/opt/ldapreset'],
}

# Install incron
package { 'incron':
        ensure => present,
	require => File['/var/www/var/www'],
	before => File['/etc/incron.allow'],
}

# Configure incron for lprt application
file { '/etc/incron.allow':
        ensure => file,
        content => 'root',
	require => Package['incron'],
	before => File['/etc/incron.d/lprt'],
	mode => '640',
	owner => 'root',
	group => 'incron',
}

file { '/etc/incron.d/lprt':
        ensure => file,
        source => '/root/automation/files/etc/incron.d/lprt',
	require => File['/etc/incron.allow'],
	mode => '600',
	owner => 'root',
	group => 'root',
}

# Configure cronjob for LPRT Priv Tool


exec { 'touch /tmp/puppetLastRunTime':
	command => '/usr/bin/touch /tmp/puppetLastRunTime',
}
