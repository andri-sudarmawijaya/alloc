<?php

/*
 * Copyright (C) 2006-2019 Alex Lance, Clancy Malcolm, Cyber IT Solutions
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


define("NO_REDIRECT", 1);
require_once("../alloc.php");


usleep(500000);

$task = new task();
if ($_GET["taskID"]) {
    $task->set_id($_GET["taskID"]);
    $task->select();
}

echo "<select name=\"managerID\"><option value=\"\">".$task->get_personList_dropdown($_GET["projectID"], "managerID", $_GET["selected"])."</select>";
