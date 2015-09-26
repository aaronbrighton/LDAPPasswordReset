/*
////////////////////////////////////////////////////
// script.js                                      //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-09-25                      //
//                                                //
// This javascript file does all the magic of the //
// of the web interface from input validation to  //
// communicating with the server using AJAX.      //
////////////////////////////////////////////////////
*/

///////////////////////////////////////////
// Sending functions for form submission //
///////////////////////////////////////////

// This function is called by the legacy method, and sends ldap username to the server for password reset request.
function send_username(ldapUsername)
{
	// Validate the username
	if (validate(ldapUsername, 'ldapUsername'))
	{
		// We need to send an ajax request to the server with the username.
		ajax({ldapUsername: ldapUsername}, 'request.php', 'send_username');
	}
	else
	{
		// Username is not valid.
		alert('Please enter a valid username.');
		return false;
	}
}

// This function sends the password for the legacy method.
function send_password(ldapPassword, ldapPasswordConfirm, key)
{
	// Verify password's match, and that the password is valid, as well as the key.
	if (ldapPassword == ldapPasswordConfirm && validate(ldapPassword, 'ldapPassword') && validate(key, 'key'))
	{
		// Validation passed, let's pass this to our ajax function which takes care of making the request.
		ajax({key: key, ldapPassword: encodeURIComponent(ldapPassword)}, 'reset.php', 'send_password');
		return true;
	}
	else
	{
		// Validation failed.
		alert('Please enter a valid password, and verify both password fields match.');
		return false;
	}
}

// This function sends fields for the ad verified method.
function send_ad_reset(ldapUsername, ldapPassword, ldapPasswordConfirm, adUsername, adPassword)
{

        // Validate all input first.
        if (validate(ldapUsername, 'ldapUsername') && validate(ldapPassword, 'ldapPassword') && ldapPassword == ldapPasswordConfirm && validate(adUsername, 'adUsername') && validate(adPassword, 'adPassword'))
        {
                // Input is valid, let's send the details to the server.
                ajax({ldapUsername: ldapUsername, ldapPassword: encodeURIComponent(ldapPassword), adUsername: adUsername, adPassword: encodeURIComponent(adPassword)}, 'ad_reset.php', 'send_ad_reset');
                return true;
        }
        else
        {
                // Input validation failed.
                alert('Please verify all input is valid.');
                return false;
        }
}

////////////////////////////////////////////////
// On the fly form field validation functions //
////////////////////////////////////////////////

// Function validates all inputs on the fly used by the application, with the helper function validate().
function validateField(field, e) 
{
	var green = '#23b123';
	var red = '#d42323';

	// Check which type of validation we need to do.
	if (field.id == 'ldapPassword' || field.id == 'ldapPasswordConfirm')
	{
		if (document.getElementById('ldapPassword').value == document.getElementById('ldapPasswordConfirm').value && validate(field.value, 'ldapPassword'))
		{
			// Input valid, set green.
                        document.getElementById('ldapPassword').style.borderColor=green;
			document.getElementById('ldapPasswordConfirm').style.borderColor=green;
		}
		else
		{
			// Input invalid, set red.
                        document.getElementById('ldapPassword').style.borderColor=red;
			document.getElementById('ldapPasswordConfirm').style.borderColor=red;
		}
	}
	else
	{
		if (validate(field.value, field.id))
		{
			// Input valid, set green.
                        field.style.borderColor=green;
		}
		else
		{
			// Input invalid, set red.
                        field.style.borderColor=red;
		}
	}

	// Character code for enter.
	var charCode = (typeof e.which === "13") ? e.which : e.keyCode;
	
	// Check if enter has been pressed.
        if (charCode == 13)
        {
                // Enter has been pressed, let's submit the form.
		document.getElementById('button').click();
        }
}


//////////////////////
// HELPER FUNCTIONS //
//////////////////////

// This function is called by other javascript functions to validate inputs.
function validate(value, type)
{
        // Type is username, let's validate username.
        if (type == 'ldapUsername')
        {
                // Check to make sure username is alphanumeric, atleast 1 character, less than 1024 characters and doesn't contain any special characters.
                if (isAlphaNumeric(value) && value.length >= 1 && value.length <= 32 && value.replace('/[\x00-\x1F\x7F]/g', '') == value)
                {
                        // Input validation passed.
                        return true;
                }
                else
                {
                        // Input validation failed.
                        return false;
                }
        }
        else if (type == 'ldapPassword')
        {
                if (value.length >= 8 && value.length <= 1024 && value.replace('/[\x00-\x1F\x7F]/g', '') == value)
                {
                        // Input validation passed.
                        return true;
                }
                else
                {
                        // Input validation failed.
                        return false;
                }
        }
        else if (type == 'key')
        {
                if (value.length == 64 && isAlphaNumeric(value) && value.replace('/[\x00-\x1F\x7F]/g', '') == value)
                {
                        // Input validation passed.
                        return true;
                }
                else
                {
                        // Input validation failed.
                        return false;
                }
        }
	else if (type == 'adUsername')
	{
		var pattern = /^([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$/i
		
		if (pattern.test(value) && value.length >= 1 && value.length <= 1024 && value.replace('/[\x00-\x1F\x7F]/g', '') == value)
		{
			// Input validation passed.
			return true;
		}
		else
		{
			// Input validation failed.
			return false;
		}
	}
	else if (type == 'adPassword')
	{
		if (value.length > 1 && value.length <= 1024 && value.replace('/[\x00-\x1F\x7F]/g', '') == value)
		{
			// Input validation passed.
			return true;
		}
		else
		{
			// Input validation failed.
			return false;
		}
	}
	else
	{
		// Don't know how to validate this type, return false.
		return false;
	}
}

// This function uses AJAX to take care of communicating back and forth between the webpages and the server.
function ajax(postArray, page, caller)
{
	var ajaxRequest;  // The variable that makes Ajax possible!
	var postString=''; // Declare post string before we end up filling it.

	try{
		// Opera 8.0+, Firefox, Safari
		ajaxRequest = new XMLHttpRequest();
	} catch (e){
		// Internet Explorer Browsers
		try{
			ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try{
				ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e){
				// Something went wrong
				alert("Your browser broke!");
				return false;
			}
		}
	}

	// Create a function that will receive data sent from the server
	ajaxRequest.onreadystatechange = function(){
		if(ajaxRequest.readyState == 4){
			
			// Request has been responded too, let's process the response.
			if (caller == 'send_username')
			{
				if (ajaxRequest.responseText != '1')
                                {
					// An unexpected error occure
                                        alert('There was an unexpected error trying to communicate with the server.  Please try again later, or notify operations.');
				}
				else
				{
					// Request was successful, let's let the user know.
                                        status_update(1);
				}
			}
			else if (caller == 'send_password')
			{
				// Check response from server.
				if (ajaxRequest.responseText != '1')
                        	{
					// An unexpected error occure
                                	alert('There was an unexpected error trying to communicate with the server.  Please try again later, or notify operations.');
                        	}
                        	else
                        	{
					// Request was successful, let's let the user know.
					status_update(2);
	                                
					// Now we need to call a function that will wait for verification of update status.
	                                //wait_for_status(postArray.key);
					setTimeout(function(){ajax({key: postArray.key}, 'status.php', 'wait_for_status')}, 2000);
        	                }
			}
			else if (caller == 'wait_for_status')
			{
				// Check the response from the server.
				if (ajaxRequest.responseText == '1')
				{
					// Password was updated.
					status_update(3);
				}
				else if (ajaxRequest.responseText == '3')
				{
					// Invalid verification key
					status_update(4);
				}
				else if (ajaxRequest.responseText == '5')
				{
					// Unknown error occured.
					status_update(5);
				}
				else if (ajaxRequest.responseText == '8' || ajaxRequest.responseText == '9')
				{
					// ActiveDirectory credentials failed to authenticate.
					status_update(6);
				}
				else if (ajaxRequest.responseText == '2')
				{
					// There hasn't been any update yet, let's check again in 2 seconds.
					setTimeout(function(){ajax({key: postArray.key}, 'status.php', 'wait_for_status')}, 2000);
				}
				else
				{
					alert('There was an unexpected error trying to communicate with the server.  Please try again later, or notify operations.');
				}
			}
			else if (caller == 'send_ad_reset')
			{
				// Check the response from the server.
                                if (ajaxRequest.responseText != '1')
                                {
					// An unknown error occuredi.
					alert('There was an unexpected error trying to communicate with the server.  Please try again later, or notify operations.');
				}
				else 
				{
					// Request was successful, let's let the user know.
                                        status_update(7);
					
					// Now we need to call a function that will wait for verification of update status.
					setTimeout(function(){ajax({key: postArray.ldapUsername}, 'status.php', 'wait_for_status')}, 2000);
				}
			}

		}
	}
	
	// Loop through the postArray object and get all our post variables, assign them to a POST compatible header string.
	for (var key in postArray)
	{
		if (postArray.hasOwnProperty(key))
		{
			// If post string is not empty.
			if (postString.length > 0)
			{
				// Let's add a & to existing post string, and add new post variable.
				postString = postString+'&'+key+'='+postArray[key];
			}
			else
			{
				// Let's start the post string variable with it's first value.
				postString = key+'='+postArray[key];
			}
		}
	}
	
	// Send the ajax POST request with the post string we constructed above.
	ajaxRequest.open("POST", page, true);
	ajaxRequest.setRequestHeader("Content-type","application/x-www-form-urlencoded");
	ajaxRequest.send(postString); 
}

// This function takes care of writing status messages to the client.
function status_update(messageId)
{
	if (messageId == 1)
	{
		// Username submission successful.
		message = "An email will be sent to your email address shortly, further instructions are provided to reset your password.";
		document.getElementById('status').innerHTML = message;
		return true;
	}
	else if (messageId == 2)
	{
		// Password submission successful.
		message = "Your password is being updated ... please wait for confirmation.<p><img src=\"images/loading-bar.png\" alt=\"Loading bar...\" title=\"Loading...\" /></p>";
		document.getElementById('status').innerHTML = message;
		return true;
	}
	else if (messageId == 3)
	{
		message = "Your password has been updated.";
		document.getElementById('status').innerHTML = message;
                return true;
	}
	else if (messageId == 4)
	{
		message = "Invalid verification key, you'll have to request a new password reset.";
		document.getElementById('status').innerHTML = message;
                return true;
	}
	else if (messageId == 5)
	{
		message = "An unknown error occured while trying to update your password.";
		document.getElementById('status').innerHTML = message;
                return true;
	}
	else if (messageId == 6)
	{
		message = "The ad credentials you entered failed to authenticate, please try again.<p>Enter your LDAP username: <input type=\"text\" name=\"ldapUsername\" id=\"ldapUsername\" onkeyup=\"validateField(this, event);\" /></p><p>Enter your new LDAP password: <input type=\"password\" name=\"ldapPassword\" id=\"ldapPassword\" onkeyup=\"validateField(this, event);\" /></p><p>Confirm your new LDAP password: <input type=\"password\" name=\"ldapPasswordConfirm\" id=\"ldapPasswordConfirm\" onkeyup=\"validateField(this, event);\" /></p><p>Enter your Active Directory username (ex. user@example.com): <input type=\"text\" name=\"adUsername\" id=\"adUsername\" onkeyup=\"validateField(this, event);\" /></p><p>Enter your Active Directory password: <input type=\"password\" name=\"adPassword\" id=\"adPassword\" onkeyup=\"validateField(this, event);\" /></p><p><input type=\"button\" name=\"btnReset\" value=\"Reset!\" id=\"button\" onclick=\"send_ad_reset(document.getElementById('ldapUsername').value, document.getElementById('ldapPassword').value, document.getElementById('ldapPasswordConfirm').value, document.getElementById('adUsername').value, document.getElementById('adPassword').value);\" /></p>";
		document.getElementById('status').innerHTML = message;
                return true;
		
	}
	else if (messageId == 7)
	{
		message = "Once we validate your ad credentials your password will be updated ... please wait for confirmation.<p><img src=\"images/loading-bar.png\" alt=\"Loading bar...\" title=\"Loading...\" /></p>";
		document.getElementById('status').innerHTML = message;
                return true;
	}
	else
	{
		return false;
	}
}

// Helper function to capture GET variables.
function getUrlVars() {
        var vars = {};
        var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = value;
        });
        return vars;
}

// Helper function to validate alphanumeric strings.
function isAlphaNumeric(str) {
        var code, i, len;

        for (i = 0, len = str.length; i < len; i++) {
                code = str.charCodeAt(i);
                        if (!(code > 47 && code < 58) && // numeric (0-9)
                           !(code > 64 && code < 91) && // upper alpha (A-Z)
                           !(code > 96 && code < 123)) { // lower alpha (a-z)
                        return false;
                        }
                }
        return true;
};
