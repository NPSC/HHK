<?php

namespace HHK\Member\Address;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameAddressRS;
use HHK\AuditLog\NameLog;
use HHK\Exception\{InvalidArgumentException, RuntimeException};
use HHK\Tables\TableRSInterface;
use HHK\House\Distance\DistanceFactory;

/**
 * Address.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017, 2018-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  Street addresses
 */
class Address extends AbstractContactPoint{

    /**
     *
     * @var CleanAddress
     */
    public $cleanAddress;

    protected function loadRecords(\PDO $dbh) {

        $adRS = new NameAddressRS();
        $id = $this->name->get_idName();
        $rsArray = array();

        if ($id > 0) {

            $adRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $adRS, array($adRS->idName));

            foreach ($rows as $r) {

                $rsArray[$r['Purpose']] = new NameAddressRS();
                EditRS::loadRow($r, $rsArray[$r['Purpose']]);

            }
        }

        // Fill out any missing purposes
        foreach ($this->codes as $p) {

            if (isset($rsArray[$p[0]]) === FALSE) {
                $rsArray[$p[0]] = new NameAddressRS();
            }
        }
        return $rsArray;
    }


    /**
     * Summary of get_preferredCode
     * @return mixed
     */
    public function get_preferredCode() {
        return $this->name->get_preferredMailAddr();
    }

    /**
     * Summary of getTitle
     * @return string
     */
    public function getTitle() {
        return "Street Address";
    }

    /**
     * Summary of setPreferredCode
     * @param mixed $code
     */
    public function setPreferredCode($code) {

        if ($code == "" || isset($this->codes[$code])) {
            $this->name->set_preferredMailAddr($code);
        }
    }

    /**
     * Summary of getSet_Incomplete
     * @param mixed $code
     * @return bool
     */
    public function getSet_Incomplete($code) {

        if ($this->rSs[$code]->Set_Incomplete->getStoredVal() == 1) {
            return TRUE;
        }

        return FALSE;
    }


    /**
     * Returns an array of column-name => field-value from the stored values of the iTable pointed at by code.
     * @param string $code
     * @return array column-name => field-value
     */
    public function get_Data($code = "") {

        // Cheap way to get around not putting a var into the signature.
        if ($code == "" && $this->get_preferredCode() != "") {
            $code = $this->get_preferredCode();
        }

        $data = array();
        $data['Preferred_Address'] = $this->get_preferredCode();

        if ($code != '' && isset($this->rSs[$code])) {

            $data['Address_1'] = $this->rSs[$code]->Address_1->getStoredVal();
            $data['Address_2'] = $this->rSs[$code]->Address_2->getStoredVal();
            $data['City'] = $this->rSs[$code]->City->getStoredVal();
            $data['State_Province'] = $this->rSs[$code]->State_Province->getStoredVal();
            $data['Country_Code'] = $this->rSs[$code]->Country_Code->getStoredVal() == '' ? 'US' : $this->rSs[$code]->Country_Code->getStoredVal();
            $data['Postal_Code'] = $this->rSs[$code]->Postal_Code->getStoredVal();
            $data['County'] = $this->rSs[$code]->County->getStoredVal();
            $data['Set_Incomplete'] = $this->rSs[$code]->Set_Incomplete->getStoredVal();

        } else {

            $data['Address_1'] = '';
            $data['Address_2'] = '';
            $data['City'] = '';
            $data['State_Province'] = '';
            $data['Country_Code'] = 'US';
            $data['Postal_Code'] = '';
            $data['County'] = '';
            $data['Set_Incomplete'] = '';
        }

        return $data;
    }

    /**
     * Returns true if the recordset has the minimum actual user data in it.
     *
     * @param string $code
     * @return boolean
     */
    public function isRecordSetDefined($code) {

        $adrRS = $this->get_recordSet($code);

        if (is_null($adrRS) || ($adrRS->Address_1->getStoredVal() == '' && $adrRS->City->getStoredVal() == '')) {
            return FALSE;
        } else {
            return TRUE;
        }

    }

    /**
     * Creates an address panel (table) for a given address type.
     *
     * @param string $addrIndex
     * @param NameAddressRS $adrRow
     * @param bool $showBadAddrCkBox
     * @return string
     */
    public function createPanelMarkup($addrIndex, NameAddressRS $adrRow, $showBadAddrCkBox = FALSE, $idPrefix = "", $class = "", $includeCounty = FALSE, $lastUpdated = '') {

        $badAddrClass = "";

        $badChkd = array (
            'type'=>'checkbox',
            'name'=>$idPrefix.'adr[' . $addrIndex . '][bad]',
            'id'=>$idPrefix.'adrbad' . $addrIndex,
            'title'=>'Check if the address is bad',
            'style'=>'margin-top: 5px;',
            'data-pref'=>$idPrefix,
        );

         if ($adrRow->Bad_Address->getStoredVal() == "true") {
             $badChkd['checked'] = 'checked';
             $badAddrClass = 'hhk-badAddress';
         }

         if ($showBadAddrCkBox) {
            $badAddrMarkup = HTMLContainer::generateMarkup('label', 'Bad ', array('for'=>$idPrefix.'adrbad' . $addrIndex, 'style'=>'margin-left: 15px; margin-top: 5px;', 'title'=>'Check if the address is bad'))
                 . HTMLInput::generateMarkup('', $badChkd);
         } else {
             $badAddrMarkup = "";
         }


        // Address table
        $table = new HTMLTable();

        // address 1
        $attr = array(
            'type'=>'text',
            'size'=>'27',
            'title'=>'Street Address',
            'id'=>$idPrefix.'adraddress1' . $addrIndex,
            'name'=>$idPrefix.'adr[' . $addrIndex . '][address1]',
            'class'=>$class,
            'data-pref'=>$idPrefix,
        	'autocomplete'=>"off",
            );

        $table->addBodyTr(HTMLTable::makeTd('Street', array('class'=>'tdlabel', 'title'=>'Street Address'))
            . HTMLTable::makeTd(
                    HTMLInput::generateMarkup($adrRow->Address_1->getStoredVal(), $attr)));

        // Address 2
        $attr['id'] = $idPrefix.'adraddress2' . $addrIndex;
        $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][address2]';
        $attr['title'] = 'Apt, Suite, Mail Stop';
        $attr['class'] = $class . ' hhk-MissingOk';

        $table->addBodyTr(HTMLTable::makeTd('', array('class'=>'tdlabel', 'title'=>'Apt, Suite, Mail Stop'))
            . HTMLTable::makeTd(
                    HTMLInput::generateMarkup($adrRow->Address_2->getStoredVal(), $attr)));

        // & Zip
        $zipAttr = array(
            'id'=>$idPrefix.'adrzip'.$addrIndex,
            'name'=>$idPrefix.'adr['.$addrIndex.'][zip]',
            'type'=>'text',
            'size'=>'10',
            'class'=>'ckzip hhk-zipsearch ' . $class,
            'title'=>'Enter Postal Code',
            'data-hhkprefix'=>$idPrefix,
            'data-hhkindex'=>$addrIndex,
            'data-pref'=>$idPrefix
            );

        // Zip
        $table->addBodyTr(HTMLTable::makeTd('Zip', array('class'=>'tdlabel', 'title'=>'Enter Zip Code'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup($adrRow->Postal_Code->getStoredVal(), $zipAttr)));


        // City
        $attr['id'] = $idPrefix.'adrcity' . $addrIndex;
        $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][city]';
        $attr['title'] = 'City Name';
        $attr['class']= $class;


        $table->addBodyTr(HTMLTable::makeTd('City', array('class'=>'tdlabel', 'title'=>'City Name'))
            . HTMLTable::makeTd(
                    HTMLInput::generateMarkup($adrRow->City->getStoredVal(), $attr)));

        // County
        if ($includeCounty) {
            $attr['id'] = $idPrefix.'adrcounty' . $addrIndex;
            $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][county]';
            $attr['title'] = 'County Name';
            $attr['class']= $class . ' hhk-MissingOk';

            $table->addBodyTr(HTMLTable::makeTd('County', array('class'=>'tdlabel', 'title'=>'County Name'))
                    . HTMLTable::makeTd(
                            HTMLInput::generateMarkup($adrRow->County->getStoredVal(), $attr)));

        }

        // State
        $stAttr['id'] = $idPrefix.'adrstate' . $addrIndex;
        $stAttr['name'] = $idPrefix.'adr[' . $addrIndex . '][state]';
        $stAttr['title'] = 'Select State or Province';
        $stAttr['style'] = 'margin-right:8px;';
        $stAttr["class"] = $class . " bfh-states";
        $stAttr['data-country'] = $idPrefix.'adrcountry' . $addrIndex;
        $stAttr['data-state'] = $adrRow->State_Province->getStoredVal();
        $stAttr['data-pref'] = $idPrefix;

        $table->addBodyTr(HTMLTable::makeTd('State', array('class'=>'tdlabel', 'title'=>'Select State or Province'))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup('', $stAttr)));


        // Country
        $coAttr['id'] = $idPrefix.'adrcountry' . $addrIndex;
        $coAttr['name'] = $idPrefix.'adr[' . $addrIndex . '][country]';
        $coAttr['title'] = 'Select Country';
        $coAttr['class'] = $class . ' input-medium bfh-countries';
        $coAttr['data-country'] = ($adrRow->Country_Code->getStoredVal() == '' ? 'US' : $adrRow->Country_Code->getStoredVal());
        $coAttr['data-prefix'] = $idPrefix;

        $table->addBodyTr(HTMLTable::makeTd('Country', array('class'=>'tdlabel'))
            . HTMLTable::makeTd(HTMLSelector::generateMarkup('', $coAttr)
            . $badAddrMarkup
            ));

        $distanceCalculator = DistanceFactory::make();
        $distance = $adrRow->Meters_From_House->getStoredVal();
    
        if($distance > 0){
            $table->addBodyTr(HTMLTable::makeTd(Labels::getString("Referral", "drivingdistancePrompt", "Distance"), array('class'=>'tdlabel', 'title'=>Labels::getString("Referral", "drivingdistancePrompt", "Distance")))
                . HTMLTable::makeTd("<b>" . $distanceCalculator->meters2miles($distance) . "</b> miles from The House"));
        }

        return $table->generateMarkup(array('class'=>$badAddrClass)) . $lastUpdated;
    }


    /**
     * Builds the tab control containing each address type.
     *
     * @param string $inputClass
     * @param bool $showBadAddrCkBox
     * @return string
     */
    public function createMarkup($inputClass = '', $showBadAddrCkBox = TRUE, $includeCounty = FALSE) {

        $panels = '';
        $tabs = "";
        $tabIndex = 0;
        $tabCounter = 0;

        // Build tab panels
        $orderd = array_keys($this->codes);

        foreach ($orderd as $addrIndex) {

            $addrLastUpdated = '';
            if ($this->getLastUpdated($addrIndex) != '') {
                $addrLastUpdated = $this->name->getContactLastUpdatedMU(new \DateTime($this->getLastUpdated($addrIndex)), 'This Address');
            }

            $table = $this->createPanelMarkup($addrIndex, $this->rSs[$addrIndex], $showBadAddrCkBox, '', '', $includeCounty, $addrLastUpdated);
//
            $panels .= HTMLContainer::generateMarkup('div', $table, array('id'=>'addrtb' . $addrIndex));

            $inptAttr = array();
            $inptAttr['type'] = 'radio';
            $inptAttr['name'] = 'rbPrefMail';
            $inptAttr['id'] = 'rbPrefMail'.$this->codes[$addrIndex][0];
            $inptAttr['class'] = 'addrPrefs ' . $inputClass;
            $inptAttr['title'] = 'Make this the Preferred Address';

            if ($this->get_preferredCode() == $addrIndex) {
                $inptAttr['checked'] = 'checked';
                $tabIndex = $tabCounter;
            } else {
                unset($inptAttr['checked']);
            }

            $tabs .= HTMLContainer::generateMarkup('li',
                    HTMLContainer::generateMarkup('a', $this->codes[$addrIndex][1], array('href'=>'#addrtb'.$this->codes[$addrIndex][0]))
                    . HTMLInput::generateMarkup($this->codes[$addrIndex][0], $inptAttr)
                    , array('class'=>$this->codes[$addrIndex][2]));

            $tabCounter++;
        }

        // wrap tabs in a UL
        $ul = HTMLContainer::generateMarkup('ul', $tabs, array('data-actIdx'=>$tabIndex));

        return $ul . $panels;
    }

    /**
     *
     * @param \PDO $dbh
     * @param array $post
     * @param string $user
     * @return string
     * @throws InvalidArgumentException
     */
    public function savePost(\PDO $dbh, array $post, $user, $idPrefix = '') {

        $message = '';
        $id = $this->name->get_idName();

        if ($id < 1) {
            return "Bad member Id.  ";
        }

        $this->cleanAddress = new CleanAddress($dbh);

        foreach ($this->codes as $purpose) {

            $message .= $this->savePanel($dbh, $purpose, $post, $user, $idPrefix);
        }

        $message .= $this->name->verifyPreferredAddress($dbh, $this, $user);

        return $message;
    }


    /**
     * Summary of savePanel
     * @param \PDO $dbh
     * @param mixed $purpose
     * @param mixed $post
     * @param mixed $user
     * @param mixed $idPrefix
     * @param mixed $incomplete
     * @return string
     */
    public function savePanel(\PDO $dbh, $purpose, $post, $user, $idPrefix = '', $incomplete = FALSE) {

        $indx = $idPrefix.'adr';
        if (isset($post[$indx][$purpose[0]]) === FALSE) {
            return '';
        }

        return $this->saveAddress($dbh, $post[$idPrefix.'adr'][$purpose[0]], $purpose, $incomplete, $user);
    }


    /**
     * Summary of saveAddress
     * @param \PDO $dbh
     * @param mixed $p
     * @param mixed $purpose
     * @param mixed $incomplete
     * @param mixed $user
     * @return string
     */
    public function saveAddress(\PDO $dbh, array $p, $purpose, $incomplete, $user) {

        $message = '';
        $uS = Session::getInstance();
        // Set some convenience vars.
        $a = $this->rSs[$purpose[0]];
        $id = $this->name->get_idName();

        // Incomplete checkbox
        if ($incomplete) {
            $a->Set_Incomplete->setNewVal(1);
        }

        // Address exists in DB?
        if ($a->idName_Address->getStoredVal() > 0) {
            // Address exists in the DB

            if (isset($p["trash"])) {

                // Delete the address record
                if (EditRS::delete($dbh, $a, array($a->idName_Address)) === FALSE) {
                    $message .= 'Problem with deleting this address.  ';
                } else {
                    NameLog::writeDelete($dbh, $a, $id, $user, $purpose[1]);
                    $a = new NameAddressRS();
                    $this->rSs[$purpose[0]] = $a;
                    $message .= 'Address deleted.  ';
                }

            } else {

                // Update the address
                $adrComplete = $this->loadPostData($a, $p);

                if ($adrComplete === TRUE) {
                    $a->Set_Incomplete->setNewVal(0);

                    if(EditRS::isChanged($a) || !$a->Meters_From_House->getStoredVal() > 0){ //if address has changed and is complete, or distance hasn't been calculated
                        //calculate distance
                        $distanceCalculator = DistanceFactory::make();
                        $distance = $distanceCalculator->getDistance($dbh, $p, $uS->houseAddr, 'meters');
                        $a->Meters_From_House->setNewVal($distance);
                    }
                }else{
                    $a->Meters_From_House->setNewVal(null);
                }

                $numRows = EditRS::update($dbh, $a, array($a->idName_Address));
                if ($numRows > 0) {
                    NameLog::writeUpdate($dbh, $a, $id, $user, $purpose[1]);
                    $message .= 'Address Updated.  ';
                }
            }

        } else {
            // Address does not exist inthe DB.
            // Did the user fill in this address panel?
            $adrComplete = $this->loadPostData($a, $p);

            if($adrComplete){
                //calculate distance
                $distanceCalculator = DistanceFactory::make();
                $distance = $distanceCalculator->getDistance($dbh, ['address1'=>$a->Address_1->getNewVal(), 'city'=>$a->City->getNewVal(), 'state'=>$a->State_Province->getNewVal(), 'zip'=>$a->Postal_Code->getNewVal()], $uS->houseAddr, 'meters');
                $a->Meters_From_House->setNewVal($distance);
            }

            if ($incomplete || $adrComplete) {

                // Insert a new address
                $a->idName->setNewVal($id);
                $a->Purpose->setNewVal($purpose[0]);
                $naId = EditRS::insert($dbh, $a);

                if ($naId > 0) {
                    NameLog::writeInsert($dbh, $a, $id, $user, $purpose[1]);
                    $message .= 'Address Inserted.  ';
                }

            }
        }

        // update the recordset
        EditRS::updateStoredVals($a);
        return $message;

    }

    /**
     *
     * @param NameAddressRS $a
     * @param array $p
     * @throws RuntimeException
     */
    protected function loadPostData(NameAddressRS $a, array $p) {

        $addrComplete = TRUE;

        if ($this->cleanAddress instanceof CleanAddress === FALSE) {
            throw new RuntimeException("CleanAddress object is missing.  ");
        }

        // Clean the street address
        if (isset($p["address1"])) {

            $addrs = $this->cleanAddress->cleanAddr(trim(filter_var($p["address1"], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));

            $street2 = '';
            if (isset($p['address2'])) {
                $street2 = trim(filter_var($p['address2'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            }

            if ($street2 != "") {
                $street2 = $this->cleanAddress->convertSecondary($street2);
            } else {
                $street2 = $addrs[1];
            }

            $a->Address_1->setNewVal($addrs[0]);
            $a->Address_2->setNewVal($street2);

            if ($addrs[0] == '') {
                $addrComplete = FALSE;
            }
        }

        // Country
        $country = '';
        if (isset($p['country'])) {
            $country = trim(filter_var($p['country'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if ($country == '' || strtoupper($country) == 'USA') {
            $country = "US";
        }

        $a->Country_Code->setNewVal($country);

        if (isset($p['county'])) {
            $a->County->setNewVal(trim(filter_var($p['county'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
        }

        // zip code, city and state
        if (isset($p['city'])) {
            $a->City->setNewVal(trim(filter_var($p['city'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));

            if ($p['city'] == '') {
                $addrComplete = FALSE;
            }
        }
        if (isset($p['state'])) {
            $a->State_Province->setNewVal(trim(filter_var($p['state'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));

            if ($p['state'] == '') {
                $addrComplete = FALSE;
            }
        }
        if (isset($p['zip'])) {
            $a->Postal_Code->setNewVal(strtoupper(trim(filter_var($p['zip'], FILTER_SANITIZE_FULL_SPECIAL_CHARS))));

            if ($p['zip'] == '') {
                $addrComplete = FALSE;
            }
        }

        $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

        if (isset($p["bad"])) {
            $a->Bad_Address->setNewVal('true');
        } else {
            $a->Bad_Address->setNewVal('');
        }

        return $addrComplete;

    }

    /**
     * Summary of checkZip
     * @param \PDO $dbh
     * @param NameAddressRS $a
     * @param mixed $p
     * @return mixed
     */
    public function checkZip(\PDO $dbh, NameAddressRS $a, array $p) {
        // zip code, city and state
        $zip = strtoupper(trim(filter_var($p['zip'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
        $city = trim(filter_var($p['city'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $state = trim(filter_var($p['state'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $country = $a->Country_Code->getStoredVal();

        if (($country == 'US' || $country == '') && $a->Postal_Code->getStoredVal() != $zip) {
            // check zip code
            $stmt = $dbh->prepare("select City, Acceptable_Cities, State from postal_Codes where Zip_Code = :zip");
            $stmt->execute(array(':zip'=>$zip));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 0) {
                return "Zip Code does not exist.  ";
            }

            if (strtolower($state) != strtolower($rows[0]['State'])) {
                return "State does not match, did you mean ". $rows[0]['State'] . "?";
            }

            if (strtolower($city) != strtolower($rows[0]['City'])) {

                $foundIt = FALSE;

                // Check alternate city names
                if ($rows[0]['Acceptable_Cities'] != '') {
                    $cities = explode(",", $rows[0]['Acceptable_Cities']);

                    foreach ($cities as $c) {
                        if (strtolower($city) == strtolower(trim($c))) {
                            $foundIt = TRUE;
                        }
                    }

                }

                if ($foundIt === FALSE) {
                    $msg =  "City does not match.  ";
                    if ($rows[0]['Acceptable_Cities'] != '') {
                        $msg .= "Here are your choices: " . $rows[0]['City'] . ", " . $rows[0]['Acceptable_Cities'];
                    } else {
                        $msg .= "Did you mean " . $rows[0]['City'] . "?";
                    }
                    return $msg;
                }
            }

        }

    }

}
?>