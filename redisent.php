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

  const CRLF = "\x0d\x0a";

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
      throw new Exception("{$errno} - {$errstr}");
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
        break;
      /* Bulk reply */
      case '$':
        $size = $response;

        if ($size == -1) {
          $response = null;
          break;
        }
        $response = '';
        $read = 0;
        do {
          $block_size = min($size - $read, 1024);
          $response .= fread($this->__sock, $block_size);
          $read += $block_size;
        } while ($read < $size);

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
          $read = 0;
          do {
            $block_size = min($size - $read, 1024);
            $response .= fread($this->__sock, $block_size);
            $read += $block_size;
          } while ($read < $size);
          $responses[] = $response;

          fread($this->__sock, 2); /* discard Redisent::CRLF */
        }
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
