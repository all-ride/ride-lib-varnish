<?php

namespace ride\library\varnish;

use ride\library\log\Log;
use ride\library\varnish\exception\VarnishException;

use \Exception;

/**
 * Administration of a single Varnish server
 */
class VarnishAdmin implements VarnishServer {

    /**
     * Source of log messages
     * @var string
     */
    const LOG_SOURCE = 'varnish';

    /**
     * Hostname or IP address of the server
     * @var string
     */
    protected $host;

    /**
     * Port the server listens to, usually 6082
     * @var integer
     */
    protected $port;

    /**
     * Secret to use for authentication
     * @var string
     */
    protected $secret;

    /**
     * Instance of the log
     * @var \ride\library\log\Log
     */
    protected $log;

    /**
     * Handler of the server connection
     * @var resource
     */
    protected $handle;

    /**
     * Constructs a new instance
     * @param string $host Hostname or IP address of the server
     * @param integer $port Port the server listens to
     * @param string $secret Secret string for authentication
     * @return null
     */
    public function __construct($host = '127.0.0.1', $port = 6082, $secret = null) {
        $this->host = $host;
        $this->port = $port;
        $this->secret = $secret;
    }

    /**
     * Destructs this instance
     * @return null
     */
    public function __destruct() {
        if ($this->isConnected()) {
            $this->quit();
        }
    }

    /**
     * Gets a string representation of this server
     * @return string
     */
    public function __toString() {
        return $this->host . ':' . $this->port;
    }

    /**
     * Sets the log instance
     * @param \ride\library\log\Log $log
     * @return null
     */
    public function setLog(Log $log) {
        $this->log = $log;
    }

    /**
     * Gets the host of this server
     * @return string Hostname or IP address
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * Gets the port of this server
     * @return integer
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * Sets the server to authenticate with this server
     * @param string $secret Secret to use for authentication
     * @return null
     */
    public function setSecret($secret) {
        $this->secret = $secret;
    }

    /**
     * Gets the secret of this server
     * @return string|null
     */
    public function getSecret() {
        return $this->secret;
    }

    /**
     * Gets whether the connection is active
     * @return boolean True when connected, false otherwise
     */
    public function isConnected() {
        return $this->handle !== null;
    }

    /**
     * Connects to the varnish server
     * @param integer $timeout Timeout in seconds
     * @return string|boolean Banner of Varnish if just connected, true if
     * already connected
     * @throws \ride\library\varnish\exception\VarnishException when the
     * connection could not be made
     */
    public function connect($timeout = 5) {
        if ($this->isConnected()) {
            return true;
        }

        $this->handle = @fsockopen($this->host, $this->port, $errorNumber, $errorMessage, $timeout);
        if (!is_resource($this->handle)) {
            $this->handle = null;

            throw new VarnishException('Could not connect to ' . $this->host . ' on port ' . $this->port . ': ' . $errorMessage);
        }

        // set socket options
        stream_set_blocking($this->handle, 1);
        stream_set_timeout($this->handle, $timeout);

        // connecting should give us the varnishadm banner with a 200 code, or
        // 107 for auth challenge
        $banner = $this->read($statusCode);
        if ($statusCode === 107) {
            if (!$this->secret) {
                $this->disconnect();

                throw new VarnishException('Could not connect to ' . $this->host . ' on port ' . $this->port . ': Authentication is required and there is no secret set, call setSecret() first');
            }

            try {
                $challenge = substr($banner, 0, 32);
                $challengeResponse = hash('sha256', $challenge . "\n" . $this->secret . "\n" . $challenge . "\n");

                $banner = $this->execute('auth ' . $challengeResponse, $statusCode, 200);
            } catch (Exception $exception){
                $this->disconnect();

                throw new VarnishException('Could not connect to ' . $this->host . ' on port ' . $this->port  . ': Authentication failed', 0, $exception);
            }
        }

        if ($statusCode !== 200) {
            $this->disconnect();

            throw new VarnishException('Could not connect to ' . $this->host . ' on port ' . $this->port . ': Bad response');
        }

        return $banner;
    }

    /**
     * Disconnects from the Varnish server
     * @return null
     */
    public function disconnect() {
        if ($this->isConnected()) {
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
                    throw new VarnishException('Could not read from ' . $this->host . ' on port ' . $this->port . ': Connection timed out');
                }
            }

            if (preg_match('/^(\d{3}) (\d+)/', $response, $matches)) {
                $statusCode = (int) $matches[1];
                $responseLength = (int) $matches[2];

                break;
            }
        }

        if (is_null($statusCode)) {
            throw new VarnishException('Could not read from ' . $this->host . ' on port ' . $this->port . ': No response status code received');
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
            throw new VarnishException('Could not write to ' . $this->host . ' on port ' . $this->port . ': Empty payload provided');
        }

        $bytes = fwrite($this->handle, $payload);
        if ($bytes !== strlen($payload)) {
            throw new VarnishException('Could not write to ' . $this->host . ' on port ' . $this->port . ': Unable to write payload to the connection');
        }
    }

    /**
     * Writes a command to the server and reads the response
     * @param string $command Command to execute
     * @param integer $statusCode Status code of the response
     * @param integer $requiredStatusCode Expected status code of the response
     * @return string
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function execute($command, &$statusCode = null, $requiredStatusCode = 200) {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if ($this->log) {
            $this->log->logDebug('Executing command on ' . $this->host . ':' . $this->port, $command, self::LOG_SOURCE);
        }

        $this->write($command . "\n");
        $response = $this->read($statusCode);

        if ($this->log) {
            $this->log->logDebug('Received response from ' . $this->host . ':' . $this->port, $statusCode, self::LOG_SOURCE);
        }

        if ($requiredStatusCode !== null && $statusCode !== $requiredStatusCode) {
            $response = implode("\n > ", explode("\n", trim($response)));

            throw new VarnishException('Could not execute command on ' . $this->host . ' with port ' . $this->port . ': Command `' . $command . '` returned code ' . $statusCode, 0, null, $response);
        }

        return $response;
    }

    /**
     * Pings the server
     * @return integer Timestamp of the server
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function ping() {
        $response = $this->execute('ping');

        $tokens = explode(' ', $response);

        return $tokens[1];
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
    public function isRunning() {
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

        $lines = explode("\n", $response);
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
     * Gets the VCL of the provided configuration
     * @param string $name Name of the configuration
     * @return string VCL configuration
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function getVcl($name) {
        return $this->execute('vcl.show ' . $name);
    }

    /**
     * Gets the active VCL
     * @param array $vclList Array with the name of the configuration as key
     * and a boolean status as value. When not provided, it will be fetched.
     * @return string Active VCL configuration
     * @see getVclList()
     */
    public function getActiveVcl(array $vclList = null) {
        if ($vclList === null) {
            $vclList = $this->getVclList();
        }

        foreach ($vclList as $name => $state) {
            if (!$state) {
                continue;
            }

            return $this->getVcl($name);
        }

        return null;
    }

    /**
     * Compiles and loads a configuration file under the provided name
     * @param string $file Path to the configuration file on the system of the
     * Varnish server
     * @param string $name Name for the configuration
     * @return string Name of the configuration
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function loadVclFromFile($file, $name = null) {
        if (!$name) {
            $name = $this->generateConfigurationName();
        }

        $this->execute('vcl.load ' . $name . ' ' . $file);

        return $name;
    }

    /**
     * Compiles and loads a configuration under the provided name
     * @param string $configuration VCL configuration to compile
     * @param string $name Name for the configuration
     * @return string Name of the configuration
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function loadVclFromConfiguration($configuration, $name = null) {
        if (!$name) {
            $name = $this->generateConfigurationName();
        }

        $this->execute('vcl.inline ' . $name . ' "' . addslashes($configuration) . '"');

        return $name;
    }

    /**
     * Generates a new configuration name based on the provided parameters
     * @param array $vclList Array with the name of the configuration as key
     * and a boolean status as value. When not provided, it will be fetched.
     * @param string $prefix Prefix for the configuration name
     * @return string Configuration name for a new vcl
     * @see getVclList()
     */
    protected function generateConfigurationName(array $vclList = null, $prefix = 'load') {
        if (!$vclList) {
            $vclList = $this->getVclList();
        }

        $index = 1;

        foreach ($vclList as $name => $status) {
            if (strpos($name, $prefix) !== 0) {
                continue;
            }

            $nameIndex = substr($name, strlen($prefix));
            if (!is_numeric($nameIndex)) {
                continue;
            }

            if ($nameIndex >= $index) {
                $index = $nameIndex + 1;
            }
        }

        return $prefix . $index;
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
     * Loads and switches the current configuration from the provided file
     * @param string $file Path to the configuration file on the system of the
     * Varnish server
     * @param string $name Name for the configuration
     * @return string Name of the configuration
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function loadAndUseVclFromFile($file, $name = null) {
        $name = $this->loadVclFromFile($file, $name);

        $this->useVcl($name);
    }

    /**
     * Discards a previously loaded configuration
     * @param string $name Name of the configuration
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function discardVcl($name) {
        $this->execute('vcl.discard ' . $name);
    }

    /**
     * Sets a parameter
     * @param string $name Name of the parameter
     * @param mixed $value Value for the parameter
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function setParameter($name, $value) {
        $this->execute('param.set ' . $name . ' ' . $value);
    }

    /**
     * Gets the last panic
     * @return string|boolean Panic message if occured, false otherwise
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function getPanic() {
        $panic = $this->execute('panic.show', $statusCode);
        if ($statusCode == 300) {
            return false;
        }

        return $panic;
    }

    /**
     * Clears the last panic
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function clearPanic() {
        $this->execute('panic.clear');
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
     * @param string $recursive Set to true to ban everything starting with the
     * provided URL
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function banUrl($url, $recursive = false) {
        $parts = parse_url($url);

        $host = $parts['host'];
        if (!isset($parts['host'])) {
            throw new VarnishException('Invalid URL provided: no host set');
        }

        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        if (isset($parts['path'])) {
            $path = $parts['path'];
        } else {
            $path = '/';
        }

        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $host = $this->escapeForRegex($host);
        $path = $this->escapeForRegex($path);

        $expression = 'req.http.host ~ "^(?i)' . $host . '$" && req.url ~ "^' . $path . (!$recursive ? '$' : '') . '"';

        return $this->ban($expression);
    }

    /**
     * Escapes a scalar value to use as regex
     * @param string $regex Scalar value
     * @return Escaped regex value
     */
    protected function escapeForRegex($regex) {
        // $regex = str_replace('.', '\\.', $regex);
        $regex = str_replace('?', '\\?', $regex);
        $regex = str_replace('[', '\\[', $regex);
        $regex = str_replace(']', '\\]', $regex);

        return $regex;
    }

    /**
     * Bans (purges) multiple URLs
     * @param array $urls Array with a URL as value
     * @param string $recursive Set to true to ban everything starting with the
     * provided URLs
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed
     */
    public function banUrls(array $urls, $recursive = false) {
        foreach ($urls as $url) {
            $this->banUrl($url, $recursive);
        }
    }

}
