<?php

namespace HHK\Purchase\PriceModel;

use HHK\SysConst\{ItemPriceCode, RoomRateCategories};

/**
 * PriceBasic.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Price
 * Fixed rate category only.  Doesn't support income chooser.
 * @author Eric
 */
class PriceBasic extends AbstractPriceModel {

    public function amountCalculator($nites, $idRoomRate, $rateCatetgory = '', $pledgedRate = 0, $guestDays = 0) {

        return $nites * $pledgedRate;

    }

    public function daysPaidCalculator($amount, $idRoomRate, $rateCatetgory = '', $pledgedRate = 0, $rateAdjust = 0, $aveGuestPerDay = 1) {

        $this->remainderAmt = 0;

        if ($pledgedRate > 0) {
            $this->remainderAmt = $amount % $pledgedRate;
            return floor($amount / $pledgedRate);
        }

        return 0;
    }

    public function hasRateCalculator() {
        return FALSE;
    }

    protected static function installRate(\PDO $dbh) {

        $modelCode = ItemPriceCode::Basic;

        $dbh->exec("Insert into `room_rate` (`idRoom_rate`,`Title`,`FA_Category`,`PriceModel`,`Reduced_Rate_1`,`Reduced_Rate_2`,`Reduced_Rate_3`,`Min_Rate`,`Status`) values "
                . "(6,'Assigned','" . RoomRateCategories::Fixed_Rate_Category . "','$modelCode',0,0,0,0,'a');");
    }

    protected function newRateMarkup(&$fTbl, $financialAssistance = false) {

        // New rate
        // No new rates are possible
        return '';
    }

    public function saveEditMarkup(\PDO $dbh, $post, $username) {
        $defaultRate = RoomRateCategories::Fixed_Rate_Category;
        return $defaultRate;
    }

}
?>