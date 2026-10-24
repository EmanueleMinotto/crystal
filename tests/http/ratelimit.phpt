--TEST--
Rate limiting middleware based on the cache service
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf->testing();

$mf('cache')->setDriver(new class () {
    private $store = array();

    public function get($key, $default = null)
    {
        return isset($this->store[$key]) ? $this->store[$key] : $default;
    }

    public function set($key, $value, $ttl = null)
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete($key)
    {
        unset($this->store[$key]);

        return true;
    }

    public function has($key)
    {
        return isset($this->store[$key]);
    }

    public function clear()
    {
        $this->store = array();

        return true;
    }
});

$mf->get('/search', function () {
    return array('results' => array());
}, array('middleware' => array($mf('ratelimit')->middleware(array(
    'limit' => 2,
    'window' => 3600,
    'key' => function ($request) {
        return $request->header('X-Client');
    },
)))));

for ($i = 1; $i <= 3; $i++) {
    $response = $mf->handle('GET', '/search', array('headers' => array('X-Client' => 'a')));
    echo $response->getStatusCode().' limit='.$response->getHeaderLine('X-RateLimit-Limit')
        .' remaining='.$response->getHeaderLine('X-RateLimit-Remaining')
        .' retry='.json_encode($response->hasHeader('Retry-After'))
        .' reset='.json_encode((int) $response->getHeaderLine('X-RateLimit-Reset') > time()).PHP_EOL;
}

// other clients have their own counter
echo $mf->handle('GET', '/search', array('headers' => array('X-Client' => 'b')))->getStatusCode().PHP_EOL;

// requests without an identifier aren't limited
echo $mf->handle('GET', '/search')->getStatusCode().PHP_EOL;

?>
--EXPECT--
200 limit=2 remaining=1 retry=false reset=true
200 limit=2 remaining=0 retry=false reset=true
429 limit=2 remaining=0 retry=true reset=true
200
200
