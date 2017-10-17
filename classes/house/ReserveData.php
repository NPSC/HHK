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
    const FULL_NAME = 'fullName';

    const ROLE = 'role';
    const PREF = 'pref';
    const STAY = 'stay';
    const ID = 'id';

    const STAYING = '1';
    const NOT_STAYING = '0';
    const CANT_STAY = 'x';

    protected $idResv = 0;
    protected $id;
    protected $idPsg = 0;
    protected $idHospitalStay = 0;
    protected $forceNewPsg = FALSE;
    protected $forceNewResv = FALSE;
    protected $fullName = '';
    protected $resvTitle;
    protected $patAsGuestFlag;
    protected $patBirthDateFlag;
    protected $patLabel;
    protected $wlNotesLabel;
    protected $addrPurpose;
    protected $resvEarlyArrDays;
    protected $psgTitle;
    protected $resvChooser;
    protected $psgChooser;
    protected $familySection;
    protected $arrivalDateStr;
    protected $departureDateStr;
    protected $psgMembers;

    function __construct($post) {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);
        $this->psgMembers = array();

        if (isset($post['rid'])) {
            $this->setIdResv(intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10));
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

        $this->resvTitle = $labels->getString('guestEdit', 'reservationTitle', 'Reservation');
        $this->resvEarlyArrDays = $uS->ResvEarlyArrDays;
        $this->patAsGuestFlag = $uS->PatientAsGuest;
        $this->patBirthDateFlag = $uS->PatientBirthDate;
        $this->patLabel = $labels->getString('MemberType', 'patient', 'Patient');
        $this->psgTitle = $labels->getString('statement', 'psgLabel', 'Patient Support Group');
        $this->wlNotesLabel = $labels->getString('referral', 'waitlistNotesLabel', 'Waitlist Notes');
        $this->addrPurpose = '1';
        $this->resvChooser = '';
        $this->psgChooser = '';
        $this->familySection = '';

    }

    protected function setMembersFromPost($postMems) {

        foreach ($postMems as $prefix => $memArray) {

            if ($prefix == '') {
                continue;
            }

            $id = 0;
            $role = '';
            $stay = '0';

            if (isset($memArray[ReserveData::ID])) {
                $id = intval($memArray[ReserveData::ID], 10);
            }
            if (isset($memArray[ReserveData::ROLE])) {
                $role = $memArray[ReserveData::ROLE];
            }
            if (isset($memArray[ReserveData::STAY])) {
                $stay = $memArray[ReserveData::STAY];
            }

            $psgMember = $this->getPsgMember($prefix);

            if (is_null($psgMember)) {
                $this->setMember(new PSGMember($id, $prefix, $role, $stay));
            } else {
                $psgMember->setStay($stay)->setRole($role);
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
            'patLabel' => $this->getPatLabel(),
            'resvTitle' => $this->getResvTitle(),
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

        if ($this->fullName != '') {
            $rtnData[ReserveData::FULL_NAME] = $this->fullName;
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

    public function getResvTitle() {
        return $this->resvTitle;
    }

    public function getPatAsGuestFlag() {
        return $this->patAsGuestFlag;
    }

    public function getPatBirthDateFlag() {
        return $this->patBirthDateFlag;
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

    public function getArrivalDateStr() {
        return $this->arrivalDateStr;
    }

    public function getDepartureDateStr() {
        return $this->departureDateStr;
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

            if ($mem->getId() === $val) {
                return $mem;
            }
        }

        return NULL;
    }

    public function setMember(PSGMember $mem) {

        if ($mem->getPrefix() !== NULL && (String)$mem->getPrefix() != '') {
            $this->psgMembers[$mem->getPrefix()] = $mem;
        }

        return $this;
    }

    public function setMembersObj($obj) {
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

    public function setFamilySection($p) {
        $this->familySection = $p;
        return $this;
    }

    public function setArrivalDateStr($arrivalDateStr) {
        $this->arrivalDateStr = $arrivalDateStr;
        return $this;
    }

    public function setDepartureDateStr($departureDateStr) {
        $this->departureDateStr = $departureDateStr;
        return $this;
    }

}

class PSGMember {

    protected $id;
    protected $prefix;
    protected $role;

    /**
     *
     * @var iPSGMemStay
     */
    protected $stay;

    public function __construct($id, $prefix, $role, $stay) {

        $this->setId($id);
        $this->setPrefix($prefix);
        $this->setRole($role);
        $this->stay = new PSGMemStay($stay);
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
        return $this->stay->getStay();
    }

    public function getStayObj() {
        return $this->stay;
    }

    public function isStaying() {
        return $this->stay->isStaying();
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

    public function isPatient() {
        if ($this->getRole() == VolMemberType::Patient) {
            return TRUE;
        }
        return FALSE;
    }

    public function setStay($stay) {
        $this->stay->setStay($stay);
        return $this;
    }

    public function setStayObj(PSGMemStay $stay) {
        $this->stay = $stay;
        return $this;
    }

    public function toArray() {

        return array(
            ReserveData::ID => $this->getId(),
            ReserveData::ROLE => $this->getRole(),
            ReserveData::STAY => $this->stay->getStay(),
            ReserveData::PREF => $this->getPrefix(),
        );
    }

}

class PSGMemStay {

    protected $stay;
    protected $index;

    public function __construct($stay) {

        if ($stay == ReserveData::STAYING || $stay == ReserveData::NOT_STAYING || $stay == ReserveData::CANT_STAY) {

            $this->stay = $stay;

        } else {
            $this->stay = ReserveData::NOT_STAYING;
        }

    }

    public function createMarkup($prefix, $isPrimaryGuest = FALSE) {

        if ($this->isBlocked()) {

            // This person cannot stay
            return '';

        } else {

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
                'class' => 'hhk-lblStay',
            );

            $rbPri = array(
                'type'=>'radio',
                'name'=>'rbPriGuest',
                'id'=>$prefix .'rbPri',
                'data-prefix'=>$prefix,
                'title'=>'Click to set this person as Primary Guest.',
                'style'=>'margin-left:5px;'
            );

            if ($isPrimaryGuest) {
                $rbPri['checked'] = 'checked';
            }

            return HTMLContainer::generateMarkup('label', 'Stay', $lblStay)
                    . HTMLInput::generateMarkup('', $cbStay)
                    . HTMLInput::generateMarkup('', $rbPri);
        }
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

    public function getIndex() {
        return $this->index;
    }

    public function setIndex($s) {
        $this->index = $s;
    }

}

class PSGMemVisit extends PSGMemStay {

    public function __construct($index) {

        $this->setIndex($index);
        $this->setBlocked();
    }

    public function createMarkup($prefix, $isPrimaryGuest = FALSE) {

        return HTMLContainer::generateMarkup('a', 'Visit', array('href'=>'whatever'));
    }

}

class PSGMemResv extends PSGMemVisit {

    public function createMarkup($prefix, $isPrimaryGuest = FALSE) {

        return HTMLContainer::generateMarkup('a', 'Resv', array('href'=>'whatever'));
    }
}
