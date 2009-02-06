<?php
/*
	misc.php
	
	Various one-off functions
*/



/*
	CONFIGURATION FUNCTIONS 

	Configuration functions perform queries against the config DB with the structure of:
	
	CREATE TABLE `config` (
	  `name` varchar(255) NOT NULL default '',
	  `value` varchar(255) NOT NULL default '',
	  PRIMARY KEY  (`name`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;
*/


/*
	config_generate_uniqueid()

	This function will generate a unique ID by looking up the current value of the supplied
	name from the config database, and will then work out an avaliable value.

	Once a suitable value has been determined, the code will return it and then update the 
	value in the config table.

	This function is ideal for when you need a field to be auto-incremented, but still providing
	the user the ability to over-write it with their own value.

	Values
	config_name	Name of the configuration field to fetch the value from
	check_sql	(optional) SQL query to check for current usage of this ID. Note that the VALUE keyword will
			be replaced by the code ID.
				eg: "SELECT id FROM mytable WHERE codevalue='VALUE'

	Returns
	#	unique ID to be used.
*/
function config_generate_uniqueid($config_name, $check_sql)
{
	log_debug("inc_misc", "Executing config_generate_uniqueid($config_name)");
	
	$config_name = strtoupper($config_name);
	
	$returnvalue	= 0;
	$uniqueid	= 0;
	

	// fetch the starting ID from the config DB
	$uniqueid	= sql_get_singlevalue("SELECT value FROM config WHERE name='$config_name'");

	if (!$uniqueid)
		die("Unable to fetch $config_name value from config database");


	if ($check_sql)
	{
		// we will use the supplied SQL query to make sure this value is not currently used
		while ($returnvalue == 0)
		{
			$sql_obj		= New sql_query;
			$sql_obj->string	= str_replace("VALUE", $uniqueid, $check_sql);
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				// the ID has already been used, try incrementing
				$uniqueid++;
			}
			else
			{
				// found an avaliable ID
				$returnvalue = $uniqueid;
			}
		}
	}
	else
	{
		// conducting no DB checks.
		$returnvalue = $uniqueid;
	}
	

	// update the DB with the new value + 1
	$uniqueid++;
				
	$sql_obj		= New sql_query;
	$sql_obj->string	= "UPDATE config SET value='$uniqueid' WHERE name='$config_name'";
	$sql_obj->execute();


	return $returnvalue;
}






/* FORMATTING/DISPLAY FUNCTIONS */


/*
	format_text_display($text)

	Formats a block of text from a database into a form suitable for display as HTML by
	replacing any \n with <br> statments

	Returns the processed text.
*/
function format_text_display($text)
{
	log_debug("misc", "Executing format_text_display($text)");
	
	// replace unrenderable html tags of > and <
	$text = str_replace(">", "&gt;", $text);
	$text = str_replace("<", "&lt;", $text);
	
	// fix newlines last
	$text = str_replace("\n", "<br>", $text);

	return $text;
}


/*
	format_size_human($bytes)

	Returns a human readable size.
*/
function format_size_human($bytes)
{
	log_debug("misc", "Executing format_size_human($bytes)");

	if(!$bytes)
	{
		// unknown - most likely the program hasn't called one one of the fetch_information_by_* functions first.
		log_debug("file_base", "Error: Unable to determine file size - no value provided");	
		return "unknown size";
	}
	else
	{
		$file_size_types = array(" Bytes", " KB", " MB", " GB", " TB");
		return round($bytes/pow(1024, ($i = floor(log($bytes, 1024)))), 2) . $file_size_types[$i];
	}
}


/*
	format_msgbox($type, $text)

	Creates a coloured message box, based on the type.

	Supported types:
	important
	info
*/
function format_msgbox($type, $text)
{
	log_debug("misc", "Executing format_msgbox($type, text)");

	print "<table width=\"100%\" class=\"table_highlight_$type\">";
	print "<tr>";
		print "<td>";
		print "$text";
		print "</td>";
	print "</tr>";
	print "</table>";
}


/*
	format_money($amount)

	Formats the provided floating integer and adds the default currency and applies
	rounding to it to make a number suitable for display.
*/
function format_money($amount)
{
	log_debug("misc", "Executing format_money($amount)");

	// 2 decimal places
	$amount = sprintf("%0.2f", $amount);

	// formatting for readability
	$amount = number_format($amount, "2", ".", ",");


	// add currency & return
	$result = sql_get_singlevalue("SELECT value FROM config WHERE name='CURRENCY_DEFAULT_SYMBOL'") . "$amount";

	return $result;
}



/* TIME FUNCTION */


/*
	time_date_to_timestamp($date)

	returns a timestamp calculated from the provided YYYY-MM-DD date
*/
function time_date_to_timestamp($date)
{
	log_debug("misc", "Executing time_date_to_timestamp($date)");
	
	$date_a = split("-", $date);

	return mktime(0, 0, 0, $date_a[1], $date_a[2] , $date_a[0]);
}


/*
	time_format_hourmins($seconds)
	
	returns the number of hours, and the number of minutes in the form of H:MM
*/
function time_format_hourmins($seconds)
{
	log_debug("misc", "Executing time_format_hourmins($seconds)");
	
 	$minutes	= $seconds / 60;
	$hours		= sprintf("%d",$minutes / 60);

	$excess_minutes = sprintf("%02d", $minutes - ($hours * 60));

	return "$hours:$excess_minutes";
}


/*
	time_format_humandate

	Provides a date formated in the user's perferred way. If no date is provided, will return the current date.

	Values
	date		Format YYYY-MM-DD (optional)

	Returns
	string		Date in human-readable format.
*/
function time_format_humandate($date = NULL)
{
	log_debug("misc", "Executing time_format_humandate($date)");

	if ($date)
	{
		// convert date to timestamp so we can work with it
		$timestamp = time_date_to_timestamp($date);
	}
	else
	{
		// no date supplied - generate current timestamp
		$timestamp = mktime();
	}


	if ($_SESSION["user"]["dateformat"])
	{
		// fetch from user preferences
		$format = $_SESSION["user"]["dateformat"];
	}
	else
	{
		// user hasn't chosen a default time format yet - use the system
		// default
		$format = sql_get_singlevalue("SELECT value FROM config WHERE name='DATEFORMAT' LIMIT 1");
	}


	// convert to human readable format
	switch ($format)
	{
		case "mm-dd-yyyy":
			return date("m-d-Y", $timestamp);
		break;

		case "dd-mm-yyyy":
			return date("d-m-Y", $timestamp);
		break;
		
		case "yyyy-mm-dd":
		default:
			return date("Y-m-d", $timestamp);
		break;
	}
}


/*
	time_calculate_weekstart($date_selected_weekofyear, $date_selected_year)

	returns the start date of the week in format YYYY-MM-DD
	
*/
function time_calculate_weekstart($date_selected_weekofyear, $date_selected_year)
{
	log_debug("misc", "Executing time_calculate_weekstart($date_selected_weekofyear, $date_selected_year)");
	
	// work out the start date of the current week
	$date_curr_weekofyear	= date("W");
	$date_curr_year		= date("Y");
	$date_curr_start	= mktime(0, 0, 0, date("m"), ((date("d") - date("w")) + 1) , $date_curr_year);

	// work out the difference in the number of weeks desired
	$date_selected_weekdiff	= ($date_curr_year - $date_selected_year) * 52;
	$date_selected_weekdiff += ($date_curr_weekofyear - $date_selected_weekofyear);

	// work out the difference in seconds (1 week == 604800 seconds)
	$date_selected_seconddiff = $date_selected_weekdiff * 604800;

	// timestamp of the first day in the week.
	$date_selected_start = $date_curr_start - $date_selected_seconddiff;

	return date("Y-m-d", $date_selected_start);
}


/*
	time_calculate_daysofweek($date_selected_start_ts)

	Passing YYYY-MM-DD of the first day of the week will
	return an array containing date of each day in YYYY-MM-DD format
*/
function time_calculate_daysofweek($date_selected_start)
{
	log_debug("misc", "Executing time_calculate_daysofweek($date_selected_start)");

	$days = array();

	// get the start day, month + year
	$dates = split("-", $date_selected_start);
	
	// get the value for all the days
	for ($i=0; $i < 7; $i++)
	{
		$days[$i] = date("Y-m-d", mktime(0,0,0,$dates[1], ($dates[2] + $i), $dates[0]));
	}

	return $days;
}


/*
	time_calculate_weeknum($date)

	Calculates what week the supplied date is in. If not date is supplied, then
	returns the current week.
*/
function time_calculate_weeknum($date = NULL)
{
	log_debug("misc", "Executing time_calculate_weeknum($date)");

	if (!$date)
	{
		$date = date("Y-m-d");
	}


	/*
		Use the SQL database to get the week number based on ISO 8601
		selection criteria.

		Note that we intentionally use SQL instead of the php date("W") function, since
		in testing the date("W") function has been found to beinconsistant on different systems.

		TODO: Investigate further what is wrong with PHP date("W")
	*/
	return sql_get_singlevalue("SELECT WEEK('$date',1) as value");
}

	




/* HELP FUNCTIONS */

/*
	helplink( id )
	returns an html string, including a help icon, with a hyperlink to the help page specified by id.
*/

function helplink($id)
{
	return "<a href=\"help/viewer.php?id=$id\" target=\"new\" title=\"Click here for a popup help box\"><img src=\"images/icons/help.gif\" alt=\"?\" border=\"0\"></a>";
}




/* LOGGING FUNCTIONS */


/*
	log_error_render()

	Displays any error logs
*/
function log_error_render()
{
        if ($_SESSION["error"]["message"])
        {
		print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\">";
                print "<tr><td bgcolor=\"#ffeda4\" style=\"border: 1px dashed #dc6d00; padding: 3px;\">";
                print "<p><b>Error:</b><br><br>";

		foreach ($_SESSION["error"]["message"] as $errormsg)
		{
			print "$errormsg<br>";
		}
		
		print "</p>";
                print "</td></tr>";
		print "</table>";
	}
}


/*
	log_notification_render()

	Displays any notification messages, provided that there are no error messages as well
*/
function log_notification_render()
{
        if ($_SESSION["notification"]["message"] && !$_SESSION["error"]["message"])
        {
		print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\">";
                print "<tr><td bgcolor=\"#c7e8ed\" style=\"border: 1px dashed #374893; padding: 3px;\">";
                print "<p><b>Notification:</b><br><br>";
		
		foreach ($_SESSION["notification"]["message"] as $notificationmsg)
		{
			print "$notificationmsg<br>";
		}

		print "</p>";
                print "</td></tr>";
		print "</table>";
        }
}




/*
	log_debug_render()

	Displays the debugging log
*/
function log_debug_render()
{
	log_debug("inc_misc", "Executing log_debug_render()");


	print "<p><b>Debug Output:</b></p>";
	print "<p><i>Please be aware that debugging will cause some impact on performance and should be turned off in production.</i></p>";
	
	
	// table header
	print "<table class=\"table_content\" width=\"100%\">";
	
	print "<tr class=\"header\">";
		print "<td nowrap><b>Time</b></td>";
		print "<td nowrap><b>Memory</b></td>";
		print "<td nowrap><b>Type</b></td>";
		print "<td nowrap><b>Category</b></td>";
		print "<td><b>Message/Content</b></td>";
	print "</tr>";


	// content
	foreach ($_SESSION["user"]["log_debug"] as $log_record)
	{
		switch ($log_record["type"])
		{
			case "error":
				print "<tr bgcolor=\"#ff5a00\">";
			break;

			case "warning":
				print "<tr bgcolor=\"#ffeb68\">";
			break;

			case "sql":
				print "<tr bgcolor=\"#7bbfff\">";
			break;

			default:
				print "<tr>";
			break;
		}
		
		print "<td nowrap>". $log_record["time"] ."</td>";
		print "<td nowrap>". format_size_human($log_record["memory"]) ."</td>";
		print "<td nowrap>". $log_record["type"] ."</td>";
		print "<td nowrap>". $log_record["category"] ."</td>";
		print "<td>". $log_record["content"] ."</td>";
		print "</tr>";
	}

	print "</table>";
}


/*
	FILESYSTEM FUNCTIONS
*/


/*
	file_generate_name

	Generates a unique name based on the base name provided and touches it to reserve it.

	Fields
	basename		Base of the filename
	extension		Extension for the file (if any)

	Returns
	string			Name for an avaliable file
*/
function file_generate_name($basename, $extension = NULL)
{
	log_debug("inc_misc", "Executing file_generate_name($basename, $extension)");
	

	if ($extension)
	{
		$extension = ".$extension";
	}

	// calculate a temporary filename
	$uniqueid = 0;
	while ($complete == "")
	{
		$filename = $basename ."_". mktime() ."_$uniqueid" . $extension;

		if (file_exists($filename))
		{
			// the filename has already been used, try incrementing
			$uniqueid++;
		}
		else
		{
			// found an avaliable ID
			touch($filename);
			return $filename;
		}
	}
}


?>
