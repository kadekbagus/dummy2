<?php

$path = $_GET['path'];
$baseUrl = 'http://';
if (isset($_GET['base']))
{
    $baseUrl = $baseUrl . $_GET['base'] . '/';
}

$ch  = curl_init($baseUrl . $path);

if (strtolower($_SERVER['REQUEST_METHOD']) == 'post')
{
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
}

curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);

list( $headers, $contents ) = preg_split('/([\r\n][\r\n])\\1/', curl_exec( $ch ), 2);

$status = curl_getinfo($ch);

curl_close($ch);

$header_list = preg_split( '/[\r\n]+/', $headers );

foreach ($header_list as $header)
{
    if ( preg_match( '/^(?:Content-Type|Content-Language|Set-Cookie):/i', $header ) ) {
        header( $header );
    }
}

if (in_array($status['http_code'], ['200', '301']))
{
    $filename = __DIR__ . $path;
    ob_start();
    if (!is_dir(dirname($filename)))
    {
        mkdir(dirname($filename), 0755, true);
    }
    file_put_contents($filename, $contents);
    ob_end_clean();
    header('X-SERVED-FROM: REMOTE');
}

print $contents;

