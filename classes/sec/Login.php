<?php
namespace HHK\sec;


use HHK\AlertControl\AlertMessage;
use HHK\Config_Lite\Config_Lite;
use HHK\Exception\CsrfException;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\CodeVersion;
use HHK\SysConst\WebRole;

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
    	$ssn = Session::getInstance($configFileName);
        // Preset the timezone to suppress errors on hte subject.
        //date_default_timezone_set('America/Chicago');

        // Get the site configuration object
        try {
            $config = new Config_Lite($configFileName);
        } catch (\Exception $ex) {
            $ssn->destroy();
            throw new RuntimeException("Configurtion file is missing, path=".$configFileName, 999, $ex);
        }

        $ssn->sitePepper = $config->getString('site', 'sitePepper', false);

        try {
        	self::dbParmsToSession($config);
        	$dbh = initPDO(TRUE);
        } catch (RuntimeException $hex) {
        	exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
        }

         // Check site maintenance
        $ssn->Site_Maintenance = SysConfig::getKeyValue($dbh, 'sys_config', 'Site_Maintenance', false);

        if ($ssn->Site_Maintenance === TRUE) {
             exit("<h1>HHK is offline for maintenance.  Try again later.</h1>");
        }


		// Check SsL
        $ssn->ssl = SysConfig::getKeyValue($dbh, 'sys_config', 'SSL', false);
        $secureComp = new SecurityComponent();

        if ($ssn->ssl === TRUE) {

            // Must access pages through SSL
            if ($secureComp->isHTTPS() === FALSE) {
                header("Location: " . $secureComp->getRootURL() . 'index.php');
            }
        }


        $ssn->mode = strtolower(SysConfig::getKeyValue($dbh, 'sys_config', 'mode', 'demo'));
        $ssn->testVersion = SysConfig::getKeyValue($dbh, 'sys_config', 'Run_As_Test', false);
        $ssn->resourceURL = $secureComp->getRootURL();
        $ssn->ver = CodeVersion::VERSION . '.' . CodeVersion::BUILD;

        // Initialize role code
        if (isset($ssn->rolecode) === FALSE) {
        	$ssn->rolecode = WebRole::Guest;
        }

        //get google API keys
        SysConfig::getCategory($dbh, $ssn, "'ga'", WebInit::SYS_CONFIG);

        return $dbh;
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


        if (isset($post["txtUname"]) && isset($post["txtPass"])) {

            $this->userName = strtolower(filter_var(substr($post["txtUname"], 0, 100), FILTER_SANITIZE_STRING));

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

    public static function IEMsg(){
        try {
            if ($userAgentArray = get_browser(NULL, TRUE)) {

                if (is_array($userAgentArray)) {

                    $browserName = $userAgentArray['parent'];

                    if($browserName && $browserName == "IE 11.0 for Desktop"){
                        // Instantiate the alert message control
                        $alertMsg = new AlertMessage("IEAlert");
                        $alertMsg->set_DisplayAttr("block");
                        $alertMsg->set_Context(AlertMessage::Alert);
                        $alertMsg->set_iconId("alrIcon");
                        $alertMsg->set_styleId("alrResponse");
                        $alertMsg->set_txtSpanId("alrMessage");
                        $alertMsg->set_Text("Internet Explorer 11 detected<span style='margin-top: 0.5em; display: block'>HHK may not function as intended. For the best experience, consider using a supported browser such as Edge, Chrome or Firefox. If you are required to continue using IE 11, and are having trouble with HHK, please contact NPSC.</span>");

                        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $alertMsg->createMarkup()), array("class"=>"col-xl-10")), array("class"=>"row justify-content-center mb-3"));
                    }
                }
            }
        } catch (\Exception $d) {
            return "Missing Browscap";
        }

        return '';
    }

    public function loginForm($uname = '') {

        if ($uname != '' && $this->userName == '') {
            $this->setUserName($uname);
        }

        $uS = Session::getInstance();
        if($uS->ssoLoginError){
            $this->validateMsg = $uS->ssoLoginError;
            unset($uS->ssoLoginError);
        }

        $valMkup = HTMLContainer::generateMarkup('div', $this->validateMsg, array('id'=>'valMsg', "class"=>"valMsg"));

        $hdr = HTMLContainer::generateMarkup("div", "Login", array("class"=>"ui-widget-header ui-corner-top p-1", "id"=>"loginTitle"));

        $userRow = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("label", 'User Name:', array("class"=>"col-4 pr-0 tdlabel")) .
            HTMLContainer::generateMarkup("div",
                HTMLInput::generateMarkup($this->userName, array('id'=>'txtUname', "class"=>"w-100")) .
                HTMLContainer::generateMarkup('span', '', array('id'=>'errUname', 'class'=>'hhk-logerrmsg'))
            , array("class"=>"col-8"))
        , array("class"=>"row mt-3 mx-0", "id"=>"userRow"));

        $pwRow = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("label", 'Password:', array("class"=>"col-4 pr-0 tdlabel")) .
            HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("div",
                    HTMLInput::generateMarkup("", array('id'=>'txtPW', 'type'=>"password", "class"=>"w-100")) .
                    '<button class="showPw mx-1 ui-button" tabindex="-1">Show</button>'
                , array("class"=>"d-flex")) .
                HTMLContainer::generateMarkup('span', '', array('id'=>'errPW', 'class'=>'hhk-logerrmsg'))
            , array("class"=>"col-8"))
            , array("class"=>"row mt-3 mx-0", "id"=>"pwRow"));

        $otpRow = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("label", 'Two Factor Code', array("class"=>"col-6 pr-0 tdlabel")) .
            HTMLContainer::generateMarkup("div",
                HTMLInput::generateMarkup("", array('id'=>'txtOTP', "name"=>"twofactorCode", "class"=>"w-100")) .
                HTMLContainer::generateMarkup('span', '', array('id'=>'errOTP', 'class'=>'hhk-logerrmsg'))
                , array("class"=>"col-6"))
            , array("class"=>"row mt-3 mx-0 d-none", "id"=>"otpRow"));

        //pass xf to login
        if(isset($_GET['xf'])){
            $xfInput = HTMLInput::generateMarkup($_GET['xf'], array('name'=>'xf', 'id'=>'xf', 'type'=>'hidden'));
        }else{
            $xfInput = '';
        }

        $loginRow = HTMLContainer::generateMarkup("div",
            $xfInput .
            HTMLInput::generateMarkup('Login', array('id'=>'btnLogn', 'type'=>'submit', 'class'=>'ui-button'))
         , array("class"=>"my-3"));

        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $hdr . HTMLContainer::generateMarkup("form", $valMkup . $userRow . $pwRow . $otpRow . $loginRow, array("class"=>"ui-widget-content ui-corner-bottom", "id"=>"hhkLogin")), array("class"=>"ui-widget center")), array('class'=>'col-12', 'id'=>'divLoginCtls')), array("class"=>"row justify-content-center mb-3"));

    }

    public static function rssWidget(string $title, string $feedurl, int $postCount) {

        $hdr = HTMLContainer::generateMarkup("div", $title, array("class"=>"ui-widget-header ui-corner-top p-1 center"));

        $content = "";
        //$feedurl = "https://forum.hospitalityhousekeeper.net/rss-feed/feed/";
        if(@simplexml_load_file($feedurl)){
            $feed = simplexml_load_file($feedurl);
            $i = 0;
            foreach ($feed->channel->item as $item){
                $content .= HTMLContainer::generateMarkup("div",
                    HTMLContainer::generateMarkup("h4", HTMLContainer::generateMarkup("a", $item->title . "<span class='ui-icon ui-icon-extlink'></span>", array("href"=>$item->link, "target"=>"_blank"))) .
                    HTMLContainer::generateMarkup("div", $item->description, array("class"=>"item-content"))
                , array("class"=>"item p-3"));
                $i++;
                if($i === 3){ break;}
            }

        }else{
            $content = "Unable to parse feed";
        }

        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $hdr . HTMLContainer::generateMarkup("div", $content, array("class"=>"ui-widget-content ui-corner-bottom")), array("class"=>"ui-widget")), array('class'=>'col-12')), array("class"=>"row justify-content-center mb-3 rssWidget",));

    }

    public static function getLinksMarkup(Session $uS, \PDO $dbh){
        $tutorialSiteURL = SysConfig::getKeyValue($dbh, 'sys_config', 'Tutorial_URL');
        $trainingSiteURL = SysConfig::getKeyValue($dbh, 'sys_config', 'Training_URL');
        $extLinkIcon = "<span class='ui-icon ui-icon-extlink'></span>";
        $hdr = HTMLContainer::generateMarkup("div", "Useful Links", array("class"=>"ui-widget-header ui-corner-top p-1"));

        $linkMkup = '';
        if ($tutorialSiteURL != '' || $trainingSiteURL != '') {
            if ($tutorialSiteURL != '') {
                $linkMkup .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'User Demonstration Videos' . $extLinkIcon, array('href'=>$tutorialSiteURL, 'target'=>'_blank', "class"=>"ui-button ui-corner-all")));
            }

            if ($trainingSiteURL != '') {
                $linkMkup .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'HHK Training Playground' . $extLinkIcon, array('href'=>$trainingSiteURL, 'target'=>'_blank', "class"=>"ui-button ui-corner-all")), array('class'=>"mt-3"));
            }
            $linkMkup = HTMLContainer::generateMarkup("div", $hdr . HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("ul", $linkMkup, array("class"=>"list-style-none")), array("class"=>"ui-widget-content ui-corner-bottom p-3 smaller")), array("class"=>"ui-widget center"));
        }

        return $linkMkup;
    }

    public static function getNewsletterMarkup(){

        $uS = Session::getInstance();

        if($uS->NewsletterURL){

            $hdr = HTMLContainer::generateMarkup("div", "Newsletter", array("class"=>"ui-widget-header ui-corner-top p-1"));

            $newsletterIframe = HTMLContainer::generateMarkup("p", "Get the latest updates from NPSC", array("class"=>"mb-3")) . HTMLContainer::generateMarkup("button", "Sign Up", array("id"=>"newsletteriframe", "href"=>$uS->NewsletterURL, "data-title"=>"Newsletter Sign Up", "class"=>"ui-button ui-corner-all"));

            $content = HTMLContainer::generateMarkup("div", $newsletterIframe, array("class"=>"ui-widget-content ui-corner-bottom p-3"));

            return HTMLContainer::generateMarkup("div", $hdr . $content, array("class"=>"ui-widget center"));
        }else{
            return "";
        }
    }

    public static function getFooterMarkup(){
        $copyYear = date('Y');

        return HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("div",
                "<hr>" .
                HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("a", "", array("href"=>"https://nonprofitsoftwarecorp.org", "target"=>"_blank", "class"=>"nplogo"))) .
                HTMLContainer::generateMarkup("div", "&copy; " . $copyYear . " Non Profit Software Corporation", array("class"=>"copyright"))
            , array("class"=>"col-xl-10"))
        , array("class"=>"row justify-content-md-center mt-5 mb-3"));
    }

    public function generateCSRF(){
        $uS = Session::getInstance();
        if(empty($uS->CSRFtoken)){
            $uS->CSRFtoken = bin2hex(openssl_random_pseudo_bytes(32));
        }
        return $uS->CSRFtoken;
    }

    public static function verifyCSRF($token = false){
        $uS = Session::getInstance();
        if($token && !empty($uS->CSRFtoken) && $token == $uS->CSRFtoken){
            return true;
        }else{
            throw new CsrfException("CSRF verification failed.");
        }
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