<?php
////////////////////////////////////////////////////
// Logging.class.php                              //
//                                                //
// Author: Aaron Brighton                         //
// Last Modified: 2015-08-24                      //
//                                                //
// This class creates a logging interface for the //
// application.  To assist in troubleshooting in  //
// the future.                                    //
////////////////////////////////////////////////////

class Logging
{
	// The function appends to the log.
	public function write_log($string)
	{
		$line = date('d/M/Y:Hi:s T').' '.$string."\n";
		$fp = fopen(LOG_FILE_PATH, 'a');
		fwrite($fp, $line, strlen($line)+1);
		fclose($fp);
		return true;
	}
}
?>
