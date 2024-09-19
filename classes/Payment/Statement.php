<?php
namespace HHK\Payment;

use HHK\HTMLControls\HTMLInput;
use HHK\Purchase\{Item, ValueAddedTax};
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\{InvoiceLineType, InvoiceStatus, ItemId, PaymentMethod, PaymentStatusCode, GLTableNames};
use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\House\Registration;
use Mpdf\Mpdf;

/**
 *
 * @author Eric
 *
 */
class Statement {

    /**
     * Summary of processRatesRooms
     * @param array $spans
     * @return array
     */
    public static function processRatesRooms(array $spans) {

        $rates = array();
        $rateCounter = 0;

        foreach ($spans as $v) {

            $now = new \DateTime();
            $now->setTime(0, 0, 0);

            $expDepDT = new \DateTime($v['Expected_Departure']);

            // Set expected departure to now if earlier than "today"
            if ($expDepDT < $now) {
                $expDepStr = $now->format('Y-m-d');
            } else {
                $expDepStr = $expDepDT->format('Y-m-d');
            }


            $rates[++$rateCounter] = array(
                'vid'=>$v['idVisit'],
                'span'=>$v['Span'],
                'status'=>$v['Status'],
                'title'=>$v['Title'],
                'idresc'=>$v['idResource'],
                'psg'=>$v['idPsg'],
                'hosp'=>$v['idHospital'],
                'assoc'=>$v['idAssociation'],
                'cat'=>$v['Rate_Category'],
                'amt'=>$v['Pledged_Rate'],
                'adj'=>$v['Expected_Rate'],
                'glide'=>$v['Rate_Glide_Credit'],
                'idrate'=>$v['idRoom_Rate'],
                'start'=>$v['Span_Start'],
                'end'=>$v['Span_End'],
                'arr'=>$v['Arrival_Date'],
                'adep'=>$v['Actual_Departure'],
                'exdep'=>$v['Expected_Departure'],
                'expEnd'=>$expDepStr,
                'days'=>$v['Actual_Span_Nights'],
                'vfa'=>$v['Visit_Fee_Amount']);

            if (isset($v['Name_First']) && isset($v['Name_Last'])) {
                $rates[$rateCounter]['fn'] = $v['Name_First'];
                $rates[$rateCounter]['ln'] = $v['Name_Last'];
                $rates[$rateCounter]['gid'] = $v['idPrimaryGuest'];
            }

            if (isset($v['Deposit_Amount'])) {
                $rates[$rateCounter]['depAmt'] = $v['Deposit_Amount'];
            } else {
                $rates[$rateCounter]['depAmt'] = 0;
            }

            if (isset($v['Guest_Nights'])) {
                $rates[$rateCounter]['gdays'] = $v['Guest_Nights'];
            }

            if (isset($v['AmountPaid'])) {
                $rates[$rateCounter]['paid'] = $v['AmountPaid'];
            }

            if (isset($v['Actual_Month_Nights'])) {
                $rates[$rateCounter]['mdays'] = $v['Actual_Month_Nights'];
            }
            if (isset($v['Actual_Guest_Nights'])) {
                $rates[$rateCounter]['gmdays'] = $v['Actual_Guest_Nights'];
            }
        }

        return $rates;
    }

    /**
     * Summary of processPayments
     * @param \PDOStatement $stmt
     * @param array $extraCols
     * @return array
     */
    public static function processPayments(\PDOStatement $stmt, array $extraCols = array()) {

        $idInvoice = 0;
        $idPayment = 0;
        $idPA = 0;

        $invoices = array();
        $invoice = array();
        $payments = array();
        $paymtAuths = array();
        $houseWaives = array();

        // Organize the data
        while ($p = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($p['idInvoice'] != $idInvoice) {
                // Next Invoice

                if ($idPayment > 0) {
                    // close last payment
                    if ($idPA > 0) {
                        $payments[$idPayment]['auths'] = $paymtAuths;
                    }
                }

                if ($idInvoice > 0) {
                    // close last invoice
                    $invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'h'=>$houseWaives);
                    $houseWaives = array();
                }

                $idInvoice = $p['idInvoice'];

                // new invoice
                $invoice = array(
                    'idInvoice'=>$p['idInvoice'],
                    'Invoice_Number'=>$p['Invoice_Number'],
                    'Invoice_Amount'=>$p['Invoice_Amount'],
                    'Sold_To_Id'=>$p['Sold_To_Id'],
                    'Bill_Agent'=>$p['Bill_Agent'],
                    'idGroup'=>$p['idGroup'],
                    'Order_Number'=>$p['Order_Number'],
                    'Suborder_Number'=>$p['Suborder_Number'],
                    'Invoice_Date'=>$p['Invoice_Date'],
                    'Invoice_Status'=>$p['Invoice_Status'],
                    'Invoice_Status_Title'=>$p['Invoice_Status_Title'],
                    'Carried_Amount'=>$p['Carried_Amount'],
                    'Invoice_Description'=>$p['Notes'],
                    'Invoice_Balance'=>$p['Invoice_Balance'],
                    'Delegated_Invoice_Id'=>$p['Delegated_Invoice_Id'],
                    'Invoice_Deleted'=>$p['Deleted'],
                    'Invoice_Updated_By'=>$p['Invoice_Updated_By'],
                );

                // add extra columns
                foreach ($extraCols as $e) {

                    if (isset($p[$e])) {
                        $invoice[$e] = $p[$e];
                    }
                }

                $idPayment = 0;
                $idPA = 0;
                $payments = array();
                $paymtAuths = array();
            }

            if ($p['idPayment'] != 0) {
                // Payment exists

                if ($idPayment != $p['idPayment']) {
                    // Next Payment

                    if ($idPayment > 0) {
                        // close last payment
                        if ($idPA > 0) {
                            $payments[$idPayment]['auths'] = $paymtAuths;
                        }
                    }


                    $idPayment = $p['idPayment'];

                    $payments[$idPayment] = array('idPayment'=>$p['idPayment'],
                        'Payment_Amount'=>$p['Payment_Amount'],
                        'idPayment_Method'=>$p['idPayment_Method'],
                        'Payment_Method_Title'=>$p['Payment_Method_Title'],
                        'Payment_Status'=>$p['Payment_Status'],
                        'Payment_Status_Title'=>$p['Payment_Status_Title'],
                        'Payment_Date'=>$p['Payment_Date'],
                        'Payment_Timestamp'=>$p['Payment_Timestamp'],
                        'Is_Refund'=>$p['Is_Refund'],
                        'Payment_idPayor'=>$p['Payment_idPayor'],
                        'Payment_Updated_By'=>$p['Payment_Updated_By'],
                        'Last_Updated'=>$p['Payment_Last_Updated'],
                        'Payment_Created_By'=>$p['Payment_Created_By'],
                        'Check_Number'=>$p['Check_Number'],
                        'Payment_External_Id'=>$p['Payment_External_Id'],
                        'Payment_Note'=>$p['Payment_Note']
                    );

                    $idPA = 0;
                    $paymtAuths = array();
                }

                // Payment_Auths
                if ($p['idPayment_auth'] != 0 && $idPA != $p['idPayment_auth']) {
                    // next payment auth

                    $idPA = $p['idPayment_auth'];

                    $paymtAuths[$idPA] = array(
                        'idPayment_auth' => $p['idPayment_auth'],
                        'Charge_Customer_Id' => $p['Charge_Customer_Id'],
                        'Masked_Account' => $p['Masked_Account'],
                        'Card_Type' => $p['Card_Type'],
                        'Approved_Amount' => $p['Approved_Amount'],
                        'Approval_Code' => $p['Approval_Code'],
                        'Auth_Last_Updated' => $p['Auth_Last_Updated'],
                        'Merchant' => $p['Merchant']
                    );
                }
            }

            // House Waive
            if ($p['il_Id'] > 0 && isset($houseWaives[$p['il_Id']]) === FALSE) {
                $houseWaives[$p['il_Id']] = array(
                    'id' => $p['il_Id'],
                    'Amount' => $p['il_Amount'],
                    'Desc' => $p['il_Description']
                );
            }
        }



        // Fiish the last entry of the data
        if ($idPayment > 0) {
            // close last payment
            if ($idPA > 0) {
                $payments[$idPayment]['auths'] = $paymtAuths;
            }
        }

        if ($idInvoice > 0) {
            // close last invoice
            $invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'h'=>$houseWaives);
        }

        return $invoices;

    }

    /**
     * Summary of addSavedTrs
     * @param array $trs
     * @param mixed $tbl
     * @return void
     */
    protected static function addSavedTrs(array $trs, &$tbl) {

        foreach ($trs as $t) {
            $tbl->addBodyTr($t);
        }
    }

    /**
     * Summary of makeOrdersRatesTable
     * @param mixed $rates
     * @param mixed $totalAmt
     * @param \HHK\Purchase\PriceModel\AbstractPriceModel $priceModel
     * @param mixed $labels
     * @param array $invLines
     * @param \HHK\Purchase\ValueAddedTax $vat
     * @param mixed $numberNites
     * @param \HHK\Purchase\Item $moaItem
     * @param \HHK\Purchase\Item $donateItem
     * @param mixed $showDetails
     * @return array
     */
    public static function makeOrdersRatesTable($rates, &$totalAmt, AbstractPriceModel $priceModel, $labels, array $invLines, ValueAddedTax $vat, &$numberNites, Item $moaItem, Item $donateItem, $showDetails) {

        $uS = Session::getInstance();
        $tbl = new HTMLTable();
        $detailTbl = NULL;

        $priceModel->rateHeaderMarkup($tbl, $labels);

        if ($showDetails) {
            $detailTbl = new HTMLTable();
            $priceModel->rateDetailHeaderMarkup($detailTbl, $labels);
        }

        $idVisitTracker = 0;
        $separator = '';
        $guestNites = 0;
        $visitFeeInvoiced = FALSE;
        $visitNights = 0;
        $preTaxRmCharge = 0;
        $taxExemptRmFees = 0;
        $roomTaxPaid = array();
        $roomFeesPaid = 0;

        foreach ($invLines as $l) {
            // Visit fee invoiced?
            if ($l['Item_Id'] == ItemId::VisitFee) {
                $visitFeeInvoiced = TRUE;
            }

            // Zero taxes paid
            if ($l['Type_Id'] == 2) {
                $roomTaxPaid[$l['Item_Id']] = 0;
            }
        }


        // orders and rates for each visit
        foreach ($rates as $r) {

            // New Visit
            if ($idVisitTracker != $r['vid']) {

                if ($idVisitTracker > 0) {
                    // Close up last visit

                    // Add tax info
                    foreach ($vat->getCurrentTaxedItems($r['vid'], $visitNights) as $t) {

                        if ($preTaxRmCharge > 0 && $t->getIdTaxedItem() == ItemId::Lodging) {

                            $taxableRmFees = $preTaxRmCharge - $taxExemptRmFees;
                            $taxableRmFees = ($taxableRmFees < 0 ? 0 : $taxableRmFees);

                            $roomBal = $preTaxRmCharge - $roomFeesPaid;

                            if ($roomBal >= 0) {
                                // normal
                                $totalTax = round($roomBal * $t->getDecimalTax(), 2)
                                + (isset($roomTaxPaid[$t->getIdTaxingItem()]) ? $roomTaxPaid[$t->getIdTaxingItem()] : 0);
                            } else {
                                // Fees paid greater than fees charged.
                                $totalTax = round($taxableRmFees * $t->getDecimalTax(), 2);
                            }

                            $totalAmt += $totalTax;

                            $tbl->addBodyTr(
                                HTMLTable::makeTd($t->getTaxingItemDesc() . ' (' . $t->getTextPercentTax() . ' of ' . number_format($taxableRmFees, 2) . ')', array('colspan'=>'6', 'class'=>'align-right'))
                                .HTMLTable::makeTd(number_format($totalTax, 2), array('style'=>'text-align:right;'))
                                );

                        }
                    }
                }


                // Prepare new visit.
                $separator = 'stmtHead';

                $visitNights = 0;
                $preTaxRmCharge = 0;
                $taxExemptRmFees = 0;
                $roomFeesPaid = 0;
                $idVisitTracker = $r['vid'];


                foreach ($roomTaxPaid as $k => $t) {
                    $roomTaxPaid[$k] = 0;
                }

            }

            $startDT = new \DateTime($r['start']);
            $startDT->setTime(0,0,0);
            $startDateStr = $startDT->format('M j, Y');
            $endDT = ($r['end'] == '' ? new \DateTime($r['expEnd']) : new \DateTime($r['end']));
            $endDT->setTime(0,0,0);
            $days = $startDT->diff($endDT, TRUE)->days;

            if ($r['days'] > 0 && isset($r['gdays'])) {
                //$guestNites += $r['gdays'];
                $gDayRatio = $r['gdays'] / $r['days'];
            } else {
                $gDayRatio = 1;
            }

            $priceModel->setCreditDays($r['glide']);
            $priceModel->setVisitStatus($r['status']);
            $tiers = $priceModel->tiersCalculation($days, $r['idrate'], $r['cat'], $r['amt'], $r['adj'], floor($days * $gDayRatio));

            $numberNites += $days;
            $visitNights += $days;

            // Mention rate aging ....
            if ($r['glide'] > 0 && $priceModel->getGlideApplied() && $r['span'] == 0) {
                $tbl->addBodyTr(
                    HTMLTable::makeTd($r['vid'] . '-' . $r['span'])
                    .HTMLTable::makeTd($r['title'])
                    .HTMLTable::makeTd('Room rate aged ' . $r['glide'] . ' days', array('colspan'=>'6', 'style'=>'font-size:small;font-style:italic;'))
                    );

            }

            // Write the record to the table
            if ($showDetails) {
                $priceModel->tiersDetailMarkup($r, $detailTbl, $tiers, $startDT, $separator, $guestNites);
            }

            $rChg = $priceModel->tiersMarkup($r, $totalAmt, $tbl, $tiers, $startDT, $separator, $guestNites);

            $preTaxRmCharge += $rChg;
            $separator = '';


            // Lay in the visit fee (Cleaning fee)
            if ($r['vfa'] > 0 && $r['span'] == 0 && ($uS->VisitFeeDelayDays < $visitNights || $visitFeeInvoiced)) {

                $item = array(
                    'orderNum'=>$r['vid'] . '-' . $r['span'],
                    'date'=>$startDateStr,
                    'desc'=>$labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'),
                    'amt'=>number_format($r['vfa'],2)
                );

                $priceModel->itemMarkup($item, $tbl);

                if ($showDetails) {
                    $priceModel->itemDetailMarkup($item, $detailTbl);
                }

                $totalAmt += $r['vfa'];

            }

            // Additional Charges/Discounts
            foreach ($invLines as $l) {

                if ($l['Order_Number'] == $r['vid'] && $l['Suborder_Number'] == $r['span']) {

                    if ($l['Item_Id'] == ItemId::AddnlCharge) {

                        $addChgAmt = $l['Amount'];

                        // Look for tax line
                        foreach ($invLines as $t) {
                            if ($t['Invoice_Id'] == $l['Invoice_Id'] && $t['Type_Id'] == InvoiceLineType::Tax && $t['Source_Item_Id'] == ItemId::AddnlCharge) {
                                $addChgAmt += $t['Amount'];
                                break;
                            }
                        }

                        $invDate = new \DateTime($l['Invoice_Date']);
                        $item = array(
                            'orderNum'=>$r['vid'] . '-' . $r['span'],
                            'date'=>$invDate->format('M j, Y'),
                            'desc'=>$l['Description'],
                            'amt'=>number_format($addChgAmt,2)
                        );

                        $priceModel->itemMarkup($item, $tbl);

                        if ($showDetails) {
                            $priceModel->itemDetailMarkup($item, $detailTbl);
                        }

                        $totalAmt += floatval($addChgAmt);

                    } else if ($l['Item_Id'] == ItemId::Discount || $l['Item_Id'] == ItemId::Waive) {

                        $discAmt = floatval($l['Amount']);
                        $totalAmt += $discAmt;
                        $preTaxRmCharge += $discAmt;

                        $invDate = new \DateTime($l['Invoice_Date']);
                        $item = array(
                            'orderNum'=>$r['vid'] . '-' . $r['span'],
                            'date'=>$invDate->format('M j, Y'),
                            'desc'=>$l['Description'],
                            'amt'=>number_format($discAmt,2)
                        );

                        $priceModel->itemMarkup($item, $tbl);

                        if ($showDetails) {
                            $priceModel->itemDetailMarkup($item, $detailTbl);
                        }

                    // Only show MOA payouts here (negative amounts).
                    } else if ($l['Item_Id'] == ItemId::LodgingMOA && $l['Amount'] < 0) {

                        $moaAmt = floatval($l['Amount']);
                        $totalAmt += $moaAmt;

                        $invDate = new \DateTime($l['Invoice_Date']);
                        $item = array(
                            'orderNum'=>$r['vid'] . '-' . $r['span'],
                            'date'=>$invDate->format('M j, Y'),
                            'desc'=>$l['Description'],
                            'amt'=>number_format($moaAmt,2)
                        );

                        $priceModel->itemMarkup($item, $tbl);

                        if ($showDetails) {
                            $priceModel->itemDetailMarkup($item, $detailTbl);
                        }

                    } else if ($l['Type_Id'] == InvoiceLineType::Tax && $l['Status'] != InvoiceStatus::Carried && ($l['Source_Item_Id'] == ItemId::Lodging || $l['Source_Item_Id'] == ItemId::LodgingReversal)) {
                        $roomTaxPaid[$l['Item_Id']] += floatval($l['Amount']);
                    } else if (($l['Item_Id'] == ItemId::Lodging || $l['Item_Id'] == ItemId::LodgingReversal) && ($l['Status'] == InvoiceStatus::Paid || $l['Status'] == InvoiceStatus::Unpaid)) {
                        $roomFeesPaid += floatval($l['Amount']);
                        if($l['tax_exempt'] == 1){
                            $taxExemptRmFees += floatval($l['Amount']);
                        }
                    }

                }
            }
        }

        // For the last visit rate.

        // Add tax info
        foreach ($vat->getCurrentTaxedItems($idVisitTracker, $visitNights) as $t) {

            if ($preTaxRmCharge > 0 && $t->getIdTaxedItem() == ItemId::Lodging) {

                $taxableRmFees = $preTaxRmCharge - $taxExemptRmFees;
                $taxableRmFees = ($taxableRmFees < 0 ? 0 : $taxableRmFees);
                //$totalTax = round( ($preTaxRmCharge * $t->getDecimalTax()), 2);

                $roomBal = $preTaxRmCharge - $roomFeesPaid;

                if ($roomBal >= 0) {
                    // normal
                    $totalTax = round($roomBal * $t->getDecimalTax(), 2)
                    + (isset($roomTaxPaid[$t->getIdTaxingItem()]) ? $roomTaxPaid[$t->getIdTaxingItem()] : 0);
                } else {
                    // Fees paid greater than fees charged.
                    $totalTax = round(($taxableRmFees) * $t->getDecimalTax(), 2);
                }

                $totalAmt += $totalTax;

                $tbl->addBodyTr(
                    HTMLTable::makeTd($t->getTaxingItemDesc() . ' (' . $t->getTextPercentTax() . ' of ' . number_format($taxableRmFees, 2) . ')', array('colspan'=>'6', 'style'=>'text-align:right;'))
                    .HTMLTable::makeTd(number_format($totalTax, 2), array('style'=>'text-align:right;'))
                    );
            }
        }


        // Room Fee totals
        $priceModel->rateTotalMarkup($tbl, $labels->getString('statement', 'roomTotalLabel', 'Total'), $numberNites, number_format($totalAmt, 2), $guestNites);


        // Room Donations & retained loging fees
        $donAmt = 0;
        $moaAmt = 0;

        foreach ($invLines as $l) {

            $itemAmount = floatval($l['Amount']);

            if ($l['Item_Id'] == ItemId::LodgingDonate && $l['Status'] == InvoiceStatus::Paid) {
                $donAmt += $itemAmount;
            }


            if ($l['Item_Id'] == ItemId::LodgingMOA && $l['Status'] == InvoiceStatus::Paid) {
                if ($itemAmount > 0) {
                    // Only show payments to MOA here.
                    $moaAmt += $itemAmount;
                } else if ($l['Order_Number'] === 0 && $itemAmount < 0) {
                    // refunded reservation prepayment.
                    $moaAmt += $itemAmount;
                }
            }
        }

        // Print Donation total
        if ($donAmt != 0) {

            $totalAmt += $donAmt;

            $priceModel->rateTotalMarkup($tbl, $donateItem->getDescription(), '', number_format($donAmt,2), '');
        }

        // Print MOA total
        if ($moaAmt != 0) {

            $totalAmt += $moaAmt;

            $priceModel->rateTotalMarkup($tbl, $moaItem->getDescription(), '', number_format($moaAmt,2), '');
        }

        // Second total line
        if ($donAmt + $moaAmt != 0) {

            // Room Fee totals
            $priceModel->rateTotalMarkup($tbl, $labels->getString('statement', 'TotalLabel', 'Total'), '', number_format($totalAmt, 2), '');
        }

        return array($tbl, $detailTbl);
    }

    /**
     * Summary of makePaymentLine
     * @param array $payLines
     * @param mixed $tbl
     * @param mixed $tdAttrs
     * @param array $descs
     * @param mixed $i
     * @return void
     */
    protected static function makePaymentLine(array $payLines, &$tbl, $tdAttrs, array $descs, $i) {

        if (count($payLines) == 0 && count($descs) > 0) {
            // fake a payment
            // Add top border for each new invoice.
            //$attrs = array('style'=>'border-top: 2px solid #2E99DD;');
            $attrs = [];
            $payLines[] = HTMLTable::makeTd(($i['Invoice_Date'] == '' ? '' : date('M j, Y', strtotime($i['Invoice_Date']))), $attrs)
            .HTMLTable::makeTd('', array_merge($attrs, array('colspan'=>'2')))
            .HTMLTable::makeTd('', $attrs)
            .HTMLTable::makeTd('0.00', array('class'=>'align-right'));

        }

        $rspan = (count($payLines) + count($descs));
        $firstT = TRUE;

        foreach ($payLines as $t) {

            if ($firstT) {

                $tbl->addBodyTr(
                    HTMLTable::makeTd($i['Order_Number'] . '-' . $i['Suborder_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan")))
                    .HTMLTable::makeTd($i['Invoice_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan")))
                    .$t
                    ,["class"=>"stmtHead"]);

                $firstT = FALSE;

            } else {

                $tbl->addBodyTr($t);
            }
        }

        foreach ($descs as $d) {

            if ($firstT) {

                $tbl->addBodyTr(
                    HTMLTable::makeTd($i['Order_Number'] . '-' . $i['Suborder_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan")))
                    .HTMLTable::makeTd($i['Invoice_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan")))
                    .$d
                    , ["class"=>"stmtHead"]);

                $firstT = FALSE;

            } else {
                $tbl->addBodyTr($d);
            }
        }

    }

    /**
     * Summary of makePaymentsTable
     * @param mixed $invoices
     * @param mixed $invLines
     * @param mixed $subsidyId
     * @param mixed $returnId
     * @param mixed $totalAmt
     * @param mixed $pmtDisclaimer
     * @param mixed $labels
     * @param mixed $tdClass
     * @return HTMLTable
     */
    public static function makePaymentsTable($invoices, $invLines, $subsidyId, $returnId, &$totalAmt, $pmtDisclaimer, $labels, $tdClass = '') {

        // Markup
        $tbl = new HTMLTable();
        $totalPment = 0.0;
        $totalReimbursment = 0.0;

        $numPayments = 0;

        $tdAttrs = array();
        if ($tdClass != '') {
            $tdAttrs['class'] = $tdClass;
        }

        // Run the invoices collecting the payments and items.
        foreach ($invoices as $r) {

            // House discounts
            if ($r['i']['Sold_To_Id'] == $subsidyId) {
                continue;
            }

            // Third party
            if ($r['i']['Bill_Agent'] == 'a') {
                continue;
            }

            $payLines = array();
            $descs = array();

            // Payments
            foreach ($r['p'] as $p) {

                $amt = floatval($p['Payment_Amount']);

                if ($p['Is_Refund'] > 0) {
                    $amt = 0 - $amt;
                }
                $amtStyle = 'text-align:right;';

                if ($p['Payment_Status'] == PaymentStatusCode::Paid) {

                    $amtMkup = number_format($amt, 2);
                    $totalPment += $amt;
                                                            // EKC 9/14/2023 - some payments have 0 amounts, so the balance does not change.
                    if ($r['i']['Invoice_Balance'] != 0) {  // && $r['i']['Invoice_Balance'] != $r['i']['Invoice_Amount']) {
                        $p['Payment_Status_Title'] = 'Paying';
                    } else {
                        $p['Payment_Status_Title'] = 'Paid';
                    }

                } else if ($p['Payment_Status'] == PaymentStatusCode::VoidReturn) {

                    $p['Payment_Status_Title'] = 'Void';

                    $amtMkup = HTMLContainer::generateMarkup('span', number_format(floatval($p['Payment_Amount']), 2), array('style'=>'color:red;'));
                    $amtStyle = 'text-align:left;';
                } else {
                    // Return or void
                    $amtMkup = HTMLContainer::generateMarkup('span', number_format(floatval($p['Payment_Amount']), 2), array('style'=>'color:red;'));
                    $amtStyle = 'text-align:left;';
                }

                $addnl = '';
                $numPayments++;

                if ($p['idPayment_Method'] == PaymentMethod::Charge) {

                    if (isset($p['auths'])) {

                        foreach ($p['auths'] as $a) {

                            if ($a['Card_Type'] != '') {
                                $addnl = $a['Card_Type'] . ' ' . $a['Masked_Account'];
                            }
                        }
                    }


                    $p['Payment_Method_Title'] = 'Credit Card';


                } else if ($p['idPayment_Method'] == PaymentMethod::Check || $p['idPayment_Method'] == PaymentMethod::Transfer) {

                    $addnl = ($p['Check_Number'] == '' ? ' ' : '#' . $p['Check_Number']);
                }

                $attrs = $tdAttrs;

                // Style the amount
                $amtAttrs = $attrs;
                if (isset($amtAttrs['style'])) {
                    $amtAttrs['style'] .= $amtStyle;
                } else {
                    $amtAttrs['style'] = $amtStyle;
                }

                $payStatus = $p['Payment_Status_Title'];

                // Catch House returns/refunds
                if ($r['i']['Sold_To_Id'] == $returnId && $p['Is_Refund'] > 0) {
                    $payStatus = 'Return';
                }


                $payLines[] = HTMLTable::makeTd(($p['Payment_Date'] == '' ? '' : date('M j, Y', strtotime($p['Payment_Date']))), $attrs)
                .($addnl == '' ? HTMLTable::makeTd($p['Payment_Method_Title'], array_merge($attrs, array('colspan'=>'2'))) :
                    (HTMLTable::makeTd($p['Payment_Method_Title'], $attrs) . HTMLTable::makeTd($addnl, $attrs)))
                    .HTMLTable::makeTd($payStatus, $attrs)
                    .HTMLTable::makeTd($amtMkup, $amtAttrs);
            }

            // Add the items
            if (count($payLines) > 0 || $r['i']['Invoice_Status'] == InvoiceStatus::Paid) {

                $myLines = array();
                foreach ($invLines as $l) {

                    if ($l['Invoice_Id'] == $r['i']['idInvoice'] || $l['Delegated_Invoice_Id'] == $r['i']['idInvoice']) {

                        // Replace carried lines
                        if ($l['Type_Id'] != InvoiceLineType::Invoice) {
                            $myLines[] = $l;
                        }
                    }
                }

                $first = TRUE;

                foreach ($myLines as $l) {

                    if ($first) {
                        $initialTd = HTMLTable::makeTd('Item' . (count($myLines) > 1 ? 's:' : ':'), array('rowspan'=>count($myLines), 'class'=>'align-right', 'style'=>'border: 0 none red; font-size:.8em;'));
                        $first = FALSE;
                    } else {
                        $initialTd = '';
                    }

                    $descs[] = $initialTd . HTMLTable::makeTd('$'.number_format($l['Amount'],2) . ';  ' .$l['Description'], array('colspan'=>'4', 'style'=>'font-size:.8em'));
                    $numPayments++;

                }

            }

            self::makePaymentLine($payLines, $tbl, $tdAttrs, $descs, $r['i']);

        }


        if ($numPayments > 0) {

            $tbl->addHeaderTr(
                HTMLTable::makeTh('Visit Id', $tdAttrs)
                .HTMLTable::makeTh('Invoice', $tdAttrs)
                .HTMLTable::makeTh('Date', $tdAttrs)
                .HTMLTable::makeTh('Type / Item(s)', array_merge($tdAttrs, array('colspan'=>'2')))
                .HTMLTable::makeTh('Status', $tdAttrs)
                .HTMLTable::makeTh($labels->getString('statement', 'paymentHeader', 'Payment'), $tdAttrs));

            $blackLine = 'hhk-tdTotals ';

            if ($totalReimbursment > 0) {

                $tbl->addBodyTr(HTMLTable::makeTd('Total Reimbursed', array('colspan'=>'6', 'class'=>'tdlabel '.$blackLine.$tdClass))
                    .HTMLTable::makeTd('$'. number_format($totalReimbursment, 2), array('class'=>'hhk-tdTotals align-right '.$tdClass)));

                $blackLine = '';
            }

            $guestPayment = $totalPment - $totalReimbursment;
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('memberType', 'visitor', 'Visitor') . ' ' . $labels->getString('statement', 'paymentTotalLabel', 'Payment Total (Thank You!)'), array('colspan'=>'6', 'class'=>'tdlabel '.$blackLine.$tdClass))
                .HTMLTable::makeTd('$'. number_format($guestPayment, 2), array('class'=>'align-right ' . $tdClass.$blackLine)));

            // Totals Line needed?
            if ($totalReimbursment > 0) {
                $tbl->addBodyTr(HTMLTable::makeTd('Total Payments', array('colspan'=>'6', 'class'=>'tdlabel hhk-tdTotals '.$tdClass))
                    .HTMLTable::makeTd('$'. number_format($totalPment, 2), array('class'=>'hhk-tdTotals align-right'.$tdClass)));
            }

        } else if ($numPayments == 0) {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('statement', 'noPaymentsRecordedLabel', 'No Payments Recorded'), array('colspan'=>'7', 'style'=>'font-style:italic;', 'class'=>$tdClass)));
        }

        $totalAmt = $totalAmt - $totalPment;
        // Disclaimer
        if ($pmtDisclaimer != '') {
            $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $pmtDisclaimer, array('class'=>'pmtDisclaimer')), array('colspan'=>'7', 'class'=>$tdClass)));
        }

        return $tbl;
    }

    /**
     * Summary of makeThirdParyTable
     * @param mixed $invoices
     * @param mixed $invLines
     * @param mixed $labels
     * @param mixed $totAmt
     * @param mixed $tdClass
     * @return string
     */
    public static function makeThirdParyTable($invoices, $invLines, $labels, &$totAmt, $tdClass = '') {

        $tbl = new HTMLTable();
        $totalPment = 0.0;
        $numPayments = 0;

        $tdAttrs = array();
        if ($tdClass != '') {
            $tdAttrs['class'] = $tdClass;
        }

        foreach ($invoices as $r) {

            // Only billing agents.
            if ($r['i']['Bill_Agent'] != 'a') {
                continue;
            }

            $myLines = array();

            foreach ($invLines as $l) {

                if ($l['Invoice_Id'] == $r['i']['idInvoice'] || $l['Delegated_Invoice_Id'] == $r['i']['idInvoice']) {
                    // Replace carried lines
                    if ($l['Type_Id'] != InvoiceLineType::Invoice) {
                        $myLines[] = $l;
                        $totalPment += $l['Amount'];
                    }
                }
            }

            if (count($myLines) > 0) {

                $numPayments++;
                $first = TRUE;
                $trAttrs = array();

                foreach ($myLines as $l) {

                    if ($first) {

                        $payor = $r['i']['Company'];
                        if ($payor == '') {
                            $payor = $r['i']['First'] . ' ' . $r['i']['Last'];
                        }

                        $mattrs = array_merge($tdAttrs);
                        $vattrs = array_merge($tdAttrs, array('class'=>'align-right'));

                        $initialTd = HTMLTable::makeTd($r['i']['Order_Number'] . '-' . $r['i']['Suborder_Number'], array_merge($tdAttrs, array('rowspan'=>count($myLines))))
                        .HTMLTable::makeTd($payor, array_merge($tdAttrs, array('rowspan'=>count($myLines))));

                        $trAttrs = array("class" => "stmtHead");

                        $first = FALSE;

                    } else {
                        $initialTd = '';
                        $mattrs = $tdAttrs;
                        $vattrs = array_merge($tdAttrs, array('class'=>'align-right'));
                    }

                    $tr = $initialTd . HTMLTable::makeTd(($r['i']['Invoice_Date'] == '' ? '' : date('M j, Y', strtotime($r['i']['Invoice_Date']))), $mattrs)
                    .HTMLTable::makeTd($l['Description'], array_merge($mattrs, array('colspan'=>'3')))
                    .HTMLTable::makeTd(($l['Status'] == InvoiceStatus::Unpaid ? 'Pending' : 'Paid'), $mattrs)
                    .HTMLTable::makeTd('$'.number_format($l['Amount'],2), $vattrs);

                    $tbl->addBodyTr($tr, $trAttrs);                }
            }
        }

        //        $oldAmt = $totAmt;
        $totAmt -= $totalPment;

        if ($numPayments > 0) {
            $tbl->addHeaderTr(
                HTMLTable::makeTh('Visit Id', $tdAttrs)
                .HTMLTable::makeTh('Organization', $tdAttrs)
                .HTMLTable::makeTh('Date', $tdAttrs)
                .HTMLTable::makeTh('Item', array_merge($tdAttrs, array('colspan'=>'3')))
                .HTMLTable::makeTh('Status', $tdAttrs)
                .HTMLTable::makeTh($labels->getString('statement', 'paymentHeader', 'Payment'), $tdAttrs));

            $tbl->addBodyTr(HTMLTable::makeTd('3rd Party Payment Total', array('colspan'=>'7', 'class'=>'tdlabel hhk-tdTotals '.$tdClass))
                .HTMLTable::makeTd('$'. number_format($totalPment, 2), array('class'=>'hhk-tdTotals align-right '.$tdClass)));

        }

        if ($numPayments > 0) {
            return $tbl->generateMarkup(["class"=>"fullWidth"]);
        }else {
            return '';
        }
    }

    /**
     * Summary of createComprehensiveStatements
     * @param \PDO $dbh
     * @param mixed $idRegistration
     * @param mixed $includeLogo
     * @return string
     */
    public static function createComprehensiveStatements(\PDO $dbh, $idRegistration, $includeLogo = TRUE) {

        $uS = Session::getInstance();

        $spans = array();
        $hospital = '';
        $rates = [];

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        if ($idRegistration > 0) {
            $spans = $priceModel->loadRegistrationNights($dbh, $idRegistration);
            $reg = new Registration($dbh, 0, $idRegistration);
        } else {
            return 'Missing Registration Id.  ';
        }


        if (count($spans) == 0 && $uS->AcceptResvPaymt === FALSE) {
            return 'Visits Not Found.  ';
        } else if (count($spans) > 0) {
            // Collect rates and rooms
            $rates = self::processRatesRooms($spans);
        }

        $idPsg = intVal($reg->getIdPsg());
        $totalAmt = 0.00;
        $totalNights = 0;

        // Get labels & config
        $labels = Labels::getLabels();

        // Payments
        $query = "select lp.*, ifnull(n.Name_First, '') as `First`,
    ifnull(n.Name_Last, '') as `Last`,
    ifnull(n.Company, '') as `Company`
from vlist_inv_pments lp
    left join
    `name` n ON lp.Sold_To_Id = n.idName
 where lp.idGroup = $idRegistration and lp.Deleted = 0 ORDER BY lp.idInvoice";
        $stmt = $dbh->query($query);

        $pments = self::processPayments($stmt, array('Last', 'First', 'Company'));

        // items
        $ilStmt = $dbh->query("select il.Invoice_Id, il.idInvoice_line, il.Type_Id, il.Amount, il.Description, il.Item_Id, il.Source_Item_Id, i.tax_exempt, i.Delegated_Invoice_Id, i.Order_Number, i.Suborder_Number, i.Invoice_Date, i.Status
from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
left join invoice_line_type ilt on il.Type_Id = ilt.id
where i.Deleted = 0 and il.Deleted = 0 and i.idGroup = $idRegistration order by i.idGroup, il.Invoice_Id, ilt.Order_Position");

        $invLines = $ilStmt->fetchAll(\PDO::FETCH_ASSOC);


        // Visits and Rates
        $tbls = self::makeOrdersRatesTable($rates, $totalAmt, $priceModel, $labels, $invLines, new ValueAddedTax($dbh), $totalNights, new Item($dbh, ItemId::LodgingMOA), new Item($dbh, ItemId::LodgingDonate), FALSE);
        $tbl = $tbls[0];
        $totalCharge = $totalAmt;

        // Thirdparty payments
        $tpTbl = self::makeThirdParyTable($pments, $invLines, $labels, $totalAmt);
        $totalThirdPayments = $totalCharge - $totalAmt;

        $ptbl = self::makePaymentsTable($pments, $invLines, $uS->subsidyId, $uS->returnId, $totalAmt, $uS->PaymentDisclaimer, $labels);
        $totalGuestPayments = $totalCharge - $totalThirdPayments - $totalAmt;

        // Find patient name
        $patientName = '';
        $diags = [];
        if ($idPsg > 0){

            $pstmt = $dbh->query("select n.Name_First, n.Name_Last, hs.idHospital, hs.idAssociation from name n left join hospital_stay hs on n.idName = hs.idPatient where hs.idPsg = $idPsg");
            $rows = $pstmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $patientName = $rows[0]['Name_First'] . ' ' . $rows[0]['Name_Last'];

                // Hospital
                if ($rows[0]['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$rows[0]['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$rows[0]['idAssociation']][1] != '(None)') {
                    $hospital .= $uS->guestLookups[GLTableNames::Hospital][$rows[0]['idAssociation']][1] . ' / ';
                }
                if ($rows[0]['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$rows[0]['idHospital']])) {
                    $hospital .= $uS->guestLookups[GLTableNames::Hospital][$rows[0]['idHospital']][1];
                }
            }
        }


        // Build the statement
        $rec = self::makeHeaderMkup($dbh, $includeLogo);
        $rec .= HTMLContainer::generateMarkup('h2', 'Comprehensive Statement of Account', array('class'=>'mb-3'));


        $rec .= self::makeSummaryDiv(
            '',
            $patientName,
            $hospital,
            $diags,
            $labels,
            $totalCharge,
            $totalThirdPayments,
            $totalGuestPayments,
            max(Registration::loadLodgingBalance($dbh, $idRegistration) - Registration::loadPrepayments($dbh, $idRegistration), 0),
            Registration::loadPrepayments($dbh, $idRegistration),
            Registration::loadDepositBalance($dbh, $idRegistration),
            $totalNights
        );

        $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'datesChargesCaption', 'Visit Dates & Room Charges'));
        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('class'=>'hhk-tdbox mb-3'));

        if ($tpTbl != '') {
            $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'thirdParty', '3rd Party'). ' Payments');
            $rec .= HTMLContainer::generateMarkup('div', $tpTbl, array('class'=>'hhk-tdbox mb-3'));
        }

        $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'paymentsCaption', 'Payments'));
        $rec .= HTMLContainer::generateMarkup('div', $ptbl->generateMarkup(['class'=>'fullWidth']), array('class'=>'hhk-tdbox mb-3'));

        return $rec;

    }

    /**
     * Summary of createStatementMarkup
     * @param \PDO $dbh
     * @param mixed $idVisit
     * @param mixed $guestName
     * @param mixed $includeLogo
     * @return string
     */
    public static function createStatementMarkup(\PDO $dbh, $idVisit, $guestName, $includeLogo = TRUE) {

        $uS = Session::getInstance();
        $spans = array();

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        if ($idVisit > 0) {
            $spans = $priceModel->loadVisitNights($dbh, $idVisit);
        } else {
            return 'Missing Visit Id.  ';
        }


        if (count($spans) == 0) {
            return 'Visit Not Found.  ';
        }

        $idPsg = intval($spans[0]['idPsg']);
        $idRegistration = intval($spans[0]['idRegistration']);

        // Hospital
        $hospital = '';
        if ($spans[0]['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$spans[0]['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$spans[0]['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$spans[0]['idAssociation']][1] . ' / ';
        }
        if ($spans[0]['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$spans[0]['idHospital']])) {
            $hospital .= $uS->guestLookups[GLTableNames::Hospital][$spans[0]['idHospital']][1];
        }

        // Payments
        $stmt = $dbh->query("select lp.*, ifnull(n.Name_First, '') as `First`, ifnull(n.Name_Last, '') as `Last`, ifnull(n.Company, '') as `Company`
from vlist_inv_pments `lp` left join `name` n ON lp.Sold_To_Id = n.idName
 where lp.Order_Number = $idVisit and lp.Deleted = 0 ORDER BY lp.idInvoice ");

        $pments = self::processPayments($stmt, array('Last', 'First', 'Company'));

        // Items
        $ilStmt = $dbh->query("select il.Invoice_Id, il.idInvoice_line, il.Type_Id, il.Amount, il.Description, il.Item_Id, il.Source_Item_Id, i.tax_exempt, i.Delegated_Invoice_Id, i.Order_Number, i.Suborder_Number, i.Invoice_Date, i.Status
from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice and il.Deleted = 0
left join invoice_line_type ilt on il.Type_Id = ilt.id
where i.Deleted = 0 and i.Order_Number = $idVisit order by il.Invoice_Id, ilt.Order_Position");

        $invLines = $ilStmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalAmt = 0.00;
        $totalNights = 0;

        // Get labels
        $labels = Labels::getLabels();


        // Visits and Rates
        $tbls = self::makeOrdersRatesTable(self::processRatesRooms($spans), $totalAmt, $priceModel, $labels, $invLines, new ValueAddedTax($dbh), $totalNights, new Item($dbh, ItemId::LodgingMOA), new Item($dbh, ItemId::LodgingDonate), $uS->ShowRateDetail);
        $tbl = $tbls[0];
        $totalCharge = $totalAmt;

        // Thirdparty payments
        $tpTbl = self::makeThirdParyTable($pments, $invLines, $labels, $totalAmt);
        $totalThirdPayments = $totalCharge - $totalAmt;

        // Payments
        $ptbl = self::makePaymentsTable($pments, $invLines, $uS->subsidyId, $uS->returnId, $totalAmt, $uS->PaymentDisclaimer, $labels);
        $totalGuestPayments = $totalCharge - $totalThirdPayments - $totalAmt;

        // Find patient name
        $patientName = '';
        $diags = [];
        if ($idPsg > 0){

            $pstmt = $dbh->query("SELECT
    n.Name_First, n.Name_Last, ifnull(g.Description, '') as Diagnosis, ifnull(g2.Description, '') as Diagnosis2
FROM
	visit v
        LEFT JOIN
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
		LEFT JOIN
    name n on hs.idPatient = n.idName
		LEFT JOIN
	gen_lookups g on g.Table_Name = 'Diagnosis' and g.Code = hs.Diagnosis
		LEFT JOIN
	gen_lookups g2 on g2.Table_Name = 'Diagnosis' and g2.Code = hs.Diagnosis2
WHERE
    v.idVisit = $idVisit");
            $rows = $pstmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $patientName = $rows[0]['Name_First'] . ' ' . $rows[0]['Name_Last'];
                $diags[1] = $rows[0]['Diagnosis'];
                $diags[2] = $rows[0]['Diagnosis2'];
            }
        }


        // Build the statement
        $rec = self::makeHeaderMkup($dbh, $includeLogo);

        $rec .= HTMLContainer::generateMarkup('h2', 'Statement of Account', array('class'=>'mb-3'));

        $rec .= self::makeSummaryDiv(
            $guestName,
            $patientName,
            $hospital,
            $diags,
            $labels,
            $totalCharge,
            $totalThirdPayments,
            $totalGuestPayments,
            Registration::loadLodgingBalance($dbh, $idRegistration, $idVisit),
            0,
            Registration::loadDepositBalance($dbh, 0, $idVisit),
            $totalNights);

        $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'datesChargesCaption', 'Visit Dates & Room Charges'));
        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('class'=>'hhk-tdbox mb-3'));

        if ($tpTbl != '') {
            $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'thirdParty', '3rd Party'). ' Payments');
            $rec .= HTMLContainer::generateMarkup('div', $tpTbl, array('class'=>'hhk-tdbox mb-3'));
        }

        $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'paymentsCaption', 'Payments'));
        $rec .= HTMLContainer::generateMarkup('div', $ptbl->generateMarkup(array("class"=>"fullWidth")), array('class'=>'hhk-tdbox mb-3'));

        if ($uS->ShowRateDetail) {
            $rec .= HTMLContainer::generateMarkup('h3', $labels->getString('statement', 'rateHeader', 'Rate').' Detail');
            $rec .= HTMLContainer::generateMarkup('div', $tbls[1]->generateMarkup(), array('class'=>'hhk-tdbox mb-3'));
        }
        return $rec;

    }

    public static function createEmailStmtWrapper(string $stmtMarkup){
        return '<html><head><style type="text/css">' .
            file_get_contents("css/jqui/jquery-ui.min.css") .
            file_get_contents("css/house.css") .
            file_get_contents("css/statement.css") .
            file_get_contents("css/bootstrap-grid.min.css") .
            '</style></head><body><div id="emailStmtDiv">' . $stmtMarkup . '</div></body></html>';
    }

    public static function makeHeaderMkup(\PDO $dbh, $includeLogo = true){
        $uS = Session::getInstance();

        $logoUrl = $uS->resourceURL . 'conf/' . $uS->statementLogoFile;
        $header = "";

        // Don't write img if logo URL not sepcified
        if ($includeLogo && $logoUrl != '') {

            $header .= HTMLTable::makeTd(
                HTMLContainer::generateMarkup('img', '', array('src'=>$logoUrl, 'id'=>'hhkrcpt', 'alt'=>$uS->siteName, 'width'=>$uS->statementLogoWidth, "class"=>"mr-5")),
            array("style"=>"vertical-align: middle; width: " . $uS->statementLogoWidth ."px"));
        }

        $header .= HTMLTable::makeTd(Receipt::getAddressTable($dbh, $uS->sId), ['style'=>'vertical-align: middle;']);

        $hdrTbl = new HTMLTable();

        $hdrTbl->addBodyTr($header);

        return $hdrTbl->generateMarkup(array("id"=>"stmtHeader", "class" => "mb-3 fullWidth"));
    }

    public static function makeEmailTbl($emFrom = "", $emSubject = "", $emAddrs = "", $emBody = "", $idRegistration = 0, $idVisit = 0){
        $emtableMarkup = "";
        $emTbl = new HTMLTable();

        $emTbl->addBodyTr(
			HTMLTable::makeTd('From', ['class'=>"tdlabel", 'style'=>"width: 110px"]) . 
			HTMLTable::makeTd($emFrom)
		);
		$emTbl->addBodyTr(
			HTMLTable::makeTd('Subject', ['class'=>"tdlabel", 'style'=>"width: 110px"]) . 
			HTMLTable::makeTd(HTMLInput::generateMarkup($emSubject, array('name' => 'txtSubject')))
		);
        $emTbl->addBodyTr(
			HTMLTable::makeTd('To', ['class'=>"tdlabel"]) . 
            HTMLTable::makeTd(HTMLInput::generateMarkup($emAddrs, array('name' => 'txtEmail')))
		);
        $emTbl->addBodyTr(
			HTMLTable::makeTd('Body', ['class'=>"tdlabel"]) . 
            HTMLTable::makeTd(HTMLContainer::generateMarkup("textarea", $emBody, array('name' => 'txtBody', 'class' => 'hhk-autosize')))
		);
		$emTbl->addBodyTr(
			HTMLTable::makeTd('Attachment', ['class'=>"tdlabel"]) . 
			HTMLTable::makeTd(HTMLContainer::generateMarkup("a", 'Statement.pdf <i class="ml-1 bi bi-cloud-arrow-down-fill"></i>', array('href' => 'ShowStatement.php?vid=' . $idVisit . '&reg=' . $idRegistration . '&pdfDownload', 'class' => 'hhk-autosize')))
		);

        $emtableMarkup .= HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("h4", 'Email ' . Labels::getString('MemberType', 'visitor', 'Guest') . ' Statement'), ['class' => "ui-widget ui-widget-header align-center ui-corner-top"]);
        
        $emtableMarkup .= HTMLContainer::generateMarkup("div", 
			$emTbl->generateMarkup(array("class"=>"emTbl mb-2"), ) . 
			HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('button', '&nbsp;<span>Send</span> <i class="ml-2 bi bi-send-fill"></i>', array('style'=>'font-size: 0.9em;', 'type'=>"button", "id"=>"btnEmail", 'class'=> 'ui-button ui-corner-all ui-widget', 'data-reg'=>$idRegistration, 'data-vid'=>$idVisit)), ["class"=>'align-center']), ["class"=>"p-2 hhk-tdbox mb-3 ui-widget ui-widget-content ui-corner-bottom hhk-visitdialog"]);

        $emtableMarkup .= HTMLContainer::generateMarkup("div",
			HTMLInput::generateMarkup('Print', ["type" => "button", "id" => "btnPrint", "class" => "ui-button ui-corner-all ui-widget mr-3"])
            //. HTMLInput::generateMarkup("Download MS Word", ["type"=>"submit", "name"=>"btnWord", "id"=>"btnWord", "class"=>"ui-button ui-corner-all ui-widget mr-3"])
            ,
		["class"=>'mb-3']);

        return $emtableMarkup;
    }

    public static function makePDF($stmtMarkup = "", bool $download = false)
	{

		$mpdf = new Mpdf(['tempDir' => sys_get_temp_dir() . "/mpdf"]);
		$mpdf->showImageErrors = true;
		$mpdf->WriteHTML('<html><head>' . HOUSE_CSS . GRID_CSS . STATEMENT_CSS . '</head><body style="font-size: 0.9em"><div class="PrintArea">' . $stmtMarkup . '</div></body></html>');

		if($download == true){
			$mpdf->OutputHttpDownload("Statement.pdf");
		} else {
			return $mpdf->Output('', 'S');
		}
	}

    public static function makeEmailTblOLD($emSubject = "", $emAddrs = "", $emBody = "", $idRegistration = 0, $idVisit = 0){
        // create send email table
        $emTbl = new HTMLTable();
        $emTbl->addBodyTr(HTMLTable::makeTd('Subject: ' . HTMLInput::generateMarkup($emSubject, array('name'=>'txtSubject', 'class'=>'ignrSave ml-2')), array("class"=>"hhk-flex")));
        $emTbl->addBodyTr(HTMLTable::makeTd(
                'Email: '
                . HTMLInput::generateMarkup($emAddrs, array('name'=>'txtEmail', 'class'=>'ignrSave ml-2')), array("class"=>"hhk-flex")));
        $emTbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Send Email', array('class'=> 'ui-button ui-corner-all ui-widget', 'name'=>'btnEmail', 'type'=>'button', 'data-reg'=>$idRegistration, 'data-vid'=>$idVisit))));

        $emtableMarkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('form',
                $emTbl->generateMarkup(array('class'=>'emTbl'), 'Email '.Labels::getString('MemberType', 'visitor', 'Guest') . ' Statement'), array('id'=>'formEm'))

                .HTMLContainer::generateMarkup('form',
                        HTMLInput::generateMarkup('Print', array('type'=>'button', 'id'=>'btnPrint', 'class'=>'ui-button ui-corner-all ui-widget mr-3 mt-2'))
                        .HTMLInput::generateMarkup('Download to MS Word', array('name'=>'btnWord', 'type'=>'submit', 'class'=>'ui-button ui-corner-all ui-widget mr-3 mt-2'))
                        .HTMLInput::generateMarkup($idRegistration, array('name'=>'hdnIdReg', 'type'=>'hidden'))
                        .HTMLInput::generateMarkup($idVisit, array('name'=>'hdnIdVisit', 'type'=>'hidden'))
                        , array('name'=>'formWord','action'=>'ShowStatement.php', 'method'=>'post'))
                ,array('class'=>'ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox my-3'));

        return $emtableMarkup;
    }

    /**
     * Summary of makeSummaryDiv
     * @param mixed $guestName
     * @param mixed $patientName
     * @param mixed $hospital
     * @param array $diags
     * @param Labels $labels
     * @param mixed $totalCharge
     * @param mixed $totalThirdPayments
     * @param mixed $totalGuestPayments
     * @param mixed $MOABalance
     * @param mixed $depositBalance
     * @param mixed $totalNights
     * @return string
     */
    protected static function makeSummaryDiv($guestName, $patientName, $hospital, $diags, $labels, $totalCharge, $totalThirdPayments, $totalGuestPayments, $MOABalance, $prepayments, $depositBalance, $totalNights) {

        $uS = Session::getInstance();
        $tbl = new HTMLTable();

        if ($guestName != '') {
            $tbl->addBodyTr(HTMLTable::makeTd(Labels::getString('memberType', 'visitor', 'Guest') . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($guestName));
        }

        $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('MemberType', 'patient', 'Patient') . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($patientName));

        // Show diagnosis
        if ($uS->ShowDiagOnStmt && count($diags) > 0 && $diags[1] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('statement', 'diagnosis', 'Diagnosis') . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($diags[1]));

            if (count($diags) > 1 && $diags[2] != '') {
                $tbl->addBodyTr(HTMLTable::makeTd('2nd '.$labels->getString('statement', 'diagnosis', 'Diagnosis') . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($diags[2]));
            }
        }

        $tbl->addBodyTr(HTMLTable::makeTd('Provider:', array('class'=>'tdlabel')) . HTMLTable::makeTd($hospital));

        // Set up balance prompt ..
        $bal = $totalCharge - ($totalThirdPayments + $totalGuestPayments);
        if ($bal > 0) {
            $finalWord = $labels->getString('statement', 'balanceDueLabel', 'Current Balance Due');
        } else if ($bal == 0) {
            $finalWord = $labels->getString('statement', 'zeroBalanceLabel', 'Current Balance');
        } else {
            $finalWord = $labels->getString('statement', 'guestCreditLabel', 'Guest Credit');
            $bal = abs($bal);
        }

        $sTbl = new HTMLTable();

        $sTbl->addHeaderTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup("strong", "Statement Summary"), ["class"=>"border-none align-center", "colspan"=>"2"])
        );

        $sTbl->addBodyTr(
            HTMLTable::makeTd('Total Nights:', array('class'=>'tdlabel'))
            . HTMLTable::makeTd(number_format($totalNights, 0), array('class'=>'align-center')),
            ["class"=>"sumDivider"]);

        $sTbl->addBodyTr(
            HTMLTable::makeTd($labels->getString('statement', 'TotalLabel', 'Total') . ':', array('class'=>'tdlabel'))
            . HTMLTable::makeTd('$'. number_format($totalCharge, 2), array('class'=>'align-right')),
            ["class"=>"sumDivider"]);

        if ($totalThirdPayments > 0) {
            $sTbl->addBodyTr(
                HTMLTable::makeTd('3rd Party Payments:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'. number_format($totalThirdPayments, 2), array('class'=>'align-right')));
        }

        $sTbl->addBodyTr(
            HTMLTable::makeTd($labels->getString('memberType', 'visitor', 'Guest') . ' Payments:', array('class'=>'tdlabel'))
            . HTMLTable::makeTd('$'. number_format($totalGuestPayments, 2), array('class'=>'align-right')),
            ["class"=>"sumDivider"]);

        $sTbl->addBodyTr(
            HTMLTable::makeTd("$finalWord:", ['class' => 'tdlabel'])
            . HTMLTable::makeTd('$'. number_format($bal, 2), ['class' => 'align-right']),
        ['class'=>'balanceLine']);


        if ($MOABalance > 0) {
            $sTbl->addBodyTr(
                HTMLTable::makeTd('Money on Account:', ['class' => 'tdlabel'])
                . HTMLTable::makeTd('($'. number_format($MOABalance, 2) . ')', ['class' => 'align-right']));
        }

        if ($prepayments > 0) {
            $sTbl->addBodyTr(
                HTMLTable::makeTd('Reservation Prepayments:', ['class' => 'tdlabel'])
                . HTMLTable::makeTd('($'. number_format($prepayments, 2) . ')', ['class' => 'align-right']));
        }

        if ($depositBalance > 0) {
            $sTbl->addBodyTr(
                HTMLTable::makeTd($labels->getString('statement', 'keyDepositLabel', 'Deposit'), ['class' => 'tdlabel'])
                . HTMLTable::makeTd('($' . number_format($depositBalance, 2) . ')', ['class' => 'align-right'])
            );
        }

        $bodyTbl = new HTMLTable();

        $bodyTbl->addBodyTr(
            HTMLTable::makeTd(
                HTMLContainer::generateMarkup("strong", 'Prepared '.date('M jS, Y')) .
                $tbl->generateMarkup(), ['class'=>'border-none']) .
            HTMLTable::makeTd(
                $sTbl->generateMarkup(["class"=>"tblStmtSummary"])
            , array("class"=>"align-right border-none"))
        );

        return $bodyTbl->generateMarkup(array("id"=>"stmtSummary", "class" => "mb-3 fullWidth"));
    }

}

