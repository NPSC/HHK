<?php
namespace HHK\Purchase;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\House\Registration;
use HHK\Payment\CreditToken;
use HHK\House\Reservation\Reservation_1;
use HHK\Payment\Invoice\Invoice;
use HHK\Purchase\ValueAddedTax;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\Payment\PaymentManager\PaymentManagerPayment;
use HHK\SysConst\{ExcessPay, GLTableNames, InvoiceStatus, ItemId, ItemPriceCode, PayType, ReturnIndex};
use HHK\sec\Labels;
use HHK\sec\Session;

/**
 * PaymentChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
  */


/**
 * Description of PaymentChooser
 *
 * @author Eric
 */
class PaymentChooser {

    /**
     *
     * @param \PDO $dbh
     * @param string $rtnIndex
     * @return PaymentManagerPayment|null
     */
    public static function readPostedPayment(\PDO $dbh, $rtnIndex = ReturnIndex::ReturnIndex) {

        $args = [
            'txtInvId' => FILTER_SANITIZE_NUMBER_INT,
            'rbUseCard' . $rtnIndex => FILTER_SANITIZE_NUMBER_INT,
            'rbUseCard' => FILTER_SANITIZE_NUMBER_INT,
            'PayTypeSel' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'rtnTypeSel' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'paymentDate' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtvdNewCardName' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtInvNotes' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtCheckNum' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtRtnCheckNum' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtTransferAcct' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtRtnTransferAcct' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selChargeType' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selRtnChargeType' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtPayNotes' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtRtnNotes' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtChargeAcct' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'txtRtnChargeAcct' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selexcpay' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'selccgw' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,

            'txtCashTendered' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'visitFeeAmt' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'keyDepAmt' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'heldAmount' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'DepRefundAmount' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'feesPayment' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'feesTax' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'feesCharges' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'guestCredit' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'HsDiscAmount' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'totalCharges' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'txtOverPayAmt' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'txtRtnAmount' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'totalPayment' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION
            ],
            'invPayAmt' => [
                'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
                'flags' => FILTER_FLAG_ALLOW_FRACTION | FILTER_REQUIRE_ARRAY
            ],

        ];

        $inputs = filter_input_array(INPUT_POST, $args);

        // Payment Type
        if (isset($inputs['PayTypeSel'])) {
            $payType = $inputs['PayTypeSel'];
        } else if (isset($inputs['rtnTypeSel'])) {
            $payType = $inputs['rtnTypeSel'];
        } else {
            return NULL;
        }


        $pmp = new PaymentManagerPayment($payType);

        // Return-payment type
        if (isset($inputs['rtnTypeSel'])) {
            $pmp->setRtnPayType($inputs['rtnTypeSel']);
        }

        // Payment Date
        if (isset($inputs['paymentDate']) && $inputs['paymentDate'] != '') {
            $pmp->setPayDate($inputs['paymentDate']);
        } else {
            $pmp->setPayDate(date('Y-m-d H:i:s'));
        }

        // Credit token
        if (isset($inputs['rbUseCard'])) {
            $pmp->setIdToken(intval($inputs['rbUseCard'], 10));
        }

        if (isset($inputs['rbUseCard' . $rtnIndex])) {
        	$pmp->setRtnIdToken(intval($inputs['rbUseCard' . $rtnIndex], 10));
        }

        // Merchant
        if (isset($inputs['selccgw'])) {
            $pmp->setMerchant($inputs['selccgw']);
        }

        // Manual Key check box
        if (isset($_POST['btnvrKeyNumber'])) {
            $pmp->setManualKeyEntry(TRUE);
        } else {
            $pmp->setManualKeyEntry(FALSE);
        }

        // Manual cardholder name
        if (isset($inputs['txtvdNewCardName'])) {
            $pmp->setCardHolderName(strtoupper($inputs['txtvdNewCardName']));
        }

        // Use new CC
        if (isset($_POST['cbNewCard'])) {
            $pmp->setNewCardOnFile(TRUE);
        } else {
            $pmp->setNewCardOnFile(FALSE);
        }

        // Invoice payor
        if (isset($inputs['txtInvId'])) {
            $pmp->setIdInvoicePayor(intval($inputs['txtInvId'], 10));
        }

        // Invoice notes
        if (isset($inputs['txtInvNotes'])) {
            $pmp->setInvoiceNotes($inputs['txtInvNotes']);
        }

        // Check number
        if (isset($inputs['txtCheckNum'])) {
            $pmp->setCheckNumber($inputs['txtCheckNum']);
        }

        // Return Check number
        if (isset($inputs['txtRtnCheckNum'])) {
            $pmp->setRtnCheckNumber($inputs['txtRtnCheckNum']);
        }

        // Transfer Account
        if (isset($inputs['txtTransferAcct'])) {
            $pmp->setTransferAcct($inputs['txtTransferAcct']);
        }

        // Return transfer acct
        if (isset($inputs['txtRtnTransferAcct'])) {
            $pmp->setRtnTransferAcct($inputs['txtRtnTransferAcct']);
        }

        // Charge Card - External Swipe
        if (isset($inputs['selChargeType'])) {
            $pmp->setChargeCard($inputs['selChargeType']);
        }
        if (isset($inputs['selRtnChargeType'])) {
            $pmp->setRtnChargeCard($inputs['selRtnChargeType']);
        }

        // Payment Notes.
        if (isset($inputs['txtPayNotes'])) {


            if ($inputs['txtPayNotes'] != '') {

                $pmp->setPayNotes($inputs['txtPayNotes']);

            } else {

                // Return Payment Notes.
                if (isset($inputs['txtRtnNotes'])) {
                    $pmp->setPayNotes($inputs['txtRtnNotes']);
                }
            }
        }

        // Charge Acct - External Swipe
        if (isset($inputs['txtChargeAcct'])) {
            $pmp->setChargeAcct($inputs['txtChargeAcct']);
        }
        if (isset($inputs['txtRtnChargeAcct'])) {
            $pmp->setRtnChargeAcct($inputs['txtRtnChargeAcct']);
        }

        // cash tendered
        if (isset($inputs['txtCashTendered'])) {
            $pmp->setCashTendered(floatval($inputs['txtCashTendered']));
        }

        //  Visit fees
        if (isset($_POST['visitFeeCb']) && isset($inputs['visitFeeAmt'])) {
            $pmp->setVisitFeePayment(floatval($inputs['visitFeeAmt']));
        }

        // Room/Key deposit
        if (isset($_POST["keyDepRx"]) && isset($inputs["keyDepAmt"])) {
            $pmp->setKeyDepositPayment(floatval($inputs["keyDepAmt"]));
        }

        // Retained Amount payment
        if (isset($_POST["cbHeld"]) && isset($inputs["heldAmount"])) {
            $pmp->setRetainedAmtPayment(floatval($inputs["heldAmount"]));
        }

        // Deposit Refund.
        if (isset($inputs["DepRefundAmount"])) {
            $pmp->setDepositRefundAmt(floatval($inputs["DepRefundAmount"]));
        }

        // Room fees.
        if (isset($inputs["feesPayment"])) {
            $pmp->setRatePayment(floatval($inputs["feesPayment"]));
        }

        // Room fee taxes.
        if (isset($inputs["feesTax"])) {
            $pmp->setRateTax(floatval($inputs["feesTax"]));
        }

        // Total Room Charge.
        if (isset($inputs["feesCharges"])) {
            $pmp->setTotalRoomChg(floatval($inputs["feesCharges"]));
        }

        // Guest Credit
        if (isset($inputs['guestCredit'])) {
            $pmp->setGuestCredit(floatval($inputs['guestCredit']));
        }

        // Reimburse Taxes.
        if (isset($_POST["cbReimburseVAT"])) {
            $pmp->setReimburseTaxCb(TRUE);
        }   else {
            $pmp->setReimburseTaxCb(FALSE);
        }

        // Total Charges.
        if (isset($inputs["totalCharges"])) {
            $pmp->setTotalCharges(floatval($inputs["totalCharges"]));
        }

        // House waive
        if (isset($_POST['houseWaiveCb'])) {
            $pmp->setFinalPaymentFlag(TRUE);
        } else {
            $pmp->setFinalPaymentFlag(FALSE);
        }

        // House Discount amount
        if (isset($_POST['houseWaiveCb']) && isset($inputs['HsDiscAmount'])) {
            $pmp->setHouseDiscPayment(floatval($inputs['HsDiscAmount']));
        }

        // OverPay amount
        if (isset($inputs['txtOverPayAmt'])) {
            $pmp->setOverPayment(floatval($inputs['txtOverPayAmt']));
        }

        // Refund amount
        if (isset($inputs['txtRtnAmount'])) {
            $pmp->setRefundAmount(floatval($inputs['txtRtnAmount']));
        }

        // Total payment.
        if (isset($inputs["totalPayment"])) {
            $pmp->setTotalPayment(floatval($inputs["totalPayment"]));
        }

        // unpaid invoices
        if (isset($inputs['invPayAmt'])) {

            foreach ($inputs['invPayAmt'] as $key => $amt) {

                $num = filter_var($key, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if (isset($_POST['unpaidCb'][$num])) {
                    $pmp->addInvoiceByNumber($dbh, $num, $amt);
                }
            }
        }

        // balance with
        if (isset($inputs['selexcpay'])) {
            $pmp->setBalWith($inputs['selexcpay']);
        }

        // Reimburse Taxes
        if (isset($_POST['cbReimburseVAT'])) {
            $pmp->setReimburseTaxCb(TRUE);
        } else {
            $pmp->setReimburseTaxCb(FALSE);
        }

        return $pmp;
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $idGuest
     * @param int $idRegistration
     * @param VisitCharges $visitCharge
     * @param AbstractPaymentGateway $paymentGateway
     * @param string $defaultPayType
     * @param bool $useDeposit
     * @param boolean $showFinalPayment
     * @param boolean $payVFeeFirst
     * @param number $prefTokenId
     * @param boolean $useVisitFee
     * @return string
     */
    public static function createMarkup(
        \PDO $dbh
        , $idGuest
        , $idResv
        , $idRegistration
        , VisitCharges $visitCharge
        , AbstractPaymentGateway $paymentGateway
        , $defaultPayType
        , $showFinalPayment = FALSE
        , $prefTokenId = 0) {

        $uS = Session::getInstance();

        /**
         * if $us->DefaultPayType is empty, the pay controls for selecting
         * payment type will be blank, forcing the user to set it every payment time.
         */
        if ($defaultPayType == '') {
            $defaultPayType = $uS->DefaultPayType;
        }

        /**
         * ItemPriceCode::None short-circuits all amount calculations to 0 and may not show Price & Pay UI on Edit Visit dialog box
         */
        $showRoomFees = TRUE;
        if ($uS->RoomPriceModel == ItemPriceCode::None) {
            $showRoomFees = FALSE;
        }

        // Get labels
        $labels = Labels::getLabels();

        // Get taxed items
        $vat = new ValueAddedTax($dbh);

        if($uS->VisitFee && ($visitCharge->getNightsStayed() > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == '' || $uS->VisitFeeDelayDays == 0)){
            $useVisitFee = TRUE;
        } else {
            $useVisitFee = FALSE;
        }

        // Resrvation Check-in
        if ($uS->AcceptResvPaymt && $idResv > 0) {
            $heldAmount = Reservation_1::getPrePayment($dbh, $idResv);
            $chkingIn = TRUE;
        } else {
            $heldAmount = Registration::loadLodgingBalance($dbh, $idRegistration);
            $otherPrepayments = Registration::loadPrepayments($dbh, $idRegistration);
            // Remove other reservations' prepayments.
            $heldAmount = max(0, ($heldAmount - $otherPrepayments));
            $chkingIn = FALSE;
        }

        $mkup = HTMLContainer::generateMarkup('div',
            self::createPaymentMarkup(
                $showRoomFees,
                $uS->KeyDeposit,
                $visitCharge,
                $useVisitFee,
                $heldAmount,
                $uS->PayVFeeFirst,
                $showFinalPayment,
                Invoice::load1stPartyUnpaidInvoices($dbh, $visitCharge->getIdVisit(), $uS->returnId),
                $labels,
                $vat,
                $visitCharge->getIdVisit(),
                readGenLookupsPDO($dbh, 'ExcessPays'),
                $uS->VisitExcessPaid,
                $uS->UseHouseWaive,
                $chkingIn
            )
            , array('id'=>'divPmtMkup', 'style'=>'float:left;margin-left:.3em;margin-right:.3em;')
        );

        $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');

        if ($uS->ShowTxPayType == FALSE) {
            unset($payTypes[PayType::Transfer]);
        }

        // Collect panels for payments
        $panelMkup = self::showPaySelection($dbh,
                $defaultPayType,
                $payTypes,
                $labels,
                $paymentGateway,
                $idGuest, $idRegistration, $prefTokenId);


        if (isset($uS->nameLookups[GLTableNames::PayType][PayType::Invoice])) {
            $panelMkup .= self::invoiceBlock();
        }

        if ($panelMkup != '') {
            $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));
        }

        // Collect panels for Returns
        unset($payTypes[PayType::Invoice]);

        $rtnMkup = HTMLContainer::generateMarkup('div', self::showReturnSelection($dbh,
                $defaultPayType,
                $payTypes,
                $paymentGateway,
        		$idGuest, $idRegistration, $prefTokenId),
                array('id'=>'divReturnPay', 'style'=>'float:left; display:none;'));

        if ($rtnMkup != '') {
        	$mkup .= HTMLContainer::generateMarkup('div', $rtnMkup, array('style'=>'float:left;'));
        }

        return HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Paying Today', array('style'=>'font-weight:bold;'))
                . $mkup, array('id'=>'hhk-PayToday', 'class'=>'hhk-panel hhk-flex', 'style'=>'min-width: max-content'));

    }

    /**
     *
     * @param \PDO $dbh
     * @param int $idGuest
     * @param int $idResv
     * @param int $idRegistration
     * @param VisitCharges $visitCharge
     * @param AbstractPaymentGateway $paymentGateway
     * @param string $defaultPayType
     * @param number $prefTokenId
     * @return string
     */
    public static function createPrePaymentMarkup(\PDO $dbh, $idGuest, $idResv, $idRegistration, VisitCharges $visitCharge, AbstractPaymentGateway $paymentGateway, $defaultPayType, $heldAmount, $prefTokenId) {

        $uS = Session::getInstance();

        if ($defaultPayType == '') {
            $defaultPayType = $uS->DefaultPayType;
        }

        // Get labels
        $labels = Labels::getLabels();

        $feesTbl = new HTMLTable();

        // Get any Unpaid invoices
        $trs = self::createUnpaidInvoiceMarkup(Invoice::loadPrePayUnpaidInvoices($dbh, $idResv, $uS->returnId));
        // Add them to the table
        foreach ($trs as $t) {
            $feesTbl->addBodyTr($t);
        }


        if ($heldAmount > 0) {
            // Show only the prepayment amount already entered.
            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Pre-Payment Balance:', array('class'=>'tdlabel', 'title'=>'Money on Account (MOA)'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span', ($heldAmount > 0 ? '$' . number_format($heldAmount, 2) : ''), array('id'=>'spnHeldAmt'))
                    .HTMLInput::generateMarkup('', array('id'=>'cbHeld', 'type'=>'checkbox', 'style'=>'display:none;', 'data-prepay'=>'1', 'data-amt'=> number_format($heldAmount, 2, '.',''))))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'heldAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;')), array('style'=>'text-align:right;')));
        }

        // Show the prepayment input box.
        $feesTbl->addBodyTr(HTMLTable::makeTd('Pre-Pay Room Fees:', array('class'=>'tdlabel'))
            .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', HTMLInput::generateMarkup('', array('id'=>'daystoPay', 'size'=>'6', 'data-vid'=>0, 'placeholder'=>'# days', 'style'=>'text-align: center;')), ($uS->HideRoomFeeCalc ? ['class'=>"d-none"] : ['style'=>'text-align:center;'])), ['style'=>'text-align: center;min-width: 62px;'])
            .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'feesPayment', 'size'=>'8', 'class'=>'hhk-feeskeys','style'=>'text-align:right;')), array('style'=>'text-align:right;', 'class'=>'hhk-feesPay'))
            , array('class'=>'hhk-RoomFees'));

        // Amount to pay
        $feesTbl->addBodyTr(
            HTMLTable::makeTh(HTMLContainer::generateMarkup('span', 'Payment Amount:', array('id'=>'spnPayTitle')), array('colspan'=>'2', 'class'=>'tdlabel'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'totalPayment', 'size'=>'8', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;font-weight:bold;', 'readonly'=>'readonly'))
                , array('style'=>'text-align:right;border-top:2px solid #2E99DD;border-bottom:2px solid #2E99DD;')));


        // Payment Date
        $feesTbl->addBodyTr(HTMLTable::makeTd('Pre-Pay Date:', array('colspan'=>'2', 'class'=>'tdlabel'))
            .HTMLTable::makeTd(HTMLInput::generateMarkup(date('M j, Y'), array('name'=>'paymentDate', 'readonly'=>'readonly', 'class'=>'hhk-feeskeys ckdate')))
            , array('style'=>'display:none;', 'class'=>'hhk-minPayment'));

        $excessPays = readGenLookupsPDO($dbh, 'ExcessPays');

        unset($excessPays[ExcessPay::Hold]);
        unset($excessPays[ExcessPay::Ignore]);
        
        if($uS->UseRebook){
            $excessPays[ExcessPay::MoveToResv] = array(ExcessPay::MoveToResv, 'Next Reservation');
        }

        // Extra payment & distribution Selector
        if (count($excessPays) > 0) {

            $feesTbl->addBodyTr(HTMLTable::makeTh('Overpayment Amount:', array('class'=>'tdlabel', 'colspan'=>'2'))
                .HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array('name'=>'txtOverPayAmt', 'style'=>'border:none;text-align:right;font-weight:bold;', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'size'=>'8'))
                    , array('style'=>'text-align:right;'))
                , array('class'=>'hhk-Overpayment'));

            $sattrs = array('name'=>'selexcpay', 'style'=>'margin-left:3px; width: 100%;', 'class'=>'hhk-feeskeys');

            $feesTbl->addBodyTr(HTMLTable::makeTd('Apply to:', array('class'=>'tdlabel', 'colspan'=>'2'))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($excessPays, '', TRUE), $sattrs))
                , array('class'=>'hhk-Overpayment'));

        }

        // Invoice Notes
        $feesTbl->addBodyTr(
            HTMLTable::makeTh('Invoice Notes (Public)', array('colspan'=>'3', 'style'=>'text-align:left;'))
            , array('style'=>'display:none;', 'class'=>'hhk-minPayment'));

        $feesTbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'txtInvNotes', 'rows'=>1, 'style'=>'width:100%;', 'class'=>'hhk-feeskeys')), array('colspan'=>'3'))
            , array('style'=>'display:none;', 'class'=>'hhk-minPayment'));

        // Error message
        $mess = HTMLContainer::generateMarkup('div','', array('id'=>'payChooserMsg', 'style'=>'clear:left;color:red;margin:5px;display:none'));

        $mkup =  $mess . $feesTbl->generateMarkup(array('id'=>'payTodayTbl', 'style'=>'margin-right:7px;float:left;'));

        $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');

        unset($payTypes[PayType::Invoice]);

        if ($uS->ShowTxPayType == FALSE) {
            unset($payTypes[PayType::Transfer]);
        }

        // Collect panels for payments
        $panelMkup = self::showPaySelection($dbh,
            $defaultPayType,
            $payTypes,
            $labels,
            $paymentGateway,
            $idGuest, $idRegistration, $prefTokenId);

        $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));

        // Collect panels for Returns
        $rtnMkup = HTMLContainer::generateMarkup('div', self::showReturnSelection($dbh,
            $defaultPayType,
            $payTypes,
            $paymentGateway,
            $idGuest, $idRegistration, $prefTokenId),
            array('id'=>'divReturnPay', 'style'=>'float:left; display:none;'));

        $mkup .= HTMLContainer::generateMarkup('div', $rtnMkup, array('style'=>'float:left;'));

        return HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Pre-Payments', array('style'=>'font-weight:bold;'))
            . $mkup, array('id'=>'hhk-PayToday', 'class'=>'hhk-panel', 'style'=>'float:left;'));

    }

    /**
     * Summary of createUnpaidInvoiceMarkup
     * @param array $unpaidInvoices
     * @return array<string>
     */
    protected static function createUnpaidInvoiceMarkup($unpaidInvoices) {

        $trs = array();

        foreach ($unpaidInvoices as $i) {

            $trashIcon = '';

            $invNumber = $i['Invoice_Number'];
            $invAttr = ['href'=>'ShowInvoice.php?invnum='.$i['Invoice_Number'], 'target'=>'_blank', 'style'=>'float:left;'];

            // Additional information
            $addnl = '';
            if (isset($i['Guest Name']) && $i['Guest Name'] != '') {
                $addnl = HTMLContainer::generateMarkup('span', $i['Guest Name'], ['style'=>'margin: 0 5px;']);
            }

            // Partially paid, or can we trash it.
            if ($i['Amount'] - $i['Balance'] != 0) {
                $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
                $invAttr['title'] = 'Partially Paid';
            } else {
                $trashIcon = HTMLContainer::generateMarkup('span','', ['class'=>'ui-icon ui-icon-trash invAction', 'id'=>'invdel'.$i['idInvoice'], 'data-iid'=>$i['idInvoice'], 'data-inb' => $i['Invoice_Number'], 'data-payor' => (isset($i['Payor']) ? $i['Payor'] : ''), 'data-stat'=>'del', 'style'=>'float:right;cursor:pointer;', 'title'=>'Delete']);
            }

            $unpaid = HTMLTable::makeTd(HTMLContainer::generateMarkup('span',
                    HTMLContainer::generateMarkup('a', 'Invoice ' . $invNumber, $invAttr)
                    . (isset($i['doNotView']) ? '' : HTMLContainer::generateMarkup('span','', ['class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$i['idInvoice'], 'data-iid'=>$i['idInvoice'], 'data-stat'=>'view', 'style'=>'float:left;cursor:pointer;', 'title'=>'View Items']))
                    . $trashIcon
                    , ["style"=>'white-space:nowrap'])
                    .$addnl, ['class'=>'tdlabel']);


            $unpaid .= HTMLTable::makeTd(
                    HTMLContainer::generateMarkup('label', 'Pay', ['for'=>$i['Invoice_Number'].'unpaidCb', 'style'=>'margin-left:5px;margin-right:3px;'])
                    .HTMLInput::generateMarkup('',
                        ['id'=> $i['Invoice_Number']. 'unpaidCb', 'name'=>'unpaidCb['.$i['Invoice_Number'].']', 'type'=>'checkbox', 'data-invnum'=>$i['Invoice_Number'], 'data-invamt'=>$i['Balance'], 'class'=>'hhk-feeskeys hhk-payInvCb', 'style'=>'margin-right:.4em;', 'title'=>'Check to pay this invoice.'])
                    .HTMLContainer::generateMarkup('span', '($'. number_format($i['Balance'], 2) . ')', ['style'=>'font-style: italic;']))
                .HTMLTable::makeTd('$'.
                    HTMLInput::generateMarkup('', ['id' => $i['Invoice_Number'] . 'invPayAmt', 'name'=>'invPayAmt['.$i['Invoice_Number'].']', 'size'=>'8', 'class'=>'hhk-feeskeys hhk-payInvAmt','style'=>'text-align:right;']), ['style'=>'text-align:right;']);

            $trs[] = $unpaid;
        }

        return $trs;
    }


    /**
     * Summary of createHousePaymentMarkup
     * @param mixed $discounts
     * @param mixed $addnls
     * @param int $idVisit
     * @param mixed $itemTaxSums
     * @param mixed $arrivalDate
     * @return string
     */
    public static function createHousePaymentMarkup(array $discounts, array $addnls, $idVisit, $itemTaxSums, $arrivalDate = '') {

        if (count($discounts) < 1 && count($addnls) < 1) {
            return '';
        }

        $buttons = '';
        $select = '';

        if (count($discounts) > 0) {

            $buttons .= HTMLContainer::generateMarkup('label', 'Discount', array('for'=>'cbAdjustPmt1'))
            . HTMLInput::generateMarkup('', array('type'=>'radio', 'name'=>'cbAdjustPmt', 'id'=>'cbAdjustPmt1', 'data-sho'=>'houseDisc', 'data-hid'=>'addnlChg', 'data-item'=>ItemId::Discount));

            $select .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($discounts), '', TRUE), array('name'=>'selHouseDisc', 'class'=>'houseDisc', 'data-amts'=>'disc', "style"=>"width:100%"));

        }

        if (count($addnls) > 0) {

            $buttons .= HTMLContainer::generateMarkup('label', 'Additional Charge', array('for'=>'cbAdjustPmt2'))
                . HTMLInput::generateMarkup('', array('type'=>'radio', 'name'=>'cbAdjustPmt', 'id'=>'cbAdjustPmt2', 'data-hid'=>'houseDisc', 'data-sho'=>'addnlChg', 'data-item'=>ItemId::AddnlCharge));

            $select .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($addnls), '', TRUE), array('name'=>'selAddnlChg', 'class'=>'addnlChg', 'data-amts'=>'addnl', "style"=>"width:100%"));

        }

        $feesTbl = new HTMLTable();

        $feesTbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $buttons, array('id'=>'cbAdjustType')), array('colspan'=>2)));


        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Select', array('class'=>'tdlabel')) . HTMLTable::makeTd($select));

        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Amount:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'housePayment', 'size'=>'9', 'data-vid'=>$idVisit, 'style'=>'text-align:right;'))));

        if (isset($itemTaxSums[ItemId::AddnlCharge])) {

            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Tax ('. TaxedItem::suppressTrailingZeros($itemTaxSums[ItemId::AddnlCharge]*100).'):', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'houseTax', 'size'=>'9', 'data-tax'=>$itemTaxSums[ItemId::AddnlCharge], 'readonly'=>'readonly', 'style'=>'text-align:right;')))
                    , array('class'=>'addnlChg', 'style'=>'display:none;'));

            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Total:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'totalHousePayment', 'size'=>'9', 'readonly'=>'readonly', 'style'=>'text-align:right;')))
                    , array('class'=>'addnlChg', 'style'=>'display:none;'));
        }

        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Date:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'housePaymentDate', 'class'=>'ckdate', 'data-vid'=>$idVisit, "style"=>"width:100%"))));

        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Notes:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'housePaymentNote', 'rows'=>'2', 'data-vid'=>$idVisit, "style"=>"width:100%"))));

        $javaScript = '<script type="text/javascript">'
                . '$("#housePaymentDate").datepicker({'
                . 'yearRange: "-1:+01",
changeMonth: true,
changeYear: true,
autoSize: true,
numberOfMonths: 1,
dateFormat: "M d, yy" ';

        if ($arrivalDate != '') {
            $javaScript .= ',minDate: new Date("' . $arrivalDate . '")';
        }

        $javaScript .= ' }); $("#housePaymentDate").datepicker("setDate", new Date());</script>';

        return $feesTbl->generateMarkup(array('style'=>'margin-bottom:7px;width:100%')) . $javaScript;

    }

    /**
     * Summary of createPayInvMarkup
     * Called from register page Unpaid invoices tab.
     * @param \PDO $dbh
     * @param int $id
     * @param int $invoiceId
     * @param int $prefTokenId
     * @return string
     */
    public static function createPayInvMarkup(\PDO $dbh, $id, $invoiceId, $prefTokenId = 0) {

        $uS = Session::getInstance();

        $idInvoice = intval($invoiceId, 10);

        if ($idInvoice > 0) {

            // Collect any unpaid invoices
            $stmt = $dbh->query("SELECT
    i.idInvoice,
    i.`Invoice_Number`,
    i.`Balance`,
    i.`Amount`,
    n.Name_Full,
    IFNULL(n.Name_Full, '') as `Payor`,
    n.Company,
    ng.Name_Last AS `Guest Name`,
    v.idVisit,
    v.Span
FROM
    `invoice` i
        LEFT JOIN
    name n ON i.Sold_To_Id = n.idName
        LEFT JOIN
    visit v ON i.Order_Number = v.idVisit
        AND i.Suborder_Number = v.Span
        LEFT JOIN
    name ng ON v.idPrimaryGuest = ng.idName
WHERE
    i.idInvoice = $idInvoice AND i.Status = '" . InvoiceStatus::Unpaid . "'
        AND i.Deleted = 0
ORDER BY v.idVisit , v.Span;");

            $unpaidInvoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($unpaidInvoices) > 0) {

                if ($unpaidInvoices[0]['Company'] != '' && $unpaidInvoices[0]['Name_Full'] != '') {
                    $name = $unpaidInvoices[0]['Company'] . ',  c/o ' . $unpaidInvoices[0]['Name_Full'];
                } else if ($unpaidInvoices[0]['Company'] != '' && $unpaidInvoices[0]['Name_Full'] == '') {
                    $name = $unpaidInvoices[0]['Company'];
                } else {
                    $name = $unpaidInvoices[0]['Name_Full'];
                }

                // Turn off view icon.
                $unpaidInvoices[0]['doNotView'] = 1;

                $labels = Labels::getLabels();

                $mkup = HTMLContainer::generateMarkup('div',
                        self::createPaymentMarkup(
                                FALSE,
                                FALSE,
                                NULL,
                                FALSE,
                                0,
                                FALSE,
                                FALSE,
                                $unpaidInvoices,
                                $labels,
                                NULL)
                        , array('id'=>'divPmtMkup', 'style'=>'float:left;margin-left:.3em;margin-right:.3em;')
                );

                $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');
                unset($payTypes[PayType::Invoice]);


                $panelMkup = self::showPaySelection(
                        $dbh, $uS->DefaultPayType,
                        $payTypes,
                        $labels,
                        AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, ''),
                        $id, 0, $prefTokenId);

                $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));

                return HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Paying Today: ' . $name, array('style'=>'font-weight:bold;'))
                        . $mkup, array('id'=>'hhk-PayToday', 'class'=>'hhk-panel', 'style'=>'float:left;'));
            }
        }

        return HTMLContainer::generateMarkup('h3', "No unpaid invoices found.");
    }

    /**
     * Summary of createPaymentMarkup
     * @param bool $showRoomFees
     * @param bool $useKeyDeposit
     * @param VisitCharges|null $visitCharge
     * @param bool $useVisitFee
     * @param float|int $heldAmount
     * @param bool $payVFeeFirst
     * @param bool $showFinalPayment
     * @param array $unpaidInvoices
     * @param Labels $labels
     * @param ValueAddedTax|null $vat
     * @param int $idVisit
     * @param mixed $excessPays
     * @param string $defaultExcessPays
     * @param bool $useHouseWaive
     * @param bool $chkingIn
     * @return string
     */
    protected static function createPaymentMarkup($showRoomFees, $useKeyDeposit, $visitCharge, $useVisitFee, $heldAmount, $payVFeeFirst,
            $showFinalPayment, array $unpaidInvoices, $labels, $vat,  $idVisit = 0, $excessPays = array(), $defaultExcessPays = ExcessPay::Ignore, $useHouseWaive = FALSE, $chkingIn = FALSE) {

        $feesTbl = new HTMLTable();

        // Get any Unpaid invoices
        $trs = self::createUnpaidInvoiceMarkup($unpaidInvoices);
        // Add them to the table
        foreach ($trs as $t) {
            $feesTbl->addBodyTr($t);
        }

        // Find any taxed items.  If the return is an empty array, then no taxes.
        if($vat instanceof ValueAddedTax){
            $taxedItems = $vat->getCurrentTaxingItems($idVisit, $visitCharge->getNightsStayed(), ItemId::Lodging);
        }else{
            $taxedItems = array();
        }


        if ($useKeyDeposit && is_null($visitCharge) === FALSE) {

            $depositLabel = $labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit');

            $keyDeposit = HTMLTable::makeTd($depositLabel . ':', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                     HTMLContainer::generateMarkup('label', "Pay", ['for'=>'keyDepRx', 'style'=>'margin-left:5px;margin-right:3px;'])
                    .HTMLInput::generateMarkup('', ['name'=>'keyDepRx', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.4em;', 'title'=>'Check if ' . $depositLabel . ' Received.'])
                    .HTMLContainer::generateMarkup('span', ($visitCharge->getDepositCharged() > 0 ? '($' . $visitCharge->getDepositCharged() . ')' : ''), ['id'=>'spnDepAmt'])
                    .HTMLInput::generateMarkup($visitCharge->getDepositCharged(), ['id'=>'hdnKeyDepAmt', 'type'=>'hidden']))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'keyDepAmt', 'size'=>'8', 'style'=>'border:none;text-align:right;', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'title'=>$depositLabel . ' Amount'])
                    , ['style'=>'text-align:right;']);

            $attrs = ['class'=>'hhk-kdrow', 'style'=>'display:none;'];

            if ($visitCharge->getDepositCharged() > 0) {
                unset($attrs['style']);
            }

            $feesTbl->addBodyTr($keyDeposit, $attrs);

        }


        if ($useVisitFee && is_null($visitCharge) === FALSE) {

            $vFeeTitle = $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee');
            $visitFee = HTMLTable::makeTd($vFeeTitle . ':', array('class'=>'tdlabel'));
            $visitFeeAmt = $visitCharge->getVisitFeeCharged();
            $visitFeePaid = $visitCharge->getVisitFeesPaid() + $visitCharge->getVisitFeesPending();

            if ($visitFeeAmt == 0 || ($visitFeePaid > 0 && $visitFeePaid >= $visitFeeAmt)) {

                $visitFee = '';

            } else {

                $vfAttr = ['name'=>'visitFeeCb', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.4em;', 'title'=>'Check if '.$vFeeTitle.' was received.'];

                if ($payVFeeFirst) {
                    $vfAttr['checked'] = 'checked';
                }

                $visitFee .= HTMLTable::makeTd(HTMLContainer::generateMarkup('label', "Pay", ['for'=>'visitFeeCb', 'style'=>'margin-left:5px;margin-right:3px;'])
                    .HTMLInput::generateMarkup('', $vfAttr) . HTMLContainer::generateMarkup('span', ($visitFeeAmt > 0 ? '($' . $visitFeeAmt . ')' : ''), ['id'=>'spnvfeeAmt', 'data-amt'=>$visitFeeAmt]))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($visitFeeAmt, ['name'=>'visitFeeAmt', 'size'=>'8', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;', 'class'=>'hhk-feeskeys', 'title'=>$vFeeTitle.' amount']), ['style'=>'text-align:right;']);

                $attrs = array('class'=>'hhk-vfrow', 'style'=>'display:none;');
                if ($visitFeeAmt > 0) {
                    unset($attrs['style']);
                }
                $feesTbl->addBodyTr($visitFee, $attrs);
            }
        }

        if ($showFinalPayment && is_null($visitCharge) === FALSE) {

            // Any remaining guest credits
            $feesTbl->addBodyTr(
                HTMLTable::makeTd($labels->getString('PaymentChooser', 'Credit', 'Guest Credit').':', ['colspan'=>'2', 'class'=>'tdlabel'])
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'guestCredit', 'size'=>'8', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;', 'readonly'=>'readonly']), ['style'=>'text-align:right;'])
                , ['style'=>'display:none;', 'class'=>'hhk-GuestCredit']);

            // Deposit Return Amount
            if (($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()) > 0) {
                $feesTbl->addBodyTr(
                    HTMLTable::makeTd($labels->getString('PaymentChooser', 'rtnDeposit', 'Deposit Refund') . ':', ['class'=>'tdlabel'])
                    .HTMLTable::makeTd(
                            HTMLContainer::generateMarkup('label', "Apply", ['for'=>'cbDepRefundApply', 'style'=>'margin-left:5px;margin-right:3px;'])
                            .HTMLInput::generateMarkup('', ['name'=>'cbDepRefundApply', 'class'=>'hhk-feeskeys', 'checked'=>'checked', 'type'=>'checkbox', 'style'=>'margin-right:.4em;']))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'DepRefundAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;', 'data-amt'=> number_format(($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()), 2)])
                            , ['style'=>'text-align:right;']), ['class'=>'hhk-refundDeposit']);
            }
        }

        // MOA money on account - held amount.
        if ($heldAmount > 0) {

            if ($chkingIn === FALSE) {
                // Regular MOA
                $feesTbl->addBodyTr(
                    HTMLTable::makeTd('MOA Balance:', ['class'=>'tdlabel', 'title'=>'Money on Account (MOA)'])
                    . HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('label', "Apply", ['for'=>'cbHeld', 'style'=>'margin-left:5px;margin-right:3px;'])
                        .HTMLInput::generateMarkup('', ['name'=>'cbHeld', 'class'=>'hhk-feeskeys', 'type'=>'checkbox', 'style'=>'margin-right:.4em;', 'data-prepay'=>'0', 'data-amt'=> number_format($heldAmount, 2, '.',''), 'data-chkingin'=>0])
                        .HTMLContainer::generateMarkup('span', ($heldAmount > 0 ? '($' . number_format($heldAmount, 2) . ')' : ''), ['id'=>'spnHeldAmt']))
                    .HTMLTable::makeTd(
                        HTMLInput::generateMarkup('', ['name'=>'heldAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;'])
                        , ['style'=>'text-align:right;'])
                    );

            } else {
                // Reservation Pre-Pay
                $feesTbl->addBodyTr(
                    HTMLTable::makeTd('Pre-Payment:', ['class'=>'tdlabel', 'title'=>'Reservation Pre-Payment'])
                    . HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('label', "Apply", ['for'=>'cbHeld', 'style'=>'margin-left:5px;margin-right:3px;'])
                        .HTMLInput::generateMarkup('', ['name'=>'cbHeld', 'class'=>'hhk-feeskeys', 'type'=>'checkbox', 'checked'=>'checked', 'style'=>'margin-right:.4em;', 'data-prepay'=>'1', 'data-amt'=> number_format($heldAmount, 2, '.',''), 'data-chkingin'=>1])
                        .HTMLContainer::generateMarkup('span', ($heldAmount > 0 ? '($' . number_format($heldAmount, 2) . ')' : ''), ['id'=>'spnHeldAmt']))
                    . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'heldAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;'])
                        , ['style'=>'text-align:right;'])
                );

            }
        }

        // Reimburse VAT for timed out items.
        if (is_null($visitCharge) === FALSE && $visitCharge->getNightsStayed() > 0) {

            foreach($vat->getTimedoutTaxItems(ItemId::Lodging, $idVisit, $visitCharge->getNightsStayed()) as $t) {

                $reimburseTax = abs($visitCharge->getItemInvCharges($t->getIdTaxingItem()));

                if ($reimburseTax > 0) {
                    $feesTbl->addBodyTr(
                        HTMLTable::makeTd('Tax Reimbusement:', ['class'=>'tdlabel', 'title'=>'Reimbursed taxes'])
                        .HTMLTable::makeTd(
                                HTMLContainer::generateMarkup('label', "Apply", ['for'=>'cbReimburseVAT', 'style'=>'margin-left:5px;margin-right:3px;'])
                                .HTMLInput::generateMarkup('', ['name'=>'cbReimburseVAT', 'class'=>'hhk-feeskeys', 'type'=>'checkbox', 'style'=>'margin-right:.4em;', 'data-amt'=> number_format($reimburseTax, 2)])
                            .HTMLContainer::generateMarkup('span', '($' . number_format($reimburseTax, 2) . ')', ['id'=>'spnHeldAmt']))
                        .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'reimburseVat', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;'])
                        , ['style'=>'text-align:right;'])
                    );
                }
            }
        }


        if ($showRoomFees && is_null($visitCharge) === FALSE) {
            $uS = Session::getInstance();

            // Make middle column td.
            $td = HTMLContainer::generateMarkup('span', HTMLContainer::generateMarkup('button',
                    HTMLContainer::generateMarkup('span', '$', ['class'=>'px-2']).
                    HTMLInput::generateMarkup('', ['id'=>'feesCharges', 'readonly'=>'readonly', 'size' => '7', 'style'=>'padding:0; border:none; margin:0;'])
                    . HTMLContainer::generateMarkup('label',  HTMLContainer::generateMarkup('span', '', ['class'=>'ui-icon ui-icon-arrowthick-1-e']), ['for'=>'feesCharges'])
                    , ['id'=>'feesChargesContr', 'class'=>'ui-button ui-widget ui-corner-all hhk-RoomCharge', 'style'=>'min-width:fit-content; padding:0;'])
                .HTMLInput::generateMarkup('', ['id'=>'daystoPay', 'size'=>'6', 'data-vid'=>$idVisit, 'placeholder'=>'# days', 'style'=>'text-align: center;']
            ), ($uS->HideRoomFeeCalc ? ['class'=>"d-none"] : []) );

        	$feesTbl->addBodyTr(HTMLTable::makeTd($labels->getString('PaymentChooser', 'PayRmFees', 'Pay Room Fees').':', ['class'=>'tdlabel'])
                .HTMLTable::makeTd($td, ["style"=>"text-align: center;min-width: 62px;"])
                .HTMLTable::makeTd('$'.
                    HTMLInput::generateMarkup('', ['name'=>'feesPayment', 'size'=>'8', 'class'=>'hhk-feeskeys','style'=>'text-align:right;'])
                    , ['style'=>'text-align:right;', 'class'=>'hhk-feesPay']
                )
                , ['class'=>'hhk-RoomFees']
            );

            if (count($taxedItems) > 0) {

                foreach ($taxedItems as $t) {
                    // show tax line
                    $feesTbl->addBodyTr(HTMLTable::makeTd($t->getTaxingItemDesc() . ' ('. $t->getTextPercentTax().' ):', ['class'=>'tdlabel', 'colspan'=>'2'])
                        .HTMLTable::makeTd(
                            HTMLInput::generateMarkup('', ['name'=>'feesTax'.$t->getIdTaxingItem(), 'data-taxrate'=>$t->getDecimalTax(), 'size'=>'6', 'class'=>'hhk-feeskeys  hhk-TaxingItem hhk-applyTax', 'style'=>'border:none;text-align:right;', 'readonly'=>'readonly'])
                            , ['style'=>'text-align:right;', 'class'=>'hhk-feesPay'])
                        , ['class'=>'hhk-RoomFees']);
                }
            }

            // Extra payments - only if checking out and room overpaid.
            $feesTbl->addBodyTr(
                HTMLTable::makeTd($labels->getString('PaymentChooser', 'ExtraPayment', 'Extra Payment') . ':', ['class' => 'tdlabel', 'colspan'=>'2'])
                . HTMLTable::makeTd(
                    HTMLInput::generateMarkup('', ['name' => 'extraPay', 'size' => '8', 'class' => 'hhk-feeskeys', 'style' => 'text-align:right;'])
                    , ['style' => 'text-align:right;']
                )
                ,
                ['style' => 'display:none;', 'class' => 'hhk-extraPayTr']
            );

        }




        // House Discount Amount
        if ($showFinalPayment && $showRoomFees) {

            $attrs = ['name' => 'houseWaiveCb', 'type' => 'checkbox', 'class' => 'hhk-feeskeys', 'style' => 'margin-right:.4em;'];

            $feesTbl->addBodyTr(
                HTMLTable::makeTd('House Waive:', ['class' => 'tdlabel'])
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('label', "Apply", ['for' => 'houseWaiveCb', 'style' => 'margin-left:5px;margin-right:3px;'])
                    . HTMLInput::generateMarkup('', $attrs))
                . HTMLTable::makeTd(
                    HTMLInput::generateMarkup('', ['name' => 'HsDiscAmount', 'size' => '8', 'class' => 'hhk-feeskeys', 'readonly' => 'readonly', 'style' => 'border:none;text-align:right;'])
                    ,
                    ['style' => 'text-align:right;']
                ),
                ['style' => 'display:none;', 'class' => ($useHouseWaive ? 'hhk-HouseDiscount' : '')]
            );

        }


        // Amount to pay
        $feesTbl->addBodyTr(
            HTMLTable::makeTh(HTMLContainer::generateMarkup('span', 'Payment Amount:', ['id' => 'spnPayTitle']), array('colspan' => '2', 'class' => 'tdlabel'))
            . HTMLTable::makeTd(
                '$' . HTMLInput::generateMarkup('', ['name' => 'totalPayment', 'size' => '8', 'class' => 'hhk-feeskeys', 'style' => 'border:none;text-align:right;font-weight:bold;', 'readonly' => 'readonly'])
                ,
                ['style' => 'text-align:right;border:2px solid #2E99DD;']
            )
            ,
            ['class' => 'totalPaymentTr']
        );


        // Extra payment & distribution Selector
        if (count($excessPays) > 0) {

            $feesTbl->addBodyTr(HTMLTable::makeTh('Overpayment Amount:', ['class'=>'tdlabel', 'colspan'=>'2'])
                    .HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', ['name'=>'txtOverPayAmt', 'style'=>'border:none;text-align:right;font-weight:bold;', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'size'=>'8'])
                            , ['style'=>'text-align:right;'])
                    , ['class'=>'hhk-Overpayment']);

            $sattrs = ['id'=>'selexcpay', 'style'=>'margin-left:3px;', 'class'=>'hhk-feeskeys'];

            $feesTbl->addBodyTr(HTMLTable::makeTd('Apply to:', ['class'=>'tdlabel', 'colspan'=>'2'])
                    .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($excessPays, '', TRUE), $sattrs))
                    , ['class'=>'hhk-Overpayment']);

        }

        // Payment Date
        $feesTbl->addBodyTr(
            HTMLTable::makeTd('Pay Date:', array('colspan' => '2', 'class' => 'tdlabel'))
            . HTMLTable::makeTd(HTMLInput::generateMarkup(date('M j, Y'), array('name' => 'paymentDate', 'readonly' => 'readonly', 'class' => 'hhk-feeskeys ckdate')))
            ,
            ['style' => 'display:none;', 'class' => 'hhk-minPayment']
        );



        // Invoice Notes
        $feesTbl->addBodyTr(
            HTMLTable::makeTh('Invoice Notes (Public)', array('colspan'=>'3', 'style'=>'text-align:left;'))
                , array('style'=>'display:none;', 'class'=>'hhk-minPayment'));

        $feesTbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'txtInvNotes', 'rows'=>1, 'style'=>'width:100%;', 'class'=>'hhk-feeskeys')), array('colspan'=>'3'))
               , array('style'=>'display:none;', 'class'=>'hhk-minPayment'));

        // Error message
        $mess = HTMLContainer::generateMarkup('div','', array('id'=>'payChooserMsg', 'style'=>'clear:left;color:red;margin:5px;display:none'));

        return $mess . $feesTbl->generateMarkup(['id'=>'payTodayTbl', 'style'=>'margin-right:7px;float:left;']);
    }

    /**
     * Summary of showPaySelection
     * @param \PDO $dbh
     * @param mixed $defaultPayType
     * @param array $payTypes
     * @param Labels $labels
     * @param \HHK\Payment\PaymentGateway\AbstractPaymentGateway $paymentGateway
     * @param int $idPrimaryGuest
     * @param int $idReg
     * @param int $prefTokenId
     * @return string
     */
    protected static function showPaySelection(\PDO $dbh, $defaultPayType, $payTypes, $labels, AbstractPaymentGateway $paymentGateway, $idPrimaryGuest, $idReg, $prefTokenId = 0) {

        $payTbl = new HTMLTable();

        // Payment Amount
        $payTbl->addBodyTr(HTMLTable::makeTd('Payment Amount:', ['colspan'=>'2', 'class'=>'tdlabel', 'style'=>'font-weight:bold;'])
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', ['id'=>'spnPayAmount'])
                , ['style'=>'font-weight:bold;']));

        // Payment Types
        $payTbl->addBodyTr(HTMLTable::makeTd('Pay With:', ['class'=>'tdlabel'])
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($payTypes), $defaultPayType, FALSE), ['name'=>'PayTypeSel', 'class'=>'hhk-feeskeys'])
                    , ['colspan'=>'2']));

        // Cash Amt Tendered
        $payTbl->addBodyTr(
             HTMLTable::makeTd($labels->getString('PaymentChooser', 'amtTenderedPrompt', 'Amount Tendered') . ': ', ['colspan'=>'2', 'style'=>'text-align:right;', 'class'=>'tdlabel'])
                     .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'txtCashTendered', 'size'=>'6', 'style'=>'margin-right:.4em;text-align:right;', 'class'=>'hhk-feeskeys']), array('style'=>'text-align:right;'))
                     , ['style'=>'display:none;', 'class'=>'hhk-cashTndrd']);

        $payTbl->addBodyTr(
             HTMLTable::makeTd('', ['id'=>'tdCashMsg', 'colspan'=>'3', 'style'=>'color:red;'])
                     , ['style'=>'display:none;', 'class'=>'hhk-cashTndrd']);

        $payTbl->addBodyTr(
                HTMLTable::makeTd('Change: ' , ['colspan'=>'2', 'style'=>'text-align:right;', 'class'=>'tdlabel'])
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span','', ['id'=>'txtCashChange', 'style'=>'min-width:3em;']), ['style'=>'text-align:right;'])
                , ['style'=>'display:none;', 'class'=>'hhk-cashTndrd']);

        // Check number
        $payTbl->addBodyTr(
             HTMLTable::makeTd('Check Number: ', ['colspan'=>'2', 'class'=>'tdlabel'])
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'txtCheckNum', 'size'=>'10', 'class'=>'hhk-feeskeys']))
                , ['style'=>'display:none;', 'class'=>'hhk-cknum']);

        // Transfer account
        $payTbl->addBodyTr(
                HTMLTable::makeTd('Transfer Acct:', ['colspan'=>'2', 'class'=>'tdlabel'])
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', ['name'=>'txtTransferAcct', 'size'=>'10', 'class'=>'hhk-feeskeys']))
                , ['style'=>'display:none;', 'class'=>'hhk-transfer']);

        // credit info
        if (isset($payTypes[PayType::Charge])) {

            // Charge card gateway
            $tkRsArray = CreditToken::getRegTokenRSs($dbh, $idReg, $paymentGateway->getGatewayType(), $idPrimaryGuest);
            self::CreditBlock($dbh, $payTbl, $tkRsArray, $paymentGateway, $prefTokenId);
        }

        // Payment notes
        $payTbl->addBodyTr(HTMLTable::makeTh('Payment Notes (Private)', ['style'=>'text-align:left;min-width:250px;', 'colspan'=>'3']), ['class'=>'paySelectNotes']);
        $payTbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', ['name'=>'txtPayNotes', 'rows'=>1, 'style'=>'width:100%;', 'class'=>'hhk-feeskeys']), ['colspan'=>'3'])
            , ['class'=>'paySelectNotes']);


        return $payTbl->generateMarkup(array('id' => 'tblPaySelect'));
    }

    /**
     * Summary of showReturnSelection
     * @param \PDO $dbh
     * @param string $defaultPayType
     * @param array $payTypes
     * @param \HHK\Payment\PaymentGateway\AbstractPaymentGateway $paymentGateway
     * @param int $idPrimaryGuest
     * @param int $idReg
     * @param int $prefTokenId
     * @return string
     */
    protected static function showReturnSelection(\PDO $dbh, $defaultPayType, $payTypes, AbstractPaymentGateway $paymentGateway, $idPrimaryGuest, $idReg, $prefTokenId) {

        $payTbl = new HTMLTable();

        // Payment Amount
        $payTbl->addBodyTr(HTMLTable::makeTd('Return Amount:', array('class'=>'tdlabel', 'style'=>'font-weight:bold;'))
                .HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array('name'=>'txtRtnAmount', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'font-weight:bold;border:none;')), array('colspan'=>'2', 'style'=>'text-align:right;')));

        // Payment Types
        $payTbl->addBodyTr(HTMLTable::makeTd('With:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($payTypes), $defaultPayType, FALSE), array('name'=>'rtnTypeSel', 'class'=>'hhk-feeskeys')), array('colspan'=>'2')));

        // Check number
        $payTbl->addBodyTr(
             HTMLTable::makeTd('Check Number: ', array('colspan'=>'2', 'class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtRtnCheckNum', 'size'=>'10', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-cknumr'));

        // Transfer account
        $payTbl->addBodyTr(
                HTMLTable::makeTd('Transfer Acct:', array('colspan'=>'2', 'class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtRtnTransferAcct', 'size'=>'10', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-transferr'));

        // credit info
        if (isset($payTypes[PayType::Charge])) {

            // Charge card gateway
            $tkRsArray = CreditToken::getRegTokenRSs($dbh, $idReg, $paymentGateway->getGatewayType(), $idPrimaryGuest);
            self::CreditBlock($dbh, $payTbl, $tkRsArray, $paymentGateway, $prefTokenId, ReturnIndex::ReturnIndex);

        }

        // Payment notes
        $payTbl->addBodyTr(HTMLTable::makeTh('Return Payment Notes', array('style'=>'text-align:left;min-width:250px;', 'colspan'=>'3')), array('class'=>'payReturnNotes'));
        $payTbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'txtRtnNotes', 'rows'=>1, 'style'=>'width:100%;', 'class'=>'hhk-feeskeys')), array('colspan'=>'3')), array('class'=>'payReturnNotes'));


        return $payTbl->generateMarkup(array('id' => 'tblRtnSelect'));
    }

    /**
     * Summary of CreditBlock
     * @param \PDO $dbh
     * @param HTMLTable $tbl
     * @param array $tkRsArray
     * @param \HHK\Payment\PaymentGateway\AbstractPaymentGateway $paymentGateway
     * @param int $prefTokenId
     * @param string $index
     * @param string $display
     * @return void
     */
    public static function CreditBlock(\PDO $dbh, &$tbl, $tkRsArray, AbstractPaymentGateway $paymentGateway, $prefTokenId = 0, $index = '', $display = 'display:none;') {

        if (count($tkRsArray) < 1 && $index == ReturnIndex::ReturnIndex) {
            // Cannot return to a new card...
            $tbl->addBodyTr(HTMLTable::makeTh("No Cards on file", array('colspan'=>'3'))
                , array('style'=>$display, 'class'=>'tblCredit' . $index));
            return;
        }

        $tbl->addBodyTr(HTMLTable::makeTh("Card on File") . HTMLTable::makeTh("Name") . HTMLTable::makeTh("Use")
                , array('style'=>$display, 'class'=>'tblCredit' . $index));

        // Pick a preferred token if one is not specified.
        if ($prefTokenId < 1 && count($tkRsArray) > 0) {
            $keys = array_keys($tkRsArray);
            $prefTokenId = $tkRsArray[$keys[0]]->idGuest_token->getStoredVal();
        }

        $attr = array('type'=>'radio', 'name'=>'rbUseCard' . $index, 'class' => 'hhk-feeskeys');

        // List any valid stored cards on file
        foreach ($tkRsArray as $tkRs) {

            if ($tkRs->CardType->getStoredVal() == '' || $tkRs->MaskedAccount->getStoredVal() == '') {
                continue;
            }

            if ($tkRs->idGuest_token->getStoredVal() == $prefTokenId) {
                $attr['checked'] = 'checked';
            } else if (isset($attr['checked'])) {
                unset($attr['checked']);
            }

            if ($tkRs->Merchant->getStoredVal() == '' || strtolower($tkRs->Merchant->getStoredVal()) == 'production' || strtolower($tkRs->Merchant->getStoredVal()) == 'local') {
                $merchant = '';
            } else {
                $merchant = ' (' . ucfirst($tkRs->Merchant->getStoredVal()) . ')';
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd($tkRs->CardType->getStoredVal() . ' - ' . $tkRs->MaskedAccount->getStoredVal() . $merchant)
                    . HTMLTable::makeTd($tkRs->CardHolderName->getStoredVal())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($tkRs->idGuest_token->getStoredVal(), $attr))
                , array('style'=>$display, 'class'=>'tblCredit' . $index));

        }

        // New card.  Not for credit return.
        if ($index !== ReturnIndex::ReturnIndex) {

        	if (count($tkRsArray) == 0) {
                $attr['checked'] = 'checked';
            } else {
                unset($attr['checked']);
            }

            $tbl->addBodyTr(HTMLTable::makeTd('New', array('style'=>'text-align:right;', 'colspan'=> '2'))
                .  HTMLTable::makeTd(HTMLInput::generateMarkup('0', $attr))
                    , array('style'=>$display, 'class'=>'tblCredit' . $index));
            $tbl->addBodyTr(
                 HTMLTable::makeTd('', array('id'=>'tdChargeMsg', 'colspan'=>'3', 'style'=>'color:red;'))
                     , array('style'=>'display:none;', 'class'=>'tblCredit' . $index));

            $paymentGateway->selectPaymentMarkup($dbh, $tbl);

        }

    }

    /**
     * Summary of invoiceBlock
     * @param mixed $index
     * @return string
     */
    public static function invoiceBlock($index = '') {

        $tblInvoice = new HTMLTable();
        $tblInvoice->addHeaderTr(HTMLTable::makeTh("Invoice", array('colspan' => '4')));

        // Show member chooser
        $tblInvoice->addBodyTr(
                HTMLTable::makeTd('Search:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtInvSearch' . $index, 'size'=>'35')))

                );

        $tblInvoice->addBodyTr(
                HTMLTable::makeTd('Invoicee:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtInvName' . $index, 'size'=>'35', 'readonly'=>'readonly'))
                        . HTMLInput::generateMarkup('', array('name'=>'txtInvId' . $index, 'class'=>'hhk-feeskeys', 'type'=>'hidden')))
                );

        $tblInvoice->addBodyTr(
             HTMLTable::makeTd('', array('id'=>'tdInvceeMsg', 'colspan'=>'3', 'style'=>'color:red;display:none;')));


        return $tblInvoice->generateMarkup(array('id' => 'tblInvoice' . $index));

    }

}
