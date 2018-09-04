<?php

final class Connections {

  public static function Instance() {
      static $instance = null;
      if ($instance === null) {
          $instance = new Connections();
      }
      return $instance;
  }

  private function __construct() {

  }

  private $mysql = null;
  public function getMySqlConnection() {
    if (!$this->mysql){
        $config = Config::Instance();
        $this->mysql = new PDO( implode('', [
          'mysql:dbname=',
          $config->settings['queue']['mysql']['name'],
          ';host=',
          $config->settings['queue']['mysql']['server'],
          ';port=',
          $config->settings['queue']['mysql']['port']
        ]),
        $config->settings['queue']['mysql']['username'],
        $config->settings['queue']['mysql']['password']
      );
    }

    return $this->mysql;
  }

  private $infusionsoft = null;
  public function getInfusionsoftConnection() {
    if (!$this->infusionsoft){
      $config = Config::Instance();
      $this->infusionsoft = new Infusionsoft\Infusionsoft([
        'clientId'     => $config->settings['infusionsoft']['key'],
        'clientSecret' => $config->settings['infusionsoft']['secret'],
        'redirectUri'  => 'https://www.pacificcoast.com/',
      ]);
    }

    return $this->infusionsoft;
  }

}

?>
