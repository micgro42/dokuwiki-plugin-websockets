<?php
/**
 * DokuWiki Plugin websockets (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  qwe <qwe>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_websockets_auth extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'handle_auth_login_check');
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */
    public function handle_auth_login_check(Doku_Event $event, $param) {
        $session_id = session_id();
        list($user, , $pass) = auth_getCookie();
        $pass = base64_encode($pass);
        $cacheFN = getCacheName('__websockets_'. $user . $session_id);

        if ($event->result) {
            if (!file_exists($cacheFN)) {
                file_put_contents($cacheFN, $pass);
            }
        } else {
            if (file_exists($cacheFN)) {
                unlink($cacheFN);
            }
        }
    }

}

// vim:ts=4:sw=4:et:
