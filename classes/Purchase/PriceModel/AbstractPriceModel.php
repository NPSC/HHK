<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{RateStatus, RoomRateCategories, ItemPriceCode};
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\Room_RateRS;
use HHK\HTMLControls\{HTMLTable, HTMLInput};
use HHK\Exception\RuntimeException;

/**
 * AbstractPriceModel.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AbstractPriceModel
 *
 * @author Eric
 */
abstract class AbstractPriceModel {

    protected $creditDays = 0;
    protected $glideApplied = FALSE;
    protected $roomRates;
    protected $activeRoomRates;
    protected $remainderAmt = 0;
    protected $visitStatus = '';
    protected $priceModelCode = '';
    public $hasPerGuestCharge = FALSE;


    public function __construct(array $roomRates) {

        $this->roomRates = $roomRates;
        $this->activeRoomRates = array();


        foreach($roomRates as $rs) {

            if ($rs->Status->getStoredVal() == RateStatus::Active) {
                $this->activeRoomRates[$rs->idRoom_rate->getStoredVal()] = $rs;
            }
        }
    }

    public function loadVisitNights(\PDO $dbh, $idVisit) {

        // Get current nights .
        $stmt1 = $dbh->query("select * from `vvisit_stmt` where `idVisit` = $idVisit and `Status` != 'p' order by `Span`");

        return $stmt1->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function loadRegistrationNights(\PDO $dbh, $idRegistration) {

        // Get current nights .
        $stmt1 = $dbh->query("select * from `vvisit_stmt` where `idRegistration` = $idRegistration and `Status` != 'p' order by `idVisit`, `Span`");

        return $stmt1->fetchAll(\PDO::FETCH_ASSOC);
    }

    public abstract function amountCalculator($nites, $idRoomRate, $rateCatetgory = '', $pledgedRate = 0, $guestDays = 0);

    public function daysPaidCalculator($amount, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $aveGuestPerDay = 1) {

        $this->remainderAmt = 0;

        if ($amount == 0) {
            return 0;
        }

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate f
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            if ($pledgedRate > 0) {

                $days = floor($amount / $pledgedRate);
                $this->remainderAmt = $amount - ($amount * $days);
                return $days;
            }
            return 0;
        }

        // Flat rate
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {
            if ($rrateRs->Reduced_Rate_1->getStoredVal() > 0) {
                $rate = (1 + $rateAdjust / 100) * $rrateRs->Reduced_Rate_1->getStoredVal();

                $days = floor($amount / $rate);
                $this->remainderAmt = $amount - ($amount * $days);
                return $days;
            }
            return 0;
        }

        $nights = 0;
        $trialAmt = 0;

        // stupid way:  try each value for days until one matches
        for ($i = 1; $i < 700; $i++) {

            $previousTrialAmt = $trialAmt;
            $trialAmt = (1 + $rateAdjust / 100) * $this->amountCalculator($i, $idRoomRate, $rrateRs->FA_Category->getStoredVal(), $pledgedRate);

            if ($trialAmt <= 0) {
                break;
            }

            if ($amount < $trialAmt) {
                $nights = $i - 1;
                $this->remainderAmt = $amount - $previousTrialAmt;
                break;
            }

            if ($amount == $trialAmt) {
                $nights = $i;
                $this->remainderAmt = 0;
                break;
            }

        }

        return $nights;
    }

    public function getCategoryRateRs($idRoomRate = 0, $category = '') {

    	if ($idRoomRate > 0 && isset($this->roomRates[$idRoomRate])) {

            return $this->roomRates[$idRoomRate];

        } else {

            // Default the category
            if ($category == '' || $category == RoomRateCategories::FullRateCategory) {
                $category = RoomRateCategories::FlatRateCategory;
            }

            foreach ($this->activeRoomRates as $rr) {

                if ($rr->FA_Category->getStoredVal() == $category) {
                    return $rr;
                }
            }
        }

        throw new RuntimeException('Unknown room rate category or Id.  ');

    }

    public function tiersCalculation($days, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $guestDays = 0) {

        $tiers = array();

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            $tiers[] = array('rate'=> $pledgedRate, 'days'=>$days, 'amt'=>($days * $pledgedRate));
            return $tiers;
        }

        $adjRatio = (1 + $rateAdjust/100);

        $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $days * $adjRatio;
        $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_1->getStoredVal() * $adjRatio, 'days'=>$days, 'amt'=>$amount);

        return $tiers;
    }

    public function tiersMarkup($r, &$totalAmt, &$tbl, $tiers, &$startDT, $separator, &$totalGuestNites) {

        $roomCharge = 0;

        foreach ($tiers as $t) {

            $totalAmt += $t['amt'];
            $roomCharge += $t['amt'];

            $tbl->addBodyTr(
                 HTMLTable::makeTd($r['vid'] . '-' . $r['span'], array('style'=>'text-align:center;' . $separator))
                .HTMLTable::makeTd($r['title'], array('style'=>$separator))
                .HTMLTable::makeTd($startDT->format('M j, Y'), array('style'=>$separator))
                .HTMLTable::makeTd($startDT->add(new \DateInterval('P' . $t['days'] . 'D'))->format('M j, Y'), array('style'=>$separator))
                .HTMLTable::makeTd(number_format($t['rate'], 2), array('style'=>'text-align:right;' . $separator))
                .HTMLTable::makeTd($t['days'], array('style'=>'text-align:center;' . $separator))
                .HTMLTable::makeTd(number_format($t['amt'], 2), array('style'=>'text-align:right;' . $separator))
            );

            $separator = '';

        }

        return $roomCharge;
    }

    public function tiersDetailMarkup($r, &$tbl, $tiers, $startDT, $separator, $totalGuestNites) {

    	$sDate = new \DateTime($startDT->format('Y-m-d'));

    	foreach ($tiers as $t) {

    		$days = $t['days'];

    		while ($days > 0) {
	    		$tbl->addBodyTr(
	    				HTMLTable::makeTd($r['vid'] . '-' . $r['span'], array('style'=>'text-align:center;' . $separator))
	    				.HTMLTable::makeTd($r['title'], array('style'=>$separator))
	    				.HTMLTable::makeTd($sDate->format('M j, Y'), array('style'=>$separator))
	    				.HTMLTable::makeTd(number_format($t['rate'], 2), array('style'=>'text-align:right;' . $separator))
	    				);

	    		$separator = '';
	    		$sDate->add(new \DateInterval('P1D'));

	    		$days--;
    		}
    	}

    	return;
    }

    public function itemDetailMarkup($r, &$tbl) {

    	$tbl->addBodyTr(
    			HTMLTable::makeTd($r['orderNum'], array('style'=>'text-align:center;'))
    			.HTMLTable::makeTd($r['desc'], array('style'=>'text-align:right;'))
    			.HTMLTable::makeTd($r['date'])
    			.HTMLTable::makeTd($r['amt'], array('style'=>'text-align:right;')));

    }

    public function itemMarkup($r, &$tbl) {

        $tbl->addBodyTr(
            HTMLTable::makeTd($r['orderNum'], array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
            .HTMLTable::makeTd($r['date'])
            .HTMLTable::makeTd($r['desc'], array('colspan'=>'3', 'style'=>'text-align:right;'))
            .HTMLTable::makeTd($r['amt'], array('style'=>'text-align:right;')));

    }

    public function rateHeaderMarkup(&$tbl, $labels) {
    	$tbl->addHeaderTr(
    			HTMLTable::makeTh('Visit Id')
    			.HTMLTable::makeTh('Room')
    			.HTMLTable::makeTh('Start')
    			.HTMLTable::makeTh('End')
    			.HTMLTable::makeTh($labels->getString('statement', 'rateHeader', 'Rate'))
    			.HTMLTable::makeTh('Nights')
    			.HTMLTable::makeTh($labels->getString('statement', 'chargeHeader', 'Charge')));

    }

    public function rateDetailHeaderMarkup(&$tbl, $labels) {
    	$tbl->addHeaderTr(
    			HTMLTable::makeTh('Visit Id')
    			.HTMLTable::makeTh('Room')
    			.HTMLTable::makeTh('Start')
    			.HTMLTable::makeTh($labels->getString('statement', 'chargeHeader', 'Charge')));

    }

    public function rateTotalMarkup(&$tbl, $label, $numberNites, $totalAmt, $guestNites) {

        // Room Fee totals
        $tbl->addBodyTr(HTMLTable::makeTd($label, array('colspan'=>'5', 'class'=>'tdlabel hhk-tdTotals', 'style'=>'font-weight:bold;'))
            .HTMLTable::makeTd($numberNites, array('class'=>'hhk-tdTotals', 'style'=>'text-align:center;font-weight:bold;'))
            .HTMLTable::makeTd('$'. $totalAmt, array('class'=>'hhk-tdTotals', 'style'=>'text-align:right;font-weight:bold;')));

    }

    public static function priceModelFactory(\PDO $dbh, $modelCode) {

        switch ($modelCode) {

            case ItemPriceCode::Basic:

                $pm = new PriceBasic(self::getModelRoomRates($dbh, $modelCode));
                $myRates = array();

                // override the stored room rates.
                foreach ($pm->getActiveModelRoomRates() as $r) {
                    if ($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
                        $myRates[$r->idRoom_rate->getStoredVal()] = $r;
                    }
                }

                if (count($myRates) != 1) {
                    throw new RuntimeException('Fixed Rate Category (f) is not set.  ');
                }

                $pm->activeRoomRates = $myRates;
                $pm->priceModelCode = $modelCode;
                return $pm;


            case ItemPriceCode::Dailey:
                $pm = new PriceDaily(self::getModelRoomRates($dbh, $modelCode));
                $pm->priceModelCode = $modelCode;
                return $pm;


            case ItemPriceCode::PerGuestDaily:
                $pm = new PriceGuestDay(self::getModelRoomRates($dbh, $modelCode));
                $pm->priceModelCode = $modelCode;
                return $pm;


            case ItemPriceCode::NdayBlock:
                $pm = new PriceNdayBlock(self::getModelRoomRates($dbh, $modelCode));
                $pm->priceModelCode = $modelCode;
                return $pm;


            case ItemPriceCode::PerpetualStep:
                $pm = new PricePerpetualSteps(self::getModelRoomRates($dbh, $modelCode));
                $pm->priceModelCode = $modelCode;
                return $pm;


            case ItemPriceCode::Step3:
                $pm = new Price3Steps(self::getModelRoomRates($dbh, $modelCode));
                $pm->periods = $pm->getRatePeriods($dbh);
                $pm->priceModelCode = $modelCode;
                return $pm;

            case ItemPriceCode::None:
                $pm = new PriceNone(self::getModelRoomRates($dbh, $modelCode));
                $pm->priceModelCode = $modelCode;
                return $pm;


            default:
                throw new RuntimeException('Price Model not defined. ');

        }
    }

    protected static function getModelRoomRates(\PDO $dbh, $priceModelCode) {

        // Room rates
        $stmt = $dbh->query("SELECT `idRoom_rate`,`Title`, `Description`, `FA_Category`, `Rate_Breakpoint_Category`, `Reduced_Rate_1`, `Reduced_Rate_2`, `Reduced_Rate_3`, `Min_Rate`, `Status`, IF(`Rate_Breakpoint_Category` != '', 1,0) as 'breakpointOrder'
FROM `room_rate`
where PriceModel = '$priceModelCode' order by `breakpointOrder` desc, idRoom_rate asc;");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $rrates = array();

        foreach ($rows as $r) {

            $rs = new Room_RateRS();
            EditRS::loadRow($r, $rs);
            $rrates[$rs->idRoom_rate->getStoredVal()] = $rs;
        }

        return $rrates;
    }

    public function getActiveModelRoomRates() {
        return $this->activeRoomRates;
    }

    public function getEditMarkup(\PDO $dbh, $defaultRoomRate = 'e') {

        $fTbl = new HTMLTable();
        $fTbl->addHeaderTr(
            HTMLTable::makeTh('Title')
            .HTMLTable::makeTh('Default')
            .HTMLTable::makeTh('Rate')
            .HTMLTable::makeTh('Retire')
            );

        // Room rates
        foreach ($this->roomRates as $r) {

            // Don't deal with non-active rates.
            if ($r->Status->getStoredVal() == RateStatus::NotActive) {
                continue;
            }

            $attrs = array('type'=>'radio', 'name'=>'rrdefault');
            $titleAttrs = array('name'=>'ratetitle['.$r->idRoom_rate->getStoredVal().']', 'size'=>'17');
            $rr1Attrs = array('name'=>'rr1['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');

            if ($r->FA_Category->getStoredVal() == $defaultRoomRate) {
                $attrs['checked'] = 'checked';
            } else {
                unset($attrs['checked']);
            }

            $cbRetire = '';
            if ($r->FA_Category->getStoredVal()[0] == RoomRateCategories::NewRate && $r->Rate_Breakpoint_Category->getStoredVal() == '') {  //RoomRateCategories::Fixed_Rate_Category && $r->FA_Category->getStoredVal() != RoomRateCategories::FlatRateCategory) {

                $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']'));

                if ($r->Status->getStoredVal() == RateStatus::Retired) {
                    // show as inactive
                    $attrs['disabled'] = 'disabled';
                    $titleAttrs['readonly'] = 'readonly';
                    $titleAttrs['style'] = 'background-color:#f0f0f0 ';
                    $rr1Attrs['disabled'] = 'disabled';

                    $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']', 'checked'=>'checked'));
                }

            }

            $fTbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($r->Title->getStoredVal(), $titleAttrs))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->FA_Category->getStoredVal(), $attrs))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_1->getStoredVal(), 2), $rr1Attrs), array('style'=>'text-align:center;')))
                .HTMLTable::makeTd($cbRetire, array('style'=>'text-align:center;'))
            );
        }

        // New rate
        $this->newRateMarkup($fTbl);

        return $fTbl;
    }

    protected function newRateMarkup(&$fTbl) {

        // New rate
        $fTbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'ratetitle[0]', 'size'=>'17')))
            .HTMLTable::makeTd('(New)')
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr1[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
        );

    }

    public function saveEditMarkup(\PDO $dbh, $post, $username) {

        $defaultRate = RoomRateCategories::Fixed_Rate_Category;

        if (isset($post['ratetitle'])) {

            $rr1s = filter_var_array($post['rr1'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
            $titles = filter_var_array($post['ratetitle'], FILTER_SANITIZE_STRING);
            $rr2s = array();
            $rr3s = array();
            $mins = array();

            if (isset($post['rr2'])) {
                $rr2s = filter_var_array($post['rr2'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
            }
            if (isset($post['rr3'])) {
                $rr3s = filter_var_array($post['rr3'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
            }
            if (isset($post['minrt'])) {
                $mins = filter_var_array($post['minrt'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
            }

            if (isset($post['rrdefault'])) {
                $defaultRate = filter_var($post['rrdefault'], FILTER_SANITIZE_STRING);
            }

            if (isset($post['cbRetire'])) {
                $retires = filter_var_array($post['cbRetire'], FILTER_SANITIZE_STRING);
            }

            foreach ($this->roomRates as $oldRs) {

                // Don't deal with non-active rates.
                if ($oldRs->Status->getStoredVal() == RateStatus::NotActive) {
                    continue;
                }

                $idRoomRate = $oldRs->idRoom_rate->getStoredVal();

                if (isset($post['ratetitle'][$idRoomRate])) {

                    $rpRs = new Room_RateRS();

                    $rpRs->FA_Category->setNewVal($oldRs->FA_Category->getStoredVal());
                    $rpRs->Rate_Breakpoint_Category->setNewVal($oldRs->Rate_Breakpoint_Category->getStoredVal());

                    // Retired?  Can't be the default rate.
                    if (isset($retires[$idRoomRate]) &&
                        $defaultRate != $oldRs->FA_Category->getStoredVal() &&
                        $oldRs->Rate_Breakpoint_Category->getStoredVal() == '') {

                        // update
                        $oldRs->Status->setNewVal(RateStatus::Retired);
                        $oldRs->Updated_By->setNewVal($username);
                        $oldRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                        EditRS::update($dbh, $oldRs, array($oldRs->idRoom_rate));

                        // Log action.
                        HouseLog::logRoomRate($dbh, 'update', $idRoomRate, HouseLog::getUpdateText($oldRs), $username);
                        continue;

                    } else if (isset($retires[$idRoomRate]) === FALSE && $oldRs->Status->getStoredVal() == RateStatus::Retired) {
                        // Un-retire the rate.
                        $oldRs->Status->setNewVal(RateStatus::Active);
                    }

                    // Clean and check values.
                    $changed = FALSE;

                    if (isset($rr1s[$idRoomRate])) {

                        $rate1 = str_replace(',', '', str_replace('$', '', $rr1s[$idRoomRate]));
                        $rpRs->Reduced_Rate_1->setNewVal($rate1);

                        if ($rate1 != $oldRs->Reduced_Rate_1->getStoredVal()) {
                            $changed = TRUE;
                        }
                    }

                    if (isset($rr2s[$idRoomRate])) {

                        $rate1 = str_replace(',', '', str_replace('$', '', $rr2s[$idRoomRate]));
                        $rpRs->Reduced_Rate_2->setNewVal($rate1);

                        if ($rate1 != $oldRs->Reduced_Rate_2->getStoredVal()) {
                            $changed = TRUE;
                        }
                    }

                    if (isset($rr3s[$idRoomRate])) {

                        $rate1 = str_replace(',', '', str_replace('$', '', $rr3s[$idRoomRate]));
                        $rpRs->Reduced_Rate_3->setNewVal($rate1);

                        if ($rate1 != $oldRs->Reduced_Rate_3->getStoredVal()) {
                            $changed = TRUE;
                        }
                    }

                    if (isset($mins[$idRoomRate])) {

                        $rate1 = str_replace(',', '', str_replace('$', '', $mins[$idRoomRate]));
                        $rpRs->Min_Rate->setNewVal($rate1);

                        if ($rate1 != $oldRs->Min_Rate->getStoredVal()) {
                            $changed = TRUE;
                        }
                    }

                    if ($changed) {
                        // Insert New
                        $rpRs->PriceModel->setNewVal($this->getPriceModelCode());
                        $rpRs->Title->setNewVal($titles[$idRoomRate]);
                        $rpRs->Updated_By->setNewVal($username);
                        $rpRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                        $rpRs->Status->setNewVal(RateStatus::Active);
                        $idrr = EditRS::insert($dbh, $rpRs);

                        // Log action.
                        HouseLog::logRoomRate($dbh, 'insert', $idrr, HouseLog::getInsertText($rpRs), $username);


                        // update old
                        $oldRs->Status->setNewVal(RateStatus::NotActive);
                        $oldRs->Updated_By->setNewVal($username);
                        $oldRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                        EditRS::update($dbh, $oldRs, array($oldRs->idRoom_rate));

                        // Log action.
                        HouseLog::logRoomRate($dbh, 'update', $idRoomRate, HouseLog::getUpdateText($oldRs), $username);

                    } else {
                        // update
                        $oldRs->Title->setNewVal($titles[$idRoomRate]);
                        $oldRs->Updated_By->setNewVal($username);
                        $oldRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                        $ctr = EditRS::update($dbh, $oldRs, array($oldRs->idRoom_rate));

                        if ($ctr > 0) {
                            // Log action.
                            HouseLog::logRoomRate($dbh, 'update', $idRoomRate, HouseLog::getUpdateText($oldRs), $username);
                        }
                    }
                }
            }

            // New rate
            if (isset($titles['0']) && $titles['0'] != '') {

                $rpRs = new Room_RateRS();

                $rpRs->Reduced_Rate_1->setNewVal(str_replace(',', '', str_replace('$', '', $rr1s['0'])));

                if (isset($rr2s[0])) {
                    $rpRs->Reduced_Rate_2->setNewVal(str_replace(',', '', str_replace('$', '', $rr2s['0'])));
                }

                if (isset($rr3s[0])) {
                    $rpRs->Reduced_Rate_3->setNewVal(str_replace(',', '', str_replace('$', '', $rr3s['0'])));
                }

                if (isset($mins[0])) {
                    $rpRs->Min_Rate->setNewVal(str_replace(',', '', str_replace('$', '', $mins['0'])));
                }

                $rpRs->FA_Category->setNewVal($this->getNewRateCategory());
                $rpRs->PriceModel->setNewVal($this->getPriceModelCode());
                $rpRs->Rate_Breakpoint_Category->setNewVal('');     // Only ResourceBuilder can make a new Breakpoint rate category.
                $rpRs->Title->setNewVal($titles['0']);
                $rpRs->Updated_By->setNewVal($username);
                $rpRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $rpRs->Status->setNewVal(RateStatus::Active);
                $idRoomRate = EditRS::insert($dbh, $rpRs);

                // Log action.
                HouseLog::logRoomRate($dbh, 'insert', $idRoomRate, HouseLog::getInsertText($rpRs), $username);

            }
        }

        return $defaultRate;
    }

    public function getNewRateCategory() {

        $newCats = array('ra','rb','rc','rd','re','rf','rg','rh','ri','rj','rk','rl','rm','rn','ro','rp','rq','rr','rs','rt','ru','rv','rw','rx','ry','rz');
        $flpnew = array_flip($newCats);

        foreach ($this->roomRates as $oldRs) {
            if (isset($flpnew[$oldRs->FA_Category->getStoredVal()])) {
                unset($newCats[$flpnew[$oldRs->FA_Category->getStoredVal()]]);
            }
        }

        reset($newCats);

        if (count($newCats) > 0) {
            return array_shift($newCats);
        } else {
            throw new RuntimeException('Ran out of new room rate codes!');
        }
    }

    public function getPriceModelCode() {
        return $this->priceModelCode;
    }

    public function setCreditDays($days) {
        $this->creditDays = intval($days, 10);
    }

    public function getGlideApplied() {
        return $this->glideApplied;
    }

    public function getRemainderAmt() {
        return $this->remainderAmt;
    }

    public function hasRateCalculator() {
        return TRUE;
    }

    public function getVisitStatus() {
        return $this->visitStatus;
    }

    public function setVisitStatus($visitStatus) {
        $this->visitStatus = $visitStatus;
        return $this;
    }

    public static function installRates(\PDO $dbh, $modelCode, $incomeRated) {

        switch ($modelCode) {

            case ItemPriceCode::Basic:
            	PriceBasic::InstallRate($dbh, $incomeRated);
                break;

            case ItemPriceCode::Dailey;
            PriceDaily::installRate($dbh, $incomeRated);
                break;

            case ItemPriceCode::PerGuestDaily;
            PriceGuestDay::installRate($dbh, $incomeRated);
                break;

            case ItemPriceCode::NdayBlock:
            	PriceNdayBlock::installRate($dbh, $incomeRated);
                break;

            case ItemPriceCode::PerpetualStep:
            	PricePerpetualSteps::installRate($dbh, $incomeRated);
                break;

            case ItemPriceCode::Step3:
            	Price3Steps::InstallRate($dbh, $incomeRated);
                break;

            case ItemPriceCode::None:
            	PriceNone::InstallRate($dbh, $incomeRated);
                break;

            default:
                throw new RuntimeException('Price Model code "' . $modelCode . '" not defined. ');

        }
    }

}
?>