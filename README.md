Description
====

LDAP Password Reset is a password reset application for OpenLDAP, allowing for secure and self-serve password resets for LDAP accounts.

The tool can verify the users identity by either having them authenticate with a secondary ADFS system whose user<->user mapping is defined in an LDAP attribute, or sending an email with a temporary reset token to the email attribute configured for the user in LDAP.

Setup
====

To use, one would need to:

1. Setup a Debian Linux server
2. Install Puppet
3. Clone the contents of this repo to: `/root/automation/`
5. Update the variables in ldaprst.pp
6. Replace `ldaprst.crt`, `ldaprst.intermediate.crt`, `ldaprst.key` with a TLS certificate to be used by the self-serve password reset portal
7. Apply the puppet manifest locally, and the rest of the configuration will take place automatically:

        puppet apply ldaprst.pp
7. If all went according to plan, you should have a webservice listening on port 443

Security
========

This application was designed with security in-mind, the admin credentials for the OpenLDAP server and the scripts that run against it exist in a priveleged area of the server.  The unprivileged web-app communicates with the privileged area using public key crypto, and runs in a jail communicating via a file written locally in the jail.  If the public facing web app is compromised, the goal is to limit the threat to only interception of future password reset requests.
