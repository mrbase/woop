<?php
/*
 * This file is part of Woop.
 *
 * (c) Ulrik Nielsen <ulrik@bellcom.dk>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// front page
$app->get('/', function() use ($app) {
  return $app['twig']->render('index.twig');
})->bind('index');


// account stuff
$app->get('/account', function() use($app) {
  $user = $app['session']->get('user');

  if (!$user) {
    return $app->redirect('/');
  }

  $stmt = $app['db']->prepare("
    SELECT *
    FROM link
    WHERE username = :username
    ORDER BY created_at DESC
  ");
  $stmt->bindParam(':username', $user['username'], SQLITE3_TEXT);
  $result = $stmt->execute();

  $params = array( 'user' => $user );
  while ($record = $result->fetchArray(SQLITE3_ASSOC)) {
    $params['bookmarks'][] = $record;
  }

  return $app['twig']->render('account/index.twig', $params);
});


// fetch the account form
$app->get('/create-account', function() use($app) {
  $params = array();
  if ($app['session']->get('user')) {
    $params = $app['session']->get('user');
  }
  return $app['twig']->render('account/create.twig', $params);
});

$app->post('/create-account', function() use($app) {
  $stmt = $app['db']->prepare("
    SELECT *
    FROM account
    WHERE
      email = :email
      OR
        username = :username
  ");

  $username = $app['request']->get('username');
  $email = $app['request']->get('email');

  $stmt->bindParam(':email', $email, SQLITE3_TEXT);
  $stmt->bindParam(':username', $username, SQLITE3_TEXT);
  $result = $stmt->execute();

  $error = '';
  $errors = array();

  if ($result->fetchArray()) {
    $record = $result->fetchArray(SQLITE3_ASSOC);

    if ($record['username'] == $username) {
      $errors['username'] = 'Username already in use, pick another.';
    }
    if ($record['email'] == $email) {
      $errors['email'] = 'Email address already in use, please choose another or login using an existing account.';
    }
  }
  else {
    if (!$username) {
      $errors['username'] = 'Please enter your prefered username.';
    } elseif (!preg_match('/[a-z0-9]{2,12}/i', $username)) {
      $errors['username'] = 'Username can only consist of leters and numbers. And must be between 2 and 12 characters long.';
    }

    $password = $app['request']->get('password');
    if (strlen($password) < 5) {
      $errors['password'] = 'Your password must be at least 5 characters long.';
    }

    $name = $app['request']->get('name');
    if (!$name) {
      $errors['name'] = 'Please enter your real name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'The provided email address is not valid.';
    }

    $secret = $app['request']->get('secret');
    if (!preg_match('/[a-z0-9\-]{5,}/i', $secret)) {
      $errors['secret'] = 'The shared secret must be 5 characters long and can only consist of letters, numbers, spaces and hyphen.';
    }

    if (0 == count($errors)) {
      $token = uniqid();
      $stmt = $app['db']->prepare("
        INSERT INTO
          account (
            username,
            password,
            email,
            name,
            token,
            secret
          )
        VALUES (
          :username,
          :password,
          :email,
          :name,
          :token,
          :secret
      )");

      $stmt->bindParam(':username', $username, SQLITE3_TEXT);
      $stmt->bindParam(':password', hash('sha256', $app['salt'].$password), SQLITE3_TEXT);
      $stmt->bindParam(':email', $email, SQLITE3_TEXT);
      $stmt->bindParam(':name', $name, SQLITE3_TEXT);
      $stmt->bindParam(':token', $token, SQLITE3_TEXT);
      $stmt->bindParam(':secret', $secret, SQLITE3_TEXT);

      if ($stmt->execute()) {
        $app['session']->set('user', array(
          'username' => $username,
          'name' => $name,
          'email' => $email,
          'token' => $token,
          'secret' => $secret
        ));
        return $app->redirect('/account');
      }
    }
  }

  if (count($errors)) {
    $error = '<ul><li>' . implode('</li><li>', array_values($errors)) . '</li></ul>';
  }

  $app['session']->setFlash('message', array(
    'type' => 'error',
    'body' => 'The following errors prevented you from creating an account. Please correct the error(s) and try agian.',
    'fields' => $errors
  ));
  return $app->redirect('/create-account');
});

$app->get('/delete-account', function() use($app) {
  return $app['twig']->render('account/confirm_delete.twig');
});

$app->post('/delete-account', function() use($app) {
  $user = $app['session']->get('user');

  if (is_array($user)) {
    $stmt = $app['db']->prepare("DELETE FROM link WHERE username = :username");
    $stmt->bindParam(':username', $user['username'], SQLITE3_TEXT);
    $stmt->execute();

    $stmt = $app['db']->prepare("DELETE FROM account WHERE username = :username");
    $stmt->bindParam(':username', $user['username'], SQLITE3_TEXT);
    $stmt->execute();

    $app['session']->remove('user');
    $app['session']->setFlash('message', array(
      'type' => 'info',
      'body' => 'Your wOOp! account has been deleted - bye now.',
    ));
  }

  return $app->redirect('/');
});

$app->get('/purge-account', function() use($app) {
  return $app['twig']->render('account/confirm_purge.twig');
});
$app->post('/purge-account', function() use($app) {
  $user = $app['session']->get('user');
  if (is_array($user)) {
    $stmt = $app['db']->prepare("DELETE FROM link WHERE username = :username");
    $stmt->bindParam(':username', $user['username'], SQLITE3_TEXT);
    $stmt->execute();

    $app['session']->setFlash('message', array(
      'type' => 'info',
      'body' => 'Your wOOp! account has been purged.',
    ));
  }
  return $app->redirect('/account');
});

$app->match('/request-password', function() use($app) {});


// handle login
$app->get('/login', function() use($app) {
  // check if the user is already logged in.
  if ($app['session']->get('user')) {
    return $app->redirect('/account');
  }
  return $app['twig']->render('account/login.twig');
});
$app->post('/login', function() use($app) {
  if ($app['request']->getMethod() == 'POST') {
    $stmt = $app['db']->prepare("
      SELECT *
      FROM account
      WHERE
        username = :username
        AND
          password = :password
    ");
    $stmt->bindParam(':username', $app['request']->get('username'), SQLITE3_TEXT);
    $stmt->bindParam(':password', hash('sha256', $app['salt'].$app['request']->get('password')), SQLITE3_TEXT);
    $result = $stmt->execute();

    $user = $result->fetchArray(SQLITE3_ASSOC);
    if (is_array($user)) {
      $app['session']->set('user', $user);
      return $app->redirect('/account');
    }

    $app['session']->setFlash('message', array(
      'type' => 'error',
      'body' => 'Invalid username or password.',
    ));
    return $app->redirect('/login');
  }

  return $app['twig']->render('account/login.twig');
});

$app->match('/logoff', function() use($app) {
  if ($app['session']->get('user')) {
    $app['session']->remove('user');
  }
  return $app->redirect('/');
});

$app->match('/add/{version_id}/{key}/', function($version_id, $key) use($app) {

  $data = array(
    'url' => $app['request']->get('url'),
    'title' => $app['request']->get('title'),
    'key' => $key,
    'created_at' => time(),
  );

  $stmt = $app['db']->prepare("
    SELECT *
    FROM account
    WHERE token = :token AND secret = :secret
  ");
  list($token, $secret) = explode('.', $app['request']->get('key'));

  $stmt->bindParam(':token', $token, SQLITE3_TEXT);
  $stmt->bindParam(':secret', $secret, SQLITE3_TEXT);
  $result = $stmt->execute();

  $user = $result->fetchArray(SQLITE3_ASSOC);
  if (!is_array($user)) {
    $data = array(
      'status' => 403,
      'message' => 'Unknown account or secret.'
    );
  }
  else {

    $link = $app['request']->get('url');
    if (!filter_var($link, FILTER_VALIDATE_URL)) {
      $data = array(
        'status' => 412,
        'message' => 'Url not in a valid format.'
      );
    }
    else {

      $stmt = $app['db']->prepare("
        SELECT link.id
        FROM link
        WHERE link = :link AND username = :username
      ");

      $stmt->bindParam(':link', $link, SQLITE3_TEXT);
      $stmt->bindParam(':username', $user['username'], SQLITE3_TEXT);

      $result = $stmt->execute();
      if ($result->fetchArray(SQLITE3_ASSOC)) {
        $data = array(
          'status' => 200,
          'message' => 'Link already exists.'
        );
      }
      else {
        $stmt = $app['db']->prepare("
          INSERT INTO link (
            username,
            link,
            title
            )
          VALUES (
            :username,
            :link,
            :title
            )
        ");
        $stmt->bindParam(':username', $user['username'], SQLITE3_TEXT);
        $stmt->bindParam(':link', $link, SQLITE3_TEXT);
        $stmt->bindParam(':title', $app['request']->get('title'), SQLITE3_TEXT);
        $stmt->execute();

        $data = array(
          'status' => 200,
          'message' => 'The url has been added to your wOOp! account.'
        );
      }
    }
  }

  $responce = new Response("var woop_data = " . json_encode($data) . ";alert(woop_data.message);", $data['status'], array(
    'Content-type' => 'text/javascript',
    'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
    'Cache-Control' => 'no-store, no-cache, must-revalidate'
  ));

  return $responce;;
})->assert('version_id', '(v[0-9]+)')
  ->assert('key', '([a-z0-9]+\.(.)+)');


$app->post('/delete-link/{link_id}', function($link_id) use($app) {
  $user = $app['session']->get('user');
  if ($user) {
    $stmt = $app['db']->prepare("
      DELETE FROM link
      WHERE id = :id
    ");
    $stmt->bindParam(':id', $link_id, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($app['request']->isXmlHttpRequest()) {
      return new Response(json_encode(array(
        'status' => 200,
        'message' => 'Link deleted'
      )), 200, array('Content-Type' => 'application/json'));
    }

    return $app->redirect('/account');
  }
})->assert('link_id', '([0-9]+)');


$app->get('/load/{version_id}/{key}/woop.js', function($version_id, $key) use($app) {

  ob_start();
    include __DIR__ . '/../public_html/js/woop.js';
  $javascript = ob_get_clean();

  $responce = new Response($javascript, 200, array(
    'Content-type' => 'text/javascript',
    'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
    'Cache-Control' => 'no-store, no-cache, must-revalidate'
  ));

  return $responce;;
})->assert('version_id', '(v[0-9]+)')
  ->assert('key', '([a-z0-9]+)');

