<?php

class MySQLConnection {
	public $host;
	public $user;
	public $password;
	public $database;
	public $db;

	public function __construct($host, $db = '', $user, $password) {
		$this->host = $host;
		$this->database = $db;
		$this->user = $user;
		$this->password = $password;

		$this->db = new mysqli($host, $user, $password, $db);
		if (mysqli_connect_errno()) {
			unset($this->db);
			$this->error = true;
		}
    }

	public function query($query) {
		if (!$this->db) return false;
		$ret = $this->db->query($query);
		return $ret;
	}

	public function fetch_array($query) {
		$res = $this->query($query);
		$arr = array();
		while ($row = $res->fetch_array()) $arr[] = $row;
		return $arr;
	}

	public function fetch_assoc_all($query) {
		$res = $this->query($query);
		$arr = array();
		while ($row = $res->fetch_assoc()) $arr[] = $row;
		return $arr;
	}

	public function close() {
		if ($this->db) {
			$this->db->close();
			unset($this->db);
		}
	}

	public function __destruct() {
		$this->close();
	}
}

?>
