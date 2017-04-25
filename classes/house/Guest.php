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
    protected function factory(\PDO $dbh, $id) {

        $this->title = 'Guest';

        $this->patientPsg = NULL;

        return new GuestMember($dbh, MemBasis::Indivual, $id);
    }

    /**
     * Generate the name table in a fieldset.
     *
     * @param PDO $dbh
     * @return string HTML div markup
     */
    protected function createNameMU(Config_Lite $labels, $useAdditionalMarkup = FALSE, $lockRelChooser = FALSE) {

        // Build name.
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($this->name->createMarkupHdr($labels));
        $tbl->addHeaderTr($this->name->createMarkupRow($this->patientRelationshipCode, $lockRelChooser));


        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', $this->title.' Name', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup()
                        . HTMLContainer::generateMarkup('div', $this->name->birthDateMarkup(), array('style'=>'float:left;'))
                        . ($useAdditionalMarkup ? HTMLContainer::generateMarkup('div', $this->name->additionalNameMarkup(), array('style'=>'float:left;')) : '')
                        . HTMLContainer::generateMarkup('div', $this->name->getContactLastUpdatedMU(new \DateTime ($this->name->get_lastUpdated()), 'Name'), array('style'=>'float:right;'))
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

    /**
     *
     * @param PDO $dbh
     * @return array  Various pieces of markup and info
     */
    public function createMarkup(\PDO $dbh, $includeRemoveBtn = FALSE, $restrictRelChooser = TRUE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();
        $labels = new Config_Lite(LABEL_FILE);


        $mk1 = $this->createNameMu($labels, FALSE, $restrictRelChooser);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;min-height:10px;'));

        $mk1 .= $this->createAddsBLock();

        // Add Emergency contact
        $search = HTMLContainer::generateMarkup('span', '', array('name'=>$idPrefix, 'class'=>'hhk-guestSearch ui-icon ui-icon-search', 'title'=>'Search', 'style'=>'float: right; margin-left:.3em;cursor:pointer;'));

        $ec = $this->getEmergContactObj($dbh);
        $mk1 .= HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Emergency Contact for Guest' . $search, array('style'=>'font-weight:bold;'))
                . $ec->createMarkup($ec, removeOptionGroups($uS->nameLookups[GL_TableNames::RelTypes]), $idPrefix, $this->incompleteEmergContact), array('class'=>'hhk-panel')),
                array('style'=>'float:left; margin-right:3px;'));

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Header info

        // Stay dates
        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);
        $cidAttr = array('name'=>$idPrefix . 'gstDate', 'class'=>'ckdate gstchkindate', 'readonly'=>'readonly');

        if (is_null($this->getCheckinDT()) === FALSE && $this->getCheckinDT() < $nowDT) {
            $cidAttr['class'] .= ' ui-state-highlight';
        }

        $stayDates = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', ($this->getPatientRelationshipCode() == RelLinkType::Self ? $labels->getString('MemberType', 'patient', 'Patient') . ': ' : 'Guest: '), array('id'=>$idPrefix . 'spnHdrLabel', 'style'=>'font-size:1.2em;'))
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

        $mk1 = '';
        $labels = new Config_Lite(LABEL_FILE);

        // Guest Name
        $mk1 .= $this->createNameMu($labels, FALSE, TRUE);

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        $mk1 .= $this->createAddsBLock();

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        return HTMLContainer::generateMarkup('form', $mk1, array('name'=>'fAddGuest', 'method'=>'post'));
    }


    public function createReservationMarkup($lockRelChooser = FALSE) {

        $uS = Session::getInstance();
        $idPrefix = $this->getNameObj()->getIdPrefix();
        $mk1 = '';
        $labels = new Config_Lite(LABEL_FILE);

        // Guest Name
        if ($uS->PatientAsGuest && ($lockRelChooser === FALSE || $this->getPatientRelationshipCode() == '')) {
            // Dont lock the patient relationship chooser.
            $mk1 = $this->createNameMu($labels, FALSE, FALSE);
        } else {
            $mk1 = $this->createNameMu($labels, FALSE, TRUE);
        }

        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));
        $mk1 .= $this->createAddsBLock();
        $mk1 .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        // Header info
        // Check dates
        $nowDT = new \DateTime();
        $cidAttr = array('name'=>$idPrefix . 'gstDate', 'size'=>'11', 'class'=>'dprange');
        if (is_null($this->getCheckinDT()) === FALSE && $this->getCheckinDT() < $nowDT) {
            $cidAttr['class'] .= ' ui-state-highlight';
        }

        $header = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', $this->title.': ', array('id'=>$idPrefix . 'spnHdrLabel', 'style'=>'font-size:1.2em;'))
               .HTMLContainer::generateMarkup('span', $this->getNameObj()->get_firstName(), array('id'=>$idPrefix . 'hdrFirstName', 'name'=>'hdrFirstName', 'style'=>'font-size:1.2em;'))
               .HTMLContainer::generateMarkup('span', ' '.$this->getNameObj()->get_lastName(), array('id'=>$idPrefix . 'hdrLastName', 'name'=>'hdrLastName', 'style'=>'font-size:1.2em;'))
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
    public function save(\PDO $dbh, array $post, $uname) {

        $message = parent::save($dbh, $post, $uname);

        $idPrefix = $this->getNameObj()->getIdPrefix();

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
        return $this->checkinDate->format('Y-m-d 16:00:00');
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
        return $this->expectedCheckOut->format('Y-m-d 10:00:00');
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

