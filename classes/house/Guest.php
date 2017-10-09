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
 * Member type of Guest
 * @author Eric
 */
class Guest extends Role {


    /**
     *
     * @param PDO $dbh
     * @param type $id
     * @return GuestMember
     */
    public function __construct(\PDO $dbh, $idPrefix, $id, $title = 'Guest') {

        $this->currentlyStaying = NULL;
        $this->idVisit = NULL;
        $this->emergContact = NULL;
        $this->title = $title;
        $this->patientPsg = NULL;

        $this->roleMember = new GuestMember($dbh, MemBasis::Indivual, $id);
        $this->roleMember->setIdPrefix($idPrefix);

        if ($this->roleMember->getMemberDesignation() != MemDesignation::Individual) {
            throw new Hk_Exception_Runtime("Must be individuals, not organizations");
        }

    }

    /**
     * Generate the name table in a fieldset.
     *
     * @param PDO $dbh
     * @return string HTML div markup
     */
    protected function createNameMU(Config_Lite $labels, $lockRelChooser = FALSE) {

        // Build name.
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($this->roleMember->createMarkupHdr($labels, FALSE));
        $tbl->addbodyTr($this->roleMember->createMarkupRow($this->patientRelationshipCode, FALSE, $lockRelChooser));

        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup()
                        . HTMLContainer::generateMarkup('div', $this->roleMember->getContactLastUpdatedMU(new \DateTime ($this->roleMember->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
                        , array('class'=>'hhk-panel')),
                        array('style'=>'float:left; margin-right:.5em;margin-bottom:.4em; font-size:.9em;'));

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

    /**
     *
     * @param PDO $dbh
     * @return array  Various pieces of markup and info
     */
    public function createMarkup(\PDO $dbh, $includeRemoveBtn = FALSE, $lockRelChooser = TRUE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getRoleMember()->getIdPrefix();
        $labels = new Config_Lite(LABEL_FILE);

        $mk1 = $this->createNameMu($labels, $lockRelChooser);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;min-height:10px;'));

        if($uS->GuestAddr) {
            $mk1 .= $this->createAddsBLock();
        }

        // Add Emergency contact
        $ecSearch = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'class'=>'hhk-guestSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'float: right; margin-left:.3em;cursor:pointer;'));
        $ec = $this->getEmergContactObj($dbh);

        $mk1 .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Emergency Contact for Guest' . $ecSearch, array('style'=>'font-weight:bold;'))
                . $ec->createMarkup($ec, $uS->nameLookups[GL_TableNames::RelTypes], $idPrefix, $this->incompleteEmergContact), array('class'=>'hhk-panel')),
                array('style'=>'float:left; margin-right:3px;'));


        // Demographics
        if ($uS->ShowDemographics) {

            $mk1 .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Demographics', array('style'=>'font-weight:bold;'))
                    . $this->getRoleMember()->createDemographicsPanel($dbh, TRUE, FALSE), array('class'=>'hhk-panel')),
                    array('style'=>'float:left; margin-right:3px;'));
        }

        // Clear float
        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Stay dates
        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);
        $cidAttr = array('name'=>$idPrefix . 'gstDate', 'class'=>'ckdate gstchkindate', 'readonly'=>'readonly');

        if (is_null($this->getCheckinDT()) === FALSE && $this->getCheckinDT() < $nowDT) {
            $cidAttr['class'] .= ' ui-state-highlight';
        }

        $stayDates = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($this->getPatientRelationshipCode() == RelLinkType::Self ? $labels->getString('MemberType', 'patient', 'Patient') . ': ' : 'Guest: '), array('id'=>$idPrefix . 'spnHdrLabel'))
               .HTMLContainer::generateMarkup('span', $this->getRoleMember()->get_firstName(), array('id'=>$idPrefix . 'hdrFirstName', 'name'=>'hdrFirstName'))
               .HTMLContainer::generateMarkup('span', ' '.$this->getRoleMember()->get_lastName(), array('id'=>$idPrefix . 'hdrLastName', 'name'=>'hdrLastName', 'style'=>'margin-right:10px;'))
               . HTMLContainer::generateMarkup('span', ' Check In: '. HTMLInput::generateMarkup((is_null($this->getCheckinDT()) ? '' : $this->getCheckinDT()->format('M j, Y')), $cidAttr), array('style'=>'margin-left:.5em;'))
               . HTMLContainer::generateMarkup('span', 'Expected Departure: '. HTMLInput::generateMarkup((is_null($this->getExpectedCheckOutDT()) ? '' : $this->getExpectedCheckOutDT()->format('M j, Y')), Array('name'=>$idPrefix . 'gstCoDate', 'class'=>'ckdate gstchkoutdate', 'readonly'=>'readonly')), array('style'=>'margin-left:.5em;'))
                . HTMLContainer::generateMarkup('span', '', array('id'=>$idPrefix . 'naAddrIcon', 'class'=>'hhk-icon-redLight', 'title'=>'Incomplete Address', 'style'=>'float:right;margin-top:4px;margin-left:3px;display:none;'))
                        , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        $prevStays = '';
        if ($this->getIdName() != 0) {

            // get previous visit info
            $query = "select * from vstays_listing where idName = " . $this->getIdName() . " order by Checkin_Date desc LIMIT 3;";
            $stmt = $dbh->query($query);

            while ($s = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $prevStays .= ($prevStays != '' ? ';  ' : '') .$s['Room'] . ", " . date('m-d-y', strtotime($s['Checkin_Date']));
            }

            if ($prevStays != '') {
                $prevStays = HTMLContainer::generateMarkup('div', '(Previous stays:  ' . $prevStays . ')', array('style'=>'font-size:.87em;margin:4px;font-weight:normal;'));
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

    public function createThinMarkup(PSGMemStay $stay, $lockRelChooser) {

        $uS = Session::getInstance();

        $mu = parent::createThinMarkup($stay, $lockRelChooser);

        if ($uS->GuestAddr) {
            // Address
            $mu .= HTMLTable::makeTd(
                    HTMLContainer::generateMarkup('ul'
                            , HTMLContainer::generateMarkup('li',
                                    HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check'))
                                    , array('class'=>'ui-widget-header ui-corner-all hhk-AddrFlag ui-state-highlight', 'id'=>$this->getRoleMember()->getIdPrefix().'liaddrflag', 'style'=>'display:inline-block;margin-left:3px;'))
                            . HTMLContainer::generateMarkup('li',
                                    HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-circle-triangle-n'))
                                    , array('class'=>'ui-widget-header ui-corner-all hhk-togAddr', 'style'=>'display:inline-block;margin-left:5px;', 'title'=>'Open - Close Address Section'))
                            , array('data-pref'=>$this->getRoleMember()->getIdPrefix(), 'style'=>'padding-top:1px;list-style-type:none;cursor:pointer;', 'class'=>'ui-widget')
                            )
                    , array('style'=>'text-align:center;min-width:50px;')
                    );

        } else {
            $mu .= HTMLTable::makeTd('');
        }

        return $mu;
    }

    public function createAddToResvMarkup() {

        $uS = Session::getInstance();
        $mk1 = '';
        $labels = new Config_Lite(LABEL_FILE);

        // Guest Name
        $mk1 .= $this->createNameMu($labels, TRUE);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        if($uS->GuestAddr) {
            $mk1 .= $this->createAddsBLock();
            $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));
        }

        return HTMLContainer::generateMarkup('form', $mk1, array('name'=>'fAddGuest', 'method'=>'post'));
    }

    public function createReservationMarkup(\PDO $dbh, $lockRelChooser = FALSE, $waitListText = '') {

        $uS = Session::getInstance();
        $idPrefix = $this->getRoleMember()->getIdPrefix();

        $labels = new Config_Lite(LABEL_FILE);

        // Guest Name
        if ($uS->PatientAsGuest && ($lockRelChooser === FALSE || $this->getPatientRelationshipCode() == '')) {
            // Dont lock the patient relationship chooser.
            $lockRelChooser = FALSE;
        } else {
            $lockRelChooser = TRUE;
        }

        $mk1 = $this->createNameMu($labels, $lockRelChooser);
        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        //Guest Address
        if($uS->GuestAddr) {
            $mk1 .= $this->createAddsBLock();
        }

        // Demographics
        if ($uS->ShowDemographics) {

            $mk1 .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Demographics', array('style'=>'font-weight:bold;'))
                    . $this->getRoleMember()->createDemographicsPanel($dbh, TRUE, FALSE), array('class'=>'hhk-panel')),
                    array('style'=>'float:left; margin-right:3px;'));
        }

        // Waitlist notes
        if ($uS->UseWLnotes) {

            $mk1 .= HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'waitlistNotesLabel', 'Waitlist Notes'), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $waitListText, array('name'=>'taCkinNotes', 'rows'=>'3', 'cols'=>'55')),
                array('class'=>'hhk-panel', 'style'=>'font-size:.9em;'));
            }

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));


       // Header info
        $header = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', $this->title.': ', array('id'=>$idPrefix . 'spnHdrLabel'))
               .HTMLContainer::generateMarkup('span', $this->getRoleMember()->get_firstName(), array('id'=>$idPrefix . 'hdrFirstName', 'name'=>'hdrFirstName'))
               .HTMLContainer::generateMarkup('span', ' '.$this->getRoleMember()->get_lastName(), array('id'=>$idPrefix . 'hdrLastName', 'name'=>'hdrLastName'))
               .HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('span', '', array('id'=>'memMsg', 'style'=>'color:red;float:right; margin-right:23px;')), array('style'=>'margin-right:23px;'))
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));



        $rtn = array();

        $rtn['expDates'] = $this->getExpectedDatesControl();
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
    public function save(\PDO $dbh, array $post, $uname) {

        $message = parent::save($dbh, $post, $uname);

        $idPrefix = $this->getRoleMember()->getIdPrefix();

        // Use House Phone?
        if (isset($post[$idPrefix . 'rbPhPref']) && filter_var($post[$idPrefix . 'rbPhPref'], FILTER_SANITIZE_STRING) == 'yr') {
            $this->useHousePhone = TRUE;
        }

        $ec = $this->getEmergContactObj($dbh);
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

        // Also set patient member type if guest is the patient.
        if ($this->patientRelationshipCode == RelLinkType::Self) {
            $message .= $this->getRoleMember()->saveMemberType($dbh, $uname, VolMemberType::Patient);
        }

        return $message;
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
        return $this->checkinDate->format('Y-m-d H:i:s');
    }

    public function getCheckinDT() {
        return $this->checkinDate;
    }

    public function setCheckinDate($stringDate, $time = 'H:i:s') {
        if ($stringDate != '') {
            $uS = Session::getInstance();

            $ciDT = new \DateTime($stringDate);
            $ciDT->setTimezone(new \DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d');
            $now = date($time);

            $this->checkinDate = new \DateTime($dt . ' ' . $now);
        }
    }

    public function setExpectedCheckinDate($stringDate) {
        if ($stringDate != '') {
            $uS = Session::getInstance();

            $ciDT = new \DateTime($stringDate);
            $ciDT->setTimezone(new \DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d 16:00:00');

            $this->checkinDate = new \DateTime($dt);
        }

    }

    public function getExpectedCheckOut() {
        if (is_null($this->expectedCheckOut)) {
            return '';
        }
        return $this->expectedCheckOut->format('Y-m-d H:i:s');
    }

    public function getExpectedCheckOutDT() {
        return $this->expectedCheckOut;
    }

    public function setExpectedCheckOut($stringDate) {
        if ($stringDate != '') {
            $uS = Session::getInstance();

            $ciDT = new \DateTime($stringDate);
            $ciDT->setTimezone(new \DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d');

            $this->expectedCheckOut = new \DateTime($dt . ' 10:00:00');
        }
    }

    public function setTitle($title) {
        $this->title = $title;
    }

}

