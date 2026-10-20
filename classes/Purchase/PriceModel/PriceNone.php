<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{ItemPriceCode, RoomRateCategories};

/**
 * PriceNone.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PriceNone
 *
 * @author Eric
 */

class PriceNone extends AbstractPriceModel {

    public function amountCalculator($nites, $idRoomRate, $rateCatetgory = '', $pledgedRate = 0, $guestDays = 0) {

        return 0.00;

    }

    public function daysPaidCalculator($amount, $idRoomRate, $rateCatetgory = '', $pledgedRate = 0, $rateAdjust = 0, $aveGuestPerDay = 1) {

        $this->remainderAmt = 0;
        return 0;
    }

    public function tiersCalculation($days, $idRoomRate, $category = '', $pledgedRate = 0, $rateAdjust = 0, $guestDays = 0) {

        $tiers[] = array('rate'=> 0, 'days'=>$days, 'amt'=>0);
        return $tiers;
    }

    public function hasRateCalculator() {
        return FALSE;
    }

    protected static function installRate(\PDO $dbh, $incomeRated) {

        $modelCode = ItemPriceCode::None;

        self::insertRates($dbh, $modelCode, [
            [5, 'Flat Rate', RoomRateCategories::FlatRateCategory, '', 0.00, 0.00, 0.00, 0],
            [6, 'Assigned', RoomRateCategories::Fixed_Rate_Category, '', 0, 0, 0, 0],
        ]);
    }

}
?>