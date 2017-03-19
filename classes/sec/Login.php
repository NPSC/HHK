<?php
/**
 * Login.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of login
 *
 * @author Eric
 */
class Login {

    protected $userName = '';
    protected $validateMsg = '';

    function __construct() {

    }

    public static function initializeSession($configFileName) {

        // get session instance
        $ssn = Session::getInstance();
        // Preset the timezone to suppress errors on hte subject.
        date_default_timezone_set('America/Chicago');

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
        $ssn->sconf = 'sys_config';
        $ssn->sId = $config->getString('site', 'Site_Id', '');
        $ssn->subsidyId = $config->getString('financial', 'RoomSubsidyId', '0');

        $ssn->tutURL = $config->getString('site', 'Tutorial_URL', '');
        $ssn->HufURL = $config->getString('site', 'HUF_URL', '');
        $ssn->adminEmailAddr = $config->getString('house', 'Admin_Address', '');
        $ssn->noreplyAddr = $config->getString('house', 'NoReply', '');
        $ssn->adminSiteURL = $config->get('site', 'Admin_URL', '');
        $ssn->ccgw = $config->getString('financial', 'CC_Gateway', '');

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
            $ssn->dbms = $dbConfig['DBMS'];
        } else {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Bad Database Configurtion");
        }

        return $config;
    }

    public function checkPost(\PDO $dbh, $post, $defaultPage) {

        $this->validateMsg = '';
        $events = array();

        $chal = new ChallengeGenerator(FALSE);
        if ($chal->testTries() === FALSE) {
            return array('mess'=>'Too many invalid login attempts.', 'stop'=>'y');
        }


        // Get next page address
        if (isset($_GET["xf"])) {
            $pge = filter_var(urldecode($_GET["xf"]), FILTER_SANITIZE_STRING);
        } else {
            $pge = $defaultPage;
        }


        if (isset($post["txtUname"]) && isset($post["challenge"])) {

            $this->userName = strtolower(filter_var($post["txtUname"], FILTER_SANITIZE_STRING));

            $password = filter_var($post["challenge"], FILTER_SANITIZE_STRING);

            $u = new UserClass();

            if ($u->_checkLogin($dbh, $this->userName, $password, false) === FALSE) {

                $this->validateMsg .= $u->logMessage;

            } else {

                if (ComponentAuthClass::is_Authorized($pge)) {
                    $events['page'] = $pge;
                } else {

                    $this->validateMsg .= "Unauthorized for page: " . $pge;
                }
            }

            $events['mess'] = $this->getValidateMsg();

            $chal->setChallengeVar();
            $events['chall'] = $chal->getChallengeVar();
        }

        return $events;

    }


    public function loginForm($uname = '') {

        if ($uname != '' && $this->userName == '') {
            $this->setUserName($uname);
        }

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->validateMsg, array('id'=>'valMsg', 'style'=>'color:red;')), array('colspan'=>'2')));
        $tbl->addBodyTr(
            HTMLTable::makeTh('User Name:', array('class'=>'hhk-loginLabel'))
            .HTMLTable::makeTd(
                    HTMLInput::generateMarkup($this->userName, array('id'=>'txtUname', 'size'=>'15')))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'errUname')))
        );
        $tbl->addBodyTr(
            HTMLTable::makeTh('Password:', array('class'=>'hhk-loginLabel'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'txtPW', 'size'=>'15', 'type'=>'password')))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'errPW'))
                    . HTMLInput::generateMarkup($this->getChallengeVar(), array('type'=>'hidden', 'id'=>'challenge')))
        );
        $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Login', array('id'=>'btnLogn', 'type'=>'button')), array('colspan'=>'2', 'class'=>'hhk-loginLabel')));


        return HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin:25px', 'id'=>'divLoginCtls'));

    }

    protected function getChallengeVar() {

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

        return $challengeVar;
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


}

