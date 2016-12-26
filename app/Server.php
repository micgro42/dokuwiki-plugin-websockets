<?php

namespace dokuwiki\plugin\websockets\app;


class Server {

    protected $host;
    protected $port;
    protected $master;

    public function __construct($host, $port) {
        $this->host = $host;
        $this->port = $port;

        $this->master = stream_socket_server("tcp://$host:$port");
        echo "Server Started : ".date('Y-m-d H:i:s')."\n";
        echo "Master master  : ".$this->master."\n";
    }

    public function run() {
        $socketsAcceptingWrites = array();
        $socketsWithException = array();
        $allSockets = array($this->master);
        while (true) {
            $changedSockets = $allSockets;
            stream_select($changedSockets, $socketsAcceptingWrites, $socketsWithException, 1);
            foreach ($changedSockets as $socket) {
                if ($socket == $this->master) {
                    $client = stream_socket_accept($socket);
                    if (!$client) {
                        echo "socket_accept() failed";
                        continue;
                    } else {
                        $allSockets[] = $client;
                        echo 'client ' . $client . " Connected!\n";
                        $header = fread($client, 1024);
                        $handshake = $this->getHandshake($header);
                        $bytesWritten = $this->writeBuffer($client, $handshake);
                    }
                } else {
                    $buffer = fread($socket, 1024);
                    try {
                        $data = $this->decodeDataFrame($buffer);
                    } catch (\Exception $e) {
                        var_dump($e);
                    }
                    var_dump($data);
                    // todo: do some user authentication
                    trigger_event('WEBSOCKET_DATA_RECEIVED', $data); // json decode first!
                }
            }
        }
    }

    public function getHandshake($header) {
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

        $responseToken = $this->createAcceptToken($headers['Sec-WebSocket-Key']);

        $response = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: $responseToken" . "\r\n\r\n";
        return $response;
    }

    // found in nekudo/php-websocket
    public function writeBuffer($resource, $string) {
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

    /**
     * this method is based on https://tools.ietf.org/html/rfc6455#section-5.2
     */
    protected function decodeDataFrame($data) {
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

}
