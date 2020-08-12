<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{RoomRateCategories, RateStatus, ItemPriceCode};
use HHK\HTMLControls\{HTMLTable, HTMLInput};

/**
 * PricePerpetualSteps.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PricePerpetualSteps
 *
 * @author Eric
 */

class PricePerpetualSteps extends AbstractPriceModel {

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


        $interval = intval($rrateRs->Reduced_Rate_3->getStoredVal(), 10);
        $deltaAmount = floatval($rrateRs->Reduced_Rate_2->getStoredVal());
        $rate = floatval($rrateRs->Reduced_Rate_1->getStoredVal());
        $minRate = floatval($rrateRs->Min_Rate->getStoredVal());

        if ($interval <= 0
                || $deltaAmount <= 0
                || ($nites + $this->creditDays) <= $interval
                || $rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {

            // No steps
            return $rate * $nites;
        }

        // Run the credit nights down
        $creditLeft = $this->creditDays;

        While ($creditLeft >= $interval && $rate > 0) {
            $creditLeft = $creditLeft - $interval;
            $rate = max($minRate, $rate - $deltaAmount);
        }

        // Nothing left to charge.
        if ($rate <= 0) {
            return 0;
        }

        if (($nites + $creditLeft) <= $interval) {
            return $rate * $nites;
        }

        // creditsLeft must be less than interval
        if ($creditLeft > 0) {

            // A few more days in the current rate interval
            $amount = ($interval - $creditLeft) * $rate;
            $rate = max($minRate, $rate - $deltaAmount);
            $nitesLeft = $nites - ($interval - $creditLeft);

        } else {

            $nitesLeft = $nites;
            $amount = 0;
        }

        if ($nitesLeft <= $interval) {
            $amount += $rate * $nitesLeft;
            return $amount;
        }

        // Calculate the rate
        do {
            // Add each full interval
            $amount += $rate * $interval;

            $nitesLeft = $nitesLeft - $interval;
            $rate = max($minRate, $rate - $deltaAmount);

        } while ($nitesLeft > $interval && $rate > $minRate);

        // Add up any leftover days
        $amount += $nitesLeft * $rate;

        return $amount;

    }

    public function getEditMarkup(\PDO $dbh, $defaultRoomRate = 'e') {

        $fTbl = new HTMLTable();
        $fTbl->addHeaderTr(
            HTMLTable::makeTh('Title')
            .HTMLTable::makeTh('Default')
            .HTMLTable::makeTh('Starting Rate')
            .HTMLTable::makeTh('Amount Drop')
            .HTMLTable::makeTh('Each Days')
            .HTMLTable::makeTh('Minimum Rate')
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
            $rr2Attrs = array('name'=>'rr2['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');
            $rr3Attrs = array('name'=>'rr3['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');
            $minAttrs = array('name'=>'minrt['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');

            if ($r->FA_Category->getStoredVal() == $defaultRoomRate) {
                $attrs['checked'] = 'checked';
            } else {
                unset($attrs['checked']);
            }

            $cbRetire = '';
            // Cannot retire flat or fixed rates
            if ($r->FA_Category->getStoredVal() != RoomRateCategories::Fixed_Rate_Category && $r->FA_Category->getStoredVal() != RoomRateCategories::FlatRateCategory) {

                $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']'));

                if ($r->FA_Category->getStoredVal() != RoomRateCategories::NewRate) {

                    if ($r->Status->getStoredVal() != RateStatus::Active) {
                        // show as inactive
                        $attrs['disabled'] = 'disabled';
                        $titleAttrs['readonly'] = 'readonly';
                        $titleAttrs['style'] = 'background-color:#f0f0f0 ';
                        $rr1Attrs['disabled'] = 'disabled';
                        $rr2Attrs['disabled'] = 'disabled';
                        $rr3Attrs['disabled'] = 'disabled';
                        $minAttrs['disabled'] = 'disabled';

                        $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']', 'checked'=>'checked'));
                    }

                } else if ($r->Status->getStoredVal() != RateStatus::Active) {
                    // Dont show retired new rates.
                    continue;
                }
            }

            $fTbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($r->Title->getStoredVal(), $titleAttrs))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->FA_Category->getStoredVal(), $attrs) . ' (' . $r->FA_Category->getStoredVal() . ')')
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_1->getStoredVal(), 2), $rr1Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? '' :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_2->getStoredVal()), $rr2Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? '' :  HTMLTable::makeTd(HTMLInput::generateMarkup(number_format($r->Reduced_Rate_3->getStoredVal()), $rr3Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category || $r->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory ? '' :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Min_Rate->getStoredVal()), $minAttrs), array('style'=>'text-align:center;')))
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
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'minrt[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
        );

        return $fTbl;

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

        $interval = intval($rrateRs->Reduced_Rate_3->getStoredVal(), 10);
        $deltaAmount = floatval($rrateRs->Reduced_Rate_2->getStoredVal());
        $rate = floatval($rrateRs->Reduced_Rate_1->getStoredVal());
        $minRate = floatval($rrateRs->Min_Rate->getStoredVal());


        if ($interval <= 0
                || $deltaAmount <= 0
                || ($days + $this->creditDays) <= $interval
                || $rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {

            // No steps
            $tiers[] = array('rate'=> $rate * $adjRatio, 'days'=>$days, 'amt'=>($days * $rate * $adjRatio));
            return  $tiers;
        }

        $this->glideApplied = TRUE;
        $creditLeft = $this->creditDays;

        // use up the credit days
        While ($creditLeft >= $interval && $rate > 0) {
            $creditLeft = $creditLeft - $interval;
            $rate = max($minRate, $rate - $deltaAmount);
        }

        // Nothing left to charge.
        if ($rate <= 0) {
            $tiers[] = array('rate'=> 0, 'days'=>$days, 'amt'=>0);
            return  $tiers;
        }

        if (($days + $creditLeft) <= $interval) {
            $tiers[] = array('rate'=> $rate * $adjRatio, 'days'=>$days, 'amt'=>($days * $rate * $adjRatio));
            return  $tiers;
        }

        // creditsLeft must be less than interval
        if ($creditLeft > 0) {

            // A few more days in the current rate interval
            $amount = ($interval - $creditLeft) * $rate * $adjRatio;
            $tiers[] = array('rate'=> $rate * $adjRatio, 'days'=>($interval - $creditLeft), 'amt'=>$amount);
            $rate = max($minRate, $rate - $deltaAmount);
            $nitesLeft = $days - ($interval - $creditLeft);

        } else {

            $nitesLeft = $days;

        }

        if ($nitesLeft <= $interval) {
            $tiers[] = array('rate'=> $rate * $adjRatio, 'days'=>$nitesLeft, 'amt'=>($nitesLeft * $rate * $adjRatio));
            return $tiers;
        }

        do {
            // Add each full interval
            $tiers[] = array('rate'=> $rate * $adjRatio, 'days'=>$interval, 'amt'=>($interval * $rate * $adjRatio));

            $nitesLeft = $nitesLeft - $interval;
            $rate = max($minRate, $rate - $deltaAmount);

        } while ($nitesLeft > $interval && $rate > $minRate);

        // Add up any leftover days
        if ($nitesLeft > 0) {

            $tiers[] = array('rate'=> $rate * $adjRatio, 'days'=>$nitesLeft, 'amt'=>($nitesLeft * $rate * $adjRatio));

        }

        return $tiers;
    }

    protected static function installRate(\PDO $dbh) {

        $modelCode = ItemPriceCode::PerpetualStep;

        $dbh->exec("Insert into `room_rate` (`idRoom_rate`,`Title`,`Description`,`FA_Category`,`PriceModel`,`Reduced_Rate_1`,`Reduced_Rate_2`,`Reduced_Rate_3`,`Min_Rate`,`Status`) values "
                . "(1,'Rate A','','a','$modelCode',5.00,3.00,1.00,1,'a'),"
                . "(2,'Rate B','','b','$modelCode',10.00,7.00,3.00,2,'a'),"
                . "(3,'Rate C','','c','$modelCode',20.00,15.00,10.00,10,'a'),"
                . "(4,'Rate D','','d','$modelCode',25.00,20.00,10.00,10,'a'),"
                . "(5,'Flat Rate','','" . RoomRateCategories::FlatRateCategory . "','$modelCode',25.00,25.00,25.00,10,'a'), "
                . "(6,'Assigned','','" . RoomRateCategories::Fixed_Rate_Category . "','$modelCode',0,0,0,0,'a');");
    }
}
?>