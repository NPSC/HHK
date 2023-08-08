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

    const MAX_GUESTS = 20;

    /**
     *
     * @var Document
     */
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

    public function saveNew(\PDO $dbh, $title, $doc, $style, $fontImport, $successTitle, $successContent, $enableRecaptcha, $enableReservation, $emailPatient, $notifySubject, $notifyContent, $initialGuests, $maxGuests, $username){

        $validationErrors = array();

        //validate CSS
        $cssValidation = $this->validateCSS($style);
        if($cssValidation['valid'] == "false"){
            $validationErrors['css'] = $cssValidation;
        }

        //validate font import
        $fontImportStr = '';
        if(is_array($fontImport)){
            $fontImportStr = "https://fonts.googleapis.com/css2?";
            foreach($fontImport as $font){
                $fontImportStr .= "family=" . $font . "&";
            }
            $fontImportStr .= "display=swap";
        }

        if(!$title){
            $validationErrors['title'] = "The title field is required.";
        }
        if(!$successTitle){
            $validationErrors['successTitle'] = "The success title field is required.";
        }
        if($emailPatient && $notifySubject == '' && $notifyContent == ''){
            $validationErrors['notify'] = "Email Subject and Email Content are both required when email notifications are enabled";
        }

        if($initialGuests > self::MAX_GUESTS){
            $validationErrors['initialGuests'] = "Initial Guests field cannot be greater than " . self::MAX_GUESTS;
        }

        if($maxGuests > self::MAX_GUESTS){
            $validationErrors['maxGuests'] = "Max Guests field cannot be greater than " . self::MAX_GUESTS;
        }

        if($initialGuests > $maxGuests){
            $validationErrors['initialmaxguests'] = "Initial guests cannot be greater than max guests";
        }

        $abstractJson = json_encode(['successTitle'=>$successTitle, 'successContent'=>$successContent, 'enableRecaptcha'=>$enableRecaptcha, 'enableReservation'=>$enableReservation, 'emailPatient'=>$emailPatient, 'notifySubject'=>$notifySubject, 'notifyContent'=>$notifyContent, 'initialGuests'=>$initialGuests, 'maxGuests'=>$maxGuests, 'fontImport'=>$fontImportStr]);

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

    public function save(\PDO $dbh, $title, $doc, $style, $fontImport, $successTitle, $successContent, $enableRecaptcha, $enableReservation, $emailPatient, $notifySubject, $notifyContent, $initialGuests, $maxGuests, $username){

        $validationErrors = array();

        //validate CSS
        $cssValidation = $this->validateCSS($style);
        if (isset($cssValidation['error'])) {
            $validationErrors['cssserver'] = $cssValidation['error'];
        }else if(isset($cssValidation['valid']) && $cssValidation['valid'] == "false"){
            $validationErrors['css'] = $cssValidation;
        }

        //validate font import
        $fontImportStr = '';
        if(is_array($fontImport)){
            $fontImportStr = "https://fonts.googleapis.com/css2?";
            foreach($fontImport as $font){
                $fontImportStr .= "family=" . $font . "&";
            }
            $fontImportStr .= "display=swap";
        }

        if(!$title){
            $validationErrors['title'] = "The title field is required.";
        }
        if(!$successTitle){
            $validationErrors['successTitle'] = "The success title field is required.";
        }
        if($emailPatient && $notifySubject == '' && $notifyContent == ''){
            $validationErrors['notify'] = "Email Subject and Email Content are both required when email notifications are enabled.";
        }

        if($initialGuests > 20){
            $validationErrors['initialGuests'] = "Initial Guests field cannot be greater than 20 people.";
        }

        if($maxGuests > 20){
            $validationErrors['maxGuests'] = "Max Guests field cannot be greater than 20 people.";
        }

        if($initialGuests > $maxGuests){
            $validationErrors['initialmaxguests'] = "Initial guests cannot be greater than max guests.";
        }

        if($this->doc->getIdDocument() > 0 && count($validationErrors) == 0){
            $abstractJson = json_encode(['successTitle'=>$successTitle, 'successContent'=>$successContent, 'enableRecaptcha'=>$enableRecaptcha, 'enableReservation'=>$enableReservation, 'emailPatient'=>$emailPatient, 'notifySubject'=>$notifySubject, 'notifyContent'=>$notifyContent, 'initialGuests'=>$initialGuests, 'maxGuests'=>$maxGuests, 'fontImport'=>$fontImportStr]);

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
            'successTitle'=>(isset($abstract->successTitle) ? $abstract->successTitle : ""),
            'successContent'=>htmlspecialchars_decode((isset($abstract->successContent) ? $abstract->successContent : ''), ENT_QUOTES),
            'enableRecaptcha'=>(isset($abstract->enableRecaptcha) && $uS->mode != "dev" ? $abstract->enableRecaptcha : false),
            'enableReservation'=>(isset($abstract->enableReservation) ? $abstract->enableReservation : true),
            'emailPatient'=>(isset($abstract->emailPatient) ? $abstract->emailPatient : false),
            'notifySubject'=>(isset($abstract->notifySubject) ? $abstract->notifySubject : ''),
            'notifyContent'=>(isset($abstract->notifyContent) ? htmlspecialchars_decode($abstract->notifyContent, ENT_QUOTES) : ''),
            'recaptchaScript'=>$recaptcha->getScriptTag(),
            'maxGuests'=>(isset($abstract->maxGuests) ? $abstract->maxGuests : 4),
            'initialGuests'=>(isset($abstract->initialGuests) ? $abstract->initialGuests : 1),
            'fontImport'=>(isset($abstract->fontImport) && strlen($abstract->fontImport) > 0 ? "@import url('" . $abstract->fontImport . "');" : '')
        ];
    }

    public static function getLookups(\PDO $dbh){
        $lookups = array();

        $demos = readGenLookupsPDO($dbh, 'Demographics', 'Order');

        foreach ($demos as $d) {
            $lookups[$d[0]] = readGenLookupsPDO($dbh, $d[0], 'Order');

            if($d[0] == 'Gender'){
                unset($lookups['Gender']['z']);
            }

            $lookups[$d[0]] = FormTemplate::rekeyLookups($lookups[$d[0]]);
        }

        $lookups['patientRelation'] = readGenLookupsPDO($dbh, 'Patient_Rel_Type', 'Description');
        unset($lookups['patientRelation']['slf']);
        FormTemplate::rekeyLookups($lookups['patientRelation']);
        $lookups['namePrefix'] = FormTemplate::rekeyLookups(readGenLookupsPDO($dbh, 'Name_Prefix', 'Description'));
        $lookups['nameSuffix'] = FormTemplate::rekeyLookups(readGenLookupsPDO($dbh, 'Name_Suffix', 'Description'));
        $lookups['diagnosis'] = FormTemplate::rekeyLookups(readGenLookupsPDO($dbh, 'Diagnosis', 'Description'));
        $lookups['location'] = FormTemplate::rekeyLookups(readGenLookupsPDO($dbh, 'Location', 'Description'));

        //backwards compatibility
        $lookups['genders'] = $lookups['Gender'];
        $lookups['ethnicities'] = $lookups['Ethnicity'];
        $lookups['patientRels'] = $lookups['patientRelation'];
        $lookups['mediaSources'] = $lookups['Media_Source'];
        $lookups['namePrefixes'] = $lookups['namePrefix'];
        $lookups['nameSuffixes'] = $lookups['nameSuffix'];
        $lookups['locations'] = $lookups['location'];

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

    /**
     * Rekey genlookups array to maintain correct ordering in JS
     *
     * @param array $lookups
     * @return array[]
     */
    private static function rekeyLookups(array $lookups){
        $newArray = array();
        foreach($lookups as $lookup){
            $newArray[] = $lookup;
        }
        return $newArray;
    }

}
