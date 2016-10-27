<?php
/**
 * Guest.php
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Member type of Guest
 * @author Eric
 */
class Guest extends Role {

    /**
     *
     * @var EmergencyContact
     */
    protected $emergContact;

    /**
     *
     * @var Psg
     */
    protected $psg;

    protected $title;
    protected $currentlyStaying;
    public $status = '';
    protected $checkinDate;
    protected $expectedCheckOut;
    protected $incompleteEmergContact = FALSE;
    protected $patientRelationshipCode = '';
    public $patientRelId = 0;


    /**
     *
     * @param PDO $dbh
     * @param type $id
     * @return GuestMember
     */
    protected function factory(PDO $dbh, $id) {

        $this->title = 'Guest';

        $this->emergContact = new EmergencyContact($dbh, $id);

        $this->currentlyStaying = $this->checkCurrentStay($dbh, $id);

        return new GuestMember($dbh, MemBasis::Indivual, $id);
    }



    public function loadPatientRel() {

        $psg = $this->psg;
        if (isset($psg->psgMembers[$this->getIdName()])) {
            $this->patientRelId = $psg->psgMembers[$this->getIdName()]->idPatient_Relationship->getStoredVal();
            $this->patientRelationshipCode = $psg->psgMembers[$this->getIdName()]->Relationship_Code->getStoredVal();
        }
    }

    /**
     *
     * @param PDO $dbh
     * @return Psg
     */
    public function getPsgObj(PDO $dbh) {
        if (is_null($this->psg)) {
            $this->psg = Psg::instantiateFromGuestId($dbh, $this->getIdName());
        }
        return $this->psg;
    }


    /**
     * Generate the name table in a fieldset.
     *
     * @param PDO $dbh
     * @return string HTML div markup
     */
    protected function createNameMU($useAdditionalMarkup = FALSE, $lockRelChooser = FALSE) {

        $uS = Session::getInstance();

        // patient?
        $birth = '';
        if ($this->patientRelationshipCode == RelLinkType::Self && $uS->PatientBirthDate) {
            $birth = HTMLContainer::generateMarkup('div', $this->name->birthDateMarkup(), array('style'=>'float:left;'));
        }

        // Build name.
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($this->name->createMarkupHdr());
        $tbl->addHeaderTr($this->name->createMarkupRow($this->patientRelationshipCode, $lockRelChooser));


        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup()
                        . $birth
                        . ($useAdditionalMarkup ? HTMLContainer::generateMarkup('div', $this->name->additionalNameMarkup(), array('style'=>'float:left;')) : '')
                        . HTMLContainer::generateMarkup('div', $this->name->getContactLastUpdatedMU(new DateTime ($this->name->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
                        , array('class'=>'hhk-panel')),
                        array('style'=>'float:left; margin-right:.5em; font-size:.9em;'));

        return $mk1;
    }


    public function createNotesMU($notes, $idTextBox, \Config_Lite $labels) {

        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'notesLabel', 'Reservation Notes'), array('style'=>'font-weight:bold;'))
                        . Notes::markupShell($notes, $idTextBox),
                        array('class'=>'hhk-panel')));

        return $mk1;
    }

    public function createThinMarkup(\PDO $dbh, $includeRemoveBtn = FALSE, $restrictRelChooser = TRUE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();

        $mk1 = $this->createNameMu(TRUE, $restrictRelChooser);

        if ($uS->GuestAddr) {
            $mk1 .= $this->createMailAddrMU($idPrefix . 'hhk-addr-val', TRUE, $uS->county, $thinMode);
        }

        $mk1 .= $this->createThinPhoneEmailMu($idPrefix);

    }


    /**
     *
     * @param PDO $dbh
     * @return array  Various pieces of markup and info
     */
    public function createMarkup(PDO $dbh, $includeRemoveBtn = FALSE, $restrictRelChooser = TRUE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();

        $mk1 = $this->createNameMu(TRUE, $restrictRelChooser);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;min-height:10px;'));

        // Home Address
        if ($uS->GuestAddr) {
            $mk1 .= $this->createMailAddrMU($idPrefix . 'hhk-addr-val', TRUE, $uS->county);
        }

        // Phone and email
        $mk1 .= $this->createPhoneEmailMU($idPrefix);


        // Add Emergency contact
        $search = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'class'=>'hhk-guestSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'float: right; margin-left:.3em;cursor:pointer;'));

        $ec = $this->emergContact;
        $mk1 .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Emergency Contact for Guest' . $search, array('style'=>'font-weight:bold;'))
                . $ec->createMarkup($ec, removeOptionGroups($uS->nameLookups[GL_TableNames::RelTypes]), $idPrefix, $this->incompleteEmergContact), array('class'=>'hhk-panel')),
                array('style'=>'float:left; margin-right:3px;'));

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Header info

        // Stay dates
        $nowDT = new DateTime();
        $nowDT->setTime(0, 0, 0);
        $cidAttr = array('name'=>$idPrefix . 'gstDate', 'class'=>'ckdate gstchkindate', 'readonly'=>'readonly');

        if (is_null($this->getCheckinDT()) === FALSE && $this->getCheckinDT() < $nowDT) {
            $cidAttr['class'] .= ' ui-state-highlight';
        }

        $stayDates = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($this->getPatientRelationshipCode() == RelLinkType::Self ? 'Patient: ' : 'Guest: '), array('id'=>$idPrefix . 'spnHdrLabel', 'style'=>'font-size:1.2em;'))
               .HTMLContainer::generateMarkup('span', $this->getNameObj()->get_firstName(), array('id'=>$idPrefix . 'hdrFirstName', 'name'=>'hdrFirstName', 'style'=>'font-size:1.5em;color:black;'))
               .HTMLContainer::generateMarkup('span', ' '.$this->getNameObj()->get_lastName(), array('id'=>$idPrefix . 'hdrLastName', 'name'=>'hdrLastName', 'style'=>'margin-right:10px;font-size:1.5em;color:black;'))
               . HTMLContainer::generateMarkup('span', ' Check In: '. HTMLInput::generateMarkup((is_null($this->getCheckinDT()) ? '' : $this->getCheckinDT()->format('M j, Y')), $cidAttr), array('style'=>'margin-left:.5em;'))
               . HTMLContainer::generateMarkup('span', 'Expected Departure: '. HTMLInput::generateMarkup((is_null($this->getExpectedCheckOutDT()) ? '' : $this->getExpectedCheckOutDT()->format('M j, Y')), Array('name'=>$idPrefix . 'gstCoDate', 'class'=>'ckdate gstchkoutdate', 'readonly'=>'readonly')), array('style'=>'margin-left:.5em;'))
                . HTMLContainer::generateMarkup('span', '', array('id'=>$idPrefix . 'naAddrIcon', 'class'=>'hhk-icon-redLight', 'title'=>'Incomplete Address', 'style'=>'float:right;margin-top:4px;margin-left:3px;display:none;'))
                        , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        $prevStays = '';
        if ($this->getIdName() != 0) {
            // get previous visit info
            $stays = VisitView::loadGuestStays($dbh, $this->getIdName());

            if (count($stays) > 0) {

                $ctr = 0;
                foreach ($stays as $s) {
                    $prevStays .= ($ctr > 0 ? ';  ' : '') .$s['Room'] . ", " . date('m-d-y', strtotime($s['Checkin_Date']));
                    if ($ctr++ > 3) {
                        break;
                    }
                }

                if ($ctr > 0) {
                    $prevStays = HTMLContainer::generateMarkup('div', '(Previous stays:  ' . $prevStays . ')', array('style'=>'font-size:.87em;margin:4px;font-weight:normal;'));
                }
            }
        }

        // Finish the markup
        $rtn = array();
        $rtn['txtHdr'] = $stayDates;
        $rtn['memMkup'] = $prevStays . HTMLContainer::generateMarkup('div', $mk1, array('class'=>'ui-corner-bottom hhk-panel hhk-tdbox'));
        $rtn['idPrefix'] = $idPrefix;
        $rtn['role'] = 'g';
        $rtn['idName'] = $this->getIdName();

        if ($includeRemoveBtn) {
            $rtn['rmvbtn'] = '1';
        }


        return $rtn;

    }


    public function createAddToResvMarkup() {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();
        $mk1 = '';

        // Guest Name
        $mk1 .= $this->createNameMu(TRUE, TRUE);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Home Address
        if ($uS->GuestAddr) {
            $mk1 .= $this->createMailAddrMU($idPrefix . 'hhk-addr-val', TRUE, $uS->county);
        }

        // Phone & email
        $mk1 .= $this->createPhoneEmailMU($idPrefix);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        return HTMLContainer::generateMarkup('form', $mk1, array('name'=>'fAddGuest', 'method'=>'post'));
    }


    public function createReservationMarkup($lockRelChooser = FALSE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();
        $mk1 = '';

        // Guest Name
        if ($uS->PatientAsGuest && $lockRelChooser === FALSE) {
            // Dont lock the patient relationship chooser.
            $mk1 = $this->createNameMu(TRUE, FALSE);
        } else {
            $mk1 = $this->createNameMu(TRUE, TRUE);
        }

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Home Address
        if ($uS->GuestAddr) {
            $mk1 .= $this->createMailAddrMU($idPrefix . 'hhk-addr-val', TRUE, $uS->county);
        }

        // Phone & email
        $mk1 .= $this->createPhoneEmailMU($idPrefix);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Header info
        // Check dates
        $nowDT = new DateTime();
        $cidAttr = array('name'=>$idPrefix . 'gstDate', 'size'=>'11', 'class'=>'dprange');
        if (is_null($this->getCheckinDT()) === FALSE && $this->getCheckinDT() < $nowDT) {
            $cidAttr['class'] .= ' ui-state-highlight';
        }

        $header = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', $this->title.': ', array('id'=>$idPrefix . 'spnHdrLabel', 'style'=>'font-size:1.5em;'))
               .HTMLContainer::generateMarkup('span', $this->getNameObj()->get_firstName(), array('id'=>$idPrefix . 'hdrFirstName', 'name'=>'hdrFirstName', 'style'=>'font-size:1.5em;'))
               .HTMLContainer::generateMarkup('span', ' '.$this->getNameObj()->get_lastName(), array('id'=>$idPrefix . 'hdrLastName', 'name'=>'hdrLastName', 'style'=>'font-size:1.5em;'))
               .HTMLContainer::generateMarkup('span', ' Check In: '.
                       HTMLInput::generateMarkup((is_null($this->getCheckinDT()) ? '' : $this->getCheckinDT()->format('M j, Y')), $cidAttr), array('style'=>'margin-left:1.5em;'))
               .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                       HTMLInput::generateMarkup((is_null($this->getExpectedCheckOutDT()) ? '' : $this->getExpectedCheckOutDT()->format('M j, Y')), Array('name'=>$idPrefix . 'gstCoDate', 'size'=>'11', 'class'=>'ckdate')), array('style'=>'margin-left:.5em;'))
               .HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('span', '', array('id'=>'memMsg', 'style'=>'color:red;float:right; margin-right:23px;')), array('style'=>'margin-right:23px;'))
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        // Finish the markup
        $rtn = array();

        $rtn['txtHdr'] = $header;
        $rtn['memMkup'] = HTMLContainer::generateMarkup('div', $mk1, array('class'=>'ui-widget ui-widget-content ui-corner-bottom hhk-panel hhk-tdbox'));
        $rtn['idPrefix'] = $idPrefix;
        $rtn['idName'] = $this->getIdName();


        return $rtn;

    }


    /**
     *
     * @param PDO $dbh
     * @param array $post
     * @return string Message for end user.
     */
    public function save(PDO $dbh, array $post, $uname) {

        $message = parent::save($dbh, $post, $uname);

        $idPrefix = $this->getNameObj()->getIdPrefix();

        // Use House Phone?
        if (isset($post[$idPrefix . 'rbPhPref']) && filter_var($post[$idPrefix . 'rbPhPref'], FILTER_SANITIZE_STRING) == 'yr') {
            $this->useHousePhone = TRUE;
        }

        $ec = $this->getEmergContactObj();
        if (is_null($ec) === FALSE) {
            $ec->save($dbh, $this->getIdName(), $post, $uname, $idPrefix);
        }

        // Guest Checkin Date
        if (isset($post[$idPrefix.'gstDate'])) {
            $this->setCheckinDate(filter_var($post[$idPrefix.'gstDate'], FILTER_SANITIZE_STRING));
        }
        // Guest Checkout Date
        if (isset($post[$idPrefix.'gstCoDate'])) {
            $this->setExpectedCheckOut(filter_var($post[$idPrefix.'gstCoDate'], FILTER_SANITIZE_STRING));
        }
        // Guest Patient relationship
        if (isset($post[$idPrefix.'selPatRel'])) {
            $this->patientRelationshipCode = filter_var($post[$idPrefix.'selPatRel'], FILTER_SANITIZE_STRING);
        }
        // Guest incomplete emergency contact
        if (isset($post[$idPrefix.'cbEmrgLater'])) {
            $this->incompleteEmergContact = TRUE;
        }

        return $message;
    }

    public function getCurrentVisitId(PDO $dbh) {

        if ($this->getIdName() > 0) {

            $query = "select ifnull(idVisit, 0) as `idVisit` from stays where `Status` = :stat and idName = :id;";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(":stat"=>  VisitStatus::CheckedIn, ":id"=>$this->getIdName()));
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if (isset($rows)) {
                return $rows[0][0];
            }
        }

        return 0;
    }


    /**
     *
     * @param PDO $dbh
     * @return boolean
     */
    public static function checkCurrentStay(PDO $dbh, $id, $status = VisitStatus::CheckedIn) {

        if ($id > 0) {

            $query = "select count(*) from stays where `Status` = :stat and idName = :id;";
            $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $stmt->execute(array(":stat"=> $status, ":id"=>$id));
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if (isset($rows) && $rows[0][0] > 0) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function isCurrentlyStaying() {
        return $this->currentlyStaying;
    }

    public function getPatientRelationshipCode() {
        return $this->patientRelationshipCode;
    }

    public function setPatientRelationshipCode($v) {
        $this->patientRelationshipCode = $v;
    }

    public function getCheckinDate() {
        if (is_null($this->checkinDate)) {
            return '';
        }
        return $this->checkinDate->format('Y-m-d H:i:s');
    }

    public function getExpectedCheckinDate() {
        if (is_null($this->checkinDate)) {
            return '';
        }
        return $this->checkinDate->format('Y-m-d 16:00:00');
    }

    public function getCheckinDT() {
        return $this->checkinDate;
    }

    public function setCheckinDate($stringDate, $time = 'H:i:s') {
        if ($stringDate != '') {
            $uS = Session::getInstance();

            $ciDT = new DateTime($stringDate);
            $ciDT->setTimezone(new DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d');
            $now = date($time);

            $this->checkinDate = new DateTime($dt . ' ' . $now);
        }
    }

    public function setExpectedCheckinDate($stringDate) {
        if ($stringDate != '') {
            $uS = Session::getInstance();

            $ciDT = new DateTime($stringDate);
            $ciDT->setTimezone(new DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d 16:00:00');

            $this->checkinDate = new DateTime($dt);
        }

    }

    public function getExpectedCheckOut() {
        if (is_null($this->expectedCheckOut)) {
            return '';
        }
        return $this->expectedCheckOut->format('Y-m-d 10:00:00');
    }

    public function getExpectedCheckOutDT() {
        return $this->expectedCheckOut;
    }

    public function setExpectedCheckOut($stringDate) {
        if ($stringDate != '') {
            $uS = Session::getInstance();

            $ciDT = new DateTime($stringDate);
            $ciDT->setTimezone(new DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d');

            $this->expectedCheckOut = new DateTime($dt . ' 10:00:00');
        }
    }

    public function getEmergContactObj() {
        return $this->emergContact;
    }

    public function setTitle($title) {
        $this->title = $title;
    }

}

