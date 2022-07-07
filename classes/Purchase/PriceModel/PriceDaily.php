<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{RoomRateCategories, ItemPriceCode};
use HHK\sec\Session;

/**
 * PriceDaily.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PriceDaily
 *
 * @author Eric
 */
class PriceDaily extends AbstractPriceModel {

    public function amountCalculator($nites, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $guestDays = 0) {

        $uS = Session::getInstance();

        // Short circuit for fixed rate x and static rate (if default is "Assigned"
        if ($rateCategory == RoomRateCategories::Fixed_Rate_Category || ($rateCategory == RoomRateCategories::FullRateCategory && $uS->RoomRateDefault == RoomRateCategories::Fixed_Rate_Category)) {
            return $nites * $pledgedRate;
        }

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $nites;
        return $amount;

    }

    public function daysPaidCalculator($amount, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $aveGuestPerDay = 1) {

        $this->remainderAmt = 0;

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {

            if ($pledgedRate > 0) {
                $this->remainderAmt = $amount % $pledgedRate;
                return floor($amount / $pledgedRate);
            }

            return 0;
        }


        if ($rrateRs->Reduced_Rate_1->getStoredVal() > 0) {

            $rate = (1 + $rateAdjust / 100) * $rrateRs->Reduced_Rate_1->getStoredVal();

            if($rate > 0){
                $this->remainderAmt = $amount % $rate;
                return floor($amount / $rate);
            }else{
                $this->remainderAmt = 0;
                return 0;
            }
        }

        return 0;
    }

    protected static function installRate(\PDO $dbh, $incomeRated) {

        $modelCode = ItemPriceCode::Dailey;

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