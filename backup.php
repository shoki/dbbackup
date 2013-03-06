#!/usr/bin/php
<?php

/******************************************************************************
 *  (C)opyright 1999-2007 Andre Pascha. All Rights Reserved.
 *
 *       Title: <one line description>
 *      Author: Andre Pascha <shoki@bsdler.de>
 *    $RCSfile$
 *   $Revision$
 *       $Date$
 *      $State$
 *
 ******************************************************************************/

ini_set('include_path', dirname(__FILE__).':.');

require 'Config.php';
require 'Log.php';
require 'MySQLConnection.php';
require 'MySQLDumper.php';
require 'FilePacker.php';
require 'FTP.php';
require 'DBBackup.php';
require 'Session.php';

$conf = new Config();
$b = new DBBackup($conf);
$b->run($argv);

?>
