<?php

namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\Payment\Receipt;
use HHK\SysConst\{PaymentMethod, PaymentStatusCode};
use HHK\sec\Session;
use HHK\ExcelHelper;

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
            $startDT = new \DateTime($txtStart);
            $start = $startDT->format('Y-m-d 00:00:00');
        }

        if ($txtEnd != '') {
            $endDT = new \DateTime($txtEnd);
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


        $reportRows = 1;
        $file = 'PaymentReport';
        $writer = new ExcelHelper($file);
        $writer->setAuthor($uS->username);
        $writer->setTitle('Payment Report');
        
        // build header
        $hdr = array(
            "Id"=>"string",
            "Third Party"=>"string",
            "Last"=>"string",
            "First"=>"string",
            "Date"=>"MM/DD/YYYY",
            "Invoice Number"=>"string",
            "Room"=>"string",
            "Pay Type"=>"string",
            "Pay Detail"=>"string",
            "Status"=>"string",
            "Original Amount"=>"dollar",
            "Amount"=>"dollar",
            "Notes"=>"string"
        );

        $colWidths = array('10', '10', '20', '20', '15', '10', '10', '15', '20', '15', '15', '15', '20');
        
        $hdrStyle = $writer->getHdrStyle($colWidths);
        
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);

        $name_lk = $uS->nameLookups;
        $name_lk['Pay_Status'] = readGenLookupsPDO($dbh, 'Pay_Status');
        $uS->nameLookups = $name_lk;

        // Now the data ...
        foreach ($invoices as $r) {

            // Payments
            foreach ($r['p'] as $p) {

                self::doDayMarkupRow($r, $p, $writer, $hdr, $reportRows, $uS->subsidyId, $uS->returnId);

            }
        }
        $writer->download();
    }

    protected static function doDayMarkupRow($r, $p, &$writer, $hdr, &$reportRows, $subsidyId, $returnId) {

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

        $flds = array(
            $r['i']['Sold_To_Id'],
            ($r['i']['Bill_Agent'] == 'a' || $r['i']['Sold_To_Id'] == $returnId ? $r['i']['Company'] : ''),
            $r['i']['Last'],
            $r['i']['First'],
            $p['Payment_Date'],
            $r['i']['Invoice_Number'],
            $r['i']['Room'],
            $payType,
            $payDetail,
            $payStatus,
            $origAmt,
            $amt,
            $p['Payment_Note']
        );

        $row = $writer->convertStrings($hdr, $flds);
        
        $writer->writeSheetRow("Sheet1", $row);
    }

    public static function doMarkupRow($fltrdFields, $r, $p, $isLocal, $hospital, &$total, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId) {

        $origAmt = $p['Payment_Amount'];
        $amt = 0;
        $payDetail = '';
        $payGW = '';
        $payStatus = $p['Payment_Status_Title'];
        $dateDT = new \DateTime($p['Payment_Date']);

        // Use timestamp for time of day.
        $timeDT = new \DateTime($p['Payment_Timestamp']);
        $dbmsDt = new \DateTime('', new \DateTimeZone('America/Los_Angeles'));
        $offset = abs(($dbmsDt->getOffset() - $timeDT->getOffset()) / 3600);
        $timeDT->add(new \DateInterval('PT' . $offset ."H"));

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
                        $dateDT = new \DateTime($a['Auth_Last_Updated']);
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
                $dateDT = new \DateTime($p['Last_Updated']);
                $timeDT = new \DateTime($p['Last_Updated']);
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
            $g['Payment_Date'] = $dateDT->format("Y-m-d");
            $g['Payment_Timestamp'] = $timeDT->format('Y-m-d H:i:s');
            $g['Invoice_Number'] = $r['i']['Invoice_Number'];
            $g['Status'] = $payStatus;
            $g['Orig_Amount'] = $origAmt;
            $g['Amount'] = $amt;

            $flds = array(
                $r['i']['Sold_To_Id'],
                ($r['i']['Bill_Agent'] == 'a' ? $r['i']['Company'] : '')
            );
            
            foreach ($fltrdFields as $f) {
                $flds[] = $g[$f[1]];
            }
            
            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);

        }

    }

}
?>