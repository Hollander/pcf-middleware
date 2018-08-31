<?php

include_once( dirname(__FILE__) . "/../includes/Data.php");
include_once( dirname(__FILE__) . "/Processor.php");

class FileProcessor extends Processor {

  public function __construct($script) {
    parent::__construct($script);
  }

  public function fetchJobs() {
    $files = array_diff(
        scandir( dirname(__FILE__) . "/../files/$this->type" ),
        array('.', '..', '.gitignore')
    );
    foreach($files as $file) {
        Queue::enqueue( $this->type, array( 'file' => dirname(__FILE__) . "/../files/$this->type/$file" ) );
    }
    return parent::fetchJobs();
  }

  public function dequeueJobs() {
    foreach($this->completed as $job) {
      $message = json_decode( $job['message'], true );
      rename( $message['file'], str_replace($this->type, 'archive', $message['file']) );
    }
    parent::dequeueJobs();
  }

}

?>
