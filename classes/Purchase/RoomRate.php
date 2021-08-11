<?php

namespace HHK\Purchase;

use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\RoomRateCategories;
use HHK\Tables\EditRS;
use HHK\Tables\House\Room_RateRS;

/**
 * RoomRate.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomRate
 *
 * @author Eric
 */
class RoomRate {

    public static function makeSelectorOptions(AbstractPriceModel $priceModel, $idRoomRate = 0) {
        // Room Rate
        $rateCategories = array();
        $activeRates = $priceModel->getActiveModelRoomRates();

        foreach ($activeRates as $rc) {

            $decimals = 0;
            if (floor($rc->Reduced_Rate_1->getStoredVal()) != $rc->Reduced_Rate_1->getStoredVal()) {
                $decimals = 2;
            }

            $rateCategories[$rc->FA_Category->getStoredVal()] = array(0=>$rc->FA_Category->getStoredVal(),
                1=>self::titleAddAmount($rc->Title->getStoredVal(), $rc->FA_Category->getStoredVal(), number_format($rc->Reduced_Rate_1->getStoredVal(), $decimals)),
                2=>number_format($rc->Reduced_Rate_1->getStoredVal(), $decimals));
        }

        // Old Room Rate?
        if ($idRoomRate > 0 && isset($activeRates[$idRoomRate]) === FALSE) {

            // Old Rate replaces new rate in the selector.
            $rateRs = $priceModel->getCategoryRateRs($idRoomRate);

            $decimals = 0;
            if (floor($rateRs->Reduced_Rate_1->getStoredVal()) != $rateRs->Reduced_Rate_1->getStoredVal()) {
                $decimals = 2;
            }

            $rateCategories[$rateRs->FA_Category->getStoredVal()] = array(0=>$rateRs->FA_Category->getStoredVal(),
                1=>'*'.self::titleAddAmount($rc->Title->getStoredVal(), $rc->FA_Category->getStoredVal(), number_format($rc->Reduced_Rate_1->getStoredVal())),
                2=>number_format($rateRs->Reduced_Rate_1->getStoredVal(), $decimals));

        }


        return $rateCategories;
    }

    public static function makeDescriptions(\PDO $dbh) {

        $rateRs = new Room_RateRS();
        $rows = EditRS::select($dbh, $rateRs, array());
        $titles = array();

        foreach ($rows as $r) {
            $titles[$r['idRoom_rate']] = self::titleAddAmount($r['Title'], $r['FA_Category'], number_format($r['Reduced_Rate_1'], 0));
        }

        $titles[0] = '';

        return $titles;
    }

    protected static function titleAddAmount($title, $faCategory, $amt) {

        if ($faCategory != RoomRateCategories::Fixed_Rate_Category) {
            return $title . ': $' .$amt;
        } else {
            return $title;
        }
    }
}
