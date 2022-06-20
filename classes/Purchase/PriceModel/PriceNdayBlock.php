<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{RoomRateCategories, RateStatus, ItemPriceCode};
use HHK\HTMLControls\{HTMLTable, HTMLInput};

/**
 * PriceNdayBlock.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PriceNdayBlock
 *
 * @author Eric
 */
class PriceNdayBlock extends AbstractPriceModel {

    protected $blockTitle = '';
    protected $blocks = 0;

    public function amountCalculator($nites, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $guestDays = 0) {

        if ($nites == 0) {
            return 0.00;
        }

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            return $nites * $pledgedRate;
        }

        // Flat Rate?
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {
            return $nites * $rrateRs->Reduced_Rate_1->getStoredVal();
        }

        $blockRate = floatval($rrateRs->Reduced_Rate_2->getStoredVal());
        $dailyRate = $rrateRs->Reduced_Rate_1->getStoredVal();

        $interval = $rrateRs->Reduced_Rate_3->getStoredVal();

        if ($interval == 0) {
        	$interval = 7;
        }

        $creditNites = $this->creditDays % $interval;

        $blocks = floor($nites / $interval);
        $nitesLeft = $nites % $interval;


        // Check for a free day
        if ($creditNites + $nitesLeft >= $interval) {
            // one free day
            $nitesLeft--;
        }

        $amount = ($blocks * $blockRate) + ($nitesLeft * $dailyRate);

        return $amount;
    }

    public function getEditMarkup(\PDO $dbh, $defaultRoomRate = 'e') {


        $fTbl = new HTMLTable();
        $fTbl->addHeaderTr(
            HTMLTable::makeTh('Title')
            .HTMLTable::makeTh('Default')
            .HTMLTable::makeTh('Daily Rate')
            .HTMLTable::makeTh('Block Rate')
            .HTMLTable::makeTh('Days in Block')
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
            $rr2Attrs = array('name'=>'rr2['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');
            $rr3Attrs = array('name'=>'rr3['.$r->idRoom_rate->getStoredVal().']', 'size'=>'4');

            if ($r->FA_Category->getStoredVal() == $defaultRoomRate) {
                $attrs['checked'] = 'checked';
            } else {
                unset($attrs['checked']);
            }

            $cbRetire = '';
            // Cannot retire flat or fixed rates
            if ($r->FA_Category->getStoredVal()[0] == RoomRateCategories::NewRate && $r->Rate_Breakpoint_Category->getStoredVal() == '') {

                $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']'));

                    if ($r->Status->getStoredVal() != RateStatus::Active) {
                        // show as inactive
                        $attrs['disabled'] = 'disabled';
                        $titleAttrs['readonly'] = 'readonly';
                        $titleAttrs['style'] = 'background-color:#f0f0f0 ';
                        $rr1Attrs['disabled'] = 'disabled';
                        $rr2Attrs['disabled'] = 'disabled';
                        $rr3Attrs['disabled'] = 'disabled';

                        $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']', 'checked'=>'checked'));
                    }

            }

            $fTbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($r->Title->getStoredVal(), $titleAttrs))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->FA_Category->getStoredVal(), $attrs) . ' (' . $r->FA_Category->getStoredVal() . ')')
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_1->getStoredVal(), 2), $rr1Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? '' :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_2->getStoredVal()), $rr2Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? '' :  HTMLTable::makeTd(HTMLInput::generateMarkup(number_format($r->Reduced_Rate_3->getStoredVal()), $rr3Attrs), array('style'=>'text-align:center;')))
                .HTMLTable::makeTd($cbRetire, array('style'=>'text-align:center;'))
            );

        }

        // New rate
        $fTbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'ratetitle[0]', 'size'=>'17')))
            .HTMLTable::makeTd('')
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr1[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr2[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'rr3[0]', 'size'=>'4')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
        );

        return $fTbl;

    }

    public function tiersCalculation($days, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $guestDays = 0) {

        $tiers = array();

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            $tiers[] = array('rate'=> $pledgedRate, 'days'=>$days, 'amt'=>($days * $pledgedRate), 'dtext'=>$days);
            $this->daysAccumulator = 0;
            return $tiers;
        }

        $adjRatio = (1 + $rateAdjust/100);

        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {
        	$tiers[] = array('rate'=> number_format($rrateRs->Reduced_Rate_1->getStoredVal() * $adjRatio, 2), 'days'=>$days, 'amt'=>($days * $rrateRs->Reduced_Rate_1->getStoredVal() * $adjRatio), 'dtext'=>$days);
            $this->daysAccumulator = 0;
            return  $tiers;
        }

        $blockRate = floatval($rrateRs->Reduced_Rate_2->getStoredVal());
        $dailyRate = floatval($rrateRs->Reduced_Rate_1->getStoredVal());

        $interval = $rrateRs->Reduced_Rate_3->getStoredVal();

        $creditNites = $this->creditDays % $interval;

        $blocks = floor($days / $interval);
        $nitesLeft = $days % $interval;
        $freeNites = 0;

        // Check for a free day
        if ($creditNites + $nitesLeft >= $interval) {
            // one free day
            $nitesLeft--;
            $freeNites = 1;
        }

        if ($blocks > 0) {
            $tiers[] = array('rate'=>number_format($blockRate * $adjRatio,2), 'days'=>($blocks * $interval), 'amt'=>($blocks * $blockRate) * $adjRatio, 'dtext'=>($blocks * $interval) . ' (' . $blocks . ')');
            $this->blocks = $blocks;
        }

        if ($freeNites > 0) {
            $tiers[] = array('rate'=>'Free', 'days'=>$freeNites, 'amt'=>0, 'dtext'=>$freeNites);
        }

        if ($nitesLeft > 0) {
            $tiers[] = array('rate'=>number_format($dailyRate * $adjRatio, 2), 'days'=>$nitesLeft, 'amt'=>($nitesLeft * $dailyRate) * $adjRatio, 'dtext'=>$nitesLeft);
        }

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
            	.HTMLTable::makeTd($t['rate'], array('style'=>'text-align:right;' . $separator))
                .HTMLTable::makeTd($t['dtext'], array('style'=>'text-align:center;' . $separator))
                .HTMLTable::makeTd(number_format($t['amt'], 2), array('style'=>'text-align:right;' . $separator))
            );

            $separator = '';

        }

        return $roomCharge;
    }

    protected static function installRate(\PDO $dbh, $incomeRated) {

        $modelCode = ItemPriceCode::NdayBlock;

        if ($incomeRated) {
            $dbh->exec("Insert into `room_rate` (`idRoom_rate`,`Title`,`FA_Category`, Rate_Breakpoint_Category,`PriceModel`,`Reduced_Rate_1`,`Reduced_Rate_2`,`Reduced_Rate_3`,`Min_Rate`,`Status`) values "
                . "(1,'Rate A','a','a','$modelCode',5.00,3.00,1.00,0,'a'),"
                . "(2,'Rate B','b','b','$modelCode',10.00,7.00,3.00,0,'a'),"
                . "(3,'Rate C','c','c','$modelCode',20.00,15.00,10.00,0,'a'),"
                . "(4,'Rate D','d','d','$modelCode',25.00,20.00,10.00,0,'a');");
        }

        $dbh->exec("Insert into `room_rate` (`idRoom_rate`,`Title`,`FA_Category`,`PriceModel`,`Reduced_Rate_1`,`Reduced_Rate_2`,`Reduced_Rate_3`,`Min_Rate`,`Status`) values "
            . "(5,'Flat Rate','" . RoomRateCategories::FlatRateCategory . "','$modelCode',25.00,25.00,25.00,10,'a'), "
            . "(6,'Assigned','" . RoomRateCategories::Fixed_Rate_Category . "','$modelCode',0,0,0,0,'a');");
    }

}