<?php
namespace HHK\sec;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;
use HHK\Member\AbstractMember;
use HHK\Member\WebUser;
use HHK\SysConst\MemBasis;
use HHK\SysConst\WebRole;
use HHK\Tables\WebSec\W_usersRS;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\SysConst\GLTableNames;
use HHK\Member\MemberSearch;
use HHK\HTMLControls\HTMLInput;
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
    protected $dbh;
    protected $auditUser;

    public function __construct(\PDO $dbh, $idpId){
        $this->IdpId = $idpId;
        $this->loadConfig($dbh);
        if($this->IdpConfig){
            $this->dbh = $dbh;
            $this->auth = new Auth($this->getSettings());
            $this->auditUser = "SAML: " . $this->IdpConfig["Name"];
        }else{
            throw new \ErrorException("Cannot load Identity Providor configuration: Invalid IdpId");
        }
    }

    public function login(){
        $this->auth->login();
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
            $error = 'Authentication Failed: ' . $error . " - " . $this->auth->getLastErrorReason();
        }else{
            //auth success
            $u = new UserClass();
            $userAr = $u->getUserCredentials($this->dbh, $this->auth->getNameId());

            if($userAr == null || (isset($userAr["idIdp"]) && $userAr["idIdp"] == $this->IdpId)){ //correct user found, set up session
                $userAr = $this->updateUser(); //create/update user with details from IdP
                if($u->doLogin($this->dbh, $userAr)){
                    header('location:../' . $uS->webSite['Relative_Address'].$uS->webSite['Default_Page']);
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

    public function updateUser(){

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

        $post = array();
        $post["txtFirstName"] = (isset($this->auth->getAttribute("FirstName")[0]) ? $this->auth->getAttribute("FirstName")[0]: "");
        $post["txtLastName"] = (isset($this->auth->getAttribute("LastName")[0]) ? $this->auth->getAttribute("LastName")[0]: "");
        $post["txtEmail"][1] = (isset($this->auth->getAttribute("Email")[0]) ? $this->auth->getAttribute("Email")[0]: "");
        $post["rbEmPref"] = "1";
        $post["txtPhone"]["dh"] = (isset($this->auth->getAttribute("Phone")[0]) ? $this->auth->getAttribute("Phone")[0]: "");
        $post["rbPhPref"] = "dh";

        $msg = $name->saveChanges($this->dbh, $post, $this->auditUser);
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

            //register Web User
            $query = "call register_web_user(" . $idName . ", '', '" . $this->auth->getNameId() . "', '" . $this->auditUser . "', 'p', '" . $role . "', '', 'v', 0, " . $this->IdpId . ");";
            if($this->dbh->exec($query) === false){
                $err = $this->dbh->errorInfo();
                return array("error"=>$err[0] . "; " . $err[2]);
            }

            UserClass::insertUserLog($this->dbh, "PS", $this->auditUser);

            $user = UserClass::getUserCredentials($this->dbh, $this->auth->getNameId());


            //make parms array for group update
            $parms = array();
            $attributes = $this->auth->getAttributes();
            $allSecurityGroups = $this->getSecurityGroups($this->dbh);

            //fill parms array
            if(isset($attributes["hhkSecurityGroups"])){
                foreach($allSecurityGroups as $secGroup){
                    if(in_array($secGroup["Title"], $attributes["hhkSecurityGroups"])){
                        $parms["grpSec_" . $secGroup["Code"]] = "checked";
                    }else{
                        $parms["grpSec_" . $secGroup["Code"]] = "unchecked";
                    }
                }
                //update security groups
                WebUser::updateSecurityGroups($this->dbh, $user["idName"], $parms, $this->auditUser);
            }
        }else{
            return array("error"=>$msg);
        }

        return $user;
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
        $query = "select * from `w_idp` where `idIdp` = :idIdp and `Status` = :status;";
        $stmt = $dbh->prepare($query);
        $stmt->bindValue(":idIdp", $this->IdpId);
        $stmt->bindValue(":status", "a");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if(count($rows) == 1){
            $this->IdpConfig = $rows[0];
        }else{
            $this->IdpConfig = [];
        }
    }

    public function getSecurityGroups(\PDO $dbh, $titlesOnly = false){
        $stmt = $dbh->query("select Group_Code as Code, Title from w_groups");
        $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
        $securityComponent = new SecurityComponent();
        $wsURL = $securityComponent->getRootURL() . 'ws_SSO.php';

        $settings = [
            'baseurl' => $securityComponent->getRootURL(),
            'strict' => true,
            'sp' => [
                'entityId' => $securityComponent->getRootURL(),
                'assertionConsumerService' => [
                    'url' => $wsURL . '?cmd=acs&idpId=' . $this->IdpConfig["idIdp"],
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
                            "isRequired" => true
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
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'x509cert' => '',
                'privateKey' => '',
            ],
            'idp' => [
                'entityId' => $this->IdpConfig["IdP_EntityId"],
                'singleSignOnService' => [
                    'url' => $this->IdpConfig["SSO_URL"],
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => $this->IdpConfig["IdP_Cert"],
            ],
            'security' => [

                /** signatures and encryptions offered */

                // Indicates that the nameID of the <samlp:logoutRequest> sent by this SP
                // will be encrypted.
                'nameIdEncrypted' => false,

                // Indicates whether the <samlp:AuthnRequest> messages sent by this SP
                // will be signed.  [Metadata of the SP will offer this info]
                'authnRequestsSigned' => $this->IdpConfig["enableSPSigning"],

                // Indicates whether the <samlp:logoutRequest> messages sent by this SP
                // will be signed.
                'logoutRequestSigned' => $this->IdpConfig["enableSPSigning"],

                // Indicates whether the <samlp:logoutResponse> messages sent by this SP
                // will be signed.
                'logoutResponseSigned' => $this->IdpConfig["enableSPSigning"],

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
                'signMetadata' => false,

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
                'lowercaseUrlencoding' => false,
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

    private function getCertificateInfo(){
        $certInfo = openssl_x509_parse($this->IdpConfig["IdP_Cert"]);
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

    public function getEditMarkup(){

        $securityComponent = new SecurityComponent();
        $wsURL = $securityComponent->getRootURL() . 'auth/ws_SSO.php';
        $idpCertInfo = $this->getCertificateInfo();

        $tbl = new HTMLTable();

        $tbl->addBodyTr(
            $tbl->makeTd("Name", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup($this->IdpConfig["Name"], array("name"=>"idpConfig[" . $this->IdpId . "][name]", "size"=>"50"))
            )
        );

        $tbl->addBodyTr(
            $tbl->makeTd("Logo URL", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup($this->IdpConfig["Logo_URL"], array("name"=>"idpConfig[" . $this->IdpId . "][logoUrl]", "size"=>"50"))
            )
        );

        $tbl->addBodyTr(
            $tbl->makeTd("SSO URL", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup($this->IdpConfig["SSO_URL"], array("name"=>"idpConfig[" . $this->IdpId . "][ssoUrl]", "size"=>"50"))
            )
        );

        $tbl->addBodyTr(
            $tbl->makeTd("IdP Entity ID", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup($this->IdpConfig["IdP_EntityId"], array("name"=>"idpConfig[" . $this->IdpId . "][idpEntityId]", "size"=>"50"))
            )
        );

        $tbl->addBodyTr(
            $tbl->makeTd("IdP Certificate", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLContainer::generateMarkup("textarea", "", array("placeholder"=>"(unchanged)", "name"=>"idpConfig[" . $this->IdpId . "][idpCert]", "rows"=>"4", "style"=>"width: 100%"))
            ).
            $tbl->makeTd(
                '<span style="font-weight: bold">Installed Certificate</span><br>' .
                '<span style="font-weight: bold">Issuer: </span>' . $idpCertInfo["issuer"] . '</span><br>' .
                '<span style="font-weight: bold">Valid From: </span>' . $idpCertInfo["validFrom"] . '</span><br>' .
                '<span style="font-weight: bold">Expires: </span>' . $idpCertInfo["expires"] . '</span>'
            )
        );

        $idpSignAttrs = array("type"=>"checkbox","disabled"=>"disabled", "name"=>"idpConfig[" . $this->IdpId . "][expectIdPSigning]");
        if($this->IdpConfig["expectIdPSigning"] == "1"){
            $idpSignAttrs["checked"] = "checked";
        }
        $tbl->addBodyTr(
            $tbl->makeTd("Require IdP Response Signing", array("class"=>"tdLabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup("", $idpSignAttrs)
            ) .
            $tbl->makeTd("If checked, all &lt;samlp:Response&gt; elements received from the IdP must be signed.")
        );

        $idpEncAttrs = array("type"=>"checkbox","disabled"=>"disabled", "name"=>"idpConfig[" . $this->IdpId . "][expectIdPEncryption]");
        if($this->IdpConfig["expectIdPEncryption"] == "1"){
            $idpEncAttrs["checked"] = "checked";
        }
        $tbl->addBodyTr(
            $tbl->makeTd("Require IdP Encryption", array("class"=>"tdLabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup("", $idpEncAttrs)
                ) .
            $tbl->makeTd("If checked, all &lt;saml:Assertion&gt; elements received from the IdP must be encrypted.")
            );

        $spSignAttrs = array("type"=>"checkbox","disabled"=>"disabled", "name"=>"idpConfig[" . $this->IdpId . "][enableSPSigning]");
        if($this->IdpConfig["enableSPSigning"] == "1"){
            $spSignAttrs["checked"] = "checked";
        }
        $tbl->addBodyTr(
            $tbl->makeTd("Sign AuthnRequests", array("class"=>"tdLabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup("", $spSignAttrs)
                ) .
            $tbl->makeTd("If checked, HHk will sign all &lt;samlp:AuthnRequest&gt; messages.")
            );

        $tbl->addBodyTr(
            $tbl->makeTd("Upload IdP metadata", array("class"=>"tdlabel")).
            $tbl->makeTd(
                HTMLInput::generateMarkup("", array("type"=>"file", "name"=>"idpConfig[" . $this->IdpId . "][idpMetadata]"))
            )
        );

        $metadataBtn = HTMLContainer::generateMarkup("a", "Download SP Metadata", array("href"=>$wsURL . '?cmd=metadata&idpId=' . $this->IdpId, "download"=>"HHKmetadata.xml", "class"=>"ui-button ui-corner-all ui-widget"));

        return HTMLContainer::generateMarkup("div", $tbl->generateMarkup(array("style"=>"margin-bottom: 0.5em;")) . $metadataBtn, array("id"=>$this->IdpId . "Auth", "class"=>"ui-tabs-hide"));

    }

    public static function getIdpMarkup(\PDO $dbh){
        $rows = self::getIdpList($dbh);
        $uS = Session::getInstance();

        $tbl = new HTMLTable();

        foreach ($rows as $row){
            $tbl->addBodyTr(
                $tbl->makeTd(
                    HTMLContainer::generateMarkup(
                        "a",
                        ($row["Logo_URL"] !="" ? '<img src="' . $row["Logo_URL"] . '" width="300px">' : "Login with " . $row["Name"]),
                        array("href"=>$uS->resourceURL . "auth/ws_SSO.php?cmd=login&idpId=" . $row["idIdp"])
                    )
                )
            );
        }

        return $tbl->generateMarkup();
    }

    public static function getIdpList(\PDO $dbh, $onlyActive = true){

        $query = "select * from `w_idp` " . ($onlyActive ? "where `Status` = 'a'": "");
        $stmt = $dbh->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

}
?>