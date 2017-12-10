<?php
/**
 * Addresses.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Addresses
 * @author Eric
 */
class Addresses {


    /** @var Email/ContactPoint */
    public $email;

    /** @var Phones/ContactPoint */
    public $phone;

    /**
     *
     * @param Phones $phone
     * @param Emails $email
     * @return string
     */
    public static function getPreferredPanel(Phones $phone, Emails $email, $useHousePhone = FALSE) {

        $table = new HTMLTable();

        $table->addBodyTr(
                HTMLTable::makeTh("Preferred Phone"));

        // Make phone number
        $phoneMkup = '';
        if ($useHousePhone) {
            $phoneMkup = "House Phone";
        } else {
            $phData = $phone->get_Data();
            $phoneMkup = $phData["Phone_Num"] . ($phData["Phone_Extension"] == "" ? "" : " x".$phData["Phone_Extension"]);
        }


        $table->addBodyTr(
                HTMLTable::makeTd($phoneMkup)
                );

        $table->addBodyTr(HTMLTable::makeTd("&nbsp;", array('style'=>'border-width:0;')));

        $table->addBodyTr(HTMLTable::makeTh("Preferred Email"));

        $emData = $email->get_Data();
        $table->addBodyTr(
                HTMLTable::makeTd($emData["Email"])
                );

        return $table->generateMarkup();
    }

}


/**
 *  ContactPoint
 * Base class for a members' street address, phone and email.
 */
abstract class ContactPoint {

    /** @var array Holds the address type codes, ex. 'Home' or 'Work' */
    protected $codes = array();

    /** @var array Holds an iTable for each address type in $codes */
    protected $rSs = array();

    /** @var Member pointer to the Member object */
    protected $name;


    /**
     *
     * @param PDO $dbh
     * @param Member $name
     * @param array $codes
     */
    function __construct(\PDO $dbh, Member $name, array $codes) {

        $this->name = $name;

        // Filter codes for member designation
        foreach ($codes as $c) {
            if ($c[Member::SUBT] == $name->getMemberDesignation() || $c[Member::SUBT] == ""|| $c[Member::SUBT] == "hhk-home") {
                $this->codes[$c[Member::CODE]] = $c;
            }
        }

        $this->rSs =  $this->loadRecords($dbh);

    }


    public abstract function setPreferredCode($code);

    public abstract function get_preferredCode();

    public abstract function getTitle();

    public function getLastUpdated($code = '') {

        if ($code == '') {
            $code = $this->get_preferredCode();
        }

        $rs = $this->get_recordSet($code);

        if (is_null($rs)) {
            return '';
        }

        return $rs->Last_Updated->getStoredVal();

    }


    /**
     * The extending objects must each load their type of record set.
     */
    protected abstract function loadRecords(\PDO $dbh);

    public abstract function createMarkup($inputClass = "");

    public abstract function savePost(\PDO $dbh, array $post, $user);

    public abstract function get_Data($code = "");

    public abstract function isRecordSetDefined($code);

    /**
     *
     * @param string $code
     * @return iTable or null
     */
    public function get_recordSet($code) {

        if (isset($this->rSs[$code])) {
            return $this->rSs[$code];
        }

        return null;
    }

    public function get_CodeArray() {
        return $this->codes;
    }
}



/**
 *  Street addresses
 */
class Address extends ContactPoint{

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


    public function get_preferredCode() {
        return $this->name->get_preferredMailAddr();
    }

    public function getTitle() {
        return "Street Address";
    }

    public function setPreferredCode($code) {

        if ($code == "" || isset($this->codes[$code])) {
            $this->name->set_preferredMailAddr($code);
        }
    }

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
            $data['Country_Code'] = $this->rSs[$code]->Country_Code->getStoredVal();
            $data['Postal_Code'] = $this->rSs[$code]->Postal_Code->getStoredVal();
            $data['County'] = $this->rSs[$code]->County->getStoredVal();
            $data['Set_Incomplete'] = $this->rSs[$code]->Set_Incomplete->getStoredVal();

        } else {

            $data['Address_1'] = '';
            $data['Address_2'] = '';
            $data['City'] = '';
            $data['State_Province'] = '';
            $data['Country_Code'] = '';
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
            'data-pref'=>$idPrefix
            );

        $table->addBodyTr(HTMLTable::makeTd('Street', array('class'=>'tdlabel', 'title'=>'Street Address'))
            . HTMLTable::makeTd(
                    HTMLInput::generateMarkup($adrRow->Address_1->getStoredVal(), $attr)));

        // Address 2
        $attr['id'] = $idPrefix.'adraddress2' . $addrIndex;
        $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][address2]';
        $attr['title'] = 'Apt, Suite, Mail Stop';
        $attr['class'] = "";

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
            $attr['class']= '';

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


        return $table->generateMarkup(array('class'=>$badAddrClass)) . $lastUpdated;
    }


    /**
      Builds the tab control containing each address type.
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
     * @param PDO $dbh
     * @param array $post
     * @param string $user
     * @return string
     * @throws Hk_Exception_InvalidArguement
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


    public function savePanel(\PDO $dbh, $purpose, $post, $user, $idPrefix = '', $incomplete = FALSE) {

        $indx = $idPrefix.'adr';
        if (isset($post[$indx][$purpose[0]]) === FALSE) {
            return;
        }

        return $this->saveAddress($dbh, $post[$idPrefix.'adr'][$purpose[0]], $purpose, $incomplete, $user);
    }


    public function saveAddress(\PDO $dbh, array $p, $purpose, $incomplete, $user) {

        $message = '';
        // Set some convenience vars.
        $a = $this->rSs[$purpose[0]];
        $id = $this->name->get_idName();

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
                $this->loadPostData($a, $p);

                if ($p['city'] != '' && $p['state'] != '' && $p['zip'] != '' && $p['address1'] != '') {
                    $a->Set_Incomplete->setNewVal(0);
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
            if ($p['city'] != '' || $p['state'] != '' || $p['zip'] != '' || $p['address1'] != '' || $incomplete) {

                // Insert a new address
                $this->loadPostData($a, $p);

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
     * @param iTableRS $a
     * @param array $p
     * @throws Hk_Exception_Runtime
     */
    protected function loadPostData(iTableRS $a, array $p) {

        if (is_a($this->cleanAddress, 'CleanAddress') === FALSE) {
            throw new Hk_Exception_Runtime("CleanAddress object is missing.  ");
        }

        // Clean the street address
        if (isset($p["address1"])) {
            $addrs = $this->cleanAddress->cleanAddr(trim(filter_var($p["address1"], FILTER_SANITIZE_STRING)));

            $street2 = '';
            if (isset($p['address2'])) {
                $street2 = trim(filter_var($p['address2'], FILTER_SANITIZE_STRING));
            }

            if ($street2 != "") {
                $street2 = $this->cleanAddress->convertSecondary($street2);
            } else {
                $street2 = $addrs[1];
            }

            $a->Address_1->setNewVal($addrs[0]);
            $a->Address_2->setNewVal($street2);
        }

        // Country
        $country = '';
        if (isset($p['country'])) {
            $country = trim(filter_var($p['country'], FILTER_SANITIZE_STRING));
        }
        if ($country == '' || strtoupper($country) == 'USA') {
            $country = "US";
        }
        $a->Country_Code->setNewVal($country);

        // zip code, city and state
        if (isset($p['city'])) {
            $a->City->setNewVal(trim(filter_var($p['city'], FILTER_SANITIZE_STRING)));
        }
        if (isset($p['county'])) {
            $a->County->setNewVal(trim(filter_var($p['county'], FILTER_SANITIZE_STRING)));
        }
        if (isset($p['state'])) {
            $a->State_Province->setNewVal(trim(filter_var($p['state'], FILTER_SANITIZE_STRING)));
        }
        if (isset($p['zip'])) {
            $a->Postal_Code->setNewVal(strtoupper(trim(filter_var($p['zip'], FILTER_SANITIZE_STRING))));
        }

        $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

        if (isset($p["bad"])) {
            $a->Bad_Address->setNewVal('true');
        } else {
            $a->Bad_Address->setNewVal('');
        }

    }

    public function checkZip(\PDO $dbh, iTableRS $a, array $p) {
        // zip code, city and state
        $zip = strtoupper(trim(filter_var($p['zip'], FILTER_SANITIZE_STRING)));
        $city = trim(filter_var($p['city'], FILTER_SANITIZE_STRING));
        $state = trim(filter_var($p['state'], FILTER_SANITIZE_STRING));
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


/**
 * Class Phone - Phone Numbers
 *
 */
class Phones extends ContactPoint {


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

    public function get_preferredCode() {
        return $this->name->get_preferredPhone();
    }

    public function getTitle() {
        return "Phone Number";
    }

    public function setPreferredCode($code) {

        if ($code == "" || isset($this->codes[$code])) {
            $this->name->set_preferredPhone($code);
        }
    }

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

        } else {

            $data["Phone_Num"] = "";
            $data["Phone_Extension"] = "";

        }

        return $data;
    }

    public function isRecordSetDefined($code) {

        $adrRS = $this->get_recordSet($code);

        if (is_null($adrRS) || $adrRS->Phone_Num->getStoredVal() == '') {
            return FALSE;
        } else {
            return TRUE;
        }

    }

    public function createMarkup($inputClass = '', $idPrefix = "", $room = FALSE, $roomPhoneCkd = FALSE) {

        $table = new HTMLTable();

        foreach ($this->codes as $p) {

            $trContents = $this->createPhoneMarkup($p, $inputClass, $idPrefix);
            // Wrapup this TR
            $table->addBodyTr($trContents);
        }

        if ($room) {
            $table->addBodyTr($this->createHousePhoneMarkup('yr', $idPrefix, $roomPhoneCkd));
        }

        return $table->generateMarkup();
    }

    public function createPhoneMarkup($p, $inputClass = '', $idPrefix = "", $showPrefCheckbox = TRUE) {

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

        // PHone number
        $attr = array();
        $attr['id'] = $idPrefix.'txtPhone' . $p[0];
        $attr['name'] = $idPrefix.'txtPhone[' . $p[0] . ']';
        $attr['title'] = 'Enter a phone number';
        $attr['class'] = 'hhk-phoneInput ' . $inputClass;
        $attr['size'] = '16';

        $tdContents = HTMLInput::generateMarkup($this->rSs[$p[0]]->Phone_Num->getStoredVal(), $attr);

        if ($p[0] != Phone_Purpose::Cell && $p[0] != Phone_Purpose::Cell2) {
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
        }

        // Wrapup the this td
        $trContents .= HTMLTable::MakeTd($tdContents, array('class'=>$p[2]));
        return $trContents;
    }

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

    public function SavePhoneNumber(\PDO $dbh, $post, $purpose, $user, $idPrefix = "") {

        if (isset($post[$idPrefix.'txtPhone'][$purpose[0]]) === FALSE) {
            return;
        }

        $id = $this->name->get_idName();
        // Set some convenience vars.
        $a = $this->rSs[$purpose[0]];
        $message = "";

        // Phone Number exists in DB?
        if ($a->idName->getStoredVal() > 0) {
            // Phone Number exists in the DB

            if ($post[$idPrefix.'txtPhone'][$purpose[0]] == '') {

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
            if ($post[$idPrefix.'txtPhone'][$purpose[0]] != '') {

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

    }

    private function loadPostData(NamePhoneRS $a, array $p, $typeCode, $uname, $idPrefix = '') {

        $ph = trim(filter_var($p[$idPrefix.'txtPhone'][$typeCode], FILTER_SANITIZE_STRING));
        $a->Phone_Num->setNewVal($ph);
        if (isset($p[$idPrefix.'txtExtn'][$typeCode])) {
            $a->Phone_Extension->setNewVal(trim(filter_var($p[$idPrefix.'txtExtn'][$typeCode], FILTER_SANITIZE_STRING)));
        }
        // phone search - use only the numberals for efficient phone number search
        $ary = array('+', '-');
        $a->Phone_Search->setNewVal(str_replace($ary, '', filter_var($ph, FILTER_SANITIZE_NUMBER_INT)));
        $a->Status->setNewVal('a');
        $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $a->Updated_By->setNewVal($uname);

    }

}

/**
 *  Email addresses
 */
class Emails extends ContactPoint {

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

    public function get_preferredCode() {
        return $this->name->get_preferredEmail();
    }

    public function getTitle() {
        return "Email Address";
    }

    public function setPreferredCode($code) {

        if ($code == "" || isset($this->codes[$code])) {
            $this->name->set_preferredEmail($code);
        }
    }

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

    public function isRecordSetDefined($code) {

        $adrRS = $this->get_recordSet($code);

        if (is_null($adrRS) || $adrRS->Email->getStoredVal() == '') {
            return FALSE;
        } else {
            return TRUE;
        }

    }

    public function createMarkup($inputClass = '', $idPrefix = "") {

        $table = new HTMLTable();

        foreach ($this->codes as $p) {

            $trContents = HTMLTable::makeTd($this->createEmailMarkup($p, $inputClass, $idPrefix));
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

    public function createEmailMarkup($p, $inputClass = '', $idPrefix = "", $showPrefCheckbox = TRUE) {

        $table = new HTMLTable();

        // Preferred Radio button
        //$tdContents = HTMLContainer::generateMarkup('label', $p[1], array('for'=>$idPrefix.'em'.$p[0]));

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
        //$tdContents .= HTMLInput::generateMarkup($p[0], $prefAttr);
        // The row
        $trContents = HTMLContainer::generateMarkup('td',
                HTMLContainer::generateMarkup('label', $p[1].$em, array('for'=>$idPrefix.'em'.$p[0]))
                .' ' .HTMLInput::generateMarkup($p[0], $prefAttr)
                , array('class'=>$p[2]));
        // Wrapup this TR
        $table->addBodyTr($trContents);


        // email address
        $attr = array();
        $attr['id'] = $idPrefix.'txtEmail' . $p[0];
        $attr['name'] = $idPrefix.'txtEmail[' . $p[0] . ']';
        $attr['title'] = 'Enter an email address';
        $attr['class'] = 'hhk-emailInput ' . $inputClass;
        $attr['size'] = '26';

        //$tdContents = HTMLInput::generateMarkup($this->rSs[$p[0]]->Email->getStoredVal(), $attr);

        // Wrapup the this tr
        $table->addBodyTr(HTMLContainer::generateMarkup('td', HTMLInput::generateMarkup($this->rSs[$p[0]]->Email->getStoredVal(), $attr), array('class'=>$p[2])));

        return $table->generateMarkup();
    }


    public function savePost(\PDO $dbh, array $post, $user, $idPrefix = '') {

        $message = '';
        $id = $this->name->get_idName();

        if ($id < 1) {
            return "Bad member Id.  ";
        }

        if (isset($post[$idPrefix.'txtEmail']) === FALSE) {
            return;
        }

        foreach ($this->codes as $purpose) {

            // Is the element even present?
            if (isset($post[$idPrefix.'txtEmail'][$purpose[0]])) {
                // Set some convenience vars.
                $a = $this->rSs[$purpose[0]];

                // Email Address exists in DB?
                if ($a->idName->getStoredVal() > 0) {
                    // Email Address exists in the DB

                    if ($post[$idPrefix.'txtEmail'][$purpose[0]] == '') {

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
                    if ($post[$idPrefix.'txtEmail'][$purpose[0]] != '') {

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


    private function loadPostData(NameEmailRS $a, array $p, $typeCode, $uname, $idPrefix = "") {

        $a->Email->setNewVal(trim(filter_var($p[$idPrefix.'txtEmail'][$typeCode], FILTER_SANITIZE_EMAIL)));
        $a->Status->setNewVal("a");
        $a->Updated_By->setNewVal($uname);
        $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

    }

}



