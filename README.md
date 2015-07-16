# Ride: Varnish Library

Varnish library of the PHP Ride framework.

## Code Sample

Check this code sample to see some of this library's functionality:

```php
<?php

use ride\library\varnish\exception\V arnishException;
use ride\library\varnish\VarnishAdmin;
use ride\library\varnish\VarnishPool;

try {
    // create a single server
    $varnish = new VarnishAdmin('localhost', 6082, 'your-secret');
    
    // check if worker process is running
    $varnish->isRunning(); // true | false
    
    // start the cache process, this will call isRunning() internally
    $varnish->start();
    
    // stop the cache process, this will call isRunning() internally
    $varnish->stop();
    
    // ban with a URL and everything underneath it
    $varnish->banUrl('http://example.com/path', true);
    
    // ban with an expression
    $varnish->ban('req.http.host == "example.com" && req.url == "/path/to/page"');
    
    // create a pool of servers
    $pool = new VarnishPool();
    $pool->addServer($varnish);
    $pool->addServer(new VarnishAdmin('example.com', 6082, 'sneaky sneaky');
    
    // ban with a URL or with an expression on all servers
    $pool->banUrl('http://example.com/path');
    $pool->ban('req.http.host == "example.com" && req.url == "/path/to/page"');
} catch (VarnishException $exception) {
    // something went wrong
}
