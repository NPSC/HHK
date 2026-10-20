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

    /**
     * Summary of amountCalculator
     * @param mixed $nites
     * @param mixed $idRoomRate
     * @param mixed $rateCategory
     * @param mixed $pledgedRate
     * @param mixed $guestDays
     * @return float
     */
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

    /**
     * Summary of daysPaidCalculator
     * @param float|int $amount
     * @param int $idRoomRate
     * @param string $rateCategory
     * @param float|int $pledgedRate
     * @param float|int $rateAdjust
     * @param int $aveGuestPerDay
     * @return float|int
     */
    public function daysPaidCalculator($amount, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $aveGuestPerDay = 1) {

        $this->remainderAmt = 0.0;

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate x
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {

            if ($pledgedRate > 0) {
                $this->remainderAmt = $amount - floor($amount / $pledgedRate);
                return floor($amount / $pledgedRate);
            }

            return 0;
        }


        if ($rrateRs->Reduced_Rate_1->getStoredVal() > 0) {

            $rate = (1 + $rateAdjust / 100) * $rrateRs->Reduced_Rate_1->getStoredVal();

            if($rate > 0){
                $this->remainderAmt = $amount - floor($amount / $rate);
                return floor($amount / $rate);
            }else{
                $this->remainderAmt = 0.0;
                return 0;
            }
        }

        return 0;
    }

    /**
     * Summary of installRate
     * @param \PDO $dbh
     * @param bool $incomeRated
     * @return void
     */
    protected static function installRate(\PDO $dbh, $incomeRated) {

        $modelCode = ItemPriceCode::Dailey;

        if ($incomeRated) {
            self::insertRates($dbh, $modelCode, [
                [1, 'Rate A', 'a', 'a', 5.00, 3.00, 1.00, 0],
                [2, 'Rate B', 'b', 'b', 10.00, 7.00, 3.00, 0],
                [3, 'Rate C', 'c', 'c', 20.00, 15.00, 10.00, 0],
                [4, 'Rate D', 'd', 'd', 25.00, 20.00, 10.00, 0],
            ]);
        }

        self::insertRates($dbh, $modelCode, [
            [5, 'Flat Rate', RoomRateCategories::FlatRateCategory, '', 25.00, 25.00, 25.00, 10],
            [6, 'Assigned', RoomRateCategories::Fixed_Rate_Category, '', 0, 0, 0, 0],
        ]);
    }
}
?>