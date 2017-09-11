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

    public function __construct(ReserveData $rData) {

        $this->rData = $rData;
        $this->patientId = 0;

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
                    $this->roleObjs[$ngrs->idName->getStoredVal()] = new Patient($dbh, $uS->addPerPrefix, $ngrs->idName->getStoredVal(), $this->rData->getPatLabel());
                    $this->roleObjs[$ngrs->idName->getStoredVal()]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());

                    $this->members[$uS->addPerPrefix]['role'] = 'p';
                    $this->members[$uS->addPerPrefix]['stay'] = ($uS->PatientAsGuest ? '0' : 'x');
                    $this->members[$uS->addPerPrefix]['id'] = $ngrs->idName->getStoredVal();
                    $this->members[$uS->addPerPrefix]['pref'] = $uS->addPerPrefix;

                    $this->patientId = $ngrs->idName->getStoredVal();

                } else {
                    // guest
                    $this->roleObjs[$ngrs->idName->getStoredVal()] = new Guest($dbh, $uS->addPerPrefix, $ngrs->idName->getStoredVal());
                    $this->roleObjs[$ngrs->idName->getStoredVal()]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());

                    $this->members[$uS->addPerPrefix]['role'] = 'g';
                    $this->members[$uS->addPerPrefix]['stay'] = '0';
                    $this->members[$uS->addPerPrefix]['id'] = $ngrs->idName->getStoredVal();
                    $this->members[$uS->addPerPrefix]['pref'] = $uS->addPerPrefix;
                }
            }

            // Load new member to existing PSG?
            if ($this->rData->getId() > 0 && !$target) {

                $uS->addPerPrefix++;

                $this->roleObjs[$this->rData->getId()] = new Guest($dbh, $uS->addPerPrefix, $this->rData->getId());

                $this->members[$uS->addPerPrefix]['role'] = 'g';
                $this->members[$uS->addPerPrefix]['stay'] = '0';
                $this->members[$uS->addPerPrefix]['id'] = $this->rData->getId();
                $this->members[$uS->addPerPrefix]['pref'] = $uS->addPerPrefix;
            }

        // Flag for new PSG for existing guest
        } else if ($this->rData->getForceNewPsg()) {

            // forced New PSG
            $uS->addPerPrefix++;

            $this->roleObjs[$this->rData->getId()] = new Guest($dbh, $uS->addPerPrefix, $this->rData->getId());

            $this->members[$uS->addPerPrefix]['role'] = '';
            $this->members[$uS->addPerPrefix]['stay'] = '0';
            $this->members[$uS->addPerPrefix]['id'] = $this->rData->getId();
            $this->members[$uS->addPerPrefix]['pref'] = $uS->addPerPrefix;

        }


        // Load empty member?
        if ($this->rData->getId() === 0) {

            $uS->addPerPrefix++;

            $this->roleObjs[0] = new Guest($dbh, $uS->addPerPrefix, 0);

            $this->members[$uS->addPerPrefix]['role'] = '';
            $this->members[$uS->addPerPrefix]['stay'] = '0';
            $this->members[$uS->addPerPrefix]['id'] = '0';
            $this->members[$uS->addPerPrefix]['pref'] = $uS->addPerPrefix;
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

                foreach ($this->members as $pref => $mem) {

                    if ($mem['id'] == $g['idGuest'] && $mem['stay'] !== 'x') {
                        $this->members[$pref]['stay'] = '1';
                        break;
                    }
                }
            }

        } else {
            // New reservation, so set the stay for the targeted guest.
            foreach ($this->members as $pref => $mem) {

                if ($mem['id'] == $this->rData->getId() && $mem['stay'] !== 'x') {
                    $this->members[$pref]['stay'] = '1';
                    break;
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

            $addrs[$role->getRoleMember()->getIdPrefix()] = $addr;

        }

        return $addrs;
    }

    public function CreateAddPersonMu(\PDO $dbh) {

        $uS = Session::getInstance();

        $addPerson = array();

        if (isset($this->roleObjs[$this->rData->getId()])) {

            $role = $this->roleObjs[$this->rData->getId()];

            $nameTr = HTMLContainer::generateMarkup('tr'
                    , $role->createThinMarkup($this->members[$role->getRoleMember()->getIdPrefix()]['stay'], ($this->rData->getIdPsg() == 0 ? FALSE : TRUE))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('Remove', array('type'=>'button', 'id'=>$role->getRoleMember()->getIdPrefix().'btnRemove'))));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $addressTr = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>'hhk-addrRow', 'style'=>'display:none;'));

            $addPerson = array('id'=>$this->rData->getId(), 'ntr'=>$nameTr, 'atr'=>$addressTr, 'tblId'=>FAMILY::FAM_TABLE_ID, 'pref'=>$role->getRoleMember()->getIdPrefix(), 'addrs'=>$this->getAddresses(array($role)));
        }

        return $addPerson;

    }

    public function createFamilyMarkup(\PDO $dbh, ReservationRS $resvRs) {

        $uS = Session::getInstance();
        $tbl = new HTMLTable();
        $rowClass = 'odd';
        $mk1 = '';


        $tbl->addHeaderTr(
                HTMLTable::makeTh('Staying')
                . RoleMember::createThinMarkupHdr($this->rData->getPatLabel(), FALSE, $this->rData->getPatBirthDateFlag())
                . HTMLTable::makeTh('Phone')
                . HTMLTable::makeTh('Addr'));


        // Put the patient first.
        if ($this->getPatientId() > 0) {

            $role = $this->roleObjs[$this->getPatientId()];
            $idPrefix = $role->getRoleMember()->getIdPrefix();

            $tbl->addBodyTr(
                    $role->createThinMarkup($this->members[$idPrefix]['stay'], ($this->rData->getIdPsg() == 0 ? FALSE : TRUE))
                    , array('class'=>$rowClass));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            if ($uS->PatientAddr) {
                $tbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>$rowClass . ' hhk-addrRow'));
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

            $tbl->addBodyTr($role->createThinMarkup($this->members[$idPrefix]['stay'], ($this->rData->getIdPsg() == 0 ? FALSE : TRUE)), array('class'=>$rowClass));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $role);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $tbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd($role->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>$rowClass . ' hhk-addrRow'));
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

        $div = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(array('id'=>FAMILY::FAM_TABLE_ID, 'class'=>'hhk-table')) . $mk1, array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-tdbox'));

        return array('hdr'=>$hdr, 'div'=>$div, 'mem'=>$this->members, 'addrs'=>$this->getAddresses($this->roleObjs));

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
        foreach ($this->members as $m) {

            $id = intval($m['id'], 10);

            if ($id < 0) {
                continue;
            }

            $role = new Guest($dbh, $m['pref'], $id);
            $role->save($dbh, $post, $uS->username);

            // Patient?
            if ($m['role'] == 'p') {
                $idPatient = $role->getIdName();
            }

            $psg->setNewMember($role->getIdName(), $role->getPatientRelationshipCode());
        }

        // Save PSG
        $psg->savePSG($dbh, $idPatient, $uS->username);

        if ($psg->getIdPsg() > 0 && $idPatient > 0) {

            // Save Hospital
            $hstay = new HospitalStay($dbh, $psg->getIdPatient());
            Hospital::saveReferralMarkup($dbh, $psg, $hstay, $post);

            $this->rData->setIdPsg($psg->getIdPsg());

        }

        return $this->rData;
    }

    public function getPatientId() {
        return $this->patientId;
    }


}
