# Ride: Varnish Library

Varnish library of the PHP Ride framework.

## What's In This Library

### VarnishServer

The _VarnishServer_ interface is used to manipulate a single Varnish server or a pool of servers transparantly.

### VarnishAdmin

The _VarnishAdmin_ class connects to a single Varnish server directly to send commands.

### VarnishPool

The _VarnishPool_ class can be used to create a pool of different _VarnishAdmin_ instances.
All commands of the _VarnishServer_ interface will be invoked on all available servers in the pool.

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
```

### Implementations

For more examples, you can check the following implementations of this library:
- [ride/app-varnish](https://github.com/all-ride/ride-app-varnish)
- [ride/wba-varnish](https://github.com/all-ride/ride-wba-varnish)
- [ride/wba-cms-varnish](https://github.com/all-ride/ride-wba-cms-varnish)
- [ride/web-cms-varnish](https://github.com/all-ride/ride-web-cms-varnish)

## Installation

You can use [Composer](http://getcomposer.org) to install this library.

```
composer require ride/lib-varnish
```
