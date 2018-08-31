<?php

include_once( dirname(__FILE__) . "/Log.php");
include_once( dirname(__FILE__) . "/SFTP.php");

class Helper {

  static function getUID($len=8) {
        $config = Config::Instance();
        $salt = $config->settings['middleware']['security']['salt'];

	    $hex = md5($salt . uniqid());
	    $pack = pack('H*', $hex);
	    $uid = preg_replace('/[^\da-z]/i', '', base64_encode($pack));

        $len = ($len < 4) ? 4 : $len;
        $len = ($len > 128) ? 128 : $len;

        while (strlen($uid) < $len)
            $uid = $uid . Helper::getUID(22);

	    return substr($uid, 0, $len);
	}

  static function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
  }

  static function getRequestHeaders() {
    $headers = array();
    foreach($_SERVER as $h=>$v)
        if(preg_match('/HTTP_(.+)/',$h,$hp))
            $headers[$hp[1]]=$v;
    return $headers;
  }

  static function isValidClient() {
      $config = Config::Instance();
      $headers = Helper::getRequestHeaders();

      $test = array(
          "ip"          => array(
              "list"    => $config->settings['security']['whitelist'],
              "client"  => Helper::getClientIP()
          ),
          "key"         => array(
              "list"    => $config->settings['security']['apikeys'],
              "client"  => isset($headers['HTTP_AUTH_KEY']) ? $headers['HTTP_AUTH_KEY'] : false
          )
      );

      foreach($test['ip']['list'] as $test_ip) {
          if ( substr($test['ip']['client'], 0, strlen($test_ip)) === $test_ip ) {
              foreach($test['key']['list'] as $test_key) {
                  if ( substr($test['key']['client'], 0, strlen($test_key)) === $test_key )
                    return true;
              }
          }
      }
      return false;
  }

  static function killRequest($log, $message, $header) {
        header($header);
        Log::commit($log, print_r(array(
            'RemoteAddress'   => Helper::getClientIP(),
            'RequestPath'     => implode('', array(
                'http',
                (isset($_SERVER['HTTPS']) ? 's' : ''),
                '://',
                $_SERVER['HTTP_HOST'],
                $_SERVER['REQUEST_URI']
            )),
            'Error' => $message
        ), true));
        die();
  }

  static function validateRequest() {
      if ($_SERVER['REQUEST_METHOD'] == "GET")
          Helper::killRequest( 'auth', "Method Not Allowed", 'HTTP/1.0 405 Method Not Allowed' );
      else if ( !Helper::isValidClient() )
          Helper::killRequest( 'auth', "Unauthorized", 'HTTP/1.0 401 Unauthorized' );
  }

  static function beautifyXmlString ($xml) {
    $xml = preg_replace ( '/(>)(<)(\/*)/', "$1\n$2$3", $xml );
    $token = strtok ( $xml, "\n" );
    $result = '';
    $pad = 0;
    $matches = array();

    while ( $token !== false ) {
        if ( preg_match ( '/.+<\/\w[^>]*>$/', $token, $matches ) ) :
            $indent = 0;
        elseif ( preg_match ( '/^<\/\w/', $token, $matches ) ) :
            $pad--;
        elseif ( preg_match ( '/^<\w[^>]*[^\/]>.*$/',
                 $token, $matches ) ) :
            $indent=1;
        else :
            $indent = 0;
        endif;

        $line = str_pad ( $token, strlen ( $token ) +
                $pad, "\t", STR_PAD_LEFT );
        $result .= $line . "\n";
        $token = strtok ( "\n" );
        $pad += $indent;
    }
    return $result;
  }

  static function beautifyJsonString($json) {
    $result = '';
    $level = 0;
    $in_quotes = false;
    $in_escape = false;
    $ends_line_level = NULL;
    $json_length = strlen( $json );

    for( $i = 0; $i < $json_length; $i++ ) {
        $char = $json[$i];
        $new_line_level = NULL;
        $post = "";
        if( $ends_line_level !== NULL ) {
            $new_line_level = $ends_line_level;
            $ends_line_level = NULL;
        }
        if ( $in_escape ) {
            $in_escape = false;
        } else if( $char === '"' ) {
            $in_quotes = !$in_quotes;
        } else if( ! $in_quotes ) {
            switch( $char ) {
                case '}': case ']':
                    $level--;
                    $ends_line_level = NULL;
                    $new_line_level = $level;
                    break;

                case '{': case '[':
                    $level++;
                case ',':
                    $ends_line_level = $level;
                    break;

                case ':':
                    $post = " ";
                    break;

                case " ": case "\t": case "\n": case "\r":
                    $char = "";
                    $ends_line_level = $new_line_level;
                    $new_line_level = NULL;
                    break;
            }
        } else if ( $char === '\\' ) {
            $in_escape = true;
        }
        if( $new_line_level !== NULL ) {
            $result .= "\n".str_repeat( "\t", $new_line_level );
        }
        $result .= $char.$post;
    }

    return $result;
  }

  static function toArray($obj) {
    if (is_object($obj)) $obj = (array)$obj;
    if (is_array($obj)) {
      $new = array();
      foreach ($obj as $key => $val) {
        $new[$key] = Helper::toArray($val);
      }
    } else {
      $new = $obj;
    }

    return $new;
  }

  static function sftpCommand($server, $port, $username, $password, $command, $remote, $local) {
    $response = null;
    $remote = "/.$remote";
    try {
      $sftp = new SFTPConnection($server, $port);
      $sftp->login($username, $password);

      switch ($command) {
        case 'download':
          $sftp->receiveFile($remote, "files/".$local);
          $response = $local;
          break;
        case 'list':
          $response = $sftp->scanFilesystem($remote);
          break;
        default:
          return false;
          break;
      }
    }
    catch (\Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
    return $response;
  }

  static function sftpCommandOracle($command, $remote, $local) {
    $config = Config::Instance();
    return Helper::sftpCommand(
      $config->settings['oracle']['ssh']['server'],
      $config->settings['oracle']['ssh']['port'],
      $config->settings['oracle']['ssh']['username'],
      $config->settings['oracle']['ssh']['password'],
      $command,
      $remote,
      $local
    );
  }

}

?>
