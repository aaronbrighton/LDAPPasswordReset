Description
====

LDAP Password Reset is a password reset application for OpenLDAP, allowing for secure and self-serve reseting of passwords for LDAP accounts.

The tool can verify the users identity by either having them authenticate with a secondary ADFS system whose user<->user mapping is defined in an LDAP attribute, or sending an email with a temporary reset token to the email attribute configured for the user in LDAP.

Setup
====

To use, one should setup a debian server with puppet installed.  Update the variables in ldaprst.pp, then simply execute the puppet manifest ldaprst.pp, and the rest of the configuration will take place automatically.

Security
========

This application was designed with security in-mind, the admin credentials for the OpenLDAP server and the scripts that run against it exists in a priveleged area of the server.  The unprivileged web-app communicates with the privileged area using public key crypto, and runs in a jail.  If the public facing web app is compromised, they only threat is the interception of future password reset requests -- no active threat can be initiated.
