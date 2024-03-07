<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{RateStatus, RoomRateCategories, ItemPriceCode};
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\Room_RateRS;
use HHK\HTMLControls\{HTMLTable, HTMLInput};
use HHK\Exception\RuntimeException;
use HHK\sec\Labels;

/**
 * AbstractPriceModel.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

abstract class AbstractPriceModel {

    /**
     * Summary of creditDays
     * @var int
     */
    protected $creditDays = 0;

    /**
     * Summary of glideApplied
     * @var bool
     */
    protected $glideApplied = FALSE;

    /**
     * Summary of roomRates
     * @var array
     */
    protected $roomRates;

    /**
     * Summary of activeRoomRates
     * @var array
     */
    protected $activeRoomRates;

    /**
     * Summary of remainderAmt
     * @var float|int
     */
    protected $remainderAmt = 0.0;

    /**
     * Summary of visitStatus
     * @var string
     */
    protected $visitStatus = '';

    /**
     * Summary of priceModelCode
     * @var string
     */
    protected $priceModelCode = '';

    /**
     * Summary of hasPerGuestCharge
     * @var bool
     */
    public $hasPerGuestCharge = FALSE;


    /**
     * Summary of __construct
     * @param mixed $roomRates
     */
    public function __construct(array $roomRates) {

        $this->roomRates = $roomRates;
        $this->activeRoomRates = array();


        foreach($roomRates as $rs) {

            if ($rs->Status->getStoredVal() == RateStatus::Active) {
                $this->activeRoomRates[$rs->idRoom_rate->getStoredVal()] = $rs;
            }
        }
    }

    /**
     * Summary of loadVisitNights
     * @param \PDO $dbh
     * @param int $idVisit
     * @param \DateTime|null $depDT  Used by PriceGuestDay.
     * @return array
     */
    public function loadVisitNights(\PDO $dbh, $idVisit, $depDT = null) {

        // Get current nights .
        $stmt1 = $dbh->query("select * from `vvisit_stmt` where `idVisit` = $idVisit and `Status` != 'p' order by `Span`");

        return $stmt1->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Summary of loadRegistrationNights
     * @param \PDO $dbh
     * @param int $idRegistration
     * @return array
     */
    public function loadRegistrationNights(\PDO $dbh, $idRegistration) {

        // Get current nights .
        $stmt1 = $dbh->query("select * from `vvisit_stmt` where `idRegistration` = $idRegistration and `Status` != 'p' order by `idVisit`, `Span`");

        return $stmt1->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Summary of amountCalculator
     * @param int $nites
     * @param int $idRoomRate
     * @param string $rateCatetgory
     * @param float|int $pledgedRate
     * @param int $guestDays
     * @return float
     */
    public abstract function amountCalculator($nites, $idRoomRate, $rateCatetgory = '', $pledgedRate = 0, $guestDays = 0);

    /**
     * Summary of daysPaidCalculator
     * @param mixed $amount
     * @param mixed $idRoomRate
     * @param mixed $rateCategory
     * @param mixed $pledgedRate
     * @param mixed $rateAdjust
     * @param mixed $aveGuestPerDay
     * @return float|int
     */
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

    /**
     * Summary of getCategoryRateRs
     * @param int $idRoomRate
     * @param string $category
     * @throws \HHK\Exception\RuntimeException
     * @return mixed
     */
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

    /**
     * Summary of tiersCalculation
     * @param int $days
     * @param int $idRoomRate
     * @param string $rateCategory
     * @param float|int $pledgedRate
     * @param float|int $rateAdjust
     * @param int $guestDays
     * @return array<array>
     */
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

    /**
     * Summary of tiersMarkup
     * @param mixed $r
     * @param float|int $totalAmt
     * @param HTMLTable $tbl
     * @param mixed $tiers
     * @param \DateTime $startDT
     * @param string $separator
     * @param int $totalGuestNites
     * @return float|int
     */
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

    /**
     * Summary of tiersDetailMarkup
     * @param mixed $r
     * @param HTMLTable $tbl
     * @param mixed $tiers
     * @param \DateTimeInterface $startDT
     * @param string $separator
     * @param int $totalGuestNites
     * @return void
     */
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

    /**
     * Summary of itemDetailMarkup
     * @param mixed $r
     * @param HTMLTable $tbl
     * @return void
     */
    public function itemDetailMarkup($r, &$tbl) {

    	$tbl->addBodyTr(
    			HTMLTable::makeTd($r['orderNum'], array('style'=>'text-align:center;'))
    			.HTMLTable::makeTd($r['desc'], array('style'=>'text-align:right;'))
    			.HTMLTable::makeTd($r['date'])
    			.HTMLTable::makeTd($r['amt'], array('style'=>'text-align:right;')));

    }

    /**
     * Summary of itemMarkup
     * @param mixed $r
     * @param HTMLTable $tbl
     * @return void
     */
    public function itemMarkup($r, &$tbl) {

        $tbl->addBodyTr(
            HTMLTable::makeTd($r['orderNum'], array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
            .HTMLTable::makeTd($r['date'])
            .HTMLTable::makeTd($r['desc'], array('colspan'=>'3', 'style'=>'text-align:right;'))
            .HTMLTable::makeTd($r['amt'], array('style'=>'text-align:right;')));

    }

    /**
     * Summary of rateHeaderMarkup
     * @param HTMLTable $tbl
     * @param Labels $labels
     * @return void
     */
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

    /**
     * Summary of rateDetailHeaderMarkup
     * @param mixed $tbl
     * @param mixed $labels
     * @return void
     */
    public function rateDetailHeaderMarkup(&$tbl, $labels) {
    	$tbl->addHeaderTr(
    			HTMLTable::makeTh('Visit Id')
    			.HTMLTable::makeTh('Room')
    			.HTMLTable::makeTh('Start')
    			.HTMLTable::makeTh($labels->getString('statement', 'chargeHeader', 'Charge')));

    }

    /**
     * Summary of rateTotalMarkup
     * @param mixed $tbl
     * @param mixed $label
     * @param mixed $numberNites
     * @param mixed $totalAmt
     * @param mixed $guestNites
     * @return void
     */
    public function rateTotalMarkup(&$tbl, $label, $numberNites, $totalAmt, $guestNites) {

        // Room Fee totals
        $tbl->addBodyTr(HTMLTable::makeTd($label, array('colspan'=>'5', 'class'=>'tdlabel hhk-tdTotals', 'style'=>'font-weight:bold;'))
            .HTMLTable::makeTd($numberNites, array('class'=>'hhk-tdTotals', 'style'=>'text-align:center;font-weight:bold;'))
            .HTMLTable::makeTd('$'. $totalAmt, array('class'=>'hhk-tdTotals', 'style'=>'text-align:right;font-weight:bold;')));

    }

    /**
     * Summary of priceModelFactory
     * @param \PDO $dbh
     * @param mixed $modelCode
     * @throws \HHK\Exception\RuntimeException
     * @return Price3Steps|PriceBasic|PriceDaily|PriceGuestDay|PriceNdayBlock|PriceNone|PricePerpetualSteps
     */
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

    /**
     * Summary of getModelRoomRates
     * @param \PDO $dbh
     * @param mixed $priceModelCode
     * @return array<Room_RateRS>
     */
    protected static function getModelRoomRates(\PDO $dbh, $priceModelCode) {

        // Room rates
        $stmt = $dbh->query("SELECT `idRoom_rate`,`Title`, `Description`, `FA_Category`, `Rate_Breakpoint_Category`, `Reduced_Rate_1`, `Reduced_Rate_2`, `Reduced_Rate_3`, `Min_Rate`, `Status`, IF(`Rate_Breakpoint_Category` != '', 1,0) as 'breakpointOrder'
FROM `room_rate`
where PriceModel = '$priceModelCode' order by `breakpointOrder` desc, FA_Category asc;");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $rrates = array();

        foreach ($rows as $r) {

            $rs = new Room_RateRS();
            EditRS::loadRow($r, $rs);
            $rrates[$rs->idRoom_rate->getStoredVal()] = $rs;
        }

        return $rrates;
    }

    /**
     * Summary of getActiveModelRoomRates
     * @return array
     */
    public function getActiveModelRoomRates() {
        return $this->activeRoomRates;
    }

    /**
     * Summary of getEditMarkup
     * @param \PDO $dbh
     * @param mixed $defaultRoomRate
     * @param mixed $financialAssistance
     * @return HTMLTable
     */
    public function getEditMarkup(\PDO $dbh, $defaultRoomRate = 'e', $financialAssistance = FALSE) {


        $fTbl = new HTMLTable();
        $fTbl->addHeaderTr(
            ($financialAssistance ? HTMLTable::makeTh('BP', array('style'=>'width:30px;', 'title'=>'Financial Assistance Breakpoint Category')) : '')
            .HTMLTable::makeTh('Title')
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
            if ($r->FA_Category->getStoredVal()[0] == RoomRateCategories::NewRate && $r->Rate_Breakpoint_Category->getStoredVal() == '') {

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
                ($financialAssistance ? HTMLTable::makeTd(strtoupper($r->Rate_Breakpoint_Category->getStoredVal()), array('style'=>'text-align:center')) : '')
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->Title->getStoredVal(), $titleAttrs))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->FA_Category->getStoredVal(), $attrs), array('style'=>'text-align:center'))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_1->getStoredVal(), 2), $rr1Attrs), array('style'=>'text-align:center;')))
                .HTMLTable::makeTd($cbRetire, array('style'=>'text-align:center;'))
            );
        }

        // New rate
        $this->newRateMarkup($fTbl, $financialAssistance);

        return $fTbl;
    }

    /**
     * Summary of newRateMarkup
     * @param HTMLTable $fTbl
     * @param mixed $financialAssistance
     * @return void
     */
    protected function newRateMarkup(&$fTbl, $financialAssistance = FALSE) {

        // New rate
        $fTbl->addBodyTr(
            ($financialAssistance ? HTMLTable::makeTd('', array('style'=>'width:30px;')) : '')
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'ratetitle[0]', 'size'=>'17')))
            .HTMLTable::makeTd('(New)', array('style'=>'text-align:center'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr1[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
        );

    }

    /**
     * Summary of saveEditMarkup
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $username
     * @return mixed
     */
    public function saveEditMarkup(\PDO $dbh, $post, $username) {

        $defaultRate = RoomRateCategories::Fixed_Rate_Category;

        if (isset($post['ratetitle'])) {

            $rr1s = filter_var_array($post['rr1'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
            $titles = filter_var_array($post['ratetitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $defaultRate = filter_var($post['rrdefault'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            if (isset($post['cbRetire'])) {
                $retires = filter_var_array($post['cbRetire'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

    /**
     * Summary of getNewRateCategory
     * @throws \HHK\Exception\RuntimeException
     * @return mixed
     */
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

    /**
     * Summary of getPriceModelCode
     * @return string
     */
    public function getPriceModelCode() {
        return $this->priceModelCode;
    }

    /**
     * Summary of setCreditDays
     * @param mixed $days
     * @return void
     */
    public function setCreditDays($days) {
        $this->creditDays = intval($days, 10);
    }

    /**
     * Summary of getGlideApplied
     * @return bool
     */
    public function getGlideApplied() {
        return $this->glideApplied;
    }

    /**
     * Summary of getRemainderAmt
     * @return float|int
     */
    public function getRemainderAmt() {
        return $this->remainderAmt;
    }

    /**
     * Summary of hasRateCalculator
     * @return bool
     */
    public function hasRateCalculator() {
        return TRUE;
    }

    /**
     * Summary of getVisitStatus
     * @return mixed|string
     */
    public function getVisitStatus() {
        return $this->visitStatus;
    }

    /**
     * Summary of setVisitStatus
     * @param mixed $visitStatus
     * @return AbstractPriceModel
     */
    public function setVisitStatus($visitStatus) {
        $this->visitStatus = $visitStatus;
        return $this;
    }

    /**
     * Summary of installRates
     * @param \PDO $dbh
     * @param mixed $modelCode
     * @param mixed $incomeRated
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    public static function installRates(\PDO $dbh, $modelCode, $incomeRated) {

        switch ($modelCode) {

            case ItemPriceCode::Basic:
            	PriceBasic::InstallRate($dbh);
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