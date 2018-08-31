<?php

include_once( "includes/Log.php");

class Cron {

  public $schedule;
  public $time;

  function Cron($schedule='* * * * *', $time=false) {
    $this->schedule = $schedule;
    $this->time = $time;
  }

  function isDue() {
      $time = is_string($this->time) ? strtotime($this->time) : time();
      $time = explode(' ', date('i G j n w', $time));
      $crontab = explode(' ', $this->schedule);
      foreach ($crontab as $k => &$v) {
          $v = explode(',', $v);
          $regexps = array(
              '/^\*$/', # every
              '/^\d+$/', # digit
              '/^(\d+)\-(\d+)$/', # range
              '/^\*\/(\d+)$/' # every digit
          );
          $content = array(
              "true", # every
              "{$time[$k]} === $0", # digit
              "($1 <= {$time[$k]} && {$time[$k]} <= $2)", # range
              "{$time[$k]} % $1 === 0" # every digit
          );
          foreach ($v as &$v1)
              $v1 = preg_replace($regexps, $content, $v1);
          $v = '('.implode(' || ', $v).')';
      }
      $crontab = implode(' && ', $crontab);
      return eval("return {$crontab};");
  }

}

?>
