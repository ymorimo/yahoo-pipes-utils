<?php
require_once 'HTTP/Request2.php';

define('MAX_REDIRECTS', 3);
$redirects = 0;

$url = $_REQUEST["url"];
$referer = $_REQUEST["referer"];

function rel2abs($rel, $base)
{
    /* return if already absolute URL */
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

    /* queries and anchors */
    if ($rel[0]=='#' || $rel[0]=='?') return $base.$rel;

    /* parse base URL and convert to local variables:
       $scheme, $host, $path */
    extract(parse_url($base));

    /* remove non-directory element from path */
    $path = preg_replace('#/[^/]*$#', '', $path);

    /* destroy path if relative url points to root */
    if ($rel[0] == '/') $path = '';

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
    global $url, $referer;
    if ($referer) {
        $request->setHeader('Referer', $referer);
    }
    $req = new HTTP_Request2($url);

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
    }

    return $res;
}

$res = send_request($url);

if ($_REQUEST["content_type"]) {
    $contentType = $_REQUEST["content_type"];
} else {
    $contentType = $res->getHeader('content-type');
}

header("Content-type: " . $contentType);

$body = $res->getBody();

if ($_REQUEST["absolute"]) {
    $body = preg_replace_callback(
        '/\b(src|href)=(["\']?)(.*?)\2/i',
        create_function('$matches',
                        'global $url; return $matches[1] . "=" . $matches[2] . rel2abs($matches[3], $url) . $matches[2];'),
        $body
    );
}

if ($_REQUEST["from_encoding"] && $_REQUEST["to_encoding"]) {
    $body = mb_convert_encoding($body, $_REQUEST["to_encoding"], $_REQUEST["from_encoding"]);
}

echo $body;
