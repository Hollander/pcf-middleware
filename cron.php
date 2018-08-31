<?php

// errors
ini_set('display_errors', 1); error_reporting(E_ALL | E_STRICT);

// includes
include_once( dirname(__FILE__) . "/includes/Config.php");
include_once( dirname(__FILE__) . "/includes/Log.php");
include_once( dirname(__FILE__) . "/includes/Cron.php");
include_once( dirname(__FILE__) . "/includes/Queue.php");
include_once( dirname(__FILE__) . "/processors/OrderCreateProcessor.php");

// config init
$config = Config::Instance();

// loop through all crons
foreach($config->settings['middleware']['cron'] as $script => $schedule) {
  $cron = new Cron($schedule);
  if ($cron->isDue()) {
    Log::commit('cron', "Attempting to run cron: $script");

    try {
        $processor = null;
        switch ($script) {
          case 'order-create':
            $processor = new OrderCreateProcessor($script);
            break;
          default:
            break;
        }

        if ($processor) {
            $processor->processJobs();
        }
    } catch (Exception $e) {
        Log::commit('cron', "$script exception: ".$e->getMessage());
    }

  }
}

?>
