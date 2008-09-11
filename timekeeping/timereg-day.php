<?php
/*
	timekeeping/timereg-day.php
	
	access: time_keeping

	Displays all the details of the selected day, and allows additions.
*/

if (user_permissions_get('timekeeping'))
{
	$date = $_GET["date"];
	
	// nav bar options.
	$_SESSION["nav"]["active"]	= 1;
	
	$_SESSION["nav"]["title"][]	= "Weekview";
	$_SESSION["nav"]["query"][]	= "page=timekeeping/timereg.php&year=". $_SESSION["timereg"]["year"] ."&weekofyear=". $_SESSION["timereg"]["weekofyear"]."";

	$_SESSION["nav"]["title"][]	= "Day View";
	$_SESSION["nav"]["query"][]	= "page=timekeeping/timereg-day.php&date=$date";
	$_SESSION["nav"]["current"]	= "page=timekeeping/timereg-day.php&date=$date";


	function page_render()
	{
		$editid		= security_script_input('/^[0-9]*$/', $_GET["editid"]);
		$date		= security_script_input('/^[0-9-]*$/', $_GET["date"]);
		$employeeid	= user_information("employeeid");

		$date_split = split("-", $date);

		/*
			Title + Summary
		*/
		print "<h3>TIME REGISTRATION - ". date("l d F Y", mktime(0,0,0, $date_split[1], $date_split[2], $date_split[0])) ."</h3><br>";


		// links
		$date_previous	= mktime(0,0,0, $date_split[1], ($date_split[2] - 1), $date_split[0]);
		$date_previous	= date("Y-m-d", $date_previous);
		
		$date_next	= mktime(0,0,0, $date_split[1], ($date_split[2] + 1), $date_split[0]);
		$date_next	= date("Y-m-d", $date_next);

		print "<p><b>";
		print "<a href=\"index.php?page=timekeeping/timereg-day.php&date=$date_previous\">Previous Day</a> || ";
		print "<a href=\"index.php?page=timekeeping/timereg-day.php&date=$date_next\">Next Day</a>";
		print "</b></p><br>";
		

	
		/*
			DRAW DAY TABLE

			We need to display a table showing all time booked for the currently
			selected day.
		*/

		// establish a new table object
		$timereg_table = New table;

		$timereg_table->language	= $_SESSION["user"]["lang"];
		$timereg_table->tablename	= "timereg_table";
		$timereg_table->sql_table	= "timereg";

		// define all the columns and structure
		$timereg_table->add_column("standard", "code_project", "");
		$timereg_table->add_column("standard", "name_project", "");
		$timereg_table->add_column("hourmins", "time_booked", "");
		$timereg_table->add_column("standard", "description", "");

		// defaults
		$timereg_table->columns		= array("code_project", "name_project", "description", "time_booked");
		$timereg_table->columns_order	= array("code_project");

		// create totals
		$timereg_table->total_columns	= array("time_booked");
		

		// fetch data from both the projects and timereg table with a custom query
		$timereg_table->sql_query = "SELECT timereg.id, timereg.time_booked, timereg.description, projects.code_project, projects.name_project FROM timereg LEFT JOIN projects ON timereg.projectid = projects.id WHERE timereg.employeeid='$employeeid' AND timereg.date='$date'";	
		$timereg_table->load_data_sql();

		if (!$timereg_table->data_num_rows)
		{
			print "<p><b>There is currently no time registered to this day.</b></p>";
		}
		else
		{
			$structure = NULL;
			$structure["editid"]["column"]	= "id";
			$structure["date"]["value"]	= "$date#form";
			$timereg_table->add_link("edit", "timekeeping/timereg-day.php", $structure);

			$timereg_table->render_table();
		}



		/*
			Input Form

			Allows the creation of a new entry for the day, or the adjustment of an existing one.
		*/
	
		print "<a name=\"form\"></a><br><br>";
		
		if ($editid)
		{
			print "<h3>ADJUST TIME RECORD:</h3>";
		}
		else
		{
			print "<h3>BOOK TIME:</h3>";
		}
		print "<br><br>";

		
		$form = New form_input;
		$form->formname = "timereg_day";
		$form->language = $_SESSION["user"]["lang"];
		
		$form->action = "timekeeping/timereg-day-process.php";
		$form->method = "post";
			
			
		// hidden stuff
		$structure = NULL;
		$structure["fieldname"] 	= "id_timereg";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$editid";
		$form->add_input($structure);
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "date";
		$structure["type"]		= "date";
		$structure["defaultvalue"]	= "$date";
		$structure["options"]["req"]	= "yes";
		$form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "time_booked";
		$structure["type"]		= "hourmins";
		$structure["options"]["req"]	= "yes";
		$form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "description";
		$structure["type"]		= "textarea";
		$structure["options"]["req"]	= "yes";
		$form->add_input($structure);
		$form->add_input($structure);

		// get data from DB and create project dropdown
		$structure = NULL;
		$structure["fieldname"] 	= "projectid";
		$structure["type"]		= "dropdown";
		$structure["options"]["req"]	= "yes";

		$mysql_string	= "SELECT id, code_project, name_project FROM `projects` ORDER BY code_project, name_project";
		$mysql_result	= mysql_query($mysql_string);
		$mysql_num_rows	= mysql_num_rows($mysql_result);

		while ($mysql_data = mysql_fetch_array($mysql_result))
		{
			$structure["values"][]					= $mysql_data["id"];
			$structure["translations"][ $mysql_data["id"] ]		= $mysql_data["code_project"] ." (". $mysql_data["name_project"] .")";
		}

				
		$form->add_input($structure);

					
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$form->add_input($structure);
		
		
		// define subforms
		$form->subforms["timereg_day"]		= array("projectid", "date", "time_booked", "description");
		$form->subforms["hidden"]		= array("id_timereg");
		$form->subforms["submit"]		= array("submit");

			
		$mysql_string	= "SELECT id FROM `timereg` WHERE id='$editid'";
		$mysql_result	= mysql_query($mysql_string);
		$mysql_num_rows	= mysql_num_rows($mysql_result);

		if ($mysql_num_rows)
		{
			// fetch the form data
			$form->sql_query = "SELECT * FROM `timereg` WHERE id='$editid' LIMIT 1";
			$form->load_data();
		}

		// display the form
		$form->render_form();




		/*
			Delete Form

			If the user is editing an option, offer a delete option.
		*/
	
		if ($editid)
		{
			print "<br><br>";
			print "<h3>DELETE TIME RECORD:</h3>";
			print "<br><br>";

			
			$form_del = New form_input;
			$form_del->formname = "timereg_delete";
			$form_del->language = $_SESSION["user"]["lang"];
			
			$form_del->action = "timekeeping/timereg-day-delete-process.php";
			$form_del->method = "post";
				
				
			// hidden stuff
			$structure = NULL;
			$structure["fieldname"] 	= "id_timereg";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= "$editid";
			$form_del->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "date";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= "$date";
			$form_del->add_input($structure);
			
			
			// general
			$structure = NULL;
			$structure["fieldname"] 	= "message";
			$structure["type"]		= "message";
			$structure["defaultvalue"]	= "If you no longer require this time entry, you can delete it using the button below";
			$form_del->add_input($structure);
			
			
			// submit section
			$structure = NULL;
			$structure["fieldname"] 	= "submit";
			$structure["type"]		= "submit";
			$structure["defaultvalue"]	= "Delete Time Entry";
			$form_del->add_input($structure);
			
			
			// define subforms
			$form_del->subforms["hidden"]		= array("id_timereg", "date");
			$form_del->subforms["timereg_delete"]	= array("message", "submit");

				
			$mysql_string	= "SELECT id FROM `timereg` WHERE id='$editid'";
			$mysql_result	= mysql_query($mysql_string);
			$mysql_num_rows	= mysql_num_rows($mysql_result);
			
			if ($mysql_num_rows)
			{
				// fetch the form data
				$form_del->sql_query = "SELECT id, date FROM `timereg` WHERE id='$editid' LIMIT 1";
				$form_del->load_data();
			}
			

			// display the form
			$form_del->render_form();
			
		}

		

	} // end page_render

} // end of if logged in
else
{
	error_render_noperms();
}

?>
