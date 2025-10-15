<?php
namespace HHK\House\Report;

use DateTime;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\VolMemberType;

class ItemReport
{

    public static function doMarkupRow($fltrdFields, $r, $isLocal, $invoice_Statuses, $diagnoses, $locations, &$total, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId, $returnId, $labels) {

        $amt = $r['Amount'];

        $payStatusAttr = array();
        $attr['style'] = 'text-align:right;';


        $invNumber = $r['Invoice_Number'];

        if ($invNumber != '') {

            $iAttr = array('href'=>'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'style'=>'float:left;', 'target'=>'_blank');

            if ($r['Invoice_Deleted'] > 0) {
                $iAttr['style'] .= 'color:red;';
                $iAttr['title'] = 'Invoice is Deleted.';
            } else if ($r['Balance'] != 0 && $r['Balance'] != $r['Invoice_Amount']) {

                $iAttr['title'] = 'Partial payment.';
                $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
            }

            $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
                .HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$r['idInvoice_Line'], 'data-stat'=>'view', 'data-iid'=>$r['idInvoice'], 'style'=>'cursor:pointer;', 'title'=>'View Items'));
        }

        $invoiceMkup = HTMLContainer::generateMarkup('span', $invNumber, array("style"=>'white-space:nowrap'));

        $dateDT = new DateTime($r['Invoice_Date']);

        $invoiceStatus = '';
        if (isset($invoice_Statuses[$r['Status']])) {
            $invoiceStatus = $invoice_Statuses[$r['Status']][1];
        }

        // Names
        if ($r['Sold_To_Id'] == $subsidyId) {
            $company = $r['Company'];
            $payorFirst = '';
            $payorLast = '';
        } else if ($r['Billing_Agent'] == VolMemberType::BillingAgent || $returnId = $r['Sold_To_Id']) {
            $company = $r['Company'];
            $payorFirst = $r['Name_First'];
            $payorLast = $r['Name_Last'];
        } else {
            $payorLast = HTMLContainer::generateMarkup('a', $r['Name_Last'], array('href'=>'GuestEdit.php?id=' . $r['Sold_To_Id'], 'title'=>'Click to go to the '.$labels->getString('MemberType', 'visitor', 'Guest'). ' Edit page.'));
            $payorFirst = $r['Name_First'];
            $company = '';
        }

        $g = array(
            'vid'=>$r['Order_Number'] . '-' . $r['Suborder_Number'],
            'Company'=>$company,
            'Last'=>$payorLast,
            'First'=>$payorFirst,
            'Address' =>$r['Address'],
            'City'=>$r['City'],
            'County'=>$r['County'],
            'State_Province'=>$r['State_Province'],
            'Postal_Code'=>$r['Postal_Code'],
            'Country'=>$r['Country'],
            'Status' => $invoiceStatus,
            'Diagnosis' => (isset($diagnoses[$r['Diagnosis']]) ? $diagnoses[$r['Diagnosis']][1] : ''),
            'Location' => (isset($locations[$r['Location']]) ? $locations[$r['Location']][1] : ''),
            'Description' => $r['Description'],
            'Invoice_Notes' => $r["Invoice_Notes"],
            'Payment_Notes' => $r["Payment_Notes"],
            'Patient_Id' => $r['Patient_Id'],
            'Patient_Name_Last' => $r['Patient_Name_Last'],
            'Patient_Name_First' => $r['Patient_Name_First'],
            'Patient_Address' =>$r['Patient_Address'],
            'Patient_City'=>$r['Patient_City'],
            'Patient_County'=>$r['Patient_County'],
            'Patient_State_Province'=>$r['Patient_State_Province'],
            'Patient_Postal_Code'=>$r['Patient_Postal_Code'],
            'Patient_Country'=>$r['Patient_Country'],
            'Invoice_Number' => $r['Invoice_Number'],
            'Amount' => $amt,
            'Updated_By'=>$r["Updated_By"],
        );

        $total += $amt;

        if ($isLocal) {

            $g['Amount'] = HTMLContainer::generateMarkup('span', number_format($amt, 2), $attr);
            $g['Invoice_Number'] = $invoiceMkup;
            $g['Status'] = HTMLContainer::generateMarkup('span', $invoiceStatus, $payStatusAttr);
            $g['Date'] = $dateDT->format('c');

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
            }

            $tbl->addBodyTr($tr);

        } else {

            $g['Date'] = $r['Invoice_Date'];

            foreach ($fltrdFields as $f) {
                $flds[] = $g[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);

        }

    }

}