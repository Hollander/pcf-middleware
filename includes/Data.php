<?php

include_once( dirname(__FILE__) . "/Config.php");
include_once( dirname(__FILE__) . "/Helper.php");
include_once( dirname(__FILE__) . "/Log.php");
include_once( dirname(__FILE__) . "/Connections.php");

class Data {

  static function prepare($sql) {
    $mysql  = Connections::Instance()->getMySqlConnection();

    $stm = $mysql->prepare($sql);
    if ($stm)
      return $stm;
    else {
      Log::error("PDO::errorInfo():");
      return false;
    }
  }

  static function insert($table, $data) {
    try {
      $stm = Data::prepare('INSERT INTO '.$table.' (`'.implode('`,`', array_keys($data)).'`) VALUES (:'.implode(',:', array_keys($data)).')');
      if (!$stm) { return false; }

      $stm->execute($data);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }

    return true;
  }

  static function update($table, $data, $query) {
    try {
      $update = array();
      foreach($data as $key => $value) {
        $update[] = "`$key`=:{$key}";
      }

      $where = array();
      foreach($query as $key => $value) {
        $where[] = "`$key`=:{$key}";
      }

      $stm = Data::prepare('UPDATE '.$table.' SET '.implode(',',$update).' WHERE '.implode(' AND ',$where));
      if (!$stm) { return false; }

      $stm->execute( array_merge($data, $query) );
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }

    return true;
  }

  static function select($table, $fields, $query) {
    $result = array();

    try {
      $where = array();
      foreach($query as $key => $value) {
        $where[] = "`$key`=:{$key}";
      }

      $stm = Data::prepare('SELECT '.implode(',',$fields).' FROM '.$table.' WHERE '.implode(' AND ',$where));
      if (!$stm) { return false; }

      $stm->execute( $query );
      $result = $stm->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }

    return $result;
  }

  static function replace($table, $data) {
    try {
      $update = array();
      $data2 = array();
      foreach($data as $key => $value) {
        $update[] = "`$key`=:{$key}2";
        $data2["{$key}2"] = $value;
      }

      $query = 'INSERT INTO `'.$table.'` (`'.implode('`,`', array_keys($data)).'`) VALUES (:'.implode(',:', array_keys($data)).') ON DUPLICATE KEY UPDATE '.implode(',',$update);
      $stm = Data::prepare($query);
      if (!$stm) { return false; }

      $data = array_merge($data,$data2);
      Log::debug("replace:\n$query\ndata:\n".print_r($data,true));

      $stm->execute($data);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }

    return true;
  }

}

?>
