<?php

include_once( dirname(__FILE__) . "/../includes/Data.php");
include_once( dirname(__FILE__) . "/../includes/Timer.php");

class Processor {

  protected $jobs = array();
  protected $completed = array();
  protected $type = false;
  private $debugTimer = null;

  public function __construct($script) {
    $this->debugTimer = new Timer();
    $this->type = $script;
  }

  public function fetchJobs() {
    $this->jobs = Queue::process($this->type);
    $count = count($this->jobs);

    if ($count > 0)
        Log::commit('cron', "$this->type ".count($this->jobs)." jobs in queue");
    else
        throw new Exception("No jobs in queue");
  }

  public function dequeueJobs() {
    $ids = array();
    foreach($this->completed as $job) {
      $ids[] = $job['id'];
    }
    Queue::dequeue($ids);
    Log::commit('cron', "$this->type ".count($this->completed)." jobs dequeued in ".round($this->debugTimer->elapsed(), 4)." seconds");
  }

  public function updateJobStatus($id, $status) {
    Queue::status($id, $status);
  }

  public function processJobs() {
    foreach($this->jobs as $job) {
      if ( $this->processJob($job) )
        $this->completed[] = $job;
    }
    $this->dequeueJobs();
  }

  public function processJob($job) {
    // overridden in child classes
    return false;
  }

}

?>
