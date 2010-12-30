<?php
/**
 * Redisent, a Redis interface for the modest
 *
 * @author Justin Poliey <jdp34@njit.edu>
 * @copyright 2009 Justin Poliey <jdp34@njit.edu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package Redisent
 */

/**
 * Throw protocol exceptions.
 */
class RedisentProtocolException extends Exception {}


/**
 * Wraps native Redis errors in friendlier PHP exceptions
 */
class RedisentException extends Exception {}

/**
 * Redisent, a Redis interface for the modest among us
 */
class Redisent {

  const CRLF = "\r\n";

  /**
   * Socket connection to the Redis server
   * @var resource
   * @access private
   */
  private $__sock;

  /**
   * Creates a Redisent connection to the Redis server on host {@link $host} and port {@link $port}.
   * @param string $host The hostname of the Redis server
   * @param integer $port The port number of the Redis server
   */
  function __construct($host, $port = 6379) {
    $this->__sock = fsockopen($host, $port, $errno, $errstr);
    if (!$this->__sock) {
      throw new RedisentProtocolException("Unable to connect to the server: {$errno} - {$errstr}");
    }
  }

  function __destruct() {
    fclose($this->__sock);
  }

  function __call($name, $args) {
    /* Build the Redis protocol command */

    // Pass the number of arguments.
    $command = '*' . (count($args) + 1) . Redisent::CRLF;

    // Start with the command name.
    $command .= '$' . strlen($name) . Redisent::CRLF . $name . Redisent::CRLF;

    foreach ($args as $arg) {
      $command .= '$' . strlen($arg) . Redisent::CRLF . $arg . Redisent::CRLF;
    }

    /* Open a Redis connection and execute the command */
    fwrite($this->__sock, $command);

    /* Parse the response based on the reply identifier */
    $type = fgetc($this->__sock);
    $response = fgets($this->__sock, 512);
    switch ($type) {
      /* Error reply */
      case '-':
        throw new RedisentException($response);
        break;
      /* Inline reply */
      case '+':
      $response= substr($response,0,-2);
        break;
      /* Bulk reply */
      case '$':
        $size = (int)$response;

        if ($size == -1) {
          $response = null;
          break;
        }
        $response = '';
        $to_read = $size;
        do {
          $block_size = min($to_read, 4096);
          if ($size) $response .= fread($this->__sock, $block_size);
          $to_read = $size - strlen($response);
        } while ($to_read > 0 && !feof($this->__sock)); // $size can be 0 in case the key is empty (which redis accepts)

        fread($this->__sock, 2); /* discard Redisent::CRLF */
        break;
      /* Multi-bulk reply */
      case '*':
        $count = $response;
        if ($count == -1) {
          return null;
        }
        $responses = array();
        for ($i = 0; $i < $count; $i++) {
          fgetc($this->__sock); /* discard $ */
          $size = (int) fgets($this->__sock, 512);

          if ($size == -1) {
            $responses[] = null;
            break;
          }
          $response = '';
          $to_read = $size;
          do {
            $block_size = min($to_read, 4096);
            if ($block_size) $response .= fread($this->__sock, $block_size); // $size can be 0 
            $to_read = $size - strlen($response);
          } while ($to_read > 0  && !feof($this->__sock)); 

          $responses[] = $response;

          fread($this->__sock, 2); /* discard Redisent::CRLF */
        }
        $response = $responses;
        break;
      /* Integer reply */
      case ':':
        $response = (int) $response;
        break;
      default:
        throw new RedisentProtocolException('Invalid server response');
        break;
    }
    /* Party on */
    return $response;
  }

}

/**
 * A wrapper class for Redisent allowing for  pre and post treatment 
 * Mostly: allows hgetall and info to return an associative array
 *
 * @package Redisent
 * @author Ori Pekelman
 */
class RedisentWrap extends redisent{
    function __call($name, $args) {
        $response = parent::__call($name, $args);
        $treated_response=null;
        switch (strtolower($name)){
            case 'hgetall': // hget all returns a hash
            foreach ($response as $key => $value){
                if (($key+2) % 2) $arrval= $value; else $arrkey=$value;
                if (isset($arrkey) && isset($arrval)) $treated_response[$arrkey]= $arrval;
                }
            $response=$treated_response;
            break;
            case 'info': // hget all returns text: a CRLF seprated list with ":" separated hash
            $response=explode(Redisent::CRLF, $response);
            foreach ($response as $value){
                if (strpos($value, ":")){
                    $res= explode(":", $value);
                    $treated_response[$res[0]]=$res[1];
                  }
                }
                $response=$treated_response;
            break;
            default:
            break;
        }
        return $response;
        
    }
    
}
