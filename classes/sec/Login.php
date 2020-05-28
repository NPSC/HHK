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


    public static function initHhkSession($configFileName) {

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
        if ($config->getBool('site', 'Site_Maintenance', FALSE)) {
            //exit("<h1>".$config->getString('site','Site_Name', ''). " is offline for maintenance.  Try again later.</h1>");
            exit("<h1>HHK is offline for maintenance.  Try again later.</h1>");
        }

        // Check SsL
        $ssl = $config->getBool('site', 'SSL', FALSE);
        $secureComp = new SecurityComponent();

        if ($ssl === TRUE) {

            // Must access pages through SSL
            if ($secureComp->isHTTPS() === FALSE) {
                header("Location: " . $secureComp->getRootURL() . 'index.php');
            }
        }

        $ssn->ssl = $ssl;
        $ssn->resourceURL = $secureComp->getRootURL();
        $ssn->mode = strtolower($config->getString('site', 'Mode', 'live'));
        $ssn->testVersion = $config->getBool('site', 'Run_As_Test', true);
        $ssn->ver = CodeVersion::VERSION . '.' . CodeVersion::BUILD;
        $ssn->sitePepper = $config->getString('site', 'sitePepper', false);

        // Initialize role code
        if (isset($ssn->rolecode) === FALSE) {
        	$ssn->rolecode = WebRole::Guest;
        }

        // Set Timezone
        self::dbParmsToSession($config);

        return $config;
    }

    public static function dbParmsToSession(Config_Lite $config) {

        // get session instance
        $ssn = Session::getInstance();

        try {
            $dbConfig = $config->getSection('db');
        } catch (Config_Lite_Exception $e) {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Database configuration parameters are missing.", 1, $e);
        }

        if (is_array($dbConfig)) {
            $ssn->databaseURL = $dbConfig['URL'];
            $ssn->databaseUName = $dbConfig['User'];
            $ssn->databasePWord = decryptMessage($dbConfig['Password']);
            $ssn->databaseName = $dbConfig['Schema'];
            $ssn->dbms = $dbConfig['DBMS'];
        } else {
            $ssn->destroy();
            throw new Hk_Exception_Runtime("Bad Database Configuration");
        }
    }

    public function checkPost(\PDO $dbh, $post, $defaultPage) {

        $this->validateMsg = '';
        $events = array();

        // Get next page address
        if (isset($_POST["xf"]) && $_POST["xf"] != '') {
            $pge = filter_var(urldecode($_POST["xf"]), FILTER_SANITIZE_STRING);
        } else {
            $pge = $defaultPage;
        }


        if (isset($post["txtUname"]) && isset($post["txtPass"])) {

            $this->userName = strtolower(filter_var($post["txtUname"], FILTER_SANITIZE_STRING));

            $password = filter_var($post["txtPass"], FILTER_SANITIZE_STRING);

            $otp = '';
            if(isset($post["otp"])){
                $otp = filter_var($post["otp"], FILTER_SANITIZE_STRING);
            }
            
            $u = new UserClass();
            
            if ($u->_checkLogin($dbh, $this->userName, $password, false, true, $otp) === FALSE) {
                if($u->logMessage == "OTPRequired"){
                    $events['OTPRequired'] = true;
                }else{
                    // Failed
                    $this->validateMsg .= $u->logMessage;
                }
            } else {

                if ($u->getDefaultPage() != '') {
                    $pge = $u->getDefaultPage();
                }

                if (SecurityComponent::is_Authorized($pge)) {
                    $events['page'] = $pge;
                } else {

                    $this->validateMsg .= "Unauthorized for page: " . $pge;
                }
            }

            $events['mess'] = $this->getValidateMsg();
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
                    HTMLInput::generateMarkup($this->userName, array('id'=>'txtUname', 'style'=>'width: 98%')))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'errUname', 'class'=>'hhk-logerrmsg')))
        );
        $tbl->addBodyTr(
            HTMLTable::makeTh('Password:', array('class'=>'hhk-loginLabel'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'txtPW', 'size'=>'17', 'type'=>'password')) . '<button class="showPw" style="font-size: .75em; margin-left: 1em;" tabindex="-1">Show</button>')
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'errPW', 'class'=>'hhk-logerrmsg')))
        );
        
        //pass xf to login
        if(isset($_GET['xf'])){
            $xfInput = HTMLInput::generateMarkup($_GET['xf'], array('name'=>'xf', 'id'=>'xf', 'type'=>'hidden'));
        }else{
            $xfInput = '';
        }
        
        $tbl->addBodyTr(HTMLTable::makeTd($xfInput . HTMLInput::generateMarkup('Login', array('id'=>'btnLogn', 'type'=>'button', 'style'=>'margin-top: 1em;')), array('colspan'=>'2', 'class'=>'hhk-loginLabel')));

        //Two Factor dialog
        $dialogMkup = '
            <div id="OTPDialog" class="hhk-tdbox hhk-visitdialog" style="font-size: .9em; display: none;">
                <div id="otpForm" style="text-align:center">
                    <div id="OTPMsg" style="color: red"></div>
                    <label for"txtOTP" style="display: block; margin-bottom: 1em">Enter Two Step Verification Code</label>
                    <input type="text" id="txtOTP" size="10">
                </div>
            </div>
        ';
        
        return HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin:25px', 'id'=>'divLoginCtls')) . $dialogMkup;

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
