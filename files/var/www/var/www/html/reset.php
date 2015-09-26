<?php
////////////////////////////////////////////////////
// reset.php                                      //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-09-25                      //
//                                                //
// This page receives an AJAX request from        //
// update.php and takes the new LDAP password and //
// passes it to our messaging class.              //
////////////////////////////////////////////////////

// Include our messaging class
require_once('../includes/Message.class.php');

$ldapPassword = rawurldecode($_POST['ldapPassword']);

// Validate input
if (strlen($_POST['key']) == 64 && ctype_alnum($_POST['key']) && strlen($ldapPassword) >= 8 && strlen($ldapPassword) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $ldapPassword) == $ldapPassword)
{
	// Create message object from messaging class
	$message = new Message();

	// Request password update
	if ($message->request_password_update($_POST['key'], $ldapPassword))
	{
		// Password update request was successful.
		echo "1";
		exit;
	}
	else
	{
		// Password update request failed, most likely due to input validation.
		echo "2";
		exit;
	}
}
else
{
	// Input validation failed
        echo "2";
        exit;

}
?>
