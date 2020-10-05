<?php

namespace HHK\House\ReserveData;

use HHK\Config_Lite\Config_Lite;
use HHK\House\ReserveData\PSGMember\{PSGMember, PSGMemStay};
use HHK\SysConst\VolMemberType;
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
    protected $insistCkinDemog;
    protected $searchTerm;

    function __construct($post, $reservationTitle = '') {

        $uS = Session::getInstance();
        $labels = Labels::getLabels();
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
        
        if (isset($post['schTerm'])) {
        	$this->setSearchTerm(filter_var($post['schTerm'], FILTER_SANITIZE_STRING));
        }
        
        if (isset($post['mem'])) {
            $this->setMembersFromPost(filter_var_array($post['mem'], FILTER_SANITIZE_STRING));
        }

        $this->saveButtonLabel = 'Save ';
        $this->resvEarlyArrDays = $uS->ResvEarlyArrDays;
        $this->patAsGuestFlag = $uS->PatientAsGuest;
        $this->patBirthDateFlag = $uS->InsistPatBD;
        $this->showBirthDate = $uS->ShowBirthDate;
        $this->insistCkinDemog = FALSE;
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
        	'insistCkinDemog' => $this->insistCkinDemog,
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
    
    public function getFullName() {
    	return $this->fullName;
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
    
    public function setInsistCkinDemog($id) {
    	$this->insistCkinDemog = $id;
    	return $this;
    }
    
    public function setSpanStartDT($id) {
        if ($id != '') {
            $this->spanStartDT = new \DateTimeImmutable($id);
        } else {
            $this->spanStartDT = $this->getArrivalDT();
        }
        return $this;
    }

    public function setSpanEndDT($id) {
        if ($id != '') {
            $this->spanEndDT = new \DateTimeImmutable($id);
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
?>