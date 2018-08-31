<?php

include_once( dirname(__FILE__) . "/Config.php");

class Log {

  static function commit($type, $message) {
    $message = array( date(DATE_ATOM), ":", $message );

    $backtrace = debug_backtrace();
    if ( !empty($backtrace[0]) && is_array($backtrace[0]) )
      array_merge( $message, array( "@", $backtrace[0]['file'], "ln", $backtrace[0]['line'] ) );

    error_log( implode(' ', $message)."\n", 3, Config::Instance()->settings['logs']['path'][$type] );
  }

  static function error($message) {
    Log::commit('error', $message);
  }

  static function debug($message) {
    if (Config::Instance()->settings['logs']['debug'] == 1)
        Log::commit('debug', $message);
  }

}

?>
