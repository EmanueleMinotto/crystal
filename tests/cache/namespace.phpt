--TEST--
Cache namespaces with every available driver
--FILE--
<?php
$mf = require_once(__DIR__.'/../../crystal.php');

$tmpDir = sys_get_temp_dir() . '/crystal_cache_namespace_' . getmypid();
mkdir($tmpDir, 0777, true);

$mf(function () use ($mf, $tmpDir) {
    $cache = $mf('cache');

    $drivers = [
        'array' => ['array', null],
        'filename' => ['filename', $tmpDir],
    ];

    if (extension_loaded('pdo_sqlite')) {
        $drivers['database'] = ['database', new PDO('sqlite::memory:')];
    }

    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $drivers['apcu'] = ['apcu', null];
    }

    foreach ($drivers as $name => $driver) {
        $cache::setDriver($driver[0], $driver[1]);
        $cache->clear();

        $users = $cache->withNamespace('users');
        $admins = $users->withNamespace('admins');
        $posts = $cache->withNamespace('posts');

        // same key, different namespaces
        $cache->set('name', 'root');
        $users->set('name', 'user');
        $admins->set('name', 'admin');
        $posts->set('name', 'post');

        // "users.name" is a valid root key and must not collide with users:name
        $cache->set('users.name', 'dotted');

        $values = [
            $cache->get('name'),
            $users->get('name'),
            $admins->get('name'),
            $posts->get('name'),
            $cache->get('users.name'),
        ];
        $multiple = $users->getMultiple(['name', 'missing'], 'none');

        // clearing a namespace clears its sub namespaces only
        $users->clear();

        $cleared = [
            $cache->get('name', 'none'),
            $users->get('name', 'none'),
            $admins->get('name', 'none'),
            $posts->get('name', 'none'),
            $cache->get('users.name', 'none'),
        ];

        // clearing the root clears everything
        $cache->clear();

        echo $name . ': ' . implode(',', $values) . ' | '
            . json_encode($multiple) . ' | '
            . implode(',', $cleared) . ' | '
            . ($posts->has('name') ? '1' : '0') . "\n";
    }

    // invalid namespaces
    foreach (['', 'a:b', 'a b', null] as $namespace) {
        try {
            $cache->withNamespace($namespace);
            echo "no exception\n";
        } catch (\InvalidArgumentException $e) {
            echo "InvalidArgumentException\n";
        }
    }

    // custom drivers without clearPrefix can't clear a namespace
    $cache::setDriver(new class () {
        public function get($key, $default = null) { return $default; }
        public function set($key, $value, $ttl = null) { return true; }
        public function delete($key) { return true; }
        public function clear() { return true; }
        public function has($key) { return false; }
    });

    try {
        $cache->withNamespace('users')->clear();
    } catch (\RuntimeException $e) {
        echo $e->getMessage() . "\n";
    }

    array_map('unlink', glob($tmpDir . '/*'));
    rmdir($tmpDir);
});
?>
--EXPECTREGEX--
array: root,user,admin,post,dotted \| {"name":"user","missing":"none"} \| root,none,none,post,dotted \| 0
filename: root,user,admin,post,dotted \| {"name":"user","missing":"none"} \| root,none,none,post,dotted \| 0
(database: root,user,admin,post,dotted \| {"name":"user","missing":"none"} \| root,none,none,post,dotted \| 0
)?(apcu: root,user,admin,post,dotted \| {"name":"user","missing":"none"} \| root,none,none,post,dotted \| 0
)?InvalidArgumentException
InvalidArgumentException
InvalidArgumentException
InvalidArgumentException
The cache driver does not support clearing a namespace
