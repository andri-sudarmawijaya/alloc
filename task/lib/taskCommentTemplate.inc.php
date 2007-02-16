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


class taskCommentTemplate extends db_entity {
  
  var $data_table = "taskCommentTemplate";
  var $display_field_name = "taskCommentTemplateName";


  function taskCommentTemplate() {
    $this->db_entity();
    $this->key_field = new db_field("taskCommentTemplateID");
    $this->data_fields = array("taskCommentTemplateName"=>new db_field("taskCommentTemplateName")
                             , "taskCommentTemplateText"=>new db_field("taskCommentTemplateText")
                             , "taskCommentTemplateLastModified"=>new db_field("taskCommentTemplateLastModified"));
   }


  function get_populated_template($taskID) {

    $task = new task;
    $task->set_id($taskID);
    $task->select();
    $swap["ti"] = $task->get_id();
    $swap["to"] = person::get_fullname($task->get_value("creatorID"));
    $swap["ta"] = person::get_fullname($task->get_value("personID"));
    $swap["tn"] = stripslashes($task->get_value("taskName"));
    
    $project = new project;
    $project->set_id($task->get_value("projectID"));
    $project->select();
    $swap["pn"] = stripslashes($project->get_value("projectName"));

    $client = new client;
    $client->set_id($project->get_value("clientID"));
    $client->select();
    $swap["cc"] = stripslashes($client->get_value("clientName"));

    $swap["cd"] = "Phone: ".config::get_config_item("companyContactPhone");
    $swap["cd"].= "\nFax: ".config::get_config_item("companyContactFax");
    $swap["cd"].= "\n".config::get_config_item("companyContactAddress");
    $swap["cd"].= "\nEmail: ".config::get_config_item("companyContactEmail");
    $swap["cd"].= "  Web: ".config::get_config_item("companyContactHomePage");

    $swap["cn"] = config::get_config_item("companyName");

    $str = $this->get_value("taskCommentTemplateText");
    foreach ($swap as $k => $v) {
      $str = str_replace("%".$k,$v,$str);
    }
    return $str;
    

  }




}
?>
