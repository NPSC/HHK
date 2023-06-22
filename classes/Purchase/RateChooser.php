<?php

namespace HHK\Purchase;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable, HTMLSelector};
use HHK\House\Reservation\Reservation_1;
use HHK\House\Visit\Visit;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\{DefaultSettings, GLTypeCodes, ItemId, RoomRateCategories, VisitStatus};
use HHK\sec\{SecurityComponent, Session};
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\{StaysRS, VisitRS};
use HHK\SysConst\ItemPriceCode;

/**
 * RateChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *
 *
 * @author Eric
 */
class RateChooser {

    /**
     * @var bool
     */
    protected $payAtCheckin;
    /**
     * @var bool
     */
    protected $incomeRated;
    /**
     * Summary of isAllowed
     * @var bool
     */
    protected $isAllowed;
    /**
     * Summary of payVisitFee
     * @var mixed
     */
    protected $payVisitFee;
    /**
     * Summary of openCheckin
     * @var bool
     */
    protected $openCheckin;
    /**
     * Summary of rateGlideExtend
     * @var mixed
     */
    protected $rateGlideExtend;

    /**
     *
     * @var AbstractPriceModel
     */
    protected $priceModel;



    /**
     * Summary of __construct
     * @param \PDO $dbh
     */
    public function __construct(\PDO $dbh) {

        $uS = Session::getInstance();

        $this->incomeRated = $uS->IncomeRated;
        $this->payAtCheckin = $uS->PayAtCkin;
        $this->openCheckin = $uS->OpenCheckin;
        $this->payVisitFee = $uS->VisitFee;
        $this->rateGlideExtend = $uS->RateGlideExtend;
        $this->priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        if ($uS->RateChangeAuth == TRUE) {
            $this->isAllowed = SecurityComponent::is_Authorized('guestadmin');
        } else {
            $this->isAllowed = TRUE;
        }

    }

    /**
     * Summary of validateCategory
     * @param string $category
     * @return bool
     */
    public function validateCategory($category) {

        $isValid = FALSE;

        foreach ($this->priceModel->getActiveModelRoomRates() as $r) {

            if ($r->FA_Category->getStoredVal() == $category) {
                $isValid = TRUE;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Summary of getPriceModel
     * @return AbstractPriceModel|PriceModel\Price3Steps|PriceModel\PriceBasic|PriceModel\PriceDaily|PriceModel\PriceGuestDay|PriceModel\PriceNdayBlock|PriceModel\PriceNone|PriceModel\PricePerpetualSteps
     */
    public function getPriceModel() {
        return $this->priceModel;
    }

    /**
     * Summary of createChangeRateMarkup
     * @param \PDO $dbh
     * @param \HHK\Tables\Visit\VisitRS $vRs
     * @return HTMLTable
     */
    public function createChangeRateMarkup(\PDO $dbh, VisitRs $vRs) {

        $attrFixed = array('class'=>'hhk-fxFixed', 'style'=>'margin-left:.5em; ');
        $attrAdj = array('class'=>'hhk-fxAdj', 'style'=>'margin-left:.5em;');
        $fixedRate = '';
        $rateTbl = new HTMLTable();

        //
        if ($vRs->Rate_Category->getStoredVal() == DefaultSettings::Fixed_Rate_Category) {
            // Fixed rate
            $attrAdj['style'] .= 'display:none;';
            $fixedRate = $vRs->Pledged_Rate->getStoredVal() == 0 ? '0' : (number_format($vRs->Pledged_Rate->getStoredVal(), 2));

        } else {

            $attrFixed['style'] .= 'display:none;';

        }

        $rateCategories = RoomRate::makeSelectorOptions($this->priceModel, $vRs->idRoom_rate->getStoredVal());

        $adjSel = $this->makeRateAdjustSel($vRs->idRateAdjust->getStoredVal());

        if ($this->isAllowed) {
            // add change rate selector

            $rateCat = array(0=>'',1=>'');
            if (isset($rateCategories[$vRs->Rate_Category->getStoredVal()])) {
                $rateCat = $rateCategories[$vRs->Rate_Category->getStoredVal()];
            }

            $visitStart = 'Start of Visit';
            if ($vRs->Span->getStoredVal() > 0) {
                $visitStart = 'Start of Visit Span (' . date('M d, Y', strtotime($vRs->Span_Start->getStoredVal())) . ')';
            }

            $rateTbl->addBodyTr(HTMLTable::makeTh(
                HTMLContainer::generateMarkup('label', 'Change Room Rate', array('for'=>'rateChgCB', 'style'=>'margin: 2px 1px;'))
                . HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'rateChgCB', 'class'=>'hhk-feeskeys', 'style'=>'margin-left: 1em;', 'title'=>'Change the room rate'))

                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), $rateCat[0], FALSE), array('name'=>'selRateCategory', 'class'=>'hhk-feeskeys'))
                .HTMLContainer::generateMarkup('span', 'Amt: $' . HTMLInput::generateMarkup($fixedRate, array('name'=>'txtFixedRate', 'class'=>'hhk-feeskeys', 'size'=>'4')), $attrFixed)
                . HTMLContainer::generateMarkup('span', 'Adj:'.$adjSel, $attrAdj), array('class'=>'changeRateTd', 'style'=>'display:none;'))
                .HTMLTable::makeTh('As Of: ', array('class'=>'changeRateTd', 'style'=>'display:none;'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('rpl', array('name'=>'rbReplaceRate', 'type'=>'radio', 'checked'=>'checked', 'class'=>'hhk-feeskeys'))
                    .HTMLContainer::generateMarkup('span', $visitStart, array('style'=>'margin-left:.3em;')), array('class'=>'changeRateTd', 'style'=>'display:none;'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('new', array('name'=>'rbReplaceRate', 'type'=>'radio', 'class'=>'hhk-feeskeys'))
                    .HTMLContainer::generateMarkup('span', 'Date', array('style'=>'margin-left:.3em; margin-right:.3em;'))
                    .HTMLInput::generateMarkup('', array('name'=>'chgRateDate', 'class'=>'hhk-feeskeys'))
                , array('class'=>'changeRateTd', 'style'=>'display:none;'))
                    .($this->incomeRated ? HTMLTable::makeTd(HTMLInput::generateMarkup('Income Chooser ...', array('type'=>'button', 'id' => 'btnFapp', 'data-rid'=>$vRs->idReservation->getStoredVal(), 'style'=>'margin:1px;'))
                , array('class'=>'changeRateTd', 'style'=>'display:none; padding: 1px 4px;')) : '')));

        }

        return $rateTbl;
    }

    /**
     * Summary of changeRoomRate
     * @param \PDO $dbh
     * @param \HHK\House\Visit\Visit $visit
     * @param mixed $post
     * @return string
     */
    public function changeRoomRate(\PDO $dbh, Visit $visit, $post) {

        $uS = Session::getInstance();
        $reply = '';
        $replaceMode = '';

        if ($this->isAllowed == FALSE) {
            return 'Not allowed to change room rates';
        }

        $visitRs = $visit->visitRS;
        $chRateDT = NULL;
        $departDT = null;
        $rateCategory = '';
        $rateAdj = 0;
        $adjAmtSelection = '0';
        $assignedRate = 0;
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $now = new \DateTime();
        $hr = $now->format('H');
        $min = $now->format('m');

        if (isset($post['rbReplaceRate'])) {
            $replaceMode = filter_var($post['rbReplaceRate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            return 'Replacement Mode not set.  ';
        }

        // Effective Date
        if ($replaceMode == 'new') {

            if (isset($post['chgRateDate']) && $post['chgRateDate'] != '') {

                $chDT = setTimeZone($uS, filter_var($post['chgRateDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                $chRateDT = new \DateTime($chDT->format('Y-m-d'));
                $chDT->setTime($hr, $min, 0);

            } else {
                $chRateDT = $today;
            }

        } else {
            // set date to start of span
            $chRateDT = new \DateTime($visitRs->Span_Start->getStoredVal());
        }

        if (is_null($chRateDT)) {
            return 'The Change Rate date is not set.  ';
        }


        // Find the departure date
        if ($visit->getVisitStatus() == VisitStatus::CheckedIn) {

            // Expected Departure date
            $departDT = $today;
            if ($visitRs->Expected_Departure->getStoredVal() != '') {
                $departDT = new \DateTime($visitRs->Expected_Departure->getStoredVal());
            }

            if ($departDT <= $today) {
                $departDT = new \DateTime($today->format('Y-m-d'));
                $departDT->add(new \DateInterval('P1D'));
            }

        } else if ($visitRs->Span_End->getStoredVal() != '') {

            $departDT = new \DateTime($visitRs->Span_End->getStoredVal());

        } else {
            return 'The visit Departure date cannot be found.  ';
        }


        // Span Start date
        $SpanStartDT = new \DateTime($visitRs->Span_Start->getStoredVal());


        $chRateDT->setTime(0, 0, 0);
        $departDT->setTime(0, 0, 0);
        $SpanStartDT->setTime(0, 0, 0);

        // Must be within visit timeframe
        if ($chRateDT < $SpanStartDT || $chRateDT >= $departDT) {
            return "The change rate date must be within the visit timeframe, between " . $SpanStartDT->format('M j, Y') . ' and ' . $departDT->format('M j, Y');
        }


        // Check rate change inputs
        if (isset($post['selRateCategory'])) {
            $rateCategory = filter_var($post['selRateCategory'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if ($rateCategory == '') {
            return "The new rate category is not set.  ";
        }

        if (isset($post['seladjAmount'])) {
            $adjAmtSelection = filter_var($post['seladjAmount'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (isset($uS->guestLookups['Room_Rate_Adjustment'][$adjAmtSelection])) {
                $rateAdj = $uS->guestLookups['Room_Rate_Adjustment'][$adjAmtSelection][2];
            }
        }

        if ($rateCategory == RoomRateCategories::Fixed_Rate_Category) {

            $rateAdj = 0;

            if (isset($post['txtFixedRate'])) {
                $assignedRate = filter_var($post['txtFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }
        }

        // Rates Changed?
        if ($visitRs->Rate_Category->getStoredVal() === $rateCategory) {

            // return if either amounts are set
            if ($rateCategory == RoomRateCategories::Fixed_Rate_Category) {

                if ($visitRs->Pledged_Rate->getStoredVal() == $assignedRate) {
                    return '';
                }
            } else if ($visitRs->Expected_Rate->getStoredVal() == $rateAdj) {
                return '';
            }
        }


        // Go ahead...
        if ($replaceMode == 'rpl' || $chRateDT == $SpanStartDT) {

            // Replace this span's rate - and any further spans not change rate themselves.
            $reply .= Visit::replaceRoomRate($dbh, $visitRs, $rateCategory, $assignedRate, $rateAdj, $adjAmtSelection, $uS->username);

        } else if ($visitRs->Status->getStoredVal() == VisitStatus::CheckedIn) {

            // Add a new rate span to the end this visit
            $reply .= $visit->changePledgedRate($dbh, $rateCategory, $assignedRate, $rateAdj, $adjAmtSelection, $chDT);

        } else {

            if ($chRateDT == $departDT) {
                return 'We cannot change the room rate on the last day of the visit span as there are no nights left for which to charge the new rate.';
            }
            // Split existing visit span into two
            $reply = $this->splitVisitSpan($dbh, $visit, $rateCategory, $assignedRate, $rateAdj, $adjAmtSelection, $uS->username, $chDT);

        }

        return $reply;

    }

    /**
     * Summary of splitVisitSpan
     * @param \PDO $dbh
     * @param \HHK\House\Visit\Visit $visit
     * @param string $rateCategory
     * @param float|int $assignedRate
     * @param float|int $rateAdj
     * @param string $uname
     * @param \DateTime $changeDT
     * @return string
     */
    protected function splitVisitSpan(\PDO $dbh, Visit $visit, $rateCategory, $assignedRate, $rateAdj, $idRateAdjust, $uname, \DateTime $changeDT) {

        $reply = '';
        $idVisit = $visit->getIdVisit();

        // get all the spans ordered by spanId, declining.
        $vrss = new VisitRs();
        $vrss->idVisit->setStoredVal($idVisit);
        $spans = EditRS::select($dbh, $vrss, array($vrss->idVisit), 'and', array($vrss->Span), FALSE);


        foreach ($spans as $s) {

            $spanRs = new VisitRs();
            EditRS::loadRow($s, $spanRs);
            $spanId = $spanRs->Span->getStoredVal();

            if ($spanId > $visit->getSpan()) {

                // Increment the Visit span id
                $upcount = $dbh->exec("UPDATE `visit` SET `Span`= '" . ($spanId + 1) . "' WHERE `idVisit`='$idVisit' and `Span`='$spanId'");

                if ($upcount != 1) {
                    $reply .= "Error on visit update, span Id = " . $spanId;
                    continue;
                }

                // update stays span id
                $stayRs = new StaysRS();
                $stayRs->idVisit->setStoredVal($visit->getIdVisit());
                $stayRs->Visit_Span->setStoredVal($spanId);
                $stys = EditRS::select($dbh, $stayRs, array($stayRs->idVisit, $stayRs->Visit_Span));

                foreach ($stys as $stay) {

                    $stayRs = new StaysRS();
                    EditRS::loadRow($stay, $stayRs);

                    $stayRs->Visit_Span->setNewVal($spanId + 1);
                    $stayRs->Updated_By->setNewVal($uname);
                    $stayRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

                    EditRS::update($dbh, $stayRs, array($stayRs->idStays));
                    $logText = VisitLog::getUpdateText($stayRs);
                    VisitLog::logStay($dbh, $visit->getIdVisit(), ($spanId + 1), $stayRs->idRoom->getNewVal(), $stayRs->idStays->getStoredVal(), $stayRs->idName->getNewVal(), $visit->getIdRegistration(), $logText, "update", $uname);
                }
            }
        }

        // split the given span
        $reply .= $visit->changePledgedRate($dbh, $rateCategory, $assignedRate, $rateAdj, $idRateAdjust, $changeDT);

        return $reply;
    }

    /**
     * Summary of createCheckinMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param int $numNights
     * @param string $visitFeeTitle
     * @return string
     */
    public function createCheckinMarkup(\PDO $dbh, Reservation_1 $resv, $numNights, $visitFeeTitle) {

        $markup = $this->createBasicChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle, $resv->getIdRegistration());

        if ($this->incomeRated) {
            $markup .= HTMLInput::generateMarkup('Income Chooser ...', array('type'=>'button', 'id' => 'btnFapp', 'data-id'=>$resv->getIdGuest(), 'style'=>'margin:1em;'));
        }

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Rate Chooser', array('style'=>'font-weight:bold;'))
                . $markup, array('class'=>'hhk-panel')), array('style'=> 'display: inline-block', 'class'=>'mr-3'));

    }

    /**
     * Summary of createResvMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param int $numNights
     * @param string $visitFeeTitle
     * @param int $idRegistration
     * @return string
     */
    public function createResvMarkup(\PDO $dbh, Reservation_1 $resv, $numNights, $visitFeeTitle, $idRegistration) {

        // Get Resv status codes
        $reservStatuses = readLookups($dbh, "ReservStatus", "Code", TRUE);

        if ($resv->isActive($reservStatuses)) {

            $markup = $this->createBasicChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle, $idRegistration);

            if ($this->incomeRated) {
                $markup .= HTMLInput::generateMarkup('Income Chooser ...', array('type'=>'button', 'id' => 'btnFapp', 'data-id'=>$resv->getIdGuest(), 'style'=>'margin:1em;'));
            }

            return HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Rate Chooser', array('style'=>'font-weight:bold;'))
                    . $markup, array('style'=>'display: inline-block;', 'class'=>'hhk-panel mr-3'));

        } else {
            return $this->createStaticMarkup($dbh, $resv, $visitFeeTitle);
        }

    }

    /**
     * Summary of createStaticMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param string $visitFeeTitle
     * @return string
     */
    protected function createStaticMarkup(\PDO $dbh, Reservation_1 $resv, $visitFeeTitle) {

        $uS = Session::getInstance();

        if($uS->VisitFee && ($resv->getExpectedDaysDt(new \DateTime($resv->getArrival()), new \DateTime($resv->getDeparture())) > $uS->VisitFeeDelayDays)){
            $this->payVisitFee = TRUE;
        }else{
            $this->payVisitFee = FALSE;
        }

        $tbl = new HTMLTable();

        // Check for rate glide
        $dayCredit = self::setRateGlideDays($dbh, $resv->getIdRegistration(), $this->rateGlideExtend);
        $this->priceModel->setCreditDays($dayCredit);

        $amount = $resv->getAdjustedTotal($this->priceModel->amountCalculator($resv->getExpectedDays(), $resv->getIdRoomRate(), $resv->getRoomRateCategory(), $resv->getFixedRoomRate()));

        $rateCategories = RoomRate::makeSelectorOptions($this->priceModel, $resv->getIdRoomRate());
        $rate = $rateCategories[$resv->getRoomRateCategory()][1];

        $tbl->addBodyTr(HTMLTable::makeTd('Room Rate', array('class'=>'tdlabel'))
             . HTMLTable::makeTd($rate, array('style'=>'text-align:center;')));

        $tbl->addBodyTr(HTMLTable::makeTd('Estimated Nights', array('class'=>'tdlabel'))
             . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $resv->getExpectedDays(), array('name'=>'spnNites')), array('style'=>'text-align:center;')));

        if ($resv->getRateAdjust() != 0) {
            $amount = (1 + $resv->getRateAdjust() / 100) * $amount;
            $tbl->addBodyTr(HTMLTable::makeTd('Adjust', array('class'=>'tdlabel'))
                 . HTMLTable::makeTd( number_format($resv->getRateAdjust(), 0) . '%', array('style'=>'text-align:center;')));
        }

        if ($this->payVisitFee) {

            $vFeeMkup = $this->makeVisitFeeSelector($this->makeVisitFeeArray($dbh), $resv->getVisitFee());

            $tbl->addBodyTr(HTMLTable::makeTd('Estimated Room Amount', array('class'=>'tdlabel'))
                 . HTMLTable::makeTd('$'. HTMLContainer::generateMarkup('span', number_format($amount,2), array('name'=>'spnLodging'))
                         , array('style'=>'text-align:right;border-top: solid 3px #2E99DD;')));

            $tbl->addBodyTr(HTMLTable::makeTd($visitFeeTitle, array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($resv->getVisitFee(),2), array('style'=>'text-align:right;'))
                    . HTMLContainer::generateMarkup('span', $vFeeMkup, array('style'=>'display:none;')));
        }

        $tbl->addBodyTr(HTMLTable::makeTd('Estimated Total Amount', array('class'=>'tdlabel'))
             . HTMLTable::makeTd('$'. HTMLContainer::generateMarkup('span', number_format($amount + $resv->getVisitFee(), 2), array('name'=>'spnAmount')), array('style'=>'text-align:right;font-weight:bold;border-top: solid 3px #2E99DD;')));

        // Add mention of rate glide credit days
        if ($dayCredit > 0) {
            $tbl->addBodyTr(HTMLTable::makeTd('(Estimated Total based on ' . $dayCredit . ' days of room rate glide.)', array('colspan'=>'4')));
        }

        return $tbl->generateMarkup();

    }

    /**
     * Summary of makeRateArray
     * @param \PDO $dbh
     * @param int $numNights
     * @param int $idRegistration
     * @param float|int $pledgedRate
     * @param int $guestNites
     * @return array<float>
     */
    public function makeRateArray(\PDO $dbh, $numNights, $idRegistration, $pledgedRate = 0, $guestNites = 0) {
        // category, rate

        $catAmounts = array();

        // Check for rate glide
        $this->priceModel->setCreditDays(self::setRateGlideDays($dbh, $idRegistration, $this->rateGlideExtend));

        foreach ($this->priceModel->getActiveModelRoomRates() as $r) {

            $catAmounts[$r->FA_Category->getStoredVal()] = $this->priceModel->amountCalculator($numNights, 0, $r->FA_Category->getStoredVal(), $pledgedRate, $guestNites);

        }

        return $catAmounts;
    }

    /**
     * Summary of makeVisitFeeArray
     * @param \PDO $dbh
     * @param float|int $visitFeeCharged
     * @return array
     */
    public function makeVisitFeeArray(\PDO $dbh, $visitFeeCharged = 0) {

        $codes = array();

        foreach (readGenLookupsPDO($dbh, 'Visit_Fee_Code') as $r) {

            if ($r['Type'] != GLTypeCodes::Archive || $visitFeeCharged == $r['Substitute']) {
                $codes[$r['Code']] = $r;
            }
        }

        return $codes;
    }

    /**
     * Summary of makeVisitFeeSelector
     * @param array $vFeesArray
     * @param float|int $visitFeeCharged
     * @param string $class
     * @param string $name
     * @return string
     */
    public function makeVisitFeeSelector($vFeesArray, $visitFeeCharged, $class = '', $name = 'selVisitFee') {

        $uS = Session::getInstance();

        $vFeeOpts = array();
        $selectedVfeeOption = $uS->DefaultVisitFee;

        foreach ($vFeesArray as $r) {
            $vFeeOpts[$r[0]] = array(0=>$r[0], 1=>$r[1] . ($r[2] == 0 ? '' :  ': $' . number_format($r[2], 0)));
            if ($visitFeeCharged == $r[2]) {
                $selectedVfeeOption = $r[0];
            }
        }

        return HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($vFeeOpts, $selectedVfeeOption, FALSE), array('name'=>$name, 'class'=>$class));
    }

    /**
     * Summary of setRateGlideDays
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param mixed $rateGlideExtend
     * @return int
     */
    public static function setRateGlideDays(\PDO $dbh, $idRegistration, $rateGlideExtend) {

        $dayCredit = 0;

//        if ($rateGlideExtend > 0 && $idRegistration > 0) {
//
//            $ext = intval($rateGlideExtend, 10);
//
//            $stmt = $dbh->query("select
//    DATEDIFF(`Span_End`,  `Span_Start`) + `Rate_Glide_Credit` as `Actual_Nights`
//from
//    `visit`
//where
//    `idRegistration` = $idRegistration
//        and `Status` = 'co'
//        and Actual_Departure in (select
//            max(Actual_Departure)
//        from
//            visit
//        where
//            `idRegistration` = $idRegistration
//                and `Status` = 'co'
//                and `Actual_Departure` > (now() - INTERVAL $ext DAY));");
//
//            $visits = $stmt->fetchall(PDO::FETCH_NUM);
//
//            if (count($visits) > 0) {
//                $dayCredit = $visits[0][0];
//            }
//        }

        return $dayCredit;

    }

    /**
     * Summary of createBasicChooserMarkup
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param int $nites
     * @param string $visitFeeTitle
     * @param int $idRegistration
     * @return string
     */
    protected function createBasicChooserMarkup(\PDO $dbh, Reservation_1 $resv, $nites, $visitFeeTitle, $idRegistration) {

        $uS = Session::getInstance();

        if($uS->VisitFee && ($resv->getExpectedDaysDt(new \DateTime($resv->getArrival()), new \DateTime($resv->getDeparture())) > $uS->VisitFeeDelayDays)){
                $this->payVisitFee = TRUE;
        }else{
                $this->payVisitFee = FALSE;
        }

        $roomRateCategory = $resv->getRoomRateCategory();

        // Rate not set yet?
        if ($resv->getRoomRateCategory() == '') {

            // First assign house default
            $roomRateCategory = $uS->RoomRateDefault;

            // Next, Look for an approved rate
            if ($idRegistration > 0 && $uS->IncomeRated) {

                $fin = new FinAssistance($dbh, $idRegistration);

                if ($fin->isApproved() && $fin->getFaCategory() != '') {
                    $roomRateCategory = $fin->getFaCategory();
                }
            }
        }

        $attrFixed = array('class'=>'hhk-fxFixed');
        $attrFixedInput = array("name"=>"txtFixedRate", "size"=>"4");
        if($uS->RoomPriceModel == ItemPriceCode::None){
            $attrAdj = array('style'=>'display:none;');
        }else{
            $attrAdj = array('class'=>'hhk-fxAdj', 'style'=>'');
        }

        // Fixed rate?
        if ($roomRateCategory == DefaultSettings::Fixed_Rate_Category) {

            $attrAdj['style'] .= 'display:none;';
            $fixedRate = $resv->getFixedRoomRate() == 0 ? '' : (number_format($resv->getFixedRoomRate(), 2));

        } else {

            $attrFixed['style'] = 'display:none;';
            $fixedRate = '';
        }

        if($uS->RoomPriceModel == ItemPriceCode::None){
            $attrAdj['style'] .= 'display:none;';
        }

        $vFeeMkup = '';

        if ($this->payVisitFee) {
            $vFeeMkup = $this->makeVisitFeeSelector($this->makeVisitFeeArray($dbh), $resv->getVisitFee());
        }

        $rateCategories = RoomRate::makeSelectorOptions($this->priceModel, $resv->getIdRoomRate());
        $rateSelectorAttrs = array('name'=>'selRateCategory', 'style'=>'display:table;');

        if ($this->isAllowed === FALSE) {
            $rateSelectorAttrs['disabled'] = 'disabled';
            $attrAdj['disabled'] = 'disabled';
            $attrFixedInput['disabled'] = 'disabled';
        }

        // Get taxed items
        $vat = new ValueAddedTax($dbh);
        $taxedItems = $vat->getTaxedItemSums(0, 0);
        $tax = (isset($taxedItems[ItemId::Lodging]) ? $taxedItems[ItemId::Lodging] : 0);

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(
            ($this->payVisitFee ? HTMLTable::MakeTh($visitFeeTitle) : '')
            .($uS->RoomPriceModel != ItemPriceCode::None ? HTMLTable::makeTh('Room Rate')
                .HTMLTable::makeTh('Adjustment', $attrAdj) : '')
            .HTMLTable::makeTh('Nights')
            .($this->payVisitFee || $tax > 0 ? HTMLTable::makeTh('Estimated Lodging') : '')
            .($tax > 0 ? HTMLTable::makeTh('Tax (' . TaxedItem::suppressTrailingZeros($tax*100).')') : '')
            .HTMLTable::makeTh('Estimated Total'));

        $rateSel = $this->makeRateSelControl(
                HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), $roomRateCategory, FALSE), $rateSelectorAttrs),
                HTMLContainer::generateMarkup('span', '$' . HTMLInput::generateMarkup($fixedRate, $attrFixedInput), $attrFixed));

        $adjSel = $this->makeRateAdjustSel($resv->getIdRateAdjust(), $resv->getRateAdjust());

        $tbl->addBodyTr(
                ($this->payVisitFee ? HTMLTable::makeTd($vFeeMkup, array('style'=>'text-align:center;')) : '')
            .HTMLTable::makeTd($rateSel, array('style'=>($uS->RoomPriceModel == ItemPriceCode::None ? 'display:none;':'')))
                . HTMLTable::makeTd($adjSel, $attrAdj)
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $nites, array('name'=>'spnNites')), array('style'=>'text-align:center;'))
                . ($this->payVisitFee || $tax > 0 ? HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('name'=>'spnLodging')), array('style'=>'text-align:center;')) : '')
                . ($tax > 0 ? HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('name'=>'spnRcTax', 'data-tax'=>$tax)), array('style'=>'text-align:center;')) : '')
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('name'=>'spnAmount')), array('style'=>'text-align:center;'))
                );

        return $tbl->generateMarkup();

    }

    /**
     * Summary of makeRateSelControl
     * @param string $selector
     * @param string $fixed
     * @return string
     */
    protected function makeRateSelControl($selector, $fixed) {

        $tbl = new HTMLTable();
        $tbl->addBodyTr(
            $tbl->makeTd($selector, array('style'=>'border-style:none;padding:0;'))
            .$tbl->makeTd($fixed, array('style'=>'border-style:none;padding:0;'))
        );

        return $tbl->generateMarkup(array('style'=>'border-style:none;padding:0;'));
    }

    /**
     * Summary of makeRateAdjustSel
     * @param string $sel
     * @param float|int $amt
     * @return string
     */
    protected function makeRateAdjustSel($sel = '', $amt = 0){
        $uS = Session::getInstance();
        $adjustments = (isset($uS->guestLookups['Room_Rate_Adjustment']) ? $uS->guestLookups['Room_Rate_Adjustment'] : array());

        if(($sel == '' || $sel == '0') && $amt < 0){
            $options = HTMLContainer::generateMarkup('option', '', array('value'=>'0', 'data-amount'=>'0'));
            $options .= HTMLContainer::generateMarkup('option', abs($amt) . '% off*', array('value'=>'keyed', 'data-amount'=>$amt, 'selected'=>'selected'));
        }else if($sel == '' || $sel == '0'){
            $options = HTMLContainer::generateMarkup('option', '', array('value'=>'', 'data-amount'=>'0', 'selected'=>'selected'));
        }else{
            $options = HTMLContainer::generateMarkup('option', '', array('value'=>'', 'data-amount'=>'0'));
        }

        foreach($adjustments as $adjust){
            if($sel == $adjust[0]){
                $options .= HTMLContainer::generateMarkup('option', $adjust[1], array('value'=>$adjust[0], 'data-amount'=>$adjust[2], 'selected'=>'selected'));
            }else{
                $options .= HTMLContainer::generateMarkup('option', $adjust[1], array('value'=>$adjust[0], 'data-amount'=>$adjust[2]));
            }
        }

        return HTMLSelector::generateMarkup($options, array('id'=>'seladjAmount', 'name'=>'seladjAmount', 'class'=>'hhk-feeskeys'));
    }

}
?>