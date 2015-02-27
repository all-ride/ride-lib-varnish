<?php

namespace ride\library\varnish;

use ride\library\varnish\exception\VarnishException;

use \Exception;

/**
 * Direct connection with a Varnish server over TCP/IP
 */
class VarnishAdmin {

    /**
     * Handler of the server connection
     * @var resource
     */
    protected $handle;

    /**
     * Hostname or IP address of the server
     * @var string
     */
    protected $server;

    /**
     * Port the server listens to, usually 6082
     * @var integer
     */
    protected $port;

    /**
     * Secret to use in authentication challenge
     * @var string
     */
    protected $secret;

    /**
     * Constructs a new Varnish admin
     * @param string $server Hostname or IP address of the server
     * @param integer $port Port the server listens to
     * @param string $secret Secret string for authentication
     * @return null
     */
    public function __construct($server = '127.0.0.1', $port = 6082, $secret = null) {
        $this->server = $server;
        $this->port = $port;
        $this->secret = $secret;
    }

    /**
     * Connects to the varnish server
     * @param integer $timeout Timeout in seconds
     * @return string|boolean The banner of Varnish if just connected, true if
     * already connected
     * @throws \ride\library\varnish\exception\VarnishException when the
     * connection could not be made
     */
    public function connect($timeout = 5) {
        if ($this->handle) {
            return true;
        }

        $this->handle = fsockopen($this->server, $this->port, $errorNumber, $errorMessage, $timeout);
        if (!is_resource($this->handle)) {
            $this->handle = null;

            throw new VarnishException('Could not connect to ' . $this->server . ' on port ' . $this->port . ': ' . $errorMessage);
        }

        // set socket options
        stream_set_blocking($this->handle, 1);
        stream_set_timeout($this->handle, $timeout);

        // connecting should give us the varnishadm banner with a 200 code, or
        // 107 for auth challenge
        $banner = $this->read($statusCode);
        if ($statusCode === 107) {
            if (!$this->secret) {
                throw new VarnishException('Could not connect to ' . $this->server . ' on port ' . $this->port . ': Authentication is required and there is no secret set, call setSecret() first');
            }

            try {
                $challenge = substr($banner, 0, 32);
                $challengeResponse = hash('sha256', $challenge . "\n" . $this->secret . "\n" . $challenge . "\n");

                $banner = $this->execute('auth ' . $challengeResponse, $statusCode, 200);
            } catch (Exception $exception){
                throw new VarnishException('Could not connect to ' . $this->server . ' on port ' . $this->port  . ': Authentication failed', 0, $exception);
            }
        }

        if ($statusCode !== 200) {
            throw new VarnishException('Could not connect to ' . $this->server . ' on port ' . $this->port . ': Bad response');
        }

        return $banner;
    }

    /**
     * Disconnects from the Varnish server
     * @return null
     */
    public function disconnect() {
        if ($this->handle) {
            fclose($this->handle);

            $this->handle = null;
        }
    }

    /**
     * Reads from the server connection
     * @param integer $statusCode Status code of the response by reference
     * @return string Message of the response
     * @throws \ride\library\varnish\exception\VarnishException when the read
     * failed
     */
    protected function read(&$statusCode = null) {
        // get bytes until we have either a response code and message length or
        // an end of file.
        // code should be on first line, so we should get it in one chunk
        while (!feof($this->handle)) {
            $response = fgets($this->handle, 1024);
            if (!$response) {
                $meta = stream_get_meta_data($this->handle);
                if ($meta['timed_out']) {
                    throw new VarnishException('Could not read from ' . $this->server . ' on port ' . $this->port . ': Connection timed out');
                }
            }

            if (preg_match('/^(\d{3}) (\d+)/', $response, $matches)) {
                $statusCode = (int) $matches[1];
                $responseLength = (int) $matches[2];

                break;
            }
        }

        if (is_null($statusCode)) {
            throw new VarnishException('Could not read from ' . $this->server . ' on port ' . $this->port . ': No response status code received');
        }

        $response = '';
        while (!feof($this->handle) && strlen($response) < $responseLength) {
            $response .= fgets($this->handle, 1024);
        }

        return $response;
    }

    /**
     * Writes to the server connection
     * @param string $payload Payload to write
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the write
     * failed
     */
    protected function write($payload) {
        if (!$payload) {
            throw new VarnishException('Could not write to ' . $this->server . ' on port ' . $this->port . ': Empty payload provided');
        }

        $bytes = fwrite($this->handle, $payload);
        if ($bytes !== strlen($payload)) {
            throw new VarnishException('Could not write to ' . $this->server . ' on port ' . $this->port . ': Unable to write payload to the connection');
        }
    }

    /**
     * Writes a command to the server and reads the response
     * @param string $command Command to execute
     * @param integer $statusCode Status code of the response
     * @param integer $requiredStatusCode Expected
     * @return string
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function execute($command, &$statusCode = null, $requiredStatusCode = 200) {
        if (!$this->handle) {
            $this->connect();
        }

        $this->write($command . "\n");

        $response = $this->read($statusCode);
        if ($requiredStatusCode !== null && $statusCode !== $requiredStatusCode) {
            $response = implode("\n > ", explode("\n", trim($response)));

            throw new VarnishException('Could not execute command ' . $command . ': Command returned code ' . $statusCode . ' (' . $response . ')');
        }

        return $response;
    }

    /**
     * Executes the quit command
     * @return null
     */
    public function quit(){
        try {
            $this->execute('quit', $statusCode, 500);
        } catch (VarnishException $exception) {

        }

        $this->disconnect();
    }

    /**
     * Checks if the cache process is running
     * @return boolean
     */
    public function isRunning(){
        try {
            $response = $this->execute('status');
            if (strpos($response, 'Child in state ') !== 0) {
                return false;
            }

            $state = trim(substr($response, 15));

            return $state === 'running';
        } catch (VarnishException $exception) {
            return false;
        }
    }

    /**
     * Starts the cache process
     * @return boolean True if the process was started, false if it was already
     * running
     * @throws \ride\library\varnish\exception\VarnishException when the cache
     * process could not be started
     * @see isRunning
     */
    public function start() {
        if ($this->isRunning()) {
            return false;
        }

        $this->execute('start');

        return true;
    }

    /**
     * Stops the cache process
     * @return boolean True if the process was stopped, false if it was not
     * running
     * @throws \ride\library\varnish\exception\VarnishException when the cache
     * process could not be stopped
     * @see isRunning
     */
    public function stop() {
        if (!$this->isRunning()) {
            return false;
        }

        $this->execute('stop');

        return true;
    }

    /**
     * Gets a list of all loaded configurations
     * @return array Array with the name of the configuration as key and a
     * boolean as value to state if it's active or not
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function getVclList() {
        $list = array();

        $response = $this->execute('vcl.list');

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }

            $tokens = explode(' ', $line);

            $name = array_pop($tokens);

            $list[$name] = $tokens[0] == 'active';
        }

        return $list;
    }

    /**
     * Compiles and loads a configuration file under the provided name
     * @param string $name Name for the configuration
     * @param string $file Path to the configuration file on the system of the
     * Varnish server
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function loadVclFromFile($name, $file) {
        $this->execute('vcl.load ' . $name . ' ' . $file);
    }

    /**
     * Compiles and loads a configuration under the provided name
     * @param string $name Name for the configuration
     * @param string $configuration VCL configuration to compile
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function loadVclFromData($name, $configuration) {
        $this->execute('vcl.inline ' . $name . ' "' . addslashes($configuration)) . '"';
    }

    /**
     * Switches to the named configuration
     * @param string $name Name of the configuration to use
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function useVcl($name) {
        $this->execute('vcl.use ' . $name);
    }

    /**
     * Bans (purges) the cached objects which match the provided expression
     * @param string $expression Expression to ban
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function ban($expression) {
        $this->execute('ban ' . $expression);
    }

    /**
     * Bans (purges) an URL and all pages underneath it
     * @param string $url URL to ban
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     * @return null
     */
    public function banUrl($url) {
        $parts = parse_url($url);

        $host = $parts['host'];
        if (!isset($parts['host'])) {
            throw new VarnishException('Invalid URL provided: no host set');
        }

        if (isset($parts['path'])) {
            $path = $parts['path'];
        } else {
            $path = '/';
        }

        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $expression = 'req.http.host ~ ' . $parts['host'] . ' && req.url ~ ' . $path;

        return $this->ban($expression);
    }

}
