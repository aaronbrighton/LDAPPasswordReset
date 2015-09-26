<?php
////////////////////////////////////////////////////
// Message.class.php                              //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-08-25                      //
//                                                //
// This class communicates with the privileged    //
// side of the application.  Username, validation //
// key and new password are passed via this       //
// application.                                   //
////////////////////////////////////////////////////

class Message {

	// Where to write requests for password resets to (username).	
	private $resetRequestsPath = '/var/www/includes/messages/rrequests.enc';

	// Where to write password update requests (key, password).
	private $updateRequestPath = '/var/www/includes/messages/rpupdate.enc';
	
	// Where to read the status updates from the privileged application.
	private $updateRequestStatusResponsePath = '/var/www/includes/messages/rpustatusr.enc';
	
	// Where to find the privileged application's public key.
	private $pPublicKeyPath = '/var/www/includes/ppublic_key.pem';

	// Where to find the unprivileged applications private key.
	private $uPrivateKeyPath = '/var/www/includes/uprivate_key.pem';

	// Where to find the unprivileged applications public key.
	private $uPublicKeyPath = '/var/www/includes/upublic_key.pem';
	
	private $logPath = '/var/www/includes/upriv-lprt.log';

	// This function passes the LDAP username up to the privileged application for reset email to be generated and sent.	
	public function request_reset($username) 
	{
		// Verify username is cleanly.
		if (ctype_alnum($username) && strlen($username) > 1 && strlen($username) <= 32)
		{
			// Username is clean, let's request the privileged application take further action.
			$this->write_log('Reset request received, username is clean...');			

			// Generate a random ID to attach to this request and create the message string to be encrypted.
			$message = $username;

			// Encrypt the message string.
			$encMessage = $this->priv_encrypt($message);
			$this->write_log('Encrypted the username using openssl public key encryption...');
			
			// Line to write to file.
			$line = $encMessage['message'].'|'.$encMessage['ekey']."\n";
			
			// Write the encrypted string to the messaging file.
			$fp = fopen($this->resetRequestsPath, 'a');
			fwrite($fp, $line);
			fclose($fp);
			$this->write_log('Wrote encrypted text to '.$this->resetRequestsPath);
			
			return true;
		}
		else
		{
			// Username does not meet requirements.
			$this->write_log('Reset request received, username is not clean returning false...');
			return false;
		}
	}

	// Purpose of this function is to send password reset request using Active Directory credentials for validation.
	public function request_reset_with_ad($ldapUsername, $ldapPassword, $adUsername, $adPassword)
	{
		// Verify cleanliness of input.
		if (filter_var($adUsername, FILTER_VALIDATE_EMAIL) && ctype_alnum($ldapUsername) && strlen($ldapUsername) >= 1 && strlen($ldapUsername) <= 32 && strlen($ldapPassword) >= 8 && strlen($ldapPassword) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $ldapPassword) == $ldapPassword && strlen($ldapPassword) <= 1024)
		{
			// Input has been verified for cleanliness.
			$this->write_log('Reset request using Active Directory credentials received, input is clean...');
			
			// Generate the message to be put into the messaging file.
			$message = $ldapUsername.','.base64_encode($ldapPassword).','.$adUsername.','.base64_encode($adPassword);
			
			// Encrypt the message.
			$encMessage = $this->priv_encrypt($message);
			$this->write_log('Encrypted the Active Directory reset request using openssl public key encryption...');
			
			// Line to write to file.
			$line = $encMessage['message'].'|'.$encMessage['ekey']."\n";
			
			// Write the encrypted string to the messaging file.
                        $fp = fopen($this->resetRequestsPath, 'a');
                        fwrite($fp, $line);
                        fclose($fp);
                        $this->write_log('Wrote encrypted text to '.$this->resetRequestsPath);

                        return true;
		}
		else
		{
			// Input is not cleanly, return false.
			$this->write_log('Reset request using Active Directory credentials received, input is dirty...');
			return false;
		}
	}

	// This function takes reset email key and new password and passes both up to the privileged application to be validated and updated.
	public function request_password_update($key, $password) 
	{
		// Verify the key is cleanly.
		if (strlen($key) == 64 && ctype_alnum($key) && strlen($password) >= 8 && strlen($password) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $password) == $password)
		{
			// Key is clean, let's request the privileged application take further action.
			$this->write_log('Password update request received, key is clean...');
			
			// Create a message with the key and password to pass to the privileged application, base64 is used so that password characters don't interfere with the , dividing key and password.
			$message = $key.','.base64_encode($password);

			// Encrypt the message string.
			$encMessage = $this->priv_encrypt($message);
			$this->write_log('Encrypted the key and password using openssl public key encryption...');			
			// Line to write to the file.
			$line = $encMessage['message'].'|'.$encMessage['ekey']."\n";

			// Write the encrypted string to the messaging file.
                        $fp = fopen($this->updateRequestPath, 'a');
                        fwrite($fp, $line);
                        fclose($fp);
			$this->write_log('Wrote encrypted text to '.$this->updateRequestPath);		
	
                        return true;
		}
		else
		{
			// Key contains illegal characters.
			$this->write_log('Reset request received, username is not clean returning false...');
			return false;
		}
	}

	// This function is called to check on the status of a password update request, to determine if an error such as invalid key or password requirements failed.
	public function request_password_update_status($key) 
	{
		// Verify the key is cleanly.
                if ((ctype_alnum($key) && strlen($key) == 64) || (ctype_alnum($key) && strlen($key) > 1 && strlen($key) < 32))
                {
			$this->write_log('Password update status request received, key is clean...');

                        // Key is clean, let's get a copy of current status as reported by the privileged half of the application.
			$responses = $this->unpriv_decrypt();
			$this->write_log('Dencrypted the response status using openssl public key encryption...');
				
			
			// Let's see if there is a status update for the current key.
			if (isset($responses[$key]))
			{
				$this->write_log('Status update exists for this key...');
				
				$this->unpriv_encrypt_and_write($responses, $key);
				$this->write_log('Removing this status update from the status file...');

				// A response for this key exists, let's determine what the status is.
				if ($responses[$key]['status'] == 1)
				{
					// Password was successfully reset.
					$this->write_log('Password was successfully reset...');
					return 1;
				}
				else if ($responses[$key]['status'] == 2)
				{
					// Invalid verification key.
					$this->write_log('Invalid reset key...');
					return 3;
				}
				else if ($responses[$key]['status'] == 3)
				{
					// Password does not meet complexity requirements.
					$this->write_log('Password does not meet complexity requirements...');
					return 4;
				}
				else if ($responses[$key]['status'] == 5)
				{
					// Password is in the old list of passwords.
					$this->write_log('Password is in the old list of passwords...');
					return 6;
				}
				else if ($responses[$key]['status'] == 6)
				{
					// Password is the same as present.
					$this->write_log('Password is the same as present...');
					return 7;
				}
				else if ($responses[$key]['status'] == 7)
				{
					// Active Directory username provided does not match LDAP database association.
					$this->write_log('Active Directory credentials associated with this request are invalid.');
					return 8;
				}
				else if ($responses[$key]['status'] == 8)
                                {
                                        // Active Directory username provided does not match LDAP database association.
                                        $this->write_log('Active Directory username associated with request, does not match LDAP database.');
                                        return 9;
                                }
				else if ($responses[$key]['status'] == 4)
				{
					// An unknown error occured.
					$this->write_log('An unknown error occured...');
					return 5;
				}
			}
			else
			{
				// No response for this key found.
				$this->write_log('No response for this key in the status file...');
				return 2;
			}
		}
		else
		{
			// Key contains illegal characters.
			$this->write_log('Key contains invalid characters...');
                        return 0;
		}
	}
	
	// This function encrypts messages for the privileged side of the program.
	private function priv_encrypt($string)
	{
		// Retrieve the public key for the privileged application.
		$fp = fopen($this->pPublicKeyPath, 'r');
		$pubKeyRaw = fread($fp, filesize($this->pPublicKeyPath));
		fclose($fp);
		
		// Import the key into the openssl functions.
		$osslPubKey = openssl_get_publickey($pubKeyRaw);

		// Encrypt the message.
		openssl_seal($string, $sealed, $ekeys, array($osslPubKey));
		
		// Free the public key.
		openssl_free_key($osslPubKey);

		// Return the encrypted message and envelope key, we have to base64 encode as both are binary.
		return array('message' => base64_encode($sealed), 'ekey' => base64_encode($ekeys[0]));
	}

	// This function decrypts messages from the privileged side of the program.
	private function unpriv_decrypt()
	{
		// Retrieve the private key for the privileged application.
                $fp = fopen($this->uPrivateKeyPath, 'r');
                $privKeyRaw = fread($fp, filesize($this->uPrivateKeyPath));
                fclose($fp);

		// Read the responses from the privileged application.
		$fp = fopen($this->updateRequestStatusResponsePath, 'r');
                $responses = fread($fp, filesize($this->updateRequestStatusResponsePath)+1);
                fclose($fp);
		
		// Explode the contents from the responses file by new lines.
		$responsesLines = explode("\n", $responses);

		// Import the private key for use with decryption below.		
		$osslPrivKey = openssl_get_privatekey($privKeyRaw);

		// Loop through all the lines (responses) from the responses file.
		for ($i=0;$i<count($responsesLines);$i++)
		{
			// Explode the response line, so we have the key and encrypted data.
			$responseLine = explode('|', $responsesLines[$i]);
			
			if ($responseLine[0] != '')
			{
				// Decode the line. 
				openssl_open(base64_decode($responseLine[0]), $decResponse, base64_decode($responseLine[1]), $osslPrivKey);
			
				// Explode the response line to seperate the key from the status message.
				$decResponseArr = explode(',', $decResponse);

				// Create an array of all responses that will be returned to the calling function.
				$response[$decResponseArr[0]]['status'] = $decResponseArr[1];
				$response[$decResponseArr[0]]['time'] = $decResponseArr[2];
			}
		}

		if (isset($response))
		{
			return $response;
		}
		else
		{
			return false;
		}
	}
	
        // This function encrypts messages for the status file, after removing a key.
	private function unpriv_encrypt_and_write($string, $key)
	{
		// Remove a status update for specific key.
		unset($string[$key]);
		
                // Retrieve the public key for the unprivileged application.
                $fp = fopen($this->uPublicKeyPath, 'r');
                $pubKeyRaw = fread($fp, filesize($this->uPublicKeyPath));
                fclose($fp);

                // Import the key into the openssl functions.
                $osslPubKey = openssl_get_publickey($pubKeyRaw);
		
		$newFileData = '';
		
		// Loop through all the responses, so we can encrypt each one.
		foreach ($string as $key => $value)
		{
			// Verify this line is from the past hour, otherwise remove it from the status file as it's a stray.
			if ($value['time'] > (time()-3600))
			{
				// Build the line we're going to encrypt.
				$line = $key.','.$value['status'].','.$value['time'];
	
				// Encrypt the line.
	        	        openssl_seal($line, $sealed, $ekeys, array($osslPubKey));
				
				$newFileData .= base64_encode($sealed).'|'.base64_encode($ekeys[0])."\n";
			}
		}

                // Free the public key.
                openssl_free_key($osslPubKey);

		// Write the file.
		$fp = fopen($this->updateRequestStatusResponsePath, 'w');
		fwrite($fp, $newFileData, strlen($newFileData)+1);
		fclose($fp);

                // Return the encrypted message and envelope key, we have to base64 encode as both are binary.
                return true;
	}

	// The function appends to the log.
	private function write_log($string)
	{
		$line = date('d/M/Y:Hi:s T').' '.$string."\n";
		$fp = fopen($this->logPath, 'a');
		fwrite($fp, $line, strlen($line)+1);
		fclose($fp);
		return true;
	}
}
?>
