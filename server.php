<?php

error_reporting(E_ALL);

//$master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$master = stream_socket_server('tcp://localhost:9000');
echo "Server Started : ".date('Y-m-d H:i:s')."\n";
echo "Master master  : ".$master."\n";
$allSockets = array($master);
$streamsWrite = array();
$streamsExcept = array();
while (true) {
    $changedSockets = $allSockets;
    stream_select($changedSockets, $streamsWrite, $streamsExcept, null);
    foreach ($changedSockets as $socket) {
        if ($socket == $master) {
            $client = stream_socket_accept($master);
            if (!$client) {
                echo "socket_accept() failed";
                continue;
            } else {
                $sockets[] = $client;
                echo 'client ' . $client . " Connected!\n";
                $header = fread($client, 1024);
                $handshake = getHandshake($header);
                $bytesWritten = writeBuffer($client, $handshake);
            }
        }
    }
}

function getHandshake($header) {
    $lines = explode("\n", $header);
    $requestURI = array_shift($lines);
    $headers = array();
    foreach ($lines as $line) {
        if (trim($line) == "") {
            continue;
        }
        list($key, $value) = explode(': ', trim($line));
        $headers[$key] = $value;
    }

    $responseToken = createAcceptToken($headers['Sec-WebSocket-Key']);

    $response = "HTTP/1.1 101 Switching Protocols\r\n";
    $response .= "Upgrade: websocket\r\n";
    $response .= "Connection: Upgrade\r\n";
    $response .= "Sec-WebSocket-Accept: $responseToken" . "\r\n\r\n";
    return $response;
}

// found in nekudo/php-websocket
function writeBuffer($resource, $string) {
    $stringLength = strlen($string);
    for($written = 0; $written < $stringLength; $written += $fwrite) {
        $fwrite = fwrite($resource, substr($string, $written));
        if($fwrite === false) {
            return false;
        }
        elseif($fwrite === 0) {
            return false;
        }
    }
    return $written;
}

// define("GLOBAL_WEBSOCKET_GUID", "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
/**
 *
 *
 * @todo: Test this with the example from the RFC
 * @link: https://tools.ietf.org/html/rfc6455#section-1.3
 */
function createAcceptToken($secret) {
    $GLOBAL_WEBSOCKET_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    return base64_encode(pack('H*',sha1($secret . $GLOBAL_WEBSOCKET_GUID)));
}
