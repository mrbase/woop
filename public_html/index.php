<?php 

/*
 * This file is part of Woop.
 *
 * (c) Ulrik Nielsen <ulrik@bellcom.dk>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require_once __DIR__.'/../src/autoload.php';
$app = require __DIR__.'/../src/app.php';

require __DIR__.'/../src/controllers.php';
$app->run();
