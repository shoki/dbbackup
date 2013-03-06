<?php
class FTP {
	public function __construct (Config $con, Log $log) {
		$this->user = $con->ftp_user;
		$this->password = $con->ftp_password;
		$this->host = $con->ftp_host;
		$this->maxfilesize = $con->ftp_maxfilesize;
		$this->split = $con->split;
		$this->log = $log;
	}

	public function connect() {
		$this->conn = ftp_connect($this->host);

		// try again in 10 sec could be a TCP Problem
		if(!$this->conn) {
			$this->log->write("Could not connect will try again in 10 seconds");
			sleep(10);
			$this->conn = ftp_connect($this->host);
		}

		if(!$this->conn)
			throw new Exception("Can´t connect to FTP Server ".$this->host);

		$this->connected = true;

		$res = ftp_login($this->conn, $this->user, $this->password);
		if (!$res) throw new Exception ("Could not login to ftp!");
		/* use passive mode */
		ftp_pasv($this->conn, true);
	}
	public function disconnect() {
		if ($this->cwd) unset($this->cwd);
		if (is_resource($this->conn) && $this->connected) ftp_close($this->conn);
		$this->connected = false;
	}

	public function __destruct() {
		$this->disconnect();
	}

	public function chdir($dir) {
		$dir = explode("/", $dir);
		if (!is_array($dir)) return false;
		foreach ($dir as $item) {
			if (!@ftp_chdir($this->conn, $item)) {
				if (@ftp_mkdir($this->conn, $item))
				$this->log->debug(__FUNCTION__." FTP dir created: $item");
				if (!@ftp_chdir($this->conn, $item)) {
					throw new Exception ("cannot create FTP directory");
				}
			}
			$this->cwd .= $item.'/';
		}
		return true;
	}

	public function open($workdir) {
		$this->connect();
		$this->chdir($workdir);
		return true;
	}

	public function put($file) {
		/* always upload file to current directory */
		return ftp_put($this->conn, basename($file), $file, FTP_BINARY);
	}

	public function upload($file) {
		if ($this->checkSizeAndSplit($file)) {
			/* when splitting files, remove them after upload */
			foreach ($file as $one) {
				if (!$ret = $this->put($one))
				$this->log->write("upload of part $one failed!");
				unlink($one);
			}
		} else
		$ret = $this->put($file);

		return $ret;
	}

	public function checkSizeAndSplit(&$file) {
		if (filesize($file) > $this->maxfilesize) {
			$this->log->write("Spliting $file. Size is bigger than $this->maxfilesize");
			$cmd = $this->split." -b ".$this->maxfilesize." ";
			$cmd .= $file." ".$file.".part. 2>&1";
			exec($cmd, $output, $ret);

			/* collect filenames */
			foreach ($output as $line) {
				if (preg_match('/file `(.*)\'$/', $line, $match)) {
					$files[] = $match[1];
				}
			}

			if ($ret) {
				$this->log->write("Split failed, skipping file!");
				$this->log->write($output);
				/* cleanup files if any */
				if (is_array($files)) {
					foreach ($files as $one)
					@unlink($one);
				}
				return false;
			} else {
				$file = $files;
				return true;
			}
		}
		return false;
	}
}


?>
