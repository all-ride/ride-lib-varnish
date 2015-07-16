<?php

namespace ride\library\varnish\exception;

use \Exception;

/**
 * Exception thrown by the Varnish library
 */
class VarnishException extends Exception {

    /**
     * Response body of the command causing this exception
     * @var string
     */
    private $response;

    /**
     * Constructs a new varnish exception
     * @param string $message
     * @param integer $code
     * @param Exception $previous
     * @param string $response
     * @return null
     */
    public function __construct($message, $code = 0, Exception $previous = null, $response = null) {
        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }

    /**
     * Gets the response body of the command causing this exception
     * @return string
     */
    public function getResponse() {
        return $this->response;
    }

}
