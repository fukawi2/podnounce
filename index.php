<?php

// set our working directory to the base of index.php
chdir(__DIR__);

// Kickstart composer autoloader
require_once 'vendor/autoload.php';
$f3 = \Base::instance();
$f3->set('PACKAGE', 'PodNounce');
$f3->set('AUTOLOAD', 'app/');
$f3->set('UI', 'views/');
$f3->set('UPLOADS', 'uploads/');
$f3->config('app/routes.cfg');
$f3->config('podnounce.conf');

/*
 * password_hash() and password_verify() were introduced in version 5.5 so if
 * our version is too old, we use a compat file to emulate those functions
 */
if (version_compare(PHP_VERSION, '5.5.0') < 0)
  require(__DIR__.'/app/password.php');

// establish connection to database
$pdostr = sprintf('pgsql:host=%s;port=%s;dbname=%s',
                $f3->get('database.host'),
                $f3->get('database.port'),
                $f3->get('database.dbname')
);
$f3->set('DB', new DB\SQL($pdostr, $f3->get('database.username'), $f3->get('database.password')));

// TODO: redirec to install page if DB is empty

$f3->run();

?>
