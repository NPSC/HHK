<?php

/**
 * Description of Family
 *
 * @author Eric
 */
class Family {

    const FAM_TABLE_ID = 'tblFamily';

    protected $roleObjs;
    protected $rData;
    protected $patientId;
    protected $patientPrefix;
    protected $hospStay;

    public function __construct(ReserveData $rData) {

        $this->rData = $rData;
        $this->patientId = 0;
        $this->patientPrefix = 0;

        $uS = Session::getInstance();

        // Prefix
        if (isset($uS->addPerPrefix) === FALSE) {
            $uS->addPerPrefix = 1;
        }

    }

    public function initMembers(\PDO $dbh) {

        $uS = Session::getInstance();

        // Load any existing PSG members.
        if ($this->rData->getIdPsg() > 0) {

            // PSG is defined
            $ngRs = new Name_GuestRS();
            $ngRs->idPsg->setStoredVal($this->rData->getIdPsg());
            $rows = EditRS::select($dbh, $ngRs, array($ngRs->idPsg));
            $target = FALSE;

            foreach ($rows as $r) {

                $ngrs = new Name_GuestRS();
                EditRS::loadRow($r, $ngrs);
                $uS->addPerPrefix++;

                // Set target prefix if found.
                if ($ngrs->idName->getStoredVal() == $this->rData->getId()) {
                    $target = TRUE;
                }

                if ($ngrs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                    // patient
                    $this->roleObjs[$uS->addPerPrefix] = new Patient($dbh, $uS->addPerPrefix, $ngrs->idName->getStoredVal(), $this->rData->getPatLabel());
                    $this->roleObjs[$uS->addPerPrefix]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());

                    if ($uS->PatientAsGuest && $this->roleObjs[$uS->addPerPrefix]->getNoReturn() == '') {
                        $staying = ReserveData::NOT_STAYING;
                    } else {
                        $staying = ReserveData::CANT_STAY;
                    }

                    $psgMember = new PSGMember($ngrs->idName->getStoredVal(), $uS->addPerPrefix, VolMemberType::Patient, $staying);
                    $this->rData->setMember($psgMember);

                    $this->patientId = $ngrs->idName->getStoredVal();
                    $this->patientPrefix = $uS->addPerPrefix;

                } else {
                    // guest
                    $this->roleObjs[$uS->addPerPrefix] = new Guest($dbh, $uS->addPerPrefix, $ngrs->idName->getStoredVal());
                    $this->roleObjs[$uS->addPerPrefix]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());

                    if ($this->roleObjs[$uS->addPerPrefix]->getNoReturn() == '') {
                        $staying = ReserveData::NOT_STAYING;
                    } else {
                        $staying = ReserveData::CANT_STAY;
                    }

                    $psgMember = new PSGMember($ngrs->idName->getStoredVal(), $uS->addPerPrefix, VolMemberType::Guest, $staying);
                    $this->rData->setMember($psgMember);
                }
            }

            // Load new existing member to existing PSG?
            if ($this->rData->getId() > 0 && !$target) {

                $uS->addPerPrefix++;

                $this->roleObjs[$uS->addPerPrefix] = new Guest($dbh, $uS->addPerPrefix, $this->rData->getId());

                $psgMember = new PSGMember($this->rData->getId(), $uS->addPerPrefix, VolMemberType::Guest, ($this->roleObjs[$uS->addPerPrefix]->getNoReturn() == '' ? ReserveData::NOT_STAYING : ReserveData::CANT_STAY));
                $this->rData->setMember($psgMember);
            }

        // Flag for new PSG for existing guest
        } else if ($this->rData->getForceNewPsg() && $this->rData->getId() > 0) {

            // forced New PSG
            $uS->addPerPrefix++;

            $this->roleObjs[$uS->addPerPrefix] = new Guest($dbh, $uS->addPerPrefix, $this->rData->getId());

            $psgMember = new PSGMember($this->rData->getId(), $uS->addPerPrefix, '', ($this->roleObjs[$uS->addPerPrefix]->getNoReturn() == '' ? ReserveData::NOT_STAYING : ReserveData::CANT_STAY));
            $this->rData->setMember($psgMember);

        // Add existing member to New PSG
        } else if ($this->rData->getIdPsg() == 0 && $this->rData->getId() > 0) {

            // Add existing member to New PSG
            $uS->addPerPrefix++;

            $this->roleObjs[$uS->addPerPrefix] = new Guest($dbh, $uS->addPerPrefix, $this->rData->getId());

            $psgMember = new PSGMember($this->rData->getId(), $uS->addPerPrefix, '', ($this->roleObjs[$uS->addPerPrefix]->getNoReturn() == '' ? ReserveData::NOT_STAYING : ReserveData::CANT_STAY));
            $this->rData->setMember($psgMember);

        }


        // Load empty member?
        if ($this->rData->getId() === 0) {

            $uS->addPerPrefix++;

            $this->roleObjs[$uS->addPerPrefix] = new Guest($dbh, $uS->addPerPrefix, 0);

            $psgMember = new PSGMember(0, $uS->addPerPrefix, '', '0');
            $this->rData->setMember($psgMember);
        }


        // Update who is staying
        $this->setGuestsStaying($dbh);
    }

    protected function setGuestsStaying(\PDO $dbh) {

        if ($this->rData->getIdResv() > 0) {

            // Existing reservation...
            $resvGuestRs = new Reservation_GuestRS();
            $resvGuestRs->idReservation->setStoredVal($this->rData->getIdResv());
            $rgs = EditRS::select($dbh, $resvGuestRs, array($resvGuestRs->idReservation));

            foreach ($rgs as $g) {

                $mem = $this->rData->findMemberById($g['idGuest']);

                if ($mem !== NULL && $mem->getStay() !== 'x') {
                    $mem->setStay('1');
                }
            }

        } else {

            // New reservation, so set the stay for the guests.
            $mems = $this->rData->getPsgMembers();

            foreach ($mems as $mem) {

                if ($mem !== NULL && $mem->getStay() !== 'x') {
                    $mem->setStay('1');
                }
            }
        }
    }

    protected function getAddresses($roles) {

        $addrs = array();

        foreach ($roles as $role) {

            $addrObj = $role->getAddrObj();
            $addr = $addrObj->get_Data(Address_Purpose::Home);

            $addr['Purpose'] = Address_Purpose::Home;

            $addr['Email'] = $role->getEmailsObj()->get_Data(Email_Purpose::Home)['Email'];
            $addr['idName'] = $role->getIdName();
            $addr['pref'] = $role->getRoleMember()->getIdPrefix();

            $addrs[$role->getRoleMember()->getIdPrefix()] = $addr;

        }

        return $addrs;
    }

    public function CreateAddPersonMu(\PDO $dbh) {

        $uS = Session::getInstance();

        $addPerson = array();

        foreach ($this->roleObjs as $prefix => $role) {

            if ($role->getIdName() != $this->rData->getId()) {
                continue;
            }

            $nameTr = HTMLContainer::generateMarkup('tr'
                    , $role->createThinMarkup($this->rData->getPsgMember($prefix)->getStay(), ($this->rData->getIdPsg() == 0 ? FALSE : TRUE))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('Remove', array('type'=>'button', 'id'=>$prefix.'btnRemove'))));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $addressTr = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>'hhk-addrRow'));

            $mem = $this->rData->getPsgMember($prefix)->toArray();
            $adr = $this->getAddresses(array($role));

            $addPerson = array('id'=>$this->rData->getId(), 'ntr'=>$nameTr, 'atr'=>$addressTr, 'tblId'=>FAMILY::FAM_TABLE_ID, 'mem'=>$mem, 'addrs'=>$adr[$prefix]);
        }

        return $addPerson;

    }

    public function createFamilyMarkup(\PDO $dbh, ReservationRS $resvRs) {

        $uS = Session::getInstance();
        //$tbl = new HTMLTable();
        $rowClass = 'odd';
        $mk1 = '';
        $trs = array();


        $th = HTMLContainer::generateMarkup('tr',
                HTMLTable::makeTh('Staying')
                . RoleMember::createThinMarkupHdr($this->rData->getPatLabel(), FALSE, $this->rData->getPatBirthDateFlag())
                . HTMLTable::makeTh('Phone')
                . HTMLTable::makeTh('Addr'));


        // Put the patient first.
        if ($this->patientPrefix > 0) {

            $role = $this->roleObjs[$this->patientPrefix];
            $idPrefix = $role->getRoleMember()->getIdPrefix();

            $isStay = $this->rData->getPsgMember($idPrefix)->getStay();

            $trs[] = HTMLContainer::generateMarkup('tr',
                    $role->createThinMarkup($this->rData->getPsgMember($idPrefix)->getStay(), TRUE)
                    , array('class'=>$rowClass));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            if ($uS->PatientAddr) {
                $trs[] = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>$rowClass . ' hhk-addrRow'));
            }

        }

        // List each member
        foreach ($this->roleObjs as $role) {

            // Skip the patient who was taken care of above
            if ($role->getIdName() > 0 && $role->getIdName() == $this->getPatientId()) {
                continue;
            }

            $idPrefix = $role->getRoleMember()->getIdPrefix();

            if ($rowClass == 'odd') {
                $rowClass = 'even';
            } else if ($rowClass == 'even') {
                $rowClass = 'odd';
            }

            $trs[] = HTMLContainer::generateMarkup('tr', $role->createThinMarkup($this->rData->getPsgMember($idPrefix)->getStay(), ($this->rData->getIdPsg() == 0 ? FALSE : TRUE)), array('class'=>$rowClass));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $trs[] = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>$rowClass . ' hhk-addrRow'));
        }

        // Guest search
        $mk1 .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Add people - Name Search: ')
                .HTMLInput::generateMarkup('', array('id'=>'txtPersonSearch', 'title'=>'Enter the first three characters of the person\'s last name'))
                , array('id'=>'divPersonSearch', 'style'=>'margin-top:10px;'));


        // Waitlist notes?
        if ($uS->UseWLnotes) {

            $mk1 .=
                    HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->rData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resvRs->Checkin_Notes->getStoredVal(), array('name'=>'taCkinNotes', 'rows'=>'2', 'style'=>'width:100%')),
                array('class'=>'hhk-panel', 'style'=>'margin-top:10px; font-size:.9em;'));
        }

        // Header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Family ')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        return array('hdr'=>$hdr, 'tblHead'=>$th, 'tblBody'=>$trs, 'adtnl'=>$mk1, 'mem'=>$this->rData->getMembersArray(), 'addrs'=>$this->getAddresses($this->roleObjs), 'tblId'=>FAMILY::FAM_TABLE_ID);

    }

    protected function getDemographicsMarkup(\PDO $dbh, $role) {

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Demographics', array('style'=>'font-weight:bold;'))
                . $role->getRoleMember()->createDemographicsPanel($dbh, TRUE, FALSE), array('class'=>'hhk-panel')),
            array('style'=>'float:left; margin-right:3px;'));

    }

    public function save(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        // Open Psg
        $psg = new Psg($dbh, $this->rData->getIdPsg());
        $idPatient = 0;

        // Save Members
        foreach ($this->rData->getPsgMembers() as $m) {

            if ($m->getId() < 0) {
                continue;
            }

            $role = new Guest($dbh, $m->getPrefix(), $m->getId());
            $role->save($dbh, $post, $uS->username);

            // Patient?
            if ($m->getRole() == 'p') {
                $idPatient = $role->getIdName();
            }

            $psg->setNewMember($role->getIdName(), $role->getPatientRelationshipCode());
        }

        // Save PSG
        $psg->savePSG($dbh, $idPatient, $uS->username);
        $this->rData->setIdPsg($psg->getIdPsg());

        if ($psg->getIdPsg() > 0 && $idPatient > 0) {

            // Save Hospital
            $this->hospStay = new HospitalStay($dbh, $psg->getIdPatient());
            Hospital::saveReferralMarkup($dbh, $psg, $this->hospStay, $post);

        }

        return $this->rData;
    }

    public function getPatientId() {
        return $this->patientId;
    }

    public function getHospStay() {
        return $this->hospStay;
    }


}
