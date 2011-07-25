<?php
require_once 'HTTP/Request2.php';

define('MAX_REDIRECTS', 3);
$redirects = 0;

if (array_key_exists('url', $_REQUEST))
    $url = $_REQUEST["url"];
else
    exit;

if (array_key_exists('referer', $_REQUEST))
    $referer = $_REQUEST["referer"];

function rel2abs($rel, $base)
{
    /* return if already absolute URL */
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

    /* queries and anchors */
    if (strlen($rel) > 0 && ($rel[0]=='#' || $rel[0]=='?')) return $base.$rel;

    /* parse base URL and convert to local variables:
       $scheme, $host, $path */
    extract(parse_url($base));

    /* remove non-directory element from path */
    $path = preg_replace('#/[^/]*$#', '', $path);

    /* destroy path if relative url points to root */
    if (strlen($rel) > 0 && ($rel[0] == '/')) $path = '';

    /* dirty absolute URL */
    $abs = "$host$path/$rel";

    /* replace '//' or '/./' or '/foo/../' with '/' */
    $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
    for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}

    /* absolute URL is ready! */
    return $scheme.'://'.$abs;
}

function send_request()
{
    global $url, $referer, $redirects;
    $req = new HTTP_Request2($url);
    if (isset($referer)) {
        $req->setHeader('Referer', $referer);
    }

    try {
        $res = $req->send();
    } catch (HTTP_Request2_Exception $e) {
        die($e->getMessage());
    } catch (Exception $e) {
        die($e->getMessage());
    }

    if (floor($res->getStatus() / 100) == 3 && $res->getHeader('location') && $redirects++ < MAX_REDIRECTS) {
        $url = $res->getHeader('location');
        $res = send_request($url);
    } else {
        die('Reached Max Redirects: ' . MAX_REDIRECTS);
    }

    return $res;
}

$res = send_request($url);

if (array_key_exists('content_type', $_REQUEST)) {
    $contentType = $_REQUEST["content_type"];
} else {
    $contentType = $res->getHeader('content-type');
}

header("Content-type: " . $contentType);

$body = $res->getBody();

if (array_key_exists('absolute', $_REQUEST)) {
    $body = preg_replace_callback(
        '/\b(src|href)=(["\']?)(.*?)\2/i',
        create_function('$matches',
                        'global $url; return $matches[1] . "=" . $matches[2] . rel2abs($matches[3], $url) . $matches[2];'),
        $body
    );
}

if (array_key_exists('from_encoding', $_REQUEST) && array_key_exists('to_encoding', $_REQUEST)) {
    $body = mb_convert_encoding($body, $_REQUEST["to_encoding"], $_REQUEST["from_encoding"]);
}

echo $body;
