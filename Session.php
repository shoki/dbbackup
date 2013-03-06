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


class Session {
    public function __construct(Config $conf, Log $log)  {
        $this->conf = $conf;
        $this->log = $log;
    }

    /* TODO: implement pruging old state log entries */
    public function purgeStateLog() {
        foreach ($this->state as $state) {
        }
    }    

    public function getState() {
        return $this->state;
    }

    public function getStateByWorkDir($workdir) {
        foreach ($this->state as $id => $state) {
            if ($state['workdir'] == $workdir)
                return $state;
        }
        return false;
    }

    public function getStateByState($bystate, $not = false) {
        foreach ($this->state as $state) {
            if ($not) {
                if ($state['state'] == $bystate) continue;
            } else {
                if ($state['state'] !== $bystate) continue;
            }

            $valid[] = $state;
        }
        return $valid;
    }

    public function purgeStateByTime($time) {
        return $this->updateStateByTime($time, 'PURGED');
    }

    public function updateStateByTime($time, $status) {
        foreach ($this->state as $id => $state) {
            if ($state['time'] == $time) {
                $this->state[$id]['state'] = $status;
                /* save state file */
                $this->writeState();
                return true;
            }
        }
        return false;
    }

    public function loadState() {
        if (file_exists($this->conf->bak_state)) {
            $serialized = file_get_contents($this->conf->bak_state);
            $state = unserialize($serialized);
            if ($state === false) 
                throw new Exception ("broken backup state, please check "
                ."{$this->conf->bak_state} file");

            $this->log->write("states loaded: ".count($state));
            $this->state = $state;
        } else {
            $this->log->write("no previous backups found.");
            $this->state = array();
        }
    }

    public function writeState() {
        if (is_array($this->state)) {
            $str = serialize($this->state);
            if ($str === false) 
                throw new Exception ("could not serialize backup state!");

            if (!@file_put_contents($this->conf->bak_state, $str)) {
                throw new Exception ("could not write backup state to file!");
            }
        }
    }

    public function setState($time, $workdir, $state) {
        $this->state[] = array ('time' => $time, 
            'workdir' => $workdir, 
            'state' => $state );
        $this->writeState();
    }
}



?>
