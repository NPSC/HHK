<?php

namespace HHK\Payment;

use HHK\HTMLControls\{HTMLContainer, HTMLInput};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\SysConst\{InvoiceStatus, PaymentStatusCode, PaymentMethod};

/**
 * PostPayment.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of PostPayment
 * Set up the post-payment actions for payment reports
 *
 * @author Eric
 */
class PostPayment {

    public static function actionButton(AbstractPaymentGateway $gateway, array &$p, $invoiceStatusCode, &$payTypeTotals, &$stat, &$amt, &$attr) {

        $actionButtonArray = array('type' => 'button', 'style' => 'font-size:.8em', 'id' => 'btnvr' . $p['idPayment'], 'data-pid' => $p['idPayment'], 'data-amt' => $amt);
        $voidContent = '';

        switch ($p['Payment_Status']) {

            case PaymentStatusCode::VoidSale:
                $stat = 'Void Sale';
                $attr['style'] .= 'color:red;';

                break;

            case PaymentStatusCode::Reverse:
                $stat = 'Reversed';
                $attr['style'] .= 'color:red;';

                break;

            case PaymentStatusCode::Retrn:
                $stat = 'Returned';
                $attr['style'] .= 'color:red;';

                if ($p['idPayment_Method'] == PaymentMethod::Charge && date('Y-m-d', strtotime($p['Last_Updated'])) == date('Y-m-d') && $gateway->hasVoidReturn()) {
                    // Void return
                    $actionButtonArray['class'] = 'hhk-voidRefundPmt';
                    $voidContent .= HTMLInput::generateMarkup('Void-Return', $actionButtonArray);
                } else if ($p['idPayment_Method'] != PaymentMethod::Charge || $gateway->hasUndoReturnPmt()) {

                    // Clawback
                    // Check the invoice status
                    if ($invoiceStatusCode == InvoiceStatus::Unpaid) {
                        $actionButtonArray['class'] = 'hhk-undoReturnPmt';
                        $voidContent .= HTMLInput::generateMarkup('Undo Return', $actionButtonArray);
                    } else {
                        $voidContent .= HTMLContainer::generateMarkup('span', 'Can\'t Undo', array('style' => 'font-size:.8em;color:#333;'));
                    }
                }

                break;

            case PaymentStatusCode::VoidReturn:
                
                if ($p['Is_Refund'] > 0) {
                    // Refund payment is returned
                    $stat = "Refund Voided";
                    $p['Payment_Status'] = PaymentStatusCode::VoidReturn;
                    $amt = 0 - $amt;
                }                
                break;

            case PaymentStatusCode::Paid:

                if ($p['Is_Refund'] > 0) {
                    // Refund payment
                    $stat = 'Refund';
                    $p['Payment_Status'] = PaymentStatusCode::Retrn;
                    $amt = 0 - $amt;

                    if ($p['idPayment_Method'] == PaymentMethod::Charge && date('Y-m-d', strtotime($p['Payment_Date'])) == date('Y-m-d') && $gateway->hasVoidReturn()) {
                        $actionButtonArray['class'] = 'hhk-voidRefundPmt';
                        $voidContent .= HTMLInput::generateMarkup('Void Refund', $actionButtonArray);
                    } else if ($p['idPayment_Method'] != PaymentMethod::Charge || $gateway->hasUndoReturnAmt()) {
                        $actionButtonArray['class'] = 'hhk-undoReturnPmt';
                        $voidContent .= HTMLInput::generateMarkup('Undo Refund', $actionButtonArray);
                    }
                } else {
                    // Regular payment
                    $payTypeTotals[$p['idPayment_Method']]['amount'] += $amt;
                    $stat = HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-check', 'style' => 'float:left;', 'title' => 'Paid'));

                    if ($amt != 0) {

                        if ($p['idPayment_Method'] == PaymentMethod::Charge && date('Y-m-d', strtotime($p['Payment_Date'])) == date('Y-m-d')) {
                            $actionButtonArray['class'] = 'hhk-voidPmt';
                            $voidContent .= HTMLInput::generateMarkup('Void', $actionButtonArray);
                        } else {
                            $actionButtonArray['class'] = 'hhk-returnPmt';
                            $voidContent .= HTMLInput::generateMarkup('Return', $actionButtonArray);
                        }
                    }
                }

                break;

            case PaymentStatusCode::Declined:

                $stat = 'Declined';
                $attr['style'] .= 'color:gray;';

                break;

            default:
                $stat = 'Undefined: ' . $p['Payment_Status'];
        }

        //add receipt icon to action column
        $voidContent .= HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-script pmtRecpt', 'id' => 'pmticon' . $p['idPayment'], 'data-pid' => $p['idPayment'], 'style' => 'cursor:pointer; margin-left: auto', 'title' => 'View Payment Receipt'));
        return HTMLContainer::generateMarkup('div', $voidContent, ['style' => 'display:flex; justify-content:space-between; flex-wrap:nowrap;']);
    }

}
