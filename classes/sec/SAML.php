<?php
namespace HHK\sec;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\IdPMetadataParser;
use OneLogin\Saml2\Utils;
use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;
use HHK\Member\AbstractMember;
use HHK\Member\WebUser;
use HHK\SysConst\MemBasis;
use HHK\SysConst\WebRole;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\SysConst\GLTableNames;
use HHK\Member\MemberSearch;
use HHK\HTMLControls\HTMLInput;
use HHK\Tables\WebSec\W_idpRS;
use HHK\Tables\EditRS;
use HHK\Tables\WebSec\W_authRS;
use HHK\AuditLog\NameLog;
use HHK\Tables\WebSec\W_idp_secgroupsRS;
/**
 * SAML.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * SAML Class
 *
 * Facilitates SAML SSO process using OneLogin's php-saml library
 */

class SAML {

    protected $auth;

    protected $IdpId;
    protected $IdpConfig;

    protected $SPacsURL;
    protected $SPEntityId;
    protected $SPloginURL;
    protected $SPmetadataURL;
    protected $SPSign;
    protected $SPcert;
    protected $SPRolloverCert;
    protected $SPkey;

    protected $dbh;
    protected $auditUser;

    public function __construct(\PDO $dbh, $idpId = 'new'){
        $this->IdpId = $idpId;
        $this->dbh = $dbh;

        $this->loadConfig($dbh);
        if($this->IdpConfig && $this->IdpConfig['idIdp'] > 0){
            try{
                $this->auth = new Auth($this->getSettings());
                $this->auditUser = "SAML: " . $this->IdpConfig["Name"];
            }catch(\Exception $e){

            }
        }else if($idpId != 'new'){
            throw new \Exception("SSO Provider not found (idpId: " . $this->IdpId . ")");
        }
    }

    public function login(){
        if($this->IdpConfig['Status'] == 'a'){
            $this->auth->login();
        }else{
            throw new \Exception("SSO Provider not enabled");
        }
    }

    /**
     * Attribute Consumer Service
     *
     * Handles the SAML Response from the IdP
     *
     * @return array;
     */
    public function acs(){
        $uS = Session::getInstance();
        $error = false;

        if (isset($uS->AuthNRequestID)) {
            $requestID = $uS->AuthNRequestID;
        } else {
            $requestID = null;
        }

        $this->auth->processResponse($requestID);
        unset($uS->AuthNRequestID);

        $errors = $this->auth->getErrors();

        if (!empty($errors)) {
            $error = implode(', ', $errors);
        }

        if (!$this->auth->isAuthenticated()) {
            $ex = $this->auth->getLastErrorException();
            $error = 'Authentication Failed <span class="hhk-help ml-2 px-1"><span class="ui-icon ui-icon-help hhk-tooltip" title="' . $ex->getMessage() . '"></span></span>';
        }else{
            //auth success
            $u = new UserClass();
            $userAr = $u->getUserCredentials($this->dbh, $this->auth->getNameId());

            if($userAr == null || (isset($userAr["idIdp"]) && $userAr["idIdp"] == $this->IdpId)){ //correct user found, set up session

                if($userAr == null || $this->IdpConfig["IdP_ManageRoles"] == 1){ //if user is new and IdP is responsible for Roles/Security Groups
                    $userAr = $this->provisionUser(); //create/update user with details from IdP
                }

                if($u->doLogin($this->dbh, $userAr)){
                    $pge = $uS->webSite['Default_Page'];
                    if ($u->getDefaultPage() != '') {
                        $pge = $u->getDefaultPage();
                    }

                    if (SecurityComponent::is_Authorized($pge)) {
                        header('location:../' . $uS->webSite['Relative_Address'].$pge);
                    } else {
                        $error = "Unauthorized for page: " . $pge;
                    }
                }

            }else if(isset($userAr["idIdp"]) && $userAr["idIdp"] != $this->IdpId){
                $error = 'User found, but is not associated with ' . $this->IdpConfig["Name"] . '. Please login via ' . $userAr['authProvider'];
            }else{
                $error = 'User authenticated at ' . $this->IdpConfig["Name"] . ', but an error occurred during login or user provisioning';
            }
        }

        if($error){
            $uS->ssoLoginError = $error;
            header('location:../' . $uS->webSite['Relative_Address']);
        }

    }

    private function provisionUser(){

        $user = UserClass::getUserCredentials($this->dbh, $this->auth->getNameId());

        //load name lookups
        $uS = Session::getInstance();
        WebInit::loadNameLookups($this->dbh, $uS);

        if($user){ //user found
            $idName = $user['idName'];
        }else{
            //search for existing name record
            $idName = $this->searchName();
        }

        $name = AbstractMember::GetDesignatedMember($this->dbh, $idName, MemBasis::Indivual);
        $phones = new Phones($this->dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
        $emails = new Emails($this->dbh, $name, $uS->nameLookups[GLTableNames::EmailPurpose]);

        //parse name attributes
        if(isset($this->auth->getAttribute("FullName")[0])){
            $nameAr = $this->parseFullName($this->auth->getAttribute("FullName")[0]);
            $firstName = $nameAr[0];
            $lastName = $nameAr[1];
        }else{
            $firstName = (isset($this->auth->getAttribute("FirstName")[0]) ? $this->auth->getAttribute("FirstName")[0]: "");
            $lastName = (isset($this->auth->getAttribute("LastName")[0]) ? $this->auth->getAttribute("LastName")[0]: "");
        }

        $post = array();
        $post["auditUser"] = $this->auditUser;
        $post["txtFirstName"] = $firstName;
        $post["txtLastName"] = $lastName;
        $post["txtEmail"][1] = (isset($this->auth->getAttribute("Email")[0]) ? $this->auth->getAttribute("Email")[0]: "");
        $post["rbEmPref"] = "1";
        $post["txtPhone"]["dh"] = (isset($this->auth->getAttribute("Phone")[0]) ? $this->auth->getAttribute("Phone")[0]: "");
        $post["rbPhPref"] = "dh";

        $msg = $name->saveChanges($this->dbh, $post);
        $msg .= $phones->savePost($this->dbh, $post, $this->auditUser);
        $msg .= $emails->savePost($this->dbh, $post, $this->auditUser);
        $idName = $name->get_idName();

        if($idName > 0){

            //map hhk role
            $role = WebRole::WebUser;
            if(isset($this->auth->getAttribute("hhkRole")[0])){
                switch($this->auth->getAttribute("hhkRole")[0]){
                    case "hhkAdminUser":
                        $role = WebRole::Admin;
                        break;
                    case "hhkWebUser":
                        $role = WebRole::WebUser;
                        break;
                    default:
                        $role = WebRole::WebUser;
                        break;
                }
            }

            if(!isset($user['Role_Id'])){
                //register Web User
                $query = "call register_web_user(" . $idName . ", '', '" . $this->auth->getNameId() . "', '" . $this->auditUser . "', 'p', '" . $role . "', '', 'v', 0, " . $this->IdpId . ");";
                if($this->dbh->exec($query) === false){
                    $err = $this->dbh->errorInfo();
                    return array("error"=>$err[0] . "; " . $err[2]);
                }
            }else{

                // update w_auth table with new role
                $authRS = new W_authRS();
                $authRS->idName->setStoredVal($idName);
                $authRows = EditRS::select($this->dbh, $authRS, array($authRS->idName));

                if (count($authRows) == 1) {
                    // update existing entry
                    EditRS::loadRow($authRows[0], $authRS);

                    $authRS->Role_Id->setNewVal($role);

                    $authRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                    $authRS->Updated_By->setNewVal($this->auditUser);

                    $n = EditRS::update($this->dbh, $authRS, array($authRS->idName));

                    if ($n == 1) {

                        NameLog::writeUpdate($this->dbh, $authRS, $idName, $this->auditUser);
                    }
                }
            }

            $user = UserClass::getUserCredentials($this->dbh, $this->auth->getNameId());


            //make parms array for group update
            $parms = array();
            $attributes = $this->auth->getAttributes();
            $allSecurityGroups = $this->getSecurityGroups($this->dbh);
            $selSecurityGroups = array();

            //fill parms array
            if(isset($attributes["hhkSecurityGroups"])){
                $selSecurityGroups = $attributes['hhkSecurityGroups'];
            }else{
                $selSecurityGroups = $this->IdpConfig['defaultGroups'];
            }
            foreach($allSecurityGroups as $secGroup){
                if(in_array($secGroup["Title"], $selSecurityGroups)){
                    $parms["grpSec_" . $secGroup["Code"]] = "checked";
                }else{
                    $parms["grpSec_" . $secGroup["Code"]] = "unchecked";
                }
            }
            //update security groups
            WebUser::updateSecurityGroups($this->dbh, $user["idName"], $parms, $this->auditUser);
        }else{
            return array("error"=>$msg);
        }

        return $user;
    }

    private function parseFullName(string $fullName){

        $nameAr = explode(" ", $fullName, 2);
        if(count($nameAr) == 2){
            return $nameAr;
        }else{
            return [$fullName, $fullName];
        }

    }

    private function searchName(){

        //Search by exact email address, if no results, search by first and last name, else return 0
        $firstName = (isset($this->auth->getAttribute("FirstName")[0]) ? $this->auth->getAttribute("FirstName")[0] : "");
        $lastName = (isset($this->auth->getAttribute("LastName")[0]) ? $this->auth->getAttribute("LastName")[0] : "");
        $email = (isset($this->auth->getAttribute("Email")[0]) ? $this->auth->getAttribute("Email")[0] : "");

        $emailSearch = new MemberSearch($email);
        $result = $emailSearch->searchLinks($this->dbh, "e", 0, true);

        if(count($result) > 0 && $result[0]["id"] > 0){
            return $result[0]["id"];
        }

        $nameSearch = new MemberSearch($firstName . " " . $lastName);
        $result = $nameSearch->searchLinks($this->dbh, "m", 0, true);

        if(count($result) == 1 && $result[0]["id"] > 0){
            return $result[0]["id"];
        }

        return 0;
    }

    public function getMetadata(){
        try {
            $settings = $this->auth->getSettings();
            $metadata = $settings->getSPMetadata();
            $errors = $settings->validateMetadata($metadata);
            if (empty($errors)) {
                header('Content-Type: text/xml');
                return $metadata;
            } else {
                throw new Error(
                    'Invalid SP metadata: '.implode(', ', $errors),
                    Error::METADATA_SP_INVALID
                    );
            }
        } catch (\Exception $e) {
            return array('error'=> $e->getMessage());
        }
    }

    private function loadConfig(\PDO $dbh){
        $query = "select * from `w_idp` where `idIdp` = :idIdp";
        $stmt = $dbh->prepare($query);
        $stmt->bindValue(":idIdp", $this->IdpId);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if(count($rows) == 1){
            $this->IdpConfig = $rows[0];

            $stmt = $this->dbh->query("select idSecGroup as Code from w_idp_secgroups where idIdp = " . $this->IdpId);
            $defaultGroups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach($defaultGroups as $g){
                $this->IdpConfig['defaultGroups'][] = $g["Code"];
            }

            $uS = Session::getInstance();

            $securityComponent = new SecurityComponent();
            $rootURL = $securityComponent->getRootURL();

            $this->SPEntityId = $rootURL . 'auth/';
            $this->SPacsURL = $rootURL . 'auth/acs?idpId=' . $this->IdpConfig["idIdp"];
            $this->SPloginURL = $rootURL . 'auth/login?idpId=' . $this->IdpConfig["idIdp"];
            $this->SPmetadataURL = $rootURL . 'auth/metadata?idpId=' . $this->IdpConfig["idIdp"];

            $this->SPSign = false;

            if($uS->keyPath){
                try{
                    $this->SPcert = (file_exists($uS->keyPath . "/certificate.crt") ? file_get_contents($uS->keyPath . "/certificate.crt") : '');
                    $this->SPRolloverCert = (file_exists($uS->keyPath . "/rollovercertificate.crt") ? file_get_contents($uS->keyPath . "/rollovercertificate.crt") : '');
                    $this->SPkey = (file_exists($uS->keyPath . "/privateKey.key") ? file_get_contents($uS->keyPath . "/privateKey.key") : '');

                    if($this->SPcert != ''){
                        openssl_x509_read($this->SPcert);
                    }
                    if($this->SPRolloverCert != ''){
                        openssl_x509_read($this->SPRolloverCert);
                    }

                    if($this->SPcert == '' && $this->SPRolloverCert == ''){
                        $this->SPSign = false;
                    }else{
                        $this->SPSign = true;
                    }
                }catch(\Exception $e){
                    $this->SPSign = false;
                }
            }


        }else{
            $this->IdpConfig = [
                "idIdp"=>0,
                "Name"=>"",
                "LogoPath"=>"",
                "SSO_URL"=>"",
                "IdP_EntityId"=>"",
                "IdP_SigningCert"=>"",
                "IdP_EncryptionCert"=>"",
                "expectIdPSigning"=>"",
                "expectIdPEncryption"=>"",
                "IdP_ManageRoles"=>"",
                "Status"=>""
            ];
        }
    }

    public function getSecurityGroups(\PDO $dbh, $titlesOnly = false){
        $stmt = $dbh->query("select Group_Code as Code, Title from w_groups");
        $groups = $stmt->fetchAll(\PDO::FETCH_BOTH);

        if($titlesOnly){ //list titles
            foreach ($groups as $g) {
                $sArray[] = $g['Title'];
            }
            return $sArray;
        }else{
            return $groups;
        }
    }

    public function getSettings(){

        $settings = array();

        $settings = [
            'baseurl' => $this->SPEntityId,
            'strict' => true,
            'sp' => [
                'entityId' => $this->SPEntityId,
                'assertionConsumerService' => [
                    'url' => $this->SPacsURL,
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                "attributeConsumingService"=> [
                    "serviceName" => "Hospitality Housekeeper",
                    "serviceDescription" => "Hospitality Housekeeper",
                    "requestedAttributes" => [
                        [
                            "name" => "FirstName",
                            "isRequired" => true
                        ],
                        [
                            "name" => "LastName",
                            "isRequired" => true
                        ],
                        [
                            "name" => "Email",
                            "isRequired" => false
                        ],
                        [
                            "name" => "Phone",
                            "isRequired" => false
                        ],
                        [
                            "name" => "hhkRole",
                            "isRequired" => true,
                            "attributeValue"=>[
                                "hhkAdminUser",
                                "hhkWebUser"
                            ]
                        ],
                        [
                            "name" => "hhkSecurityGroups",
                            "isRequired" => true,
                            "attributeValue"=>$this->getSecurityGroups($this->dbh, true)
                        ]
                    ]
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
                'x509cert' => ($this->SPcert ? $this->SPcert:''),
                'privateKey' => ($this->SPkey ? $this->SPkey:''),
                'x509certNew' => ($this->SPRolloverCert ? $this->SPRolloverCert:''),
            ],
            'idp' => [
                'entityId' => $this->IdpConfig["IdP_EntityId"],
                'singleSignOnService' => [
                    'url' => $this->IdpConfig["SSO_URL"],
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509certMulti' => array(
                    'signing' => array(
                        0 => $this->IdpConfig["IdP_SigningCert"],
                        1 => $this->IdpConfig["IdP_SigningCert2"], //rollover cert
                    ),
                    'encryption' => array(
                        0 => $this->IdpConfig["IdP_EncryptionCert"],
                        1 => $this->IdpConfig["IdP_EncryptionCert2"], //rollover cert
                    )
                )
            ],
            'security' => [

                /** signatures and encryptions offered */

                // Indicates that the nameID of the <samlp:logoutRequest> sent by this SP
                // will be encrypted.
                'nameIdEncrypted' => false,

                // Indicates whether the <samlp:AuthnRequest> messages sent by this SP
                // will be signed.  [Metadata of the SP will offer this info]
                'authnRequestsSigned' => $this->SPSign,

                // Indicates whether the <samlp:logoutRequest> messages sent by this SP
                // will be signed.
                'logoutRequestSigned' => $this->SPSign,

                // Indicates whether the <samlp:logoutResponse> messages sent by this SP
                // will be signed.
                'logoutResponseSigned' => $this->SPSign,

                /* Sign the Metadata
                 False || True (use sp certs) || array (
                 keyFileName => 'metadata.key',
                 certFileName => 'metadata.crt'
                 )
                 || array (
                 'x509cert' => '',
                 'privateKey' => ''
                 )
                 */
                'signMetadata' => $this->SPSign,

                /** signatures and encryptions required **/

                // Indicates a requirement for the <samlp:Response>, <samlp:LogoutRequest>
                // and <samlp:LogoutResponse> elements received by this SP to be signed.
                'wantMessagesSigned' => $this->IdpConfig["expectIdPSigning"],

                // Indicates a requirement for the <saml:Assertion> elements received by
                // this SP to be encrypted.
                'wantAssertionsEncrypted' => $this->IdpConfig["expectIdPEncryption"],

                // Indicates a requirement for the <saml:Assertion> elements received by
                // this SP to be signed. [Metadata of the SP will offer this info]
                'wantAssertionsSigned' => false, //$this->IdpConfig["expectIdPSigning"],

                // Indicates a requirement for the NameID element on the SAMLResponse
                // received by this SP to be present.
                'wantNameId' => true,

                // Indicates a requirement for the NameID received by
                // this SP to be encrypted.
                'wantNameIdEncrypted' => false,

                // Authentication context.
                // Set to false and no AuthContext will be sent in the AuthNRequest.
                // Set true or don't present this parameter and you will get an AuthContext 'exact' 'urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport'.
                // Set an array with the possible auth context values: array ('urn:oasis:names:tc:SAML:2.0:ac:classes:Password', 'urn:oasis:names:tc:SAML:2.0:ac:classes:X509').
                'requestedAuthnContext' => false,

                // Indicates if the SP will validate all received xmls.
                // (In order to validate the xml, 'strict' and 'wantXMLValidation' must be true).
                'wantXMLValidation' => true,

                // If true, SAMLResponses with an empty value at its Destination
                // attribute will not be rejected for this fact.
                'relaxDestinationValidation' => false,

                // If true, the toolkit will not raised an error when the Statement Element
                // contain atribute elements with name duplicated
                'allowRepeatAttributeName' => false,

                // If true, Destination URL should strictly match to the address to
                // which the response has been sent.
                // Notice that if 'relaxDestinationValidation' is true an empty Destintation
                // will be accepted.
                'destinationStrictlyMatches' => false,

                // If true, SAMLResponses with an InResponseTo value will be rejectd if not
                // AuthNRequest ID provided to the validation method.
                'rejectUnsolicitedResponsesWithInResponseTo' => false,

                // Algorithm that the toolkit will use on signing process. Options:
                //    'http://www.w3.org/2000/09/xmldsig#rsa-sha1'
                //    'http://www.w3.org/2000/09/xmldsig#dsa-sha1'
                //    'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'
                //    'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384'
                //    'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512'
                // Notice that sha1 is a deprecated algorithm and should not be used
                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',

                // Algorithm that the toolkit will use on digest process. Options:
                //    'http://www.w3.org/2000/09/xmldsig#sha1'
                //    'http://www.w3.org/2001/04/xmlenc#sha256'
                //    'http://www.w3.org/2001/04/xmldsig-more#sha384'
                //    'http://www.w3.org/2001/04/xmlenc#sha512'
                // Notice that sha1 is a deprecated algorithm and should not be used
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',

                // ADFS URL-Encodes SAML data as lowercase, and the toolkit by default uses
                // uppercase. Turn it True for ADFS compatibility on signature verification
                'lowercaseUrlencoding' => true,
            ],
            'contactPerson' => [
                'technical' => [
                    'givenName' => 'Non Profit Software Corporation',
                    'emailAddress' => 'support@nonprofitsoftwarecorp.org'
                ],
                'support' => [
                    'givenName' => 'Non Profit Software Corporation',
                    'emailAddress' => 'support@nonprofitsoftwarecorp.org'
                ],
            ],
            'organization' => [
                'en-US' => [
                    'name' => 'Non Profit Software Corp',
                    'displayname' => 'Non Profit Software Corp',
                    'url' => 'https://nonprofitsoftwarecorp.org'
                ],
            ],
        ];

        return $settings;
    }

    private function getCertificateInfo($type, $certStr = ''){

        if($type == "idpSign"){
            $certData = $this->IdpConfig["IdP_SigningCert"];
        }else if($type == "idpSign2"){
                $certData = $this->IdpConfig["IdP_SigningCert2"];
        }else if($type == "idpEncryption"){
            $certData = $this->IdpConfig["IdP_EncryptionCert"];
        }else if($type == "idpEncryption2"){
                $certData = $this->IdpConfig["IdP_EncryptionCert2"];
        }else if($type == "sp"){
            $certData = $this->SPcert;
        }else if($type == "sprollover"){
            $certData = $this->SPRolloverCert;
        }else{
            $certData = $certStr;
        }

        $certInfo = openssl_x509_parse($certData);
        if($certInfo){
            $validFromDate = date_create_from_format('ymdHise',$certInfo["validFrom"]);
            $validToDate = date_create_from_format('ymdHise', $certInfo["validTo"]);

            return array(
                "issuer"=>(isset($certInfo["issuer"]["O"]) ? $certInfo["issuer"]["O"]:""),
                "validFrom"=>$validFromDate->format("M j, Y"),
                "expires"=>$validToDate->format("M j, Y")
            );
        }else{
            return false;
        }
    }

    public function getEditMarkup($formOnly = false){

        $idpSigningCertInfo = $this->getCertificateInfo("idpSign");
        $idpRolloverSigningCertInfo = $this->getCertificateInfo("idpSign2");
        $idpEncryptionCertInfo = $this->getCertificateInfo("idpEncryption");
        $idpRolloverEncryptionCertInfo = $this->getCertificateInfo("idpEncryption2");
        $spCertInfo = $this->getCertificateInfo("sp");
        $spRolloverCertInfo = $this->getCertificateInfo("sprollover");

        $tbl = new HTMLTable();

        $tbl->addBodyTr(
            $tbl->makeTd("Name", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup($this->IdpConfig["Name"], array("name"=>"idpConfig[" . $this->IdpId . "][name]", "size"=>"50"))
            ).
            $tbl->makeTd("Friendly name used on the login page, user settings pages and error messages")
        );

        $tbl->addBodyTr(
            $tbl->makeTd("Logo Path", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup($this->IdpConfig["LogoPath"], array("name"=>"idpConfig[" . $this->IdpId . "][LogoPath]", "size"=>"50"))
            ).
            $tbl->makeTd("Path to a logo image (relative to /conf/ directory) to use in place of the IdP Name on login pages")
        );

        if($this->IdpId !== 'new'){
            $tbl->addBodyTr(
                $tbl->makeTd("", array("colspan"=>"3", "style"=>"height:1em;"))
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Identity Provider Settings", array("colspan"=>"3", "style"=>"font-weight:bold;border-top: solid 1px black;"))
            );

            $tbl->addBodyTr(
                $tbl->makeTd("Upload IdP metadata", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLInput::generateMarkup("", array("type"=>"file", "accept"=>"text/xml", "name"=>"idpConfig[" . $this->IdpId . "][idpMetadata]"))
                ).
                $tbl->makeTd("Upload an XML metadata file to autofill the following IdP settings")
            );

            $tbl->addBodyTr(
                $tbl->makeTd("SSO URL", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLInput::generateMarkup($this->IdpConfig["SSO_URL"], array("name"=>"idpConfig[" . $this->IdpId . "][ssoUrl]", "size"=>"50"))
                ).
                $tbl->makeTd("Single Sign On URL")
            );

            $tbl->addBodyTr(
                $tbl->makeTd("IdP Entity ID", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLInput::generateMarkup($this->IdpConfig["IdP_EntityId"], array("name"=>"idpConfig[" . $this->IdpId . "][idpEntityId]", "size"=>"50"))
                ).
                $tbl->makeTd("Entity ID for Identity Provider")
            );

            $tbl->addBodyTr(
                $tbl->makeTd("IdP Signing Certificate", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("textarea", $this->IdpConfig["IdP_SigningCert"], array("name"=>"idpConfig[" . $this->IdpId . "][idpSigningCert]", "rows"=>"4", "style"=>"width: 100%"))
                ).
                $tbl->makeTd(
                    (is_array($idpSigningCertInfo) ?
                    '<span style="font-weight: bold">Installed Certificate</span><br>' .
                    '<span style="font-weight: bold">Issuer: </span>' . $idpSigningCertInfo["issuer"] . '</span><br>' .
                    '<span style="font-weight: bold">Valid From: </span>' . $idpSigningCertInfo["validFrom"] . '</span><br>' .
                    '<span style="font-weight: bold">Expires: </span>' . $idpSigningCertInfo["expires"] . '</span>'
                    : '')
                )
            );

            $tbl->addBodyTr(
                $tbl->makeTd("IdP Rollover Signing Certificate", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("textarea", $this->IdpConfig["IdP_SigningCert2"], array("name"=>"idpConfig[" . $this->IdpId . "][idpSigningCert2]", "rows"=>"4", "style"=>"width: 100%"))
                    ).
                $tbl->makeTd(
                    (is_array($idpRolloverSigningCertInfo) ?
                        '<span style="font-weight: bold">Installed Certificate</span><br>' .
                        '<span style="font-weight: bold">Issuer: </span>' . $idpRolloverSigningCertInfo["issuer"] . '</span><br>' .
                        '<span style="font-weight: bold">Valid From: </span>' . $idpRolloverSigningCertInfo["validFrom"] . '</span><br>' .
                        '<span style="font-weight: bold">Expires: </span>' . $idpRolloverSigningCertInfo["expires"] . '</span>'
                        : '')
                    )
                );

            $tbl->addBodyTr(
                $tbl->makeTd("IdP Encryption Certificate", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("textarea", $this->IdpConfig["IdP_EncryptionCert"], array("name"=>"idpConfig[" . $this->IdpId . "][idpEncryptionCert]", "rows"=>"4", "style"=>"width: 100%"))
                    ).
                $tbl->makeTd(
                    (is_array($idpEncryptionCertInfo) ?
                        '<span style="font-weight: bold">Installed Certificate</span><br>' .
                        '<span style="font-weight: bold">Issuer: </span>' . $idpEncryptionCertInfo["issuer"] . '</span><br>' .
                        '<span style="font-weight: bold">Valid From: </span>' . $idpEncryptionCertInfo["validFrom"] . '</span><br>' .
                        '<span style="font-weight: bold">Expires: </span>' . $idpEncryptionCertInfo["expires"] . '</span>'
                        : '')
                    )
                );

            $tbl->addBodyTr(
                $tbl->makeTd("IdP Rollover Encryption Certificate", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("textarea", $this->IdpConfig["IdP_EncryptionCert2"], array("name"=>"idpConfig[" . $this->IdpId . "][idpEncryptionCert2]", "rows"=>"4", "style"=>"width: 100%"))
                    ).
                $tbl->makeTd(
                    (is_array($idpRolloverEncryptionCertInfo) ?
                        '<span style="font-weight: bold">Installed Certificate</span><br>' .
                        '<span style="font-weight: bold">Issuer: </span>' . $idpRolloverEncryptionCertInfo["issuer"] . '</span><br>' .
                        '<span style="font-weight: bold">Valid From: </span>' . $idpRolloverEncryptionCertInfo["validFrom"] . '</span><br>' .
                        '<span style="font-weight: bold">Expires: </span>' . $idpRolloverEncryptionCertInfo["expires"] . '</span>'
                        : '')
                    )
                );

            $boolOpts = array(
                array(1, 'True'),
                array(0, 'False')
            );

            $statusOpts = array(
                array('a', 'Active'),
                array('d', 'Disabled')
            );

            $tbl->addBodyTr(
                $tbl->makeTd("Require IdP Response Signing", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($boolOpts, $this->IdpConfig['expectIdPSigning'], FALSE), array('name' => "idpConfig[" . $this->IdpId . "][expectIdPSigning]"))
                ) .
                $tbl->makeTd("If true, all &lt;samlp:Response&gt; elements received from the IdP must be signed.")
            );

            $tbl->addBodyTr(
                $tbl->makeTd("Require IdP Encryption", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($boolOpts, $this->IdpConfig['expectIdPEncryption'], FALSE), array('name' => "idpConfig[" . $this->IdpId . "][expectIdPEncryption]"))
                    ) .
                $tbl->makeTd("If true, all &lt;saml:Assertion&gt; elements received from the IdP must be encrypted.")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Require IdP to manage Roles", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($boolOpts, $this->IdpConfig['IdP_ManageRoles'], FALSE), array('name' => "idpConfig[" . $this->IdpId . "][IdP_ManageRoles]"))
                    ) .
                $tbl->makeTd("If true, HHK Roles and Security Groups must be defined by the IdP as attributes. If false, HHK admins manage Roles and Security Groups")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Default Security Groups", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($this->getSecurityGroups($this->dbh), $this->IdpConfig["defaultGroups"], true), array('multiple'=>'multiple', 'size'=>'10', 'name' => "idpConfig[" . $this->IdpId . "][defaultSecurityGroups][]"))
                    ) .
                $tbl->makeTd("If the IdP doesn't define any security groups, new users will be provisioned with these.")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("IdP Status", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($statusOpts, $this->IdpConfig['Status'], FALSE), array('name' => "idpConfig[" . $this->IdpId . "][Status]"))
                    ) .
                $tbl->makeTd("Enable/Disable this Identity Provider")
                );
        }
        $tbl->addBodyTr(
            $tbl->makeTd(
                HTMLInput::generateMarkup("Save", array("type"=>"submit", "class"=>"ui-button ui-corner-all ui-widget mb-5"))
                , array("colspan"=>"3", "style"=>"text-align:right;"))
            );

        if($this->IdpId !== 'new'){

            $tbl->addBodyTr(
                $tbl->makeTd("Service Provider Information", array("colspan"=>"3", "style"=>"font-weight:bold;border-top: solid 1px black;"))
                );

            $tbl->addBodyTr(
                $tbl->makeTd("SP EntityId", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    $this->SPEntityId
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("SP ACS URL", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    $this->SPacsURL
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("SP Signing", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    ($this->SPSign ? "True":"False")
                    ) .
                $tbl->makeTd("HHK will sign all authnRequests and metadata using sha-256.")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("SP Certificate", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("textarea", $this->SPcert, array("readonly"=>"readonly", "rows"=>"4", "style"=>"width: 100%"))
                    ).
                $tbl->makeTd(
                    (is_array($spCertInfo) ?
                    '<span style="font-weight: bold">Installed Certificate</span><br>' .
                    '<span style="font-weight: bold">Issuer: </span>' . $spCertInfo["issuer"] . '</span><br>' .
                    '<span style="font-weight: bold">Valid From: </span>' . $spCertInfo["validFrom"] . '</span><br>' .
                    '<span style="font-weight: bold">Expires: </span>' . $spCertInfo["expires"] . '</span>'
                    : '')
                    )
                );

            $tbl->addBodyTr(
                $tbl->makeTd("SP Rollover Certificate", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("textarea", $this->SPRolloverCert, array("readonly"=>"readonly", "rows"=>"4", "style"=>"width: 100%"))
                    ).
                $tbl->makeTd(
                    (is_array($spRolloverCertInfo) ?
                        '<span style="font-weight: bold">Installed Certificate</span><br>' .
                        '<span style="font-weight: bold">Issuer: </span>' . $spRolloverCertInfo["issuer"] . '</span><br>' .
                        '<span style="font-weight: bold">Valid From: </span>' . $spRolloverCertInfo["validFrom"] . '</span><br>' .
                        '<span style="font-weight: bold">Expires: </span>' . $spRolloverCertInfo["expires"] . '</span>'
                        : '')
                    )
                );

            $tbl->addBodyTr(
                $tbl->makeTd("SP Metadata", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    HTMLContainer::generateMarkup("a", "Download SP Metadata", array("href"=>$this->SPmetadataURL, "download"=>"HHKmetadata.xml", "class"=>"ui-button ui-corner-all ui-widget"))
                ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("", array("colspan"=>"3", "style"=>"height:1em;"))
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Response Attributes", array("colspan"=>"3", "style"=>"font-weight:bold;border-top: solid 1px black;"))
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Please send the following attributes in SAML responses", array("colspan"=>"3"))
                );

            $tbl->addBodyTr(
                $tbl->makeTd("nameId", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    "Required - nameId will be used as the username"
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("FullName", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    "Optional"
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("FirstName", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    "Required if FullName is empty"
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("LastName", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    "Required if FullName is empty"
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Email", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    "Optional"
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("Phone", array("class"=>"tdlabel")).
                $tbl->makeTd(
                    "Optional"
                    ) .
                $tbl->makeTd("")
                );

            $tbl->addBodyTr(
                $tbl->makeTd("hhkRole", array("style"=>"text-align:right; vertical-align:top;")).
                $tbl->makeTd(
                    "Required - determines the user's role in HHK<br>" .
                    "<strong>Possible Values:</strong><br>" .
                    "hhkAdminUser<br>" .
                    "hhkWebUser"
                    ) .
                $tbl->makeTd(
                    ""
                    , array("style"=>"vertical-align:top;"))
                );

            $tbl->addBodyTr(
                $tbl->makeTd("hhkSecurityGroups", array("style"=>"vertical-align:top; text-align:right")).
                $tbl->makeTd(
                    "Required - determines the user's Security Groups in HHKMultiple values accepted<br>" .
                    "<strong>Possible Values:</strong><br>" .
                    implode("<br>", $this->getSecurityGroups($this->dbh, true))
                    ) .
                $tbl->makeTd(
                    ""
                    )
                );
        }
        $formContent = $tbl->generateMarkup(array("style"=>"margin-bottom: 0.5em;"));

        if($formOnly){
            return $formContent;
        }else{
            return HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("form", $formContent, array("class"=>"authForm", "id"=>"form" . $this->IdpId . "auth", "method"=>"post", "action"=>"Configure.php", "enctype"=>"multipart/form-data")), array("id"=>$this->IdpId . "Auth", "class"=>"ui-tabs-hide"));
        }
    }

    public function save($post, $files){
        if(isset($post['idpConfig'][$this->IdpId])){
            $idpConfig = array(
                'name'=>'',
                'LogoPath'=>'',
                'ssoUrl'=>'',
                'idpEntityId'=>'',
                'idpSigningCert'=>'',
                'idpSigningCert2'=>'',
                'idpEncryptionCert'=>'',
                'idpEncryptionCert2'=>'',
                'expectIdPSigning'=>false,
                'expectIdPEncryption'=>false,
                'IdP_ManageRoles'=>true,
                'status'=>'d'
            );
            $errorMsg = '';

            if(isset($post['idpConfig'][$this->IdpId]['name']) && $post['idpConfig'][$this->IdpId]['name'] != ''){
                $idpConfig['name'] = filter_var($post['idpConfig'][$this->IdpId]['name'], FILTER_SANITIZE_STRING);
            }else{
                $errorMsg .= "<br>Name is required";
            }

            if(isset($post['idpConfig'][$this->IdpId]['LogoPath'])){
                $idpConfig['LogoPath'] = filter_var($post['idpConfig'][$this->IdpId]['LogoPath'], FILTER_SANITIZE_STRING);
            }

            if(!isset($post['idpConfig']['new'])){

                if(isset($files['idpConfig']['tmp_name'][$this->IdpId]['idpMetadata']) && $files['idpConfig']['tmp_name'][$this->IdpId]['idpMetadata'] != ''){
                    $metadata = $this->checkMetadataFiles($files['idpConfig']['tmp_name'][$this->IdpId]['idpMetadata']);
                    $idpConfig['ssoUrl'] = (isset($metadata['idp']['singleSignOnService']['url']) ? $metadata['idp']['singleSignOnService']['url'] : '');
                    $idpConfig['idpEntityId'] = (isset($metadata['idp']['entityId']) ? $metadata['idp']['entityId'] : '');
                    $idpConfig['idpSigningCert'] = (isset($metadata['idp']['x509cert']) ? $metadata['idp']['x509cert'] : (isset($metadata['idp']['x509certMulti']['signing'][0]) ? $metadata['idp']['x509certMulti']['signing'][0]: ""));
                    $idpConfig['idpSigningCert2'] = (isset($metadata['idp']['x509certMulti']['signing'][1]) ? $metadata['idp']['x509certMulti']['signing'][1] :'');
                    $idpConfig['idpEncryptionCert'] = (isset($metadata['idp']['x509cert']) ? $metadata['idp']['x509cert'] : (isset($metadata['idp']['x509certMulti']['encryption'][0]) ? $metadata['idp']['x509certMulti']['encryption'][0]: ""));
                    $idpConfig['idpEncryptionCert2'] = (isset($metadata['idp']['x509certMulti']['encryption'][1]) ? $metadata['idp']['x509certMulti']['encryption'][1] :'');
                }else{

                    if(isset($post['idpConfig'][$this->IdpId]['ssoUrl'])){
                        $idpConfig['ssoUrl'] = filter_var($post['idpConfig'][$this->IdpId]['ssoUrl'], FILTER_SANITIZE_URL);
                    }

                    if(isset($post['idpConfig'][$this->IdpId]['idpEntityId'])){
                        $idpConfig['idpEntityId'] = filter_var($post['idpConfig'][$this->IdpId]['idpEntityId'], FILTER_SANITIZE_URL);
                    }

                    if(isset($post['idpConfig'][$this->IdpId]['idpSigningCert'])){
                        $idpConfig['idpSigningCert'] = filter_var($post['idpConfig'][$this->IdpId]['idpSigningCert'], FILTER_SANITIZE_STRING);
                    }

                    if(isset($post['idpConfig'][$this->IdpId]['idpSigningCert2'])){
                        $idpConfig['idpSigningCert2'] = filter_var($post['idpConfig'][$this->IdpId]['idpSigningCert2'], FILTER_SANITIZE_STRING);
                    }

                    if(isset($post['idpConfig'][$this->IdpId]['idpEncryptionCert'])){
                        $idpConfig['idpEncryptionCert'] = filter_var($post['idpConfig'][$this->IdpId]['idpEncryptionCert'], FILTER_SANITIZE_STRING);
                    }

                    if(isset($post['idpConfig'][$this->IdpId]['idpEncryptionCert2'])){
                        $idpConfig['idpEncryptionCert2'] = filter_var($post['idpConfig'][$this->IdpId]['idpEncryptionCert2'], FILTER_SANITIZE_STRING);
                    }
                }

                if($idpConfig['ssoUrl'] == ''){
                    $errorMsg.= "<br>SSO URL is required";
                }
                if($idpConfig['idpEntityId'] == ''){
                    $errorMsg.= "<br>Idp Entity ID is required";
                }
                if($idpConfig['idpSigningCert'] == ''){
                    $errorMsg.= "<br>Idp Signing Cert is required";
                }else{
                    $formattedCert = Utils::formatCert($idpConfig['idpSigningCert'], true);
                    if(!is_array($this->getCertificateInfo(false, $formattedCert))){
                        $errorMsg.="<br>Idp Signing Cert must be a valid certificate";
                    }else{
                        $idpConfig["idpSigningCert"] = $formattedCert;
                    }
                }
                if($idpConfig['idpSigningCert2'] != ''){
                    $formattedCert = Utils::formatCert($idpConfig['idpSigningCert'], true);
                    if(!is_array($this->getCertificateInfo(false, $formattedCert))){
                        $errorMsg.="<br>Idp Rollover Signing Cert must be a valid certificate";
                    }else{
                        $idpConfig["idpSigningCert2"] = $formattedCert;
                    }
                }
                if($idpConfig['idpEncryptionCert'] == ''){
                    $errorMsg.= "<br>Idp Encryption Cert is required";
                }else{
                    $formattedCert = Utils::formatCert($idpConfig['idpEncryptionCert'], true);
                    if(!is_array($this->getCertificateInfo(false, $formattedCert))){
                        $errorMsg.="<br>Idp Encryption Cert must be a valid certificate";
                    }else{
                        $idpConfig["idpEncryptionCert"] = $formattedCert;
                    }
                }
                if($idpConfig['idpEncryptionCert2'] != ''){
                    $formattedCert = Utils::formatCert($idpConfig['idpEncryptionCert'], true);
                    if(!is_array($this->getCertificateInfo(false, $formattedCert))){
                        $errorMsg.="<br>Idp Rollover Encryption Cert must be a valid certificate";
                    }else{
                        $idpConfig["idpEncryptionCert2"] = $formattedCert;
                    }
                }

                if(isset($post['idpConfig'][$this->IdpId]['expectIdPSigning'])){
                    $idpConfig['expectIdPSigning'] = boolval(filter_var($post['idpConfig'][$this->IdpId]['expectIdPSigning'], FILTER_VALIDATE_BOOLEAN));
                }

                if(isset($post['idpConfig'][$this->IdpId]['expectIdPEncryption'])){
                    $idpConfig['expectIdPEncryption'] = boolval(filter_var($post['idpConfig'][$this->IdpId]['expectIdPEncryption'], FILTER_VALIDATE_BOOLEAN));
                }

                if(isset($post['idpConfig'][$this->IdpId]['IdP_ManageRoles'])){
                    $idpConfig['IdP_ManageRoles'] = boolval(filter_var($post['idpConfig'][$this->IdpId]['IdP_ManageRoles'], FILTER_VALIDATE_BOOLEAN));
                }

                if(isset($post['idpConfig'][$this->IdpId]['Status'])){
                    $idpConfig['status'] = filter_var($post['idpConfig'][$this->IdpId]['Status']);
                }
            }
            if($errorMsg !=''){
                throw new \ErrorException($errorMsg);
            }

            $wIdpRS = new W_idpRS();
            EditRS::loadRow($this->IdpConfig, $wIdpRS);

            $wIdpRS->Name->setNewVal($idpConfig['name']);
            $wIdpRS->LogoPath->setNewVal($idpConfig['LogoPath']);
            $wIdpRS->SSO_URL->setNewVal($idpConfig['ssoUrl']);
            $wIdpRS->IdP_EntityId->setNewVal($idpConfig['idpEntityId']);
            $wIdpRS->IdP_SigningCert->setNewVal($idpConfig['idpSigningCert']);
            $wIdpRS->IdP_SigningCert2->setNewVal($idpConfig['idpSigningCert2']);
            $wIdpRS->IdP_EncryptionCert->setNewVal($idpConfig['idpEncryptionCert']);
            $wIdpRS->IdP_EncryptionCert2->setNewVal($idpConfig['idpEncryptionCert2']);
            $wIdpRS->expectIdPSigning->setNewVal($idpConfig['expectIdPSigning']);
            $wIdpRS->expectIdPEncryption->setNewVal($idpConfig['expectIdPEncryption']);
            $wIdpRS->IdP_ManageRoles->setNewVal($idpConfig['IdP_ManageRoles']);
            $wIdpRS->Status->setNewVal($idpConfig['status']);

            //save default security groups
            $this->updateDefaultSecurityGroups($post['idpConfig'][$this->IdpId]['defaultSecurityGroups']);

            if($this->IdpId == 'new'){
                $id = EditRS::insert($this->dbh, $wIdpRS);
                return new SAML($this->dbh, $id);
            }else{
                EditRS::update($this->dbh, $wIdpRS, array($wIdpRS->idIdp));
                return new SAML($this->dbh, $this->IdpId);
            }
        }

        throw new \ErrorException("Error saving provider: IdP id doesn't match expected IdP id . Expected " . $this->IdpId . ", got " . array_key_first($post['idpConfig']));
    }

    private function checkMetadataFiles($file){
        if(file_exists($file) && mime_content_type($file) == "text/xml"){
            return IdPMetadataParser::parseFileXML($file);
        }else{
            throw new \ErrorException("Uploaded file is not an XML file");
        }
    }

    public static function getIdpMarkup(\PDO $dbh){
        $IdPs = self::getIdpList($dbh);
        $uS = Session::getInstance();
        $container = '';

        if(count($IdPs) > 0){
            $contentMkup = "";
            foreach($IdPs as $key=>$IdP){
                $attrs = array();

                if($key !== array_key_last($IdPs)){
                    $attrs['class'] = "mb-3";
                }

                if($IdP["LogoPath"] !=""){
                    $contentMkup .= HTMLContainer::generateMarkup("li",
                        HTMLContainer::generateMarkup(
                            "a",'<img src="' . $uS->resourceURL . 'conf/' . $IdP["LogoPath"] . '" height="50px">',
                            array("href"=>$uS->resourceURL . "auth/login?idpId=" . $IdP["idIdp"], "class"=>"ui-button ui-corner-all")
                        )
                    , $attrs);
                }else{
                    $contentMkup .= HTMLContainer::generateMarkup("li",
                        HTMLContainer::generateMarkup(
                            "a", "Login with " . $IdP["Name"],
                            array("href"=>$uS->resourceURL . "auth/login?idpId=" . $IdP["idIdp"], "class"=>"ui-button ui-corner-all")
                        )
                    , $attrs);
                }
            }

            $contentMkup = HTMLContainer::generateMarkup("ul", $contentMkup, array("class"=>"list-style-none"));

            $container = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("div",
                    HTMLContainer::generateMarkup("div",
                        HTMLContainer::generateMarkup("div", "Single Sign On", array("class"=>"ui-widget-header ui-corner-top p-1")) .
                        HTMLContainer::generateMarkup("div", $contentMkup, array("class"=>"ui-widget-content ui-corner-bottom hhk-tdbox p-3"))
                    , array("class"=>"ui-widget mb-3")) .
                    HTMLContainer::generateMarkup("p", "- OR -")
                , array("class"=>"col-12"))
            , array("class"=>"row justify-content-md-center mb-3 center"));

        }

        return $container;
    }

    private function updateDefaultSecurityGroups(array $parms){
        // Group Code security table
        //$sArray = readGenLookups($dbh, "Group_Code");
        $stmt = $this->dbh->query("select Group_Code as Code, Description from w_groups");
        $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($groups as $g) {
            $sArray[$g['Code']] = $g;
        }



        $secRS = new W_idp_secgroupsRS();
        $secRS->idIdp->setStoredVal($this->IdpId);
        $rows = EditRS::select($this->dbh, $secRS, array($secRS->idIdp));

        foreach ($rows as $r) {
            $sArray[$r['Group_Code']]["exist"] = "t";
        }

        $updtd = FALSE;

        foreach ($sArray as $g) {

            if (!isset($g["exist"]) && in_array($g["Code"], $parms)) {

                // new group code to put into the database
                $secRS = new W_idp_secgroupsRS();
                $secRS->idIdp->setNewVal($this->IdpId);
                $secRS->idSecGroup->setNewVal($g["Code"]);
                $n = EditRS::insert($this->dbh, $secRS);

                $updtd = TRUE;

            } else if (isset($g["exist"]) && !in_array($g["Code"], $parms)) {

                // group code to delete from the database.
                $secRS = new W_idp_secgroupsRS();
                $secRS->idIdp->setStoredVal($this->IdpId);
                $secRS->idSecGroup->setStoredVal($g["Code"]);
                $n = EditRS::delete($this->dbh, $secRS, array($secRS->idIdp, $secRS->idSecGroup));

                if ($n == 1) {
                    $updtd = TRUE;
                }
            }
        }
        return $updtd;
    }

    public static function getIdpList(\PDO $dbh, $onlyActive = true){

        $query = "select * from `w_idp` " . ($onlyActive ? "where `Status` = 'a'": "");
        $stmt = $dbh->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    public function getIdIdp(){
        return $this->IdpId;
    }

    public function getIdpName(){
        return $this->IdpConfig["Name"];
    }

    public function getIdpManageRoles(){
        return $this->IdpConfig["IdP_ManageRoles"];
    }

}
?>