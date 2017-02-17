<?php

namespace dokuwiki\plugin\websockets\app;


class Server {

    protected $host;
    protected $port;
    protected $master;
    protected $clients = array();

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
        $utils = new utils();
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
                        $this->clients[(int)$client] = new Connection($this, $client);
                        echo 'client ' . $client . " Connected!\n";
                        //$header = fread($client, 1024);
                        //$handshake = $utils->getHandshakeResponse($header);
                        //$bytesWritten = $this->writeBuffer($client, $handshake);
                    }
                } else {
                    $buffer = fread($socket, 1024);
                    if (empty($buffer)) {
                        continue;
                    }
                    $this->clients[(int)$socket]->handleData($buffer);

                    //$encodedData = $utils->encodeDataFrame('{"msg":"testmessage2"}', 'text', false);
                    //if (!$this->writeBuffer($socket, $encodedData)) {
                    //    throw new \Exception('write to socket failed!');
                    //}
                }
            }
        }
    }


    /**
     *
     * @todo we probabely also shouldn't call writeBuffer here directly but call a send method on Connection
     * @todo this should receive unencoded data and handle encoding by itself.
     *
     * @param string $payload
     */
    public function writeDataToAllClients($payload) {
        foreach ($this->clients as $connection) {
            if (!$connection->isWritable()) {
                continue;
            }
            $bytesWritten = $this->writeBuffer($connection->getSocket(), $payload);
            if (!$bytesWritten) {
                $user = $connection->getUser();
                print_r("$user: failed to write: \n$string\n");
            }
        }
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


}
