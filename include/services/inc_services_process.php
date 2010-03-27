<?php
/*
	include/services/inc_services_details.php

	Provides forms and functions for managing basic service details.
*/

// include required functions
require("../include/accounts/inc_charts.php");




/*
	service_form_details_process()

	Processes data from the services form page.
*/
function service_form_details_process()
{
	log_debug("inc_services_process", "Executing service_form_details_process()");

	
	/*
		Fetch all form data
	*/


	// get the ID for an edit
	if ($_POST["id_service"])
	{
		$id	= @security_form_input_predefined("int", "id_service", 1, "");
		$mode	= "edit";
	}
	else
	{
		$id	= NULL;
		$mode	= "add";
	}
	

	// general details
	$data["name_service"]		= @security_form_input_predefined("any", "name_service", 1, "");
	$data["chartid"]		= @security_form_input_predefined("int", "chartid", 1, "");
	$data["description"]		= @security_form_input_predefined("any", "description", 0, "");

	// fetch information for all tax checkboxes from form
	$sql_tax_obj		= New sql_query;
	$sql_tax_obj->string	= "SELECT id FROM account_taxes";
	$sql_tax_obj->execute();

	if ($sql_tax_obj->num_rows())
	{
		$sql_tax_obj->fetch_array();

		foreach ($sql_tax_obj->data as $data_tax)
		{
			$data["tax_". $data_tax["id"] ]	= @security_form_input_predefined("any", "tax_". $data_tax["id"], 0, "");
		}

	} // end of loop through taxes



	// are we editing an existing service or adding a new one?
	if ($id)
	{
		$mode = "edit";

		// make sure that the service actually exists
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM services WHERE id='$id'";
		$sql_obj->execute();

		if (!$sql_obj->num_rows())
		{
			$_SESSION["error"]["message"][] = "The service you have attempted to edit - $id - does not exist in this system.";
		}
	}
	else
	{
		$mode = "add";
		
		// only fetch the type ID when adding new services
		$data["typeid"]	= @security_form_input_predefined("int", "typeid", 1, "");
	}


	//// ERROR CHECKING ///////////////////////


	// make sure we don't choose a service name that is already in use
	if ($data["code_service"])
	{
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM services WHERE name_service='". $data["name_service"] ."'";
		
		if ($id)
			$sql_obj->string .= " AND id!='$id'";
	
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$_SESSION["error"]["message"][]			= "This service name is already in use by another service. Please choose a unique name.";
			$_SESSION["error"]["name_service-error"]	= 1;
		}
	}


	/// if there was an error, go back to the entry page
	if ($_SESSION["error"]["message"])
	{	
		$_SESSION["error"]["form"]["service_view"] = "failed";

		if ($mode == "add")
		{
			header("Location: ../index.php?page=services/add.php");
		}
		else
		{
			header("Location: ../index.php?page=services/view.php&id=$id");
		}
		
		exit(0);
	}
	else
	{
		// start transaction
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		if ($mode == "add")
		{
			/*
				Create new service
			*/
			
			$sql_obj->string	= "INSERT INTO services (name_service, typeid) VALUES ('".$data["name_service"]."', '". $data["typeid"] ."')";
			$sql_obj->execute();

			$id = $sql_obj->fetch_insert_id();
		}

		if ($id)
		{
			/*
				Update general service details
			*/
			
			$sql_obj->string = "UPDATE services SET "
						."name_service='". $data["name_service"] ."', "
						."chartid='". $data["chartid"] ."', "
						."description='". $data["description"] ."' "
						."WHERE id='$id' LIMIT 1";
			
			$sql_obj->execute();


			/*
				Update service tax options
			*/

			// delete existing tax options for this service (if any)
			$sql_obj->string	= "DELETE FROM services_taxes WHERE serviceid='$id'";
			$sql_obj->execute();

			// fetch list of tax IDs
			$sql_tax_obj		= New sql_query;
			$sql_tax_obj->string	= "SELECT id FROM account_taxes";
			$sql_tax_obj->execute();

			if ($sql_tax_obj->num_rows())
			{
				$sql_tax_obj->fetch_array();

				foreach ($sql_tax_obj->data as $data_tax)
				{
					if ($data["tax_". $data_tax["id"] ] == "on")
					{
						// enable selected tax options
						$sql_obj->string	= "INSERT INTO services_taxes (serviceid, taxid) VALUES ('$id', '". $data_tax["id"] ."')";
						$sql_obj->execute();
					}
				}

			} // end of loop through taxes



			/*
				Update Journal
			*/

			if ($mode == "add")
			{
				journal_quickadd_event("services", $id, "Service successfully created");
			}
			else
			{
				journal_quickadd_event("services", $id, "Service successfully updated");
			}



			/*
				Success! :-)
			*/
			if (error_check())
			{
				$sql_obj->trans_rollback();

				log_write("error", "process", "An error occured whilst attempting to update the service - no changes have been made.");

				if ($mode == "add")
				{
					header("Location: ../index.php?page=services/add.php");
				}
				else
				{
					header("Location: ../index.php?page=services/view.php&id=$id");
				}
			}
			else
			{
				$sql_obj->trans_commit();

				if ($mode == "add")
				{
					log_write("notification", "process", "Service successfully created.");
					
					// take user to plan configuration page
					header("Location: ../index.php?page=services/plan.php&id=$id");
					exit(0);
				}
				else
				{
					log_write("notification", "process", "Service successfully updated.");

					// display updated details
					header("Location: ../index.php?page=services/view.php&id=$id");
					exit(0);
				}
			}

		
			
		} // end if ID

	} // end if passed tests


} // end if service_form_details_process



/*
	service_form_plan_process()

	Processes data from the services form page.
*/
function service_form_plan_process()
{
	log_debug("inc_services_process", "Executing service_form_plan_process()");

	
	/*
		Fetch all form data
	*/


	$id				= @security_form_input_predefined("int", "id_service", 1, "");
	
	// general details
	$data["price"]			= @security_form_input_predefined("money", "price", 0, "");
	$data["billing_cycle"]		= @security_form_input_predefined("int", "billing_cycle", 1, "");
	$data["billing_mode"]		= @security_form_input_predefined("int", "billing_mode", 1, "");

	// needed to handle errors, but not used
	$data["name_service"]		= @security_form_input_predefined("any", "name_service", 0, "");


	// make sure that the service actually exists
	$sql_plan_obj		= New sql_query;
	$sql_plan_obj->string	 = "SELECT services.typeid, service_types.name FROM services LEFT JOIN service_types ON service_types.id = services.typeid WHERE services.id='$id' LIMIT 1";
	$sql_plan_obj->execute();

	if (!$sql_plan_obj->num_rows())
	{
		$_SESSION["error"]["message"][] = "The service you have attempted to edit - $id - does not exist in this system.";
	}
	else
	{
		$sql_plan_obj->fetch_array();
	}


	// fetch fields depending on the service type
	switch ($sql_plan_obj->data[0]["name"])
	{
		case "generic_with_usage":
			$data["units"]			= @security_form_input_predefined("any", "units", 1, "");
			$data["included_units"]		= @security_form_input_predefined("int", "included_units", 0, "");
			$data["price_extraunits"]	= @security_form_input_predefined("money", "price_extraunits", 0, "");
			$data["usage_mode"]		= @security_form_input_predefined("int", "usage_mode", 1, "");

			$data["alert_80pc"]		= @security_form_input_predefined("any", "alert_80pc", 0, "");
			$data["alert_100pc"]		= @security_form_input_predefined("any", "alert_100pc", 0, "");
			$data["alert_extraunits"]	= @security_form_input_predefined("any", "alert_extraunits", 0, "");
		break;


		case "licenses":
			$data["units"]			= @security_form_input_predefined("any", "units", 1, "");
			$data["included_units"]		= @security_form_input_predefined("int", "included_units", 0, "");
			$data["price_extraunits"]	= @security_form_input_predefined("money", "price_extraunits", 0, "");
		break;

		case "time":
		case "data_traffic":
			$data["units"]			= @security_form_input_predefined("int", "units", 1, "");
			$data["included_units"]		= @security_form_input_predefined("int", "included_units", 0, "");
			$data["price_extraunits"]	= @security_form_input_predefined("money", "price_extraunits", 0, "");
			
			// force data usage/time to be incrementing
			$data["usage_mode"]		= sql_get_singlevalue("SELECT id as value FROM service_usage_modes WHERE name='incrementing' LIMIT 1");

			$data["alert_80pc"]		= @security_form_input_predefined("any", "alert_80pc", 0, "");
			$data["alert_100pc"]		= @security_form_input_predefined("any", "alert_100pc", 0, "");
			$data["alert_extraunits"]	= @security_form_input_predefined("any", "alert_extraunits", 0, "");
		break;

		case "phone_services":
			$data["id_rate_table"]		= @security_form_input_predefined("int", "id_rate_table", 1, "");
		break;
	}


	// convert checkbox input
	if ($data["alert_80pc"])
		$data["alert_80pc"] = 1;
	
	if ($data["alert_100pc"])
		$data["alert_100pc"] = 1;




	//// ERROR CHECKING ///////////////////////


	/// if there was an error, go back to the entry page
	if ($_SESSION["error"]["message"])
	{	
		$_SESSION["error"]["form"]["service_plan"] = "failed";
		header("Location: ../index.php?page=services/plan.php&id=$id");
		
		exit(0);
	}
	else
	{
		/*
			Begin Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			Update plan details
		*/
			
		$sql_obj = New sql_query;		

		switch ($sql_plan_obj->data[0]["name"])
		{
			case "time":
			case "data_traffic":

				$sql_obj->string = "UPDATE services SET "
						."price='". $data["price"] ."', "
						."units='". $data["units"] ."', "
						."price_extraunits='". $data["price_extraunits"] ."', "
						."included_units='". $data["included_units"] ."', "
						."billing_cycle='". $data["billing_cycle"] ."', "
						."billing_mode='". $data["billing_mode"] ."', "
						."usage_mode='". $data["usage_mode"] ."', "
						."alert_80pc='". $data["alert_80pc"] ."', "
						."alert_100pc='". $data["alert_100pc"] ."', "
						."alert_extraunits='". $data["alert_extraunits"] ."' "
						."WHERE id='$id'";
	
				
			break;
			
			case "generic_with_usage":

				$sql_obj->string = "UPDATE services SET "
						."price='". $data["price"] ."', "
						."units='". $data["units"] ."', "
						."price_extraunits='". $data["price_extraunits"] ."', "
						."included_units='". $data["included_units"] ."', "
						."billing_cycle='". $data["billing_cycle"] ."', "
						."billing_mode='". $data["billing_mode"] ."', "
						."usage_mode='". $data["usage_mode"] ."', "
						."alert_80pc='". $data["alert_80pc"] ."', "
						."alert_100pc='". $data["alert_100pc"] ."', "
						."alert_extraunits='". $data["alert_extraunits"] ."' "
						."WHERE id='$id'";
	
			break;
			

			case "licenses":
			
				$sql_obj->string = "UPDATE services SET "
						."price='". $data["price"] ."', "
						."units='". $data["units"] ."', "
						."price_extraunits='". $data["price_extraunits"] ."', "
						."included_units='". $data["included_units"] ."', "
						."billing_cycle='". $data["billing_cycle"] ."', "
						."billing_mode='". $data["billing_mode"] ."' "
						."WHERE id='$id'";
			break;
			

			case "phone_services":

				$sql_obj->string = "UPDATE services SET "
						."price='". $data["price"] ."', "
						."billing_cycle='". $data["billing_cycle"] ."', "
						."billing_mode='". $data["billing_mode"] ."', "
						."id_rate_table='". $data["id_rate_table"] ."' "
						."WHERE id='$id'";
			break;


			case "generic_no_usage":
			case "bundle":
			default:

				$sql_obj->string = "UPDATE services SET "
						."price='". $data["price"] ."', "
						."billing_cycle='". $data["billing_cycle"] ."', "
						."billing_mode='". $data["billing_mode"] ."' "
						."WHERE id='$id'";

			break;
		}

		$sql_obj->execute();



		/*
			Update the Journal
		*/

		journal_quickadd_event("services", $id, "Service plan configuration changed");

	

		/*
			Commit
		*/
		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "process", "An error occured whilst attempting to update service plan information. No changes have been made.");
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("notification", "process", "Service successfully update.");
		}

		// display updated details
		header("Location: ../index.php?page=services/plan.php&id=$id");
		exit(0);

	} // end if passed tests


} // end if service_form_plan_process






/*
	service_form_delete_process($id)

	Process the delete form to delete the requested service.

*/
function service_form_delete_process()
{
	log_debug("inc_services_process", "Executing service_form_delete_process()");

	
	/*
		Fetch all form data
	*/


	// get form data
	$id				= @security_form_input_predefined("int", "id_service", 1, "");
	$data["delete_confirm"]		= @security_form_input_predefined("any", "delete_confirm", 1, "You must confirm the deletion");



	//// ERROR CHECKING ///////////////////////
	
	// make sure the service actually exists
	$sql_obj		= New sql_query;
	$sql_obj->string	= "SELECT id FROM services WHERE id='$id' LIMIT 1";
	$sql_obj->execute();

	if (!$sql_obj->num_rows())
	{
		log_write("error", "process", "The service you have attempted to edit - $id - does not exist in this system.");
	}


	// make sure the service is not active for any customers
	$sql_obj		= New sql_query;
	$sql_obj->string	= "SELECT id FROM services_customers WHERE serviceid='$id' LIMIT 1";
	$sql_obj->execute();
		
	if ($sql_obj->num_rows())
	{
		log_write("error", "process", "Service is active for customers and can therefore not be deleted.");
	}


	/// if there was an error, go back to the entry page
	if ($_SESSION["error"]["message"])
	{	
		$_SESSION["error"]["form"]["service_delete"] = "failed";
		header("Location: ../index.php?page=services/delete.php&id=$id");
		exit(0);
	}
	else
	{
		/*
			Begin Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			Delete the service data
		*/
		$sql_obj->string	= "DELETE FROM services WHERE id='$id' LIMIT 1";
		$sql_obj->execute();


		/*
			Delete the service taxes
		*/

		$sql_obj->string	= "DELETE FROM services_taxes WHERE serviceid='$id'";
		$sql_obj->execute();


		/*
			Delete service journal data
		*/
		journal_delete_entire("services", $id);



		/*
			Commit
		*/
		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "process", "An error occured whilst attempting to delete the transaction. No changes have been made.");

			header("Location: ../index.php?page=services/view.php&id=$id");
			exit(0);
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("notification", "process", "Service successfully deleted");

			header("Location: ../index.php?page=services/services.php");
			exit(0);
		}
			
	} // end if passed tests


} // end if service_form_delete_process





?>
