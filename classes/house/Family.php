<?php

/**
 * Description of Family
 *
 * @author Eric
 */
class Family {

    const FAM_TABLE_ID = 'tblFamily';

    protected $roleObjs;
    protected $patientId;
    protected $patientPrefix;
    protected $hospStay;

    public function __construct(\PDO $dbh, &$rData) {

        //$this->rData = $rData;
        $this->patientId = 0;
        $this->patientPrefix = 0;

        $uS = Session::getInstance();

        // Prefix
        if (isset($uS->addPerPrefix) === FALSE) {
            $uS->addPerPrefix = 1;
        }

        $this->initMembers($dbh, $rData);

    }

    protected function initMembers(\PDO $dbh, ReserveData &$rData) {

        $uS = Session::getInstance();

        // Load any existing PSG members.
        if ($rData->getIdPsg() > 0) {

            // PSG is defined
            $ngRs = new Name_GuestRS();
            $ngRs->idPsg->setStoredVal($rData->getIdPsg());
            $rows = EditRS::select($dbh, $ngRs, array($ngRs->idPsg));
            $target = FALSE;

            foreach ($rows as $r) {

                $ngrs = new Name_GuestRS();
                EditRS::loadRow($r, $ngrs);

                // Set target prefix if found.
                if ($ngrs->idName->getStoredVal() == $rData->getId()) {
                    $target = TRUE;
                }

                $psgMember = $rData->findMemberById($ngrs->idName->getStoredVal());

                if ($psgMember != NULL) {
                    $prefix = $psgMember->getPrefix();
                } else {
                    $prefix = $uS->addPerPrefix++;
                    $psgMember = new PSGMember($ngrs->idName->getStoredVal(), $prefix, '', new PSGMemStay(ReserveData::NOT_STAYING));
                }

                if ($ngrs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                    // patient
                    $this->roleObjs[$prefix] = new Patient($dbh, $prefix, $ngrs->idName->getStoredVal(), $rData->getPatLabel());
                    $this->roleObjs[$prefix]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());

                    if ($uS->PatientAsGuest && $this->roleObjs[$prefix]->getNoReturn() != '') {
                        $psgMember->setStay(ReserveData::CANT_STAY);
                    }

                    $psgMember->setRole(VolMemberType::Patient);
                    $rData->setMember($psgMember);

                    $this->patientId = $ngrs->idName->getStoredVal();
                    $this->patientPrefix = $prefix;

                } else {
                    // guest
                    $this->roleObjs[$prefix] = new Guest($dbh, $prefix, $ngrs->idName->getStoredVal());
                    $this->roleObjs[$prefix]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());

                    if ($this->roleObjs[$prefix]->getNoReturn() != '') {
                        $psgMember->setStay(ReserveData::CANT_STAY);
                    }

                    $psgMember->setRole(VolMemberType::Guest);
                    $rData->setMember($psgMember);
                }
            }

            // Load new existing member to existing PSG?
            if ($rData->getId() > 0 && !$target) {

                $psgMember = $rData->findMemberById($rData->getId());

                if ($psgMember != NULL) {
                    $prefix = $psgMember->getPrefix();
                } else {
                    $prefix = $uS->addPerPrefix++;
                    $psgMember = new PSGMember($rData->getId(), $prefix, VolMemberType::Guest, new PSGMemStay(ReserveData::NOT_STAYING));
                }

                $this->roleObjs[$prefix] = new Guest($dbh, $prefix, $rData->getId());

                if ($this->roleObjs[$prefix]->getNoReturn() != '') {
                    $psgMember->setStay(ReserveData::CANT_STAY);
                }

                $rData->setMember($psgMember);
            }

        } else if ($rData->getId() > 0) {

            // Load an existing member to a new psg
            $psgMember = $rData->findMemberById($rData->getId());

            if ($psgMember != NULL) {
                $prefix = $psgMember->getPrefix();
            } else {
                $prefix = $uS->addPerPrefix++;
                $psgMember = new PSGMember($rData->getId(), $prefix, VolMemberType::Guest, new PSGMemStay(ReserveData::NOT_STAYING));
            }

            $this->roleObjs[$prefix] = new Guest($dbh, $prefix, $rData->getId());

            if ($this->roleObjs[$prefix]->getNoReturn() != '') {
                $psgMember->setStay(ReserveData::CANT_STAY);
            }

            $rData->setMember($psgMember);

        }

        // Load empty member?
        if ($rData->getId() === 0) {

            $psgMember = $rData->findMemberById(0);

            if ($psgMember != NULL) {
                $prefix = $psgMember->getPrefix();
            } else {
                $prefix = $uS->addPerPrefix++;
                $psgMember = new PSGMember(0, $prefix, '', new PSGMemStay(ReserveData::NOT_STAYING));
            }

            $this->roleObjs[$prefix] = new Guest($dbh, $prefix, 0);

            $rData->setMember($psgMember);
        }

        // Reset the ignore idName param
        if ($rData->getId() < 0) {
            $rData->setId(0);
        }

    }

    public function setGuestsStaying(\PDO $dbh, ReserveData &$rData, $resvIdGuest) {

        if ($rData->getIdResv() > 0) {

            // Existing reservation...
            $resvGuestRs = new Reservation_GuestRS();
            $resvGuestRs->idReservation->setStoredVal($rData->getIdResv());
            $rgs = EditRS::select($dbh, $resvGuestRs, array($resvGuestRs->idReservation));

            $foundPriGuest = FALSE;

            foreach ($rgs as $g) {

                $mem = $rData->findMemberById($g['idGuest']);

                if ($mem !== NULL) {
                    $mem->getStayObj()->setStaying();

                    if ($g['Primary_Guest'] == '1') {
                        $foundPriGuest = TRUE;
                        $mem->getStayObj()->setPrimaryGuest(TRUE);
                    }
                }
            }

            if ($foundPriGuest === FALSE && $resvIdGuest > 0) {
                $mem = $rData->findMemberById($resvIdGuest);
                if ($mem !== NULL) {
                    $mem->getStayObj()->setPrimaryGuest(TRUE);
                }
            }

        } else {

            // New reservation, so set the stay for the guests.
            $mems = $rData->getPsgMembers();

            if (is_null($mems) === FALSE) {

                foreach ($mems as $mem) {

                    if ($mem !== NULL) {
                        $mem->getStayObj()->setStaying();
                    }
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

    public function createAddPersonMu(\PDO $dbh, ReserveData $rData) {

        $uS = Session::getInstance();

        $addPerson = array();

        foreach ($this->roleObjs as $prefix => $role) {

            if ($role->getIdName() != $rData->getId()) {
                continue;
            }

            // Remove guests.
            $removeIcons = HTMLContainer::generateMarkup('ul'
                , HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash'))
                    , array('class'=>'ui-state-default ui-corner-all hhk-removeBtn', 'style'=>'float:right;', 'data-prefix'=>$prefix, 'title'=>'Remove guest'))
                , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));

            $nameTr = HTMLContainer::generateMarkup('tr'
                    , $role->createThinMarkup($rData->getPsgMember($prefix)->getStayObj(), ($rData->getIdPsg() == 0 ? FALSE : TRUE))
                    . HTMLTable::makeTd($removeIcons));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $addressTr = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>'hhk-addrRow'));

            $mem = $rData->getPsgMember($prefix)->toArray();
            $adr = $this->getAddresses(array($role));

            $addPerson = array('id'=>$rData->getId(), 'ntr'=>$nameTr, 'atr'=>$addressTr, 'tblId'=>FAMILY::FAM_TABLE_ID, 'mem'=>$mem, 'addrs'=>$adr[$prefix]);
        }

        return $addPerson;

    }

    public function createCopyPersonMu(\PDO $dbh, ReserveData $rData) {

        $uS = Session::getInstance();

        $addPerson = array();

        foreach ($this->roleObjs as $prefix => $role) {

            if ($role->getIdName() != $rData->getId()) {
                continue;
            }

            // remove guests.
            $removeIcons = HTMLContainer::generateMarkup('ul'
                , HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash'))
                    , array('class'=>'ui-state-default ui-corner-all hhk-removeBtn', 'style'=>'float:right;', 'data-prefix'=>$prefix, 'title'=>'Remove guest'))
                , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));

            $nameTr = HTMLContainer::generateMarkup('tr'
                    , $role->createThinMarkup($rData->getPsgMember($prefix)->getStayObj(), TRUE)
                    . HTMLTable::makeTd($removeIcons));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $addressTr = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>'hhk-addrRow'));

            $mem = $rData->getPsgMember($prefix)->toArray();
            $adr = $this->getAddresses(array($role));

            $addPerson = array('id'=>$rData->getId(), 'ntr'=>$nameTr, 'atr'=>$addressTr, 'tblId'=>FAMILY::FAM_TABLE_ID, 'mem'=>$mem, 'addrs'=>$adr[$prefix]);
        }

        return $addPerson;

    }

    public function createFamilyMarkup(\PDO $dbh, ReservationRS $resvRs, ReserveData $rData) {

        $uS = Session::getInstance();
        //$tbl = new HTMLTable();
        $rowClass = 'odd';
        $mk1 = '';
        $trs = array();
        $copyGuests = array();
        $familyName = '';

        $AdrCopyDownIcon = HTMLContainer::generateMarkup('ul'
                    ,  HTMLContainer::generateMarkup('li',
                       HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-arrowthick-1-s'))
                        , array('class'=>'ui-state-default ui-corner-all', 'id'=>'adrCopy', 'style'=>'float:right;', 'title'=>'Copy top address down to any blank addresses.'))
                        .HTMLContainer::generateMarkup('span', 'Addr', array('style'=>'float:right;margin-top:5px;margin-right:.4em;'))
                    , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));


        // Name Header
        $th = HTMLContainer::generateMarkup('tr',
                HTMLTable::makeTh('Staying')
                . HTMLTable::makeTh('PG', array('title'=>'Primary Guest'))
                . RoleMember::createThinMarkupHdr($rData->getPatLabel(), FALSE, $rData->getShowBirthDate())
                . HTMLTable::makeTh('Phone')
                . HTMLTable::makeTh($AdrCopyDownIcon));


        // Put the patient first.
        if ($this->patientPrefix > 0) {

            $role = $this->roleObjs[$this->patientPrefix];
            $idPrefix = $role->getRoleMember()->getIdPrefix();

            $trs[] = HTMLContainer::generateMarkup('tr',
                    $role->createThinMarkup($rData->getPsgMember($idPrefix)->getStayObj(), TRUE)
                    , array('class'=>$rowClass));

            if ($role->getRoleMember()->getMemberFullName() != '') {
                $copyGuests[] = array(0=>$role->getIdName(), 1=>$role->getRoleMember()->getMemberFullName() );
            }

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

            $idPrefix = $role->getRoleMember()->getIdPrefix();

            if ($rData->getPsgMember($idPrefix)->isPrimaryGuest()) {
                $familyName = $role->getRoleMember()->get_lastName();
            }

            // Skip the patient who was taken care of above
            if ($role->getIdName() > 0 && $role->getIdName() == $this->getPatientId()) {
                continue;
            }


            if ($rowClass == 'odd') {
                $rowClass = 'even';
            } else if ($rowClass == 'even') {
                $rowClass = 'odd';
            }

            // Remove guests.
            $removeIcons = HTMLContainer::generateMarkup('ul'
                , HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-trash'))
                    , array('class'=>'ui-state-default ui-corner-all hhk-removeBtn', 'style'=>'float:right;', 'data-prefix'=>$idPrefix, 'title'=>'Remove guest'))
                , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'));


            $trs[] = HTMLContainer::generateMarkup('tr',
                    $role->createThinMarkup($rData->getPsgMember($idPrefix)->getStayObj(), ($rData->getIdPsg() == 0 ? FALSE : TRUE))
                    . ($role->getIdName() == 0 ? HTMLTable::makeTd($removeIcons) : '')
                    , array('class'=>$rowClass));

//            if ($role->getRoleMember()->getMemberFullName() != '') {
//                $copyGuests[] = array(0=>$role->getIdName(), 1=>$role->getRoleMember()->getMemberFullName() );
//            }

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
                HTMLContainer::generateMarkup('span', 'Add people - Name search: ')
                .HTMLInput::generateMarkup('', array('id'=>'txtPersonSearch', 'style'=>'margin-right:2em;', 'title'=>'Enter the first three characters of the person\'s last name'))

//                .HTMLContainer::generateMarkup('span', '- Add a copy of a guest: ')
//                .HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($copyGuests, '', TRUE), array('id'=>'selCopyGuest', 'style'=>'margin-right:.3em;'))
//                .HTMLInput::generateMarkup('Copy', array('id'=>'btnCopyGuest','type'=>'button'))
                , array('id'=>'divPersonSearch', 'style'=>'margin-top:10px;'));



        // Header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', $familyName . ' Family')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        return array('hdr'=>$hdr, 'tblHead'=>$th, 'tblBody'=>$trs, 'adtnl'=>$mk1, 'mem'=>$rData->getMembersArray(), 'addrs'=>$this->getAddresses($this->roleObjs), 'tblId'=>FAMILY::FAM_TABLE_ID);

    }

    protected function getDemographicsMarkup(\PDO $dbh, $role) {

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Demographics', array('style'=>'font-weight:bold;'))
                . $role->getRoleMember()->createDemographicsPanel($dbh, TRUE, FALSE), array('class'=>'hhk-panel')),
            array('style'=>'float:left; margin-right:3px;'));

    }

    public function save(\PDO $dbh, $post, ReserveData $rData) {

        $uS = Session::getInstance();

        // Open Psg
        $psg = new Psg($dbh, $rData->getIdPsg());
        $idPatient = 0;

        // Save Members
        foreach ($rData->getPsgMembers() as $m) {

            if ($m->getId() < 0) {
                continue;
            }

            // Patient?
            if ($m->getRole() == 'p') {

                $role = new Patient($dbh, $m->getPrefix(), $m->getId());
                $role->save($dbh, $post, $uS->username);
                $this->roleObjs[$m->getPrefix()] = $role;

                $m->setId($role->getIdName());

                $idPatient = $role->getIdName();
                $this->patientId = $role->getIdName();
                $this->patientPrefix = $m->getPrefix();

            } else {

                $role = new Guest($dbh, $m->getPrefix(), $m->getId());
                $role->save($dbh, $post, $uS->username);
                $this->roleObjs[$m->getPrefix()] = $role;

                $m->setId($role->getIdName());

            }

            $psg->setNewMember($role->getIdName(), $role->getPatientRelationshipCode());
        }

        // Save PSG
        $psg->savePSG($dbh, $this->patientId, $uS->username);
        $rData->setIdPsg($psg->getIdPsg());

        if ($psg->getIdPsg() > 0 && $this->patientId > 0) {

            // Save Hospital
            $this->hospStay = new HospitalStay($dbh, $psg->getIdPatient());
            Hospital::saveReferralMarkup($dbh, $psg, $this->hospStay, $post);
            $rData->setIdHospital_Stay($this->hospStay->getIdHospital_Stay());

        }

        return $rData;
    }

    public function getPatientId() {
        return $this->patientId;
    }

    public function getHospStay() {
        return $this->hospStay;
    }

}

class JoinNewFamily extends Family {

    public function initMembers(\PDO $dbh, ReserveData &$rData) {

        $uS = Session::getInstance();

        // forced New PSG
        $psgMember = $rData->findMemberById($rData->getId());

        if ($psgMember != NULL) {
            $prefix = $psgMember->getPrefix();
        } else {
            $prefix = $uS->addPerPrefix++;
            $psgMember = new PSGMember($rData->getId(), $prefix, VolMemberType::Guest, new PSGMemStay(ReserveData::NOT_STAYING));
            $rData->setMember($psgMember);
        }

        $this->roleObjs[$prefix] = new Guest($dbh, $prefix, $rData->getId());

        $psgMember->setStay(($this->roleObjs[$prefix]->getNoReturn() == '' ? ReserveData::NOT_STAYING : ReserveData::CANT_STAY));

    }
}


