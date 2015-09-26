<?php
////////////////////////////////////////////////////
// Ldap.class.php                                 //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-08-16                      //
//                                                //
// This class provides an interface to interact   //
// with the LDAP server, for performing email     //
// lookups and password changes.                  //
////////////////////////////////////////////////////

class Ldap {

	// This function queries the LDAP server using the provided username to retrieve the user's email address.
	public function get_email($username, $ad=null)
	{
		// Verify username is only alphanumeric.
		if (ctype_alnum($username))
		{
			// Connect to the LDAP server.
			$ds = ldap_connect(LDAP_SERVER);
			
			if ($ds)
			{
				// Connected to the LDAP server.
				ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
				ldap_start_tls($ds);

				// Bind to the LDAP server.
				ldap_bind($ds);
				
				// Search for the user in the LDAP database.
				$search = ldap_search($ds, 'dc=xx,dc=xx,dc=example,dc=com', 'uid='.$username);
				
				// Get the user as an array from the previous search.
				$ldapUser = ldap_get_entries($ds, $search);

				// Close the bind.
				ldap_close($ds);
				
				// Check if we need ad username or ldap email.
				if ($ad == null)
				{
					// Pick out the email.
					$email = $ldapUser[0]['mail'][0];
				}
				else
				{
					$email = $ldapUser[0]['mail'][1];
				}

				// Check to see if an email was actually returned.
                        	if (filter_var($email, FILTER_VALIDATE_EMAIL))
                        	{
                                	// Email address is valid.
                                	return $email;
                        	}
                        	else
                        	{
                                	// Email address is not valid.
                                	return false;
                        	}
				
			}
			else
			{
				// Failed to connect to the LDAP server.
				return false;
			}
		}
		else
		{
			// The username is not alphanumeric.
			return false;
		}
	}

	// This function updates the user's password using an LDAP query.
	public function update_password($username, $password)
	{
		// Let's verify the password is in a format we can accept.
		if (ctype_alnum($username) && strlen($username) >= 1 && strlen($username) <= 32 && strlen($password) >= 8 && strlen($password) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $password) == $password)
		{
			// Connect to the LDAP server.
			$ds = ldap_connect(LDAP_SERVER);

                        if ($ds)
                        {
                                // Connected to the LDAP server.
                                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                                ldap_start_tls($ds);
				
				// Bind to LDAP server with privileged user.
				ldap_bind($ds, 'uid='.LDAP_ADMIN_USER.',dc=xx,dc=xx,dc=example,dc=com', LDAP_ADMIN_PASS);
				
				// Search for the user in the LDAP database.
                                $search = ldap_search($ds, 'dc=xx,dc=xx,dc=example,dc=com', 'uid='.$username);

                                // Get the user as an array from the previous search.
                                $ldapUser = ldap_get_entries($ds, $search);

				// Do the password update, using the SSHA hash.`
				ldap_mod_replace($ds, 'cn='.$ldapUser[0]['cn'][0].',ou=People,dc=xx,dc=xx,dc=xx,dc=com', array('userpassword' => $this->hash($password)));
				
                                // Close the bind.
                                ldap_close($ds);

				return 1;
					
			}
			else
			{
				// There was an issue connecting to the LDAP server.
				return false;
			}
		}
		else
		{
			// The password does not meet complexity requirements or username does not match complexity requirements.
			return 5;
		}
	}

	private function hash($string)
	{
		// Generate the salt.
		$salt = chr(rand(0,255)).chr(rand(0,255)).chr(rand(0,255)).chr(rand(0,255));

		// Generate the SSHA hash.
		$hash = '{SSHA}'.base64_encode(sha1($string.$salt,true).$salt);
		
		// Return the hash.
		return $hash;
	}
}
?>
