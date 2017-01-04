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

//        $statusList = readGenLookupsPDO($dbh, 'Payment_Status');

        $payTypes = array();
        $txtStart = '';
        $txtEnd = '';
        $statusSelections = array();
        $payTypeSelections = array();

        foreach ($uS->nameLookups[GL_TableNames::PayType] as $p) {
            if ($p[2] != '') {
                $payTypes[$p[2]] = array($p[2], $p[1]);
            }
        }

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


        $whDates = " and lp.Payment_Date < '$end' and lp.Payment_Date >= '$start' ";


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
        vlist_inv_pments lp
            left join
        `name` n ON lp.Sold_To_Id = n.idName
            left join
        visit v on lp.Order_Number = v.idVisit and lp.Suborder_Number = v.Span
            left join
        resource r ON v.idResource = r.idResource
    where lp.idPayment > 0
      $whDates $whStatus $whType order by `idInvoice`, `idPayment`, `idPayment_auth`";

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

                self::doMarkupRow($r, $p, $sml, $reportRows, $uS->subsidyId, $uS->returnId);

            }
        }

        // Finalize and print.
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $file . '.xlsx"');
            header('Cache-Control: max-age=0');

            OpenXML::finalizeExcel($sml);
            exit();

    }

    protected static function doMarkupRow($r, $p, &$sml, &$reportRows, $subsidyId, $returnId) {

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

            case PaymentStatusCode::VoidReturn:
                $amt = 0;
                $origAmt = 0 - $origAmt;

                break;

            case PaymentStatusCode::Reverse:

                $amt = 0;
                break;

            case PaymentStatusCode::Retrn:

                $amt = 0;
                break;

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


}
