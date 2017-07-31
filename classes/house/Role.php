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
    protected $name;

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

    function __construct(\PDO $dbh, $idPrefix, $id) {

        $this->currentlyStaying = NULL;
        $this->idVisit = NULL;
        $this->emergContact = NULL;

        $this->name = $this->factory($dbh, $id);
        $this->name->setIdPrefix($idPrefix);

        $this->build($dbh);

    }

    protected abstract function factory(\PDO $dbh, $id);


    protected function build(\PDO $dbh) {

        // get session instance
        $uS = Session::getInstance();


        if ($this->name->getMemberDesignation() != MemDesignation::Individual) {
            throw new Hk_Exception_Runtime("Must be individuals, not organizations");
        }

        $this->addr = new Address($dbh, $this->name, $uS->nameLookups[GL_TableNames::AddrPurpose]);
        $this->phones = new Phones($dbh, $this->name, $uS->nameLookups[GL_TableNames::PhonePurpose]);
        $this->emails = new Emails($dbh, $this->name, $uS->nameLookups[GL_TableNames::EmailPurpose]);

    }

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

        $idPrefix = $this->getNameObj()->getIdPrefix();

        $trash = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'id'=>$idPrefix.'t', 'class'=>'hhk-addrErase ui-icon ui-icon-trash', 'title'=>'Erase', 'style'=>'float: right; margin-left:.3em;'));
        $copy = '';

        if ($useCopyIcon) {
            $copy = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'id'=>$idPrefix.'c', 'class'=>'hhk-addrCopy ui-icon ui-icon-copy', 'title'=>'Copy', 'style'=>'float: right; margin-left:.3em;'));
        }

        // Incomplete address
        $attr = array('type'=>'checkbox', 'name'=>$idPrefix.'incomplete');
        if ($this->addr->getSet_Incomplete(Address_Purpose::Home)) {
            $attr['checked'] = 'checked';
        }

        $incomplete = HTMLContainer::generateMarkup('div',
                HTMLInput::generateMarkup('', $attr)
                . HTMLContainer::generateMarkup('label', ' Incomplete Address', array('for'=>$idPrefix.'incomplete')), array('title'=>'Incomplete Address', 'style'=>'margin-top: 10px;'));


        // Last Updated
        $lastUpdated = $this->addr->getLastUpdated();
        if ($lastUpdated != '') {
            $lastUpdated = $this->name->getContactLastUpdatedMU(new DateTime($this->addr->getLastUpdated()));
        }

        return HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup(
                    'fieldset',
                    HTMLContainer::generateMarkup('legend', 'Home Address'.$copy.$trash, array('style'=>'font-weight:bold;'))
                    . $this->addr->createPanelMarkup(Address_Purpose::Home, $this->addr->get_recordSet(Address_Purpose::Home), FALSE, $idPrefix, $class, $includeCounty, $lastUpdated)
                    . $incomplete,
                    array('class'=>'hhk-panel')),
                    array('style'=>'float:left; margin-right:3px; font-size:0.9em;'));

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

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('div', $ul . $divs, array('id'=>$idPrefix.'phEmlTabs', 'style'=>'font-size:.9em;')), array('style'=>'float:left;margin-top:5px;margin-right:5px;', 'class'=>'hhk-tdbox'));
    }

    protected function createThinPhoneEmailMu($idPrefix = '') {

        $tbl = new HTMLTable();

        $tr = '';
        $p = $this->getPhonesObj()->get_CodeArray();
        $tr .= $this->getPhonesObj()->createPhoneMarkup($p[Phone_Purpose::Cell], '', $idPrefix, FALSE);

        $e = $this->getEmailsObj()->get_CodeArray();
        $tr .= $this->getEmailsObj()->createEmailMarkup($e[Email_Purpose::Home], '', $idPrefix, FALSE);

        $tbl->addBodyTr($tr);

        return $tbl->generateMarkup();

    }

    public function createThinMarkup($staying, $lockRelChooser) {

        $tr = $this->name->createMarkupRow($this->patientRelationshipCode, FALSE, $lockRelChooser);

        $cbStay = array(
            'type'=>'checkbox',
            'name'=>$this->getIdName() .'cbStay',
            'class' => 'hhk-cbStay',
        );

        if ($staying == '1') {
            $cbStay['checked'] = 'checked';
        }

        if ($this->getNoReturn() != '') {
            // Set for no return
            $tr .= HTMLTable::makeTd('No Return', array('title'=>$this->getNoReturn()));

        } else if ($staying == 'x') {
            // This person cannot stay
            $tr .= HTMLTable::makeTd('');

        } else {

            $tr .= HTMLTable::makeTd(
                HTMLContainer::generateMarkup('label', 'Staying', array('for'=>$this->getIdName() . 'cbStay'))
                . HTMLInput::generateMarkup('', $cbStay));
        }

        return $tr;

    }


        // Address, email and Phone
    protected function createAddsBLock() {

        $mkup = '';
        $uS = Session::getInstance();

        // Street Address
        $mkup .= $this->createMailAddrMU($this->getNameObj()->getIdPrefix() . 'hhk-addr-val', TRUE, $uS->county);

        // Phone and email
        $mkup .= $this->createPhoneEmailMU($this->getNameObj()->getIdPrefix());

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
        $idPrefix = $this->getNameObj()->getIdPrefix();

        // Name
        $message .= $this->getNameObj()->saveChanges($dbh, $post);


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
        $this->getNameObj()->verifyPreferredAddress($dbh, $this->getAddrObj(), $uname);

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

        if (isset($uS->nameLookups['NoReturnReason'][$this->name->getNoReturnDemog()])) {
            return $uS->nameLookups['NoReturnReason'][$this->name->getNoReturnDemog()][1];
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
        return $this->name->get_idName();
    }

    public function getNameObj() {
        return $this->name;
    }

    public function getAddrObj() {
        return $this->addr;
    }

    public function getPhonesObj() {
        return $this->phones;
    }

    public function getEmailsObj() {
        return $this->emails;
    }

    public function isNew() {
        return $this->name->isNew();
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
