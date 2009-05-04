<?php

/*
 * Copyright (C) 2006, 2007, 2008 Alex Lance, Clancy Malcolm, Cybersource
 * Pty. Ltd.
 * 
 * This file is part of the allocPSA application <info@cyber.com.au>.
 * 
 * allocPSA is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at
 * your option) any later version.
 * 
 * allocPSA is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public
 * License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with allocPSA. If not, see <http://www.gnu.org/licenses/>.
*/

require_once("../alloc.php");




  function show_attachments() {
    global $projectID;
    util_show_attachments("project",$projectID);
  }

  function list_attachments($template_name) {
    global $TPL, $projectID;

    if ($projectID) {
      $rows = get_attachments("project",$projectID);
      foreach ($rows as $row) {
        $TPL = array_merge($TPL,$row);
        include_template($template_name);
      }
    }
  }

  function show_timeSheet_list() {
    global $TPL, $projectID, $current_user, $project;

    if ($projectID) {

      $defaults = array("showHeader"=>true
                       ,"showProjectLink"=>true
                       ,"showAmount"=>true
                       ,"showAmountTotal"=>true
                       ,"showCustomerBilledDollars"=>true
                       ,"showCustomerBilledDollarsTotal"=>true
                       ,"showTransactionsPos"=>true
                       ,"showTransactionsNeg"=>true
                       ,"showDuration"=>true
                       ,"showPerson"=>true
                       ,"showDateFrom"=>true
                       ,"showDateTo"=>true
                       ,"showStatus"=>true
                       ,"projectID"=>$projectID
                       );

      // Limit to the owner's timesheets if necessary
      //This is to be corrected when the new permissions system is in place.
      //The full display should not appear to normal users.

      if (!$project->have_perm(PERM_READ_WRITE)) {
        $defaults["personID"] = $current_user->get_id();
        unset($defaults["showTransactionsPos"]);
        unset($defaults["showTransactionsNeg"]);
        unset($defaults["showCustomerBilledDollars"]);
        unset($defaults["showCustomerBilledDollarsTotal"]);
      }
      echo timeSheet::get_list($defaults);
    }
  }

  function show_transaction($template) {
    global $db, $TPL, $projectID, $current_user;

    $transaction = new transaction;

    if (isset($projectID) && $projectID) {
      if (have_entity_perm("transaction", PERM_READ, $current_user, false)) {
        $query = sprintf("SELECT transaction.* ")
          .sprintf("FROM transaction ")
          .sprintf("WHERE transaction.projectID = '%d' AND status='approved' ", $projectID)
          .sprintf("ORDER BY transactionModifiedTime desc");
      } else {
        $query = sprintf("SELECT transaction.* ")
          .sprintf("FROM transaction ")
          .sprintf("WHERE transaction.projectID = '%d' ", $projectID)
          .sprintf(" AND transaction.tfID = %d AND status='approved'", $current_user->get_id())
          .sprintf("ORDER BY transactionModifiedTime desc");
      }
      $db->query($query);
      while ($db->next_record()) {
        $transaction = new transaction;
        $transaction->read_db_record($db);
        $transaction->set_tpl_values(DST_HTML_ATTRIBUTE, "transaction_");

        $tf = $transaction->get_foreign_object("tf");
        $tf->set_tpl_values();
        $tf->set_tpl_values(DST_HTML_ATTRIBUTE, "tf_");

        $TPL["transaction_username"] = $db->f("username");
        $TPL["transaction_amount"] = number_format(($TPL["transaction_amount"]), 2);
        include_template($template);

      }


    }
  }

  function show_commission_list($template_name) {
    global $TPL, $db, $projectID;

    $TPL["commission_list_buttons"] = "
      <input type=\"submit\" name=\"commission_save\" value=\"Save\">
      <input type=\"submit\" name=\"commission_delete\" value=\"Delete\">";

    if ($projectID) {
      $query = sprintf("SELECT * from projectCommissionPerson WHERE projectID= %d", $projectID);
      $db->query($query);

      while ($db->next_record()) {
        $commission_item = new projectCommissionPerson;
        $commission_item->read_db_record($db);
        $commission_item->set_tpl_values(DST_HTML_ATTRIBUTE, "commission_");
        $tf = $commission_item->get_foreign_object("tf");
        include_template($template_name);
      }
    }
  }

  function show_new_commission($template_name) {
    global $TPL, $projectID;

    // Don't show entry form for new projects
    if (!$projectID) {
      return;
    }

    $TPL["commission_list_buttons"] = "<input type=\"submit\" name=\"commission_save\" value=\"Add\">";
    $commission_item = new projectCommissionPerson;
    $commission_item->set_tpl_values(DST_HTML_ATTRIBUTE, "commission_");
    $TPL["commission_projectID"] = $projectID;
    include_template($template_name);
  }

  function show_person_list($template) {
    global $db, $TPL, $projectID;
    global $email_type_array, $rate_type_array, $project_person_role_array;

    if ($projectID) {
      $query = sprintf("SELECT projectPerson.*, roleSequence
                          FROM projectPerson 
                     LEFT JOIN role ON role.roleID = projectPerson.roleID
                         WHERE projectID=%d ORDER BY roleSequence DESC,personID ASC", $projectID);
      $db->query($query);

      while ($db->next_record()) {
        $projectPerson = new projectPerson;
        $projectPerson->read_db_record($db);
        $projectPerson->set_tpl_values(DST_HTML_ATTRIBUTE, "person_");
        $person = $projectPerson->get_foreign_object("person");
        $TPL["person_username"] = $person->get_value("username");
        $TPL["person_emailType_options"] = page::select_options($email_type_array, $TPL["person_emailType"]);
        $TPL["person_role_options"] = page::select_options($project_person_role_array, $TPL["person_roleID"]);
        $TPL["rateType_options"] = page::select_options($rate_type_array, $TPL["person_rateUnitID"]);
        include_template($template);
      }
    }
  }

  function show_projectPerson_list() {
    global $db, $TPL, $projectID;
    $template = "templates/projectPersonSummaryViewR.tpl";

    if ($projectID) {
      $query = sprintf("SELECT personID, roleName
                          FROM projectPerson
                     LEFT JOIN role ON role.roleID = projectPerson.roleID
                         WHERE projectID = %d AND roleHandle IN ('isManager', 'timeSheetRecipient')
                      ORDER BY roleSequence DESC, personID ASC", $projectID);
      $db->query($query);
      while ($db->next_record()) {
        $projectPerson = new projectPerson;
        $projectPerson->read_db_record($db);
        $TPL['person_roleName'] = $db->f("roleName");
        $TPL['person_name'] = person::get_fullname($projectPerson->get_value('personID'));
        include_template($template);
      }
    }
  }

  function show_new_person($template) {
    global $TPL, $email_type_array, $rate_type_array, $projectID, $project_person_role_array;

    // Don't show entry form for new projects
    if (!$projectID) {
      return;
    }
    $project_person = new projectPerson;
    $project_person->set_tpl_values(DST_HTML_ATTRIBUTE, "person_");
    $TPL["person_emailType_options"] = page::select_options($email_type_array, $TPL["person_emailType"]);
    $TPL["person_role_options"] = page::select_options($project_person_role_array,false);
    $TPL["rateType_options"] = page::select_options($rate_type_array, $TPL["person_rateUnitID"]);
    include_template($template);
  }

  function show_time_sheets($template_name) {
    global $current_user;

    if ($current_user->is_employee()) {
      include_template($template_name);
    }
  }

  function show_project_managers($template_name) {
    include_template($template_name);
  }

  function show_transactions($template_name) {
    global $current_user;

    if ($current_user->is_employee()) {
      include_template($template_name);
    }
  }

  function show_person_options() {
    global $TPL;
    echo page::select_options(person::get_username_list($TPL["person_personID"]),$TPL["person_personID"]);
  }

  function show_tf_options($commission_tfID) {
    global $tf_array, $TPL;
    echo page::select_options($tf_array, $TPL[$commission_tfID]);
  }

  function show_comments() {
    global $projectID, $TPL;
    $options["showEditButtons"] = true;
    $TPL["commentsR"] = comment::util_get_comments("project",$projectID,$options);

    if ($TPL["commentsR"] && !$_GET["comment_edit"]) {
      $TPL["class_new_project_comment"] = "hidden";
    }

    include_template("templates/projectCommentM.tpl");
  }

  function show_tasks() {
    global $tasks, $TPL, $project;
    $options["showHeader"] = true;
    $options["taskView"] = "byProject";
    $options["projectIDs"] = array($project->get_id());   
    $options["taskStatus"] = "open";
    $options["showTaskID"] = true;
    $options["showAssigned"] = true;
    $options["showStatus"] = true;
    $options["showManager"] = true;
    $options["showDates"] = true;
    #$options["showTimes"] = true; // performance hit
    $options["return"] = "arrayAndHtml";
    // $tasks is used for the budget estimatation outside of this function
    list($tasks,$TPL["task_summary"]) = task::get_list($options); 
    include_template("templates/projectTaskS.tpl"); 
  }

  function show_reminders($template) {
    global $TPL, $projectID, $reminderID, $current_user;

    // show all reminders for this project
    $db = new db_alloc;
    $permissions = explode(",", $current_user->get_value("perms"));

    if (in_array("admin", $permissions) || in_array("manage", $permissions)) {
      $query = sprintf("SELECT * FROM reminder WHERE reminderType='project' AND reminderLinkID=%d", $projectID);
    } else {
      $query = sprintf("SELECT * FROM reminder WHERE reminderType='project' AND reminderLinkID=%d AND personID='%s'", $projectID, $current_user->get_id());
    }

    $db->query($query);

    while ($db->next_record()) {
      $reminder = new reminder;
      $reminder->read_db_record($db);
      $reminder->set_tpl_values(DST_HTML_ATTRIBUTE, "reminder_");

      if ($reminder->get_value('reminderRecuringInterval') == "No") {
        $TPL["reminder_reminderRecurence"] = "&nbsp;";
      } else {
        $TPL["reminder_reminderRecurence"] = "Every ".$reminder->get_value('reminderRecuringValue')
          ." ".$reminder->get_value('reminderRecuringInterval')."(s)";
      }

      $TPL["reminder_reminderRecipient"] = $reminder->get_recipient_description();
      $TPL["returnToParent"] = "project";

      include_template($template);
    }
  }

  function show_import_export($template) {
    include_template($template);
  }


// END FUNCTIONS




global $current_user;

$projectID = $_POST["projectID"] or $projectID = $_GET["projectID"];

$project = new project;

if ($projectID) {
  $project->set_id($projectID);
  $project->check_perm();
  $new_project = false;
} else {
  $new_project = true;
}


if ($_POST["save"]) {
  $project->read_globals();
  #$project->set_value("is_agency", $_POST["project_is_agency"] ? 1 : 0);
  


  if (!$project->get_id()) {    // brand new project
    $definately_new_project = true;
  }

  if (!$project->get_value("projectName")) {  
    $TPL["message"][] = "Please enter a name for the Project.";
  }  

  if (!$TPL["message"]) {

    $project->set_value("projectComments",rtrim($project->get_value("projectComments")));
    $project->save();
    $projectID = $project->get_id();

    $client = new client;
    $client->set_id($project->get_value("clientID"));
    $client->select();
    if ($client->get_value("clientStatus") == 'potential') {
      $client->set_value("clientStatus", "current");
      $client->save();
    }
   
    if ($definately_new_project) {
      $projectPerson = new projectPerson;
      $projectPerson->set_value("projectID", $projectID);
      $projectPerson->set_value_role("isManager");
      $projectPerson->set_value("personID", $current_user->get_id());
      $projectPerson->save();
    }
  }
} else if ($_POST["delete"]) {
  $project->read_globals();
  $project->delete();
  alloc_redirect($TPL["url_alloc_projectList"]);

// If they are creating a new project that is based on an existing one
} else if ($_POST["copy_project_save"] && $_POST["copy_projectID"] && $_POST["copy_project_name"]) {
  
  $p = new project();
  $p->set_id($_POST["copy_projectID"]);
  if ($p->select()) {
    $p2 = new project;
    $p2->read_row_record($p->row());
    $p2->set_id("");
    $p2->set_value("projectName",$_POST["copy_project_name"]);
    $p2->save();
    $TPL["message_good"][] = "Project details copied successfully.";

    // Copy project people
    $q = sprintf("SELECT * FROM projectPerson WHERE projectID = %d",$p->get_id());
    $db = new db_alloc();
    $db->query($q);
    while ($row = $db->row()) {
      $projectPerson = new projectPerson;
      $projectPerson->read_row_record($row);
      $projectPerson->set_id("");
      $projectPerson->set_value("projectID",$p2->get_id());
      $projectPerson->save();
      $TPL["message_good"]["projectPeople"] = "Project people copied successfully.";
    }

    // Copy commissions
    $q = sprintf("SELECT * FROM projectCommissionPerson WHERE projectID = %d",$p->get_id());
    $db = new db_alloc();
    $db->query($q);
    while ($row = $db->row()) {
      $projectCommissionPerson = new projectCommissionPerson;
      $projectCommissionPerson->read_row_record($row);
      $projectCommissionPerson->set_id("");
      $projectCommissionPerson->set_value("projectID",$p2->get_id());
      $projectCommissionPerson->save();
      $TPL["message_good"]["projectCommissions"] = "Project commissions copied successfully.";
    }

    alloc_redirect($TPL["url_alloc_project"]."projectID=".$p2->get_id());
  }


}




if ($projectID) {

  if ($_POST["person_save"]) {
    $q = sprintf("SELECT * FROM projectPerson WHERE projectID = %d",$project->get_id());
    $db = new db_alloc();
    $db->query($q);
    while ($db->next_record()) {
      $pp = new projectPerson;
      $pp->read_db_record($db);
      $delete[] = $pp->get_id();
      #$pp->delete(); // need to delete them after, cause we'll accidently wipe out the current user
    }

    if (is_array($_POST["person_personID"])) {
      foreach ($_POST["person_personID"] as $k => $personID) {
        if ($personID) {
          $pp = new projectPerson;
          $pp->set_value("projectID",$project->get_id());
          $pp->set_value("personID",$personID);
          $pp->set_value("roleID",$_POST["person_roleID"][$k]);
          $pp->set_value("rate",$_POST["person_rate"][$k]);
          $pp->set_value("rateUnitID",$_POST["person_rateUnitID"][$k]);
          $pp->set_value("projectPersonModifiedUser",$current_user->get_id());
          $pp->save();
        }
      }
    }

    if (is_array($delete)) {
      foreach ($delete as $projectPersonID) {
        $pp = new projectPerson;
        $pp->set_id($projectPersonID);
        $pp->delete();
      }
    }

  

  } else if ($_POST["commission_save"] || $_POST["commission_delete"]) {
    $commission_item = new projectCommissionPerson;
    $commission_item->read_globals();
    $commission_item->read_globals("commission_");

    if ($_POST["commission_save"]) {
      $commission_item->save();
    } else if ($_POST["commission_delete"]) {
      $commission_item->delete();
    }
  } else if ($_POST['do_import']) {
    // Import from an uploaded file
    switch($_POST['import_type']) {
      case 'planner':
        import_gnome_planner('import');
      break;
      case 'csv':
        import_csv('import');
      break;
    }
  }
  // Displaying a record
  $project->set_id($projectID);
  $project->select() || die("Could not load project $projectID");
} else {
  // Creating a new record
  $project->read_globals();
  $projectID = $project->get_id();
  $project->select();
}

// Comments
if ($_GET["commentID"] && $_GET["comment_edit"]) {
  $comment = new comment();
  $comment->set_id($_GET["commentID"]);
  $comment->select();
  $TPL["comment"] = $comment->get_value('comment');
  $TPL["comment_buttons"] =
    sprintf("<input type=\"hidden\" name=\"comment_id\" value=\"%d\">", $_GET["commentID"])
           ."<input type=\"submit\" name=\"comment_update\" value=\"Save Comment\">";
} else {
  $TPL["comment_buttons"] = "<input type=\"submit\" name=\"comment_save\" value=\"Save Comment\">";
}


// if someone uploads an attachment
if ($_POST["save_attachment"]) {
  move_attachment("project",$projectID);
  alloc_redirect($TPL["url_alloc_project"]."projectID=".$projectID."&sbs_link=attachments");
}


$project->set_tpl_values(DST_HTML_ATTRIBUTE, "project_");

$ops = array(""=>"","0"=>"No","1"=>"Yes");
$TPL["is_agency_options"] = page::select_options($ops,$project->get_value("is_agency"));
$TPL["project_is_agency_label"] = $ops[$project->get_value("is_agency")];


$db = new db_alloc;

$clientID = $project->get_value("clientID") or $clientID = $_GET["clientID"];
$client = new client;
$client->set_id($clientID);
$client->select();
$client->set_tpl_values(DST_HTML_ATTRIBUTE, "client_");

// If a client has been chosen
if ($clientID) {
  $query = sprintf("SELECT * 
                      FROM client 
                 LEFT JOIN clientContact ON client.clientPrimaryContactID = clientContact.clientContactID 
                     WHERE client.clientID = %d "
                   ,$clientID);

  $db->query($query);
  $row = $db->next_record();
  
  $row["clientStreetAddressOne"] and $one.= $row["clientStreetAddressOne"]."<br/>";
  $row["clientSuburbOne"]        and $one.= $row["clientSuburbOne"]."<br/>";
  $row["clientStateOne"]         and $one.= $row["clientStateOne"]."<br/>";
  $row["clientPostcodeOne"]      and $one.= $row["clientPostcodeOne"]."<br/>";
  $row["clientCountryOne"]       and $one.= $row["clientCountryOne"]."<br/>";

  $row["clientStreetAddressTwo"] and $two.= $row["clientStreetAddressTwo"]."<br/>";
  $row["clientSuburbTwo"]        and $two.= $row["clientSuburbTwo"]."<br/>";
  $row["clientStateTwo"]         and $two.= $row["clientStateTwo"]."<br/>";
  $row["clientPostcodeTwo"]      and $two.= $row["clientPostcodeTwo"]."<br/>";
  $row["clientCountryTwo"]       and $two.= $row["clientCountryTwo"]."<br/>";

  $row["clientContactName"]      and $thr.= $row["clientContactName"]."<br/>";
  $row["clientContactPhone"]     and $thr.= $row["clientContactPhone"]."<br/>";
  $row["clientContactMobile"]    and $thr.= $row["clientContactMobile"]."<br/>";
  $row["clientContactFax"]       and $thr.= $row["clientContactFax"]."<br/>";
  $row["clientContactEmail"]     and $thr.= $row["clientContactEmail"]."<br/>";

  $project->get_value("projectClientName")    and $fou.= $project->get_value("projectClientName")."<br/>";
  $project->get_value("projectClientAddress") and $fou.= $project->get_value("projectClientAddress")."<br/>";
  $project->get_value("projectClientPhone")   and $fou.= $project->get_value("projectClientPhone")."<br/>";
  $project->get_value("projectClientMobile")  and $fou.= $project->get_value("projectClientMobile")."<br/>";
  $project->get_value("projectClientEMail")   and $fou.= $project->get_value("projectClientEMail")."<br/>";

  $temp = str_replace("<br/>","",$fou);
  $temp and $thr = $fou;

  if ($project->get_value("clientContactID")) {
    $q = sprintf("SELECT * FROM clientContact WHERE clientContactID = %d",$project->get_value("clientContactID"));  
    $db->query($q);
    $db->next_record();
    $db->f("clientContactName")          and $fiv .= "<nobr>".$db->f("clientContactName")."</nobr><br/>";
    $db->f("clientContactStreetAddress") and $fiv .= $db->f("clientContactStreetAddress")."<br/>";
    $db->f("clientContactSuburb")        and $fiv .= $db->f("clientContactSuburb")."<br/>";
    $db->f("clientContactPostcode")      and $fiv .= $db->f("clientContactPostcode")."<br/>";
    $db->f("clientContactPhone")         and $fiv .= $db->f("clientContactPhone")."<br/>";
    $db->f("clientContactMobile")        and $fiv .= $db->f("clientContactMobile")."<br/>";
    $db->f("clientContactEmail")         and $fiv .= $db->f("clientContactEmail")."<br/>";
    $temp = str_replace("<br/>","",$fiv);
    $temp and $thr = $fiv;
  }

  $TPL["clientDetails"] = "<table width=\"100%\">";
  $TPL["clientDetails"].= "<tr>";
  $TPL["clientDetails"].= "<td colspan=\"3\"><h2 style=\"margin-bottom:0px; display:inline;\">".$TPL["client_clientName"]."</h2></td>";
  $TPL["clientDetails"].= "</tr>";
  $TPL["clientDetails"].= "<tr>";
  $one and $TPL["clientDetails"].= "<td class=\"nobr\"><u>Postal Address</u></td>";
  $two and $TPL["clientDetails"].= "<td class=\"nobr\"><u>Street Address</u></td>";
  $thr and $TPL["clientDetails"].= "<td><u>Contact</u></td>";
  $TPL["clientDetails"].= "</tr>";
  $TPL["clientDetails"].= "<tr>";
  $one and $TPL["clientDetails"].= "<td valign=\"top\">".$one."</td>";
  $two and $TPL["clientDetails"].= "<td valign=\"top\">".$two."</td>";
  $thr and $TPL["clientDetails"].= "<td valign=\"top\">".$thr."</td>";
  $TPL["clientDetails"].= "</tr>";
  $TPL["clientDetails"].= "</table>";
}


$TPL["clientContactDropdown"] = "<input type=\"hidden\" name=\"clientContactID\" value=\"".$project->get_value("clientContactID")."\">";
$TPL["clientHidden"] = "<input type=\"hidden\" id=\"clientID\" name=\"clientID\" value=\"".$clientID."\">";
$TPL["clientHidden"].= "<input type=\"hidden\" id=\"clientContactID\" name=\"clientContactID\" value=\"".$project->get_value("clientContactID")."\">";

// Gets $ per hour, even if user uses metric like $200 Daily
function get_projectPerson_hourly_rate($personID,$projectID) {
  $db = new db_alloc;
  $q = sprintf("SELECT rate,rateUnitID FROM projectPerson WHERE personID = %d AND projectID = %d",$personID,$projectID);
  $db->query($q);
  $db->next_record();
  $rate = $db->f("rate");
  $unitID = $db->f("rateUnitID");
  $t = new timeUnit;
  $timeUnits = $t->get_assoc_array("timeUnitID","timeUnitSeconds",$unitID);
  ($rate && $timeUnits[$unitID]) and $hourly_rate = $rate / ($timeUnits[$unitID]/60/60);
  return $hourly_rate;
}

if (is_object($project) && $project->get_id()) {
  if (is_array($tasks)) { // $tasks is a global defined in show_tasks() for performance reasons
    foreach ($tasks as $tid => $t) {
      $hourly_rate = get_projectPerson_hourly_rate($t["personID"],$t["projectID"]);
      $time_remaining = $t["timeEstimate"] - (task::get_time_billed($t["taskID"])/60/60);

      $cost_remaining = $hourly_rate * $time_remaining;

      if ($cost_remaining > 0) {
        #echo "<br/>Tally: ".$TPL["cost_remaining"] += $cost_remaining; 
        $TPL["cost_remaining"] += $cost_remaining; 
        $TPL["time_remaining"] += $time_remaining;
      } 
      $t["timeEstimate"] and $count_quoted_tasks++;
    }
    $currency = '$';
    $TPL["cost_remaining"] and $TPL["cost_remaining"] = $currency.sprintf("%0.2f",$TPL["cost_remaining"]);
    $TPL["time_remaining"] and $TPL["time_remaining"] = sprintf("%0.1f",$TPL["time_remaining"])." Hours.";

    $TPL["count_incomplete_tasks"] = count($tasks);
    $not_quoted = count($tasks) - $count_quoted_tasks;
    $not_quoted and $TPL["count_not_quoted_tasks"] = "(".sprintf("%d",$not_quoted)." tasks not included in estimate)";
  }

  list($TPL["total_timesheet_transactions"], $TPL["total_other_transactions"]) = $project->get_project_budget_spent();
  $TPL["grand_total"] = sprintf("%0.2f",$TPL["total_timesheet_transactions"] + $TPL["total_other_transactions"]);
  $TPL["project_projectBudget"] and $TPL["project_projectBudget"] = $pb = sprintf("%0.2f", $TPL["project_projectBudget"]);

  // calculate percentage from grand total and project budget (pb)
  if ($TPL["grand_total"] > 0 && $pb > 0) {
    $p = $TPL["grand_total"] / $pb * 100;
  } else {
    $p = "0";
  }
  $TPL["percentage"] = sprintf("%0.1f",$p);
}

$TPL["navigation_links"] = $project->get_navigation_links();

$query = sprintf("SELECT tfID AS value, tfName AS label 
                    FROM tf 
                   WHERE tfActive = 1
                ORDER BY tfName");
$TPL["commission_tf_options"] = page::select_options($query, $TPL["commission_tfID"]);
$TPL["cost_centre_tfID_options"] = page::select_options($query, $TPL["project_cost_centre_tfID"]);

$db->query($query);
while ($db->row()) {
  $tf_array[$db->f("value")] = $db->f("label");
}

if ($TPL["project_cost_centre_tfID"]) {
  $tf = new tf();
  $tf->set_id($TPL["project_cost_centre_tfID"]);
  $tf->select();
  $TPL["cost_centre_tfID_label"] = $tf->get_link();
}



$query = sprintf("SELECT roleName,roleID FROM role WHERE roleLevel = 'project' ORDER BY roleSequence");
$db->query($query);
#$project_person_role_array[] = "";
while ($db->next_record()) {
  $project_person_role_array[$db->f("roleID")] = $db->f("roleName");
}



$email_type_array = array("None"=>"None", "Assigned Tasks"=>"Assigned Tasks", "All Tasks"=>"All Tasks");
$currency_array = array("AUD"=>"AUD", "USD"=>"USD", "NZD"=>"NZD", "CAD"=>"CAD");
$projectType_array = project::get_project_type_array();
$projectStatus_array = array("current"=>"Current", "potential"=>"Potential", "archived"=>"Archived");
$timeUnit = new timeUnit;
$rate_type_array = $timeUnit->get_assoc_array("timeUnitID","timeUnitLabelB");
$TPL["project_projectType"] or $TPL["project_projectType"] = "project";
$TPL["project_projectType"] = $projectType_array[$TPL["project_projectType"]];
$TPL["projectType_options"] = page::select_options($projectType_array, $TPL["project_projectType"]);
$TPL["projectStatus_options"] = page::select_options($projectStatus_array, $TPL["project_projectStatus"]);
$TPL["project_projectPriority"] or $TPL["project_projectPriority"] = 3;
$projectPriorities = config::get_config_item("projectPriorities") or $projectPriorities = array();
$tp = array();
foreach($projectPriorities as $key => $arr) {
  $tp[$key] = $arr["label"];
}
$TPL["projectPriority_options"] = page::select_options($tp,$TPL["project_projectPriority"]);
$TPL["project_projectPriority"] and $TPL["priorityLabel"] = " <div style=\"display:inline; color:".$projectPriorities[$TPL["project_projectPriority"]]["colour"]."\">[".$tp[$TPL["project_projectPriority"]]."]</div>";





$TPL["currencyType_options"] = page::select_options($currency_array, $TPL["project_currencyType"]);

if ($_GET["projectID"] || $_POST["projectID"] || $TPL["project_projectID"]) {
  define("PROJECT_EXISTS",1);
}

if ($new_project && !(is_object($project) && $project->get_id())) {
  $TPL["main_alloc_title"] = "New Project - ".APPLICATION_NAME;
  $TPL["projectSelfLink"] = "New Project";
  $p = new project;
  $TPL["message_help"][] = "Create a new Project by inputting the Project Name and any other details, and clicking the Save button.";
  $TPL["message_help"][] = "";
  $TPL["message_help"][] = "<a href=\"#x\" class=\"magic\" id=\"copy_project_link\">Or copy an existing project</a>";
  $str =<<<DONE
    <div id="copy_project" style="display:none; margin-top:10px;">
      <form action="{$TPL["url_alloc_project"]}" method="post">
        <table>
          <tr>
            <td colspan="2">
              <label for="project_status_current">Current Projects</label>
              <input id="project_status_current" type="radio" name="project_status"  value="curr" checked>
              &nbsp;&nbsp;&nbsp;
              <label for="project_status_potential">Potential Projects</label>
              <input id="project_status_potential" type="radio" name="project_status"  value="pote">
              &nbsp;&nbsp;&nbsp;
              <label for="project_status_archived">Archived Projects</label>
              <input id="project_status_archived" type="radio" name="project_status"  value="arch">
            </td>
          </tr>
          <tr>
            <td>Existing Project</td><td><div id="projectDropdown"><select name="copy_projectID"></select></div></td>
          </tr>
          <tr>
            <td>New Project Name</td><td><input type="text" size="50" name="copy_project_name"></td>
          </tr>
          <tr>
            <td colspan="2" align="center"><input type="submit" name="copy_project_save" value="Copy Project"></td>
          </tr>
        </table>
      </form>
    </div>
DONE;
  $TPL["message_help"][] = $str;

} else {
  $TPL["main_alloc_title"] = "Project " . $project->get_id() . ": " . $project->get_project_name() . " - ".APPLICATION_NAME;
  $TPL["projectSelfLink"] = "<a href=\"". $project->get_url() . "\">";
  $TPL["projectSelfLink"] .=  sprintf("%d %s", $project->get_id(), $project->get_project_name());
  $TPL["projectSelfLink"] .= "</a>";
}

$TPL["taxName"] = config::get_config_item("taxName");

// Need to html-ise projectName and description
$TPL["project_projectName_html"] = page::to_html($project->get_value("projectName"));
$TPL["project_projectComments_html"] = page::to_html($project->get_value("projectComments"));


if ($project->have_perm(PERM_READ_WRITE)) {
  include_template("templates/projectFormM.tpl");
} else {
  include_template("templates/projectViewM.tpl");
}


?>
