<?php

class FilePacker {
	public function __construct(Config $conf, Log $log) {
		$this->conf = $conf;
		$this->log = $log;
	}

	public function pack($file) {
		$this->log->verbose("Packing ".$file);
		$cmd = str_replace("[FILE]", $file, $this->conf->zip);
		$this->runcmd2($cmd);
		unlink($file);
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
}

?>