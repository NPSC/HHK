<?php
/**
 * ReserveData.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 **/

/**
 * Description of ReserveData
 *
 * @author Eric
 */
class ReserveData {

    const RESV_CHOOSER = 'resvChooser';
    const PSG_CHOOSER = 'psgChooser';
    const FAM_SECTION = 'famSection';
    const EXPECTED_DATES = 'expDates';
    const HOSPITAL_SECTION = 'hosp';
    const RESV_SECTION = 'resv';
    const CHECKIN_SECTION = 'resv';
    const FULL_NAME = 'fullName';
    const ADD_PERSON = 'addPerson';
    const WARNING = 'warning';

    const ROLE = 'role';
    const PREF = 'pref';
    const STAY = 'stay';
    const ID = 'id';
    const PRI = 'pri';


    const GUEST_ADMIN = 'guestadmin';

    const STAYING = '1';
    const NOT_STAYING = '0';
    const CANT_STAY = 'x';
    const IN_ROOM = 'r';

    const DATE_FORMAT = 'M j, Y';

    protected $idResv = 0;
    protected $id;
    protected $idPsg = 0;
    protected $idHospitalStay = 0;
    protected $idVisit = 0;
    protected $span = 0;
    protected $spanStatus = '';
    protected $spanStartDT = NULL;
    protected $spanEndDT = NULL;
    protected $forceNewPsg = FALSE;
    protected $forceNewResv = FALSE;
    protected $fullName = '';
    protected $resvTitle;
    protected $saveButtonLabel;
    protected $fillEmergencyContact;
    protected $patAsGuestFlag;
    protected $patBirthDateFlag;
    protected $showBirthDate;
    protected $patLabel;
    protected $wlNotesLabel;
    protected $addrPurpose;
    protected $resvEarlyArrDays;
    protected $psgTitle;
    protected $resvChooser;
    protected $psgChooser;
    protected $familySection;
    protected $expectedDatesSection;
    protected $hospitalSection;
    protected $reservationSection;
    protected $checkinSection;
    protected $paymentSection;
    protected $addPerson;
    protected $arrivalDT;
    protected $departureDT;
    protected $concurrentRooms = 0;
    protected $psgMembers;
    protected $errors;
    protected $resvPrompt;

    function __construct($post, $reservationTitle = '') {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);
        $this->psgMembers = array();

        if (isset($post['rid'])) {
            $this->setIdResv(intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['vid'])) {
            $this->setIdVisit(intval(filter_var($post['vid'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['span'])) {
            $this->setSpan(intval(filter_var($post['span'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['vstatus'])) {
            $this->setSpanStatus(filter_var($post['vstatus'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['id'])) {
            $this->setId(intval(filter_var($post['id'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['idPsg'])) {
            $this->setIdPsg(intval(filter_var($post['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        if (isset($post['fullName'])) {
            $this->fullName = filter_var($post['fullName'], FILTER_SANITIZE_STRING);
        }

        if (isset($post['gstDate'])) {
            $this->setArrivalDateStr(filter_var($post['gstDate'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['gstCoDate'])) {
            $this->setDepartureDateStr(filter_var($post['gstCoDate'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['mem'])) {
            $this->setMembersFromPost(filter_var_array($post['mem'], FILTER_SANITIZE_STRING));
        }

        $this->saveButtonLabel = 'Save ';
        $this->resvEarlyArrDays = $uS->ResvEarlyArrDays;
        $this->patAsGuestFlag = $uS->PatientAsGuest;
        $this->patBirthDateFlag = $uS->InsistPatBD;
        $this->showBirthDate = $uS->ShowBirthDate;
        $this->fillEmergencyContact = isset($uS->EmergContactFill) ? $uS->EmergContactFill : 'false';
        $this->patLabel = $labels->getString('MemberType', 'patient', 'Patient');
        $this->psgTitle = $labels->getString('statement', 'psgLabel', 'Patient Support Group');
        $this->wlNotesLabel = $labels->getString('referral', 'waitlistNotesLabel', 'Waitlist Notes');
        $this->addrPurpose = '1';
        $this->resvChooser = '';
        $this->psgChooser = '';
        $this->familySection = '';
        $this->expectedDatesSection = '';
        $this->hospitalSection = '';
        $this->reservationSection = '';
        $this->checkingInSection = '';
        $this->paymentSection = '';
        $this->errors = '';
        $this->resvPrompt = $labels->getString('guestEdit', 'reservationTitle', 'Reservation');
        $this->resvTitle = ($reservationTitle == '' ? $this->resvPrompt : $reservationTitle);

    }

    protected function setMembersFromPost($postMems) {

        foreach ($postMems as $prefix => $memArray) {

            if ($prefix == '') {
                continue;
            }

            $id = 0;
            $role = '';
            $stay = ReserveData::NOT_STAYING;
            $priGuest = 0;

            if (isset($memArray[ReserveData::ID])) {
                $id = intval($memArray[ReserveData::ID], 10);
            }

            if (isset($memArray[ReserveData::ROLE])) {
                $role = $memArray[ReserveData::ROLE];
            }

            if (isset($memArray[ReserveData::STAY])) {
                $stay = $memArray[ReserveData::STAY];
            }

            if (isset($memArray[ReserveData::PRI])) {
                $priGuest = $memArray[ReserveData::PRI];
            }

            $psgMember = $this->getPsgMember($prefix);

            if (is_null($psgMember)) {
                $this->setMember(new PSGMember($id, $prefix, $role, $priGuest, new PSGMemStay($stay)));
            } else {
                $psgMember->setStay($stay)
                        ->setRole($role)
                        ->setPrimaryGuest($priGuest);
            }

        }
    }

    public function getMembersArray() {

        $memArray = array();

        foreach ($this->getPsgMembers() as $mem) {
            $memArray[$mem->getPrefix()] = $mem->toArray();
        }

        return $memArray;
    }

    public function toArray() {

        $rtnData =  array(
            'id' => $this->getId(),
            'rid' => $this->getIdResv(),
            'idPsg' => $this->getIdPsg(),
            'vid' => $this->getIdVisit(),
            'span'=> $this->getSpan(),
            'patLabel' => $this->getPatLabel(),
            'resvTitle' => $this->getResvTitle(),
            'saveButtonLabel' => $this->saveButtonLabel,
        );

        if ($this->resvChooser != '') {
            $rtnData[ReserveData::RESV_CHOOSER] = $this->resvChooser;
        }

        if ($this->psgChooser != '') {
            $rtnData[ReserveData::PSG_CHOOSER] = $this->psgChooser;
        }

        if ($this->familySection != '') {
            $rtnData[ReserveData::FAM_SECTION] = $this->familySection;
        }

        if ($this->hospitalSection != '') {
            $rtnData[ReserveData::HOSPITAL_SECTION] = $this->hospitalSection;
        }

        if ($this->expectedDatesSection != '') {
            $rtnData[ReserveData::EXPECTED_DATES] = $this->expectedDatesSection;
        }

        if ($this->reservationSection != '') {
            $rtnData[ReserveData::RESV_SECTION] = $this->reservationSection;
        }

        if ($this->checkinSection != '') {
            $rtnData[ReserveData::CHECKIN_SECTION] = $this->checkinSection;
        }

        if ($this->addPerson != '') {
            $rtnData[ReserveData::ADD_PERSON] = $this->addPerson;
        }

        if ($this->fullName != '') {
            $rtnData[ReserveData::FULL_NAME] = $this->fullName;
        }

        if ($this->errors != '') {
            $rtnData[ReserveData::WARNING] = $this->errors;
        }

        return $rtnData;
    }

    public function getIdResv() {
        return $this->idResv;
    }

    public function getId() {
        return $this->id;
    }

    public function getIdPsg() {
        return $this->idPsg;
    }

    public function getIdHospital_Stay() {
        return $this->idHospitalStay;
    }

    public function getIdVisit() {
        return $this->idVisit;
    }

    public function getSpan() {
        return $this->span;
    }

    public function getSpanStatus() {
        return $this->spanStatus;
    }

    public function getSpanStartDT() {
        if (is_null($this->spanStartDT)) {
            return $this->getArrivalDT();
        }
        return $this->spanStartDT;
    }

    public function getSpanEndDT() {
        if (is_null($this->spanEndDT)) {
            return $this->getDepartureDT();
        }
        return $this->spanEndDT;
    }

    public function getConcurrentRooms() {
        return $this->concurrentRooms;
    }

    public function getResvTitle() {
        return $this->resvTitle;
    }

    public function getResvPrompt() {
        return $this->resvPrompt;
    }

    public function getPatAsGuestFlag() {
        return $this->patAsGuestFlag;
    }

    public function getPatBirthDateFlag() {
        return $this->patBirthDateFlag;
    }

    public function getShowBirthDate() {
        return $this->showBirthDate;
    }

    public function getPatLabel() {
        return $this->patLabel;
    }

    public function getWlNotesLabel() {
        return $this->wlNotesLabel;
    }

    public function getResvEarlyArrDays() {
        return $this->resvEarlyArrDays;
    }

    public function getPsgTitle() {
        return $this->psgTitle;
    }

    public function getForceNewPsg() {
        return $this->forceNewPsg;
    }

    public function getForceNewResv() {
        return $this->forceNewResv;
    }

    public function getAddrPurpose() {
        return $this->addrPurpose;
    }

    public function getArrivalDT() {
        return $this->arrivalDT;
    }

    public function getArrivalDateStr($format = ReserveData::DATE_FORMAT) {

        if ($this->arrivalDT !== NULL) {
            return $this->arrivalDT->format($format);
        }

        return '';
    }

    public function getDepartureDT() {
        return $this->departureDT;
    }

    public function getDepartureDateStr($format = ReserveData::DATE_FORMAT) {

        if ($this->departureDT !== NULL) {
            return $this->departureDT->format($format);
        }

        return '';
    }

    public function getPsgMembers() {
        return $this->psgMembers;
    }

    public function getPsgMember($prefix) {

        if (isset($this->psgMembers[$prefix])) {
            return $this->psgMembers[$prefix];
        }

        return NULL;
    }

    public function findMemberById($val) {

        foreach ($this->getPsgMembers() as $mem) {

            if ($mem->getId() == $val) {
                return $mem;
            }
        }

        return NULL;
    }

    public function findPatientMember() {

        foreach ($this->getPsgMembers() as $m) {
            if ($m->getRole() == VolMemberType::Patient) {
                return $m;
            }
        }

        // No Patient?
        return NULL;
    }

    public function findPrimaryGuestId() {

        foreach ($this->getPsgMembers() as $m) {
            if ($m->isPrimaryGuest()) {
                return $m->getId();
            }
        }

        // No one defined
        return NULL;
    }

    public function setMember(PSGMember $mem) {

        if ($mem->getPrefix() !== NULL && (String)$mem->getPrefix() != '') {
            $this->psgMembers[$mem->getPrefix()] = $mem;
        }

        return $this;
    }

    public function setPsgMembers($obj) {
        $this->psgMembers = $obj;
    }

    public function setIdResv($idResv) {
        if ($idResv < 0) {
            $this->idResv = 0;
            $this->forceNewResv = TRUE;
        } else {
            $this->idResv = $idResv;
            $this->forceNewResv = FALSE;
        }
        return $this;
    }

    public function setIdPsg($idPsg) {

        if($idPsg < 0) {
            $this->forceNewPsg = TRUE;
            $this->idPsg = 0;
        } else {
            $this->idPsg = $idPsg;
            $this->forceNewPsg = FALSE;
        }
        return $this;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setIdVisit($id) {
        $this->idVisit = $id;
        return $this;
    }

    public function setSpan($id) {
        $this->span = $id;
        return $this;
    }

    public function setSpanStatus($id) {
        $this->spanStatus = $id;
        return $this;
    }

    public function setSpanStartDT($id) {
        if ($id != '') {
            $this->spanStartDT = new DateTimeImmutable($id);
        } else {
            $this->spanStartDT = $this->getArrivalDT();
        }
        return $this;
    }

    public function setSpanEndDT($id) {
        if ($id != '') {
            $this->spanEndDT = new DateTimeImmutable($id);
        } else {
            $this->spanEndDT = $this->getDepartureDT();
        }
        return $this;
    }

    public function addConcurrentRooms($numberRooms) {
        $this->concurrentRooms += intval($numberRooms);
        return $this;
    }

    public function setSaveButtonLabel($label) {
        $this->saveButtonLabel = $label;
        return $this;
    }
    public function setIdHospital_Stay($id) {
        $this->idHospitalStay = $id;
        return $this;
    }

    public function setResvChooser($resvChooser) {
        $this->resvChooser = $resvChooser;
        return $this;
    }

    public function setPsgChooser($psgChooser) {
        $this->psgChooser = $psgChooser;
        return $this;
    }

    public function setAddPerson($p) {
        $this->addPerson = $p;
        return $this;
    }

    public function setFamilySection($p) {
        $this->familySection = $p;
        return $this;
    }

    public function setHospitalSection($p) {
        $this->hospitalSection = $p;
        return $this;
    }

    public function setExpectedDatesSection($p) {
        $this->expectedDatesSection = $p;
        return $this;
    }

    public function setResvSection($p) {
        $this->reservationSection = $p;
        return $this;
    }

    public function setCheckinSection($p) {
        $this->checkinSection = $p;
        return $this;
    }

    public function setArrivalDT($arrivalDT) {
        $this->arrivalDT = $arrivalDT;
        return $this;
    }

    public function setArrivalDateStr($strDate) {

        if ($strDate != '') {
            $this->setArrivalDT(new \DateTime($strDate));
        }
        return $this;
    }

    public function setDepartureDT($departureDate) {
        $this->departureDT = $departureDate;
        return $this;
    }

    public function setDepartureDateStr($strDate) {

        if ($strDate != '') {
            $this->setDepartureDT(new \DateTime($strDate));
        }
        return $this;
    }

    public function addError($e) {
        $this->errors .= $e;
    }

    public function hasError() {

        if ($this->errors != '') {
            return TRUE;
        }

        return FALSE;
    }

    public function getErrors() {
        return $this->errors;
    }

}


class PSGMember {

    protected $id;
    protected $prefix;
    protected $role;
    protected $primaryGuest;

    /**
     *
     * @var PSGMemStay
     */
    protected $memStay;

    public function __construct($id, $prefix, $role, $isPrimaryGuest, PSGMemStay $memStay) {

        $this->setId($id);
        $this->setPrefix($prefix);
        $this->setRole($role);
        $this->setPrimaryGuest($isPrimaryGuest);

        $this->memStay = $memStay;
    }

    public function createPrimaryGuestRadioBtn($prefix) {

        $rbPri = array(
            'type'=>'radio',
            'name'=>'rbPriGuest',
            'id'=>$prefix .'rbPri',
            'data-prefix'=>$prefix,
            'title'=>'Click to set this person as Primary Guest.',
            'style'=>'margin-left:5px;',
            'class'=>'hhk-rbPri'
        );

        if ($this->isPrimaryGuest()) {
            $rbPri['checked'] = 'checked';
        }

        return HTMLInput::generateMarkup($prefix, $rbPri);

    }


    public function getId() {
        return $this->id;
    }

    public function getPrefix() {
        return $this->prefix;
    }

    public function getRole() {
        return $this->role;
    }

    public function getStay() {
        return $this->memStay->getStay();
    }

    public function getStayObj() {
        return $this->memStay;
    }

    public function isStaying() {
        return $this->memStay->isStaying();
    }

    public function isBlocked() {
        return $this->memStay->isBlocked();
    }

    public function isPrimaryGuest() {
        return $this->primaryGuest;
    }

    public function isPatient() {
        if ($this->getRole() == VolMemberType::Patient) {
            return TRUE;
        }
        return FALSE;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function setPrefix($prefix) {
        $this->prefix = $prefix;
        return $this;
    }

    public function setRole($role) {
        $this->role = $role;
        return $this;
    }

    public function setPrimaryGuest($primaryGuest) {

        if ($primaryGuest == TRUE) {
            $this->primaryGuest = TRUE;
        } else {
            $this->primaryGuest = FALSE;
        }

        return $this;
    }

    public function setStay($stay) {
        $this->memStay->setStay($stay);
        return $this;
    }

    public function setStayObj(PSGMemStay $stay) {
        $this->memStay = $stay;
        return $this;
    }

    public function toArray() {

        return array(
            ReserveData::ID => $this->getId(),
            ReserveData::ROLE => $this->getRole(),
            ReserveData::STAY => $this->memStay->getStay(),
            ReserveData::PRI => ($this->isPrimaryGuest() ? '1' : '0'),
            ReserveData::PREF => $this->getPrefix(),
        );
    }

}


class PSGMemStay {

    protected $stay;
    protected $myStayType = 'open';

    public function __construct($stayIndex) {

        if ($stayIndex == ReserveData::STAYING || $stayIndex == ReserveData::NOT_STAYING || $stayIndex == ReserveData::CANT_STAY || $stayIndex == ReserveData::IN_ROOM) {
            $this->stay = $stayIndex;
        } else {
            $this->stay = ReserveData::NOT_STAYING;
        }
    }

    public function createStayButton($prefix) {


        $cbStay = array(
            'type'=>'checkbox',
            'name'=>$prefix .'cbStay',
            'id'=>$prefix .'cbStay',
            'data-prefix'=>$prefix,
            'class' => 'hhk-cbStay',
        );

        $lblStay = array(
            'for'=>$prefix . 'cbStay',
            'id' => $prefix . 'lblStay',
            'data-stay' => $this->getStay(),
            'class' => 'hhk-lblStay hhk-stayIndicate',
        );


        return HTMLContainer::generateMarkup('label', 'Stay', $lblStay)
                . HTMLInput::generateMarkup('', $cbStay);

    }

    public function isStaying() {
        if ($this->getStay() == ReserveData::STAYING) {
            return TRUE;
        }
        return FALSE;
    }

    public function isBlocked() {
        if ($this->getStay() == ReserveData::CANT_STAY) {
            return TRUE;
        }
        return FALSE;
    }

    public function getStay() {
        return $this->stay;
    }

    public function setStay($s) {
        $this->stay = $s;
    }

    public function setBlocked() {
        $this->stay = ReserveData::CANT_STAY;
    }

    public function setNotStaying() {
        $this->stay = ReserveData::NOT_STAYING;
    }

    public function setStaying() {
        if ($this->isBlocked() === FALSE) {
            $this->stay = ReserveData::STAYING;
        }
    }

    public function getMyStayType() {
        return $this->myStayType;
    }

}

class PSGMemVisit extends PSGMemStay {

    protected $index = array();
    protected $myStayType = 'visit';

    public function __construct($index) {

        parent::__construct(ReserveData::NOT_STAYING);

        $this->index = $index;
        $this->setNotStaying();
    }

    public function createStayButton($prefix) {

        if (isset($this->index['idVisit']) && isset($this->index['Visit_Span'])) {
            $stIcon = '';

            if ($this->index['status'] == VisitStatus::CheckedOut) {
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-extlink', 'style'=>'float: right; margin-right:.3em;', 'title'=>'Checked Out'));
            } else if ($this->index['status'] == VisitStatus::ChangeRate) {
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-tag', 'style'=>'float: right; margin-right:.3em;', 'title'=>'Changed Room Rate'));
            } else if ($this->index['status'] == VisitStatus::NewSpan) {
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-newwin', 'style'=>'float: right; margin-right:.3em;', 'title'=>'Changed Rooms'));
            }

            return HTMLInput::generateMarkup($this->index['room'], array('type'=>'button', 'class'=>'hhk-getVDialog hhk-stayIndicate', 'data-vid'=>$this->index['idVisit'], 'data-span'=>$this->index['Visit_Span'])) . $stIcon;

        } else {
            $this->setStay(ReserveData::IN_ROOM);
            return HTMLContainer::generateMarkup('span', 'In Room', array('class'=>'hhk-stayIndicate'));
        }
    }

}

class PSGMemResv extends PSGMemVisit {

    protected $myStayType = 'resv';

    public function createStayButton($prefix) {

        if (isset($this->index['idReservation']) && isset($this->index['idPsg'])) {
            return HTMLContainer::generateMarkup('a', (isset($this->index['label']) ? $this->index['label'] : 'Reservation')
                , array('href'=>'Reserve.php?idPsg=' . $this->index['idPsg'] . '&rid=' . $this->index['idReservation'] . '&id=' . $this->index['idGuest'], 'class'=>'hhk-stayIndicate'));
        } else {
            return HTMLContainer::generateMarkup('span', $this->index['label'], array('class'=>'hhk-stayIndicate'));
        }
    }
}
