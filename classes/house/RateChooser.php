<?php
/**
 * RateChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
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
    protected $isAdmin;
    protected $payVisitFee;
    protected $openCheckin;
    protected $rateGlideExtend;

    /**
     *
     * @var PriceModel
     */
    protected $priceModel;
    /**
     * @var \array
     */


    public function __construct(\PDO $dbh) {

        $uS = Session::getInstance();

        $this->incomeRated = $uS->IncomeRated;
        $this->payAtCheckin = $uS->PayAtCkin;
        $this->openCheckin = $uS->OpenCheckin;
        $this->payVisitFee = $uS->VisitFee;
        $this->isAdmin = SecurityComponent::is_Authorized('guestadmin');
        $this->rateGlideExtend = $uS->RateGlideExtend;
        $this->priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

    }

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

    public function getPriceModel() {
        return $this->priceModel;
    }

    public function createChangeRateMarkup(\PDO $dbh, \VisitRs $vRs, $isAdmin = FALSE) {

        $attrFixed = array('class'=>'hhk-fxFixed', 'style'=>'margin-left:.5em; ');
        $attrAdj = array('class'=>'hhk-fxAdj', 'style'=>'text-align:center;margin-left:.7em;');
        $fixedRate = '';
        $rateTitle = '';
        $rateAdjust = '';

        //
        if ($vRs->Rate_Category->getStoredVal() == Default_Settings::Fixed_Rate_Category) {
            // Fixed rate
            $attrAdj['style'] .= 'display:none;';
            $fixedRate = $vRs->Pledged_Rate->getStoredVal() == 0 ? '0' : (number_format($vRs->Pledged_Rate->getStoredVal(), 2));
            $rateTitle = ',  Amt: $' . $fixedRate;

        } else {

            $adj = floatval($vRs->Expected_Rate->getStoredVal());
            $adjRatio = (1 + $adj/100);
            $rateAdjust = ($adjRatio == 1 ? '' : number_format($adj, 0));
            $rateTitle = ($rateAdjust == '' ? '' : ',  Adj:' . $adj . '%');
            $attrFixed['style'] .= 'display:none;';

        }

        $rateCategories = RoomRate::makeSelectorOptions($this->priceModel, $vRs->idRoom_rate->getStoredVal());

        $rateTbl = new HTMLTable();

        if ($isAdmin) {
            // add change rate selector

            $rateCat = array(0=>'',1=>'');
            if (isset($rateCategories[$vRs->Rate_Category->getStoredVal()])) {
                $rateCat = $rateCategories[$vRs->Rate_Category->getStoredVal()];
            }

            $visitStart = 'Start of Visit';
            if ($vRs->Span->getStoredVal() > 0) {
                $visitStart = 'Start of Visit Span (' . date('M d, Y', strtotime($vRs->Span_Start->getStoredVal())) . ')';
            }


            $rateTbl->addBodyTr(HTMLTable::makeTh('Room Rate ('
                . HTMLContainer::generateMarkup('label', 'Edit', array('for'=>'rateChgCB', 'style'=>'margin-right:.5em;'))
                . HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'rateChgCB', 'class'=>'hhk-feeskeys'))
                . ')')

                .HTMLTable::makeTd($rateCat[1] . $rateTitle, array('id'=>'showRateTd'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), $rateCat[0], FALSE), array('name'=>'selRateCategory', 'class'=>'hhk-feeskeys'))
                .HTMLContainer::generateMarkup('span', 'Amt: $' . HTMLInput::generateMarkup($fixedRate, array('name'=>'txtFixedRate', 'class'=>'hhk-feeskeys', 'size'=>'4')), $attrFixed)
                .HTMLContainer::generateMarkup('span', 'Adj:'.HTMLInput::generateMarkup($rateAdjust, array('name'=>'txtadjAmount', 'class'=>'hhk-feeskeys', 'size'=>'2')) . '%', $attrAdj), array('class'=>'changeRateTd', 'style'=>'display:none;'))

                .HTMLTable::makeTh('As Of: ', array('class'=>'changeRateTd', 'style'=>'display:none;'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('rpl', array('name'=>'rbReplaceRate', 'type'=>'radio', 'checked'=>'checked', 'class'=>'hhk-feeskeys'))
                    .HTMLContainer::generateMarkup('span', $visitStart, array('style'=>'margin-left:.3em;')), array('class'=>'changeRateTd', 'style'=>'display:none;'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('new', array('name'=>'rbReplaceRate', 'type'=>'radio', 'class'=>'hhk-feeskeys'))
                    .HTMLContainer::generateMarkup('span', 'Date', array('style'=>'margin-left:.3em; margin-right:.3em;'))
                    .HTMLInput::generateMarkup('', array('name'=>'chgRateDate', 'class'=>'hhk-feeskeys'))
                , array('class'=>'changeRateTd', 'style'=>'display:none;'))
                    .($this->incomeRated ? HTMLTable::makeTd(HTMLInput::generateMarkup('Income Chooser ...', array('type'=>'button', 'id' => 'btnFapp', 'data-rid'=>$vRs->idReservation->getStoredVal(), 'style'=>'margin:1px;'))
                , array('class'=>'changeRateTd', 'style'=>'display:none;')) : ''));


        } else {
            $rateTbl->addBodyTr(HTMLTable::makeTh('Room Rate')
                .HTMLTable::makeTd($rateCategories[$vRs->Rate_Category->getStoredVal()][1] . $rateTitle));
        }

        return $rateTbl;
    }

    public function changeRoomRate(\PDO $dbh, Visit $visit, $post) {

        $uS = Session::getInstance();
        $reply = '';
        $replaceMode = '';

        $visitRs = $visit->visitRS;
        $chRateDT = NULL;
        $departDT = null;
        $rateCategory = '';
        $rateAdj = 0;
        $assignedRate = 0;
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $now = new DateTime();
        $hr = $now->format('H');
        $min = $now->format('m');

        if (isset($post['rbReplaceRate'])) {
            $replaceMode = filter_var($post['rbReplaceRate'], FILTER_SANITIZE_STRING);
        } else {
            return 'Replacement Mode not set.  ';
        }

        // Effective Date
        if ($replaceMode == 'new') {

            if (isset($post['chgRateDate']) && $post['chgRateDate'] != '') {

                $chDT = setTimeZone($uS, filter_var($post['chgRateDate'], FILTER_SANITIZE_STRING));
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
            return 'The Change Rate date not set.  ';
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
            return 'The Departure date cannot be found.  ';
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
            $rateCategory = filter_var($post['selRateCategory'], FILTER_SANITIZE_STRING);
        }

        if ($rateCategory == '') {
            return "The new rate category is nissing.  ";
        }

        if (isset($post['txtadjAmount'])) {
            $rateAdj = intval(filter_var($post['txtadjAmount'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($rateCategory == RoomRateCategorys::Fixed_Rate_Category) {

            $rateAdj = 0;

            if (isset($post['txtFixedRate'])) {
                $assignedRate = filter_var($post['txtFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }
        }

        // Rates Changed?
        if ($visitRs->Rate_Category->getStoredVal() === $rateCategory) {
            // return if either amounts are set
            if ($rateCategory == RoomRateCategorys::Fixed_Rate_Category) {
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
            $reply .= Visit::replaceRoomRate($dbh, $visitRs, $rateCategory, $assignedRate, $rateAdj, $uS->username);

        } else if ($visitRs->Status->getStoredVal() == VisitStatus::CheckedIn) {

            // Add a new rate span to the end this visit
            $reply .= $visit->changePledgedRate($dbh, $rateCategory, $assignedRate, $rateAdj, $uS->username, $chDT, ($uS->RateGlideExtend > 0 ? TRUE : FALSE));

        } else {

            if ($chRateDT == $departDT) {
                return 'We cannot change the room rate on the last day of the visi span as there are no nights left for which to charge the new rate.';
            }
            // Split existing visit span into two
            $reply = $this->splitVisitSpan($dbh, $visit, $rateCategory, $assignedRate, $rateAdj, $uS->username, $chDT);

        }

        return $reply;

    }

    protected function splitVisitSpan(\PDO $dbh, Visit $visit, $rateCategory, $assignedRate, $rateAdj, $uname, \DateTime $changeDT) {

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
        $reply .= $visit->changePledgedRate($dbh, $rateCategory, $assignedRate, $rateAdj, $uname, $changeDT);

        return $reply;
    }



    public function createCheckinMarkup(\PDO $dbh, \Reservation_1 $resv, $numNights, $visitFeeTitle) {

        // Select payment block
        if ($this->payAtCheckin) {

            if ($this->incomeRated) {

                $markup = $this->createIncomeChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle);

            } else {

                $markup = $this->createBasicChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle);
            }

            return HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Rate Chooser', array('style'=>'font-weight:bold;'))
                    . $markup);

        } else {
            return $this->createStaticMarkup($dbh, $resv, $visitFeeTitle);
        }

    }

    public function createResvMarkup(\PDO $dbh, \Reservation_1 $resv, $numNights, $visitFeeTitle) {

        if ($resv->isActive()) {

            if ($this->incomeRated) {

                $markup = $this->createIncomeChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle);

            } else {

                $markup = $this->createBasicChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle);
            }

            return HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', 'Rate Chooser', array('style'=>'font-weight:bold;'))
                    . $markup, array('style'=>'float:left;', 'class'=>'hhk-panel'));

        } else {
            return $this->createStaticMarkup($dbh, $resv, $visitFeeTitle);
        }

    }

    protected function createIncomeChooserMarkup($dbh, \Reservation_1 $resv, $numNights, $visitFeeTitle) {

        $markup = $this->createBasicChooserMarkup($dbh, $resv, $numNights, $visitFeeTitle);
        $markup .= HTMLInput::generateMarkup('Income Chooser ...', array('type'=>'button', 'id' => 'btnFapp', 'data-id'=>$resv->getIdGuest(), 'style'=>'margin:1em;'));

        return $markup;

    }

    protected function createStaticMarkup(\PDO $dbh, \Reservation_1 $resv, $visitFeeTitle) {

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
             . HTMLTable::makeTd($resv->getExpectedDays(), array('style'=>'text-align:center;')));

        if ($resv->getRateAdjust() != 0) {
            $amount = (1 + $resv->getRateAdjust() / 100) * $amount;
            $tbl->addBodyTr(HTMLTable::makeTd('Adjust', array('class'=>'tdlabel'))
                 . HTMLTable::makeTd( number_format($resv->getRateAdjust(), 0) . '%', array('style'=>'text-align:center;')));
        }

        if ($this->payVisitFee) {

            $tbl->addBodyTr(HTMLTable::makeTd('Estimated Room Amount', array('class'=>'tdlabel'))
                 . HTMLTable::makeTd('$'. number_format($amount,2), array('style'=>'text-align:right;border-top: solid 3px #2E99DD;')));

            $tbl->addBodyTr(HTMLTable::makeTd($visitFeeTitle, array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'.number_format($resv->getVisitFee(),2), array('style'=>'text-align:right;')));
        }


        $tbl->addBodyTr(HTMLTable::makeTd('Estimated Total Amount', array('class'=>'tdlabel'))
             . HTMLTable::makeTd('$'. number_format($amount + $resv->getVisitFee(), 2), array('style'=>'text-align:right;font-weight:bold;border-top: solid 3px #2E99DD;')));

        // Add mention of rate glide credit days
        if ($dayCredit > 0) {
            $tbl->addBodyTr(HTMLTable::makeTd('(Estimated Total based on ' . $dayCredit . ' days of room rate glide.)', array('colspan'=>'4')));
        }


        return HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Rate Info', array('style'=>'font-weight:bold;'))
                . $tbl->generateMarkup());

    }


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

    public function makeRoomsArray(RoomChooser $roomChooser, $staticRoomRates, $keyDepCodes, $overRideMaxOccs = 0) {

        $resources = $roomChooser->resv->getAvailableResources();
        $resArray = array();

        foreach ($resources as $rc) {

            if ($roomChooser->getSelectedResource() != NULL && $rc->getIdResource() == $roomChooser->getSelectedResource()->getIdResource()) {
                $assignedRate = $roomChooser->resv->getFixedRoomRate();
            } else {
                $assignedRate = $rc->getRate($staticRoomRates);
            }

            $resArray[$rc->getIdResource()] = array(
                "maxOcc" => ($overRideMaxOccs == 0 ? $rc->getMaxOccupants() : $overRideMaxOccs),
                "rate" => $assignedRate,
                "title" => $rc->getTitle(),
                'key' => $rc->getKeyDeposit($keyDepCodes),
                'status' => 'a'
            );
        }

        // Blank
        $resArray['0'] = array(
            "maxOcc" => 0,
            "rate" => 0,
            "title" => '',
            'key' => 0,
            'status' => ''
        );

        return $resArray;
    }

    public static function makeVisitFeeArray(\PDO $dbh) {

        return readGenLookupsPDO($dbh, 'Visit_Fee_Code');
    }

    public static function makeVisitFeeSelector($vFeesArray, $myVFeeAmt, $class = '', $name = 'selVisitFee') {

        $vFeeOpts = array();
        $selectedVfeeOption = '1';

        foreach ($vFeesArray as $r) {
            $vFeeOpts[$r[0]] = array(0=>$r[0], 1=>$r[1] . ($r[2] == 0 ? '' :  ': $' . number_format($r[2], 0)));
            if ($myVFeeAmt == $r[2]) {
                $selectedVfeeOption = $r[0];
            }
        }

        return HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($vFeeOpts, $selectedVfeeOption, FALSE), array('name'=>$name, 'class'=>$class));
    }


    public static function setRateGlideDays(\PDO $dbh, $idRegistration, $rateGlideExtend) {

        $dayCredit = 0;

        if ($rateGlideExtend > 0 && $idRegistration > 0) {

            $ext = intval($rateGlideExtend, 10);

            $stmt = $dbh->query("select
    DATEDIFF(`Span_End`,  `Span_Start`) + `Rate_Glide_Credit` as `Actual_Nights`
from
    `visit`
where
    `idRegistration` = $idRegistration
        and `Status` = 'co'
        and Actual_Departure in (select
            max(Actual_Departure)
        from
            visit
        where
            `idRegistration` = $idRegistration
                and `Status` = 'co'
                and `Actual_Departure` > (now() - INTERVAL $ext DAY));");

            $visits = $stmt->fetchall(PDO::FETCH_NUM);

            if (count($visits) > 0) {
                $dayCredit = $visits[0][0];
            }
        }

        return $dayCredit;
    }


    protected function createBasicChooserMarkup(\PDO $dbh, \Reservation_1 $resv, $nites, $visitFeeTitle) {

        // Check for rate glide
        $dayCredit = self::setRateGlideDays($dbh, $resv->getIdRegistration(), $this->rateGlideExtend);

        //
        // Javascript calculates the amount based on number of days and number of guests.
        //

        $attrFixed = array('class'=>'hhk-fxFixed', 'style'=>'margin-left:.5em; ');
        $attrAdj = array('class'=>'hhk-fxAdj', 'style'=>'text-align:center;');

        $fixedRate = '';

        // Fixed rate?
        if ($resv->getRoomRateCategory() == Default_Settings::Fixed_Rate_Category) {

            $attrAdj['style'] .= 'display:none;';
            $fixedRate = $resv->getFixedRoomRate() == 0 ? '' : (number_format($resv->getFixedRoomRate(), 2));

        } else {

            $attrFixed['style'] .= 'display:none;';
            $fixedRate = '';
        }

        $vFeeMkup = '';

        if ($this->payVisitFee) {
            $vFeeMkup = $this->makeVisitFeeSelector(self::makeVisitFeeArray($dbh), $resv->getVisitFee());
        }

        $rateCategories = RoomRate::makeSelectorOptions($this->priceModel, $resv->getIdRoomRate());
        $rateSelectorAttrs = array('name'=>'selRateCategory');

        if ($this->isAdmin === FALSE) {
            $rateSelectorAttrs['disabled'] = 'disabled';
        }

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(($this->payVisitFee ? HTMLTable::MakeTh($visitFeeTitle) : '')
            .HTMLTable::makeTh('Room Rate')
            .HTMLTable::makeTh('Adjustment', $attrAdj)
            .HTMLTable::makeTh('Estimated Nights')
            .($this->payVisitFee ? HTMLTable::makeTh('Estimated Lodging') : '')
            .HTMLTable::makeTh('Estimated Total'));

        $tbl->addBodyTr(
                ($this->payVisitFee ? HTMLTable::makeTd($vFeeMkup, array('style'=>'text-align:center;')) : '')
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($rateCategories), $resv->getRoomRateCategory(), FALSE), $rateSelectorAttrs)
                    .HTMLContainer::generateMarkup('span', '$' . HTMLInput::generateMarkup($fixedRate, array('name'=>'txtFixedRate', 'size'=>'4')), $attrFixed))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', HTMLInput::generateMarkup(($resv->getRateAdjust() == 0 ? '' : number_format($resv->getRateAdjust(), 0)), array('name'=>'txtadjAmount', 'size'=>'2')) . '%'), $attrAdj)
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $nites, array('name'=>'spnNites')), array('style'=>'text-align:center;'))
                . ($this->payVisitFee ? HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('name'=>'spnLodging')), array('style'=>'text-align:center;')) : '')
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('name'=>'spnAmount')), array('style'=>'text-align:center;'))
                );

        // Add mention of rate glide credit days
        if ($dayCredit > 0) {
            $tbl->addBodyTr(HTMLTable::makeTd('(Estimated Total based on ' . $dayCredit . ' days of room rate glide.)', array('colspan'=>'4')));
        }

        return $tbl->generateMarkup();

    }

}
