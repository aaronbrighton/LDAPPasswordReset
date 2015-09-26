<?php
////////////////////////////////////////////////////
// request.php                                    //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-09-07                      //
//                                                //
// This page is part of the legacy password reset //
// method.  This page takes the AJAX form         //
// submission from legacy.html (ldap username)    //
// and passes that username to our Message class  //
// which takes care of communicating it to the    //
// privileged application.                        //
////////////////////////////////////////////////////

// Include out messaging class.
require_once('../includes/Message.class.php');

// Verify the username has been set and that it meets our complexity requirements.
if (isset($_POST['ldapUsername']) && ctype_alnum($_POST['ldapUsername']) && strlen($_POST['ldapUsername']) > 1 && strlen($_POST['ldapUsername']) <= 32 && preg_replace('/[\x00-\x1F\x7F]/', '', $_POST['ldapUsername']) == $_POST['ldapUsername'])
{
	// Create the message object.
	$message = new Message();

	// 
	if ($message->request_reset($_POST['ldapUsername']))
	{
		// Request was successful, let's let the client know they should check their email.
		echo '1';
		exit;
	}
	else
	{
		// Request was unsuccessful, most likely due to input validation by the class.
		echo '2';
		exit;
	}
}
else
{
	// Input validation failed.
	echo '2';
        exit;
}
?>
