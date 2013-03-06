<?php
class MySQLDumper {
	public function __construct(Config $conf, Log $log, MySQLConnection $con,
			$type = null, $characterset = null) {
		$this->conf = $conf;
		$this->con = $con;
		$this->log = $log;
		$this->dump_cmd = $this->conf->mysqldump;

		$this->type = $type;
		$this->characterset = $characterset;
	}

	private function runcmd($cmd) {
		$this->log->debug(__FUNCTION__." cmd=".$cmd);
		exec($cmd, $output, $ret);
		if ($ret != 0) {
			$this->log->write("Command ($cmd) failed for $table. Output:");
			$this->log->write($output);
			return false;
		}
		return true;
	}

	private function runcmd2($cmd) {
		$this->log->debug(__FUNCTION__." cmd=".$cmd);

		$pipes;
		$ret = -1;

		$desc = array (
			0 => array ("pipe", "r"),
			1 => array ("pipe", "w"),
			2 => array ("pipe", "w")
			);

		$proc = proc_open($cmd, $desc, $pipes);
		if (is_resource($proc)) {
			if ($out = stream_get_contents($pipes[1]))
				$this->log->write(explode("\n", $out));
			if ($out = stream_get_contents($pipes[2]))
				$this->log->write(explode("\n", $out));

			/* close pipes before continuing */
			foreach ($pipes as $idx => $value) {
				fclose($pipes[$idx]);
			}

			$ret = proc_close($proc);
		}

		if ($ret == 0) return true;
		else return false;
	}

	/* pipe mysqldump into bzip */
	public function dump($table) {
		$filename = $table;
		if ($this->type)
			$filename .= ".".$this->type;

		$cmd = $this->getDumpCommand($table);
		$cmd .= " > ".$filename;

		if ($ret = $this->runcmd2($cmd))
			$this->lastFileName = $filename;

		return $ret;
	}

	private function getDumpCommand($table) {
		$args = '';
		switch ($this->type) {
			case 'data':
				/* dump only data without triggers and create info */
				$args = '-t --skip-triggers';
				break;
			case 'trigger':
				/* dump only triggers without data or create info */
				$args = '-d -t --triggers';
				break;
			case 'structure':
				/* dump only table structure */
				$args = '-d --skip-triggers';
				break;
			case 'view':
			case 'federated':
				$args = '-d';
				break;
			default:
				/* use mysqldump defaults */
				break;
		}

		/* add default characterset if required */
		if ($this->characterset) {
			$args .= ' --default-character-set='.$this->characterset;
		}

		$cmd = $this->dump_cmd." -u".$this->con->user." -p".$this->con->password." -h".$this->con->host;
		$cmd .= " ".$args." ".$this->con->database." ".$table;

		return $cmd;
	}

	public function getLastFileName() {
		return $this->lastFileName;
	}
}


?>
