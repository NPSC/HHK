<?php

namespace HHK\Document;

use HHK\House\Hospital\Hospital;
use HHK\sec\Session;
use HHK\sec\Recaptcha;
/**
 * FormTemplate.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of FormTemplate
 *
 * @author Will
 */
class FormTemplate {


    const TemplateCat = "tmpt";
    const JsonType = "json";

    protected $doc;

    public function __construct() {

    }

    public function loadTemplate(\PDO $dbh, $id){
        $this->doc = new Document($id);
        $this->doc->loadDocument($dbh);
        if($this->doc->getType() ==  self::JsonType && $this->doc->getCategory() == self::TemplateCat){
            return true;
        }else{
            $this->doc = new Document();
            return false;
        }
    }

    public static function listTemplates(\PDO $dbh){
        $query = 'SELECT idDocument, Title, Status from `document` where `Type` = "' . self::JsonType . '" AND `Category` = "' . self::TemplateCat . '" order by `Status`';
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    public function saveNew(\PDO $dbh, $title, $doc, $style, $successTitle, $successContent, $enableRecaptcha, $username){

        $validationErrors = array();

        //validate CSS
        $cssValidation = $this->validateCSS($style);
        if($cssValidation['valid'] == "false"){
            $validationErrors['css'] = $cssValidation;
        }

        if(!$title){
            $validationErrors['title'] = "The title field is required.";
        }
        if(!$successTitle){
            $validationErrors['successTitle'] = "The success title field is required.";
        }

        $abstractJson = json_encode(['successTitle'=>$successTitle, 'successContent'=>$successContent, 'enableRecaptcha'=>$enableRecaptcha]);

        if(count($validationErrors) == 0){

            $this->doc = new Document();
            $this->doc->setTitle($title);
            $this->doc->setType(self::JsonType);
            $this->doc->setCategory(self::TemplateCat);
            $this->doc->setDoc($doc);
            $this->doc->setStyle($style);
            $this->doc->setAbstract($abstractJson);
            $this->doc->setStatus('a');
            $this->doc->setCreatedBy($username);

            $this->doc->saveNew($dbh);

            if($this->doc->getIdDocument() > 0){
                return array('status'=>'success', 'msg'=>"Form saved successfully", 'doc'=>array('idDocument'=>$this->doc->getIdDocument(), 'title'=>$this->doc->getTitle()));
            }else{
                return array('status'=>'error', 'msg'=>'Unable to create new form', 'errors'=>array("Server error - Unable to create form"));
            }

        }else{
            return array('status'=>'error', 'msg'=>'Unable to create new form', 'errors'=>$validationErrors);
        }
    }

    public function save(\PDO $dbh, $title, $doc, $style, $successTitle, $successContent, $enableRecaptcha, $username){

        $validationErrors = array();

        //validate CSS
        $cssValidation = $this->validateCSS($style);
        if (isset($cssValidation['error'])) {
            $validationErrors['cssserver'] = $cssValidation['error'];
        }else if(isset($cssValidation['valid']) && $cssValidation['valid'] == "false"){
            $validationErrors['css'] = $cssValidation;
        }

        if(!$title){
            $validationErrors['title'] = "The title field is required.";
        }
        if(!$successTitle){
            $validationErrors['successTitle'] = "The success title field is required.";
        }


        if($this->doc->getIdDocument() > 0 && count($validationErrors) == 0){
            $abstractJson = json_encode(['successTitle'=>$successTitle, 'successContent'=>$successContent, 'enableRecaptcha'=>$enableRecaptcha]);

            $count = $this->doc->save($dbh, $title, $doc, $style, $abstractJson, $username);
            if($count == 1){
                return array('status'=>'success', 'msg'=>"Form updated successfully");
            }else{
                return array('status'=>'error', 'msg'=>'No changes detected, no updates made');
            }
        }else{
            return array('status'=>'error', 'msg'=>'The following errors have been found', 'errors'=>$validationErrors);
        }
    }

    public function validateCSS($styles){
        $uS = Session::getInstance();
        try{
            ini_set('default_socket_timeout', 10);
            $encodedStyle = urlencode($styles);
            $url = $uS->CssValidationService . $encodedStyle;
            $resp = file_get_contents($url);
            if($resp === FALSE){
                return array('error'=>"Could not validate CSS: CSS Validator service could not be reached.");
            }else{
                $resp = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $resp);
                $respObj = new \SimpleXMLElement($resp);
                $isValid = $respObj->xpath('//envBody')[0]->mcssvalidationresponse->mvalidity;
                $warnings = array();
                $errors = array();
                ini_restore('default_socket_timeout');

                //collect errors
                if($respObj->xpath('//envBody')[0]->mcssvalidationresponse->mresult->merrors->merrorcount > 0){
                    foreach($respObj->xpath('//envBody')[0]->mcssvalidationresponse->mresult->merrors->merrorlist->merror as $error){
                        $errors[] = ['line'=>$error->mline, 'message'=>$error->mmessage];
                    }
                }

                //collect warnings
                if($respObj->xpath('//envBody')[0]->mcssvalidationresponse->mresult->mwarnings->mwarningcount > 0){
                    foreach($respObj->xpath('//envBody')[0]->mcssvalidationresponse->mresult->mwarnings->mwarninglist->mwarning as $warning){
                        $warnings[] = ['line'=>$warning->mline, 'message'=>$warning->mmessage];
                    }
                }

                return array('valid'=>$isValid, 'errors'=>$errors, 'warnings'=>$warnings);
            }
        }catch (\Exception $e){
            return array('error'=>"Could not validate CSS: " .  $e->getMessage());
        }
    }

    public function getTemplate(){
        return $this->doc->getDoc();
    }

    public function getStyle() {
        return $this->doc->getStyle();
    }

    public function getTitle() {
        return $this->doc->getTitle();
    }

    public function getSettings(){
        $uS = Session::getInstance();
        $abstract = json_decode($this->doc->getAbstract());
        $recaptcha = new Recaptcha();

        return [
            'formStyle'=>$this->getStyle(),
            'successTitle'=>$abstract->successTitle,
            'successContent'=>htmlspecialchars_decode($abstract->successContent, ENT_QUOTES),
            'enableRecaptcha'=>(isset($abstract->enableRecaptcha) && $uS->mode != "dev" ? $abstract->enableRecaptcha : false),
            'recaptchaScript'=>$recaptcha->getScriptTag()
        ];
    }

    public static function getLookups(\PDO $dbh){
        $lookups = array();

        $lookups['genders'] = readGenLookupsPDO($dbh, 'gender', 'Order');
        unset($lookups['genders']['z']);
        $lookups['ethnicities'] = readGenLookupsPDO($dbh, 'ethnicity', 'Description');
        $lookups['patientRels'] = readGenLookupsPDO($dbh, 'Patient_Rel_Type', 'Order');
        unset($lookups['patientRels']['slf']);
        $lookups['mediaSources'] = readGenLookupsPDO($dbh, 'Media_Source','Order');
        $lookups['namePrefixes'] = readGenLookupsPDO($dbh, 'Name_Prefix', 'Order');
        $lookups['nameSuffixes'] = readGenLookupsPDO($dbh, 'Name_Suffix', 'Order');
        $lookups['diagnosis'] = readGenLookupsPDO($dbh, 'Diagnosis', 'Order');
        $lookups['locations'] = readGenLookupsPDO($dbh, 'Location', 'Order');
        $hospitals = Hospital::loadHospitals($dbh);
        $hospitalAr = array();
        foreach($hospitals as $hospital){
            if($hospital['Status'] == 'a' && $hospital['Type'] == 'h'){
                $hospitalAr[] = ['Code'=>$hospital['idHospital'], 'Description'=>$hospital['Title']];
            }
        }
        $lookups['hospitals'] = $hospitalAr;
        $stateList = array('', 'AB', 'AE', 'AL', 'AK', 'AR', 'AZ', 'BC', 'CA', 'CO', 'CT', 'CZ', 'DC', 'DE', 'FL', 'GA', 'GU', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS',
            'KY', 'LA', 'LB', 'MA', 'MB', 'MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NB', 'NC', 'ND', 'NE', 'NF', 'NH', 'NJ', 'NM', 'NS', 'NT', 'NV', 'NY', 'OH',
            'OK', 'ON', 'OR', 'PA', 'PE', 'PR', 'PQ', 'RI', 'SC', 'SD', 'SK', 'TN', 'TX', 'UT', 'VA', 'VI', 'VT', 'WA', 'WI', 'WV', 'WY');
        $formattedStates = array();
        foreach($stateList as $state){
            $formattedStates[$state] = ["Code"=>$state, "Description"=>$state];
        }
        $lookups['vehicleStates'] = $formattedStates;

        return $lookups;
    }

}
