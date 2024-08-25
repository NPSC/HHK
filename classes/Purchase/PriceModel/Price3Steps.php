<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{RoomRateCategories, RateStatus, ItemPriceCode};
use HHK\HTMLControls\{HTMLTable, HTMLInput};

/**
 * Price3Steps.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Price3Steps
 *
 * @author Eric
 */
class Price3Steps extends AbstractPriceModel {

    protected $periods;
    const TABLE_NAME = 'Rate_Period';

    public function amountCalculator($days, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $guestDays = 0) {

        $amount = 0.00;

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            return $days * $pledgedRate;
        }

        $periods = $this->periods;

        $allDays = $days + $this->creditDays;

        if ($allDays <= $periods['1'] || $rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {

           $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $days;
           return $amount;
        }


        if ($allDays <= $periods['2']) {

            if ($this->creditDays <= $periods['1']) {
                // period 1 and period 2 days
                $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * (($periods['1'] - $this->creditDays));
                $amount += $rrateRs->Reduced_Rate_2->getStoredVal() * ($days - ($periods['1'] - $this->creditDays));

            } else {
                // only period 2 days
                $amount = $rrateRs->Reduced_Rate_2->getStoredVal() * ($days);

            }

        } else {


            if ($this->creditDays <= $periods['1']) {
                // period 1, 2 and period 3 days
                $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * ($periods['1'] - $this->creditDays);

                $daysleft = $days - ($periods['1'] - $this->creditDays);

                if ($daysleft <= ($periods['2'] - $periods['1'])) {

                    $amount += $rrateRs->Reduced_Rate_2->getStoredVal() * $daysleft;

                } else {
                    $amount += $rrateRs->Reduced_Rate_2->getStoredVal() * ($periods['2'] - $periods['1']);
                    $amount += $rrateRs->Reduced_Rate_3->getStoredVal() * ($daysleft - ($periods['2'] - $periods['1']));
                }

            } else if ($this->creditDays <= $periods['2']) {

                $amount = $rrateRs->Reduced_Rate_2->getStoredVal() * ($periods['2'] - $this->creditDays);
                $daysleft = $days - ($periods['2'] - $this->creditDays);
                $amount += $rrateRs->Reduced_Rate_3->getStoredVal() * $daysleft;

            } else {
                $amount = $rrateRs->Reduced_Rate_3->getStoredVal() * $days;
            }

        }

        return $amount;
    }

    public function getEditMarkup(\PDO $dbh, $defaultRoomRate = 'e', $financialAssistance = false) {

        $rp = readGenLookupsPDO($dbh, Price3Steps::TABLE_NAME);

        $fTbl = new HTMLTable();
        $fTbl->addHeaderTr(
            HTMLTable::makeTh('Title')
            .HTMLTable::makeTh('Default')
            .HTMLTable::makeTh('Starting Rate')
            .HTMLTable::makeTh('After ' . HTMLInput::generateMarkup($rp['1'][2], array('name'=>'rp1', 'size'=>'3', 'disabled'=>'disabled')) . ' days')
            .HTMLTable::makeTh('After ' . HTMLInput::generateMarkup($rp['2'][2], array('name'=>'rp2', 'size'=>'3', 'disabled'=>'disabled')) . ' days')
            .HTMLTable::makeTh('Retire')
            );

        // Room rates
        foreach ($this->roomRates as $r) {

            // Don't deal with non-active rates.
            if ($r->Status->getStoredVal() == RateStatus::NotActive) {
                continue;
            }

            $attrs = array('type'=>'radio', 'name'=>'rrdefault', 'id'=>false);
            $titleAttrs = array('name'=>'ratetitle['.$r->idRoom_rate->getStoredVal().']', 'size'=>'17');
            $rr1Attrs = array('name'=>'rr1['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');
            $rr2Attrs = array('name'=>'rr2['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');
            $rr3Attrs = array('name'=>'rr3['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');

            if ($r->FA_Category->getStoredVal() == $defaultRoomRate) {
                $attrs['checked'] = 'checked';
            } else {
                unset($attrs['checked']);
            }

            $cbRetire = '';
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
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->FA_Category->getStoredVal(), $attrs))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_1->getStoredVal(), 2), $rr1Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_2->getStoredVal()),$rr2Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_3->getStoredVal()), $rr3Attrs), array('style'=>'text-align:center;')))
                .HTMLTable::makeTd($cbRetire, array('style'=>'text-align:center;'))
            );

        }

        // New rate
        $fTbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'ratetitle[0]', 'size'=>'17')))
            .HTMLTable::makeTd('')
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr1[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr2[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr3[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
        );

        return $fTbl;
    }

    public static function getRatePeriods(\PDO $dbh) {

        $query = "Select Code, Substitute from gen_lookups where Table_Name = '" . Price3Steps::TABLE_NAME . "'";
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $pers = array();

        foreach ($rows as $r) {
            $pers[$r['Code']] = $r['Substitute'];
        }

        return $pers;
    }

    public function tiersCalculation($days, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $guestDays = 0) {

        $tiers = array();

        if ($days < 1) {
            return $tiers;
        }

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            $tiers[] = array('rate'=> $pledgedRate, 'days'=>$days, 'amt'=>($days * $pledgedRate));
            return $tiers;
        }

        $adjRatio = (1 + $rateAdjust/100);

        // Flat Rate?
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {
           $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $days * $adjRatio;
           $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_1->getStoredVal() * $adjRatio, 'days'=>$days, 'amt'=>$amount);
           return $tiers;
        }

        $periods = $this->periods;
        $p2Days = 0;

        $this->glideApplied = TRUE;

        if ($this->creditDays <= $periods['1']) {

            $p1Days = $periods['1'] - $this->creditDays;

            if ($p1Days > $days) {
                $p1Days = $days;
            }

            if ($p1Days > 0) {
                $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $p1Days * $adjRatio;
                $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_1->getStoredVal() * $adjRatio, 'days'=>$p1Days, 'amt'=>$amount);
            }

            $days = $days - $p1Days;

            // now any p2 days
            if ($days > 0 && $days <= ($periods['2'] - $periods['1'])) {
                $p2Days = $days;
                $days = 0;
            } else if ($days > 0) {
                $p2Days = $periods['2'] - $periods['1'];
                $days = $days - $p2Days;
            }

        } else if ($this->creditDays <= $periods['2']) {

            $p2Days = $periods['2'] - $this->creditDays;

            if ($p2Days > $days) {
                $p2Days = $days;
            }

            $days = $days - $p2Days;

        }

        if ($p2Days > 0) {
            $amount = $rrateRs->Reduced_Rate_2->getStoredVal() * $p2Days * $adjRatio;
            $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_2->getStoredVal() * $adjRatio, 'days'=>$p2Days, 'amt'=>$amount);
        }


        if ($days > 0) {
            $amount2 = $rrateRs->Reduced_Rate_3->getStoredVal() * ($days) * $adjRatio;
            $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_3->getStoredVal() * $adjRatio, 'days'=>($days), 'amt'=>$amount2);
        }

        return $tiers;

    }

    protected static function installRate(\PDO $dbh, $incomeRated) {

        $modelCode = ItemPriceCode::Step3;

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
?>