<?php

/*
 *
 * Copyright 2006, Alex Lance, Clancy Malcolm, Cybersource Pty. Ltd.
 * 
 * This file is part of allocPSA <info@cyber.com.au>.
 * 
 * allocPSA is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 * 
 * allocPSA is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * allocPSA; if not, write to the Free Software Foundation, Inc., 51 Franklin
 * St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 */

require_once("alloc.inc");

$person = new person;

if (isset($personID)) {
  $person->set_id($personID);
} else {
  $person->set_id($current_user->get_id());
}
$person->select();

if (!$current_user->is_employee() || !$person->is_employee()) {
  die("You do not have permission to access absence form.");
}

function EMailForm() {
  global $mailToPerson, $absence, $TPL, $db;
  $absence->read_globals();
  $absence->read_globals("absence_");

  // Set subject field
  $subject = "Alloc - Away notices";

  // Fill the message.
  $person = $absence->get_foreign_object("person");
  $msg = $person->get_value("username")." will be away on ".$absence->get_value("absenceType")." leave from ".$absence->get_value("dateFrom")." to ".$absence->get_value("dateTo")
    .".  Emergency contact details are as follows:  \n";
  $msg.= $absence->get_value("contactDetails");

  // Set to TO field.
  $toPerson = new person;
  $toPerson->set_id($mailToPerson);
  $toPerson->select();
  $to = $toPerson->get_value("emailAddress");

  // Set FROM field
  $header = "From: ".ALLOC_DEFAULT_FROM_ADDRESS;
  return mail($to, $subject, $msg, $header);
}

$absence = new absence;
$db = new db_alloc;

if (isset($save)) {
  // Saving a record
  $absence->read_globals();
  $absence->read_globals("absence_");
  $success = $absence->save();


  if ($success) {
    // save
    $url = $TPL["url_alloc_person"]."personID=".$personID;
  }
  header("Location: $url");
  page_close();
  exit();
} else if (isset($delete)) {
  // Deleting a record
  $absence->read_globals();
  $absence->delete();
  header("location: ".$TPL["url_alloc_person"]."personID=".$personID);
} else if (isset($mailTo)) {
  EMailForm();
} else if (isset($absenceID)) {
  // Displaying a record
  $absence->set_id($absenceID);
  $absence->select();
} else {
  // create a new record
  $absence->read_globals();
  $absence->read_globals("absence_");
  $absence->set_value("personID", $person->get_id());
}

$absence->set_tpl_values(DST_HTML_ATTRIBUTE, "absence_");

  // Set up the person name;
# $person = $absence->get_foreign_object("person");


$TPL["personName"] = $person->get_value("username");

  // Set up the options for a list of user.
$query = sprintf("SELECT * FROM person ORDER by username");
$db->query($query);
$person_array = get_array_from_db($db, "personID", "username");
$TPL["person_options"] = get_options_from_array($person_array, $personID);

  // Set up the options for the absence type.
$absenceType_array = array("conference"=>"conference", "holiday"=>"holiday", "sick"=>"sick");
$TPL["absenceType_options"] = get_options_from_array($absenceType_array, $absence->get_value("absenceType"));

include_template("templates/absenceFormM.tpl");

page_close();



?>
