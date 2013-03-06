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


class Config {
    public $bak_dir = "/backup/db";
    public $debug = false;
    /* backup state is stored here as a serialized array */
    public $bak_state = "/backup/db/bak.state";
    /* how many backups to keep on local disk */
    public $keep_backups = 21;


    // workercounts
    public $worker_dump = 4;
    public $worker_pack = 8;

	/* enable uploading of dumps to remote location */
	public $upload = false;
    /* directory on FTP where to store log/state */
    public $state_bak_dir = "_dbbackup";
    /* backup state file to FTP yes/no */
    public $backup_state_file = true;

    /* logfilename of current session written in sessiondir */
    public $logfilename = "session.log";

    /* send emails notification to this addresses */
    public $notification_to = "admin@localhost";
    public $notification_from = "dbbackup@localhost";

    /* db authentications */
    public $profile = array ( 0 => array ( 
				'username' => "root", 
				'password' => "mypass", 
				'characterset' => "utf8" ,
				'dumptypes' => array ( 'structure', 'view', 'federated', 'trigger', 'data' ),
				));

    /* Authinfo for db_log.userlog */
    public $db_log = array(
        "hostname" => "db_log",
        "database" => "userlog",
        'username' => "root",
        'password' => "mypass",
        'characterset' => "utf8",
    );

    /* commands */
    public $mysqldump = "mysqldump --single-transaction --skip-lock-tables --extended-insert";
    public $zip = "gzip -c [FILE] > [FILE].gz";
    public $split = "split -d --verbose";

    /* FTP config */
    public $ftp_user="bak";
    public $ftp_password="password";
    public $ftp_host="vbak";
    public $ftp_maxfilesize="2000000000";	/* FTP is limited to 2GB filesize */
    public $ftp_base = "/db";

    /* LB realserver to host mapping */
    public $realserver = array ( );

    /* databases to backup. 
	format: <host>.<database> => authindex
     */
    public $backup_db=array (
        /*
			  'asp2.*' => 0,
              'dbmogile2.mogilefs' => 0,
         */
    );

	public $exclude_db=array(
		"*.information_schema",	// no need for information schema backup
        "*.mysql",		// we don´t want mysql being backed up ;)
	);

} 

?>
