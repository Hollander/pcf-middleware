<?php

final class Config {

  var $settings = array();

  public static function Instance() {
      static $instance = null;
      if ($instance === null) {
          $instance = new Config();

          $instance->settings = parse_ini_file("config.ini", true);
      }
      return $instance;
  }

  private function __construct() {

  }

}

?>
