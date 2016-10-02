<?php
/**
 * session.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/*
    Use the static method getInstance to get the object.
*/

class Session
{
    const SESSION_STARTED = TRUE;
    const SESSION_NOT_STARTED = FALSE;

    // The state of the session
    private $sessionState = self::SESSION_NOT_STARTED;

    // THE only instance of the class
    private static $instance;


    // Empty Constructor
    private function __construct() {}


    /**
    *    Returns THE instance of 'Session'.
    *    The session is automatically initialized if it wasn't.
    *
    *    @return    object
    **/
    public static function getInstance()
    {
        if (!isset(self::$instance))
        {
            self::$instance = new self;
        }

        self::$instance->startSession();

        return self::$instance;
    }


    /**
    *    (Re)starts the session.
    *
    *    @return    bool    TRUE if the session has been initialized, else FALSE.
    **/

    public function startSession()
    {
        if ( $this->sessionState == self::SESSION_NOT_STARTED )
        {
            $this->sessionState = session_start();
        }

        return $this->sessionState;
    }

    /**
    *    Regenerates the session's ID.
    *
    *    @return    bool    TRUE if the session id is regenerated, else FALSE.
    **/
    public function regenSessionId() {
        return session_regenerate_id();
    }


    /**
    *    Stores datas in the session.
    *    Example: $instance->foo = 'bar';
    *
    *    @param    name    Name of the data.
    *    @param    value    Your data.
    *    @return    void
    **/

    public function __set( $name , $value )
    {
        $_SESSION[$name] = $value;
    }


    /**
    *    Gets datas from the session.
    *    Example: echo $instance->foo;
    *
    *    @param    name    Name of the data to get.
    *    @return    mixed    Data stored in session.
    **/

    public function __get( $name )
    {
        if ( isset($_SESSION[$name]))
        {
            return $_SESSION[$name];
        }
    }


    public function __isset( $name )
    {
        return isset($_SESSION[$name]);
    }


    public function __unset( $name )
    {
        unset( $_SESSION[$name] );
    }


    /**
    *    Destroys the current session.
    *
    *    @return    bool    TRUE is session has been deleted, else FALSE.
    **/

    public function destroy()
    {
        if ( $this->sessionState == self::SESSION_STARTED ) {
            $this->sessionState = !session_destroy();

            return !$this->sessionState;
        } else {
            session_start();
            $_SESSION = array();

            return session_destroy();
        }

        return FALSE;
    }

    //        session_start();
//
//        // Unset all of the session variables.
//        $_SESSION = array();
//
//        // If it's desired to kill the session, also delete the session cookie.
//        // Note: This will destroy the session, and not just the session data!
//        if (ini_get("session.use_cookies")) {
//            $params = session_get_cookie_params();
//            setcookie(session_name(), '', time() - 42000,
//                $params["path"], $params["domain"],
//                $params["secure"], $params["httponly"]
//            );
//        }
//
//

}
