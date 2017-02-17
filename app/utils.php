<?php

namespace dokuwiki\plugin\websockets\app;

class utils {

    public function getHandshakeResponse($header) {
        list($endpoint, $headers) = $this->parseHeaderToArray($header);

        if (!isset($headers['Upgrade']) || strpos($headers['Upgrade'], 'websocket') === false) {
            throw new \Exception('Upgrade field missing or invalid!');
        }

        // @todo check Connection field etc. and make more validity checks

        $responseToken = $this->createAcceptToken($headers['Sec-WebSocket-Key']);

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: $responseToken" . "\r\n\r\n";
        return $response;
    }

    public function createClientHandshake() {
        $host = '...';
        $handshake = "GET / HTTP/1.1\r\n";
        $handshake .= "Host: $host"."\r\n";
        $handshake .= "Upgrade: websocket\r\n";
        $handshake .= "Connection: Upgrade\r\n";
        $handshake .= "Sec-WebSocket-Version: 13\r\n";

        $secret = base64_encode(openssl_random_pseudo_bytes(16, $crypto_strong));
        if (!$crypto_strong) {
            throw new \Exception('Your system is broken and does not support strong crypto algorithms!');
        }
        $handshake .= "Sec-WebSocket-Key: $secret" . "\r\n";

        $handshake .= "\r\n";
        return $handshake;
    }

    /**
     * 
     */
    public function encodeDataFrame($data, $type = 'text', $maskIt = true) {
        $frameHeader = '';
        $fin = 1; // we currently do not support fragmented messages.
        $RSV1 = 0;
        $RSV2 = 0;
        $RSV3 = 0;
        switch ($type) {
        case 'continuation':
            $opcode = '0000';
            throw new \Exception('Sending of continuation frames is not yet supported!');
            break;
        case 'text':
            $opcode = '0001';
            break;
        case 'binary':
            $opcode = '0010';
            break;
        case 'close':
            $opcode = '1000';
            break;
        case 'ping':
            $opcode = '1001';
            throw new \Exception('Sending of pings is not yet supported!');
            break;
        case 'pong':
            $opcode = '1010';
            break;
        default:
            throw new \Exception('Unknown frame type ' . $type);
        }

        $firstByteBinary = $fin . $RSV1 . $RSV2 . $RSV3 . $opcode;
        $frameHeader .= chr(bindec($firstByteBinary));
        $maskBit = $maskIt ? '1' : '0';
        $payloadLength = strlen($data);
        $payloadBytes = '';
        // @todo: handle payload of length 0
        if ($payloadLength < 126) {
            $secondByteBinary = $maskBit . sprintf('%07b', $payloadLength);
        } elseif ($payloadLength < 65536) {
            $secondByteBinary = $maskBit . sprintf('%07b', 126);
            $payloadBytes = sprintf('%016b', $payloadLength);
        } else {
            $secondByteBinary = $maskBit . sprintf('%07b', 127);
            $payloadBytes = sprintf('%064b', $payloadLength);
        }
        $frameHeader .= chr(bindec($secondByteBinary));
        $frameHeader .= join('', array_map('chr', array_map('bindec', array_filter(str_split($payloadBytes,8)))));
        if ($maskIt) {
            $mask = openssl_random_pseudo_bytes(4, $crypto_strong);
            $frameHeader .= $mask;
            if (!$crypto_strong) {
                throw new \Exception('Your system is broken and does not support strong crypto algorithms!');
            }
            $maskedPayload = '';
            for ($i = 0; $i < $payloadLength; $i += 1) {
                $maskedPayload .= $data[$i] ^ $mask[$i % 4];
            }
            $frame = $frameHeader . $maskedPayload;
        } else {
            $frame = $frameHeader . $data;
        }
        return $frame;
    }

    /**
     * this method is based on https://tools.ietf.org/html/rfc6455#section-5.2
     */
    public function decodeDataFrame($data) {
        if (empty($data)) {
            throw new \Exception('data passed to this function must not be empty!');
        }
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));

        /** bool $final indicates if this is the final part of a multi-part message. The first part may also be final*/
        $final = substr($firstByteBinary, 0 , 1) === '1';
        $reserve1 = substr($firstByteBinary, 1 , 1) === '1';
        $reserve2 = substr($firstByteBinary, 2 , 1) === '1';
        $reserve3 = substr($firstByteBinary, 3 , 1) === '1';
        if ($reserve1 || $reserve2 || $reserve3) {
            throw new \Exception('The reserve bits must be 0 !');
        }
        $opcode = bindec(substr($firstByteBinary, 4));
        $frametype;
        switch ($opcode) {
        case 0:
            $frametype = 'continuation';
            break;
        case 1:
            $frametype = 'text';
            break;
        case 2:
            $frametype = 'binary';
            break;
        case 8:
            $frametype = 'close';
            break;
        case 9:
            $frametype = 'ping';
            break;
        case 10:
            $frametype = 'pong';
            break;
        default:
            throw new \Exception('unknown opcode: ' . $opcode);
        }

        // @todo: handle fragmentation https://tools.ietf.org/html/rfc6455#section-5.4

        $isMasked = substr($secondByteBinary, 0, 1) === '1';
        $payloadLength = bindec(substr($secondByteBinary, 1));
        if ($payloadLength < 126) {
            $datacursor = 2;
        } elseif ($payloadLength == 126) {
            $payloadLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3])));
            if ($payloadLength < 126) {
                throw new Exception('Payload length MUST be encoded in the minimal number of bytes.');
            }
            $datacursor = 4;
        } else {
            $payloadbytes = '';
            for ($i = 2; $i <= 10; $i += 1) {
                $payloadbytes .= sprintf('%08b', ord($data[$i]));
            }
            $payloadLength = bindec($payloadbytes);
            if ($payloadLength < 65536) {
                throw new Exception('Payload length MUST be encoded in the minimal number of bytes.');
            }
            $datacursor = 8;
        }
        $mask = '';
        if ($isMasked) {
            $MASK_LENGTH = 4;
            $mask = substr($data, $datacursor, $MASK_LENGTH);
            $datacursor += $MASK_LENGTH;
        }

        // @todo: check for correct payload length

        $payload = '';
        if ($isMasked) {
            for($i = $datacursor; $i < $datacursor+$payloadLength; $i++)
            {
                $j = $i - $datacursor;
                if(isset($data[$i]))
                {
                    $payload .= $data[$i] ^ $mask[$j % 4];
                }
            }
        } else {
            $payload = substr($data, $datacursor);
        }
        return $payload;

    }

    // define("GLOBAL_WEBSOCKET_GUID", "258EAFA5-E914-47DA-95CA-C5AB0DC85B11");
    /**
     *
     *
     * @todo: Test this with the example from the RFC
     * @link: https://tools.ietf.org/html/rfc6455#section-1.3
     */
    protected function createAcceptToken($secret) {
        $GLOBAL_WEBSOCKET_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
        return base64_encode(pack('H*',sha1($secret . $GLOBAL_WEBSOCKET_GUID)));
    }



    public function getSecret($timestamp, $createIfNotExists = false) {
        $cacheFN = getCacheName('__websockets_'.$timestamp);
        if (file_exists($cacheFN)) {
            return file_get_contents($cacheFN);
        }
        if ($createIfNotExists) {
            $secret = base64_encode(openssl_random_pseudo_bytes(16, $crypto_strong));
            if (!$crypto_strong) {
                msg('Your system has only weak crypto support. Please deactivate the websockets plugin!',-1,'','',MSG_ADMINS_ONLY);
            }
            file_put_contents($cacheFN, $secret);
            return $secret;
        }
        return false;
    }

    /**
     * @param $header
     * @return array
     * @throws \Exception
     */
    public function parseHeaderToArray($header) {
        $lines = array_filter(explode("\r\n", $header));
        list($method, $endpoint, $protocol) = explode(' ', array_shift($lines));
        if ($method !== 'GET') {
            throw new \Exception('Method not allowed: ' . $method);
        }
        if (substr($protocol, 0, 4) !== 'HTTP' || ((float)substr($protocol, 5)) < 1.1) {
            throw new \Exception('Protocol invalid: ' . $protocol);
        }
        $headers = array();
        foreach ($lines as $line) {
            if (trim($line) == "") {
                continue;
            }
            if (strpos($line, ':') === false) {
                var_dump($header);
                var_dump($line);
                continue;
            }
            list($key, $value) = explode(': ', trim($line));
            $headers[$key] = $value;
        }
        return array($endpoint, $headers);
    }
}