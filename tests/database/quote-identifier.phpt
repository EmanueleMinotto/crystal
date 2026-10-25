--TEST--
Identifiers quoted using the syntax of each database
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

class FakeDriverPdo extends PDO
{
    public $driver;

    #[\ReturnTypeWillChange]
    public function getAttribute($attribute)
    {
        return PDO::ATTR_DRIVER_NAME === $attribute ? $this->driver : parent::getAttribute($attribute);
    }
}

$mf(function () use ($mf) {
    $db = $mf('database');
    $pdo = new FakeDriverPdo('sqlite::memory:');

    // the driver name is read once, by setPdo()
    foreach (['mysql', 'pgsql', 'sqlite', 'sqlsrv'] as $driver) {
        $pdo->driver = $driver;
        $db::setPdo($pdo);
        echo $driver.': '.$db->quoteIdentifier('users').' '.$db->quoteIdentifier('app.users').' '.$db->quoteIdentifier('we`ird"na]me')."\n";
    }
});

?>
--EXPECT--
mysql: `users` `app`.`users` `we``ird"na]me`
pgsql: "users" "app"."users" "we`ird""na]me"
sqlite: `users` `app`.`users` `we``ird"na]me`
sqlsrv: [users] [app].[users] [we`ird"na]]me]
