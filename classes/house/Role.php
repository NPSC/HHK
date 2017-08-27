<?php
/**
 * Guest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Role
 *
 * @author Eric
 */
abstract class Role {

    /**
     *
     * @var RoleMember
     */
    protected $roleMember;

    /**
     *
     * @var Address
     */
    protected $addr;

    /**
     *
     * @var Phones
     */
    protected $phones;

    /**
     *
     * @var Emails
     */
    protected $emails;

    protected $incompleteAddress = FALSE;
    protected $useHousePhone = FALSE;
    /**
     *
     * @var EmergencyContact
     */
    protected $emergContact;

    protected $patientPsg;
    protected $idVisit;
    protected $title;
    protected $currentlyStaying;
    public $status = '';
    protected $checkinDate;
    protected $expectedCheckOut;
    protected $incompleteEmergContact = FALSE;
    protected $patientRelationshipCode = '';


    /**
     *
     * @param string $prefix HTML control id prefix
     * @param string $title
     * @return array Multiple pieces of the search header.
     */
    public static function createSearchHeaderMkup($prefix = "", $title = "", $showPhoneSearch = TRUE) {

        $phoneSearchMkup = '';

        if ($showPhoneSearch) {
            $phoneSearchMkup = HTMLContainer::generateMarkup('span', 'Phone # Search: ', array('style'=>'margin-left:1em; '))
                    .HTMLInput::generateMarkup('', array('id'=>$prefix.'phSearch', 'size'=>'14', 'title'=>'Enter at least 5 numbers to invoke search'));
        }

        $frst = HTMLContainer::generateMarkup('span', HTMLContainer::generateMarkup('span', $title, array('id'=>$prefix.'prompt'))
                .HTMLInput::generateMarkup('', array('id'=>$prefix.'Search', 'size'=>'25', 'title'=>'Enter at least 3 characters to invoke search'))
                .$phoneSearchMkup
                , array('id'=>$prefix . 'span'))
                .HTMLContainer::generateMarkup('span', 'Room Full', array('id'=>$prefix.'fullspan', 'style'=>'display:none;'));

        $rtn = array();
        $rtn['hdr'] = HTMLContainer::generateMarkup('div', $frst, array('id'=>'h2srch'.$prefix, 'style'=>"padding:4px;", 'class'=>$prefix.'Slot ui-widget ui-widget-header ui-state-default ui-corner-all'));
        $rtn['idPrefix'] = $prefix;
        return  $rtn;
    }

    protected function createMailAddrMU($class = "", $useCopyIcon = TRUE, $includeCounty = FALSE) {

        $idPrefix = $this->getRoleMember()->getIdPrefix();

        $trash = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'id'=>$idPrefix.'t', 'class'=>'hhk-addrErase ui-icon ui-icon-trash', 'title'=>'Erase', 'style'=>'float: right; margin-left:.3em;'));
        $copy = '';

        if ($useCopyIcon) {
            $copy = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'id'=>$idPrefix.'c', 'class'=>'hhk-addrCopy ui-icon ui-icon-copy', 'title'=>'Copy', 'style'=>'float: right; margin-left:.3em;'));
        }

        // Incomplete address
        $attr = array('type'=>'checkbox', 'name'=>$idPrefix.'incomplete');
        if ($this->getAddrObj()->getSet_Incomplete(Address_Purpose::Home)) {
            $attr['checked'] = 'checked';
        }

        $incomplete = HTMLContainer::generateMarkup('div',
                HTMLInput::generateMarkup('', $attr)
                . HTMLContainer::generateMarkup('label', ' Incomplete Address', array('for'=>$idPrefix.'incomplete')), array('title'=>'Incomplete Address', 'style'=>'margin-top: 10px;'));


        // Last Updated
        $lastUpdated = $this->getAddrObj()->getLastUpdated();
        if ($lastUpdated != '') {
            $lastUpdated = $this->roleMember->getContactLastUpdatedMU(new DateTime($this->getAddrObj()->getLastUpdated()));
        }

        return HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup(
                    'fieldset',
                    HTMLContainer::generateMarkup('legend', 'Home Address'.$copy.$trash, array('style'=>'font-weight:bold;'))
                    . $this->addr->createPanelMarkup(Address_Purpose::Home, $this->getAddrObj()->get_recordSet(Address_Purpose::Home), FALSE, $idPrefix, $class, $includeCounty, $lastUpdated)
                    . $incomplete,
                    array('class'=>'hhk-panel')),
                    array('style'=>'float:left; margin-right:3px; font-size:0.9em;', 'class'=>'hhk-addrPanel'));

    }

    protected function createPhoneEmailMU($idPrefix = '') {
        // Phone & email
        $ul = HTMLContainer::generateMarkup('ul',
            HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', 'Summary', array('href'=>"#".$idPrefix. "prefTab", 'title'=>"Show the preferred phone and Email")))
            .HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', 'Phone', array('href'=>"#".$idPrefix. "phonesTab", 'title'=>"Edit the Phone Numbers and designate the preferred number")))
            .HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', 'Email', array('href'=>"#".$idPrefix. "emailTab", 'title'=>"Edit the Email Addresses and designate the preferred address")))
            , array('style'=>'font-size:0.9em')
        );
        $divs = HTMLContainer::generateMarkup('div', Addresses::getPreferredPanel($this->getPhonesObj(), $this->getEmailsObj(), $this->getHousePhone()), array('id'=>$idPrefix.'prefTab', 'class'=>'ui-tabs-hide'))
                .HTMLContainer::generateMarkup('div', $this->getPhonesObj()->createMarkup("", $idPrefix, TRUE, $this->getHousePhone()), array('id'=>$idPrefix.'phonesTab', 'class'=>'ui-tabs-hide'))
                .HTMLContainer::generateMarkup('div', $this->getEmailsObj()->createMarkup("", $idPrefix), array('id'=>$idPrefix.'emailTab', 'class'=>'ui-tabs-hide'));

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('div', $ul . $divs, array('id'=>$idPrefix.'phEmlTabs', 'class'=>'hhk-phemtabs', 'style'=>'font-size:.9em;')), array('style'=>'float:left;margin-top:5px;margin-right:5px;', 'class'=>'hhk-tdbox'));
    }

    public function createThinAddrHdr($includeCounty = FALSE) {

        return HTMLTable::makeTh('Address')
                . HTMLTable::makeTh('')
                . HTMLTable::makeTh('Zip')
                . HTMLTable::makeTh('City')
                . ($includeCounty ? HTMLTable::makeTh('County') : '')
                . HTMLTable::makeTh('State')
                . HTMLTable::makeTh('Country');

    }

    public function createThinAddrMU($includeCounty = FALSE) {

        $idPrefix = $this->getRoleMember()->getIdPrefix();
        $addrIndex = Address_Purpose::Home;
        $adrRow = $this->getAddrObj()->get_recordSet($addrIndex);
        $rowTr = '';

        // address 1
        $attr = array(
            'type'=>'text',
            'size'=>'27',
            'title'=>'Street Address',
            'id'=>$idPrefix.'adraddress1' . $addrIndex,
            'name'=>$idPrefix.'adr[' . $addrIndex . '][address1]',
            );


        $rowTr .= HTMLTable::makeTd(HTMLInput::generateMarkup($adrRow->Address_1->getStoredVal(), $attr));

        // Address 2
        $attr['id'] = $idPrefix.'adraddress2' . $addrIndex;
        $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][address2]';
        $attr['title'] = 'Apt, Suite, Mail Stop';
        $attr['size'] = '17';

        $rowTr .= HTMLTable::makeTd(HTMLInput::generateMarkup($adrRow->Address_2->getStoredVal(), $attr));

        // & Zip
        $zipAttr = array(
            'id'=>$idPrefix.'adrzip'.$addrIndex,
            'name'=>$idPrefix.'adr['.$addrIndex.'][zip]',
            'type'=>'text',
            'size'=>'10',
            'class'=>'ckzip hhk-zipsearch ',
            'title'=>'Enter Postal Code',
            'data-hhkprefix'=>$idPrefix,
            'data-hhkindex'=>$addrIndex
            );

        $rowTr .= HTMLTable::makeTd(HTMLInput::generateMarkup($adrRow->Postal_Code->getStoredVal(), $zipAttr));

        // City
        $attr['id'] = $idPrefix.'adrcity' . $addrIndex;
        $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][city]';
        $attr['title'] = 'City Name';
        $attr['class']= '';

        $rowTr .= HTMLTable::makeTd(HTMLInput::generateMarkup($adrRow->City->getStoredVal(), $attr));

        // County
        if ($includeCounty) {
            $attr['id'] = $idPrefix.'adrcounty' . $addrIndex;
            $attr['name'] = $idPrefix.'adr[' . $addrIndex . '][county]';
            $attr['title'] = 'County Name';
            $attr['class']= '';

            $rowTr .= HTMLTable::makeTd(HTMLInput::generateMarkup($adrRow->County->getStoredVal(), $attr));
        }

        // State
        $stAttr['id'] = $idPrefix.'adrstate' . $addrIndex;
        $stAttr['name'] = $idPrefix.'adr[' . $addrIndex . '][state]';
        $stAttr['title'] = 'Select State or Province';
        $stAttr['style'] = 'margin-right:8px;';
        $stAttr["class"] = "bfh-states";
        $stAttr['data-country'] = $idPrefix.'adrcountry' . $addrIndex;
        $stAttr['data-state'] = $adrRow->State_Province->getStoredVal();

        $rowTr .= HTMLTable::makeTd(HTMLSelector::generateMarkup('', $stAttr));

        // Country
        $coAttr['id'] = $idPrefix.'adrcountry' . $addrIndex;
        $coAttr['name'] = $idPrefix.'adr[' . $addrIndex . '][country]';
        $coAttr['title'] = 'Select Country';
        $coAttr['class'] = 'input-medium bfh-countries';
        $coAttr['data-country'] = ($adrRow->Country_Code->getStoredVal() == '' ? 'US' : $adrRow->Country_Code->getStoredVal());

        $rowTr .= HTMLTable::makeTd(HTMLSelector::generateMarkup('', $coAttr));


        return $rowTr;

    }

    public function createThinMarkup($staying, $lockRelChooser) {

        $td = '';

        // Staying button
        if ($this->getNoReturn() != '') {
            // Set for no return
            $td = HTMLTable::makeTd('No Return', array('title'=>$this->getNoReturn()));

        } else if ($staying == 'x') {
            // This person cannot stay
            $td = HTMLTable::makeTd('');

        } else {

            $cbStay = array(
                'type'=>'checkbox',
                'name'=>$this->getIdName() .'cbStay',
                'class' => 'hhk-cbStay',
            );

            $lblStay = array(
                'for'=>$this->getIdName() . 'cbStay',
                'id' => $this->getIdName() . 'lblStay',
                'data-stay' => ($staying == '1' ? '1' : '0'),
                'class' => 'hhk-lblStay',
            );

            $td = HTMLTable::makeTd(
                HTMLContainer::generateMarkup('label', 'Stay', $lblStay)
                . HTMLInput::generateMarkup('', $cbStay));
        }

        // Phone
        $ph = HTMLTable::makeTd($this->getPhonesObj()->get_Data()['Phone_Num']);

        // Address
        $ad = HTMLTable::makeTd(HTMLInput::generateMarkup('Show', array('type'=>'button', 'id'=>$this->getIdName() . 'toggleAddr', 'class'=>'hhk-togAddr')));

        return $td . $this->roleMember->createThinMarkupRow($this->patientRelationshipCode, FALSE, $lockRelChooser) . $ph . $ad;

    }


        // Address, email and Phone
    public function createAddsBLock() {

        $mkup = '';
        $uS = Session::getInstance();

        // Street Address
        $mkup .= $this->createMailAddrMU($this->getRoleMember()->getIdPrefix() . 'hhk-addr-val', TRUE, $uS->county);

        // Phone and email
        $mkup .= $this->createPhoneEmailMU($this->getRoleMember()->getIdPrefix());

        return $mkup;
    }


    /**
     *
     * @param \PDO $dbh
     * @param array $post
     * @return string Message for end user.
     */
    public function save(\PDO $dbh, array $post, $uname) {

        $message = "";
        $idPrefix = $this->getRoleMember()->getIdPrefix();

        // Name
        $message .= $this->getRoleMember()->saveChanges($dbh, $post);


        $incomplete = FALSE;

        // Guest Incomplete address
        if (isset($post[$idPrefix.'incomplete'])) {
            $incomplete = TRUE;
        }

        // street Address
        $this->getAddrObj()->cleanAddress = new CleanAddress($dbh);
        $cdArray = $this->getAddrObj()->get_CodeArray();
        $message .= $this->getAddrObj()->savePanel($dbh, $cdArray[Address_Purpose::Home], $post, $uname, $idPrefix, $incomplete);

        // set preferred mail address
        $this->getRoleMember()->verifyPreferredAddress($dbh, $this->getAddrObj(), $uname);

        // Set incomplete address
        if ($this->getAddrObj()->getSet_Incomplete(Address_Purpose::Home)) {
            $this->incompleteAddress = TRUE;
        } else {
            $this->incompleteAddress = FALSE;
        }

        // Phone
        $message .= $this->getPhonesObj()->savePost($dbh, $post, $uname, $idPrefix);

        // Email
        $message .= $this->getEmailsObj()->savePost($dbh, $post, $uname, $idPrefix);

        return $message;
    }

        /**
     *
     * @param \PDO $dbh
     * @return boolean
     */
    public static function checkCurrentStay(\PDO $dbh, $idName) {

        $id = intval($idName, 10);
        $idVisit = 0;

        if ($id > 0) {

            $query = "select idVisit from stays where `Status` = '" . VisitStatus::CheckedIn . "' and idName = " . $id;
            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($rows) > 0 && $rows[0][0] > 0) {
                $idVisit = $rows[0][0];
            }
        }

        return $idVisit;
    }

    public static function checkPsgStays(\PDO $dbh, $idName, $PSG_Id) {

        $id = intval($idName, 10);
        $idPsg = intval($PSG_Id, 10);

        if ($id > 0 && $idPsg > 0) {

            $query = "Select count(s.idStays)
from stays s join visit v on s.idVisit = v.idVisit
	left join registration r on v.idRegistration = r.idRegistration
where r.idPsg = $idPsg and s.idName = " . $id;
            $stmt = $dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($rows) > 0 && $rows[0][0] > 0) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function getCurrentVisitId(\PDO $dbh) {

        if (is_null($this->idVisit)) {
            $this->setCurrentIdVisit(self::checkCurrentStay($dbh, $this->getIdName()));
        }

        return $this->idVisit;
    }

    public function getNoReturn() {
        $uS = Session::getInstance();

        if (isset($uS->nameLookups['NoReturnReason'][$this->roleMember->getNoReturnDemog()])) {
            return $uS->nameLookups['NoReturnReason'][$this->roleMember->getNoReturnDemog()][1];
        }

        return '';
    }

    public function setIncompleteAddr($TorF) {

        if ($TorF === TRUE) {
            $this->incompleteAddress = TRUE;
        } else {
            $this->incompleteAddress = FALSE;
        }
    }

    public function isCurrentlyStaying(\PDO $dbh) {

        if (is_null($this->currentlyStaying)) {
            $this->setCurrentIdVisit(self::checkCurrentStay($dbh, $this->getIdName()));
        }

        return $this->currentlyStaying;
    }

    protected function setCurrentIdVisit($idVisit) {

        $idv = intval($idVisit, 10);
        if ($idv > 0) {
            $this->currentlyStaying = TRUE;
            $this->idVisit = $idv;
        } else {
            $this->currentlyStaying = FALSE;
            $this->idVisit = 0;
        }

    }

    public function getEmergContactObj(\PDO $dbh) {

        if (is_null($this->emergContact)) {
            $this->emergContact = new EmergencyContact($dbh, $this->getIdName());
        }

        return $this->emergContact;
    }

    public function getExpectedDatesControl() {


        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);
        $cidAttr = array('name'=>'gstDate', 'readonly'=>'readonly', 'size'=>'14' );
        if (is_null($this->getCheckinDT()) === FALSE && $this->getCheckinDT() < $nowDT) {
            $cidAttr['class'] = ' ui-state-highlight';
        }

        return HTMLContainer::generateMarkup('span',
                HTMLContainer::generateMarkup('span', 'Expected Check In: '.
                    HTMLInput::generateMarkup((is_null($this->getCheckinDT()) ? '' : $this->getCheckinDT()->format('M j, Y')), $cidAttr))
               .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                    HTMLInput::generateMarkup((is_null($this->getExpectedCheckOutDT()) ? '' : $this->getExpectedCheckOutDT()->format('M j, Y'))
                            , Array('name'=>'gstCoDate', 'readonly'=>'readonly', 'size'=>'14')), array('style'=>'margin-left:.7em;'))
                 , array('style'=>'float:left;', 'id'=>'spnRangePicker'));

    }

    public function getIdName() {
        return $this->roleMember->get_idName();
    }

    public function getRoleMember() {
        return $this->roleMember;
    }

    public function getAddrObj() {

        if (is_null($this->addr)) {
            $dbh = initPDO();
            $uS = Session::getInstance();
            $this->addr = new Address($dbh, $this->roleMember, $uS->nameLookups[GL_TableNames::AddrPurpose]);
        }
        return $this->addr;
    }

    public function getPhonesObj() {
        if (is_null($this->phones)) {
            $dbh = initPDO();
            $uS = Session::getInstance();
            $this->phones = new Phones($dbh, $this->roleMember, $uS->nameLookups[GL_TableNames::PhonePurpose]);
        }
        return $this->phones;
    }

    public function getEmailsObj() {
        if (is_null($this->emails)) {
            $dbh = initPDO();
            $uS = Session::getInstance();
            $this->emails = new Emails($dbh, $this->roleMember, $uS->nameLookups[GL_TableNames::EmailPurpose]);
        }
        return $this->emails;
    }

    public function isNew() {
        return $this->roleMember->isNew();
    }

    public function getHousePhone() {
        return $this->useHousePhone;
    }

    public function getPatientRelationshipCode() {
        return $this->patientRelationshipCode;
    }

    public function setPatientRelationshipCode($v) {
        $this->patientRelationshipCode = $v;
    }

}
