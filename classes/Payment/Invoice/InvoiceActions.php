<?php

namespace HHK\Payment\Invoice;

use HHK\sec\Session;
use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\Payment\Statement;
use HHK\Exception\{RuntimeException, PaymentException};

/**
 * Invoice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Invoice
 *
 * @author Eric
 */
class InvoiceActions {


    /**
     * Summary of invoiceAction
     * @param \PDO $dbh
     * @param mixed $iid
     * @param mixed $action
     * @param mixed $eid
     * @param string $container
     * @param mixed $showBillTo
     * @return array
     */
    public static function invoiceAction(\PDO $dbh, $iid, $action, $eid, $container, $showBillTo = FALSE) {

        if ($iid < 1) {
            return array('error' => 'Bad Invoice Id');
        }

        $uS = Session::getInstance();
        $mkup = '';

        if ($action == 'view') {

            // Return listing of lines

            $stmt = $dbh->query(
    "SELECT
        i.idInvoice,
        i.`Invoice_Number`,
        i.`Balance`,
        i.`Amount`,
        i.Deleted,
        i.Sold_To_Id,
        n.Name_Full,
        n.Company,
        ng.Name_Full AS `GuestName`,
        v.idVisit,
        v.Span,
        il.Description,
        il.Amount as `LineAmount`,
        il.Deleted as `Item_Deleted`
    FROM
        `invoice` i
            LEFT JOIN
        name n ON i.Sold_To_Id = n.idName
            LEFT JOIN
        visit v ON i.Order_Number = v.idVisit
            AND i.Suborder_Number = v.Span
            LEFT JOIN
        name ng ON v.idPrimaryGuest = ng.idName
        left join invoice_line il on i.idInvoice = il.Invoice_Id
    WHERE
        i.idInvoice = $iid");

            $tbl = new HTMLTable();
            $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $deleted = '';
            if ($lines[0]['Deleted'] == 1) {
                $deleted = '(Deleted)';
            }

            if (count($lines) > 0) {
                // create lines markup
                foreach ($lines as $l) {

                    if ($l['Item_Deleted'] == 0 && $lines[0]['Deleted'] == 0) {

                        $tbl->addBodyTr(
                                HTMLTable::makeTd($l['Description'], array('class' => 'tdlabel'))
                                . HTMLTable::makeTd(number_format($l['LineAmount'], 2), array('style' => 'text-align:right;')));
                    } else {
                        // Show deleted Itmes
                        $tbl->addBodyTr(
                                HTMLTable::makeTd($l['Description'], array('class' => 'tdlabel'))
                                . HTMLTable::makeTd(number_format($l['LineAmount'], 2), array('style' => 'text-align:right;')));
                    }
                }
            } else {
                // No invoice lines, show a blank
                $tbl->addBodyTr(
                                HTMLTable::makeTd('*No Items*', array('class' => 'tdlabel'))
                                . HTMLTable::makeTd(' ', array('style' => 'text-align:right;')));
            }


            $divAttr = array('id' => 'pudiv', 'class' => 'ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-panel', 'style' => 'position:absolute; min-width:300px;');
            $tblAttr = array('style' => 'background-color:lightyellow; width:100%;');

            if ($lines[0]['Deleted'] == 1) {
                $tblAttr['style'] = 'background-color:red; min-width:260px;';
            }

            $mkup = HTMLContainer::generateMarkup('div',
                $tbl->generateMarkup($tblAttr, "Items For Invoice $deleted#" . $lines[0]['Invoice_Number'] . HTMLContainer::generateMarkup('span', ' (' . $lines[0]['GuestName'] . ')', array('style' => 'font-size:.8em;')))
                . ($showBillTo ? Invoice::getBillToAddress($dbh, $lines[0]['Sold_To_Id'])->generateMarkup(array(), 'Bill To') : '')
                , $divAttr);

            return array('markup' => $mkup, 'eid' => $eid);

        } else if ($action == 'vpmt') {

            // Return listing of Payments
            $divAttr = array('id' => 'pudiv', 'class' => 'ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-panel', 'style' => 'clear:both; float:left;');
            $tblAttr = array('style' => 'background-color:lightyellow;');

            $tbl = new HTMLTable();
            $mkup = HTMLContainer::generateMarkup('div', 'No Payments', $divAttr);

            $stmt = $dbh->query("Select * from vlist_inv_pments where idPayment > 0 and idInvoice = $iid");
            $invoices = Statement::processPayments($stmt, array());

            foreach ($invoices as $r) {

                $tbl->addHeaderTr(HTMLTable::makeTh('Date') . HTMLTable::makeTh('Method') . HTMLTable::makeTh('Status') . HTMLTable::makeTh('Amount'));

                // Payments
                foreach ($r['p'] as $p) {

                    $tbl->addBodyTr(
                            HTMLTable::makeTd(($p['Payment_Date'] == '' ? '' : date('M j, Y', strtotime($p['Payment_Date']))), array('class' => 'tdlabel'))
                            . HTMLTable::makeTd($p['Payment_Method_Title'], array('class' => 'tdlabel'))
                            . HTMLTable::makeTd($p['Payment_Status_Title'], array('class' => 'tdlabel'))
                            . HTMLTable::makeTd(number_format($p['Payment_Amount'], 2), array('style' => 'text-align:right;'))
                    );
                }

                $mkup = HTMLContainer::generateMarkup('div', $tbl->generateMarkup($tblAttr, 'Payments For Invoice #: ' . $r['i']['Invoice_Number']), $divAttr);
            }

            return array('markup' => $mkup, 'eid' => $eid, 'container' => $container);

        } else if ($action == 'del') {

            $invoice = new Invoice($dbh);
            $invoice->loadInvoice($dbh, $iid);

            try {
                $invoice->deleteInvoice($dbh, $uS->username);
                return array('delete' => 'Invoice Number ' . $invoice->getInvoiceNumber() . ' is deleted.', 'eid' => $eid, 'container'=>$container);
            } catch (PaymentException $ex) {
                return array('error' => $ex->getMessage());
            }
        } else if ($action == 'srch') {

            $invNum = $iid . '%';
            $stmt = $dbh->query("Select idInvoice, Invoice_Number from invoice where Invoice_Number like '$invNum'");

            $numbers = array();

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $numbers[] = array('id' => $r['idInvoice'], 'value' => $r['Invoice_Number']);
            }

            return $numbers;
        }

        return array('error' => 'Bad Invoice Action.  ');
    }

    /**
     * Summary of invoiceSetBill
     * @param \PDO $dbh
     * @param mixed $invNum
     * @param mixed $invDateStr
     * @param mixed $user
     * @param mixed $element
     * @param mixed $notes
     * @param mixed $notesElement
     * @return array
     */
    public static function invoiceSetBill(\PDO $dbh, $invNum, $invDateStr, $user, $element, $notes, $notesElement)
    {

        if ($invNum == '') {
            return array('error' => 'Empty Invoice Number.');
        }

        if ($invDateStr != '') {

            try {
                $billDT = setTimeZone(NULL, $invDateStr);
            } catch (RunTImeException $ex) {
                return array('error' => 'Bad Date:  ' . $ex->getMessage());
            }
        } else {
            $billDT = NULL;
        }

        $invoice = new Invoice($dbh, $invNum);

        $wrked = $invoice->setBillDate($dbh, $billDT, $user, $notes);


        if ($wrked) {
            return array(
                'success' => 'Invoice number ' . $invNum . ' updated.',
                'elemt' => $element,
                'strDate' => (is_null($billDT) ? '' : $billDT->format('M j, Y')),
                'notes' => $invoice->getNotes(),
                'notesElemt' => $notesElement
            );
        }

        return array('error' => 'Set invoice billing date Failed.');
    }


}