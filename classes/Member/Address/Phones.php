<?php

namespace HHK\Member\Address;

use HHK\AuditLog\NameLog;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Session;
use HHK\SysConst\PhonePurpose;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NamePhoneRS;

/**
 * Phones.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Class Phone - Phone Numbers
 *
 */
class Phones extends AbstractContactPoint {


    /**
     * Summary of loadRecords
     * @param \PDO $dbh
     * @return array<NamePhoneRS>
     */
    protected function loadRecords(\PDO $dbh) {

        $id = $this->name->get_idName();
        $phRS = new NamePhoneRS();
        $rsArray = array();

        if ($id > 0) {

            $phRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $phRS, array($phRS->idName));
            $phRS = null;

            foreach ($rows as $r) {

                $rsArray[$r['Phone_Code']] = new NamePhoneRS();
                EditRS::loadRow($r, $rsArray[$r['Phone_Code']]);

            }
        }

        // Fill out any missing purposes
        foreach ($this->codes as $p) {

            if (isset($rsArray[$p[0]]) === FALSE) {
                $rsArray[$p[0]] = new NamePhoneRS();
            }
        }
        return $rsArray;
    }

    /**
     * Summary of get_preferredCode
     * @return mixed
     */
    public function get_preferredCode() {
        return $this->name->get_preferredPhone();
    }

    /**
     * Summary of getTitle
     * @return string
     */
    public function getTitle() {
        return "Phone Number";
    }

    /**
     * Summary of setPreferredCode
     * @param mixed $code
     * @return void
     */
    public function setPreferredCode($code) {

        if ($code == "" || isset($this->codes[$code])) {
            $this->name->set_preferredPhone($code);
        }
    }

    /**
     * Summary of get_Data
     * @param mixed $code
     * @return array
     */
    public function get_Data($code = "") {

        // Cheap way to get around not putting a var into the signature.
        if ($code == "" && $this->get_preferredCode() != "") {
            $code = $this->get_preferredCode();
        }

        $data = array();
        $data["Preferred_Phone"] = $this->get_preferredCode();

        if ($code != "" && isset($this->rSs[$code])) {

            $data["Phone_Num"] = $this->rSs[$code]->Phone_Num->getStoredVal();
            $data["Phone_Extension"] = $this->rSs[$code]->Phone_Extension->getStoredVal();
            $data["Unformatted_Phone"] = $this->rSs[$code]->Phone_Search->getStoredVal();

        } else {

            $data["Phone_Num"] = "";
            $data["Phone_Extension"] = "";
            $data["Unformatted_Phone"] = "";

        }

        return $data;
    }

    /**
     * Summary of isRecordSetDefined
     * @param mixed $code
     * @return bool
     */
    public function isRecordSetDefined($code) {

        $adrRS = $this->get_recordSet($code);

        if (is_null($adrRS) || ($code !== PhonePurpose::NoPhone && $adrRS->Phone_Num->getStoredVal() == '')) {
            return FALSE;
        } else {
            return TRUE;
        }

    }

    /**
     * Summary of createMarkup
     * @param mixed $inputClass
     * @param mixed $idPrefix
     * @param mixed $room
     * @param mixed $roomPhoneCkd
     * @return string
     */
    public function createMarkup($inputClass = '', $idPrefix = "", $room = FALSE, $roomPhoneCkd = FALSE) {

        $table = new HTMLTable();

        foreach ($this->codes as $p) {

            $trContents = $this->createPhoneMarkup($p, $inputClass, $idPrefix);
            // Wrapup this TR
            $table->addBodyTr($trContents);
        }

//         if ($room) {
//             $table->addBodyTr($this->createHousePhoneMarkup('yr', $idPrefix, $roomPhoneCkd));
//         }

        return $table->generateMarkup();
    }

    /**
     * Summary of createPhoneMarkup
     * @param mixed $p
     * @param mixed $inputClass
     * @param mixed $idPrefix
     * @param mixed $showPrefCheckbox
     * @return string
     */
    public function createPhoneMarkup($p, $inputClass = '', $idPrefix = "", $showPrefCheckbox = TRUE) {
        $uS = Session::getInstance();
        // Preferred Radio button
        $tdContents = HTMLContainer::generateMarkup('label', $p[1], array('for'=>$idPrefix.'ph'.$p[0], 'style'=>'margin-right:6px;'));

        $prefAttr = array();
        $prefAttr['id'] = $idPrefix.'ph' . $p[0];
        $prefAttr['name'] = $idPrefix.'rbPhPref';
        $prefAttr['class'] = 'prefPhone ' . $inputClass;
        $prefAttr['title'] = 'Make this the Preferred phone number';
        $prefAttr['type'] = 'radio';

        if ($p[0] == $this->get_preferredCode()) {
            $prefAttr['checked'] = 'checked';
        } else {
            unset($prefAttr['checked']);
        }

        if ($showPrefCheckbox === FALSE) {
            $prefAttr['style'] = 'display:none;';
        }
        $tdContents .= HTMLInput::generateMarkup($p[0], $prefAttr);
        // Start the row
        $trContents = HTMLTable::MakeTd($tdContents, array('class'=>'tdlabel '.$p[2]));

        if ($p[0] == PhonePurpose::NoPhone) {
            $tdContents = '';
        } else {
            // PHone number
            $attr = array();
            $attr['id'] = $idPrefix.'txtPhone' . $p[0];
            $attr['name'] = $idPrefix.'txtPhone[' . $p[0] . ']';
            $attr['title'] = 'Enter a phone number';
            $attr['class'] = 'hhk-phoneInput ' . $inputClass;
            $attr['size'] = '16';

            $tdContents = HTMLInput::generateMarkup($this->rSs[$p[0]]->Phone_Num->getStoredVal(), $attr);

            if ($p[0] != PhonePurpose::Cell && $p[0] != PhonePurpose::Cell2) {
                // Extension
                $attr['id'] = $idPrefix.'txtExtn' . $p[0];
                $attr['name'] = $idPrefix.'txtExtn[' . $p[0] . ']';
                $attr['title'] = 'If needed, enter an Extension here';
                $attr['size'] = '5';

                if ($inputClass != '') {
                    $attr['class'] = $inputClass;
                } else {
                    unset($attr['class']);
                }
                $tdContents .=  'x'.HTMLInput::generateMarkup($this->rSs[$p[0]]->Phone_Extension->getStoredVal(), $attr);
            } else if ($uS->smsProvider && ($p[0] == PhonePurpose::Cell || $p[0] == PhonePurpose::Cell2)) {
                $smsOptions = [[" ",""],["opt_in", "Opt In"],["opt_out", "Opt Out"]];
                $smsOptInMkup = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($smsOptions, $this->rSs[$p[0]]->SMS_status->getStoredVal(), true, "SMS?"), ["name" => $idPrefix . 'selSMS[' . $p[0] . ']', 'id' => $idPrefix . 'selSMS' . $p[0], "class" => "ml-2 mr-1"]);
                
                $tdContents .= $smsOptInMkup;
            }
        }

        // Wrapup the this td
        $trContents .= HTMLTable::MakeTd($tdContents, array('class'=>$p[2]));
        return $trContents;
    }

    /**
     * Summary of createHousePhoneMarkup
     * @param mixed $prefCode
     * @param mixed $idPrefix
     * @param mixed $roomPhoneCkd
     * @return string
     */
    protected function createHousePhoneMarkup($prefCode, $idPrefix = "", $roomPhoneCkd = FALSE) {

        // Preferred Radio button
        $tdContents = HTMLContainer::generateMarkup('label', 'ROOM', array('for'=>$idPrefix.'ph'.$prefCode, 'style'=>'margin-right:6px;'));

        $prefAttr = array();
        $prefAttr['id'] = $idPrefix.'ph' . $prefCode;
        $prefAttr['name'] = $idPrefix.'rbPhPref';
        $prefAttr['class'] = 'prefPhone';
        $prefAttr['title'] = 'Make this the Preferred phone number';
        $prefAttr['type'] = 'radio';

        if ($roomPhoneCkd) {
            $prefAttr['checked'] = 'checked';
        } else {
            unset($prefAttr['checked']);
        }

        $tdContents .= HTMLInput::generateMarkup($prefCode, $prefAttr);
        // Start the row
        $trContents = HTMLTable::MakeTd($tdContents, array('class'=>'tdlabel i'));

        // Wrapup the this td
        $trContents .= HTMLTable::MakeTd('House Phone');
        return $trContents;
    }

    /**
     * Summary of savePost
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $user
     * @param mixed $idPrefix
     * @return string
     */
    public function savePost(\PDO $dbh, array $post, $user, $idPrefix = '') {

        $message = '';
        $id = $this->name->get_idName();

        if ($id < 1) {
            return "Bad member Id.  ";
        }

        foreach ($this->codes as $purpose) {

            $this->SavePhoneNumber($dbh, $post, $purpose, $user, $idPrefix);
        }

        $message .= $this->name->verifyPreferredAddress($dbh, $this, $user);

        return $message;
    }

    /**
     * Summary of SavePhoneNumber
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $purpose
     * @param mixed $user
     * @param mixed $idPrefix
     * @return string
     */
    public function SavePhoneNumber(\PDO $dbh, $post, $purpose, $user, $idPrefix = "") {

        $postedPhone = '';

        if (isset($post[$idPrefix.'txtPhone'][$purpose[0]])) {
            $postedPhone = $post[$idPrefix.'txtPhone'][$purpose[0]];
        }

        $id = $this->name->get_idName();
        // Set some convenience vars.
        $a = $this->rSs[$purpose[0]];
        $message = "";

        // Phone Number exists in DB?
        if ($a->idName->getStoredVal() > 0) {
            // Phone Number exists in the DB

            if ($postedPhone == '' && $purpose[0] !== PhonePurpose::NoPhone) {

                // Delete the Phone Number record
                if (EditRS::delete($dbh, $a, array($a->idName, $a->Phone_Code)) === FALSE) {
                    $message .= 'Problem with deleting this phone number.  ';
                } else {
                    NameLog::writeDelete($dbh, $a, $id, $user, $purpose[1]);
                    $this->rSs[$purpose[0]] = new NamePhoneRS();
                    $message .= 'Phone Number deleted.  ';
                }

            } else {

                // Update the Phone Number
                $this->loadPostData($a, $post, $purpose[0], $user, $idPrefix);
                $numRows = EditRS::update($dbh, $a, array($a->idName, $a->Phone_Code));
                if ($numRows > 0) {
                    NameLog::writeUpdate($dbh, $a, $id, $user, $purpose[1]);
                    $message .= 'Phone Number Updated.  ';
                }
            }

        } else {
            // Phone Number does not exist inthe DB.
            // Did the user fill in this Phone Number panel?
            if ($postedPhone != '' || $purpose[0] === PhonePurpose::NoPhone) {

                // Insert a new Phone Number
                $this->loadPostData($a, $post, $purpose[0], $user, $idPrefix);

                $a->idName->setNewVal($id);
                $a->Phone_Code->setNewVal($purpose[0]);
                EditRS::insert($dbh, $a);

                NameLog::writeInsert($dbh, $a, $id, $user, $purpose[1]);
                $message .= 'Phone Number Inserted.  ';

            }
        }

        // update the recordset
        EditRS::updateStoredVals($a);
        return $message;
    }

    /**
     * Summary of loadPostData
     * @param \HHK\Tables\Name\NamePhoneRS $a
     * @param mixed $p
     * @param mixed $typeCode
     * @param mixed $uname
     * @param mixed $idPrefix
     * @return void
     */
    private function loadPostData(NamePhoneRS $a, array $p, $typeCode, $uname, $idPrefix = '') {

        $ph = '';
        $extn = '';
        $smsOptIn = 0;

        if (isset($p[$idPrefix.'txtPhone'][$typeCode])) {
            $ph = trim(filter_var($p[$idPrefix.'txtPhone'][$typeCode], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        $a->Phone_Num->setNewVal($ph);

        if (isset($p[$idPrefix.'txtExtn'][$typeCode])) {
            $extn = trim(filter_var($p[$idPrefix.'txtExtn'][$typeCode], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        $a->Phone_Extension->setNewVal($extn);
        
        if (isset($p[$idPrefix.'selSMS'][$typeCode])) {
            $smsOptIn = filter_var($p[$idPrefix.'selSMS'][$typeCode], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $a->SMS_status->setNewVal($smsOptIn);


        // phone search - use only the numberals for efficient phone number search
        $ary = array('+', '-');
        $a->Phone_Search->setNewVal(str_replace($ary, '', filter_var($ph, FILTER_SANITIZE_NUMBER_INT)));
        $a->Status->setNewVal('a');
        $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $a->Updated_By->setNewVal($uname);

    }

}