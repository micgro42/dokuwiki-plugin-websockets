<?php

namespace dokuwiki\plugin\websockets\app;

class Connection {
    protected $hasHandshake = false;
    protected $server;
    protected $socket;
    protected $user;
    protected $animal;
    protected $writable = false;

    public function __construct($server, $socket) {
        $this->server = $server;
        $this->socket = $socket;
    }

    public function handleData($data) {
        if ($this->hasHandshake) {
            $this->handle($data);
            return;
        }
        $this->performHandshake($data);
        $this->setUserData($data);
    }

    protected function setUserData($header) {
        $utils = new utils();
        list($endpoint, $headers) = $utils->parseHeaderToArray($header);

        if (true && isset($headers['Cookie'])) {  // FIXME implement authentication
            $cookies = explode('; ', $headers['Cookie']);
            // split the cookies into name => payload
            $cookies = array_reduce($cookies, function($cookieArray, $cookie) {
                list($cookieName, $cookiePayload) = explode('=', $cookie);
                $cookieArray[$cookieName] = urldecode($cookiePayload);
                return $cookieArray;
            }, array());

            $sessionid = $cookies['DokuWiki'];
            print_r('$sessionid: ' . $sessionid . "\n");
            global $conf;
            $authCookieKey = 'DW'.md5($endpoint.(($conf['securecookie'])?80:''));
            if (isset($cookies[$authCookieKey])) {
                list($user, $sticky, $pass) = explode('|', $cookies[$authCookieKey], 3);
                $sticky = (bool) $sticky;
                $user   = base64_decode($user);
                $this->user = $user;
                $this->writable = true;
                var_dump($user);
                $cacheFN = getCacheName('__websockets_'. $user . $sessionid);

                print_r('auth file exists: ' . file_exists($cacheFN) . "\n");
                var_dump(file_get_contents($cacheFN));
                var_dump($pass);
                /*
                $pass   = base64_decode($pass);
                $secret = auth_cookiesalt(!$sticky, true); //bind non-sticky to session
                $pass   = auth_decrypt($pass, $secret);
                var_dump(auth_login($user, $pass, false, true));
                print_r("$user $pass $sticky \n");
                */
            }
        } else {
            $this->user = 'WikiServer';
        }
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
        $json = $utils->decodeDataFrame($buffer);
        $data = json_decode($json, true);
        var_dump($data);
        if (isset($data['secret']) && !empty($data['secret'])) {
            if ($utils->getSecret($data['timestamp']) === $data['secret']) {
                // data from server -> send to all (web) clients
                $payload = array(
                    'call' => $data['call'],
                    'data' => $data['data'],
                );
                $dataframe = $utils->encodeDataFrame(json_encode($payload), 'text', false);
                $this->server->writeDataToAllClients($dataframe);
                return;
            }
        }
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

    /**
     * @return mixed
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @return bool
     */
    public function isWritable() {
        return $this->writable;
    }
}
