<?php
/**
 * PaymentChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
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
     * @param array $post
     * @param string $rtnIndex
     * @return \PaymentManagerPayment
     */
    public static function readPostedPayment(\PDO $dbh, $post, $rtnIndex = ReturnIndex::ReturnIndex) {

        $uS = Session::getInstance();


        // Payment Type
        if (isset($post['PayTypeSel'])) {
            $payType = filter_var($post['PayTypeSel'], FILTER_SANITIZE_STRING);
        } else if (isset($post['rtnTypeSel'])) {
            $payType = filter_var($post['rtnTypeSel'], FILTER_SANITIZE_STRING);
        } else {
            return NULL;
        }


        $pmp = new PaymentManagerPayment($payType);

        // Return payment type
        if (isset($post['rtnTypeSel'])) {
            $pmp->setRtnPayType(filter_var($post['rtnTypeSel'], FILTER_SANITIZE_STRING));
        }

        // Payment Date
        if (isset($post['paymentDate']) && $post['paymentDate'] != '') {
            $pDT = setTimeZone($uS, filter_var($post['paymentDate'], FILTER_SANITIZE_STRING));
            $pmp->setPayDate($pDT->format('Y-m-d H:i:s'));
        } else {
            $pmp->setPayDate(date('Y-m-d H:i:s'));
        }

        // Credit token
        if (isset($post['rbUseCard'])) {
            $pmp->setIdToken(intval(filter_var($post['rbUseCard'], FILTER_SANITIZE_STRING), 10));
        }

        if (isset($post['rbUseCard' . $rtnIndex])) {
            $pmp->setRtnIdToken(intval(filter_var($post['rbUseCard' . $rtnIndex], FILTER_SANITIZE_STRING), 10));
        }

        // Manual Key check box
        if (isset($post['btnvrKeyNumber'])) {
            $pmp->setManualKeyEntry(TRUE);
        } else {
            $pmp->setManualKeyEntry(FALSE);
        }

        if (isset($post['txtvdNewCardName'])) {
            $pmp->setCardHolderName(strtoupper(filter_var($post['txtvdNewCardName'], FILTER_SANITIZE_STRING)));
        }

        // Invoice payor
        if (isset($post['txtInvId'])) {
            $pmp->setIdInvoicePayor(intval(filter_var($post['txtInvId'], FILTER_SANITIZE_NUMBER_INT), 10));
        }

        // Invoice notes
        if (isset($post['txtInvNotes'])) {
            $pmp->setInvoiceNotes(filter_var($post['txtInvNotes'], FILTER_SANITIZE_STRING));
        }

        // Check number
        if (isset($post['txtCheckNum'])) {
            $pmp->setCheckNumber(filter_var($post['txtCheckNum'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['txtRtnCheckNum'])) {
            $pmp->setRtnCheckNumber(filter_var($post['txtRtnCheckNum'], FILTER_SANITIZE_STRING));
        }

        // Transfer Account
        if (isset($post['txtTransferAcct'])) {
            $pmp->setTransferAcct(filter_var($post['txtTransferAcct'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['txtRtnTransferAcct'])) {
            $pmp->setRtnTransferAcct(filter_var($post['txtRtnTransferAcct'], FILTER_SANITIZE_STRING));
        }

        // Charge Card - External Swipe
        if (isset($post['selChargeType'])) {
            $pmp->setChargeCard(filter_var($post['selChargeType'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['selRtnChargeType'])) {
            $pmp->setRtnChargeCard(filter_var($post['selRtnChargeType'], FILTER_SANITIZE_STRING));
        }

        // Payment Notes.
        if (isset($post['txtPayNotes'])) {

            $payNotes = filter_var($post['txtPayNotes'], FILTER_SANITIZE_STRING);

            if ($payNotes != '') {

                $pmp->setPayNotes($payNotes);

            } else {

                // Return Payment Notes.
                if (isset($post['txtRtnNotes'])) {
                    $pmp->setPayNotes(filter_var($post['txtRtnNotes'], FILTER_SANITIZE_STRING));
                }
            }
        }

        // Charge Acct - External Swipe
        if (isset($post['txtChargeAcct'])) {
            $pmp->setChargeAcct(filter_var($post['txtChargeAcct'], FILTER_SANITIZE_STRING));
        }
        if (isset($post['txtRtnChargeAcct'])) {
            $pmp->setRtnChargeAcct(filter_var($post['txtRtnChargeAcct'], FILTER_SANITIZE_STRING));
        }

        // cash tendered
        if (isset($post['txtCashTendered'])) {
            $pmp->setCashTendered(floatval(filter_var($post['txtCashTendered'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        //  Visit fees
        if (isset($post['visitFeeCb']) && isset($post['visitFeeAmt'])) {
            $pmp->setVisitFeePayment(floatval(filter_var($post['visitFeeAmt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Room/Key deposit
        if (isset($post["keyDepRx"]) && isset($post["keyDepAmt"])) {
            $pmp->setKeyDepositPayment(floatval(filter_var($post["keyDepAmt"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Retained Amount payment
        if (isset($post["cbHeld"]) && isset($post["heldAmount"])) {
            $pmp->setRetainedAmtPayment(floatval(filter_var($post["heldAmount"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Deposit Refund.
        if (isset($post["DepRefundAmount"])) {
            $pmp->setDepositRefundAmt(floatval(filter_var($post["DepRefundAmount"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Room fees.
        if (isset($post["feesPayment"])) {
            $pmp->setRatePayment(floatval(filter_var($post["feesPayment"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Room fee taxes.
        if (isset($post["feesTax"])) {
            $pmp->setRateTax(floatval(filter_var($post["feesTax"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Total Room Charge.
        if (isset($post["feesCharges"])) {
            $pmp->setTotalRoomChg(floatval(filter_var($post["feesCharges"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // House Discount amount
        if (isset($post['HsDiscAmount'])) {
            $pmp->setHouseDiscPayment(floatval(filter_var($post["HsDiscAmount"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // OverPay amount
        if (isset($post['txtOverPayAmt'])) {
            $pmp->setOverPayment(floatval(filter_var($post["txtOverPayAmt"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Refund amount
        if (isset($post['txtRtnAmount'])) {
            $pmp->setRefundAmount(floatval(filter_var($post["txtRtnAmount"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Guest Credit
        if (isset($post['guestCredit'])) {
            $pmp->setGuestCredit(floatval(filter_var($post["guestCredit"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // Total payment.
        if (isset($post["totalPayment"])) {
            $pmp->setTotalPayment(floatval(filter_var($post["totalPayment"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)));
        }

        // unpaid invoices
        foreach ($post as $key => $p) {
            if (($num = strstr($key, 'unpaidCb', TRUE)) !== FALSE) {
                $num = filter_var($num, FILTER_SANITIZE_STRING);
                $amt = floatval(filter_var($post[$num . 'invPayAmt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                $pmp->addInvoiceByNumber($dbh, $num, $amt);
            }
        }

        // balence with
        if (isset($post['selexcpay'])) {
            $pmp->setBalWith(filter_var($post['selexcpay'], FILTER_SANITIZE_STRING));
        }

        // Final Payment
        if (isset($post['cbFinalPayment'])) {
            $pmp->setFinalPaymentFlag(TRUE);
        } else {
            $pmp->setFinalPaymentFlag(FALSE);
        }

        if (isset($post['cbNewCard'])) {
            $pmp->setNewCardOnFile(TRUE);
        } else {
            $pmp->setNewCardOnFile(FALSE);
        }

        return $pmp;
    }


    public static function createMarkup(\PDO $dbh, $idGuest, $idRegistration, VisitCharges $visitCharge, $defaultPayType, $deposit, $showFinalPayment = FALSE, $payVFeeFirst = TRUE, $prefTokenId = 0) {

        $uS = Session::getInstance();

        if ($defaultPayType == '') {
            $defaultPayType = $uS->DefaultPayType;
        }

        $unpaidInvoices = Invoice::load1stPartyUnpaidInvoices($dbh, $visitCharge->getIdVisit(), $uS->returnId);

        $showRoomFees = TRUE;
        if ($uS->RoomPriceModel == ItemPriceCode::None) {
            $showRoomFees = FALSE;
        }

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        // Get taxed items
        $taxedItems = getTaxedItems($dbh);

        $useVisitFee = FALSE;
        if($uS->VisitFee && ($visitCharge->getNightsStayed() > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == '')){
                $useVisitFee = TRUE;
        }

        $mkup = HTMLContainer::generateMarkup('div',
                self::createPaymentMarkup(
                    $showRoomFees,
                    $deposit,
                    $visitCharge->getDepositCharged(),
                    ($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()),
                    $useVisitFee,
                    $visitCharge->getVisitFeeCharged(),
                    ($visitCharge->getVisitFeesPaid() + $visitCharge->getVisitFeesPending()),
                    Registration::loadLodgingBalance($dbh, $idRegistration),
                    $payVFeeFirst,
                    $showFinalPayment,
                    $unpaidInvoices,
                    $labels,
                    $taxedItems,
                    $visitCharge->getIdVisit(),
                    readGenLookupsPDO($dbh, 'ExcessPays'),
                    $uS->VisitExcessPaid,
                    $uS->DefaultCkBalStmt
                )
                , array('id'=>'divPmtMkup', 'style'=>'float:left;margin-left:.3em;margin-right:.3em;')
                );

        $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');

        if ($uS->ShowTxPayType == FALSE) {
            unset($payTypes[PayType::Transfer]);
        }

        $chargeCards = removeOptionGroups(readGenLookupsPDO($dbh, 'Charge_Cards'));

        // Collect panels for payments
        $panelMkup = self::showPaySelection($dbh,
                $defaultPayType,
                $payTypes,
                $chargeCards,
                $labels,
                $uS->PaymentGateway,
                $idGuest, $idRegistration, $prefTokenId);



        if (isset($uS->nameLookups[GL_TableNames::PayType][PayType::Invoice])) {
            $panelMkup .= self::invoiceBlock();
        }

        if ($panelMkup != '') {
            $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));
        }

        // Collect panels for Returns
        unset($payTypes[PayType::Check]);
        $mkup .= HTMLContainer::generateMarkup('div', self::showReturnSelection($dbh,
                $defaultPayType,
                $payTypes,
                $chargeCards,
                $uS->PaymentGateway,
                $idGuest, $idRegistration, $prefTokenId),
                array('id'=>'divReturnPay', 'style'=>'float:left;display:none;'));


        return HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Paying Today', array('style'=>'font-weight:bold;'))
                . $mkup, array('id'=>'hhk-PayToday', 'class'=>'hhk-panel', 'style'=>'float:left;'));

    }

    public static function createUnpaidInvoiceMarkup($unpaidInvoices) {

        $trs = array();

        if (count($unpaidInvoices) > 0) {

            foreach ($unpaidInvoices as $i) {

                $trashIcon = '';

                $invNumber = $i['Invoice_Number'];
                $invAttr = array('href'=>'ShowInvoice.php?invnum='.$i['Invoice_Number'], 'target'=>'_blank', 'style'=>'float:left;');

                // Additional information
                $addnl = '';
                if (isset($i['Guest Name']) && $i['Guest Name'] != '') {
                    $addnl = HTMLContainer::generateMarkup('span', $i['Guest Name'], array('style'=>'margin: 0 5px;'));
                }

                if ($i['Amount'] - $i['Balance'] != 0) {
                    $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
                    $invAttr['title'] = 'Partially Paid';
                } else {
                    $trashIcon = HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-trash invAction', 'id'=>'invdel'.$i['idInvoice'], 'data-iid'=>$i['idInvoice'], 'data-stat'=>'del', 'style'=>'float:right;cursor:pointer;', 'title'=>'Delete'));
                }

                $unpaid = HTMLTable::makeTd(HTMLContainer::generateMarkup('span',
                        HTMLContainer::generateMarkup('a', 'Invoice ' . $invNumber, $invAttr)
                        . HTMLContainer::generateMarkup('span','', array('class'=>'ui-icon ui-icon-comment invAction', 'id'=>'invicon'.$i['idInvoice'], 'data-iid'=>$i['idInvoice'], 'data-stat'=>'view', 'style'=>'float:left;cursor:pointer;', 'title'=>'View Items'))
                        . $trashIcon
                        , array("style"=>'white-space:nowrap'))
                        .$addnl, array('class'=>'tdlabel'));


                $unpaid .= HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('label', 'Pay', array('for'=>$i['Invoice_Number'].'unpaidCb', 'style'=>'margin-left:5px;margin-right:3px;'))
                        .HTMLInput::generateMarkup('', array('name'=>$i['Invoice_Number'].'unpaidCb', 'type'=>'checkbox', 'data-invnum'=>$i['Invoice_Number'], 'data-invamt'=>$i['Balance'], 'class'=>'hhk-feeskeys hhk-payInvCb', 'style'=>'margin-right:.4em;', 'title'=>'Check to pay this invoice.'))
                        .HTMLContainer::generateMarkup('span', '($'. number_format($i['Balance'], 2) . ')', array('style'=>'font-style: italic;')))
                    .HTMLTable::makeTd('$'. HTMLInput::generateMarkup('', array('name'=>$i['Invoice_Number'].'invPayAmt', 'size'=>'8', 'class'=>'hhk-feeskeys hhk-payInvAmt','style'=>'text-align:right;')), array('style'=>'text-align:right;'));

                $trs[] = $unpaid;
            }
        }

        return $trs;
    }

    public static function createChangeRoomMarkup(\PDO $dbh, $idGuest, $idRegistration, VisitCharges $visitCharge, $prefTokenId = 0, $depositRefundType = '') {

        $uS = Session::getInstance();

        if ($uS->KeyDeposit === FALSE) {
            return '';
        }

        // no invoices
        $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');
        unset($payTypes[PayType::Invoice]);

        $labels = new Config_Lite(LABEL_FILE);

        $mkup = HTMLContainer::generateMarkup('div',
            self::createPaymentMarkup(
                FALSE,
                $uS->KeyDeposit,
                $visitCharge->getDepositCharged(),
                ($visitCharge->getDepositPending() + $visitCharge->getKeyFeesPaid()),
                FALSE,
                0,
                0,
                0,
                FALSE,
                FALSE,
                array(),
                $labels,
                getTaxedItems($dbh),
                $visitCharge->getIdVisit(),
                array(), '', FALSE, $depositRefundType)
            , array('id'=>'divPmtMkup', 'style'=>'float:left;margin-left:.3em;margin-right:.3em;')
        );

        // payment types panel
        $panelMkup = self::showPaySelection($dbh, $uS->DefaultPayType, $payTypes, removeOptionGroups(readGenLookupsPDO($dbh, 'Charge_Cards')), $labels, $uS->PaymentGateway,
                $idGuest, $idRegistration, $prefTokenId);

        $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));


        return HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Paying Today', array('style'=>'font-weight:bold;'))
                . $mkup, array('class'=>'hhk-panel hhk-kdrow', 'style'=>'float:left;'));
    }

    public static function createHousePaymentMarkup(array $discounts, array $addnls, $idVisit, $itemTax, $arrivalDate = '') {

        if (count($discounts) < 1 && count($addnls) < 1) {
            return '';
        }

        $buttons = '';
        $select = '';

        if (count($discounts) > 0) {

            $buttons .= HTMLContainer::generateMarkup('label', 'Discount', array('for'=>'cbAdjustPmt1'))
            . HTMLInput::generateMarkup('', array('type'=>'radio', 'name'=>'cbAdjustPmt', 'id'=>'cbAdjustPmt1', 'data-sho'=>'houseDisc', 'data-hid'=>'addnlChg', 'data-item'=>ItemId::Discount));

            $select .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($discounts), '', TRUE), array('name'=>'selHouseDisc', 'class'=>'houseDisc', 'data-amts'=>'disc'));

        }

        if (count($addnls) > 0) {

            $buttons .= HTMLContainer::generateMarkup('label', 'Additional Charge', array('for'=>'cbAdjustPmt2'))
                . HTMLInput::generateMarkup('', array('type'=>'radio', 'name'=>'cbAdjustPmt', 'id'=>'cbAdjustPmt2', 'data-hid'=>'houseDisc', 'data-sho'=>'addnlChg', 'data-item'=>ItemId::AddnlCharge));

            $select .= HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($addnls), '', TRUE), array('name'=>'selAddnlChg', 'class'=>'addnlChg', 'data-amts'=>'addnl'));

        }

        $feesTbl = new HTMLTable();

        $feesTbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $buttons, array('id'=>'cbAdjustType')), array('colspan'=>2)));


        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Select', array('class'=>'tdlabel')) . HTMLTable::makeTd($select));

        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Amount:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'housePayment', 'size'=>'9', 'data-vid'=>$idVisit, 'style'=>'text-align:right;'))));

        if (isset($itemTax[ItemId::AddnlCharge])) {

            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Tax (' . number_format($itemTax[ItemId::AddnlCharge], 3) . ')', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'houseTax', 'size'=>'9', 'data-tax'=>$itemTax[ItemId::AddnlCharge], 'readonly'=>'readonly', 'style'=>'text-align:right;')))
                    , array('class'=>'addnlChg', 'style'=>'display:none;'));

            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Total:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'totalHousePayment', 'size'=>'9', 'readonly'=>'readonly', 'style'=>'text-align:right;')))
                    , array('class'=>'addnlChg', 'style'=>'display:none;'));
        }

        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Date:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'housePaymentDate', 'class'=>'ckdate', 'data-vid'=>$idVisit))));

        $feesTbl->addBodyTr(
                HTMLTable::makeTd('Notes:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'housePaymentNote', 'rows'=>'2', 'cols'=>'40', 'data-vid'=>$idVisit))));

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

        return $feesTbl->generateMarkup(array('style'=>'clear:left;margin-bottom:7px;')) . $javaScript;

    }

    public static function createResvMarkup(\PDO $dbh, $idGuest, \Registration $reg, $payTypes, $defaultPayType, $ccgw) {

//        $labels = new Config_Lite(LABEL_FILE);
        $cof = '';


        if ($ccgw != '') {
            // show card on file button
            $cof = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Put card on file (no payment)', array('style'=>'font-weight:bold;'))
                . HouseServices::viewCreditTable($dbh, $reg->getIdRegistration(), 0)
                , array('id'=>'hhk-COF', 'class'=>'hhk-panel', 'style'=>'float:left;'));
        }


//        unset($payTypes[PayType::Invoice]);
//
//        $mkup = HTMLContainer::generateMarkup('div',
//                self::createPaymentMarkup(
//                    TRUE,
//                    FALSE,
//                    0,
//                    0,
//                    FALSE,
//                    0,
//                    0,
//                    0,
//                    FALSE,
//                    FALSE,
//                    array(),
//                    $labels)
//                , array('id'=>'divPmtMkup', 'style'=>'float:left;margin-left:.3em;margin-right:.3em;')
//        );
//
//        // payment types panel
//        $panelMkup = self::showPaySelection($dbh,
//                $defaultPayType,
//                $payTypes,
//                readGenLookupsPDO($dbh, 'Charge_Cards'),
//                array(),
//                $labels,
//                $ccgw,
//                $idGuest, $reg->getIdRegistration(), $reg->getPreferredTokenId());
//
//        $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));

//        $payToday = HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Pre-paying Today', array('style'=>'font-weight:bold;'))
//                . $mkup, array('id'=>'hhk-PayToday', 'class'=>'hhk-panel', 'style'=>'float:left;'));


        return $cof;

    }

    public static function createPayInvMarkup(\PDO $dbh, $id, $iid, $prefTokenId = 0) {

        $uS = Session::getInstance();

        $idInvoice = intval($iid, 10);

        if ($idInvoice > 0) {

            // Collect any unpaid invoices
            $stmt = $dbh->query("SELECT
    i.idInvoice,
    i.`Invoice_Number`,
    i.`Balance`,
    i.`Amount`,
    n.Name_Full,
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

                $labels = new Config_Lite(LABEL_FILE);

                $mkup = HTMLContainer::generateMarkup('div',
                        self::createPaymentMarkup(
                                FALSE,
                                FALSE,
                                0,
                                0,
                                FALSE,
                                0,
                                0,
                                0,
                                FALSE,
                                FALSE,
                                $unpaidInvoices,
                                $labels,
                                getTaxedItems($dbh))
                        , array('id'=>'divPmtMkup', 'style'=>'float:left;margin-left:.3em;margin-right:.3em;')
                );

                $payTypes = readGenLookupsPDO($dbh, 'Pay_Type');
                unset($payTypes[PayType::Invoice]);

                // House return invoices
//                if ($id == $uS->returnId) {
//                    unset($payTypes[PayType::Charge]);
//                    unset($payTypes[PayType::ChargeAsCash]);
//                    $panelMkup = self::showReturnSelection($dbh, PayType::Check, $payTypes, array(), $uS->ccgw,
//                        $id, 0, 0, '');
//
//                } else {
                    // payment types panel
                    $panelMkup = self::showPaySelection($dbh, $uS->DefaultPayType, $payTypes, removeOptionGroups(readGenLookupsPDO($dbh, 'Charge_Cards')), $labels, $uS->PaymentGateway,
                        $id, 0, $prefTokenId, '');
//                }

                $mkup .= HTMLContainer::generateMarkup('div', $panelMkup, array('style'=>'float:left;', 'class'=>'paySelectTbl'));

                return HTMLContainer::generateMarkup('fieldset', HTMLContainer::generateMarkup('legend', 'Paying Today: ' . $name, array('style'=>'font-weight:bold;'))
                        . $mkup, array('id'=>'hhk-PayToday', 'class'=>'hhk-panel', 'style'=>'float:left;'));
            }
        }

        return HTMLContainer('h3', "No unpaid invoices found.");
    }

    protected static function createPaymentMarkup($showRoomFees, $useKeyDeposit, $keyDepAmount, $keyDepPaid, $useVisitFee, $visitFeeAmt, $visitFeePaid, $heldAmount, $payVFeeFirst,
            $showFinalPaymentCB, array $unpaidInvoices, $labels, $taxedItems,  $idVisit = 0, $excessPays = array(), $defaultExcessPays = ExcessPay::Ignore, $defaultCkFinalPayCB = FALSE) {

        $feesTbl = new HTMLTable();

        // Get any Unpaid invoices
        $trs = self::createUnpaidInvoiceMarkup($unpaidInvoices);
        // Add them to the table
        foreach ($trs as $t) {
            $feesTbl->addBodyTr($t);
        }

        if ($useKeyDeposit) {

            $depositLabel = $labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit');

            $keyDeposit = HTMLTable::makeTd($depositLabel . ':', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(
                     HTMLContainer::generateMarkup('label', "Pay", array('for'=>'keyDepRx', 'style'=>'margin-left:5px;margin-right:3px;'))
                    .HTMLInput::generateMarkup('', array('name'=>'keyDepRx', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.4em;', 'title'=>'Check if ' . $depositLabel . ' Received.'))
                    .HTMLContainer::generateMarkup('span', ($keyDepAmount > 0 ? '($' . $keyDepAmount . ')' : ''), array('id'=>'spnDepAmt', 'data-amt'=>$keyDepAmount)))
                .HTMLTable::makeTd('$'. HTMLInput::generateMarkup($keyDepAmount, array('name'=>'keyDepAmt', 'size'=>'8', 'style'=>'border:none;text-align:right;', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'title'=>$depositLabel . ' Amount')), array('style'=>'text-align:right;'));

            $attrs = array('class'=>'hhk-kdrow', 'style'=>'display:none;');
            if ($keyDepAmount > 0) {
                unset($attrs['style']);
            }

            $feesTbl->addBodyTr($keyDeposit, $attrs);

        }


        if ($useVisitFee) {

            $vFeeTitle = $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee');
            $visitFee = HTMLTable::makeTd($vFeeTitle . ':', array('class'=>'tdlabel'));

            if ($visitFeeAmt == 0) {

                $visitFee = '';  // .= HTMLTable::makeTd('No Charge', array('colspan'=>'2'));

            } else if ($visitFeePaid > 0 && $visitFeePaid >= $visitFeeAmt) {

                $visitFee = '';  // .= HTMLTable::makeTd('Paid', array('colspan'=>'2'));

            } else {

                $vfAttr = array('name'=>'visitFeeCb', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.4em;', 'title'=>'Check if '.$vFeeTitle.' was received.');

                if ($payVFeeFirst) {
                    $vfAttr['checked'] = 'checked';
                }

                $visitFee .= HTMLTable::makeTd(HTMLContainer::generateMarkup('label', "Pay", array('for'=>'visitFeeCb', 'style'=>'margin-left:5px;margin-right:3px;'))
                    .HTMLInput::generateMarkup('', $vfAttr) . HTMLContainer::generateMarkup('span', ($visitFeeAmt > 0 ? '($' . $visitFeeAmt . ')' : ''), array('id'=>'spnvfeeAmt', 'data-amt'=>$visitFeeAmt)))
                    .HTMLTable::makeTd( '$'. HTMLInput::generateMarkup($visitFeeAmt, array('name'=>'visitFeeAmt', 'size'=>'8', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;', 'class'=>'hhk-feeskeys', 'title'=>$vFeeTitle.' amount')), array('style'=>'text-align:right;'));
            }


            if ($visitFee != '') {

                $attrs = array('class'=>'hhk-vfrow', 'style'=>'display:none;');
                if ($visitFeeAmt > 0) {
                    unset($attrs['style']);
                }
                $feesTbl->addBodyTr($visitFee, $attrs);
            }
        }

        // Fee Charges
        if ($showFinalPaymentCB) {

            // Any remaining room charges
            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Room Charges:', array('colspan'=>'2', 'class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'feesCharges', 'size'=>'8', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;', 'readonly'=>'readonly')), array('style'=>'text-align:right;'))
                , array('style'=>'display:none;', 'class'=>'hhk-RoomCharge'));

            // Any remaining guest credits
            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Guest Credit:', array('colspan'=>'2', 'class'=>'tdlabel'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'guestCredit', 'size'=>'8', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;', 'readonly'=>'readonly')), array('style'=>'text-align:right;'))
                , array('style'=>'display:none;', 'class'=>'hhk-GuestCredit'));


            // Deposit Return Amount
            if ($keyDepPaid > 0) {
                $feesTbl->addBodyTr(
                    HTMLTable::makeTd('Deposit Refund:', array('class'=>'tdlabel', 'colspan'=>'2'))
                    .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'DepRefundAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;'))
                            , array('style'=>'text-align:right;')), array('class'=>'hhk-refundDeposit'));
            }
        }

        // MOA money on account - held amount.
        if ($heldAmount > 0) {
            $feesTbl->addBodyTr(
                HTMLTable::makeTd('Retained Amount:', array('class'=>'tdlabel', 'title'=>'Money on Account (MOA)'))
                . HTMLTable::makeTd(
                        HTMLContainer::generateMarkup('label', "Apply", array('for'=>'keyDepRx', 'style'=>'margin-left:5px;margin-right:3px;'))
                        .HTMLInput::generateMarkup('', array('name'=>'cbHeld', 'class'=>'hhk-feeskeys', 'type'=>'checkbox', 'style'=>'margin-right:.4em;', 'data-amt'=>$heldAmount))
                    .HTMLContainer::generateMarkup('span', ($heldAmount > 0 ? '($' . number_format($heldAmount, 2) . ')' : ''), array('id'=>'spnHeldAmt')))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'heldAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;')), array('style'=>'text-align:right;')));
        }


        // Total Charges
        $feesTbl->addBodyTr(
                HTMLTable::makeTh('Total Charges:', array('colspan'=>'2', 'class'=>'tdlabel', 'style'=>'border-bottom:2px solid #2E99DD;'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'totalCharges', 'size'=>'8', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;font-weight:bold;', 'readonly'=>'readonly')), array('style'=>'text-align:right;border-bottom:2px solid #2E99DD;border-top:2px solid #2E99DD;'))
                , array('style'=>'display:none;', 'class'=>'hhk-finalPayment'));


        if ($showRoomFees) {

            $tax = (isset($taxedItems[ItemId::Lodging]) ? $taxedItems[ItemId::Lodging] : 0);

            $feesTbl->addBodyTr(HTMLTable::makeTd('Pay Room Fees:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd('Days: ' . HTMLInput::generateMarkup('', array('id'=>'daystoPay', 'size'=>'1', 'data-vid'=>$idVisit)), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'feesPayment', 'size'=>'8', 'class'=>'hhk-feeskeys','style'=>'text-align:right;', 'data-tax'=>$tax)), array('style'=>'text-align:right;', 'class'=>'hhk-feesPay'))
                , array('class'=>'hhk-RoomFees'));

            if ($tax > 0) {
                // show tax line
                $feesTbl->addBodyTr(HTMLTable::makeTd('Tax:', array('class'=>'tdlabel'))
                    .HTMLTable::makeTd('('.number_format($tax, 3).'%)', array('style'=>'text-align:center; font-size:smaller;'))
                    .HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array('name'=>'feesTax', 'size'=>'6', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;font-weight:bold;', 'readonly'=>'readonly')), array('style'=>'text-align:right;', 'class'=>'hhk-feesPay'))
                    , array('class'=>'hhk-RoomFees'));
            }
        }

        // House Discount Amount
        if ($showFinalPaymentCB && $showRoomFees) {

            $attrs = array('name'=>'cbFinalPayment', 'type'=>'checkbox', 'class'=>'hhk-feeskeys', 'style'=>'margin-right:.4em;');
            if ($defaultCkFinalPayCB) {
                $attrs['checked'] = 'checked';
            }

            $feesTbl->addBodyTr(
                HTMLTable::makeTd('House Waive:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('label', "Apply", array('for'=>'cbFinalPayment', 'style'=>'margin-left:5px;margin-right:3px;'))
                    .HTMLInput::generateMarkup('', $attrs))
                .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'HsDiscAmount', 'size'=>'8', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'style'=>'border:none;text-align:right;'))
                    , array('style'=>'text-align:right;')), array('style'=>'display:none;', 'class'=>'hhk-HouseDiscount'));

        }


        // Amount to pay
        $feesTbl->addBodyTr(
            HTMLTable::makeTh(HTMLContainer::generateMarkup('span', 'Payment Amount:', array('id'=>'spnPayTitle')), array('colspan'=>'2', 'class'=>'tdlabel'))
            .HTMLTable::makeTd('$'.HTMLInput::generateMarkup('', array('name'=>'totalPayment', 'size'=>'8', 'class'=>'hhk-feeskeys', 'style'=>'border:none;text-align:right;font-weight:bold;', 'readonly'=>'readonly'))
                    , array('style'=>'text-align:right;border-top:2px solid #2E99DD;border-bottom:2px solid #2E99DD;')));


       // Payment Date
        $feesTbl->addBodyTr(HTMLTable::makeTd('Pay Date:', array('colspan'=>'2', 'class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup(date('M j, Y'), array('name'=>'paymentDate', 'readonly'=>'readonly', 'class'=>'hhk-feeskeys ckdate')))
                , array('style'=>'display:none;', 'class'=>'hhk-minPayment'));


        // Extra payment & distribution Selector
        if ($defaultExcessPays !== ExcessPay::Ignore && count($excessPays) > 0) {

            $feesTbl->addBodyTr(HTMLTable::makeTh('Overpayment Amount:', array('class'=>'tdlabel', 'colspan'=>'2'))
                    .HTMLTable::makeTd('$' . HTMLInput::generateMarkup('', array('name'=>'txtOverPayAmt', 'style'=>'border:none;text-align:right;font-weight:bold;', 'data-amt'=>'0', 'class'=>'hhk-feeskeys', 'readonly'=>'readonly', 'size'=>'8'))
                            , array('style'=>'text-align:right;'))
                    , array('class'=>'hhk-Overpayment'));

            $sattrs = array('id'=>'selexcpay', 'style'=>'margin-left:3px;', 'class'=>'hhk-feeskeys');

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

        return $mess . $feesTbl->generateMarkup(array('id'=>'payTodayTbl', 'style'=>'margin-right:7px;float:left;'));
    }

    protected static function showPaySelection(\PDO $dbh, $defaultPayType, $payTypes, $chargeCards, $labels, $paymentGateway, $idPrimaryGuest, $idReg, $prefTokenId = 0) {

        $payTbl = new HTMLTable();

        // Payment Amount
        $payTbl->addBodyTr(HTMLTable::makeTd('Payment Amount:', array('class'=>'tdlabel', 'style'=>'font-weight:bold;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'spnPayAmount')), array('colspan'=>'2', 'style'=>'font-weight:bold;')));

        // Payment Types
        $payTbl->addBodyTr(HTMLTable::makeTd('Pay With:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($payTypes), $defaultPayType, FALSE), array('name'=>'PayTypeSel', 'class'=>'hhk-feeskeys'))
                        . ($paymentGateway == PaymentGateway::INSTAMED ?
                        HTMLContainer::generateMarkup('label', 'Key:', array('for'=>'btnvrKeyNumber', 'class'=>'hhkvrKeyNumber', 'style'=>'margin-left:1em;', 'title'=>'Key in credit account number'))
                        . HTMLInput::generateMarkup('Key', array('type'=>'checkbox', 'name'=>'btnvrKeyNumber', 'class'=>'hhk-feeskeys hhkvrKeyNumber', 'style'=>'margin-left:.3em;margin-top:2px;', 'title'=>'Key in credit account number')) : ''), array('colspan'=>'2')));

        if ($paymentGateway == PaymentGateway::INSTAMED) {

            $payTbl->addBodyTr(
                    HTMLTable::makeTd('Cardholder Name', array('class'=>'tdlabel hhkvrKeyNumber'))
                    .HTMLTable::makeTd( HTMLInput::generateMarkup('', array('type' => 'textbox', 'name' => 'txtvdNewCardName', 'class'=>'hhk-feeskeys hhkvrKeyNumber', 'style' => 'margin-right:4px;')), array('colspan' => '2'))
                , array('id'=>'trvdCHName'));
        }


        // Cash Amt Tendered
        $payTbl->addBodyTr(
             HTMLTable::makeTd($labels->getString('PaymentChooser', 'amtTenderedPrompt', 'Amount Tendered') . ': ', array('colspan'=>'2', 'style'=>'text-align:right;', 'class'=>'tdlabel'))
                     .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtCashTendered', 'size'=>'6', 'style'=>'margin-right:.4em;text-align:right;', 'class'=>'hhk-feeskeys')), array('style'=>'text-align:right;'))
                     , array('style'=>'display:none;', 'class'=>'hhk-cashTndrd'));
        $payTbl->addBodyTr(
             HTMLTable::makeTd('', array('id'=>'tdCashMsg', 'colspan'=>'3', 'style'=>'color:red;'))
                     , array('style'=>'display:none;', 'class'=>'hhk-cashTndrd'));
        $payTbl->addBodyTr(
                HTMLTable::makeTd('Change: ' , array('colspan'=>'2', 'style'=>'text-align:right;', 'class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('span','', array('id'=>'txtCashChange', 'style'=>'min-width:3em;')), array('style'=>'text-align:right;'))
                , array('style'=>'display:none;', 'class'=>'hhk-cashTndrd'));

        // Check number
        $payTbl->addBodyTr(
             HTMLTable::makeTd('Check Number: ', array('colspan'=>'2', 'class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtCheckNum', 'size'=>'10', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-cknum'));

        // Transfer account
        $payTbl->addBodyTr(
                HTMLTable::makeTd('Transfer Acct:', array('colspan'=>'2', 'class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtTransferAcct', 'size'=>'10', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-transfer'));

        // credit info
        if (isset($payTypes[PayType::Charge]) && $payTypes[PayType::Charge][2] == PaymentMethod::Charge) {

            // Charge card gateway
            self::CreditBlock($dbh, $payTbl, $idPrimaryGuest, $idReg, $paymentGateway, $prefTokenId);

        } else if (isset($payTypes[PayType::Charge])) {

            // Charge as Cash markup
            $payTbl->addBodyTr(
                HTMLTable::makeTd('Card: ', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($chargeCards, '', TRUE), array('name'=>'selChargeType', 'style'=>'margin-right:.4em;', 'class'=>'hhk-feeskeys')))
                .HTMLTable::makeTd(' Acct. #: '.HTMLInput::generateMarkup('', array('name'=>'txtChargeAcct', 'size'=>'4', 'title'=>'Only the last 4 digits.', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-mcred'));

        }

        // Payment notes
        $payTbl->addBodyTr(HTMLTable::makeTh('Payment Notes (Private)', array('style'=>'text-align:left;min-width:250px;', 'colspan'=>'3')), array('class'=>'paySelectNotes'));
        $payTbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'txtPayNotes', 'rows'=>1, 'style'=>'width:100%;', 'class'=>'hhk-feeskeys')), array('colspan'=>'3'))
            , array('class'=>'paySelectNotes'));


        return $payTbl->generateMarkup(array('id' => 'tblPaySelect'));
    }

    protected static function showReturnSelection(\PDO $dbh, $defaultPayType, $payTypes, $chargeCards, $paymentGateway, $idPrimaryGuest, $idReg, $prefTokenId = 0) {

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
                , array('style'=>'display:none;', 'class'=>'hhk-cknum'));

        // Transfer account
        $payTbl->addBodyTr(
                HTMLTable::makeTd('Transfer Acct:', array('colspan'=>'2', 'class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name'=>'txtRtnTransferAcct', 'size'=>'10', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-transferr'));


        // credit info
        if (isset($payTypes[PayType::Charge]) && $payTypes[PayType::Charge][2] == PaymentMethod::Charge) {

            // Credit gateway
            self::CreditBlock($dbh, $payTbl, $idPrimaryGuest, $idReg, $paymentGateway, $prefTokenId, ReturnIndex::ReturnIndex);

        } else if (isset($payTypes[PayType::Charge])) {

            // Charge as Cash markup
            $payTbl->addBodyTr(
                HTMLTable::makeTd('Card: ', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($chargeCards, '', TRUE), array('name'=>'selRtnChargeType', 'style'=>'margin-right:.4em;', 'class'=>'hhk-feeskeys')))
                .HTMLTable::makeTd(' Acct. #: '.HTMLInput::generateMarkup('', array('name'=>'txtRtnChargeAcct', 'size'=>'4', 'title'=>'Only the last 4 digits.', 'class'=>'hhk-feeskeys')))
                , array('style'=>'display:none;', 'class'=>'hhk-mcredr'));

        }

        // Payment notes
        $payTbl->addBodyTr(HTMLTable::makeTh('Return Payment Notes', array('style'=>'text-align:left;min-width:250px;', 'colspan'=>'3')), array('class'=>'payReturnNotes'));
        $payTbl->addBodyTr(
            HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea', '', array('name'=>'txtRtnNotes', 'rows'=>1, 'style'=>'width:100%;', 'class'=>'hhk-feeskeys')), array('colspan'=>'3')), array('class'=>'payReturnNotes'));


        return $payTbl->generateMarkup(array('id' => 'tblRtnSelect'));
    }

    protected static function CreditBlock(\PDO $dbh, &$tbl, $idPrimaryGuest, $idReg, $paymentGateway, $prefTokenId = 0, $index = '') {

        $tkRsArray = CreditToken::getRegTokenRSs($dbh, $idReg, $idPrimaryGuest);

        if (count($tkRsArray) < 1 && $index == ReturnIndex::ReturnIndex) {
            $tbl->addBodyTr(HTMLTable::makeTh("No Cards on file", array('colspan'=>'3'))
                , array('style'=>'display:none;', 'class'=>'tblCredit' . $index));
            return;
        }

        $tbl->addBodyTr(HTMLTable::makeTh("Card on File") . HTMLTable::makeTh("Name") . HTMLTable::makeTh("Use")
                , array('style'=>'display:none;', 'class'=>'tblCredit' . $index));

        // preset useCardRb
        if (count($tkRsArray) == 1 || (count($tkRsArray) > 1 && $prefTokenId == 0)) {
            $keys = array_keys($tkRsArray);
            $prefTokenId = $tkRsArray[$keys[0]]->idGuest_token->getStoredVal();
        }

        $attr = array('type'=>'radio', 'name'=>'rbUseCard' . $index, 'class' => 'hhk-feeskeys');

        // List any valid stored cards on file
        foreach ($tkRsArray as $tkRs) {

            if ($tkRs->idGuest_token->getStoredVal() == $prefTokenId) {
                $attr['checked'] = 'checked';
            } else if (isset($attr['checked'])) {
                unset($attr['checked']);
            }

            $tbl->addBodyTr(HTMLTable::makeTd($tkRs->CardType->getStoredVal() . ' - ' . $tkRs->MaskedAccount->getStoredVal())
                    . HTMLTable::makeTd($tkRs->CardHolderName->getStoredVal())
                    . HTMLTable::makeTd(HTMLInput::generateMarkup($tkRs->idGuest_token->getStoredVal(), $attr))
                , array('style'=>'display:none;', 'class'=>'tblCredit' . $index));

        }

        // New card.  Not for credit return.
        if ($index !== ReturnIndex::ReturnIndex) {

            if ($prefTokenId == 0) {
                $attr['checked'] = 'checked';
            } else {
                unset($attr['checked']);
            }

            $tbl->addBodyTr(HTMLTable::makeTd('New', array('style'=>'text-align:right;', 'colspan'=> '2'))
                .  HTMLTable::makeTd(HTMLInput::generateMarkup('0', $attr))
                    , array('style'=>'display:none;', 'class'=>'tblCredit' . $index));

        }

    }

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
