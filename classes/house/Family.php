<?php

/**
 * Description of Family
 *
 * @author Eric
 */
class Family {

    const FAM_TABLE_ID = 'tblFamily';

    protected $members;
    protected $roleObj;
    protected $rData;
    protected $patientId;

    public function __construct(\PDO $dbh, ReserveData $rData) {

        $this->rData = $rData;
        $this->patientId = 0;

        $this->loadMembers($dbh);

    }

    protected function loadMembers(\PDO $dbh) {

        $uS = Session::getInstance();
        $this->members = array();

        // Load any existing PSG members.
        if ($this->rData->getidPsg() > 0) {

            // PSG is defined
            $ngRs = new Name_GuestRS();
            $ngRs->idPsg->setStoredVal($this->rData->getidPsg());
            $rows = EditRS::select($dbh, $ngRs, array($ngRs->idPsg));

            foreach ($rows as $r) {
                $ngrs = new Name_GuestRS();
                EditRS::loadRow($r, $ngrs);

                if ($ngrs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                    $this->roleObj[$ngrs->idName->getStoredVal()] = new Patient($dbh, $ngrs->idName->getStoredVal(), $ngrs->idName->getStoredVal(), $this->rData->getPatLabel());
                    $this->roleObj[$ngrs->idName->getStoredVal()]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());
                    $this->members[$ngrs->idName->getStoredVal()]['role'] = 'p';
                    $this->members[$ngrs->idName->getStoredVal()]['stay'] = ($uS->PatientAsGuest ? '0' : 'x');
                    $this->patientId = $ngrs->idName->getStoredVal();
                } else {
                    $this->roleObj[$ngrs->idName->getStoredVal()] = new Guest($dbh, $ngrs->idName->getStoredVal(), $ngrs->idName->getStoredVal());
                    $this->roleObj[$ngrs->idName->getStoredVal()]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());
                    $this->members[$ngrs->idName->getStoredVal()]['role'] = 'g';
                    $this->members[$ngrs->idName->getStoredVal()]['stay'] = '0';
                }
            }
        }

        // Load new member?
        if ($this->rData->getId() > 0 && isset($this->members[$this->rData->getId()]) === FALSE) {
            $this->roleObj[$this->rData->getId()] = new Guest($dbh, $this->rData->getId(), $this->rData->getId());
            $this->members[$this->rData->getId()]['role'] = '';
            $this->members[$this->rData->getId()]['stay'] = '1';
        }

        // Load empty member?
        if ($this->rData->getId() === 0) {
            $this->roleObj[0] = new Guest($dbh, '0', 0);
            $this->members[0]['role'] = '';
            $this->members[0]['stay'] = '1';
        }


        // Update who is staying
        $this->loadResvGuests($dbh);
    }

    protected function loadResvGuests(\PDO $dbh) {

        if ($this->rData->getIdResv() > 0) {

            $resvGuestRs = new Reservation_GuestRS();
            $resvGuestRs->idReservation->setStoredVal($this->rData->getIdResv());
            $rgs = EditRS::select($dbh, $resvGuestRs, array($resvGuestRs->idReservation));

            foreach ($rgs as $g) {

                if (isset($this->members[$g['idGuest']]) && $this->members[$g['idGuest']]['stay'] != 'x') {
                    $this->members[$g['idGuest']]['stay'] = '1';
                }
            }
        }
    }

    protected function getAddresses() {

        $addrs = array();

        foreach ($this->roleObj as $m) {

            $addrObj = $m->getAddrObj();
            $addr = $addrObj->get_Data(Address_Purpose::Home);

            $addr['Purpose'] = Address_Purpose::Home;

            $addr['Email'] = $m->getEmailsObj()->get_Data(Email_Purpose::Home)['Email'];

            $addrs[$m->getIdName()] = $addr;

        }

        return $addrs;
    }

    public function addPerson(\PDO $dbh) {

        $uS = Session::getInstance();
        $addPerson = array();

        if (isset($this->roleObj[$this->rData->getId()])) {

            $m = $this->roleObj[$this->rData->getId()];

            $nameTr = HTMLContainer::generateMarkup('tr', $m->createThinMarkup($this->members[$m->getIdName()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE)));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $m);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $addressTr = HTMLContainer::generateMarkup('tr', HTMLTable::makeTd('') . HTMLTable::makeTd($m->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>'hhk-addrRow'));

            $addPerson = array('ntr'=>$nameTr, 'atr'=>$addressTr, 'tblId'=>FAMILY::FAM_TABLE_ID, 'addrs'=>$this->getAddresses());
        }

        return array('addPerson' => $addPerson);

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

            $tbl->addBodyTr(
                    $this->roleObj[$this->getPatientId()]->createThinMarkup($this->members[$this->getPatientId()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE))
                    , array('class'=>$rowClass));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $this->roleObj[$this->getPatientId()]);
            } else {
                $demoMu = '';
            }

            if ($uS->PatientAddr) {
                $tbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd($this->roleObj[$this->getPatientId()]->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>$rowClass . ' hhk-addrRow', 'style'=>'display:none;'));
            }

        }

        // List each member
        foreach ($this->roleObj as $m) {

            // Skip the patient who was taken care of above
            if ($m->getIdName() > 0 && $m->getIdName() == $this->getPatientId()) {
                continue;
            }

            if ($rowClass == 'odd') {
                $rowClass = 'even';
            } else if ($rowClass == 'even') {
                $rowClass = 'odd';
            }

            $tbl->addBodyTr($m->createThinMarkup($this->members[$m->getIdName()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE)), array('class'=>$rowClass));

            // Demographics
            if ($uS->ShowDemographics) {
                $demoMu = $this->getDemographicsMarkup($dbh, $m);
            } else {
                $demoMu = '';
            }

            // Add addresses and demo's
            $tbl->addBodyTr(HTMLTable::makeTd('') . HTMLTable::makeTd($m->createAddsBLock() . $demoMu, array('colspan'=>'11')), array('class'=>$rowClass . ' hhk-addrRow', 'style'=>'display:none;'));
        }

        // Guest search
        $mk1 .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('span', 'Add people - Name Search: ')
                .HTMLInput::generateMarkup('', array('id'=>'txtPersonSearch', 'title'=>'Enter the first three characters of the person\'s last name'))
                , array('id'=>'divPersonSearch', 'style'=>'margin-top:10px;'));


        // Waitlist notes?
        if ($uS->UseWLnotes) {

            $mk1 .= HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->rData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resvRs->Checkin_Notes->getStoredVal(), array('name'=>'taCkinNotes', 'rows'=>'2', 'cols'=>'75')),
                array('class'=>'hhk-panel', 'style'=>'clear:both; float:left; margin-top:10px; font-size:.9em;'));
            }

            // Header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Family ')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        $div = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(array('id'=>FAMILY::FAM_TABLE_ID, 'class'=>'hhk-table')) . $mk1, array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-tdbox'));

        return array('hdr'=>$hdr, 'div'=>$div, 'addrs'=>$this->getAddresses());

    }

    protected function getDemographicsMarkup(\PDO $dbh, $m) {

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
        HTMLContainer::generateMarkup('legend', 'Demographics', array('style'=>'font-weight:bold;'))
        . $m->getRoleMember()->createDemographicsPanel($dbh, TRUE, FALSE), array('class'=>'hhk-panel')),
        array('style'=>'float:left; margin-right:3px;'));

    }


    public function getPatientId() {
        return $this->patientId;
    }


}
