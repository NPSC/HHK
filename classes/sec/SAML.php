<?php
namespace HHK\sec;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;
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
    
    public function __construct(){
        $this->auth = new Auth($this->getSettings());
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
        
        if (isset($uS->AuthNRequestID)) {
            $requestID = $uS->AuthNRequestID;
        } else {
            $requestID = null;
        }
        
        $this->auth->processResponse($requestID);
        unset($uS->AuthNRequestID);
        
        $errors = $this->auth->getErrors();
        
        if (!empty($errors)) {
            return array('error'=>implode(', ', $errors));
        }
        
        if (!$this->auth->isAuthenticated()) {
            return array('error'=>'Not authenticated');
        }else{
            return array('success'=>'authenticated', 'samlUserdata'=>$this->auth->getAttributes());
        }
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
    
    public static function getSettings(){
        
        $uS = Session::getInstance();
        
        $settings = array();
        $securityComponent = new SecurityComponent();
        $wsURL = $securityComponent->getRootURL() . 'ws_SSO.php';
        
        $settings = [
            'baseurl' => $securityComponent->getRootURL(),
            'sp' => [
                'entityId' => $securityComponent->getRootURL(),
                'assertionConsumerService' => [
                    'url' => $wsURL . '?cmd=acs',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'x509cert' => '',
                'privateKey' => '',
            ],
            'idp' => [
                'entityId' => $uS->IdP_Entity_Id,
                'singleSignOnService' => [
                    'url' => $uS->SSO_URL,
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => $uS->IdP_Cert,
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
}
?>