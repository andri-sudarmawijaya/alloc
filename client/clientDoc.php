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

$file = urldecode($_GET["file"]);

if ($_GET["clientID"] && is_numeric($_GET["clientID"]) && $file && !preg_match("/\.\./",$file)) {

  $file = $TPL["url_alloc_clientDocs_dir"].$_GET["clientID"]."/".$file;

  if (file_exists($file) && is_writeable($file)) {
    $fp = fopen($file, "rb");
    header('Content-Type: application/octet-stream');
    header("Content-Length: ".filesize($file));
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    fpassthru($fp);
    exit;
  }
}



?>
