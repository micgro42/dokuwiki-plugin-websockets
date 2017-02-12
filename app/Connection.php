<?php

namespace dokuwiki\plugin\websockets\app;

class Connection {
    protected $hasHandshake = false;
    protected $server;
    protected $socket;

    public function __construct($server, $socket) {
        $this->server = $server;
        $this->socket = $socket;
    }

    public function handleData($data) {
        if ($this->hasHandshake) {
            return $this->handle($data);
        }
        return $this->performHandshake($data);
    }


    protected function performHandshake($data) {
        $utils = new utils();
        $handshake = $utils->getHandshakeResponse($data);
        $this->server->writeBuffer($this->socket, $handshake);
        $this->hasHandshake = true;
    }

    protected function handle($buffer) {
        $utils = new utils();
        //try {
        $data = $utils->decodeDataFrame($buffer);
        var_dump($data);
        //} catch (\Exception $e) {
        // var_dump($e);
        //}
        // todo: do some user authentication
        trigger_event('WEBSOCKET_DATA_RECEIVED', $data); // json decode first!

    }

    /**
     * @return resource
     */
    public function getSocket() {
        return $this->socket;
    }
}
