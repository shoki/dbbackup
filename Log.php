<?php

class Log {
    private $logs  = array();
    private $log_verbose = true;

    public function __construct(Config $conf) {
		$this->conf = $conf;
		define_syslog_variables();
		openlog("DBBackup", LOG_PID, LOG_DAEMON);
    }

    public function setVerbose($flag) {
    	$this->verbose = $flag;
    }

    public function error($msg) {
    	$this->_log($msg, LOG_ERR);
    }

	public function write($msg) {
		if (is_array($msg)) {
			foreach ($msg as $line) {
				if ($line)
					$this->_log($line, LOG_NOTICE);
			}
		} else
			$this->_log($msg, LOG_NOTICE);
	}

    public function verbose($msg) {
    	if ($this->log_verbose)
    			$this->_log($msg, LOG_INFO);
    }

    public function debug($msg) {
    	if($this->conf->debug)
    		$this->_log($msg, LOG_DEBUG);
    }

    // syslog and echo
    private function _log($msg, $type = LOG_NOTICE) {
    switch($type) {
    		case LOG_ERR:
    			$type_human = "ERROR"; break;
    		case LOG_NOTICE:
    			$type_human = "INFO"; break;
    		case LOG_INFO:
    			$type_human = "VERBOSE"; break;
    		case LOG_DEBUG:
    			$type_human = "DEBUG"; break;
    	}
    	syslog($type, $type_human.": ".rtrim($msg));
		$out = sprintf("%s [%d] %s: %s\n", date('Y-m-d H:i:s'), posix_getpid(), $type_human, $msg);
		$this->logs[] = $out;
		echo $out;
    }

    public function get()  {
    	foreach ($this->logs as $entry) {
            $text .= $entry;
        }
    	return $text;
    }
}

?>
