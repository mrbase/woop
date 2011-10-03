<?php

/*
 * This file is part of Woop.
 *
 * (c) Ulrik Nielsen <ulrik@bellcom.dk>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require_once __DIR__.'/../vendor/Silex/vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
  'Woop'   => __DIR__,
  'Symfony' => array(__DIR__.'/../vendor/Silex/vendor', __DIR__.'/../vendor'),
  'Silex'   => __DIR__.'/../vendor/Silex/src',
));
$loader->registerPrefixes(array(
  'Pimple' => __DIR__.'/../vendor/Silex/vendor/pimple/lib',
  'Twig_'  => __DIR__.'/../vendor/Silex/vendor/twig/lib',
));
$loader->register();
