<?php

/* settings/database.php */

return array(
    'mysql' => array(
        'dbdriver' => 'mysql',
        'username' => 'root',
        'password' => '',
        'dbname' => 'repair',
        'prefix' => 'erepair',
    ),
    'tables' => array(
        'user' => 'user',
        'category' => 'category',
        'language' => 'language',
        'repair' => 'repair',
        'inventory' => 'inventory',
    ),
);
