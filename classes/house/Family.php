<?php

/**
 * Description of Family
 *
 * @author Eric
 */
class Family {

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
            $this->members[$this->rData->getId()]['stay'] = '0';
        }

        // Load empty member
        $this->roleObj[0] = new Guest($dbh, 'a', 0);
        $this->members[0]['role'] = '';
        $this->members[0]['stay'] = '0';


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

    public function createFamilyMarkup(ReservationRS $resvRs) {

        $uS = Session::getInstance();
        $tbl = new HTMLTable();
        $mk1 = '';
        $rowClass = 'odd';


        $tbl->addHeaderTr(HTMLTable::makeTh('Staying') . $this->roleObj[0]->getRoleMember()->createThinMarkupHdr($this->rData->getPatLabel(), FALSE) . HTMLTable::makeTh('Phone') . HTMLTable::makeTh('Addr'));



        // Put the patient first.
        if ($this->getPatientId() > 0) {

            $tbl->addBodyTr($this->roleObj[$this->getPatientId()]->createThinMarkup($this->members[$this->getPatientId()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE)), array('class'=>$rowClass));

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

            //$name = $m->getNameObj();
            $tbl->addBodyTr($m->createThinMarkup($this->members[$m->getIdName()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE)), array('class'=>$rowClass));
        }

        $adrTbl = new HTMLTable();
        $adrTbl->addHeaderTr($m->createThinAddrHdr($uS->county));
        $adrTbl->addBodyTr($m->createThinAddrMU($uS->county));


        // Waitlist notes
        if ($uS->UseWLnotes) {

            $mk1 = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->rData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resvRs->Checkin_Notes->getStoredVal(), array('name'=>'taCkinNotes', 'rows'=>'2', 'cols'=>'75')),
                array('class'=>'hhk-panel', 'style'=>'clear:both; float:left; margin-top:10px; font-size:.9em;'));
            }

        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Visitors ')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        $div = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(array('id'=>'tblFamily', 'class'=>'hhk-table')) . $mk1, array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-panel hhk-tdbox'));

        return array('hdr'=>$hdr, 'div'=>$div, 'addrs'=>$this->getAddresses(), 'adrTbl' => $adrTbl->generateMarkup());

    }


    public function getPatientId() {
        return $this->patientId;
    }


}
