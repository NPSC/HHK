<?php

namespace HHK\Purchase;

use HHK\Notes;
use HHK\sec\{Session, SecurityComponent};
use HHK\SysConst\FinAppStatus;
use HHK\TableLog\ReservationLog;
use HHK\TableLog\VisitLog;
use HHK\Tables\Reservation\Fin_ApplicationRS;
use HHK\Tables\EditRS;
use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput, HTMLSelector};
use HHK\Exception\RuntimeException;
use HHK\Purchase\PriceModel\AbstractPriceModel;

/**
 * FinAssistance.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of FinAssistance
 *
 * @author Eric
 */

class FinAssistance {

    protected $finAppRs;
    protected $roomRateRS;
    protected $idRegistration;
    protected $idRoomRate;
    public $rateCategory;
    protected $rrates;


    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param int $idRegistration
     * @param string $category
     */
    function __construct(\PDO $dbh, $idRegistration, $category = '') {

        $uS = Session::getInstance();

        $this->idRegistration = intval($idRegistration);
        $this->rateCategory = $category;

        $finRs = new Fin_ApplicationRS();

        if ($idRegistration > 0) {

            $finRs->idRegistration->setStoredVal($this->idRegistration);
            $rows = EditRS::select($dbh, $finRs, array($finRs->idRegistration));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $finRs);
            }
        }

        $this->finAppRs = $finRs;

        if ($this->rateCategory == '') {
            $this->rateCategory = $finRs->FA_Category->getStoredVal();
        }

        $this->rrates = RoomRate::makeSelectorOptions(AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel));

    }


    /**
     *
     * @return HTMLTable
     */
    public function createRateCalcMarkup() {

        $calcTbl = new HTMLTable();

        $calcTbl->addHeader(HTMLTable::makeTh('Estimated Monthly Household Income', array('colspan'=>'2')) . HTMLTable::makeTh('Household Size') .HTMLTable::makeTh('Rate'));

        $rateCodeTitle = '';
        if (isset($this->rrates[$this->getFaCategory()])) {
            $rateCodeTitle = $this->rrates[$this->getFaCategory()][1];
        }

        $calcTbl->addBodyTr(
            HTMLTable::makeTd(
                    HTMLContainer::generateMarkup("div",
                        HTMLContainer::generateMarkup('span', '(Before Taxes)').
                        HTMLContainer::generateMarkup("span", '$ '.HTMLInput::generateMarkup($this->getMontylyIncome() == 0 ? '' : number_format($this->getMontylyIncome()), array('type'=>'number','name'=>'txtFaIncome', 'style'=>'width:5em')))
                    , array("class"=>"hhk-flex hhk-flex-wrap", "style"=>"justify-content:space-evenly;"))
                , array('style'=>'text-align:center;', 'colspan'=>'2'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup($this->getHhSize(), array('type'=>'number','name'=>'txtFaSize', 'style'=>"width:3em")), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $rateCodeTitle, array('id'=>'spnFaCatTitle'))
                    .HTMLInput::generateMarkup($this->getFaCategory(), array('name'=>'hdnRateCat', 'type'=>'hidden'))
                    , array('style'=>'text-align:center;'))
            );

        return $calcTbl;
    }

    public function createIncomeDialog() {

        $uS = Session::getInstance();
        $calcTbl = $this->createRateCalcMarkup();

        // Wrap up the calculator
        $calcMarkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Financial Assistance Calculator', array('style'=>'font-weight:bold;'))
            . $calcTbl->generateMarkup(array("style"=>"width:100%")), array('class'=>'hhk-panel')),
            array('class'=>'mb-3 ignrSave'));


        // Category assignment table.
        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh('Status') . HTMLTable::makeTh('Status Date') . HTMLTable::makeTh('Approved By', array('colspan'=>'2')));
        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($uS->guestLookups['FinAppStatus']), $this->getFaStatus(), TRUE), array('name'=>'SelFaStatus')), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup(($this->getFaStatusDate() == '' ? '' : date('M j, Y', strtotime($this->getFaStatusDate()))), array('name'=>'txtFaStatusDate', 'readonly'=>'readonly', 'style'=>'width: 100%; border: none; text-align:center;')))
                .HTMLTable::makeTd($this->getApprovedId(), array('style'=>'text-align:center;'))
                );

        $tbl->addBodyTr(
                HTMLTable::makeTd('Reason:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($this->getFaReason(), array('type'=>'textbox', 'name'=>'txtFaReason', 'size'=>'55', 'style'=>"width: 100%")) , array('colspan'=>'2'))
                );

        $tbl->addBodyTr(
                HTMLTable::makeTd('Notes: '. Notes::markupShell($this->getFaNotes(), 'txtFaNotes'), array('colspan'=>'3'))
                );

        $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'spnFaErrorMsg')), array('colspan'=>'3')));

        $markup = $calcMarkup . $tbl->generateMarkup(array('style'=>'width: 100%'));

        return $markup;
    }


    public function saveDialogMarkup(\PDO $dbh, $newStatus, $newCategory, $reason, $faStatDate, $notes, $uname) {

        $uS = Session::getInstance();

        if ($this->hasApplied() === FALSE) {
            $this->setApplied(TRUE);
        }

        if (SecurityComponent::is_Authorized('guestadmin')) {

            $catTitle = '';
            $statTitle = '';

            // Category changed?
            if ($newCategory != '' && $newCategory != $this->finAppRs->FA_Category->getStoredVal()) {

                if (isset($this->rrates[$newCategory]) === FALSE) {
                    throw new RuntimeException('Rate Category is undefined: ' . $newCategory);
                }

                $this->finAppRs->Approved_Id->setNewVal($uname);
                $this->finAppRs->FA_Category->setNewVal($newCategory);

                $catTitle = "Rate: " . $this->rrates[$newCategory][1];

            }

            //  Status change?
            if ($newStatus != '' && $newStatus != $this->finAppRs->FA_Status->getStoredVal()) {

                if (isset($uS->guestLookups['FinAppStatus'][$newStatus]) === FALSE) {
                    throw new RuntimeException('Financial Application Status is undefined: ' . $newStatus);
                }

                if ($faStatDate == '') {
                    $faStatDate = date('Y-m-d H:i:s');
                }

                $this->finAppRs->Approved_Id->setNewVal($uname);
                $this->finAppRs->FA_Status_Date->setNewVal($faStatDate);
                $this->finAppRs->FA_Status->setNewVal($newStatus);
                $this->finAppRs->FA_Reason->setNewVal($reason);

                $statTitle = "New Status: " . $uS->guestLookups['FinAppStatus'][$newStatus][1];

                if ($catTitle != '') {
                    $statTitle = '; ' . $statTitle;
                }

                if ($notes != '') {
                    $statTitle .= '; ';
                }
            }

            // Notes
            if ($notes != '' || $catTitle != '' || $statTitle != '') {
                $this->finAppRs->Notes->setNewVal($this->getFaNotes() . "\r\n" . date('m-d-Y') . ', ' . $uname . ' - ' . $catTitle . $statTitle . $notes);
            }
        }

        $this->finAppRs->Updated_By->setNewVal($uname);
        $this->finAppRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        if ($this->finAppRs->idFin_application->getStoredVal() > 0) {
            // update
            $updt = EditRS::update($dbh, $this->finAppRs, array($this->finAppRs->idFin_application));

            if ($updt > 0) {
                $logText = VisitLog::getUpdateText($this->finAppRs);
                ReservationLog::logFinAppl($dbh, 0,
                    $this->idRegistration,
                    $this->getIdRoomRate(),
                    $logText, "update", $uname);
            }

        } else {
            // insert
            $this->finAppRs->idRegistration->setNewVal($this->idRegistration);
            $this->finAppRs->Status->setNewVal('a');

            $idFa = EditRS::insert($dbh, $this->finAppRs);
            $this->finAppRs->idFin_application->setNewVal($idFa);

            $logText = VisitLog::getInsertText($this->finAppRs);
            ReservationLog::logFinAppl($dbh, 0,
                    $this->idRegistration,
                    $this->getIdRoomRate(),
                    $logText, "insert", $uname);

        }

        EditRS::updateStoredVals($this->finAppRs);

    }

    public function isApproved() {
        if ($this->getFaStatus() == FinAppStatus::Granted) {
            return TRUE;
        }
        return FALSE;
    }

    public function getFaStatus() {
        return $this->finAppRs->FA_Status->getStoredVal();
    }

    public function getHhSize() {
        return $this->finAppRs->HH_Size->getStoredVal();
    }

    public function setHhSize($size) {
        $tsize = intval($size, 10);
        $this->finAppRs->HH_Size->setNewVal($tsize);
        return $this;
    }

    public function getMontylyIncome() {
        return $this->finAppRs->Monthly_Income->getStoredVal();
    }

    public function setMontylyIncome($income) {
        $tsize = intval($income, 10);
        $this->finAppRs->Monthly_Income->setNewVal($tsize);
        return $this;
    }

    public function getFaReason() {
        return $this->finAppRs->FA_Reason->getStoredVal();
    }

    public function getFaNotes() {
        return (is_null($this->finAppRs->Notes->getStoredVal()) ? '' : $this->finAppRs->Notes->getStoredVal());
    }

    public function getApprovedId() {
        return $this->finAppRs->Approved_Id->getStoredVal();
    }

    public function getFaStatusDate() {
        return $this->finAppRs->FA_Status_Date->getStoredVal();
    }

    public function getFaCategory() {
        if (is_null($this->finAppRs->FA_Category->getNewVal())) {
            return $this->finAppRs->FA_Category->getStoredVal();
        } else {
            return $this->finAppRs->FA_Category->getNewVal();
        }
    }

    public function hasApplied() {
        if ($this->finAppRs->FA_Applied->getStoredVal() == 'y') {
            return TRUE;
        }
        return FALSE;
    }

    public function setApplied($TorF, $appDate = '') {
        if ($appDate == '') {
            $appDate = date('Y-m-d');
        }
        if ($TorF) {
            $this->finAppRs->FA_Applied->setNewVal('y');
            $this->finAppRs->FA_Applied_Date->setNewVal(date('Y-m-d', strtotime($appDate)));
        } else {
            $this->finAppRs->FA_Applied->setNewVal('n');
        }
    }

    public function getAppliedDate() {
        return $this->finAppRs->FA_Applied_Date->getStoredVal();
    }

    /**
     * Summary of getEstAmount
     * @param \PDO $dbh
     * @param int $days
     * @param string $category
     * @param mixed $pledgedRate
     * @return mixed
     */
//    public function getEstAmount(\PDO $dbh, $days, $category, $pledgedRate = 0) {
//        return self::amountCalculator($dbh, $days, $category, $pledgedRate);
//    }

    public function getIdRoomRate() {
        return $this->idRoomRate;
    }

    public function getIdRegistration() {
        return $this->finAppRs->idRegistration->getStoredVal();
    }

    public static function getAssistanceCategory(\PDO $dbh, $income, $hhSize) {

        $maxHhSize = 1;
        $ratCats = [];

        $stmt = $dbh->query("Select max(Household_Size) from rate_breakpoint;");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) > 0) {
            $maxHhSize = $rows[0][0];
        }

        if ($hhSize > $maxHhSize) {
            $hhSize = $maxHhSize;
        }

        // preload all rate categories
        $stmt = $dbh->query("select distinct Rate_Category from rate_breakpoint ORDER BY `Rate_Category`");

        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
            $ratCats[] = $r[0];
        }


        $query = "Select * from rate_breakpoint where Household_Size = :size order by Rate_Category";
        $stmt = $dbh->prepare($query);
        $stmt->execute(array(':size'=>$hhSize));

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $bpCat = '';
        $cat = '';

        foreach ($rows as $r) {

            if ($ratCats[0] == $r['Rate_Category'] && $income < $r['Breakpoint']) {
                // first rate category
                $bpCat = $r['Rate_Category'];
                break;
            } else if ($ratCats[count($ratCats)-1] == $r['Rate_Category'] && $income > $r['Breakpoint']) {
                // Last rate category
                $bpCat = $r['Rate_Category'];
                break;
            } else if ($income <= $r['Breakpoint']) {
                // All the rest
                $bpCat = $r['Rate_Category'];
                break;
            }
        }

        if ($bpCat != '') {

            $stmt = $dbh->query("SELECT `FA_Category` FROM `room_rate` WHERE `Status` = 'a' AND Rate_Breakpoint_Category = '$bpCat'");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) > 0) {
                $cat = $rows[0]['FA_Category'];
            }
        }

        return $cat;
    }

}
