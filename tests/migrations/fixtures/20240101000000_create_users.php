<?php

return [
    'up' => function ($db) {
        $db->query('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
    },
    'down' => function ($db) {
        $db->query('DROP TABLE users');
    },
];
