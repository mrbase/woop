<?php
session_start();

if (empty($_SESSION['data'])) {
  $xml = simplexml_load_file('http://api.flickr.com/services/feeds/groups_pool.gne?id=718574@N22&lang=en-us&format=rss_200', 'SimpleXMLElement', LIBXML_NOCDATA);

  $data = array();
  foreach ($xml->channel->item as $item) {
    $item->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');

    $img = $item->xpath('media:thumbnail');
    $data[] = array(
      'src' => (string) $img[0]->attributes()->url,
      'title' => (string) $item->title,
      'href' => (string) $item->link
    );

  }
  $_SESSION['data'] = $data;
} else {
  $data = $_SESSION['data'];
}

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

$offset = empty($_REQUEST['offset']) ? 0 : (int) $_REQUEST['offset'];
$limit  = empty($_REQUEST['limit']) ? 4 : (int) $_REQUEST['limit'];

$count = count($data);
$data = array_slice($data, $offset, $limit);

die(json_encode(array(
  'count' => $count,
  'items' => $data,
)));


