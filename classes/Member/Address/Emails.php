<?php

namespace HHK\Member\Address;

use HHK\AuditLog\NameLog;
use HHK\Exception\RuntimeException;
use HHK\Exception\ValidationException;
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\SysConst\EmailPurpose;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameEmailRS;

/**
 * Emails.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  Email addresses
 */
class Emails extends AbstractContactPoint {

    /**
     * Summary of loadRecords
     * @param \PDO $dbh
     * @return array<NameEmailRS>
     */
    protected function loadRecords(\PDO $dbh) {

        $id = $this->name->get_idName();
        $emRS = new NameEmailRS();
        $rsArray = array();

        if ($id > 0) {

            $emRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $emRS, array($emRS->idName));
            $emRS = null;

            foreach ($rows as $r) {

                $rsArray[$r['Purpose']] = new NameEmailRS();
                EditRS::loadRow($r, $rsArray[$r['Purpose']]);

            }
        }

        // Fill out any missing purposes
        foreach ($this->codes as $p) {

            if (isset($rsArray[$p[0]]) === FALSE) {
                $rsArray[$p[0]] = new NameEmailRS();
            }
        }
        return $rsArray;
    }

    /**
     * Summary of get_preferredCode
     * @return mixed
     */
    public function get_preferredCode() {
        return $this->name->get_preferredEmail();
    }

    /**
     * Summary of getTitle
     * @return string
     */
    public function getTitle() {
        return "Email Address";
    }

    /**
     * Summary of setPreferredCode
     * @param mixed $code
     * @return void
     */
    public function setPreferredCode($code) {

        if ($code == "" || isset($this->codes[$code])) {
            $this->name->set_preferredEmail($code);
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
        $data["Preferred_Email"] = $this->get_preferredCode();

        if ($code != "" && isset($this->rSs[$code])) {

            $data["Email"] = $this->rSs[$code]->Email->getStoredVal();

        } else {

            $data["Email"] = "";

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

        if (is_null($adrRS) || ($code !== EmailPurpose::NoEmail && $adrRS->Email->getStoredVal() == '')) {
            return FALSE;
        } else {
            return TRUE;
        }

    }

    /**
     * Summary of createMarkup
     * @param mixed $inputClass
     * @param mixed $idPrefix
     * @return string
     */
    public function createMarkup($inputClass = '', $idPrefix = "") {

        $table = new HTMLTable();

        foreach ($this->codes as $p) {

            $trContents = $this->createEmailMarkup($p, $inputClass, $idPrefix);
            // Wrapup this TR
            $table->addBodyTr($trContents);

        }

        // Email warning message
//        $table->addBodyTr(
//                HTMLContainer::generateMarkup('td',
//                        HTMLContainer::generateMarkup('span', '', array('style'=>'color:red;', 'id'=>$idPrefix.'emailWarning'))));
//
        return $table->generateMarkup();
    }

    /**
     * Summary of createEmailMarkup
     * @param mixed $p
     * @param mixed $inputClass
     * @param mixed $idPrefix
     * @param mixed $showPrefCheckbox
     * @return string
     */
    public function createEmailMarkup($p, $inputClass = '', $idPrefix = "", $showPrefCheckbox = TRUE) {

        // Preferred Radio button
        $prefAttr = array();
        $prefAttr['id'] = $idPrefix.'em' . $p[0];
        $prefAttr['name'] = $idPrefix.'rbEmPref';
        $prefAttr['class'] = 'prefEmail ' . $inputClass;
        $prefAttr['title'] = 'Make this the Preferred email address';
        $prefAttr['type'] = 'radio';

        if ($p[0] == $this->get_preferredCode()) {
            $prefAttr['checked'] = 'checked';
        } else {
            unset($prefAttr['checked']);
        }

        $em = '';
        if ($showPrefCheckbox === FALSE) {
            $prefAttr['style'] = 'display:none;';
            $em = ' Email:';
        }
        // The row
        $trContents = HTMLContainer::generateMarkup('td',
                HTMLContainer::generateMarkup('label', $p[1].$em, array('for'=>$idPrefix.'em'.$p[0]))
                .' ' .HTMLInput::generateMarkup($p[0], $prefAttr)
                , array('class'=>"tdlabel " .$p[2]));

        if ($p[0] == EmailPurpose::NoEmail) {
            $tdContents = '';
        } else {
            // email address
            $attr = array();
            $attr['id'] = $idPrefix . 'txtEmail' . $p[0];
            $attr['name'] = $idPrefix . 'txtEmail[' . $p[0] . ']';
            $attr['title'] = 'Enter an email address';
            $attr['class'] = 'hhk-emailInput ' . $inputClass;
            $attr['size'] = '26';

            $tdContents = HTMLInput::generateMarkup($this->rSs[$p[0]]->Email->getStoredVal(), $attr);
        }

        //add input td
        $trContents .= HTMLContainer::generateMarkup('td', $tdContents, array('class' => $p[2]));
        
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
        $prefEmail = "";

        if ($id < 1) {
            return "Bad member Id.  ";
        }

        if (isset($post[$idPrefix.'txtEmail']) === FALSE) {
            return '';
        }

        foreach ($this->codes as $purpose) {

            $postedEmail = "";

            // Is the element even present?
            if (isset($post[$idPrefix . 'txtEmail'][$purpose[0]])) {
                $postedEmail = trim($post[$idPrefix . 'txtEmail'][$purpose[0]]);

                // Set some convenience vars.
                $a = $this->rSs[$purpose[0]];

                // Email Address exists in DB?
                if ($a->idName->getStoredVal() > 0) {
                    // Email Address exists in the DB

                    if ($postedEmail == '') {

                        // Delete the Email Address record
                        if (EditRS::delete($dbh, $a, array($a->idName, $a->Purpose)) === FALSE) {
                            $message .= 'Problem with deleting this Email Address.  ';
                        } else {
                            NameLog::writeDelete($dbh, $a, $id, $user, $purpose[1]);
                            $this->rSs[$purpose[0]] = new NameEmailRS();
                            $message .= 'Email Address deleted.  ';
                        }

                    } else {

                        // Update the Email Address
                        $this->loadPostData($a, $post, $purpose[0], $user, $idPrefix);
                        $numRows = EditRS::update($dbh, $a, array($a->idName, $a->Purpose));
                        if ($numRows > 0) {
                            NameLog::writeUpdate($dbh, $a, $id, $user, $purpose[1]);
                            $message .= 'Email Address Updated.  ';
                        }
                    }

                } else {
                    // Email Address does not exist inthe DB.
                    // Did the user fill in this Email Address panel?
                    if ($postedEmail != '') {

                        // Insert a new Email Address
                        $this->loadPostData($a, $post, $purpose[0], $user, $idPrefix);

                        $a->idName->setNewVal($id);
                        $a->Purpose->setNewVal($purpose[0]);
                        EditRS::insert($dbh, $a);

                        NameLog::writeInsert($dbh, $a, $id, $user, $purpose[1]);
                        $message .= 'Email Address Inserted.  ';

                    }
                }

                // update the recordset
                EditRS::updateStoredVals($a);
            }
        }

        $message .= $this->name->verifyPreferredAddress($dbh, $this, $user);

        return $message;
    }


    /**
     * Summary of loadPostData
     * @param \HHK\Tables\Name\NameEmailRS $a
     * @param mixed $p
     * @param mixed $typeCode
     * @param mixed $uname
     * @param mixed $idPrefix
     * @return void
     */
    private function loadPostData(NameEmailRS $a, array $p, $typeCode, $uname, $idPrefix = "") {
        //if ($typeCode !== EmailPurpose::NoEmail) {
            //$email = filter_var(filter_var($p[$idPrefix . 'txtEmail'][$typeCode], FILTER_SANITIZE_EMAIL), FILTER_VALIDATE_EMAIL);
            //if($email == false){
            //    throw new ValidationException("Email field must be a valid Email address");
            //}
        //}else{
        //    $email = "";
        //}

        $email = "";
        if (isset($p[$idPrefix . 'txtEmail'][$typeCode])) {
            $email = filter_var($p[$idPrefix . 'txtEmail'][$typeCode], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $a->Email->setNewVal(strtolower(trim($email)));
        $a->Status->setNewVal("a");
        $a->Updated_By->setNewVal($uname);
        $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

    }

}