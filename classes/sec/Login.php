<?php
/**
 * Login.php
 *
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of login
 *
 * @author Eric
 */
class Login {

    protected $userName = '';
    protected $validateMsg = '';
    protected $action = '';

    function __construct() {

        $this->action = 'index.php';

    }

    public static function initializeSession($configFileName) {

        // get session instance
        $ssn = Session::getInstance();
        // Preset the timezone to suppress errors on hte subject.
        date_default_timezone_set('America/Chicago');

        // Short circuit
        $chal = new ChallengeGenerator(FALSE);
        if ($chal->testTries() === FALSE) {
            exit('<h3>Too many tries</h3>');
        }

        // Get the site configuration object
        try {
            $config = new Config_Lite($configFileName);
        } catch (Exception $ex) {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Configurtion file is missing, path=".$configFileName, 999, $ex);
        }

        // Check site maintenance
        $maint = $config->getBool('site', 'Site_Maintenance', FALSE);
        if ($maint) {
            exit("<h1>".$config->getString('site','Site_Name', ''). " is offline for maintenance.  Try again later.</h1>");
        }

        // Check SsL
        $ssl = $config->getBool('site', 'SSL', FALSE);
        if ($ssl === TRUE) {

            // Must access pages through SSL
            if (empty($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) == 'off' ) {
                // non-SSL access.
               header("Location: " . $config->getString('site','Site_URL', ''));
            }
        }


        // Load session
        $ssn->testVersion = $config->getBool('site', 'Run_As_Test', true);
        $ssn->siteName = $config->getString('site','Site_Name', 'Hospitality HouseKeeper');
        $ssn->resourceURL = $config->getString('site','Site_URL', '');
        $ssn->tz = $config->getString('calendar', 'TimeZone', 'America/Chicago');
        $ssn->ver = $config->getString('code', 'Version', '*') . '.' . $config->getString('code', 'Build', '*');
        $ssn->ssl = $ssl;
        $ssn->mode = $config->getString('site', 'Mode', 'live');
        $ssn->sconf = $config->getString('site', 'SysConfigTable', 'sys_config');
        $ssn->sId = $config->getString('site', 'Site_Id', '');
        $ssn->subsidyId = $config->getString('financial', 'RoomSubsidyId', '0');

        $ssn->tutURL = $config->getString('site', 'Tutorial_URL', '');
        $ssn->adminEmailAddr = $config->getString('house', 'Admin_Address', '');
        $ssn->noreplyAddr = $config->getString('house', 'NoReply', '');
        $ssn->adminSiteURL = $config->get('site', 'Admin_URL', '');
        $ssn->ccgw = $config->getString('financial', 'CC_Gateway', '');
        $ssn->county = $config->getBool('site', 'IncludeCounty', FALSE);

        // Set Timezone
        date_default_timezone_set($ssn->tz);

        try {
            $dbConfig = $config->getSection('db');
        } catch (Config_Lite_Exception $e) {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Database configurtion data is missing.", 1, $e);
        }

        if (is_array($dbConfig)) {
            $ssn->databaseURL = $dbConfig['URL'];
            $ssn->databaseUName = $dbConfig['User'];
            $ssn->databasePWord = decryptMessage($dbConfig['Password']);
            $ssn->databaseName = $dbConfig['Schema'];
        } else {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Bad Database Configurtion");
        }

        return $config;
    }

    public function checkPost(PDO $dbh, $post) {

        if (isset($post["btnLogn"])) {

            $this->validateMsg = '';
            $password = '';

            if (isset($post["txtUname"])) {

                $this->userName = strtolower(filter_var($post["txtUname"], FILTER_SANITIZE_STRING));

                if ($this->userName == "") {
                    $this->validateMsg = "Enter a User Name.  ";
                }

            } else {
                $this->validateMsg .= "Enter a User Name.  ";
            }

            if (isset($post["challenge"])) {

                $password = filter_var($post["challenge"], FILTER_SANITIZE_STRING);

                if ($password == "") {
                    $this->validateMsg .= "Enter a password.";
                }

            } else {
                $this->validateMsg .= "Enter a password.";
            }


            if ($this->validateMsg == "") {

                $u = new UserClass();

                if ($u->_checkLogin($dbh, $this->userName, $password, false) === FALSE) {
                    $this->validateMsg = $u->logMessage;
                }
            }
        }

    }


    public function loginForm($uname = '') {

        if ($uname != '' && $this->userName == '') {
            $this->setUserName($uname);
        }

        $form = $this->loginJavaScript();

        $form .= "<div style='margin:25px;'><form id='lginForm' action='" . $this->getAction() . "' method='post' onsubmit='javascript:return checkForm();'>
<table><tr>
    <td colspan='2'><span id='valMsg' style='color: red;'>" . $this->validateMsg . "</span></td>
</tr><tr>
    <th style='text-align:right;padding:5px;'>User Name: </th><td><input type='text' size ='15' name='txtUname' id='txtUname' value='" . $this->userName . "'  /></td>
</tr><tr>
    <th style='text-align:right;padding:5px;'>Password: </th><td><input type='password' size='15' name='txtPW' id='txtPW' value=''  />
    <input type='hidden' name='challenge' id='challenge' value=''  /></td>
</tr><tr>
    <td colspan='2' style='padding-top: 15px; text-align: right;'><input type='submit' name='btnLogn' value='Login' /></td>
</tr></table></form></div>";

        return $form;

    }

    protected function loginJavaScript() {

        // get session instance
        $uS = Session::getInstance();

        $resetTries = TRUE;
        if (isset($uS->Challtries) && $uS->Challtries > 0) {
            $resetTries = FALSE;
        }

        // instantiate a ChallengeGenerator object
        $chlgen = new ChallengeGenerator($resetTries);
        // register challenge variable
        $chlgen->setChallengeVar();
        $challengeVar = $chlgen->getChallengeVar();

        $script = "<script type='text/javascript'>
function loadBody() {
    var psw = document.getElementById('txtPW');
    psw.value = '';
    var uname = document.getElementById('txtUname');
    uname.focus();
}
function checkForm() {
    var usrid = document.getElementById('txtUname');
    if (!usrid || !usrid.value) {
        showError(usrid, '-Enter your Username');
        return false;
    }
    var psw = document.getElementById('txtPW');
    if (!psw || !psw.value) {
        showError(psw, '-Enter your password');
        return false;
    }
    var chlng = document.getElementById('challenge');
    chlng.value = hex_md5(hex_md5(psw.value) + '$challengeVar');
    psw.value = '';
    return true;
}
function showError(obj, message) {
    document.getElementById('valMsg').innerHTML = '';
    if (!obj.errorNode) {
        obj.onclick = hideError;
        var p = document.createElement('span');
        //p.style.marginLeft = '60px';
        p.style.color = 'red';
        p.appendChild(document.createTextNode(message));
        obj.parentNode.appendChild(p);
        obj.errorNode = p;
        obj.focus();
    }
    return;
}
function hideError() {
    this.parentNode.removeChild(this.errorNode);
    this.errorNode = null;
    this.onchange = null;
}
</script>";

        return $script;
    }

    public function getUserName() {
        return $this->userName;
    }

    public function setUserName($userName) {
        $this->userName = $userName;
    }

    public function getValidateMsg() {
        return $this->validateMsg;
    }

    public function setValidateMsg($validateMsg) {
        $this->validateMsg = $validateMsg;
    }

    public function getAction() {
        return $this->action;
    }

    public function setAction($action) {
        $this->action = $action;
    }

}

