<?php

/*
 * PaymentReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PaymentReport
 *
 * @author Eric
 */
class PaymentReport {

    public static function generateDayReport(\PDO $dbh, $post) {

        $uS = Session::getInstance();


        $txtStart = '';
        $txtEnd = '';
        $statusSelections = array();
        $payTypeSelections = array();

        if (isset($post['stDate'])) {
            $txtStart = filter_var($post['stDate'], FILTER_SANITIZE_STRING);

            if ($txtStart == '') {
                $txtStart = date('Y-m-d');
            }
        }

        if (isset($post['enDate'])) {
            $txtEnd = filter_var($post['enDate'], FILTER_SANITIZE_STRING);

            if ($txtEnd == '') {
                $txtEnd = date('Y-m-d');
            }
        }

        if (isset($post['selPayStatus'])) {
            $reqs = $post['selPayStatus'];
            if (is_array($reqs)) {
                $statusSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
            }
        }

        if (isset($post['selPayType'])) {
            $reqs = $post['selPayType'];
            if (is_array($reqs)) {
                $payTypeSelections = filter_var_array($reqs, FILTER_SANITIZE_STRING);
            }
        }

        $showDelInv = FALSE;
        if (isset($post['fcbdinv'])) {
            $showDelInv = TRUE;
        }


        // Dates
        if ($txtStart != '') {
            $startDT = new DateTime($txtStart);
            $start = $startDT->format('Y-m-d 00:00:00');
        }

        if ($txtEnd != '') {
            $endDT = new DateTime($txtEnd);
            $end = $endDT->format('Y-m-d 23:59:59');
        }


        $whDates = " and DATE(lp.Payment_Date) <= DATE('$end') and DATE(lp.Payment_Date) >= DATE('$start') ";


        $whStatus = '';
        foreach ($statusSelections as $s) {
            if ($s != '') {
                // Set up query where part.
                if ($whStatus == '') {
                    $whStatus = "'" . $s . "'";
                } else {
                    $whStatus .= ",'".$s . "'";
                }

            }
        }

        if ($whStatus != '') {
            $whStatus = " and lp.Payment_Status in (" . $whStatus . ") ";
        }


        $whType = '';
        foreach ($payTypeSelections as $s) {
            if ($s != '') {
                // Set up query where part.
                if ($whType == '') {
                    $whType = $s ;
                } else {
                    $whType .= ",".$s;
                }

            }
        }

        if ($whType != '') {
            $whType = " and lp.idPayment_Method in (" . $whType . ") ";
        }

        if ($showDelInv === FALSE) {
            $whType .= " and lp.Deleted = 0 ";
        }


        $query = "Select
        lp.*,
        ifnull(n.Name_First, '') as `First`,
        ifnull(n.Name_Last, '') as `Last`,
        ifnull(n.Company, '') as `Company`,
        ifnull(r.Title, '') as `Room`
    from
        vlist_pments lp
            left join
        `name` n ON lp.Sold_To_Id = n.idName
            left join
        visit v on lp.Order_Number = v.idVisit and lp.Suborder_Number = v.Span
            left join
        resource r ON v.idResource = r.idResource
    where lp.idPayment > 0
      $whDates $whStatus $whType ";

        $stmt = $dbh->query($query);
        $invoices = Receipt::processPayments($stmt, array('First', 'Last', 'Company', 'Room'));

        require_once CLASSES . 'OpenXML.php';

        $reportRows = 1;
        $file = 'PaymentReport';
        $sml = OpenXML::createExcel($uS->username, 'Payment Report');

        // build header
        $hdr = array();
        $n = 0;

        $hdr[$n++] = "Id";
        $hdr[$n++] = "Third Party";
        $hdr[$n++] = "Last";
        $hdr[$n++] = "First";
        $hdr[$n++] = "Date";
        $hdr[$n++] = "Invoice Number";
        $hdr[$n++] = "Room";
        $hdr[$n++] = "Pay Type";
        $hdr[$n++] = "Pay Detail";
        $hdr[$n++] = "Status";
        $hdr[$n++] = "Original Amount";
        $hdr[$n++] = "Amount";
        $hdr[$n++] = "Notes";

        OpenXML::writeHeaderRow($sml, $hdr);
        $reportRows++;


        $name_lk = $uS->nameLookups;
        $name_lk['Pay_Status'] = readGenLookupsPDO($dbh, 'Pay_Status');
        $uS->nameLookups = $name_lk;

        // Now the data ...
        foreach ($invoices as $r) {

            // Payments
            foreach ($r['p'] as $p) {

                self::doDayMarkupRow($r, $p, $sml, $reportRows, $uS->subsidyId, $uS->returnId);

            }
        }

        // Finalize and print.
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
            header('Cache-Control: max-age=0');

            OpenXML::finalizeExcel($sml);
            exit();

    }

    protected static function doDayMarkupRow($r, $p, &$sml, &$reportRows, $subsidyId, $returnId) {

        $origAmt = $p['Payment_Amount'];
        $amt = 0;
        $payDetail = '';
        $payStatus = $p['Payment_Status_Title'];
        $payType = $p['Payment_Method_Title'];

        if ($p['idPayment_Method'] == PaymentMethod::Charge || $p['idPayment_Method'] == PaymentMethod::ChgAsCash) {

            if (isset($p['auths'])) {

                foreach ($p['auths'] as $a) {

                    if ($a['Card_Type'] != '') {
                        $payDetail = $a['Card_Type'] . ' - ' . $a['Masked_Account'];
                    }
                }
            }

            $payType = 'Credit Card';

        } else if ($p['idPayment_Method'] == PaymentMethod::Check || $p['idPayment_Method'] == PaymentMethod::Transfer) {

            $payDetail = $p['Check_Number'];
        }

        switch ($p['Payment_Status']) {

            case PaymentStatusCode::VoidSale:

                $amt = 0;
                break;

            case PaymentStatusCode::Reverse:

                $amt = 0;
                break;

            case PaymentStatusCode::Retrn:

                $amt = 0;
                break;

            case PaymentStatusCode::VoidReturn:
            case PaymentStatusCode::Paid:

                if ($p['Is_Refund'] == 1) {
                    $origAmt = 0 - $origAmt;
                }

                $amt = $origAmt;

                break;

            case PaymentStatusCode::Declined:
                $amt = $origAmt;

                break;

        }


        // Mark deleted invoices.
        if ($r['i']['Invoice_Deleted'] > 0) {
            $amt = 0;
            $r['i']['Invoice_Number'] .= '-Deleted';
        }

        // Set names for special
        if ($r['i']['Sold_To_Id'] == $subsidyId) {
            $payType = $r['i']['Invoice_Description'];
        }


        $n = 0;
        $flds = array(
            $n++ => array('type' => "n",
                'value' => $r['i']['Sold_To_Id']
            ),
            $n++ => array('type' => "s",
                'value' => ($r['i']['Bill_Agent'] == 'a' || $r['i']['Sold_To_Id'] == $returnId ? $r['i']['Company'] : '')
            ),
            $n++ => array('type' => "s",
                'value' => $r['i']['Last']
            ),
            $n++ => array('type' => "s",
                'value' => $r['i']['First']
            ),
            $n++ => array('type' => "n",
                'value' => PHPExcel_Shared_Date::PHPToExcel(strtotime($p['Payment_Date'])),
                'style' => PHPExcel_Style_NumberFormat::FORMAT_DATE_XLSX22
            ),
            $n++ => array('type' => "s",
                'value' => $r['i']['Invoice_Number']
            ),
            $n++ => array('type' => "s",
                'value' => $r['i']['Room']
            ),
            $n++ => array('type' => "s",
                'value' => $payType
            ),
            $n++ => array('type' => "s",
                'value' => $payDetail
            ),
            $n++ => array('type' => "s",
                'value' => $payStatus
            ),
            $n++ => array('type' => "n",
                'value' => $origAmt
            ),
            $n++ => array('type' => "n",
                'value' => $amt
            ),
            $n++ => array('type' => "s",
                'value' => $p['Payment_Note']
            )
        );

        $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

    }

    public static function doMarkupRow($fltrdFields, $r, $p, $isLocal, $hospital, &$total, &$tbl, &$sml, &$reportRows, $subsidyId) {

        $origAmt = $p['Payment_Amount'];
        $amt = 0;
        $payDetail = '';
        $payGW = '';
        $payStatus = $p['Payment_Status_Title'];
        $dateDT = new \DateTime($p['Payment_Date']);

        // Use timestamp for time of day.
        $timeDT = new DateTime($p['Payment_Timestamp']);
        $dbmsDt = new DateTime('', new DateTimeZone('America/Los_Angeles'));
        $offset = abs(($dbmsDt->getOffset() - $timeDT->getOffset()) / 3600);
        $timeDT->add(new DateInterval('PT' . $offset ."H"));

        $payType = $p['Payment_Method_Title'];
        $statusAttr = array();

        if ($p['idPayment_Method'] == PaymentMethod::Charge) {

            if (isset($p['auths'])) {

                foreach ($p['auths'] as $a) {

                	if ($a['Card_Type'] != '') {
                		$payDetail = $a['Card_Type'] . ' - ' . $a['Masked_Account'];
                	}
                	
                	if ($a['Merchant'] != '' && $a['Merchant'] != 'Production') {
                		$payGW = ucfirst($a['Merchant']);
                	}
                	
                    if ($a['Auth_Last_Updated'] !== '') {
                        $dateDT = new DateTime($a['Auth_Last_Updated']);
                    }
                }
            }

            $payType = 'Credit Card';


        } else if ($p['idPayment_Method'] == PaymentMethod::Check || $p['idPayment_Method'] == PaymentMethod::Transfer) {

            $payDetail = $p['Check_Number'];
        }

        switch ($p['Payment_Status']) {

            case PaymentStatusCode::VoidSale:
                $statusAttr['style'] = 'color:#ea4848;';
                break;

            case PaymentStatusCode::Reverse:
                $statusAttr['style'] = 'color:#ea4848;';
                break;

            case PaymentStatusCode::Retrn:  // Return payment
                $statusAttr['style'] = 'color:#ea4848;';
                $dateDT = new DateTime($p['Last_Updated']);
                $timeDT = new DateTime($p['Last_Updated']);
                break;

            case PaymentStatusCode::VoidReturn:
            case PaymentStatusCode::Paid:

                if ($p['Is_Refund'] == 1) {  // Return Amount
                    $origAmt = 0 - $origAmt;
                    $payStatus = 'Refund';
                    $statusAttr['style'] = 'color:#ea4848;';
                }

                $amt = $origAmt;
                break;

            case PaymentStatusCode::Declined:
                $statusAttr['style'] = 'color:#ea4848;';
                break;

        }


        if ($r['i']['Sold_To_Id'] == $subsidyId) {

            $payType = 'House Discount';
            $payorLast = $r['i']['Company'];
            $payorFirst = '';

        } else if ($r['i']['Bill_Agent'] == 'a') {

            $payorLast = $r['i']['Company'];
            $payorFirst = $r['i']['Last'] . ', ' . $r['i']['First'];

        } else {

            $payorLast = HTMLContainer::generateMarkup('a', $r['i']['Last'], array('href'=>'GuestEdit.php?id=' . $r['i']['Sold_To_Id'], 'title'=>'Click to go to the Guest Edit page.'));
            $payorFirst = $r['i']['First'];
        }


        $invNumber = $r['i']['Invoice_Number'];

        if ($invNumber != '') {

            $iAttr = array('href'=>'ShowInvoice.php?invnum=' . $r['i']['Invoice_Number'], 'style'=>'float:left;', 'target'=>'_blank');

            if ($r['i']['Invoice_Deleted'] > 0) {
                $iAttr['style'] .= 'color:red;';
                $iAttr['title'] = 'Invoice is Deleted.';
            } else if ($r['i']['Invoice_Balance'] != 0) {

                $iAttr['title'] = 'Partial payment.';
                $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
            }

            $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
                .HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$p['idPayment'], 'data-stat'=>'view', 'data-iid'=>$r['i']['idInvoice'], 'style'=>'cursor:pointer;', 'title'=>'View Items'));
        }

        $invoiceMkup = HTMLContainer::generateMarkup('span', $invNumber, array('style'=>'white-space:nowrap'));
        $statusMkup = HTMLContainer::generateMarkup('span', $payStatus, $statusAttr);

        $g = array(
            'idHospital' => $hospital,
            'Title' => $r['i']['Room'],
            'Patient_Last'=>$r['i']['Patient_Last'],
            'Patient_First'=>$r['i']['Patient_First'],
            'Pay_Type' => $payType,
            'Detail' => $payDetail,
            'Payment_External_Id'=>$p['Payment_External_Id'],
            'By' => $p['Payment_Created_By'],
            'Notes'=>$p['Payment_Note'],
        	'Merchant'=>$payGW
        );


        if ($isLocal) {

            $g['Last'] = $payorLast;
            $g['First'] = $payorFirst;
            $g['Payment_Date'] = $dateDT->format('c');
            $g['Payment_Timestamp'] = $timeDT->format('H:i');
            $g['Invoice_Number'] = $invoiceMkup;
            $g['Status'] = $statusMkup;
            $g['Orig_Amount'] = number_format($origAmt, 2);
            $g['Amount'] = number_format($amt, 2);

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
            }

            $tbl->addBodyTr($tr);
            $total += $amt;


        } else {

            $g['Last'] = $r['i']['Last'];
            $g['First'] = $r['i']['First'];
            $g['Payment_Date'] = PHPExcel_Shared_Date::PHPToExcel($dateDT);
            $g['Payment_Timestamp'] = PHPExcel_Shared_Date::PHPToExcel($timeDT);
            $g['Invoice_Number'] = $r['i']['Invoice_Number'];
            $g['Status'] = $payStatus;
            $g['Orig_Amount'] = $origAmt;
            $g['Amount'] = $amt;

            $n = 0;

            $flds = array(
                $n++ => array('type' => "n",
                    'value' => $r['i']['Sold_To_Id']
                ),
                $n++ => array('type' => "s",
                    'value' => ($r['i']['Bill_Agent'] == 'a' ? $r['i']['Company'] : '')
                )
            );

            foreach ($fltrdFields as $f) {
                $flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
            }

            $reportRows = OpenXML::writeNextRow($sml, $flds, $reportRows);

        }

    }



}
