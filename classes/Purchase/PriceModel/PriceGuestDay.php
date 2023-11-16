<?php

namespace HHK\Purchase\PriceModel;

use HHK\sec\Session;
use HHK\SysConst\{RateStatus, RoomRateCategories, ItemPriceCode};
use HHK\HTMLControls\{HTMLTable, HTMLInput};

/**
 * PriceGuestDay.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PriceGuestDay
 *
 * @author Eric
 */

class PriceGuestDay extends AbstractPriceModel {

    /**
     * {@inheritDoc}
     * @see \HHK\Purchase\PriceModel\AbstractPriceModel::__construct()
     */
    public function __construct(array $roomRates)
    {
        parent::__construct($roomRates);

        $this->hasPerGuestCharge = TRUE;
     }


    public function loadVisitNights(\PDO $dbh, $idVisit, $depDT = null) {

        $uS = Session::getInstance();

        $parm = "NOW()";
        if (is_null($depDT) === FALSE) {
            $parm = "'" . $depDT->format('Y-m-d') . "'";
        }

        // Run the vvisit_stmt view, but modified by $parm
        $stmt1 = $dbh->query("select
    v.idVisit,
    v.Span,
    v.idRegistration,
    v.idPrimaryGuest,
    v.idResource,
    ifnull(v.Arrival_Date, '') as `Arrival_Date`,
    ifnull(v.Expected_Departure, '') as `Expected_Departure`,
    ifnull(v.Actual_Departure, '') as `Actual_Departure`,
    ifnull(v.Span_Start, '') as `Span_Start`,
    ifnull(v.Span_End, '') as `Span_End`,
    v.`Status`,
    v.Rate_Glide_Credit,
    ifnull(rm.Title, '') as `Title`,
    ifnull(g.Substitute, 0) as Deposit_Amount,
    v.DepositPayType,
    v.Pledged_Rate,
    v.Rate_Category,
    v.idRoom_Rate,
    v.Expected_Rate,
    rv.Visit_Fee as `Visit_Fee_Amount`,
    DATEDIFF(ifnull(v.Span_End, $parm), v.Span_Start) as `Actual_Span_Nights`,
    ifnull(hs.idPsg, 0) as `idPsg`,
    ifnull(hs.idHospital, 0) as `idHospital`,
    ifnull(hs.idAssociation, 0) as `idAssociation`
from
    visit v
        left join
    reservation rv on v.idReservation = rv.idReservation
        left join
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        left join
    resource_room re ON v.idResource = re.idResource
        left join
    room rm on re.idRoom = rm.idRoom
        left join
    gen_lookups g on g.`Table_Name` = 'Key_Deposit_Code' and g.`Code` = rm.Key_Deposit_Code
WHERE v.`Status` not in ( 'c', 'p' ) and v.idVisit = $idVisit
order by v.`Span`;");

        $spans = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

        $ageYears = $uS->StartGuestFeesYr;

        $stmt = $dbh->query("SELECT
            s.Visit_Span,
            SUM(DATEDIFF(IFNULL(DATE(s.Span_End_Date),
            DATE($parm)), DATE(s.Span_Start_Date))) AS `GDays`
        FROM stays s JOIN name n ON s.idName = n.idName
        WHERE IFNULL(DATE(n.BirthDate), DATE('1901-01-01')) < DATE(DATE_SUB(DATE(s.Checkin_Date), INTERVAL $ageYears YEAR)) AND s.idVisit = $idVisit
        GROUP BY s.Visit_Span");


        $stays = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stays[$r['Visit_Span']] = max($r['GDays'], 0);
        }

        $perGuestChg = FALSE;

        for ($n=0; $n < count($spans); $n++) {

            if (isset($stays[$spans[$n]['Span']])) {

                $rateRs = $this->getCategoryRateRs($spans[$n]['idRoom_Rate']);

                // If the extra guest rate exists....
                if ($rateRs->Reduced_Rate_2->getStoredVal() > 0) {
                    $spans[$n]['Guest_Nights'] = max(($stays[$spans[$n]['Span']] - $spans[$n]['Actual_Span_Nights']), 0);
                    $perGuestChg = TRUE;
                } else {
                    $spans[$n]['Guest_Nights'] = 0;
                }
            }
        }

        $this->hasPerGuestCharge = $perGuestChg;

        return $spans;
    }

    public function loadRegistrationNights(\PDO $dbh, $idRegistration) {

        $uS = Session::getInstance();

        $spans = parent::loadRegistrationNights($dbh, $idRegistration);

        $ageYears = $uS->StartGuestFeesYr;
        $parm = "NOW()";

        $stmt = $dbh->query("SELECT
    s.idVisit,
    s.Visit_Span,
    SUM(DATEDIFF(IFNULL(DATE(s.Span_End_Date), DATE($parm)), DATE(s.Span_Start_Date))) AS `GDays`
FROM stays s JOIN name n ON s.idName = n.idName
    JOIN visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
WHERE v.idRegistration = $idRegistration AND IFNULL(DATE(n.BirthDate), DATE('1901-01-01')) < DATE(DATE_SUB(DATE(s.Checkin_Date), INTERVAL $ageYears YEAR))
GROUP BY s.idVisit, s.Visit_Span");


        $stays = array();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stays[$r['idVisit']][$r['Visit_Span']] = $r['GDays'] < 0 ? 0 : $r['GDays'];
        }

        $this->hasPerGuestCharge = FALSE;

        for ($n=0; $n<count($spans); $n++) {

            if (isset($stays[$spans[$n]['idVisit']][$spans[$n]['Span']])) {

                $rateRs = $this->getCategoryRateRs($spans[$n]['idRoom_Rate']);

                // If the extra guest rate exists....
                if ($rateRs->Reduced_Rate_2->getStoredVal() > 0) {
                    $spans[$n]['Guest_Nights'] = max(($stays[$spans[$n]['idVisit']][$spans[$n]['Span']] - $spans[$n]['Actual_Span_Nights']), 0);
                    $this->hasPerGuestCharge = TRUE;
                } else {
                    $spans[$n]['Guest_Nights'] = 0;
                }
            }
        }

        return $spans;
    }

    public function amountCalculator($nites, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $guestDays = 0) {

        // Short circuit for fixed rate x
        if ($rateCategory == RoomRateCategories::Fixed_Rate_Category) {
            return $nites * $pledgedRate;
        }


        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $nites;
        //$guestDays -= $nites;

        if ($guestDays > 0) {
            $amount += $rrateRs->Reduced_Rate_2->getStoredVal() * $guestDays;
        }

        return $amount;
    }

    public function daysPaidCalculator($amount, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $aveGuestPerDay = 1) {

        $this->remainderAmt = 0;
        $guestDays = 0;

        if ($amount == 0) {
            return 0;
        }

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            if ($pledgedRate > 0) {
                $days = floor($amount / $pledgedRate);
                $this->remainderAmt = $amount - ($amount * $days);
                return $days;
            }
            return 0;
        }

        // Flat rate    EKC 10/2/2023  Flat rate now treated as the others.
        // if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::FlatRateCategory) {
        //     if ($rrateRs->Reduced_Rate_1->getStoredVal() > 0) {

        //         $rate = (1 + $rateAdjust / 100) * $rrateRs->Reduced_Rate_1->getStoredVal();
        //         $days = floor($amount / $rate);
        //         $this->remainderAmt = $amount - ($amount * $days);
        //         return $days;
        //     }
        //     return 0;
        // }

        // the rest
        if ($rrateRs->Reduced_Rate_1->getStoredVal() > 0) {
            $rate = (1 + $rateAdjust / 100) * $rrateRs->Reduced_Rate_1->getStoredVal();
            $guestDays = floor($amount / $rate);
            $this->remainderAmt = $amount - ($amount * $guestDays);
        }

        if ($guestDays < 1) {
            return 0;
        }

        // Figure out the real days.
        if ($aveGuestPerDay > 0) {
            return floor($guestDays / $aveGuestPerDay);
        } else {
            return $guestDays;
        }

    }

    public function tiersCalculation($days, $idRoomRate, $rateCategory = '', $pledgedRate = 0, $rateAdjust = 0, $guestDays = 0) {

        $tiers = array();

        $rrateRs = $this->getCategoryRateRs($idRoomRate, $rateCategory);

        // Short circuit for fixed rate f
        if ($rrateRs->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            $tiers[] = array('rate'=> $pledgedRate, 'days'=>$days, 'amt'=>($days * $pledgedRate), 'gdays'=>0);
            return $tiers;
        }

        $adjRatio = (1 + $rateAdjust/100);

        $amount = $rrateRs->Reduced_Rate_1->getStoredVal() * $days * $adjRatio;
        $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_1->getStoredVal() * $adjRatio, 'days'=>$days, 'amt'=>$amount, 'gdays'=>$days);

        //$guestDays -= $days;

        if ($guestDays > 0 && $this->hasPerGuestCharge) {  // added rate check EKC 12/5/2022
            $amount = $rrateRs->Reduced_Rate_2->getStoredVal() * $guestDays * $adjRatio;
            $tiers[] = array('rate'=>$rrateRs->Reduced_Rate_2->getStoredVal() * $adjRatio, 'days'=>$days, 'amt'=>$amount, 'gdays'=>$guestDays);
        }

        return $tiers;
    }

    public function tiersMarkup($r, &$totalAmt, &$tbl, $tiers, &$startDT, $separator, &$totalGuestNites) {

        $startDate = $startDT->format('M j, Y');
        $startDT->add(new \DateInterval('P' . $tiers[0]['days'] . 'D'));
        $endDate = new \DateTime($startDT->format('y-m-d 00:00:00'));
        $endDateStr = $startDT->format('M j, Y');

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $guestEnu = 'First';
        $roomCharge = 0;

        foreach ($tiers as $t) {

            $totalAmt += $t['amt'];
            $roomCharge += $t['amt'];
            $totalGuestNites += $t['gdays'];

            if ($today < $endDate) {
                $gDays = $t['gdays'] == 0 ? '' : $t['gdays'] . ' (Est.)';
                $total = number_format($t['amt'], 2) . ' (Est.)';
            } else {
                $gDays = $t['gdays'] == 0 ? '' : $t['gdays'];
                $total = number_format($t['amt'], 2);
            }


            $tbl->addBodyTr(
                 HTMLTable::makeTd($r['vid'] . '-' . $r['span'], array('style'=>'text-align:center;' . $separator))
                .HTMLTable::makeTd($r['title'], array('style'=>$separator))
                .HTMLTable::makeTd($startDate, array('style'=>$separator))
                .HTMLTable::makeTd($endDateStr, array('style'=>$separator))
                .HTMLTable::makeTd(number_format($t['rate'], 2), array('style'=>'text-align:right;' . $separator))
                .($this->hasPerGuestCharge ? HTMLTable::makeTd($guestEnu, array('style'=>'text-align:center;' . $separator)) : '')
                .HTMLTable::makeTd($gDays, array('style'=>'text-align:center;' . $separator))
                .HTMLTable::makeTd($total, array('style'=>'text-align:right;' . $separator))
            );

            $endDateStr = '';
            $startDate = '';
            $separator = '';
            $guestEnu = 'Others';

        }

        return $roomCharge;
    }

    public function rateHeaderMarkup(&$tbl, $labels) {

        $tbl->addHeaderTr(
            HTMLTable::makeTh('Visit Id')
            .HTMLTable::makeTh('Room')
            .HTMLTable::makeTh('Start')
            .HTMLTable::makeTh('End')
            .HTMLTable::makeTh($labels->getString('statement', 'rateHeader', 'Rate'))
            .($this->hasPerGuestCharge ? HTMLTable::makeTh('Guest') : '')
            .HTMLTable::makeTh('Nights')
            .HTMLTable::makeTh($labels->getString('statement', 'chargeHeader', 'Charge')));

    }

    public function rateTotalMarkup(&$tbl, $desc, $numberNites, $totalAmt, $guestNites) {

        // Room Fee totals
        $tbl->addBodyTr(HTMLTable::makeTd($desc, array('colspan' => 6, 'class' => 'tdlabel hhk-tdTotals', 'style' => 'font-weight:bold;'))
            . ($this->hasPerGuestCharge ? HTMLTable::makeTd($guestNites, array('class' => 'hhk-tdTotals', 'style' => 'text-align:center;font-weight:bold;')) : '')
            . HTMLTable::makeTd('$' . $totalAmt, array('class' => 'hhk-tdTotals', 'style' => 'text-align:right;font-weight:bold;')));

    }

    public function itemMarkup($r, &$tbl) {

        $cols = 3;
        if ($this->hasPerGuestCharge) {
            $cols = 4;
        }
        $tbl->addBodyTr(
            HTMLTable::makeTd($r['orderNum'], array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
            .HTMLTable::makeTd($r['date'])
            .HTMLTable::makeTd($r['desc'], array('colspan'=>$cols, 'style'=>'text-align:right;'))
            .HTMLTable::makeTd($r['amt'], array('style'=>'text-align:right;')));

    }

    public function getEditMarkup(\PDO $dbh, $defaultRoomRate = 'e', $financialAssistance = false) {

        $fTbl = new HTMLTable();
        $fTbl->addHeaderTr(
            HTMLTable::makeTh('Title')
            .HTMLTable::makeTh('Default')
            .HTMLTable::makeTh('Rate/1st Guest')
            .HTMLTable::makeTh('Rate/2nd or more guests')
            .HTMLTable::makeTh('Retire')
        );


        foreach ($this->roomRates as $r) {

            // Don't deal with non-active rates.
            if ($r->Status->getStoredVal() == RateStatus::NotActive) {
                continue;
            }

            $defattrs = array('type'=>'radio', 'name'=>'rrdefault');
            $titleAttrs = array('name'=>'ratetitle['.$r->idRoom_rate->getStoredVal().']', 'size'=>'17');
            $rr1Attrs = array('name'=>'rr1['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');
            $rr2Attrs = array('name'=>'rr2['.$r->idRoom_rate->getStoredVal().']', 'size'=>'6');

            if ($r->FA_Category->getStoredVal() == $defaultRoomRate) {
                $defattrs['checked'] = 'checked';
            }

            $cbRetire = '';
            if ($r->FA_Category->getStoredVal()[0] == RoomRateCategories::NewRate && $r->Rate_Breakpoint_Category->getStoredVal() == '') {

                $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']'));

                    if ($r->Status->getStoredVal() != RateStatus::Active) {
                        // show as inactive
                        $defattrs['disabled'] = 'disabled';
                        $titleAttrs['readonly'] = 'readonly';
                        $titleAttrs['style'] = 'background-color:#f0f0f0 ';
                        $rr1Attrs['disabled'] = 'disabled';
                        $rr2Attrs['disabled'] = 'disabled';

                        $cbRetire = HTMLInput::generateMarkup('', array('type'=>'checkbox', 'name'=>'cbRetire['.$r->idRoom_rate->getStoredVal().']', 'checked'=>'checked'));
                    }

            }

            $fTbl->addBodyTr(
                HTMLTable::makeTd(HTMLInput::generateMarkup($r->Title->getStoredVal(), $titleAttrs))
                .HTMLTable::makeTd(HTMLInput::generateMarkup($r->FA_Category->getStoredVal(), $defattrs), array('style'=>'text-align:center;'))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_1->getStoredVal(), 2), $rr1Attrs), array('style'=>'text-align:center;')))
                .($r->FA_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category ? HTMLTable::makeTd('') :  HTMLTable::makeTd('$'.HTMLInput::generateMarkup(number_format($r->Reduced_Rate_2->getStoredVal(), 2), $rr2Attrs), array('style'=>'text-align:center;')))
                .HTMLTable::makeTd($cbRetire, array('style'=>'text-align:center;'))
            );
        }

        // New rate
        $fTbl->addBodyTr(
            HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'ratetitle[0]', 'size'=>'17')))
            .HTMLTable::makeTd('(New)', array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr1[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'rr2[0]', 'size'=>'6')), array('style'=>'text-align:center;'))
            .HTMLTable::makeTd('')
        );

        return $fTbl;
    }

    protected static function installRate(\PDO $dbh, $incomeRated) {

        $modelCode = ItemPriceCode::PerGuestDaily;

        if ($incomeRated) {
            $dbh->exec("Insert into `room_rate` (`idRoom_rate`,`Title`,`FA_Category`, Rate_Breakpoint_Category,`PriceModel`,`Reduced_Rate_1`,`Reduced_Rate_2`,`Reduced_Rate_3`,`Min_Rate`,`Status`) values "
                . "(1,'Rate A','a','a','$modelCode',5.00,3.00,0,0,'a'),"
                . "(2,'Rate B','b','b','$modelCode',10.00,7.00,0,0,'a'),"
                . "(3,'Rate C','c','c','$modelCode',20.00,15.00,0,0,'a'),"
                . "(4,'Rate D','d','d','$modelCode',25.00,20.00,0,0,'a');");
        }

        $dbh->exec("Insert into `room_rate` (`idRoom_rate`,`Title`,`FA_Category`,`PriceModel`,`Reduced_Rate_1`,`Reduced_Rate_2`,`Reduced_Rate_3`,`Min_Rate`,`Status`) values "
            . "(5,'Flat Rate','" . RoomRateCategories::FlatRateCategory . "','$modelCode',25.00,25.00,0,0,'a'), "
            . "(6,'Assigned','" . RoomRateCategories::Fixed_Rate_Category . "','$modelCode',0,0,0,0,'a');");
    }
}
?>