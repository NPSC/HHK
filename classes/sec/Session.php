<?php
namespace HHK\sec;

/**
 * session.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/*
    Use the static method getInstance to get the object.
*/

class Session
{
    const SESSION_STARTED = TRUE;
    const SESSION_NOT_STARTED = FALSE;

    // The state of the session
    private bool $sessionState = self::SESSION_NOT_STARTED;

    // THE only instance of the class
    private static Session $instance;


    // Empty Constructor
    private function __construct() {}


    /**
    *    Returns THE instance of 'Session'.
    *    The session is automatically initialized if it wasn't.
    *
    *    @return    object
    **/
    public static function getInstance(string $confPath = CONF_PATH, string $confFile = ciCFG_FILE): Session
    {
        if (!isset(self::$instance))
        {
            self::$instance = new self;
        }

        self::$instance->startSession($confPath, $confFile);

        return self::$instance;
    }


    /**
    *    (Re)starts the session.
    *
    *    @return    bool    TRUE if the session has been initialized, else FALSE.
    **/

    public function startSession(string $confPath = '', string $confFile = ''): bool
    {
        if ( $this->sessionState == self::SESSION_NOT_STARTED || session_status() !== PHP_SESSION_ACTIVE)
        {
            ini_set( 'session.cookie_httponly', 1 );
            session_name($this->getSessionName($confPath, $confFile));
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
    *    @param    $name    Name of the data.
    *    @param    $value    Your data.
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
    *    @param    $name    Name of the data to get.
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
     * Summary of destroy
     * @param bool $delCookie
     * @return void
     */
    public function destroy($delCookie = FALSE)
    {
        if ( $this->sessionState == self::SESSION_STARTED ) {
            $this->sessionState = !session_destroy();

        } else {
            session_start();
            $_SESSION = array();

            session_destroy();
        }

        if ($delCookie && ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }

    /**
     * Summary of getSessionName
     * @param string $confPath
     * @param string $confFile
     * @return string
     */
    private function getSessionName(string $confPath, string $confFile): string
    {
        if(!empty($confPath) && !empty($confFile)){
            try{
                $config = parse_ini_file($confPath . $confFile, true);
                return strtoupper((isset($config["db"]["Schema"])? $config["db"]["Schema"]: '')) . 'HHKSESSION';
            }catch(\Exception $ex){
                return 'HHKSESSION';
            }
        }else{
            return 'HHKSESSION';
        }
    }
}
