# Ride: Tokenizer Library

Varnish library of the PHP Ride framework.

## Code Sample

Check this code sample to see how to use this library:
    
    <?php
    
    use ride\library\varnish\exception\VarnishException;
    use ride\library\varnish\VarnishAdmin;
    
    $varnish = new VarnishAdmin('localhost', 6082, 'your-secret');
    
    try {
        // connect to the server
        $varnish->connect();
        
        // check if working process is running
        $varnish->isRunning(); // true | false
        
        // start the cache process, this will call isRunning() internally
        $varnish->start();
        
        // stop the cache process, this will call isRunning() internally
        $varnish->stop();
        
        // ban an expression
        $varnish->ban('req.http.host == "example.com" && req.url == '/path/to/page');
        
        // ban a url and all underlying pages
        $varnish->banUrl('http://example.com/path');
        
        // disconnect from the server
        $varnish->disconnect();
    } catch (VarnishException $exception) {
        // something went wrong
    }
