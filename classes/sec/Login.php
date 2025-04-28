<?php
namespace HHK\sec;


use HHK\AlertControl\AlertMessage;
use HHK\Exception\AuthException;
use HHK\Exception\CsrfException;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLInput;
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


    public static function initHhkSession(string $confPath = '', string $confFile = '') {

        // get session instance
    	$ssn = Session::getInstance($confPath, $confFile);

        // Get the site configuration object
        try {
            $config = parse_ini_file($confPath . $confFile, true);
        } catch (\Exception $ex) {
            $ssn->destroy();
            throw new RuntimeException("Configurtion file is missing, path=".$confFile, 999, $ex);
        }

        $ssn->sitePepper = (isset($config["site"]["sitePepper"]) ? $config["site"]["sitePepper"]:'');

        try {
            self::dbParmsToSession($confPath, $confFile);
        	$dbh = initPDO(TRUE);
        } catch (RuntimeException $hex) {
        	exit('<h3>' . $hex->getMessage() . '; <a href="index.php">Continue</a></h3>');
        }

        // Deprecated 7/23 EKC.
         // Check site maintenance
//        $ssn->Site_Maintenance = SysConfig::getKeyValue($dbh, 'sys_config', 'Site_Maintenance', false);
//
//        if ($ssn->Site_Maintenance === TRUE) {
//             exit("<h1>HHK is offline for maintenance.  Try again later.</h1>");
//        }


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

        SysConfig::getCategory($dbh, $ssn, ["a", "f", "es", "ga", "pr", "ha", "sms", "g"], WebInit::SYS_CONFIG);
        
        return $dbh;
    }

    public static function dbParmsToSession(string $confPath, string $confFile) {

        // get session instance
        $ssn = Session::getInstance();

        if(!isset($config["db"]["URL"])){
            try {
                $config = parse_ini_file($confPath . $confFile, true);
            } catch (\Exception $e) {
                $ssn->destroy();
                throw new RuntimeException("Database configuration parameters are missing.", 1, $e);
            }
        }

        if (isset($config["db"]["URL"]) && isset($config["db"]["User"]) && isset($config["db"]["Password"]) && isset($config["db"]["Schema"]) && isset($config["db"]["DBMS"])) {
            $ssn->databaseURL = $config["db"]['URL'];
            $ssn->databaseUName = $config["db"]['User'];
            $ssn->databasePWord = decryptMessage($config["db"]['Password']);
            $ssn->databaseName = $config["db"]['Schema'];
            $ssn->dbms = $config["db"]['DBMS'];
        } else {
            $ssn->destroy();
            throw new RuntimeException("Bad Database Configuration");
        }
    }

    public function checkPost(\PDO $dbh, $post, $defaultPage) {

        $otpMsgs = [
            'authenticator'=>'Enter the one-time <strong>Authenticator</strong> code',
            'email'=>'Enter the one-time code <strong>emailed</strong> to you',
            'backup'=>'Enter a one-time <strong>Backup</strong> code'
        ];

        $this->validateMsg = '';
        $events = array();

        // Get next page address
        if (isset($_POST["xf"]) && $_POST["xf"] != '') {
            $pge = urldecode($_POST["xf"]);
        } else {
            $pge = $defaultPage;
        }


        if (isset($post["txtUname"]) && isset($post["txtPass"])) {

            $this->userName = strtolower(substr(filter_var($post["txtUname"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 0, 100));

            $password = filter_var($post["txtPass"], FILTER_UNSAFE_RAW);

            $otp = '';
            if(isset($post["otp"])){
                $otp = filter_var($post["otp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $otpMethod = false;
            if(isset($post["otpMethod"])){
                $otpMethod = filter_var($post['otpMethod'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $showMethodMkup = false;
            if(isset($post['showMethodMkup'])){
                $showMethodMkup = boolval(filter_var($post['showMethodMkup'], FILTER_VALIDATE_BOOLEAN));
            }

            $rememberMe = false;
            if(isset($post['rememberMe'])){
                $rememberMe = boolval(filter_var($post['rememberMe'], FILTER_VALIDATE_BOOLEAN));
            }

            $u = new UserClass();

            if ($u->_checkLogin($dbh, $this->userName, $password, $rememberMe, true, $otpMethod, $otp) === FALSE) {
                if($u->logMessage == "OTPRequired"){
                    $events['OTPRequired'] = true;
                    $events['method'] = ($otpMethod !== '' ? $otpMethod : $u->getDefaultOtpMethod($dbh, $this->userName));
                    $events['methodMsg'] = $otpMsgs[$events['method']];
                    if($showMethodMkup){
                        $events['otpMethodMkup'] = $u->getOtpMethodMarkup($dbh, $this->userName, $otpMethod);
                    }
                    if($u->showDifferentMethodBtn($dbh, $this->userName)){
                        $events['showMethodBtn'] = 'true';
                    }
                }else{
                    // Failed
                    $this->validateMsg .= $u->logMessage;
                }
            } else {

                WebInit::resetSessionIdle(); //extend idle session to prevent double login

                if ($pge == $defaultPage && $u->getDefaultPage() != '') {
                    $pge = $u->getDefaultPage();
                }

                try {
                    if (SecurityComponent::is_Authorized($pge, true)) {
                        $events['page'] = $pge;
                    } else {
                        $this->validateMsg .= "Unauthorized for page: " . $pge;
                    }
                }catch(AuthException $e){
                    $this->validateMsg .= $e->getMessage();
                }
            }

            $events['mess'] = $this->getValidateMsg();
        }

        return $events;

    }

    public static function IEMsg(){
        try {
            if ($userAgentArray = @get_browser(NULL, TRUE)) {

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
        }

        return '';
    }

    public static function trainingMsg(){
        $uS = Session::getInstance();

        if($uS->testVersion){
            $alertMsg = new AlertMessage("TrainingAlert");
            $alertMsg->set_DisplayAttr("block");
            $alertMsg->set_Context(AlertMessage::Info);
            $alertMsg->set_iconId("alrIcon");
            $alertMsg->set_styleId("alrResponse");
            $alertMsg->set_txtSpanId("alrMessage");
            $alertMsg->set_Text("This is the shared HHK Training site. DO NOT use real guest or patient names. <span class='d-block mt-3'><strong>Your house HHK credentials won't work here.</strong> If you don't have training credentials, please contact NPSC at support@nonprofitsoftwarecorp.org</span>");

            return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $alertMsg->createMarkup()), array("class"=>"col-xl-10")), array("class"=>"row justify-content-center mb-3"));
        }else{
            return "";
        }
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
                    '<button type="button" class="showPw mx-1 ui-button" tabindex="-1">Show</button>'
                , array("class"=>"d-flex")) .
                HTMLContainer::generateMarkup('span', '', array('id'=>'errPW', 'class'=>'hhk-logerrmsg'))
            , array("class"=>"col-8"))
            , array("class"=>"row mt-3 mx-0", "id"=>"pwRow"));

        $otpChoiceRow = HTMLContainer::generateMarkup("div",
            '<p>Pick how you would like to receive your temporary code</p> <div id="otpChoices" class="my-3 col-12 center"></div>'
            , array("class"=>"row mt-3 mx-0 d-none", "id"=>"otpChoiceRow"));

        $otpRow = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("div", "", array("class"=>"col-12 pb-3", "id"=>'otpMsg')) .
            HTMLContainer::generateMarkup("label", 'Two Factor Code', array("class"=>"col-6 pr-0 tdlabel")) .
            HTMLContainer::generateMarkup("div",
                HTMLInput::generateMarkup("", array('id'=>'txtOTP', "name"=>"twofactorCode", "class"=>"w-100")) .
                HTMLContainer::generateMarkup('span', '', array('id'=>'errOTP', 'class'=>'hhk-logerrmsg')) .
                HTMLInput::generateMarkup(false, array('name'=>"otpMethod", 'type'=>"hidden", 'id'=>"otpMethod"))
                , array("class"=>"col-6")) .
            ($uS->rememberTwoFA != '' ?
            HTMLContainer::generateMarkup("div",
                HTMLInput::generateMarkup(false, array("type"=>"checkbox", "name"=>"rememberMe")) .
                HTMLContainer::generateMarkup("label", "Remember this device for " . $uS->rememberTwoFA . " days", array("for"=>"rememberMe", "class"=>"ml-1"))
                , array("class"=>"col-12 my-3"))
                : '') .
            HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("button", "Use a different method...", array("id"=>"changeMethod", "data-showMkup"=>"false", "type"=>"button"))
                , array("class"=>"col-12 my-3 right"))
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
         , array("class"=>"my-3", 'id'=>'loginBtnRow'));

        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $hdr . HTMLContainer::generateMarkup("form", $valMkup . $userRow . $pwRow . $otpChoiceRow . $otpRow . $loginRow, array("class"=>"ui-widget-content ui-corner-bottom", "id"=>"hhkLogin")), array("class"=>"ui-widget center")), array('class'=>'col-12', 'id'=>'divLoginCtls')), array("class"=>"row justify-content-center mb-3"));

    }

    public static function rssWidget($title) {

        $hdr = HTMLContainer::generateMarkup("div", $title, array("class"=>"ui-widget-header ui-corner-top p-1 center"));

        $content = '<div id="hhk-loading-spinner" class="center p-3"><img src="../images/ui-anim_basic_16x16.gif"></div>';

        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $hdr . HTMLContainer::generateMarkup("div", $content, array("class"=>"ui-widget-content ui-corner-bottom")), array("class"=>"ui-widget")), array('class'=>'col-12')), array("class"=>"row justify-content-center mb-3 rssWidget",));

    }

    public static function welcomeWidget($title, $rootURL = '..') {

        $uS = Session::getInstance();

        $hdr = HTMLContainer::generateMarkup("div", $title, array("class"=>"ui-widget-header ui-corner-top p-1 center"));

        $content = '<div id="hhk-loading-spinner" class="center p-3 ui-widget-content ui-corner-bottom"><img src="' . $rootURL . '/images/ui-anim_basic_16x16.gif"></div>';
        $content .= HTMLContainer::generateMarkup("div",HTMLContainer::generateMarkup("div", '', ['class'=>'welcomeContent']), ['id'=>'welcomeWidget', 'class'=>'d-none ui-widget-content ui-corner-bottom', 'data-url'=>$uS->loginFeedURL]);
        //$content .= '<iframe src="' . $uS->loginFeedURL . '" width="100%" height="320px" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" id="welcomeWidget" class="d-none ui-widget-content ui-corner-bottom"></iframe>';

        return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup("div", $hdr . $content, array("class"=>"ui-widget")), array('class'=>'col-12')), array("class"=>"row justify-content-center mb-3 welcomeWidgetContainer"));

    }

    public static function getRssData($feedurl) {
        $content = @file_get_contents($feedurl);
        if($content !== false){
            header("Content-Type: text/xml");
            echo $content;
        }else{
            http_response_code(503);
            echo "Unable to Fetch data";
        }
        exit;
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