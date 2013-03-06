<?php

/******************************************************************************
 *  (C)opyright 2000-2007 KWICK! Community GmbH, All Rights Reserved.
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

class DBBackup {
	private $workdirstack = array();
	private $current_profile;
	private $dumpJobs = array();
	private $packJobs = array();
	private $errors = 0;

	public function __construct(Config $conf) {
		$this->conf = $conf;
		$this->dbs = $conf->backup_db;
		$this->excludes = $conf->exclude_db;
		$this->log = new Log($this->conf);
		$this->ftp = new FTP($this->conf, $this->log);
		$this->sess = new Session($this->conf, $this->log);
	}

	private function createWorkDir() {
		$maxtry = 100;	/* maximum tries to get a unique directory */
		/* check backup base directory */
		if (!file_exists($this->conf->bak_dir)) {
			throw new Exception ("Backup dir is not writeable!");
		}

		$today = date('Ymd');
		/* try to get a working directory that is not already in state db */
		$i = 0;
		do {
			$try++;
			$workdir = $today."_".sprintf("%02d", $i);
			if (!$this->sess->getStateByWorkDir($workdir)) {
				/* not recorded in state file */
				if (file_exists($workdir)) {
					$this->log->write("Working directory ({$workdir}) already exists but is not "
							."in state file, skipping!");
				} else {
					/* not recorded in state file and not existing */
					break;
				}
			}
			$i++;
		} while ($try < $maxtry);

		/* create current workdir */
		mkdir($workdir);
		$this->log->write("Working directory created: $workdir");
		return $workdir;
	}

	private function resolveAsterixinDBList() {
		$ret = array();
		foreach($this->dbs as $db => $profidx) {
			$db = explode(".", $db);
			// if asterix resolve
			if($db[1] == "*") {
				foreach($this->getTablelistFromServer($db[0], $profidx) as $table) {
					$key = $db[0].".".$table;
					$ret[$key] = $profidx;
				}
			}
			// just passthru for others
			else
				$ret[implode(".", $db)] = $profidx;
		}
		$this->dbs = $ret;
	}

	private function applyExcludestoDBList() {
		foreach($this->excludes as $exclude) {
			$exclude = explode(".", $exclude);
			if($exclude[0] == "*") {
				foreach($this->dbs as $db => $profile) {
					$db_e = explode(".", $db);
					if($exclude[1] == $db_e[1]) {
						unset($this->dbs[$db]);
					}
				}
			}
			else {
				foreach($this->dbs as $db => $profile) {
					if(implode(".", $exclude) == $db) {
						unset($this->dbs[$db]);
					}
				}
			}
		}
	}

	private function getTablelistFromServer($host, $profidx) {
		if (!$this->conf->profile[$profidx])
			throw new Exception ("No configuration profile found for host $db with profile ".$profidx);

		$this->log->write("Gettings list of Tables from $host");

		$this->current_profile = $this->conf->profile[$profidx];

		$con = new MySQLConnection($host,false, $this->current_profile['username'],$this->current_profile['password']);
		if ($con->error) {
			unset($con);
			$this->log->write("could not connect to $host to get tableslist, skipping");
			return array();
		}
		else {
			$tables = $con->fetch_array("SHOW DATABASES");
			$ret = array();
			foreach($tables as $table)
				$ret[] = $table[0];

			if(count($ret) == 0)
				$this->log->write("found 0 databases for host ".$host." this could be a problem!");

			return $ret;
		}
	}

	private function purge() {
		/* get unpurged backups */
		$valid = $this->sess->getStateByState('PURGED', true);
		if (!is_array($valid)) return;

		$old = array_slice($valid, 0, -$this->conf->keep_backups);
		if (is_array($old)) {
			foreach ($old as $backup ) {
				/* skip unfinished backups */
				$workdir = $this->conf->bak_dir.'/'.$backup['workdir'];
				if (!file_exists($workdir)) {
					$this->log->write("$workdir disappeared, purging state");
				} else {
					$this->log->write("Purging {$backup['workdir']} from "
							.date('Y-m-d h:i:s', $backup['time'])
							.' with state \''.$backup['state'].'\'');
					if (!$this->removeDirectory($workdir.'/')) {
						$this->log->error("Failed purging {$workdir}, skipping!");
						continue;
					}
				}
				if (!$this->sess->purgeStateByTime($backup['time'])) {
					throw new Exception ("state database corrupted!");
				}
			}
		}
	}

	private function removeDirectory($dir) {
		$dir_contents = @scandir($dir);
		if ($dir_contents === false) return false;
		foreach ($dir_contents as $item) {
			if ($item == '.' || $item == '..') continue;
			if (is_dir($dir.$item)) {
				if (!$this->removeDirectory($dir.$item.'/'))
					return false;
			}
			elseif (file_exists($dir.$item)) {
				if (!@unlink($dir.$item))
					return false;
			}
		}
		return (@rmdir($dir));
	}

	public function cleanShutdown() {
		$this->log->write("Shutting down.");
		try {
			$this->sess->writeState();
		} catch (Exception $e) {
			$this->log->write($e->getMessage());
		}
	}

	private function registerSignals() {
		pcntl_signal(SIGTERM, array(&$this, 'cleanShutdown'));
		pcntl_signal(SIGHUP, array(&$this, 'cleanShutdown'));
		pcntl_signal(SIGUSR1, array(&$this, 'cleanShutdown'));
	}

	private function generateDumpJobs() {
		foreach($this->dbs as $db => $profile) {
			$dbe = explode(".", $db);
			$this->dumpJobs[$dbe[0]][] = $db;
		}
	}

	private function generatePackJobs() {
		foreach($this->sessionfiles as $db => $files) {
			foreach($files as $file) {
				$this->packJobs[] = $this->workdir.DIRECTORY_SEPARATOR.$db.DIRECTORY_SEPARATOR.$file;
			}
		}
	}

	private function popDumpJob() {
		return array_pop($this->dumpJobs);
	}

	private function popPackJob() {
		return array_pop($this->packJobs);
	}

	private function doDumping() {
		$this->log->write("Dumping process started");
		$workerRunning = 0;

		$this->generateDumpJobs();

		while(true) {
			if($workerRunning < $this->conf->worker_dump && count($this->dumpJobs)) {
				// get work
				$job = $this->popDumpJob();

				// fork this ;)
				$pid = pcntl_fork();
				if($pid) {
					$this->log->debug("started worker ".$pid." to dump a db");
					$workerRunning++;
				}
				else {
					// IN WORKER
					exit($this->doDumpJob($job));
				}
			}
			// if worker running wait for worker to exit
			else if($workerRunning) {
				$pid = pcntl_wait($workerStatus);
				$workerReturnCode = pcntl_wexitstatus($workerStatus);
				$this->log->debug("Worker ".$pid." exited with statuscode ".$workerReturnCode);
				if($workerReturnCode != 0)
					$this->errors++;
				$workerRunning--;
			}
			// we are ready ;)
			else {
				$this->generateSessionFileList();
				return true;
			}
		}
	}

	private function generateSessionFileList() {
		$this->sessionfiles = array();
		$workdir = new DirectoryIterator($this->workdir);
		foreach($workdir as $db) {
			if(!$db->isDot()) {
				$dbdir = new DirectoryIterator($db->getPathname());
				foreach($dbdir as $file) {
					if(!$file->isDot())
						$this->sessionfiles[$db->getBasename()][] = $file->getBasename();
				}
			}
		}
	}

	private function doPacking() {
		$this->log->write("Packing process started");
		$workerRunning = 0;

		$this->generatePackJobs();

		while(true) {
			if($workerRunning < $this->conf->worker_pack && count($this->packJobs)) {
				// get work
				$job = $this->popPackJob();

				// fork this ;)
				$pid = pcntl_fork();
				if($pid) {
					$this->log->debug("started worker ".$pid." to pack a file");
					$workerRunning++;
				}
				else {
					// IN WORKER
					$this->doPackJob($job);
					exit;
				}
			}
			// if worker running wait for worker to exit
			else if($workerRunning) {
				$pid = pcntl_wait($workerReturnCode);
				$this->log->debug("Worker ".$pid." exited with statuscode ".$workerReturnCode);
				$workerRunning--;
			}
			// we are ready ;)
			else {
				$this->generateSessionFileList();
				return true;
			}
		}
	}

	private function doPackJob($job) {
		$pack = new FilePacker($this->conf, $this->log);
		$pack->pack($job);
	}

	private function doUploading() {
		$this->log->write("Uploading process started");
		if ($this->conf->upload) {
			if (!$this->uploadDumps()) $this->errors++;
		}
		else {
			$this->log->write("Uploading ist disabled in config!");
		}
		return true;
	}

	private function doDumpJob($job) {
		$errs = $this->errors;
		foreach($job as $db) {
			$profidx = $this->dbs[$db];
			if (!$this->conf->profile[$profidx])
				throw new Exception ("No configuration profile found for host $db");

			$dbcfg = explode(".", $db);

			$this->log->write("Backup $db");
			$this->current_profile = $this->conf->profile[$profidx];

			$con = new MySQLConnection($dbcfg[0],$dbcfg[1],
				$this->current_profile['username'],$this->current_profile['password']);
			if ($con->error) {
				unset($con);
				$this->log->write("could not connect to {$dbcfg[0]}, skipping");
				$this->errors++;
				continue;
			}
			$this->backupTables($con);
			unset($con);
		}
		if($this->errors > $errs)
			return 1;
		else
			return 0;
	}

	/* MAIN magic happens here */
	public function run($argv) {
		if ($argv[1] == "-q") {
			$this->log->setVerbose(false);
		} elseif (isset($argv[1])) {
			$this->dbs = array();
			$this->dbs[$argv[1]] = 0;
		}

		try {
			$this->registerSignals();
			$this->log->write(__CLASS__." started");
			$this->log->write($this->conf->worker_dump." dumper and ".$this->conf->worker_pack." packer");
			$this->sess->loadState();
			$this->pushWorkDir($this->conf->bak_dir);
			$this->sessiondir = $this->createWorkDir();
			$this->pushWorkDir($this->sessiondir);
			$this->starttime = time();
			$this->resolveAsterixinDBList();
			$this->applyExcludestoDBList();

			/* start a new Session with inital state set to FAILED */
			$this->sess->setState($this->starttime, basename($this->workdir), "FAILED");

			$this->doDumping();

			$this->doPacking();

			$this->doUploading();



			/* set session state */
			if ($this->errors)
				$this->sess->updateStateByTime($this->starttime, "WITH_ERRORS");
			else
				$this->sess->updateStateByTime($this->starttime, "OK");

			/* update state file */
			$this->sess->writeState();

			/* backup state to FTP if requested */
			if ($this->conf->upload && $this->conf->backup_state_file) {
				$this->log->write("Backup state file to FTP");
				$this->uploadFileToDir($this->sessiondir.'/'.$this->conf->state_bak_dir,
						$this->conf->bak_state);
			}

			if ($this->conf->upload) {
				/* write session log and backup to FTP */
				$this->log->write("Backup session log to FTP");
				file_put_contents($this->conf->logfilename, $this->log->get());
				$this->uploadFileToDir($this->sessiondir.'/'.$this->conf->state_bak_dir,
						$this->conf->logfilename);
			}

			/* done with this session */
			$this->popWorkDir();

			/* search for files to purge */
			$this->purge();

		} catch (Exception $e) {
			$this->log->write("Failure: ".$e->getMessage());
			/* remove traces */
			if ($this->workdir && file_exists($this->workdir)) {
				$this->removeDirectory($this->workdir.'/');
				$this->sess->updateStateByTime($this->starttime, "PURGED");
			}
			// log the failure
			try {
				$con = new MySQLConnection($host,false, $this->current_profile['username'],$this->current_profile['password']);
				$logDB = $this->getLogDB();
				$logDB->query("REPLACE INTO log_dbbackup SET `date` = NOW(), status = 0");
				unset($logDB);
			}
			catch (Exception $e) {
				$this->log->error("Failure: Logging the failure failed!");
			}
		}

		// log success
		$logDB = $this->getLogDB();

		if($this->errors == 0)
			$status = 1;
		else {
			$status = 0;
			$this->log->write("Warning Errorcount is: ".$this->errors);
		}

		$logDB->query("REPLACE INTO log_dbbackup SET `date` = NOW(), status = ".$status);
		unset($logDB);

		$this->cleanShutdown();
	}

	private function getLogDB() {
		return new MySQLConnection(
			$this->conf->db_log['hostname'],
			$this->conf->db_log['database'],
			$this->conf->db_log['username'],
			$this->conf->db_log['password']
		);
	}

	private function uploadDumps() {
		if (!is_array($this->sessionfiles)) return false;

		try {
			$this->ftp->connect();
			$this->ftp->chdir($this->conf->ftp_base.'/'.$this->sessiondir);

			foreach ($this->sessionfiles as $dir => $files) {
				$this->log->debug(__FUNCTION__." dir=$dir");
				$this->pushWorkDir($dir);
				$this->ftp->chdir($dir);
				foreach ($files as $file) {
					$this->log->verbose("upload $file");
					if (!$this->ftp->put(basename($file))) {
						$this->log->error("upload failed for $file");
						$this->errors++;
					}
				}
				$this->ftp->chdir("..");
				$this->popWorkDir($dir);
			}

			$this->ftp->disconnect();
		} catch (Exception $e) {
			$this->log->error("Upload failed: ".$e->getMessage());
			return false;
		}
		return true;
	}

	private function uploadFileToDir($dir, $file) {
		try {
			$ftpdir = $this->conf->ftp_base.'/'.$dir;
			//echo("uploaddir $ftpdir\n");
			if ($this->ftp->open($ftpdir)) {
				if (!$this->ftp->upload($file)) {
					throw new Exception ("Could not upload $file!");
				}
				$this->ftp->disconnect();
			} else
				throw new Exception ("cannot connect to FTP!");
			return true;
		} catch ( Exception $e) {
			$this->ftp->disconnect();
			$this->log->write($e->getMessage());
			return false;
		}
	}

	private function pushWorkDir($newdir) {
		array_push($this->workdirstack, getcwd());
		if (chdir($newdir)) {
			//array_push($this->workdirstack, $this->workdir.'/'.$newdir);
			$this->workdir .= '/'.$newdir;
			return true;
		}
		return false;
	}

	private function popWorkDir($level = 1) {
		if (!empty($this->workdirstack))
			return chdir(array_pop($this->workdirstack));
		return false;
	}

	private function backupTables(MySQLConnection $con) {
		$host = '['.$con->host.'.'.$con->database.'] ';
		/* create database directory */
		$workdir = $con->host.'.'.$con->database;
		mkdir($workdir);
		$this->pushWorkDir($workdir);

		$tables = $con->fetch_assoc_all("SHOW TABLE STATUS");
		/* backup first */
		foreach ($tables as $table) {
			$this->log->verbose($host."dump table ".$table['Name']." engine '".$table['Engine']."'");

			/* handle special table types */
			if (empty($table['Engine']) && $table['Comment'] == 'VIEW')
				$types = array ('view');
			elseif ($table['Engine'] == 'FEDERATED')
				$types = array ('federated', 'trigger');
			else
				$types = array ('structure', 'data', 'trigger');

			foreach ($types as $type) {
				$this->log->debug($host."dump ".$type." of table ".$table['Name']);
				$dumper = new MySQLDumper($this->conf, $this->log, $con, $type, $this->current_profile['characterset']);
				if (!$dumper->dump($table['Name'])) {
					$this->log->error($host."dump of table ".$table['Name']." failed.");
					$this->errors++;
				}
			}
		}
		$this->popWorkDir();

		return $files;
	}
}

?>
