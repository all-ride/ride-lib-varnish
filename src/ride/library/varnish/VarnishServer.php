<?php

namespace ride\library\varnish;

/**
 * Interface for a Varnish setup
 */
interface VarnishServer {

    /**
     * Checks if the cache process is running
     * @return boolean
     */
    public function isRunning();

    /**
     * Starts the cache process
     * @return boolean True if the process was started, false if it was already
     * running
     * @throws \ride\library\varnish\exception\VarnishException when the cache
     * process could not be started
     * @see isRunning
     */
    public function start();

    /**
     * Stops the cache process
     * @return boolean True if the process was stopped, false if it was not
     * running
     * @throws \ride\library\varnish\exception\VarnishException when the cache
     * process could not be stopped
     * @see isRunning
     */
    public function stop();

    /**
     * Bans (purges) the cached objects which match the provided expression
     * @param string $expression Expression to ban
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed on one of the servers
     */
    public function ban($expression);

    /**
     * Bans (purges) an URL and all pages underneath it
     * @param string $url URL to ban
     * @param string $recursive Set to true to ban everything starting with the
     * provided URL
     * @return null
     * @throws \ride\library\varnish\exception\VarnishException when the command
     * could not be executed on one of the servers
     */
    public function banUrl($url, $recursive = false);

    /**
     * Bans (purges) multiple URLs
     * @param array $urls Array with a URL as value
     * @param string $recursive Set to true to ban everything starting with the
     * provided URLs
     * @return null
     */
    public function banUrls(array $urls, $recursive = false);

}
