<?php

namespace HHK\Document;

use HHK\DataTableServer\SSP;
use HHK\Notification\Mail\HHKMailer;
use HHK\sec\Labels;
use HHK\sec\Session;

/**
 * FormDocument.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of FormDocument
 *
 * @author Will
 */
class FormDocument {


    const formCat = "form";
    const JsonType = "json";

    protected $doc;
    protected $formTemplate;

    public function __construct() {

    }

    public static function listForms(\PDO $dbh, $status, $params, $totalsOnly = false){

        if($totalsOnly){
            //sync referral/resv statuses
            //$dbh->exec('CALL sync_referral_resv_status()');  // takes too long.

            $query = "SELECT g.Code AS 'idStatus', g.Description AS 'Status', g.Substitute AS 'icon', COUNT(d.idDocument) AS 'count' FROM gen_lookups g
			left join document d on g.Code = d.Status and `d`.`Type` = 'json' and `d`.`Category` = 'form'
		where g.Table_Name = 'Referral_Form_Status'
        group by g.Code;";

            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $totals = [];

            foreach($rows as $row){
                $totals[$row['idStatus']] = $row;
            }

            return array('totals'=>$totals);

        }else{
            $columns = array(
                array( 'db' => 'idDocument', 'dt' => 'idDocument'),
                array( 'db' => 'Timestamp',  'dt' => 'Date' ),
                array( 'db' => 'idDocument',    'dt' => 'Actions'),
                array( 'db' => 'patientFirstName', 'dt'=>'Patient First Name'),
                array( 'db' => 'patientLastName', 'dt'=> 'Patient Last Name'),
                array( 'db' => 'ExpectedCheckin', 'dt'=>'Expected Checkin'),
                array( 'db' => 'ExpectedCheckout', 'dt'=>'Expected Checkout'),
                array( 'db' => 'hospitalName', 'dt'=>'Hospital'),
                array( 'db' => 'status', 'dt'=>'Status'),
                array( 'db' => 'status ID', 'dt'=>'idStatus'),
                array( 'db' => 'idResv', 'dt'=>'idResv'),
                array( 'db' => 'resvStatus', 'dt'=>'resvStatus'),
                array( 'db' => 'resvStatusName', 'dt'=>'resvStatusName'),
                array( 'db' => 'FormTitle', 'dt'=>'FormTitle'),
                array( 'db' => 'enableReservation', 'dt'=>'enableReservation'),
            );
            if($status == 'inbox'){
                $whereClause = '`Status ID` IN ("n", "ip")';
            }else{
                $whereClause = '`Status ID` IN ("' . $status . '")';
            }

            return SSP::complex($params, $dbh, 'vform_listing', 'idDocument', $columns, null, $whereClause);
        }
    }


    /**
     *
     * @param \PDO $dbh
     * @param int $id
     * @return boolean
     */
    public function loadDocument(\PDO $dbh, $id){
        $this->doc = new Document(intval($id));
        $this->doc->loadDocument($dbh);
        if($this->doc->getType() ==  self::JsonType && $this->doc->getCategory() == self::formCat){
            return true;
        }else{
            $this->doc = new Document();
            return false;
        }
    }

    /**
     *
     * @param \PDO $dbh
     * @param string $json
     * @return array[]|string[]|string[]
     */
    public function saveNew(\PDO $dbh, array $fields, $templateId = 0){

        $this->formTemplate = new FormTemplate();
        $this->formTemplate->loadTemplate($dbh, $templateId);
        $templateName = $this->formTemplate->getTitle();
        $templateSettings = $this->formTemplate->getSettings();

        $abstractJson = json_encode(["enableReservation"=>$templateSettings['enableReservation']]);

        $validatedDoc = $this->validateFields($fields);

        if(count($validatedDoc['errors']) > 0){
            return array('errors'=>$validatedDoc['errors']);
        }

        $validatedFields = json_encode($validatedDoc['fields']);
        $sanitizedDoc = base64_encode(json_encode($validatedDoc['sanitizedDoc']));

        $this->doc = new Document();
        $this->doc->setType(self::JsonType);
        $this->doc->setMimeType("base64:text/json");
        $this->doc->setCategory(self::formCat);
        $this->doc->setTitle($templateName);
        $this->doc->setUserData($validatedFields);
        $this->doc->setAbstract($abstractJson);
        $this->doc->setDoc($sanitizedDoc);
        $this->doc->setStatus('n');
        $this->doc->setCreatedBy('Web');

        $this->doc->saveNew($dbh);

        if($this->doc->getIdDocument() > 0){
            //$this->sendPatientEmail();
            $this->sendNotifyEmail($dbh);
            return array("status"=>"success");
        }else{
            return array("status"=>"error");
        }
    }


    /**
     * Notify staff of new submission
     *
     * @return boolean
     */
    private function sendNotifyEmail(\PDO $dbh){
        $uS = Session::getInstance();
        $to = filter_var(trim($uS->referralFormEmail), FILTER_SANITIZE_EMAIL);

        try{
            if ($to !== FALSE && $to != '') {
                $formSettings = $this->formTemplate->getSettings();

                $content = "Hello,<br>" . PHP_EOL . "A new " . $this->formTemplate->getTitle() . " was submitted to " . $uS->siteName . ". <br><br><a href='" . $uS->resourceURL . "house/register.php' target='_blank'>Click here to log into HHK and take action.</a><br>" . PHP_EOL;

                $mail = new HHKMailer($dbh);

                $mail->From = ($uS->NoReplyAddr ? $uS->NoReplyAddr : "no_reply@nonprofitsoftwarecorp.org");
                $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                $mail->addAddress($to);

                $mail->isHTML(true);

                $mail->Subject = (isset($formSettings["notifySubject"]) && $formSettings["notifySubject"] != "" ? $formSettings["notifySubject"] : "New " . Labels::getString("register", "onlineReferralTitle", "Referral") . " submitted");
                $mail->msgHTML($content);

                if ($mail->send() === FALSE) {
                    return false;
                }else{
                    return true;
                }
            }
        }catch(\Exception $e){
            return false;
        }
        return false;
    }

/*     private function sendPatientEmail(){
        $templateSettings = $this->formTemplate->getSettings();
        $userData = json_decode($this->doc->getUserData(), true);
        $patientEmailAddress = (isset($userData['patient']['email']) ? $userData['patient']['email'] : '');
        $uS = Session::getInstance();

        if($this->doc->getIdDocument() > 0 && $templateSettings['emailPatient'] == true && $patientEmailAddress != '' && $templateSettings['notifySubject'] !='' && $templateSettings['notifyContent'] != ''){
            //send email

            $mail = new HHKMailer();

            $mail->From = $uS->NoReplyAddr;
            $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
            $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);

            $to = filter_var(trim($patientEmailAddress), FILTER_SANITIZE_EMAIL);
            if ($to !== FALSE && $to != '') {
                $mail->addAddress($to);
            }

            $mail->isHTML(true);

            $mail->Subject = $templateSettings['notifySubject'];
            $mail->msgHTML($templateSettings['notifyContent']);

            if ($mail->send() === FALSE) {
                return array('error'=>$mail->ErrorInfo);
            }else{
                return true;
            }

        }

    } */

    public function updateStatus(\PDO $dbh, $status){
        if($this->getStatus() == 'd' && $status == 'd'){
            return $this->doc->updateStatus($dbh, 'dd'); //fully delete
        }else{
            return $this->doc->updateStatus($dbh, $status);
        }
    }

    public function updateUserData(\PDO $dbh, $userData){
        return $this->doc->updateUserData($dbh, $userData);
    }

    public function getStatus() {
        return $this->doc->getStatus();
    }

    public function validateFields(array $fields){
        $response = ["fields"=>[], "errors"=>[]];

        $fieldData = [];

        foreach($fields as $key=>$field){
            if(isset($field->name) && isset($field->required)){ //filter out non input fields
                if($field->required && (!isset($field->userData[0]) || $field->userData[0] == '')){ //if field is required but user didn't fill field
                    $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' is required.'];
                    continue;
                }elseif($field->type == "date" && $field->userData[0] != ''){ //if date field and not empty
                    try{
                        $date = new \DateTime($field->userData[0]);
                    }catch(\Exception $e){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be a valid date.'];
                    }

                    $today = new \DateTime();
                    $dateLimit = new \DateTime('1900-01-01');
                    if(isset($field->validation) && $field->validation == 'lessThanToday' && $date->format('Y-m-d') > $today->format('Y-m-d')){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be in the past.'];
                    }else if(isset($field->validation) && $field->validation == 'greaterThanToday' && $date->format('Y-m-d') < $today->format('Y-m-d')){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be in the future.'];
                    }else if($date->format("Y-m-d") < $dateLimit->format("Y-m-d")){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be at least January 1, 1900'];
                    }

                    //save dates in correct format
                    $field->userData[0] = $date->format('Y-m-d');

                }elseif($field->type == "text" && $field->subtype == "email" && $field->userData[0] != ''){ //if email field and not empty
                    if(!filter_var($field->userData[0], FILTER_VALIDATE_EMAIL)){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be a valid Email address.'];
                    }
                }elseif($field->type == "text" && $field->subtype == "tel" && $field->userData[0] != ''){ //if phone field and not empty
                    if(!filter_var($field->userData[0], FILTER_SANITIZE_FULL_SPECIAL_CHARS) || !preg_match('/^([\(]{1}[0-9]{3}[\)]{1}[\.| |\-]{0,1}|^[0-9]{3}[\.|\-| ]?)?[0-9]{3}(\.|\-| )?[0-9]{4}$/', $field->userData[0])){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be formatted as: (###) ###-####'];
                    }
                }elseif(($field->type == "text" || $field->type == "textarea") && $field->userData[0] != ''){
                    $sanitized = filter_var($field->userData[0], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    $field->userData[0] = $sanitized;
                }

                if(isset($field->userData[0])){ //fill fields array
                    $fieldPathAr = [];
                    $this->assignArrayByPath($fieldPathAr, $field->name, $field->userData[0]);
                    $fieldData = array_merge_recursive($fieldData, $fieldPathAr);
                }

                //Check checkin/checkout dates
                if(isset($fieldData['checkindate']) && $fieldData['checkindate'] != "" && isset($fieldData['checkoutdate']) && $fieldData['checkoutdate'] != ""){
                    try{
                        $checkin = new \DateTime($fieldData['checkindate']);
                        $checkout = new \DateTime($fieldData['checkoutdate']);

                        if($checkin->format('Y-m-d') >= $checkout->format('Y-m-d')){
                            $response["errors"][] = ['field'=>'checkoutdate', 'error'=>'Checkout Date must be after Checkin Date'];
                        }
                    }catch(\Exception $e){

                    }
                }

            }

            //remove buttons
            if($field->type == "button"){
                unset($fields[$key]);
            }
        }
        $response['fields'] = $fieldData;
        $response['sanitizedDoc'] = array_values($fields);
        return $response;
    }

    public function getDoc(){
        if(str_starts_with($this->doc->getMimeType(), "base64:")){
            return base64_decode($this->doc->getDoc());
        }else{
            return $this->doc->getDoc();
        }
    }

    public function getUserData(){
        try{
            return json_decode($this->doc->getUserData(), true);
        }catch(\Exception $e){
            return NULL;
        }
    }

    public function linkNew(\PDO $dbh, $guestId = null, $psgId = null){
        return $this->doc->linkNew($dbh, $guestId, $psgId);
    }

    /**
     * Converts dot notation to array
     *
     * @param array $arr
     * @param string $path
     * @param mixed $value
     * @param string $separator
     */
    public function assignArrayByPath(&$arr, $path, $value, $separator='.') {
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            $arr = &$arr[$key];
        }

        $arr = $value;
    }

}
