<?php

return function ($db) {
    $db->create('users', ['name' => 'foo']);
};
