<?php
////////////////////////////////////////////////////
// ActiveDirectory.class.php                      //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-08-16                      //
//                                                //
// This class authenticates ADFS credentials      //
// against the publicly accessible adfs server.   //
////////////////////////////////////////////////////

class ActiveDirectory {

	// This function tests authentication against the adfs server and returns true on success, false on failure.
	public function authenticate($username, $password)
	{
		// Validate input before using it to authenticate.
		if (filter_var($username, FILTER_VALIDATE_EMAIL) && strlen($password) <= 1024)
		{
			// Let's define the post_fields for the first request.
			$post = urlencode('SignInIdpSite').'='.urlencode('SignInIdpSite').'&'.urlencode('SignInSubmit').'='.urlencode('Sign in').'&'.urlencode('SingleSignOut').'='.urlencode('SingleSignOut');
			$pressSignIn = $this->curl_adfs($post);
			
			// Let's send request to attempt signing in.
			$post = urlencode('UserName').'='.urlencode($username).'&'.urlencode('Password').'='.urlencode($password).'&'.urlencode('AuthMethod').'='.urlencode('FormsAuthentication');
			$attemptSignIn = $this->curl_adfs($post, $pressSignIn['cookies']);
			
			// Let's check the content from the sign in attempt, and see if we were successful:
			if (strpos($attemptSignIn['content'], 'You are signed in.') !== false)
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			// Input is invalid, let's return false.
			return false;
		}
	}
	
	// Purpose of this function is to make the curl'ing a little cleaner, so it doesn't look like a disaster above.
	private function curl_adfs($post_fields, $cookies=null)
	{
		// Declare the variable we'll use ot keep track of each request's response.
		$responseCode='';

		// Declare the variable we'll use to count the number of requests as part of the loop, used for infinite loop protection.
		$loopCount=0;
		
		// Keep sending requests till we get a 200 code, sometimes redirects need to occur.
		while ($responseCode != '200')
		{
			// Let's build the curl requests givin the provided options.
			$ch = curl_init();
			
			// Set URL we'll send request to.
			curl_setopt($ch, CURLOPT_URL, ADFS_URL);
			
			// Set the HTTP Proxy.
			curl_setopt($ch, CURLOPT_PROXY, HTTP_PROXY);

			// Array of headers to forge.
			$headers = array(
                                'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:40.0) Gecko/20100101 Firefox/40.0',
                                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                                'Accept-Language: en-US,en;q=0.5',
                                'Accept-Encoding: gzip, deflate',
                                'Referer: https://adfs.example.com/adfs/ls/idpinitiatedsignon',
                                'Connection: keep-alive'
                        );


			// Set the headers.
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			
			// Check to see if we have any cookies to set.
			if (!is_null($cookies))
			{
				// Set the cookies.
				curl_setopt($ch, CURLOPT_COOKIE, $cookies);
				$cookies = null;
			}

			// Check to see if we have any post vars to set.
			if ($post_fields !== false)
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
				curl_setopt($ch, CURLOPT_POST, 1);
			}

			// Set the remainder of options, before we send the request.
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_PATH);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			// Let's execute the request.
			$response = curl_exec($ch);
			
			// Get the response code.
			$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			// Get the cookies.
			preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
                        foreach($matches[1] as $item) {
                            //parse_str($item, $cookie);
                            //$cookies = array_merge($cookies, $cookie);
				if (strlen($cookies) > 0)
				{
					$cookies .= ';'.$item;
				}
				else
				{
					$cookies = $item;
				}
			}

			// Let's close the curl sessions.
			curl_close($ch);

			// Loop protection.
			if ($loopCount >= 10)
			{
				// We've sent more than 10 requests as part of this loop, probably a never ending loop occuring -- let's break out.
				break;
			}

			$loopCount++;
		}
		
		return array('cookies'=>$cookies, 'content'=>$response);
	}
}
?>
