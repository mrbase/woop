<?php

/*
 * This file is part of Woop.
 *
 * (c) Ulrik Nielsen <ulrik@bellcom.dk>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Silex\Application;
use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\Response;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\SymfonyBridgesServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

$app = new Application();
$app->register(new SessionServiceProvider());
$app->register(new SymfonyBridgesServiceProvider());
$app->register(new UrlGeneratorServiceProvider());

$app->register(new TwigServiceProvider(), array(
  'twig.path' => __DIR__.'/templates',
  'twig.configure' => $app->protect(function ($twig) use ($app) {
    #$twig->setCache($app['twig.cache.path']);
    $twig->setCache(false);
    $twig->addGlobal('bodytag', 'bt-' . basename($app['request']->getRequestUri()));
    $twig->addGlobal('http_host', $_SERVER['HTTP_HOST']);
    
    if ($app['session']->hasFlash('message')) {
      $twig->addGlobal('message', $app['session']->getFlash('message'));
    }
  }),
));

$app->error(function (\Exception $e, $code) use ($app) {
    error_log(__FILE__ . ' ' . __LINE__ . "\n" . print_r($e->getMessage(), 1));
  if ($app['debug']) {
    return;
  }

  $error = 404 == $code ? $e->getMessage() : null;
  return new Response($app['twig']->render('error.twig', array('error' => $error)), $code);
});

// salt for password hashing
$app['salt'] = '7348#¤%#-lkjl(-&;HKH._1';

$app['data.path'] = __DIR__ . '/../data';
$app['db.path'] = $app->share(function ($app) {
  if (!is_dir($app['data.path'])) {
    mkdir($app['data.path'], 0777, true);
  }

  return $app['data.path'] . '/woop.db';
});


$app['db.schema'] = <<<EOF
PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS account (
  username    TEXT,
  password    TEXT,
  email       TEXT UNIQUE,
  name        TEXT,
  token       TEXT,
  secret      TEXT,
  created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
  status      INTEGER DEFAULT 1,
  PRIMARY KEY (username)
);
CREATE TABLE IF NOT EXISTS link (
  id          INTEGER PRIMARY KEY ASC,
  username    TEXT,
  link        TEXT,
  title       TEXT,
  created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (username) REFERENCES account(username)
);
EOF;

$app['db'] = $app->share(function () use ($app) {
  $db = new \SQLite3($app['db.path']);
  $db->busyTimeout(1000);
  $db->exec($app['db.schema']);

  return $db;
});

return $app;
