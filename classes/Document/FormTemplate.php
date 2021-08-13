<?php

namespace HHK\Document;

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
        if($cssValidation['valid'] == "false"){
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
        try{

            $encodedStyle = urlencode($styles);
            $url = 'https://jigsaw.w3.org/css-validator/validator?output=soap12&text=' . $encodedStyle;
            $resp = file_get_contents($url);
            $resp = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $resp);
            $respObj = new \SimpleXMLElement($resp);
            $isValid = $respObj->xpath('//envBody')[0]->mcssvalidationresponse->mvalidity;
            $warnings = array();
            $errors = array();

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
        $abstract = json_decode($this->doc->getAbstract());

        return [
            'formStyle'=>$this->getStyle(),
            'successTitle'=>$abstract->successTitle,
            'successContent'=>htmlspecialchars_decode($abstract->successContent, ENT_QUOTES),
            'enableRecaptcha'=>(isset($abstract->enableRecaptcha) ? $abstract->enableRecaptcha : false)
        ];
    }

}
