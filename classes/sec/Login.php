<?php
namespace HHK\sec;


use HHK\Exception\RuntimeException;
use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput};
use HHK\SysConst\{CodeVersion, WebRole};
use HHK\Config_Lite\Config_Lite;
use HHK\AlertControl\AlertMessage;

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
        } catch (\Exception $ex) {
            $ssn->destroy();
            throw new RuntimeException("Configurtion file is missing, path=".$configFileName, 999, $ex);
        }

        // Check site maintenance
        if ($config->getBool('site', 'Site_Maintenance', FALSE)) {
            exit("<h1>HHK is offline for maintenance.  Try again later.</h1>");
        }

        // Check SsL
        $ssl = $config->getBool('site', 'SSL', TRUE);
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
        } catch (\Exception $e) {
            $ssn->destroy();
            throw new RuntimeException("Database configuration parameters are missing.", 1, $e);
        }

        if (is_array($dbConfig)) {
            $ssn->databaseURL = $dbConfig['URL'];
            $ssn->databaseUName = $dbConfig['User'];
            $ssn->databasePWord = decryptMessage($dbConfig['Password']);
            $ssn->databaseName = $dbConfig['Schema'];
            $ssn->dbms = $dbConfig['DBMS'];
        } else {
            $ssn->destroy();
            throw new RuntimeException("Bad Database Configuration");
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


        if (isset($post["txtUname"]) && isset($post["challenge"])) {

            $this->userName = strtolower(filter_var($post["txtUname"], FILTER_SANITIZE_STRING));

            $password = filter_var($post["challenge"], FILTER_SANITIZE_STRING);

            $u = new UserClass();

            if ($u->_checkLogin($dbh, $this->userName, $password, false) === FALSE) {

                // Failed
                $this->validateMsg .= $u->logMessage;

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

    public function IEMsg(){
        try {
            $userAgentArray = get_browser(NULL, TRUE);
            $browserName = $userAgentArray['parent'];
        } catch (\Exception $d) {
            $browserName = "Missing Browscap";
        }
        if($browserName == "IE 11.0 for Desktop"){
            // Instantiate the alert message control
            $alertMsg = new AlertMessage("IEAlert");
            $alertMsg->set_DisplayAttr("block");
            $alertMsg->set_Context(AlertMessage::Alert);
            $alertMsg->set_iconId("alrIcon");
            $alertMsg->set_styleId("alrResponse");
            $alertMsg->set_txtSpanId("alrMessage");
            $alertMsg->set_Text("Internet Explorer 11 detected<span style='margin-top: 0.5em; display: block'>HHK may not function as intended. For the best experience, consider using a supported browser such as Edge, Chrome or Firefox. If you are required to continue using IE 11, and are having trouble with HHK, please contact NPSC.</span>");
            
            return HTMLContainer::generateMarkup('div', $alertMsg->createMarkup(), array('style'=>'margin-top: 1em;'));
        }else{
            return '';
        }
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


        return HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin:25px', 'id'=>'divLoginCtls'));

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
?>