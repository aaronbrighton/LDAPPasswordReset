<?php
////////////////////////////////////////////////////
// MessagePriv.class.php                          //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-08-16                      //
//                                                //
// This class communicates with the unprivileged  //
// side of the application.  Username, validation //
// key and new password are passed via this       //
// application.                                   //
////////////////////////////////////////////////////

class MessagePriv {

	// The purpose of this function is to retrieve the reset requests from the unprivileged application.
	public function get_reset_requests()
	{
		return $this->priv_decrypt(RESET_REQUEST_PATH);
	}
	
	public function get_update_requests()
	{
		return $this->priv_decrypt(UPDATE_REQUEST_PATH);
	}

	public function set_update_response($response, $key)
	{
		// Construct the unencrypted message we'll be writing.
		$message = $key.','.$response.','.time();

		// Let's get the encrypted message for the response file.
		$encMessage = $this->upriv_encrypt($message);

		// Line to write to the file.
                $line = $encMessage['message'].'|'.$encMessage['ekey']."\n";

                // Write the encrypted string to the response file.
                $fp = fopen(UPDATE_REQUEST_STATUS_RESPONSE_PATH, 'a');
                fwrite($fp, $line);
                fclose($fp);

                return true;
	}

	public function remove_reset_request($username)
	{
		// Get all reset requests.
                $rRequests = $this->priv_decrypt(RESET_REQUEST_PATH);

		// Check to make sure there are some actual update requests to take of.
		if ($rRequests !== false)
		{

			// Make lines empty, incase we don't have any lines it wont through a php note when writing to file.
			$lines='';

                	// Loop through all the requests in the database, and let's encrypt the lines after finding the one we want to remove.
	                foreach ($rRequests as $key => $value)
	                {
				// Verify  this request is not for the username we want to remove.
				
				if (isset($value['ldapUsername']))
				{
					// This row is for ad validated reset requests.
					if ($username != $value['ldapUsername'])
					{
						// This row is not ad validated reset request that we want to remove.
						// Build the plaintext line.
						$message = $value['ldapUsername'].','.base64_encode($value['ldapPassword']).','.$value['adUsername'].','.base64_encode($value['adPassword']);
 	                                       // Encrypt the message string.
        	                                $encMessage = $this->priv_encrypt($message);
        	
                	                        // Line to write to the file.
                        	                $lines .= $encMessage['message'].'|'.$encMessage['ekey']."\n";
					}
				}
				else if ($value != $username)
				{
	                        	// Build the plain text line.
		                        $message = $key.','.$value[1];
	
	        	                // Encrypt the message string.
	                	        $encMessage = $this->priv_encrypt($message)."\n";
	
	                        	// Line to write to the file.
		                        $lines .= $encMessage['message'].'|'.$encMessage['ekey']."\n";
				}
	                }
			
			// Write the encrypted string to the messaging file.
	                $fp = fopen(RESET_REQUEST_PATH, 'w');
        	        fwrite($fp, $lines);
	                fclose($fp);
		}

                return true;
	}

	public function remove_update_request($key)
	{
		// Get all update requests.
		$uRequests = $this->priv_decrypt(UPDATE_REQUEST_PATH);

		// Unset the update request entry for this key.
		unset($uRequests[$key]);
	
		// Define lines just so that we dont get a php notice when it gets written to the file in the event that there are no new lines to write.
		$lines='';		
	
		// Loop through all the requests in the database, and let's encrypt the lines.
		foreach ($uRequests as $key => $value)
		{
			// Build the plain text line.
			$message = $key.','.base64_encode($value[1]);

			// Encrypt the message string.
                        $encMessage = $this->priv_encrypt($message)."\n";

                        // Line to write to the file.
                        $lines .= $encMessage['message'].'|'.$encMessage['ekey']."\n";
		}

		// Write the encrypted string to the messaging file.
                $fp = fopen(UPDATE_REQUEST_PATH, 'w');
                fwrite($fp, $lines);
                fclose($fp);


		return true;
	}
		
	private function priv_decrypt($path)
	{
		// Retrieve the private key for the privileged application.
                $fp = fopen(P_PRIVATE_KEY_PATH, 'r');
                $privKeyRaw = fread($fp, filesize(P_PRIVATE_KEY_PATH));
                fclose($fp);

		// Read the messaging file from the unprivileged application.
                $fp = fopen($path, 'r');
                $messages = fread($fp, filesize($path)+1);
                fclose($fp);

		// Explode the contents from the messaging file by new lines.
                $messagesLines = explode("\n", $messages);

		// Import the private key for use with decryption below.                
                $osslPrivKey = openssl_get_privatekey($privKeyRaw);

		// Loop through all the lines (messages) from the messaging file.
                for ($i=0;$i<count($messagesLines);$i++)
                {
                        // Explode the message line, so we have the key and encrypted data.
                        $messageLine = explode('|', $messagesLines[$i]);
			if ($messageLine[0] != '')
			{
	                        // Decode the line. 
                        	openssl_open(base64_decode($messageLine[0]), $decMessage, base64_decode($messageLine[1]), $osslPrivKey);
			
				// If the above array has more than two values, use the first value as the key in our returned array.
                	        if (substr_count($decMessage,',') == 1)
				{
					// Explode the line to seperate the parameters.
	                                $decMessageArr = explode(',', $decMessage);
					$decMessageArr[1] = base64_decode($decMessageArr[1]);					

					// Add to array of messages, using the first field as the key/id.
                        		$message[$decMessageArr[0]] = $decMessageArr;
				}
				else if (substr_count($decMessage,',') == 3)
				{
					// Array contains 4 values, must be a ActiveDirectory verified reset -- let's build our message array.
					// Explode the line to seperate the parameters.
                                        $decMessageArr = explode(',', $decMessage);
				
					// Build our message array.	
					$message[$i]['ldapUsername'] = $decMessageArr[0];
                                        $message[$i]['ldapPassword'] = base64_decode($decMessageArr[1]);
                                        $message[$i]['adUsername'] = $decMessageArr[2];
                                        $message[$i]['adPassword'] = base64_decode($decMessageArr[3]);
				}
				else
				{
					// Use line number as a reference for this single parameter message.
					$message[$i] = $decMessage;
				}

			}
                }
		
		// Check to see if $message is defined, this is so we don't throw a PHP Notice.
		if (isset($message))
		{
			// Return the message.
			return $message;
		}
		else
		{
			// Return false as there is no messages.
			return false;
		}
	}

	private function upriv_encrypt($string)
	{
		// Retrieve the public key for the unprivileged application.
                $fp = fopen(U_PUBLIC_KEY_PATH, 'r');
                $pubKeyRaw = fread($fp, filesize(U_PUBLIC_KEY_PATH));
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

	private function priv_encrypt($string)
	{
		// Retrieve the public key for the privileged application.
                $fp = fopen(P_PUBLIC_KEY_PATH, 'r');
                $pubKeyRaw = fread($fp, filesize(P_PUBLIC_KEY_PATH));
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
}
?>
