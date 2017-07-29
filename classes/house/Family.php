<?php

/**
 * Description of Family
 *
 * @author Eric
 */
class Family {

    protected $members;
    protected $rData;

    public function __construct(\PDO $dbh, ReserveData $rData) {

        $this->rData = $rData;
        $this->loadMembers($dbh);

    }

    public function getPatientId(){

        foreach ($this->members as $m) {
            if (is_a($m, 'Patient')) {
                return $m->getIdName();
            }
        }

        return 0;
    }

    protected function loadMembers(\PDO $dbh) {

        $this->members = array();

        if ($this->rData->getidPsg() > 0) {

            $ngRs = new Name_GuestRS();
            $ngRs->idPsg->setStoredVal($this->rData->getidPsg());
            $rows = EditRS::select($dbh, $ngRs, array($ngRs->idPsg));

            foreach ($rows as $r) {
                $ngrs = new Name_GuestRS();
                EditRS::loadRow($r, $ngrs);

                if ($ngrs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                    $this->members[$ngrs->idName->getStoredVal()] = new Patient($dbh, $ngrs->idName->getStoredVal(), $ngrs->idName->getStoredVal());
                } else {
                    $this->members[$ngrs->idName->getStoredVal()] = new Guest($dbh, $ngrs->idName->getStoredVal(), $ngrs->idName->getStoredVal());
                }
            }
        }

        // Load new member?
        if ($this->rData->getId() > 0 && isset($this->members[$this->rData->getId()]) === FALSE) {
            $this->members[$this->rData->getId()] = new Guest($dbh, $this->rData->getId(), $this->rData->getId());
        }

        // Load empty member
        $this->members[0] = new Guest($dbh, '0', 0);


    }

    public function createFamilyMarkup() {

        $tbl = new HTMLTable();
        $addHdr = TRUE;
        $expDatesControl = '';

        foreach ($this->members as $m) {

            $name = $m->getNameObj();

            if ($addHdr) {
                $tbl->addHeaderTr($name->createMarkupHdr($this->rData->getPatLabel(), FALSE));
                $addHdr = FALSE;
                $expDatesControl = $m->getExpectedDatesControl();;
            }

            $tbl->addBodyTr($m->createThinMarkup($tbl, ($this->rData->getidPsg() == 0 ? FALSE : TRUE)));
        }

        $div = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-panel hhk-tdbox'));

        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Visitors ')
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        return array('hdr'=>$hdr, 'div'=>$div, 'expDates'=>$expDatesControl);

    }


}
