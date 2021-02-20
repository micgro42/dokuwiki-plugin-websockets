<?php

use dokuwiki\plugin\websockets\app\utils;

class helper_plugin_websockets_api extends DokuWiki_Plugin {

    protected $client = 'baz';

    public function __construct() {
        $this->client = stream_socket_client('tcp://127.0.0.1:9000',$errno, $errstr);
        if (!empty($errno)) {
            throw new \Exception('error establishing connection to websocket-server: ' . $errno . ' ' . $errstr);
        }
        $utils = new utils();
        $handshake = $utils->createClientHandshake();
        dbglog($handshake);
        fwrite($this->client, $handshake);
    }

    public function sendDataToServer($data, $call) {
        $timestamp = time();
        $utils = new utils();
        $payload = array(
            'call' => $call,
            'data' => $data,
            'timestamp' => $timestamp,
            'secret' => $utils->getSecret($timestamp, true) // FIXME change system to single-use secrets
        );
        $json = json_encode($payload);
        if ($json == false) {
            msg(json_last_error_msg(),-1);
            return;
        }
        $dataFrame = $utils->encodeDataFrame($json);
        $bytesWritten = fwrite($this->client, $dataFrame);
    }


}