<?php

namespace HHK\Document;

use HHK\DataTableServer\SSP;

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
    
    public function __construct() {
        
    }
    
    public static function listForms(\PDO $dbh, $status, $params, $totalsOnly = false){
        
        if($totalsOnly){
            $query = 'SELECT `Status`, count(*) as "count" from `vform_listing` group by `Status`';
            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array('totals'=>$rows);
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
                array( 'db' => 'status ID', 'dt'=>'idStatus')
            );
            if($status == 'inbox'){
                $whereClause = '`Status ID` IN ("n", "ip")';
            }else{
                $whereClause = '`Status ID` IN ("' . $status . '")';
            }
            
            return SSP::complex($params, $dbh, 'vform_listing', 'idDocument', $columns, null, $whereClause);
        }
    }
    
    
    
    public function loadDocument(\PDO $dbh, $id){
        $this->doc = new Document($id);
        $this->doc->loadDocument($dbh);
        if($this->doc->getType() ==  self::JsonType && $this->doc->getCategory() == self::formCat){
            return true;
        }else{
            $this->doc = new Document();
            return false;
        }
    }
    
    public function saveNew(\PDO $dbh, $json){
        
        $validatedDoc = $this->validateFields($json);
        
        if(count($validatedDoc['errors']) > 0){
            return array('errors'=>$validatedDoc['errors']);
        }
        
        $validatedFields = json_encode($validatedDoc['fields']);
        
        $this->doc = new Document();
        $this->doc->setType(self::JsonType);
        $this->doc->setCategory(self::formCat);
        $this->doc->setUserData($validatedFields);
        $this->doc->setDoc($json);
        $this->doc->setStatus('n');
        
        $this->doc->saveNew($dbh);
        
        if($this->doc->getIdDocument() > 0){
            return array("status"=>"success");
        }else{
            return array("status"=>"error");
        }
    }
    
    public function updateStatus(\PDO $dbh, $status){
        return $this->doc->updateStatus($dbh, $status);
    }
    
    public function validateFields($doc){
        $response = ["fields"=>[], "errors"=>[]];
        
        $json = json_decode($doc);
        
        foreach($json as $field){
            if(isset($field->name) && isset($field->required)){ //filter out non input fields
                if($field->required && $field->userData[0] == ''){ //if field is required but user didn't fill field
                    $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' is required.'];
                    continue;
                }elseif($field->type == "date" && $field->userData[0] != ''){ //if date field and not empty
                    try{
                        $date = new \DateTime($field->userData[0]);
                    }catch(\Exception $e){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be a valid date.'];
                    }
                }elseif($field->type == "text" && $field->subtype == "email" && $field->userData[0] != ''){ //if email field and not empty
                    if(!filter_var($field->userData[0], FILTER_VALIDATE_EMAIL)){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be a valid Email address.'];
                    }
                }elseif($field->type == "text" && $field->subtype == "tel" && $field->userData[0] != ''){ //if phone field and not empty
                    if(!filter_var($field->userData[0], FILTER_VALIDATE_INT)){
                        $response["errors"][] = ['field'=>$field->name, 'error'=>$field->label . ' must be a valid phone number.'];
                    }
                }
                
                if(isset($field->userData[0])){ //fill fields array
                    $response['fields'][$field->name] = $field->userData[0];
                }
            }
        }
        
        return $response;
    }
    
    public function getDoc(){
        return $this->doc->getDoc();
    }
    
    public function getUserData(){
        try{
            return json_decode($this->doc->getUserData());
        }catch(\Exception $e){
            return NULL;
        }
    }

}
