<?php

namespace HHK\House\ReserveData;

use HHK\House\ReserveData\PSGMember\{PSGMember, PSGMemStay};
use HHK\SysConst\{VolMemberType, ReservationStatusType};
use HHK\sec\Labels;
use HHK\sec\Session;

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
    const SUCCESS = 'success';
    const INFO = 'info';

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
    protected $idReferralDoc = 0;
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
    protected $guestBirthDateFlag;
    protected $useRepeatingResv;
    protected $showBirthDate;
    protected $patLabel;
    protected $primaryGuestLabel;
    protected $guestLabel;
    protected $visitorLabel;
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
    protected $msgs;
    protected $resvPrompt;
    protected $insistCkinDemog;
    protected $insistCkinPhone;
    protected $insistCkinEmail;
    protected $insistCkinAddress;
    protected $searchTerm;
    protected $resvStatusCode;
    protected $resvStatusType;
    protected $hasMOA;
    protected $prePayment = 0;
    protected $deleteChildReservations = FALSE;
    protected $intervalRepeatResv = 0;
    protected $numberRepeatResv = 0;

    protected $rawPost;

    public function __construct($rawPost, $reservationTitle = '') {

        $uS = Session::getInstance();
        $labels = Labels::getLabels();
        $this->psgMembers = [];
        $this->rawPost = $rawPost;


        $args = [
            'rid' => FILTER_SANITIZE_NUMBER_INT,
            'vid' => FILTER_SANITIZE_NUMBER_INT,
            'span' => FILTER_SANITIZE_NUMBER_INT,
            'id' => FILTER_SANITIZE_NUMBER_INT,
            'idPsg' => FILTER_SANITIZE_NUMBER_INT,
            'vstatus' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'fullName' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'gstDate' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'gstCoDate' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'schTerm' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'prePayment' => FILTER_SANITIZE_NUMBER_FLOAT,
            'deleteChilden' => FILTER_VALIDATE_BOOLEAN,
            'mem' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],

            'mrInterval' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY
            ],
            'mrnumresv' => FILTER_SANITIZE_NUMBER_INT,
        ];

        $inputs = filter_var_array($rawPost, $args);

        if (isset($inputs['rid'])) {
            $this->setIdResv(intval($inputs['rid'], 10));
        }

        if (isset($inputs['vid'])) {
            $this->setIdVisit(intval($inputs['vid'], 10));
        }

        if (isset($inputs['span'])) {
            $this->setSpan(intval($inputs['span'], 10));
        }

        if (isset($inputs['vstatus'])) {
            $this->setSpanStatus($inputs['vstatus']);
        }

        if (isset($inputs['id'])) {
            $this->setId(intval($inputs['id'], 10));
        }

        if (isset($inputs['idPsg'])) {
            $this->setIdPsg(intval($inputs['idPsg'], 10));
        }

        if (isset($inputs['fullName'])) {
            $this->fullName = $inputs['fullName'];
        }

        if (isset($inputs['gstDate'])) {
            $this->setArrivalDateStr($inputs['gstDate']);
        }

        if (isset($inputs['gstCoDate'])) {
        	$this->setDepartureDateStr($inputs['gstCoDate']);
        }

        if (isset($inputs['schTerm'])) {
        	$this->setSearchTerm($inputs['schTerm']);
        }

        if (isset($inputs['mem'])) {
            $this->setMembersFromPost($inputs['mem']);
        }

        if (isset($inputs['prePayment'])) {
            $this->setPrePayment($inputs['prePayment']);
        }

        if (isset($inputs['deleteChilden'])) {
            $this->setDeleteChildReservations($inputs['deleteChilden']);
        }

        if (isset($inputs['mrnumresv']) && isset($inputs['mrInterval'])) {
            $this->numberRepeatResv = intval($inputs['mrnumresv'], 10);
            $this->intervalRepeatResv = $inputs['mrInterval'];
        }

        $this->saveButtonLabel = 'Save ';
        $this->resvEarlyArrDays = $uS->ResvEarlyArrDays;
        $this->patAsGuestFlag = $uS->PatientAsGuest;
        $this->patBirthDateFlag = $uS->InsistPatBD;
        $this->guestBirthDateFlag = $uS->InsistGuestBD;
        $this->showBirthDate = $uS->ShowBirthDate;
        $this->useRepeatingResv = $uS->UseRepeatResv;
        $this->insistCkinDemog = FALSE;
        $this->insistCkinPhone = FALSE;
        $this->insistCkinEmail = FALSE;
        $this->insistCkinAddress = FALSE;
        $this->fillEmergencyContact = isset($uS->EmergContactFill) ? $uS->EmergContactFill : 'false';
        $this->patLabel = $labels->getString('MemberType', 'patient', 'Patient');
        $this->guestLabel = $labels->getString('MemberType', 'guest', 'Guest');
        $this->primaryGuestLabel = $labels->getString('MemberType', 'primaryGuest', 'Primary Guest');
        $this->visitorLabel = $labels->getString('MemberType', 'visitor', 'Guest');
        $this->psgTitle = $labels->getString('statement', 'psgLabel', 'Patient Support Group');
        $this->wlNotesLabel = $labels->getString('referral', 'waitlistNotesLabel', 'Waitlist Notes');
        $this->addrPurpose = '1';
        $this->resvChooser = '';
        $this->psgChooser = '';
        $this->familySection = '';
        $this->expectedDatesSection = '';
        $this->hospitalSection = '';
        $this->reservationSection = '';
        $this->checkinSection = '';
        $this->paymentSection = '';
        $this->errors = '';
        $this->msgs = '';
        $this->resvPrompt = $labels->getString('guestEdit', 'reservationTitle', 'Reservation');
        $this->resvTitle = ($reservationTitle == '' ? $this->resvPrompt : $reservationTitle);
        $this->resvStatusCode = '';
        $this->hasMOA = FALSE;


    }

    /**
     * Summary of setMembersFromPost
     * @param array $postMems
     * @return void
     */
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

    /**
     * Summary of getMembersArray
     * @return array
     */
    public function getMembersArray() {

        $memArray = array();

        foreach ($this->getPsgMembers() as $mem) {
            $memArray[$mem->getPrefix()] = $mem->toArray();
        }

        return $memArray;
    }

    /**
     * Summary of toArray
     * @return array
     */
    public function toArray() {

        $rtnData =  array(
            'id' => $this->getId(),
            'rid' => $this->getIdResv(),
            'idPsg' => $this->getIdPsg(),
            'vid' => $this->getIdVisit(),
            'span'=> $this->getSpan(),
            'patLabel' => $this->getPatLabel(),
            'guestLabel' => $this->guestLabel,
            'primaryGuestLabel' => $this->primaryGuestLabel,
            'visitorLabel' => $this->visitorLabel,
            'resvTitle' => $this->getResvTitle(),
            'saveButtonLabel' => $this->saveButtonLabel,
        	'insistCkinDemog' => $this->insistCkinDemog,
            'resvStatusCode' => $this->getResvStatusCode(),
            'resvStatusType' => $this->getResvStatusType(),
            'hasMOA' => $this->getHasMOA(),
            'prePayment' => $this->getPrePayment(),
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

        if ($this->msgs != '') {
            $rtnData[ReserveData::INFO] = $this->msgs;
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

    public function getIdReferralDoc() {
        return $this->idReferralDoc;
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

    public function getGuestBirthDateFlag() {
        return $this->guestBirthDateFlag;
    }

    public function getShowBirthDate() {
        return $this->showBirthDate;
    }

    public function getPatLabel() {
        return $this->patLabel;
    }

    public function getResvStatusCode() {
        return $this->resvStatusCode;
    }

    public function getResvStatusType() {
        return $this->resvStatusType;
    }

    public function getWlNotesLabel() {
        return $this->wlNotesLabel;
    }

    public function getSearchTerm($p) {
    	 return $this->searchTerm;
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

    /**
     * Summary of getArrivalDT
     * @return \DateTime
     */
    public function getArrivalDT() {
        return $this->arrivalDT;
    }

    public function getArrivalDateStr($format = ReserveData::DATE_FORMAT) {

        if ($this->arrivalDT !== NULL) {
            return $this->arrivalDT->format($format);
        }

        return '';
    }

    /**
     * Summary of getDepartureDT
     * @return \DateTime
     */
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

    public function getFullName() {
        return $this->fullName;
    }

    public function getPrePayment()
    {
        return $this->prePayment;
    }

    public function getDeleteChildReservations()
    {
        return $this->deleteChildReservations;
    }

    public function getHasMOA() {
        return $this->hasMOA;
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

    public function setDeleteChildReservations($v) {
        $this->deleteChildReservations = ($v === true) ? true : false;
        return $this;
    }



    public function setPrePayment($prepayment) {
        $this->prePayment = $prepayment;
        return $this;
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

    public function setIdReferralDoc($id) {
        $this->idReferralDoc = $id;
        return $this;
    }

    public function setSpan($id) {
        $this->span = $id;
        return $this;
    }

    public function setResvStatusType($resvStatusType) {
        $this->resvStatusType = $resvStatusType;
        return $this;
    }

    public function setResvStatusCode($resvStatusCode) {
        $this->resvStatusCode = $resvStatusCode;
        return $this;
    }

    public function setSpanStatus($id) {
    	$this->spanStatus = $id;
    	return $this;
    }

    public function setInsistCkinDemog($id) {
        $this->insistCkinDemog = $id;
        return $this;
    }

    public function setInsistCkinPhone($id) {
        $this->insistCkinPhone = $id;
        return $this;
    }

    public function setInsistCkinEmail($id) {
        $this->insistCkinEmail = $id;
        return $this;
    }

    public function setInsistCkinAddress($id) {
        $this->insistCkinAddress = $id;
        return $this;
    }

    public function setHasMOA($bol) {
        $this->hasMOA = $bol;
        return $this;
    }

    public function setSpanStartDT($strDate) {
        if ($strDate != '') {
            $this->spanStartDT = new \DateTimeImmutable($strDate);
        } else {
            $this->spanStartDT = $this->getArrivalDT();
        }
        return $this;
    }

    public function setSpanEndDT($strDate) {
        if ($strDate != '') {
            $this->spanEndDT = new \DateTimeImmutable($strDate);
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

    public function setSearchTerm($p) {
    	$this->searchTerm = $p;
    	return $this;
    }

    public function setArrivalDT(\DateTimeInterface $arrivalDT) {
        $this->arrivalDT = $arrivalDT;
        return $this;
    }

    public function setArrivalDateStr($strDate) {

        if ($strDate != '') {
            $this->setArrivalDT(new \DateTime($strDate));
        }
        return $this;
    }

    public function setDepartureDT(\DateTimeInterface $departureDate) {
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

    public function addMsg($e) {
        $this->msgs .= $e;
    }

    public function hasMsg() {

        if ($this->msgs != '') {
            return TRUE;
        }

        return FALSE;
    }

    public function getMsgs() {
        return $this->msgs;
    }

    public function getRawPost() {
        return $this->rawPost;
    }

}