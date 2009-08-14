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


/**
*
* This class provides methods for SOAP services to access key parts of
* alloc's functionality.
*
* The inline PHPDoc-style comments, provide information for WSDL_Gen so that
* the alloc.wsdl file can be dynamically generated by running `make soap`.
*
* You must run `make soap` every time modifications are made to this class.
* You must ensure that the PHPDoc parameter definitions are correct.
*
*/

class alloc_soap {

  /** The authenticate function
   * @param string $username
   * @param string $password
   * @return string $sessKey
   */
  public function authenticate($username,$password) {
    $person = new person;
    $sess = new Session;
    $row = $person->get_valid_login_row($username,$password); 
    if ($row) {
      $sess->Start($row,false);
      $sess->UseGet();
      $sess->Save();
      return $sess->GetKey();
    } else {
      throw new SoapFault("Server","Authentication Failed(1)."); 
    }
  }  

  private function get_current_user($key) {
    $sess = new Session($key);
    if (!$sess->Started()) {
      throw new SoapFault("Server","Authentication Failed(2).");
    } else {
      $person = new person;
      $person->load_current_user($sess->Get("personID"));
      return $person;
    }
  }

  /** The get_task_comments function
   * @param string $sessKey
   * @param int $taskID
   * @return array $comments
   */
  public function get_task_comments($key,$taskID) {
    global $current_user; // Always need this :(
    $current_user = $this->get_current_user($key);
    if ($taskID) {
      $task = new task;
      $task->set_id($taskID);
      $task->select();
      return $task->get_task_comments_array();
    }
  }

  /** The add_timeSheetItem_by_task function
   * @param string $sessKey
   * @param int $taskID
   * @param string $duration
   * @param string $comments
   * @return int $timeSheetID
   */
  public function add_timeSheetItem_by_task($key, $task, $duration, $comments) {
    global $current_user; // Always need this :(
    $current_user = $this->get_current_user($key);
    $bits = timeSheet::add_timeSheetItem_by_task($task, $duration, $comments);
    return $bits["timeSheetID"];
  }

  /** The get_list function
   * @param string $sessKey
   * @param string $entity
   * @param mixed $options
   * @return mixed $list
   */
  public function get_list($key, $entity, $options=array()) {
    global $current_user; // Always need this :(
    $current_user = $this->get_current_user($key);
    if (class_exists($entity)) {
      $options = obj2array($options);
      $e = new $entity;
      if (method_exists($e, "get_list")) {
        ob_start();
        $rtn = $e->get_list($options);
        $echoed = ob_get_contents();
        if (!$rtn && $echoed) {
          return $echoed;
        } else {
          return $rtn;
        }
      } else {
        throw new SoapFault("Server","Entity method '".$entity."->get_list()' does not exist."); 
      }
    } else {
      throw new SoapFault("Server","Entity '".$entity."' does not exist."); 
    }
  }

  /** The get_email function
   * @param string $sessKey
   * @param string $emailUID
   * @return string $email
   */
  public function get_email($key, $emailUID) {
    global $current_user; // Always need this :(
    $current_user = $this->get_current_user($key);
    if ($emailUID) {
      $lockfile = ATTACHMENTS_DIR."mail.lock.person_".$current_user->get_id();
      $info["host"] = config::get_config_item("allocEmailHost");
      $info["port"] = config::get_config_item("allocEmailPort");
      $info["username"] = config::get_config_item("allocEmailUsername");
      $info["password"] = config::get_config_item("allocEmailPassword");
      $info["protocol"] = config::get_config_item("allocEmailProtocol");
      if (!$info["host"]) {
        die("Email mailbox host not defined, assuming email fetch function is inactive.");
      }
      $mail = new alloc_email_receive($info,$lockfile);
      $mail->open_mailbox(config::get_config_item("allocEmailFolder"));
      $str = $mail->get_raw_email_by_msg_uid($emailUID);
      $mail->close();
      return utf8_encode($str);
    }
  }

  /** The get_help function
   * @param string $topic
   * @return string $helptext
   */
  public function get_help($method="") {
    $this_methods = get_class_methods($this);

    if (!$method) {
      foreach ($this_methods as $method) {
        $m = $method."_help";
        if (method_exists($this,$m)) {
          $available_topics.= $commar.$method;
          $commar = ", ";
        }
      }
      return "Help is available for the following methods: ".$available_topics;

    } else {
      $m = $method."_help";
      if (method_exists($this,$m)) {
        return $this->$m();
      } else {
        throw new SoapFault("Server","No help exists for this method: ".$method); 
      }
    }
  }

  /** The help function for get_list
   * @param string $sessKey
   * @return string $helptext
   */
  private function get_list_help() {
    # This function does not require authentication.
    #global $current_user; // Always need this :(
    #$current_user = $this->get_current_user($key);

    global $modules;
    foreach ($modules as $name => $object) {  
      if (is_object($object) && is_array($object->db_entities)) {
        foreach ($object->db_entities as $entity) {
          unset($commar2);
          if (class_exists($entity)) {
            $e = new $entity;
            if (method_exists($e, "get_list")) {
              $rtn.= "\n\nEntity: ".$entity."\nOptions:\n";
              if (method_exists($e, "get_list_vars")) {
                $options = $e->get_list_vars();
                foreach ($options as $option=>$help) {
                  $padding = 30 - strlen($option);
                  $rtn.= $commar2."    ".$option.str_repeat(" ",$padding).$help;
                  $commar2 = "\n";
                }
              }
            }
          }
        }
      }
    }
    return "Usage: get_list(sessionKey, entity, options). The following entities are available: ".$rtn;
  }

} 


?>
