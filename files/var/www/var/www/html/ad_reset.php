<?php
////////////////////////////////////////////////////
// ad_reset.php                               //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-09-25                      //
//                                                //
// This page receives the AjAX submission from    //
// ad.html and passes the values to our           //
// messaging class.                               //
////////////////////////////////////////////////////

// Include the Messaging class
require_once('../includes/Message.class.php');

$ldapPassword = rawurldecode($_POST['ldapPassword']);
$adPassword = rawurldecode($_POST['adPassword']);

// Validate input.
if (isset($_POST['adUsername']) && filter_var($_POST['adUsername'], FILTER_VALIDATE_EMAIL) && preg_replace('/[\x00-\x1F\x7F]/', '', $_POST['adUsername']) == $_POST['adUsername'] && ctype_alnum($_POST['ldapUsername']) && preg_replace('/[\x00-\x1F\x7F]/', '', $_POST['ldapUsername']) == $_POST['ldapUsername'] && strlen($_POST['ldapUsername']) >= 1 && strlen($_POST['ldapUsername']) <= 32 && strlen($ldapPassword) >= 8 && strlen($ldapPassword) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $ldapPassword) == $ldapPassword && isset($adPassword) && strlen($adPassword) >= 1 && strlen($adPassword) <= 1024 && preg_replace('/[\x00-\x1F\x7F]/', '', $adPassword) == $adPassword)
{
	// Create the message object from our Messaging class
	$message = new Message();

	// Pass the details to our request reset function in our messaging class
	if ($message->request_reset_with_ad($_POST['ldapUsername'], $ldapPassword, $_POST['adUsername'], $adPassword))
	{
		// Request was successful
		echo "1";
		exit;
	}
	else
	{
		// Request failed, most likely due to input validation.
		echo "2";
		exit;
	}
}
else
{
	// Input validation failed.
        echo "2";
        exit;

}
?>
