<?php
namespace HHK\House\Report;

use DateTime;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\SysConst\InvoiceStatus;
use HHK\SysConst\ItemId;

class InvoiceReport
{

    public static function doMarkupRow($fltrdFields, $r, $isLocal, $hospital, $statusTxt, &$tbl, &$writer, $hdr, &$reportRows, $subsidyId) {

        $g = array();

        $g['Payor'] = $r['Company'];
        if ($r['Sold_To_Name'] != '') {

            if ($r['Company'] != '') {
                $g['Payor'] = $r['Company'] . ' c/o ';
            }

            $g['Payor'] .= $r['Sold_To_Name'];
        }


        $g['Patient'] = $r['Patient_Name'];
        if ($r['idPatient'] > 0) {
            $g['Patient'] = HTMLContainer::generateMarkup('a', $g['Patient'], ['href' => 'GuestEdit.php?id=' . $r['idPatient']]);
        }

        $g['payments'] = HTMLContainer::generateMarkup('span', number_format(($r['Amount'] - $r['Balance']), 2), ['style' => 'float:right;']);
        if (($r['Amount'] - $r['Balance']) != 0 && $r['Sold_To_Id'] != $subsidyId) {
            $g['payments'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'vwpmt' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'vpmt', 'style' => 'cursor:pointer;', 'title' => 'View Payments']);
        }

        $invoiceNumber = $r['Invoice_Number'];
        if ($invoiceNumber != '') {

            $invAttr = ['href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'target' => '_blank'];

            if ($r['Balance'] != 0 && $r['Balance'] != $r['Amount']) {
                $invoiceNumber .= HTMLContainer::generateMarkup('sup', '-p');
                $invAttr['title'] = 'Partial Payment';
            }

            $invoiceNumber = HTMLContainer::generateMarkup('a', $invoiceNumber, $invAttr)
                .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction ml-2', 'id' => 'invicon' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items']);
        }

        //$g['invoiceMkup'] = HTMLContainer::generateMarkup('span', $invoiceNumber, ["style" => 'white-space:nowrap;']);
        $g['invoiceMkup'] = $invoiceNumber;

        if ($r['Status'] == InvoiceStatus::Carried && $r['Deleted'] == 0) {

            $r['Balance'] = 0;

            $g['Status'] = HTMLContainer::generateMarkup('span',
                    HTMLContainer::generateMarkup('span', $statusTxt . ' by ' . HTMLContainer::generateMarkup('a', $r['Delegated_Invoice_Number'], ['href' => 'ShowInvoice.php?invnum=' . $r['Delegated_Invoice_Number'], 'target' => '_blank']))
                    .HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicond' . $r['Delegated_Invoice_Id'], 'data-iid' => $r['Delegated_Invoice_Id'], 'data-stat' => 'view', 'style' => 'cursor:pointer;', 'title' => 'View Items'])
                    , array("style"=>'white-space:nowrap;'));
        } else {

            $g['Status']= HTMLContainer::generateMarkup('span', $statusTxt, ["style" => 'white-space:nowrap;']);
        }

        $dateDT = new DateTime($r['Invoice_Date']);
        $g['date'] = $dateDT->format('c');

        $billDateStr = ($r['BillDate'] == '' ? '' : date('M j, Y', strtotime($r['BillDate'])));

        $g['billed'] = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup('span', $billDateStr, ['id' => 'trBillDate' . $r['Invoice_Number']])
            . HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-calendar invSetBill', 'data-name' => $g['Payor'], 'data-inb' => $r['Invoice_Number'], 'style' => 'cursor:pointer;margin-left:5px;', 'title' => 'Set Billing Date']),
            ["class"=>"hhk-flex", "style"=>"justify-content: space-between; align-items: end;"]);

        $g['emailed'] = ($r["EmailDate"] == '' ? '' : (new DateTime($r["EmailDate"]))->format("M j, Y"));

        $g['Amount'] = number_format($r['Amount'], 2);

        // Show Delete Icon?
        if (($r['Amount'] == 0 || $r['Item_Id'] == ItemId::Discount) && $r['Deleted'] != 1) {
            $g['Amount'] .= HTMLContainer::generateMarkup('span','', ['class' => 'ui-icon ui-icon-trash invAction', 'id' => 'invdel' . $r['idInvoice'], 'data-inv' => $r['Invoice_Number'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'del', 'style' => 'cursor:pointer;', 'title' => 'Delete This Invoice']);
        }

        $g['County'] = $r['County'];
        $g['Zip'] = $r['Zip'];
        $g['Title'] = $r['Title'];
        $g['Arrival'] = $r['Arrival'];
        $g['Departure'] = $r['Departure'];
        $g['hospital'] = $hospital;
        $g['Balance'] = number_format($r['Balance'], 2);
        $g['Notes'] = HTMLContainer::generateMarkup('div', $r['Notes'], ['id' => 'divInvNotes' . $r['Invoice_Number'], 'style' => 'max-width:190px; white-space:normal;']);

    //add invoice notes to payment report
        if ($isLocal) {

            $tr = '';
            foreach ($fltrdFields as $f) {
                $tr .= HTMLTable::makeTd($g[$f[1]], $f[6]);
            }

            $tbl->addBodyTr($tr);

        } else {

            $g['invoiceMkup'] = $r['Invoice_Number'];
            $g['date'] = $r['Invoice_Date'];
            $g['Status'] = $statusTxt;
            $g['billed'] = $billDateStr;
            $g['Patient'] = $r['Patient_Name'];
            $g['Notes'] = $r['Notes'];
            $g['payments'] = $r['Amount'] - $r['Balance'];
            $g['Amount'] = $r['Amount'];
            $g['Balance'] = $r['Balance'];

            $n = 0;
            $flds = array();

            foreach ($fltrdFields as $f) {
                //$flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
                $flds[] = $g[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }
    }
}