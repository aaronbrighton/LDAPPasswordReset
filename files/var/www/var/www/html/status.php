<?php
////////////////////////////////////////////////////
// status.php                                     //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-09-07                      //
//                                                //
// This page receives AJAX request from           //
// update.php or ad.html and checks with the  //
// messaging class to see if an update has been   //
// provided to password update status.            //
////////////////////////////////////////////////////

// Include the messaging class
require_once('../includes/Message.class.php');

// Validate the input, a reset key in the case of legacy, or a username in the case of ad.
if (((ctype_alnum($_POST['key']) && strlen($_POST['key']) == 64) || (ctype_alnum($_POST['key']) && strlen($_POST['key']) >= 1 && strlen($_POST['key']) < 32)))
{
	// Create message object from messaging class.
	$message = new Message();

	// Return the response from the update_status function.
	echo $message->request_password_update_status($_POST['key']);
	exit;
}
else
{
	// Input is not valid.
	echo 0;
	exit;
}
?>
