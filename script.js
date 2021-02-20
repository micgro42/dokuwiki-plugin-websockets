window.dokuwiki__websocket = {
    messageHandlers: [],
    ws: null,
};
jQuery(function () {
    'use strict';

    console.log('jsstart');
    const websocket = new WebSocket('ws://127.0.0.1:9000/~michael/dokuwiki/');// ~michael/dokuwiki/lib/plugins/websockets/server.php');
    websocket.onopen = function (evt) {
        console.log('open');
        console.dir(evt);
        // websocket.send('{"msg":"testmessage"}');
        window.dokuwiki__websocket.ws = websocket;
        // websocket.onmessage = window.dokuwiki__websocket.messageHandlers[0];
    };
    websocket.onclose = function (evt) {
        console.log('close');
        // console.dir(evt);
    };
    websocket.onmessage = function (evt) {
        console.log('message 1');
        console.dir(evt);
        console.log(evt.data);
        console.log(evt.data.length);
        // window.dokuwiki__websocket.messageHandlers
    };

    websocket.onmessage = function (evt) {
        console.log('message 2');
    };

    websocket.onerror = function (evt) {
        console.log('error');
        console.dir(evt);
    };
    console.log('jsdone');
});
