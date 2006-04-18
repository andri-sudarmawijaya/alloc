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

function show_person_list($template) {
  global $db, $TPL, $tf;

  $TPL["person_buttons"] = "
          <input type=\"submit\" name=\"person_save\" value=\"Save\">
          <input type=\"submit\" name=\"person_delete\" value=\"Delete\">";
  $tfID = $tf->get_id();

  if ($tfID) {
    $query = sprintf("SELECT * from tfPerson WHERE tfID=%d", $tfID);
    $db->query($query);
    while ($db->next_record()) {
      $tfPerson = new tfPerson;
      $tfPerson->read_db_record($db);
      $tfPerson->set_tpl_values(DST_HTML_ATTRIBUTE, "person_");
      $person = $tfPerson->get_foreign_object("person");
      $TPL["person_username"] = $person->get_value("username");
      include_template($template);
    }
  }
}

function show_new_person($template) {
  global $TPL;
  $TPL["person_buttons"] = "
          <input type=\"submit\" name=\"person_save\" value=\"Add\">";
  $tfPerson = new tfPerson;
  $tfPerson->set_tpl_values(DST_HTML_ATTRIBUTE, "person_");
  include_template($template);
}

function show_person_options() {
  global $person_array, $TPL;
  echo get_options_from_array($person_array, $TPL["person_personID"]);
}

$db = new db_alloc;
$tf = new tf;

$db->query("SELECT * FROM person ORDER BY username");
$person_array = get_array_from_db($db, "personID", "username");


if ($tfID) {
  $tf->set_id($tfID);
  $tf->select();
}



if ($save) {
  $tf->read_globals();

  if ($tf->get_value("tfName") == "") {
    $TPL["message"][] = "You must enter a name.";
  } else {

    $db = new db_alloc;
    $q = sprintf("SELECT count(*) AS tally FROM tf WHERE tfName = '%s'",db_esc($tf->get_value("tfName")));
    $db->query($q);
    $db->next_record();

    if ($db->f("tally")) {
      $TPL["message"][] = "That TF name is taken, please choose another.";
    } else {
      $tf->save();
      $TPL["message_good"][] = "Your TF has been saved.";
    }
  
  }


} else {

  if ($delete) {
    $tf->delete();
    header("location:".$TPL["url_alloc_tfList"]);
    exit();
  }
}

if ($person_save || $person_delete) {

  $tfPerson = new tfPerson;
  $tfPerson->read_globals();
  $tfPerson->read_globals("person_");
  if (!$_POST["person_personID"]) {
    $TPL["message"][] = "Please select a person from the dropdown list." ;
  } else if ($person_save) {
    $tfPerson->save();
    $TPL["message_good"][] = "Person added to TF.";
  } else if ($person_delete) {
    $tfPerson->delete();
  }
}

if (!isset($tfID)) {
  $tf->set_value("tfBalance", 0);
}

$tf->set_tpl_values();


$TPL["tfModifiedTime"] = get_display_date($tf->get_value("tfModifiedTime"));
if ($tf->get_value("tfModifiedUser")) {
  $db->query("select username from person where personID=".$tf->get_value("tfModifiedUser"));
  $db->next_record();
  $TPL["tfModifiedUser"] = $db->f("username");
}

include_template("templates/tfM.tpl");

page_close();



?>
