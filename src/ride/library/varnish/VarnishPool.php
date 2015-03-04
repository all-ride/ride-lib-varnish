<?php

namespace ride\library\varnish;

use ride\library\varnish\exception\VarnishException;

/**
 * Varnish setup with a pool of servers
 */
class VarnishPool implements VarnishServer {

    /**
     * Array with the servers of the pool
     * @var array
     */
    protected $servers = array();

    /**
     * Flag to see if a command should be continued on the remaining servers
     * when one fails
     * @var boolean
     */
    protected $ignoreOnFail = false;

    /**
     * Gets a string representation of this pool
     * @return string
     */
    public function __toString() {
        return '[' . implode(',', array_keys($this->servers)) . ']';
    }

    /**
     * Sets whether a command should continue on the remaining servers when one
     * fails, no exceptions will be thrown
     */
    public function setIgnoreOnFail($ignoreOnFail) {
        $this->ignoreOnFail = $ignoreOnFail;
    }

    /**
     * Gets whether a command should continue on the remaining servers when one
     * fails, no exceptions will be thrown
     * @return boolean
     */
    public function willIgnoreOnFail() {
        return $this->ignoreOnFail;
    }

    /**
     * Adds a server to the pool
     * @param VarnishServer $server Instance of the server
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the server
     * already exists in this pool
     */
    public function addServer(VarnishServer $server) {
        $name = (string) $server;
        if (isset($this->servers[$name])) {
            throw new VarnishException('Could not add the server: already exists');
        }

        $this->servers[$name] = $server;
    }

    /**
     * Removes a single server from the pool
     * @param string $server String representation of the server
     * @return boolean True if the server was removed, false if it did not exist
     */
    public function removeServer($server) {
        if (!isset($this->servers[$server])) {
            return false;
        }

        unset($this->servers[$server]);

        return true;
    }

    /**
     * Gets a single server from the pool
     * @param string $server String representation of the server
     * @return VarnishServer|null Instance of the server if set, null otherwise
     */
    public function getServer($server) {
        if (!isset($this->servers[$server])) {
            return null;
        }

        return $this->servers[$server];
    }

    /**
     * Gets the servers from the pool
     * @return array
     */
    public function getServers() {
        return $this->servers;
    }

    /**
     * Checks if the cache process is running
     * @return boolean
     */
    public function isRunning() {
        if (!$this->servers) {
            return false;
        }

        foreach ($this->servers as $server) {
            if (!$server->isRunning()) {
                return false;
            }
        }

        return true;
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
        $status = false;

        foreach ($this->servers as $server) {
            try {
                if ($server->start()) {
                    $status = true;
                }
            } catch (VarnishException $exception) {
                if (!$this->ignoreOnFail) {
                    throw $exception;
                }
            }
        }

        return $status;
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
        $status = false;

        foreach ($this->servers as $server) {
            try {
                if ($server->stop()) {
                    $status = true;
                }
            } catch (VarnishException $exception) {
                if (!$this->ignoreOnFail) {
                    throw $exception;
                }
            }
        }

        return $status;
    }

    /**
     * Bans (purges) the cached objects which match the provided expression
     * @param string $expression Expression to ban
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed on one of the servers
     */
    public function ban($expression) {
        foreach ($this->servers as $server) {
            try {
                $server->ban($expression);
            } catch (VarnishException $exception) {
                if (!$this->ignoreOnFail) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Bans (purges) an URL and all pages underneath it
     * @param string $url URL to ban
     * @param string $recursive Set to true to ban everything starting with the
     * provided URL
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed on one of the servers
     */
    public function banUrl($url, $recursive = false) {
        foreach ($this->servers as $server) {
            try {
                $server->banUrl($url, $recursive);
            } catch (VarnishException $exception) {
                if (!$this->ignoreOnFail) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Bans (purges) multiple URLs
     * @param array $urls Array with a URL as value
     * @param string $recursive Set to true to ban everything starting with the
     * provided URLs
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed on one of the servers
     */
    public function banUrls(array $urls, $recursive = false) {
        foreach ($this->servers as $server) {
            try {
                $server->banUrls($urls, $recursive);
            } catch (VarnishException $exception) {
                if (!$this->ignoreOnFail) {
                    throw $exception;
                }
            }
        }
    }

}
