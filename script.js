jQuery(function(){
    'use strict';
    console.log('jsstart');
    const websocket = new WebSocket('ws://127.0.0.1:9000/~michael/dokuwiki/');//~michael/dokuwiki/lib/plugins/websockets/server.php');
    websocket.onopen = function (evt) {
        console.log('open');
        console.dir(evt);
        websocket.send('{"msg":"testmessage"}');
    };
    websocket.onclose = function (evt) {
        console.log('close');
        //console.dir(evt);
    };
    websocket.onmessage = function (evt) {
        console.log('message');
        console.dir(evt);
        console.log(evt.data);
        console.log(evt.data.length);
    };
    websocket.onerror = function (evt) {
        console.log('error');
        console.dir(evt);
    };
    console.log('jsdone');
});