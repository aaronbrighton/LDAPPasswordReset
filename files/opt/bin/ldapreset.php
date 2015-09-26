<?php
////////////////////////////////////////////////////
// ldapreset.php                                  //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-08-23                      //
//                                                //
// This is the main script for the privileged     //
// side of the application, it includes all the   //
// necessary configs,classes,etc..  creates the   //
// objects and does all the processing of the     //
// privileged application.                        //
////////////////////////////////////////////////////

// Required as part of the php expect LDAP commands used in the LDAP class to set the new password.
ini_set("expect.timeout", -1);
ini_set("expect.loguser", "Off");

// Require dependencies.
require_once('/etc/opt/ldapreset/config.inc.php');
require_once('/opt/ldapreset/MessagePriv.class.php');
require_once('/opt/ldapreset/Authorize.class.php');
require_once('/opt/ldapreset/Ldap.class.php');
require_once('/opt/ldapreset/Logging.class.php');
require_once('/opt/ldapreset/ActiveDirectory.class.php');

// Let's define our objects.
$message = new MessagePriv();
$authorize = new Authorize();
$ldap = new Ldap();
$log = new Logging();
$ad = new ActiveDirectory();

// Let's now get a list of password reset requests.
$rRequests = $message->get_reset_requests();
$log->write_log('Getting password reset requests...');

// Make sure that we actually have some requests before executing the loop.
if ($rRequests !== false)
{
	// Loop through all requests, so that we can send out emails.
	for($i=0;$i<count($rRequests);$i++)
	{
		// Check to see if this user wants Active Directory verification.
		if (isset($rRequests[$i]['adUsername']))
		{
			// Perform input validation on input before proceeding.
			if (ctype_alnum($rRequests[$i]['ldapUsername']) && preg_replace('/[\x00-\x1F\x7F]/', '', $rRequests[$i]['ldapUsername']) == $rRequests[$i]['ldapUsername'] && strlen($rRequests[$i]['ldapUsername']) >= 1 && strlen($rRequests[$i]['ldapUsername']) <= 32 && strlen($rRequests[$i]['ldapPassword']) >= 8 && strlen($rRequests[$i]['ldapPassword']) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $rRequests[$i]['ldapPassword']) == $rRequests[$i]['ldapPassword'] && filter_var($rRequests[$i]['adUsername'], FILTER_VALIDATE_EMAIL) && preg_replace('/[\x00-\x1F\x7F]/', '', $rRequests[$i]['adUsername']) == $rRequests[$i]['adUsername'] && strlen($rRequests[$i]['adPassword']) >= 1 && strlen($rRequests[$i]['adPassword']) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $rRequests[$i]['adPassword']) == $rRequests[$i]['adPassword'])
			{

				$log->write_log('Looking up ad username for '.$rRequests[$i]['ldapUsername']);

				// We need to process an Active Directory verification, first we need to verify the Active Directory credentials.
				$adUsername = $ldap->get_email($rRequests[$i]['ldapUsername'], 5);
				$log->write_log('Successfully retrieved ad username '.$adUsername.' for username...'.$rRequests[$i]['ldapUsername']);
	
				// Check to see if username provided matches, the Active Directory username we have on file.
				if ($adUsername == $rRequests[$i]['adUsername'])
				{
					// Username matches, let's test credentials against adfs site.
					$log->write_log('Active Directory username provided matches username associated with this ldap user...');
	
					// Attempt to authenticate the user.
					if ($ad->authenticate($adUsername, $rRequests[$i]['adPassword']))
					{
						// Authentication attempt was successful.
						$log->write_log('Active Directory user '.$adUsername.' authenticated successfully...');
						
						// Proceed with password reset.
						$uStatus = $ldap->update_password($rRequests[$i]['ldapUsername'], $rRequests[$i]['ldapPassword']);
	
						// Let's check the status returned by the update request.
	                        		if ($uStatus == 1)
			                        {
			                                // Password was updated successfully.
			                                $message->set_update_response(1, $rRequests[$i]['ldapUsername']);
			                                $log->write_log('Password was successfully updated for '.$rRequests[$i]['ldapUsername'].' with Active Directory validated user '.$adUsername.'...');
			                        }
			                        else if ($uStatus == 5 || $uStatus == 4)
			                        {
			                                // Password does not meet complexity requirements.
			                                $message->set_update_response(3, $rRequests[$i]['ldapUsername']);
			                                $log->write_log('Pssword update failed due to complexity requirements for '.$rRequests[$i]['ldapUsername'].' with Active Directory validated user '.$adUsername.'...');
			                        }
			                        else if ($uStatus == 2)
			                        {
			                                // Password is in list of old passwords.
			                                $message->set_update_response(5, $rRequests[$i]['ldapUsername']);
			                                $log->write_log('Pssword update failed due to old passwords lists for '.$rRequests[$i]['ldapUsername'].' with Active Directory validated user '.$adUsername.'...');
			                        }
			                        else if ($uStatus == 3)
			                        {
			                                // Password is the same as present.
			                                $message->set_update_response(6, $rRequests[$i]['ldapUsername']);
			                                $log->write_log('Pssword update failed due to password being the same as present for '.$rRequests[$i]['ldapUsername'].' with Active Directory validated user '.$adUsername.'...');
			                        }
			                        else
			                        {
				                                // An unknown error occured, and we likely weren't able to update the password.
			                                $message->set_update_response(4, $rRequests[$i]['ldapUsername']);
			                                $log->write_log('Pssword update failed due to an unknown error for '.$rRequests[$i]['ldapUsername'].' with Active Directory validated user '.$adUsername.'...');
			                        }
					}
					else
					{
						$log->write_log('Active Directory user '.$adUsername.' failed to authenticate...');
						$message->set_update_response(7, $rRequests[$i]['ldapUsername']);
					}
				}
				else
				{
					// Username does not match, let's fail.
					$message->set_update_response(8, $rRequests[$i]['ldapUsername']);
					$log->write_log('Active Directory username '.$rRequests[$i]['adUsername'].' does not match with the Active Directory associated email for user '.$rRequests[$i]['ldapUsername'].'...');
				}

				// Removing reset request.
				$message->remove_reset_request($rRequests[$i]['ldapUsername']);
				$log->write_log('Removing reset request for username '.$rRequests[$i]['ldapUsername'].'...');
			}
			else
			{
				// Input validation failed for some sort of ad field.
                                $log->write_log('Input validaton failed for request with Active Directory username '.$rRequests[$i]['adUsername'].'...');

                                // Let's remove the request, regardless of whether we could send email or not.
                                $message->remove_reset_request($rRequests[$i]['ldapUsername']);
                                $log->write_log('Removing reset request for Active Directory username '.$rRequests[$i]['adUsername'].'...');
			}
		}
		else
		{
			// This is not a Active Directory verified password reset, let's use legacy email option.
		
			// Validate input.
			if (ctype_alnum($rRequests[$i]) && strlen($rRequests[$i]) >= 1 && strlen($rRequests[$i]) <= 32 && preg_replace('/[\x00-\x1F\x7F]/', '', $rRequests[$i]) == $rRequests[$i])
			{
	                        // Let's get the email address for this user's request.
				$email = $ldap->get_email($rRequests[$i]);
				$log->write_log('Looking up email for username '.$rRequests[$i]);		
		
				// Check to make sure this username does actually exist, and has an email associated with it.
				if ($email != false)
				{
					// User exists, and has a mail attribute.
					$log->write_log('Successfully retrieved email...'.$email.' for username...'.$rRequests[$i]);			
					// Let's send the reset email for this user.
					$authorize->send_email($rRequests[$i], $email);
					$log->write_log('Email sent to '.$email.'...');
				}
				else
				{
					// Email or username does not exist.
					$log->write_log('Failed to lookup an email for this username, either user does not exist or mail attribute does not exist for this user.');
				}
		
				// Let's remove the request, regardless of whether we could send email or not.
		                $message->remove_reset_request($rRequests[$i]);
      	 	         	$log->write_log('Removing reset request for username '.$rRequests[$i].'...');
			}
			else
			{
				// Input validation failed, write log, and remove key.
				$log->write_log('Input validaton failed for username '.$rRequests[$i].'...');

				// Let's remove the request, regardless of whether we could send email or not.
                                $message->remove_reset_request($rRequests[$i]);
                                $log->write_log('Removing reset request for username '.$rRequests[$i].'...');
			}
		}
	}
}

// Let's get all password update requests. 
$uRequests = $message->get_update_requests();
$log->write_log('Getting password update requests...');

// Check to make sure there are some actual update requests to take care of.
if ($uRequests !== false)
{
	// Let's loop through all the password update requests.
	foreach ($uRequests as $key => $value)
	{
		// Validate input.
		if (strlen($key) == 64 && ctype_alnum($key) && strlen($value[1]) >= 8 && strlen($value[1]) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $value[1]) == $value[1])
		{
			
			// Let's get the username for this request if the key is valid.
			$username = $authorize->is_key_authorized($key);		
	
			// Let's verify if this password update request is valid.
			if ($username !== false)
			{
				$log->write_log('Valid key '.$key.' for username '.$username.'...');			
	
				// Key is authorized, let's perform the password update.
				$uStatus = $ldap->update_password($username, $value[1]);			

				// Let's check the status returned by the update request.
				if ($uStatus == 1)
				{
					// Password was updated successfully.
					$message->set_update_response(1, $key);
					$log->write_log('Password was successfully updated for '.$username.' with key '.$key.'...');				
					
					// Let's remove this key from our database now.
					$authorize->remove_key($key);
					$log->write_log('Removing key from database '.$key.'...');
				}
				else if ($uStatus == 5 || $uStatus == 4)
				{
					// Password does not meet complexity requirements.
					$message->set_update_response(3, $key);
					$log->write_log('Password update failed due to complexity requirements for '.$username.' with key '.$key.'...');
				}
				else if ($uStatus == 2)
				{
					// Password is in list of old passwords.
					$message->set_update_response(5, $key);
					$log->write_log('Password update failed due to old passwords lists for '.$username.' with key '.$key.'...');
				}
				else if ($uStatus == 3)
				{
					// Password is the same as present.
					$message->set_update_response(6, $key);
					$log->write_log('Password update failed due to password being the same as present for '.$username.' with key '.$key.'...');
					
					// Let's remove this key from our database now.
	                                $authorize->remove_key($key);
	                                $log->write_log('Removing key from database '.$key.'...');
				}
				else
				{
					// An unknown error occured, and we likely weren't able to update the password.
					$message->set_update_response(4, $key);
					$log->write_log('Password update failed due to an unknown error for '.$username.' with key '.$key.'...');				
	
					// Let's remove this key from our database now.
					$authorize->remove_key($key);
					$log->write_log('Removing key from database '.$key.'...');
				}
			}
			else
			{
				// Key is not authorized.
				$message->set_update_response(2, $key);
				$log->write_log('Password update failed due to an invalid key for '.$username.' with key '.$key.'...');
			}
		
			// Remove this password update request, now that we've processed it.
			$message->remove_update_request($key);
			$log->write_log('Removing update request '.$key.'...');
		}
		else
		{
			// Input is not valid, let's log and remove key, and notify of error.
			$log->write_log('Input validation failed for password reset request with key'.$key.' ...');

			// An unknown error occured.
                        $message->set_update_response(4, $key);
		
			 // Remove this password update request, now that we've processed it.
                        $message->remove_update_request($key);
                        $log->write_log('Removing update request '.$key.'...');	
		}
	}
}

// Now we need to do some database maintenance, and clear out any key's that are over an hour old.
$authorize->maintenance();
$log->write_log('Performing maintenance routine on database...');

// Program has ran, let's touch our file that monitors to make sure this script runs.
touch('/tmp/lprtstatus');
?>
