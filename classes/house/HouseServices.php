<?php

/**
 * HouseServices.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class HouseServices {

    /**
     * Show visit details
     *
     * @param PDO $dbh
     * @param int $idGuest Supply either this or the next
     * @param int $idVisit
     * @param int $span span = 'max' means load last visit span, otherwise load int value
     * @param boolean $isAdmin Administrator flag
     * @param string $action Processing code with various settings.
     *
     * @return array
     */
    public static function getVisitFees(\PDO $dbh, $idGuest, $idV, $idSpan, $isAdmin, $action = '', $coDate = '') {

        $uS = Session::getInstance();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $idVisit = intval($idV, 10);
        $span = intval($idSpan, 10);

        if ($idVisit < 1 || $span < 0) {
            return array("error" => "A Visit is not selected: " . $idV . "-" . $idSpan);
        }

        $query = "select * from vspan_listing where idVisit = $idVisit and Span = $span;";
        $stmt1 = $dbh->query($query);
        $rows = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

        $dataArray = array();


        if (count($rows) == 0) {
            return array("error" => "<span>No Data.</span>");
        }

        $r = $rows[0];

        // Hospital and association lists
        $r['Hospital'] = '';
        if (isset($uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']])) {
            $r['Hospital'] = $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
        }

        $r['Association'] = '';
        if (isset($uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']])) {
            $r['Association'] = $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1];
            if (trim($r['Association']) == '(None)') {
                $r['Association'] = '';
            }
        }


        $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        $visitCharge = new VisitCharges($r['idVisit']);
        $visitCharge->sumPayments($dbh);

        if ($action == 'ref') {
            // Visit is checking out to a different date than "today"
            $visitCharge->sumDatedRoomCharge($dbh, $priceModel, $coDate, 0, TRUE);
        } else {
            $visitCharge->sumCurrentRoomCharge($dbh, $priceModel, 0, TRUE);
        }

        // Show adjust button?
        $showAdjust = FALSE;
        $hdArry = readGenLookupsPDO($dbh, "House_Discount");
        $adnlArray = readGenLookupsPDO($dbh, 'Addnl_Charge');

        if ($action != 'cf' && (count($hdArry) > 0 || count($adnlArray) > 0)) {
            $showAdjust = TRUE;
        }

        $mkup = HTMLInput::generateMarkup($uS->EmptyExtendLimit, array('id' =>'EmptyExtend', 'type' => 'hidden'));

        // Get main visit markup section
        $mkup .= HTMLContainer::generateMarkup('div',
                VisitView::createActiveMarkup(
                        $dbh,
                        $r,
                        $visitCharge,
                        $uS->guestLookups[GL_TableNames::KeyDispositions],
                        $uS->KeyDeposit,
                        $uS->VisitFee,
                        $uS->Reservation,
                        $isAdmin,
                        $uS->EmptyExtendLimit,
                        $action,
                        $coDate,
                        $showAdjust)
                , array('style' => 'clear:left;margin-top:10px;'));

        // Change rooms control
        if ($action == 'cr' && $r['Status'] == VisitStatus::CheckedIn) {

            $dataArray['resc'] = Resource::roomListJSON($dbh);

            $expDepDT = new \DateTime($r['Expected_Departure']);
            $expDepDT->setTime(10, 0, 0);
            $now = new \DateTime();
            $now->setTime(10, 0, 0);

            if ($expDepDT < $now) {
                $expDepDT = $now->add(new \DateInterval('P1D'));
            }

            $reserv = Reservation_1::instantiateFromIdReserv($dbh, $r['idReservation'], $idVisit);
            $roomChooser = new RoomChooser($dbh, $reserv, 0, new \DateTime(), $expDepDT);
            $mkup .= $roomChooser->createChangeRoomsMarkup($dbh, $visitCharge, $idGuest, $isAdmin);

        // Pay fees
        } else if ($action == 'pf') {

            $mkup .= HTMLContainer::generateMarkup('div',
                    VisitView::createPaymentMarkup($dbh, $r, $visitCharge, $idGuest, $action), array('style' => 'min-width:600px;clear:left;'));

        } else {
            $mkup = HTMLContainer::generateMarkup('div',
                    VisitView::createStaysMarkup($dbh, $idVisit, $span, $r['idPrimaryGuest'], $isAdmin, $idGuest, $labels, $action, $coDate) . $mkup, array('id'=>'divksStays'));

            $mkup .= HTMLContainer::generateMarkup('div',
                    VisitView::createPaymentMarkup($dbh, $r, $visitCharge, $idGuest, $action), array('style' => 'min-width:600px;clear:left;'));
        }

        $mkup .= HTMLContainer::generateMarkup('div', VisitView::visitMessageArea('', ''), array('id' => 'visitMsg', 'class' => 'hhk-VisitMessage'));
        $dataArray['success'] = $mkup;


        if ($r['Span_End'] != '') {
            $vspanEndDT = new \DateTime($r['Span_End']);
            $vspanEndDT->sub(new DateInterval('P1D'));
        } else {
            $vspanEndDT = new \DateTime();
        }

        $vspanEndDT->setTime(23, 59, 59);
        $vspanStartDT = new \DateTime($r['Span_Start']);

        $dataArray['start'] = $vspanStartDT->format('c');
        $dataArray['end'] = $vspanEndDT->format('c');

        return $dataArray;
    }

    public static function saveFees(\PDO $dbh, $idVisit, $span, $isGuestAdmin, array $post, $postbackPage, $returnCkdIn = FALSE) {

        $uS = Session::getInstance();
        $dataArray = array();
        $creditCheckOut = array();
        $reply = '';
        $returnReserv = FALSE;

        $payFailPage = 'register.php';
        if (isset($post['payFailPage'])) {
            $payFailPage = filter_var($post['payFailPage'], FILTER_SANITIZE_STRING);
        }


        if ($idVisit == 0) {
            return array("error" => "Neither Guest or Visit was selected.");
        }


        // Remove any indicated visit stays
        if (isset($post['removeCb'])) {

            foreach ($post['removeCb'] as $r => $v) {
                $idRemoved = intval(filter_var($r, FILTER_SANITIZE_NUMBER_INT), 10);
                $reply .= VisitView::removeStays($dbh, $idVisit, $span, $idRemoved, $uS->username);
            }
        }


        // instantiate current visit
        $visit = new Visit($dbh, 0, $idVisit, NULL, NULL, NULL, $uS->username, $span);


        // Notes
        if (isset($post["tavisitnotes"])) {

            $notes = filter_var($post["tavisitnotes"], FILTER_SANITIZE_STRING);

            if ($notes != '' && $visit->getIdRegistration() > 0) {

                $roomTitle = $visit->getRoomTitle($dbh);
                $visit->setNotes($notes, $uS->username, $roomTitle);
                $visit->updateVisitRecord($dbh, $uS->username);

                //Add notes to psg Notes
                $stmt = $dbh->query("Select p.* from registration rg join psg p on rg.idPsg = p.idPsg where rg.idRegistration = " . $visit->getIdRegistration());
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (count($rows) > 0) {

                    $psgRs = new PSG_RS();
                    EditRS::loadRow($rows[0], $psgRs);

                    $oldNotes = is_null($psgRs->Notes->getStoredVal()) ? '' : $psgRs->Notes->getStoredVal();
                    $psgRs->Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . ', visit ' . $idVisit . '-' . $visit->getSpan() . ', room ' . $roomTitle . ', ' . $uS->username . ' - ' . $notes);
                    EditRS::update($dbh, $psgRs, array($psgRs->idPsg));
                }
            }
        }

        // Return Date
        if (isset($post['visRtn']) && $visit->getVisitStatus() == VisitStatus::CheckedIn) {

            $retDate = filter_var($post['visRtn'], FILTER_SANITIZE_STRING);

            if ($retDate != '') {
                $visit->setReturnDate(date('Y-m-d', strtotime($retDate)));
            }
        }

        // Change room rate
        if ($isGuestAdmin && isset($post['rateChgCB']) && isset($post['extendCb']) === FALSE) {
            $rateChooser = new RateChooser($dbh);
            $reply .= $rateChooser->changeRoomRate($dbh, $visit, $post);
            $returnCkdIn = TRUE;
        }

        // Change Visit Fee
        if (isset($post['selVisitFee'])) {

            $visitFeeOption = filter_var($post['selVisitFee'], FILTER_SANITIZE_STRING);

            $vFees = readGenLookupsPDO($dbh, 'Visit_Fee_Code');

            if (isset($vFees[$visitFeeOption])) {
                $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId());
                if ($resv->isNew() === FALSE) {

                    if ($resv->getVisitFee() != $vFees[$visitFeeOption][2]) {

                        $resv->setVisitFee($vFees[$visitFeeOption][2]);
                        $resv->saveReservation($dbh, $visit->getIdRegistration(), $uS->username);

                        $reply .= 'Cleaning Fee Setting Updated.  ';
                    }
                }
            }
        }

        // Change STAY Checkin date
        if (isset($post['stayCkInDate'])) {
            $reply .= $visit->moveStay($dbh, $post['stayCkInDate']);
        }


        // Undo checkout
        if (isset($post['undoCkout']) && $visit->getVisitStatus() == VisitStatus::CheckedOut) {

            // Get the new expected co date.
            if (isset($post['txtUndoDate'])) {

                $newExpectedDT = new \DateTime(filter_var($post['txtUndoDate'], FILTER_SANITIZE_STRING));
                $newExpectedDT->setTimezone(new \DateTimeZone($uS->tz));

            } else {

                $newExpectedDT = new \DateTime();
                $newExpectedDT->setTimezone(new \DateTimeZone($uS->tz));
                $newExpectedDT->add(new \DateInterval('P1D'));
            }

            $reply .= self::undoCheckout($dbh, $visit, $newExpectedDT, $uS->username);
            $returnCkdIn = TRUE;

        } else {

            // Instantiate a payment manager payment container.
            $paymentManger = new PaymentManager(PaymentChooser::readPostedPayment($dbh, $post));


            // Process a checked in guest
            if ($visit->getVisitStatus() == VisitStatus::CheckedIn) {

                // Change any expected checkout dates
                if (isset($post['stayExpCkOut'])) {

                    $replyArray = $visit->changeExpectedCheckoutDates($dbh, $post['stayExpCkOut'], $uS->MaxExpected, $uS->username);

                    if (isset($replyArray['isChanged']) && $replyArray['isChanged']) {
                        $returnCkdIn = TRUE;
                        $returnReserv = TRUE;
                    }

                    $reply .= $replyArray['message'];
                }


                // Begin visit leave?
                if (isset($post['extendCb']) && $uS->EmptyExtendLimit > 0) {

                    $extendStartDate = '';
                    if (isset($post['txtWStart']) && $post['txtWStart'] != '') {
                        $extendStartDate = filter_var($post['txtWStart'], FILTER_SANITIZE_STRING);
                    }

                    $extDays = 0;
                    if (isset($post['extendDays'])) {
                        $extDays = intval(filter_var($post['extendDays'], FILTER_SANITIZE_NUMBER_INT), 10);
                    }

                    $noCharge = FALSE;
                    if (isset($post['noChargeCb'])) {
                        $noCharge = TRUE;
                    }

                    $reply .= $visit->beginLeave($dbh, $extendStartDate, $extDays, $noCharge);
                    $returnCkdIn = TRUE;
                }


                // Return from leave?
                if (isset($post['leaveRetCb']) && $uS->EmptyExtendLimit > 0) {

                    $extendReturnDate = '';
                    if (isset($post['txtWRetDate']) && $post['txtWRetDate'] != '') {
                        $extendReturnDate = filter_var($post['txtWRetDate'], FILTER_SANITIZE_STRING);
                    }

                    $returning = TRUE;
                    if (isset($post['noReturnRb'])) {
                        $returning = FALSE;
                    }

                    $reply .= $visit->endLeave($dbh, $returning, $extendReturnDate);
                    $returnCkdIn = TRUE;
                }


                // Change Rooms?
                if (isset($post['selResource'])) {

                    $newRescId = intval(filter_var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);

                    if ($newRescId != 0 && $newRescId != $visit->getidResource()) {

                        $resc = Resource::getResourceObj($dbh, $newRescId);

                        $now = new \DateTime();

                        if (isset($post['rbReplaceRoomrpl'])) {

                            $chRoomDT = new \DateTime($visit->getSpanStart());

                        } else {

                            if (isset($post['resvChangeDate']) && $post['resvChangeDate'] != '') {

                                $chDT = setTimeZone($uS, filter_var($post['resvChangeDate'], FILTER_SANITIZE_STRING));
                                $chRoomDT = new \DateTime($chDT->format('Y-m-d') . ' ' . $now->format('H:i:s'));

                            } else {
                                $chRoomDT = $now;
                            }
                        }

                        $departDT = new \DateTime($visit->getExpectedDeparture());
                        $departDT->setTime(10, 0, 0);
                        $now2 = new \DateTime();
                        $now2->setTime(10, 0, 0);

                        if ($departDT < $now2) {
                            $departDT = $now2;
                        }

                        $arriveDT = new \DateTime($visit->getSpanStart());

                        if ($chRoomDT < $arriveDT || $chRoomDT > $now) {

                            $reply .= "The change room date must be within the visit timeframe, between " . $arriveDT->format('M j, Y') . ' and ' . $now->format('M j, Y');

                        } else {

                            $depDisposition = '';
                            if (isset($post["selDepDisposition"])) {
                                $depDisposition = filter_var($post["selDepDisposition"], FILTER_SANITIZE_STRING);
                            }

                            $reply .= $visit->changeRooms($dbh, $resc, $uS->username, $chRoomDT, $isGuestAdmin, $depDisposition);
                            $returnCkdIn = TRUE;
                            $returnReserv = TRUE;
                        }
                    }
                }


                // Change primary guest?
                if (isset($post['rbPriGuest'])) {
                    $newPg = intval(filter_var($post['rbPriGuest'], FILTER_SANITIZE_NUMBER_INT), 10);

                    if ($newPg > 0 && $newPg != $visit->getPrimaryGuestId()) {
                        $visit->setPrimaryGuestId($newPg);
                        $visit->updateVisitRecord($dbh, $uS->username);
                        $reply .= 'Primary Guest Id updated.  ';
                    }
                }

                // Check-out any guests.
                if (isset($post['stayActionCb'])) {

                    // Get any last note for the checkout email
                    $notes = '';
                    if (isset($post["tavisitnotes"])) {
                        $notes = filter_var($post["tavisitnotes"], FILTER_SANITIZE_STRING);
                    }

                    // See whose checking out
                    foreach ($post['stayActionCb'] as $idr => $v) {

                        $id = intval(filter_var($idr, FILTER_SANITIZE_NUMBER_INT));
                        if ($id < 1) {
                            continue;
                        }

                        // Enter into waitlist if return date is valid
                        if (isset($post['visRtn'])) {

                            $retDate = filter_var($post['visRtn'], FILTER_SANITIZE_STRING);

                            if ($retDate != '') {

                                $retDT = setTimeZone($uS, $retDate);

                                $reply .= Waitlist::makeGuestEntry($dbh, $id, $retDT->format('Y-m-d H:i:s'), $uS->username);
                            }
                        }

                        // Check out Date
                        $coDate = date('Y-m-d');
                        if (isset($post['stayCkOutDate'][$id]) && $post['stayCkOutDate'][$id] != '') {
                            $coDate = filter_var($post['stayCkOutDate'][$id], FILTER_SANITIZE_STRING);
                        }

                        $cDT = setTimeZone($uS, $coDate);
                        $dt = $cDT->format('Y-m-d');
                        $now = date('H:i:s');
                        $coDT = new \DateTime($dt . ' ' . $now);

                        $reply .= $visit->checkOutGuest($dbh, $id, $coDT->format('Y-m-d H:i:s'), $notes, TRUE);
                        $returnCkdIn = TRUE;

                        // Only need Notes once.
                        $notes = '';
                    }
                }
            }


            // Make guest payment
            $payResult = self::processPayments($dbh, $paymentManger, $visit, $postbackPage, $visit->getPrimaryGuestId());

            if (is_null($payResult) === FALSE) {

                $reply .= $payResult->getReplyMessage();

                if ($payResult->getStatus() == PaymentResult::FORWARDED) {
                    $creditCheckOut = $payResult->getForwardHostedPayment();
                }

                // Receipt
                if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                    $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());

                    Registration::updatePrefTokenId($dbh, $visit->getIdRegistration(), $payResult->getIdToken());
                }

                // New Invoice
                if (is_null($payResult->getInvoiceMarkup()) === FALSE && $payResult->getInvoiceMarkup() != '') {
                    $dataArray['invoice'] = HTMLContainer::generateMarkup('div', $payResult->getInvoiceMarkup());
                }
            }

        }

        // Undo Room Change
        if (isset($post['undoRmChg']) && $visit->getVisitStatus() == VisitStatus::NewSpan) {

            $reply .= self::undoRoomChange($dbh, $visit, $uS->username);
            $returnCkdIn = TRUE;

        }



        // divert to credit payment site.
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        }

        // Return checked in guests markup?
        if ($returnCkdIn) {
            $dataArray['curres'] = 'y';
        }

        if ($returnReserv && $uS->Reservation) {
            $dataArray['reservs'] = 'y';
            $dataArray['waitlist'] = 'y';

            if ($uS->ShowUncfrmdStatusTab) {
                $dataArray['unreserv'] = 'y';
            }
        }


        $dataArray['success'] = $reply;

        return $dataArray;
    }

    public static function showPayInvoice(\PDO $dbh, $id, $iid) {

        $mkup = HTMLContainer::generateMarkup('div', PaymentChooser::createPayInvMarkup($dbh, $id, $iid), array('style' => 'min-width:600px;clear:left;'));

        return array('mkup'=>$mkup);
    }

    public static function payInvoice(\PDO $dbh, $idPayor, array $post) {

        $reply = 'Uh-oh, Payment NOT made.';
        $postbackPage = '';
        $dataArray = array();
        $creditCheckOut = array();

        if (isset($post['pbp'])) {
            $postbackPage = filter_var($post['pbp'], FILTER_SANITIZE_STRING);
        }

        // Instantiate a payment manager payment container.
        $paymentManager = new PaymentManager(PaymentChooser::readPostedPayment($dbh, $post));

        // Is it a return payment?
        if (is_null($paymentManager->pmp) === FALSE && $paymentManager->pmp->getTotalPayment() < 0) {

            // Here is where we look for the reimbursment pay type.
            $paymentManager->pmp->setRtnPayType($paymentManager->pmp->getPayType());
            $paymentManager->pmp->setRtnCheckNumber($paymentManager->pmp->getCheckNumber());
            $paymentManager->pmp->setRtnTransferAcct($paymentManager->pmp->getTransferAcct());
            $paymentManager->pmp->setBalWith(ExcessPay::Refund);
        }

        // Make payment
        $payResult = self::processPayments($dbh, $paymentManager, NULL, $postbackPage, $idPayor);

        if (is_null($payResult) === FALSE) {

            $reply = $payResult->getReplyMessage();

            if ($payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $payResult->getForwardHostedPayment();
            }

            // Receipt
            if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());
            }

            // New Invoice
            if (is_null($payResult->getInvoiceMarkup()) === FALSE && $payResult->getInvoiceMarkup() != '') {
                $dataArray['invoice'] = HTMLContainer::generateMarkup('div', $payResult->getInvoiceMarkup());
            }
        }

        // divert to credit payment site.
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        }

        $dataArray['success'] = $reply;

        return $dataArray;
    }

    public static function saveHousePayment(\PDO $dbh, $idItem, $ord, $amt, $discount, $addnlCharge, $adjDate, $notes) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($ord == 0 || ($discount == '' && $addnlCharge == '')) {
            return $dataArray;
        }

        $visit = new Visit($dbh, 0, $ord);
        $amount = floatval($amt);
        $invoice = NULL;

        if ($adjDate != '') {
            $invDate = date('Y-m-d H:i:s', strtotime($adjDate));
        } else {
            $invDate = date('Y-m-d H:i:s');
        }


        if ($idItem == ItemId::Discount) {

            $codes = readGenLookupsPDO($dbh, 'House_Discount');

            if (isset($codes[$discount])) {

                $amount = 0 - $amount;

                $invLine = new OneTimeInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::Discount, $amount), 1, $codes[$discount][1]);
                $invoice = new Invoice($dbh);

                $invoice->newInvoice(
                    $dbh,
                    0,
                    $uS->subsidyId,
                    $visit->getIdRegistration(),
                    $visit->getIdVisit(),
                    $visit->getSpan(),
                    $notes,
                    $invDate,
                    $uS->username);

                $invoice->addLine($dbh, $invLine, $uS->username);

                // Pay the invoice
                $invoice->updateInvoiceBalance($dbh, $amount, $uS->username);

                $dataArray['reply'] = $codes[$discount][1] . ' Discount Applied.  ';

            } else {
                $dataArray['reply'] = 'Discount code not found: ' . $discount;
            }

        }

        if ($idItem == ItemId::AddnlCharge) {

            $codes = readGenLookupsPDO($dbh, 'Addnl_Charge');

            if (isset($codes[$addnlCharge])) {

                $invLine = new OneTimeInvoiceLine();
                $invLine->createNewLine(new Item($dbh, ItemId::AddnlCharge, $amount), 1, $codes[$addnlCharge][1]);

                if (is_null($invoice)) {
                    $invoice = new Invoice($dbh);

                    $invoice->newInvoice(
                        $dbh,
                        0,
                        $visit->getPrimaryGuestId(),
                        $visit->getIdRegistration(),
                        $visit->getIdVisit(),
                        $visit->getSpan(),
                        $notes,
                        $invDate,
                        $uS->username);

                }

                $invoice->addLine($dbh, $invLine, $uS->username);
                $invoice->updateInvoiceStatus($dbh, $uS->username);

                if ($invoice->getAmount() == 0) {

                    // We can pay it now and return a receipt.
                    $paymentManager = new PaymentManager(new PaymentManagerPayment(PayType::Cash));
                    $paymentManager->setInvoice($invoice);
                    $payResult = $paymentManager->makeHousePayment($dbh, '', $invDate);

                    if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                        $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());
                        $dataArray['reply'] = $codes[$addnlCharge][1] . ': item recorded. ';
                    }

                } else {
                    $dataArray['reply'] = $codes[$addnlCharge][1] . ' additional charge is invoiced. ';
                }

            } else {
                $dataArray['reply'] = 'Additional Charge code not found: ' . $addnlCharge;
            }
        }

        return $dataArray;
    }

    public static function processPayments(\PDO $dbh, PaymentManager $paymentManager, $visit, $postbackPage, $idPayor) {

        $uS = Session::getInstance();
        $payResult = NULL;

        if (is_null($paymentManager->pmp)) {
            return $payResult;
        }


        // Payments - setup
        if (is_null($visit) === FALSE) {
            $paymentManager->pmp->setPriceModel(PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel));
            $paymentManager->pmp->priceModel->setCreditDays($visit->getRateGlideCredit());
            $paymentManager->pmp->setVisitCharges(new VisitCharges($visit->getIdVisit()));
        }


        if ($paymentManager->pmp->getPayType() == PayType::Invoice) {
            $idPayor = $paymentManager->pmp->getIdInvoicePayor();
        }

        // Create Invoice.
        $invoice = $paymentManager->createInvoice($dbh, $visit, $idPayor, $paymentManager->pmp->getInvoiceNotes());

        if (is_null($invoice) === FALSE && $invoice->getStatus() == InvoiceStatus::Unpaid) {

            if ($invoice->getAmountToPay() >= 0) {
                // Make guest payment
                $payResult = $paymentManager->makeHousePayment($dbh, $postbackPage, $paymentManager->pmp->getPayDate());

            } else if ($invoice->getAmountToPay() < 0) {
                // Make guest return
                $payResult = $paymentManager->makeHouseReturn($dbh, $paymentManager->pmp->getPayDate());
            }
        }

        return $payResult;

    }

    public static function undoRoomChange(\PDO $dbh, Visit $visit, $uname) {

        // Reservation
        $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId(), $visit->getIdVisit());

        if ($resv->isNew() === TRUE) {
            return '';
        }

        // Get next visit
        $nextVisitRs = new VisitRs();
        $nextVisitRs->idVisit->setStoredVal($visit->getIdVisit());
        $nextVisitRs->Span->setStoredVal($visit->getSpan() + 1);
        $vRows = EditRS::select($dbh, $nextVisitRs, array($nextVisitRs->idVisit, $nextVisitRs->Span));

        if (count($vRows) != 1) {
            return 'Next Visit Span not found.  ';
        }

        EditRS::loadRow($vRows[0], $nextVisitRs);

        // Next visit must be the last.
        if ($nextVisitRs->Status->getStoredVal() != VisitStatus::CheckedIn && $nextVisitRs->Status->getStoredVal() != VisitStatus::CheckedOut) {
            return 'Next Visit Span must be Checked-in or Checked-out.  ';
        }

        // Dates
        if ($nextVisitRs->Span_End->getStoredVal() == '') {

            $expDepDT = new \DateTime($nextVisitRs->Expected_Departure->getStoredVal());
            $now = new \DateTime();

            if ($now > $expDepDT) {
                $expDepDT = $now;
            }

        } else {
            $expDepDT = new \DateTime($nextVisitRs->Span_End->getStoredVal());
        }

        $startDt = date('Y-m-d 23:59:59', strtotime($visit->getSpanStart()));


        // Number of occupants

        // Load ALL of next visit's stays
        $nextStays = Visit::loadStaysStatic($dbh, $nextVisitRs->idVisit->getStoredVal(), $nextVisitRs->Span->getStoredVal(), VisitStatus::CheckedIn);

        // Load this visit's stays that were still checked in.
        $stays = Visit::loadStaysStatic($dbh, $visit->getIdVisit(), $visit->getSpan(), VisitStatus::NewSpan);

        $numOccupants = max( array(count($nextStays), count($visit->stays)) );

        // Check room availability
        if ($resv->isResourceOpen($dbh, $visit->getidResource(), $startDt, $expDepDT->format('Y-m-d 01:00:00'), $numOccupants, array('room', 'rmtroom', 'part'), TRUE, TRUE) === FALSE) {
            return 'The room is unavailable. ';
        }

        // Get new room cleaning status to copy to original room
        $resc = Resource::getResourceObj($dbh, $nextVisitRs->idResource->getStoredVal());
        $rooms = $resc->getRooms();
        $room = array_shift($rooms);
        $roomStatus = $room->getStatus();

        // Transaction
        //$result = $dbh->exec("Begin Trans;");

        // Undo visit checkout
        $visit->visitRS->Actual_Departure->setNewVal($nextVisitRs->Actual_Departure->getStoredVal());
        $visit->visitRS->Span_End->setNewVal($nextVisitRs->Span_End->getStoredVal());
        $visit->visitRS->Expected_Departure->setNewVal($expDepDT->format('Y-m-d 10:00:00'));
        $visit->visitRS->Status->setNewVal($nextVisitRs->Status->getStoredVal());
        $visit->visitRS->Key_Dep_Disposition->setNewVal($nextVisitRs->Key_Dep_Disposition->getStoredVal());
        $updateCounter = $visit->updateVisitRecord($dbh, $uname);

        if ($updateCounter != 1) {
            throw new Hk_Exception_Runtime('Visit table update failed. Checkout is not undone.');
        }

        // update Reservation
        $resv->setIdResource($visit->getidResource());
        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);

        // remove the next visit
        EditRS::delete($dbh, $nextVisitRs, array($nextVisitRs->idVisit, $nextVisitRs->Span));
        $logDelText = VisitLog::getDeleteText($nextVisitRs, $nextVisitRs->idVisit->getStoredVal());
        VisitLog::logVisit($dbh, $nextVisitRs->idVisit->getStoredVal(), $nextVisitRs->Span->getStoredVal(), $nextVisitRs->idResource->getStoredVal(), $nextVisitRs->idRegistration->getStoredVal(), $logDelText, "delete", $uname);


        // original stays with status = changeRoom.
        $visit->loadStays($dbh, VisitStatus::NewSpan);
        foreach ($visit->stays as $s) {

            // Check status in next stay
            foreach ($nextStays as $ns) {

                if ($s->idName->getStoredVal() == $ns->idName->getStoredVal()
                        && date('Y-m-d', strtotime($s->Span_End_Date->getStoredVal())) == date('Y-m-d', strtotime($ns->Span_Start_Date->getStoredVal()))) {

                    $s->Checkout_Date->setNewVal($ns->Checkout_Date->getStoredVal());
                    $s->Span_End_Date->setNewVal($ns->Span_End_Date->getStoredVal());
                    $s->Status->setNewVal($ns->Status->getStoredVal());
                    $s->Expected_Co_Date->setNewVal($ns->Expected_Co_Date->getStoredVal());
                    $s->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                    $s->Updated_By->setNewVal($uname);

                    $cnt = EditRS::update($dbh, $s, array($s->idStays));
                    if ($cnt > 0) {
                        $logText = VisitLog::getUpdateText($s);
                        EditRS::updateStoredVals($s);
                        VisitLog::logStay($dbh, $s->idVisit->getStoredVal(), $s->Visit_Span->getStoredVal(), $s->idRoom->getStoredVal(), $s->idStays->getStoredVal(), $s->idName->getStoredVal(), $visit->getIdRegistration(), $logText, "update", $uname);
                    }

                    break;
                }
            }
        }


        // Next stays that are not in original stays
        foreach ($nextStays as $ns) {

            // remove the known stays
            foreach ($stays as $s) {

                if ($s->idName->getStoredVal() == $ns->idName->getStoredVal()
                        && date('Y-m-d', strtotime($s->Span_End_Date->getStoredVal())) == date('Y-m-d', strtotime($ns->Span_Start_Date->getStoredVal()))) {

                    EditRS::delete($dbh, $ns, array($ns->idStays));
                    continue 2;
                }
            }

            // just change the span.
            $ns->Visit_Span->setNewVal($visit->getSpan());

            $ns->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $ns->Updated_By->setNewVal($uname);

            $cnt = EditRS::update($dbh, $ns, array($ns->idStays));
            if ($cnt > 0) {
                $logText = VisitLog::getUpdateText($ns);
                EditRS::updateStoredVals($ns);
                VisitLog::logStay($dbh, $ns->idVisit->getStoredVal(), $ns->Visit_Span->getStoredVal(), $ns->idRoom->getStoredVal(), $ns->idStays->getStoredVal(), $ns->idName->getStoredVal(), $visit->getIdRegistration(), $logText, "update", $uname);
            }

        }

        // Update room cleaning status of this room
        $resc2 = Resource::getResourceObj($dbh, $visit->getidResource());
        $rooms2 = $resc2->getRooms();
        $room2 = array_shift($rooms2);
        $room2->setStatus($roomStatus);
        $room2->saveRoom($dbh, $uname, TRUE, $roomStatus);

        // Update invoices
        $dbh->exec("Update invoice set Suborder_Number = " . $visit->getSpan() . " where Order_Number = " . $visit->getIdVisit() . " and Suborder_Number != ". $visit->getSpan());

        return "Change Rooms is undone.  ";

    }

    public static function undoCheckout(\PDO $dbh, Visit $visit, \DateTime $newExpectedDT, $uname) {

        $reply = '';

        if ($visit->getVisitStatus() != VisitStatus::CheckedOut) {
            return 'Cannot undo checkout, visit continues in another room or at another rate.  ';
        }

        // only allow 15 days to undo the checkout
        $actDeptDT = new \DateTime($visit->getActualDeparture());

//        $fulcrumDT = new \DateTime();
//        $fulcrumDT->sub(new \DateInterval('P15D'));
//
//        if ($actDeptDT < $fulcrumDT) {
//            $reply .= 'Cannot undo a checkout after 15 days.  ';
//        }


        $resv = Reservation_1::instantiateFromIdReserv($dbh, $visit->getReservationId(), $visit->getIdVisit());


        $startDT = new \DateTime($visit->getSpanStart());
        $startDT->setTime(23, 59, 59);


        // Check room availability
        $availResc = $resv->isResourceOpen($dbh, $visit->getidResource(), $startDT->format('Y-m-d H:i:s'), $newExpectedDT->format('Y-m-d 01:00:00'), 1, array('room', 'rmtroom', 'part'), TRUE, TRUE);

        if ($availResc === FALSE) {
            $reply .= 'Cannot undo checkout, the room is not available.  ';
            return $reply;
        }

        $idPsg = $resv->getIdPsg($dbh);

        // Check for pending reservations
        $resvs = ReservationSvcs::getCurrentReservations($dbh, $resv->getIdReservation(), 0, $idPsg, $startDT, $newExpectedDT);

        foreach ($resvs as $rv) {

            // another concurrent reservation already there
            if ($rv['idPsg'] == $idPsg) {

                $type = 'Reservaion';

                if ($rv['Status'] == ReservationStatus::Staying) {
                    $type = 'Visit';
                }

                $reply .=  "Cannot undo checkout, this family has a conflicting $type.  ";
                return $reply;
            }
        }



        // Undo reservation termination
        $resv->setActualDeparture('');
        $resv->setExpectedDeparture($newExpectedDT->format('Y-m-d 10:00:00'));
        $resv->setStatus(ReservationStatus::Staying);

        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);


        // Undo visit checkout
        $visit->visitRS->Actual_Departure->setNewVal('');
        $visit->visitRS->Span_End->setNewVal('');
        $visit->visitRS->Expected_Departure->setNewVal($newExpectedDT->format('Y-m-d 10:00:00'));
        $visit->visitRS->Status->setNewVal(VisitStatus::CheckedIn);
        $visit->visitRS->Key_Dep_Disposition->setNewVal('');
        $visit->visitRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $visit->visitRS->Updated_By->setNewVal($uname);

        $updateCounter = EditRS::update($dbh, $visit->visitRS, array($visit->visitRS->idVisit, $visit->visitRS->Span));

        if ($updateCounter != 1) {
            throw new Hk_Exception_Runtime('Visit table update failed. Checkout is not undone.');
        }

        $logText = VisitLog::getUpdateText($visit->visitRS);
        EditRS::updateStoredVals($visit->visitRS);
        VisitLog::logVisit($dbh, $visit->getIdVisit(), $visit->visitRS->Span->getStoredVal(), $visit->visitRS->idResource->getStoredVal(), $visit->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);
        $reply .= "Checkout is undone.  ";

        // Update stays
        $visit->loadStays($dbh, VisitStatus::CheckedOut);
        foreach ($visit->stays as $s) {

            if (date('Y-m-d', strtotime($s->Span_End_Date->getStoredVal())) == $actDeptDT->format('Y-m-d')) {

                // Undo stay checkout
                $s->Checkout_Date->setNewVal('');
                $s->Span_End_Date->setNewVal('');
                $s->Status->setNewVal(VisitStatus::CheckedIn);
                $s->Expected_Co_Date->setNewVal($newExpectedDT->format('Y-m-d 10:00:00'));

                // cancel on-leave
                $s->On_Leave->setNewVal(0);

                $s->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $s->Updated_By->setNewVal($uname);

                $cnt = EditRS::update($dbh, $s, array($s->idStays, $s->Visit_Span));
                if ($cnt > 0) {
                    $logText = VisitLog::getUpdateText($s);
                    EditRS::updateStoredVals($s);
                    VisitLog::logStay($dbh, $s->idVisit->getStoredVal(), $s->Visit_Span->getStoredVal(), $s->idRoom->getStoredVal(), $s->idStays->getStoredVal(), $s->idName->getStoredVal(), $visit->getIdRegistration(), $logText, "update", $uname);
                }
            }
        }


        // Update room cleaning status of this room
        $resc2 = Resource::getResourceObj($dbh, $visit->getidResource());
        $rooms2 = $resc2->getRooms();
        $room2 = array_shift($rooms2);
        $room2->setStatus(RoomState::Clean);
        $room2->saveRoom($dbh, $uname, TRUE, RoomState::Clean);

        return $reply;
    }

    public static function addVisitStay(\PDO $dbh, $idVisit, $visitSpan, $idGuest, $post) {

        $uS = Session::getInstance();
        $dataArray = array();
        $prefix = 'q';

        if ($idVisit < 1 || $visitSpan < 0) {
            return array("error" => "Visit not selected.  ");
        }

        $visitRs = new VisitRs();
        $visitRs->idVisit->setStoredVal($idVisit);
        $visitRs->Span->setStoredVal($visitSpan);

        $visits = EditRS::select($dbh, $visitRs, array($visitRs->idVisit, $visitRs->Span));

        if (count($visits) != 1) {
            return array("error" => "Visit not found.  ");
        }

        EditRS::loadRow($visits[0], $visitRs);

        $guest = new Guest($dbh, $prefix, $idGuest);


        // Arrival Date
        $arrDate = new \DateTime($visitRs->Span_Start->getStoredVal());

        // Departure Date
        if ($visitRs->Span_End->getStoredVal() != '') {
            $depDate = new \DateTime($visitRs->Span_End->getStoredVal());
        } else {
            $depDate = new \DateTime($visitRs->Expected_Departure->getStoredVal());
            $today = new \DateTime();

            if ($depDate < $today) {
                $depDate = $today;
            }
        }

        if ($arrDate >= $depDate) {
            return array("error" => "Visit Dates not suitable.  arrive: " . $arrDate->format('Y-m-d H:i:s') . ", depart: " . $depDate->format('Y-m-d H:i:s'));
        }

        $reg = new Registration($dbh, 0, $visitRs->idRegistration->getStoredVal());
        $psg = new Psg($dbh, $reg->getIdPsg());

        if (isset($post[$prefix.'txtLastName'])) {

            // Get labels
            $labels = new Config_Lite(LABEL_FILE);

            // save the guest
            $guest->save($dbh, $post, $uS->username);
            $nameObj = $guest->getNameObj();

            // Attach to PSG if not
            if (isset($psg->psgMembers[$guest->getIdName()]) === FALSE) {
                $psg->setNewMember($guest->getIdName(), $guest->getPatientRelationshipCode());
                $psg->savePSG($dbh, $psg->getIdPatient(), $uS->username);
            }

            // Get the resource
            $resource = null;
            if ($visitRs->idResource->getStoredVal() > 0) {
                $resource = Resource::getResourceObj($dbh, $visitRs->idResource->getStoredVal());
            } else {
                return array('error' => 'Room not found.  ');
            }

            // Verify dates
            $ckinDT = $guest->getCheckinDT();
            $ckinDate = $ckinDT->format('Y-m-d H:m:s');
            $ckinDT->setTime(0,0,0);

            $ckoutDT = $guest->getExpectedCheckOutDT();
            $ckoutDT->setTime(0,0,0);

            if ($ckinDT < $arrDate || $ckinDT > $depDate) {
                $ckinDT = $arrDate;
            }

            if ($ckoutDT <= $ckinDT || $ckoutDT > $depDate) {
                $ckoutDT = $depDate;
            }


            // get stays
            $staysRs = new StaysRS();

            $staysRs->idVisit->setStoredVal($idVisit);
            $staysRs->Visit_Span->setStoredVal($visitSpan);
            $existingStays = EditRS::select($dbh, $staysRs, array($staysRs->idVisit, $staysRs->Visit_Span));

            $rooms = $resource->getRooms();
            $numGuests = 0;

            foreach ($existingStays as $s) {
                $sRs = new StaysRS();
                EditRS::loadRow($s, $sRs);

                // Only count rooms assigned to the resoource of the visit.
                if (array_key_exists($sRs->idRoom->getStoredVal(), $rooms)) {

                    // Only during the dates of the new stay
                    $stayStrt = new \DateTime($sRs->Span_Start_Date->getStoredVal());
                    $stayStrt->setTime(0,0,0);

                    if ($sRs->Span_End_Date->getStoredVal() != '') {
                        $stayEnd = new \DateTime($sRs->Span_End_Date->getStoredVal());
                    } else {
                        $stayEnd = new \DateTime($sRs->Expected_Co_Date->getStoredVal());
                        $today = new \DateTime();
                        if ($stayEnd < $today) {
                            $stayEnd = $today;
                        }
                    }

                    $stayEnd->setTime(0, 0, 0);

                    if ($ckinDT < $stayEnd && $ckoutDT > $stayStrt) {
                        // This person is staying.

                        if ($guest->getIdName() == $sRs->idName->getStoredVal()) {
                            return array('error' => $nameObj->get_fullName() . ' is already staying during a part of the indicated check-in and check-out dates.  ');
                        }

                        $numGuests++;
                    }
                }
            }

            if ($numGuests >= $resource->getMaxOccupants()) {
                return array("error" => "Room is full during a part of the indicated check-in and check-out dates.  ");
            }


            $ckoutDate = $ckoutDT->format('Y-m-d 10:00:00');
            $room = reset($rooms);

            // is the guest somewhere else in the house?
            $stmt = $dbh->query("Select count(idName) from stays "
                    . "where idName = " . $guest->getIdName() . " and DATEDIFF(ifnull(Span_End_Date, Expected_Co_Date), Span_Start_Date) != 0 "
                    . " and DATE('" . $ckinDT->format('Y-m-d H:m:s') . "') < ifnull(DATE(Span_End_Date), DATE(Expected_Co_Date)) and DATE('$ckoutDate') > DATE(Span_Start_Date)");
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (isset($rows[0][0]) && $rows[0][0] > 0) {
                return array('error' => $nameObj->get_fullName() . ' is already included in a different visit.  ');
            }

            // Add the stay
            $stayRS = new StaysRS();

            $stayRS->idName->setNewVal($guest->getIdName());
            $stayRS->idRoom->setNewVal($room->getIdRoom());
            $stayRS->Checkin_Date->setNewVal($ckinDate);
            $stayRS->Expected_Co_Date->setNewVal($ckoutDate);
            $stayRS->Span_Start_Date->setNewVal($ckinDate);

            if ($visitRs->Status->getStoredVal() != VisitStatus::CheckedIn) {

                $stayRS->Checkout_Date->setNewVal($ckoutDate);
                $stayRS->Span_End_Date->setNewVal($ckoutDate);
            }

            $stayRS->Status->setNewVal($visitRs->Status->getStoredVal());
            $stayRS->idVisit->setNewVal($idVisit);
            $stayRS->Visit_Span->setNewVal($visitSpan);
            $stayRS->Updated_By->setNewVal($uS->username);
            $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

            $idStays = EditRS::insert($dbh, $stayRS);
            $stayRS->idStays->setNewVal($idStays);

            $logText = VisitLog::getInsertText($stayRS);
            VisitLog::logStay($dbh, $idVisit, $visitSpan, $stayRS->idRoom->getNewVal(), $idStays, $guest->getIdName(), $visitRs->idRegistration->getStoredVal(), $logText, "insert", $uS->username);

            $dataArray['stays'] = VisitView::createStaysMarkup($dbh, $idVisit, $visitSpan, $visitRs->idPrimaryGuest->getStoredVal(), FALSE, $guest->getIdName(), $labels);

        } else {
            // send back a guest dialog to collect name, address, etc.

            if ($depDate <= $arrDate) {
                $depDateStr = '';
            } else {
                $depDateStr = $depDate->format('M j, Y');
            }

            $guest->setCheckinDate($arrDate->format('M j, Y'));
            $guest->setExpectedCheckOut($depDateStr);

            if (isset($psg->psgMembers[$guest->getIdName()])) {
                $guest->setPatientRelationshipCode($psg->psgMembers[$guest->getIdName()]->Relationship_Code->getStoredVal());
            }

            $dataArray['addtguest'] = $guest->createMarkup($dbh);
            $dataArray['addr'] = self::createAddrObj($dbh, $visitRs->idPrimaryGuest->getStoredVal());
        }

        return $dataArray;
    }

    public static function createAddrObj(\PDO $dbh, $idName) {

        $guest = new Guest($dbh, '', $idName);
        $addrObj = $guest->getAddrObj();
        $addr = $addrObj->get_Data(Address_Purpose::Home);

        $adrArray = array(
            'adraddress1' => $addr['Address_1'],
            'adraddress2' => $addr['Address_2'],
            'adrcity' => $addr['City'],
            'adrcounty' => $addr['County'],
            'adrstate' => $addr['State_Province'],
            'adrcountry' => $addr['Country_Code'],
            'adrzip' => $addr['Postal_Code']
        );

        return $adrArray;
    }

    /**
     * This credit card viewer does not take any money.
     * Just show credit cards on file
     *
     * @param PDO $dbh
     * @param integer $idRegistration
     * @param integer $idGuest
     * @return string
     */
    public static function viewCreditTable(\PDO $dbh, $idRegistration, $idGuest) {

        $tkRsArray = CreditToken::getRegTokenRSs($dbh, $idRegistration, $idGuest);

        $tblPayment = new HTMLTable();
        $tblPayment->addHeaderTr(HTMLTable::makeTh("Credit Card on File", array('colspan' => '4')));

        if (count($tkRsArray) > 0) {
            $tblPayment->addBodyTr(HTMLTable::makeTh("Type") . HTMLTable::makeTh("Account") . HTMLTable::makeTh("Name") . HTMLTable::makeTh("Delete"));
        }

        foreach ($tkRsArray as $tkRs) {

            if (CreditToken::hasToken($tkRs)) {

                $attr = array('type' => 'checkbox', 'class'=>'ignrSave', 'name' => 'crdel_' . $tkRs->idGuest_token->getStoredVal());

                $tblPayment->addBodyTr(
                        HTMLTable::makeTd($tkRs->CardType->getStoredVal())
                        . HTMLTable::makeTd($tkRs->MaskedAccount->getStoredVal())
                        . HTMLTable::makeTd($tkRs->CardHolderName->getStoredVal())
                        . HTMLTable::makeTd(
                                HTMLInput::generateMarkup($tkRs->idGuest_token->getStoredVal(), $attr), array('style' => 'text-align:center;'))
                );
            }
        }

        $attr = array('type' => 'checkbox', 'name' => 'cbNewCard', 'class'=>'ignrSave', 'style' => 'margin-right:4px;');

        $tblPayment->addBodyTr(
                HTMLTable::makeTd(
                        HTMLInput::generateMarkup('', $attr)
                        . HTMLContainer::generateMarkup('label', 'Put a new card on file', array('for' => 'cbNewCard')), array('colspan' => '4'))
        );

        return $tblPayment->generateMarkup(array('id' => 'tblupCredit'));
    }

    /**
     * Return from View Credit Table
     *
     * @param PDO $dbh
     * @param integer $idGuest
     * @param integer $idGroup
     * @param array $post
     * @param URL $postBackPage
     * @return array
     */
    public static function cardOnFile(\PDO $dbh, $idGuest, $idGroup, $post, $postBackPage) {
        // Credit card processing
        $uS = Session::getInstance();

        $dataArray = array();

        if ($uS->ccgw == '') {
            return $dataArray;
        }


        // Delete any tokens
        $keys = array_keys($post);
        $msg = '';

        foreach ($keys as $k) {

            $parts = explode('_', $k);
            if (count($parts) > 1 && $parts[0] == 'crdel') {

                $idGt = intval(filter_var($parts[1], FILTER_SANITIZE_NUMBER_INT), 10);

                if ($idGt > 0) {

                    $cnt = $dbh->exec("update guest_token set Token = '' where idGuest_token = " . $idGt);

                    if ($cnt > 0) {
                        $gtRs = CreditToken::getTokenRsFromId($dbh, $idGt);
                        $msg .= 'Card ' . $gtRs->MaskedAccount->getStoredVal() . ', Name ' . $gtRs->CardHolderName->getStoredVal() . ' deleted.  ';
                    }

                }
            }
        }

        // Add a new card
        if (isset($post['cbNewCard'])) {

            try {

                $dataArray = PaymentSvcs::initCardOnFile($dbh, $uS->ccgw, $uS->siteName, $idGuest, $idGroup, '', $postBackPage);

            } catch (Hk_Exception_Payment $ex) {

                $dataArray['error'] = $ex->getMessage();
            }
        }

        if ($msg != '') {
            $dataArray['success'] = $msg;
        }

        return $dataArray;
    }

    public static function changeExpectedDepartureDate(\PDO $dbh, $idGuest, $idVisit, $newDate) {

        if ($newDate == '' || $idGuest < 1 || $idVisit < 1) {
            return array('error' => 'Parameters not specified.   ');
        }

        $visit = new Visit($dbh, 0, $idVisit);
        $guestDates[$idGuest] = $newDate;
        $uS = Session::getInstance();

        $result = $visit->changeExpectedCheckoutDates($dbh, $guestDates, $uS->MaxExpected, $uS->username);

        return array('success' => $result['message'], 'isChanged' => $result['isChanged']);
    }

    /** Move a visit temporally by so many days
     *
     * @param PDO $dbh
     * @param int $idVisit
     * @param int $dayDelta
     * @return array
     */
    public static function moveVisit(\PDO $dbh, $idVisit, $span, $startDelta, $endDelta) {

        $uS = Session::getInstance();
        $dataArray = array();

        if ($idVisit == 0) {
            return array("error" => "Visit not specified.");
        }

        if (ComponentAuthClass::is_Authorized('guestadmin') === FALSE) {
            return array("error" => "User not authorized to move visits.");
        }

        // save the visit info
        $reply = VisitView::moveVisit($dbh, $idVisit, $span, $startDelta, $endDelta, $uS->username);

        if ($reply === FALSE) {

            $reply = 'Warning:  Visit not moved.';

        } else {

            // Return checked in guests markup?
            $dataArray['curres'] = 'y';
        }

        $dataArray['success'] = $reply;

        return $dataArray;
    }

    /**
     * Verify visit input dates
     *
     * @param \Reservation_1 $resv
     * @param \DateTime $chkinDT
     * @param \DateTime $chkoutDT
     * @param bool $autoResv
     * @throws Hk_Exception_Runtime
     */
    public static function verifyVisitDates(\Reservation_1 $resv, \DateTime $chkinDT, \DateTime $chkoutDT, $autoResv = FALSE) {

        $uS = Session::getInstance();

        $rCkinDT = new \DateTime(($resv->getActualArrival() == '' ? $resv->getExpectedArrival() : $resv->getActualArrival()));
        $rCkinDT->setTime(0, 0, 0);
        $rCkoutDT = new \DateTime($resv->getExpectedDeparture());
        $rCkoutDT->setTime(23, 59, 59);

        if ($resv->getStatus() == ReservationStatus::Committed && $rCkinDT->diff($chkinDT)->days > $uS->ResvEarlyArrDays) {
            throw new Hk_Exception_Runtime('Cannot check-in earlier than ' . $uS->ResvEarlyArrDays . ' days of the reservation expected arrival date of ' . $rCkinDT->format('M d, Y'));
        } else if ($resv->getStatus() == ReservationStatus::Committed && $chkoutDT > $rCkoutDT && $autoResv === FALSE) {
            throw new Hk_Exception_Runtime('Cannot check-out later than the reservation expected departure date of ' . $rCkoutDT->format('M d, Y'));
        }

        if ($chkinDT >= $chkoutDT) {
            throw new Hk_Exception_Runtime('A check-in date cannot be AFTER the check-out date.  (Silly Human)  ');
        }
    }

    /**
     * Veriy Stay dates, check for a continuous visit.
     *
     * @param array $guests
     * @param \DateTime $chkinDT
     * @param \DateTime $chkoutDT
     * @throws Hk_Exception_Runtime
     */
    public static function verifyStayDates(array $guests, \DateTime $chkinDT, \DateTime $chkoutDT) {

        if (count($guests == 0)) {
            return;
        }

        $days = array();
        $p1d = new \DateInterval('P1D');

        foreach ($guests as $guest) {

            if ($guest->getCheckinDT() > $guest->getExpectedCheckOutDT()) {
                throw new Hk_Exception_Runtime('A check-in date cannot be AFTER the check-out date.  ');
            }

            $trackerDT = new \DateTime($guest->getCheckinDT()->format('Y-m-d H:i:s'));

            // Mark the days at the house
            while ($trackerDT <= $guest->getExpectedCheckOutDT()) {

                $today = $trackerDT->format('Y-m-d');
                if (isset($days[$today])) {
                    $days[$today] ++;
                } else {
                    $days[$today] = 1;
                }
                $trackerDT->add($p1d);
            }
        }

        // Continous Visit?
        $trackerDT = new \DateTime($chkinDT->format('Y-m-d H:i:s'));
        $mostGuests = 1;
        $mostGuestStartDT = new \DateTime($chkinDT->format('Y-m-d H:i:s'));

        while ($trackerDT <= $chkoutDT) {

            $today = $trackerDT->format('Y-m-d');
            if (isset($days[$today]) === FALSE) {
                throw new Hk_Exception_Runtime('Non-continuous visit - At least one guest must be resident each day of the visit. ');
            }

            if ($days[$today] > $mostGuests) {
                $mostGuests++;
                $mostGuestStartDT = new \DateTime($today);
            }

            $trackerDT->add($p1d);
        }
    }

    /**
     * Check-in guests main logic
     *
     * @param \PDO $dbh
     * @param array $post
     * @param bool $isAuthorized
     * @return array of new markup and variables for post check-in processing
     * @throws Hk_Exception_Runtime
     */
    public static function saveCheckinPage(\PDO $dbh, $post, $isAuthorized = FALSE) {

        $uS = Session::getInstance();
        $dataArray = array();
        $dataArray['warning'] = '';
        $reply = '';
        $creditCheckOut = array();


        //
        // Reservation
        //
        $idReserv = 0;
        if (isset($post['rid'])) {
            $idReserv = intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($idReserv == 0) {
            throw new Hk_Exception_Runtime('Check-in missing reservation id.  ');
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

        if (($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::Waitlist || $resv->getStatus() == ReservationStatus::Staying || $resv->getStatus() == ReservationStatus::Imediate) === FALSE) {
            throw new Hk_Exception_Runtime('Reservation Status is wrong - ' . $resv->getStatusTitle());
        }


        //
        // Save members
        //
        $chkinGroup = new CheckInGroup();
        $chkinGroup->saveMembers($dbh, $resv->getIdHospitalStay(), $post);

        if (is_null($chkinGroup->patient)) {
            // Get labels
            $labels = new Config_Lite(LABEL_FILE);

            throw new Hk_Exception_Runtime($labels->getString('MemberType', 'patient', 'Patient') . ' not specified.  ');
        }

        // any new guests?
        if (count($chkinGroup->newGuests) == 0) {
            throw new Hk_Exception_Runtime('All guests are already checked in.  ');
        }


        //
        // verify/save psg
        //
        $psg = $chkinGroup->savePsg($dbh, '', $uS->username);



        //
        // dates
        //
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // Visit dates
        if (isset($post['ckindt']) && isset($post['ckoutdt'])) {

            try {
                $chkinDT = setTimeZone($uS, filter_var($post['ckindt'], FILTER_SANITIZE_STRING));
                $chkoutDT = setTimeZone($uS, filter_var($post['ckoutdt'], FILTER_SANITIZE_STRING));

                // Edit checkin date for later hour of checkin if posting the check in late.
                $tCkinDT = new \DateTime($chkinDT->format('Y-m-d 00:00:00'));

                if ($chkinDT->format('H') < 16 && $today > $tCkinDT) {
                    $chkinDT->setTime(16,0,0);
                }

                self::verifyVisitDates($resv, $chkinDT, $chkoutDT, $uS->OpenCheckin);

                // Stay dates
                self::verifyStayDates($chkinGroup->newGuests, $chkinDT, $chkoutDT);

            } catch (Exception $ex) {
                throw new Hk_Exception_Runtime('Bad dates:  ' . $ex->getMessage());
            }

        } else {
            throw new Hk_Exception_Runtime('Check-in and/or check-out dates are missing!  ');
        }

        //
        // Hospital
        //
        $hospitalStay = new HospitalStay($dbh, $psg->getIdPatient());
        Hospital::saveHospitalMarkup($dbh, $psg, $hospitalStay, $post);


        //
        // Room
        //

        // Find selected resource
        $newRescId = $resv->getIdResource();

        if (isset($post['selResource'])) {
            $newRescId = intval(filter_var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        // Is resource specified?
        if ($newRescId == 0) {
            return array("error" => 'A room was not specified.');
        }

        $resources = $resv->findGradedResources($dbh, $chkinDT->format('Y-m-d'), $chkoutDT->format('Y-m-d'), 1, array('room', 'rmtroom', 'part'), TRUE);


        // Does the resource still fit the requirements?
        if (isset($resources[$newRescId]) === FALSE) {
            return array("error" => 'The room is busy.');
        }

        // Get our room.
        $resc = $resources[$newRescId];
        unset($resources);

        // Only admins can pick an unsuitable room.
        if ($isAuthorized === FALSE && $resc->optGroup != '') {
            return array("error" => 'Room ' . $resc->getTitle() . " is " . $resc->optGroup);
        }


        //
        if ($resv->getStatus() == ReservationStatus::Staying) {
            $numOccupants = $resc->getCurrantOccupants($dbh) + count($chkinGroup->newGuests);
        } else {
            $numOccupants = count($chkinGroup->newGuests);
        }

        if ($numOccupants > $resc->getMaxOccupants()) {
            return array("error" => "The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
        }

        //
        // Registration
        //
        $reg = new Registration($dbh, $psg->getIdPsg());

        if ($uS->TrackAuto) {
            $reg->extractVehicleFlag($post);
        }

        // Save registration
        $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), $uS->username);

        // Save any vehicles
        if ($uS->TrackAuto && $reg->getNoVehicle() == 0) {
            Vehicle::saveVehicle($dbh, $post, $reg->getIdRegistration());
        }


        $idVisit = -1;
        $stmt = $dbh->query("Select idVisit from visit where idReservation = " . $resv->getIdReservation() . " limit 1;");

        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            $idVisit = $rows[0][0];
        }

        // create visit
        $visit = new Visit($dbh, $reg->getIdRegistration(), $idVisit, $chkinDT, $chkoutDT, $resc, $uS->username);


        // Add guests
        foreach ($chkinGroup->newGuests as $g) {

            $visit->addGuestStay($g->getIdName(), $g->getCheckinDate(), $g->getCheckinDate(), $g->getExpectedCheckOut());

            // Use room/house phone?
            if ($g->getHousePhone()) {
                $visit->setExtPhoneInstalled();
            }
        }


        // Room rate

        $visit->setRateCategory($resv->getRoomRateCategory());
        $visit->setIdRoomRate($resv->getIdRoomRate());
        $visit->setRateAdjust($resv->getRateAdjust());
        $visit->setPledgedRate($resv->getFixedRoomRate());

        // Category
        if (isset($post['selRateCategory']) && ($isAuthorized || $uS->RateChangeAuth === FALSE)) {

            $rateCategory = filter_var($post['selRateCategory'], FILTER_SANITIZE_STRING);

            // Verify new rate...
            if ($rateCategory != $resv->getRoomRateCategory()) {

                $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
                $rateRs = $priceModel->getCategoryRateRs(0, $rateCategory);

                $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());
                $visit->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

                $visit->setRateCategory($rateCategory);
                $resv->setRoomRateCategory($rateCategory);

            }
        }

        // Adjustment amount
        if (isset($post['txtadjAmount']) && ($isAuthorized || $uS->RateChangeAuth === FALSE)) {

            if ($post['txtadjAmount'] === '0' || $post['txtadjAmount'] === '') {
                $rateAdjust = 0;
            } else {
                $rateAdjust = floatval(filter_var($post['txtadjAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            }

            $visit->setRateAdjust($rateAdjust);
            $resv->setRateAdjust($rateAdjust);

        } else {
            $visit->setRateAdjust($resv->getRateAdjust());
        }

        // Pledged room rate
        if (isset($post["txtFixedRate"]) && ($isAuthorized || $uS->RateChangeAuth === FALSE)) {

            if ($post['txtFixedRate'] === '0' || $post['txtFixedRate'] === '') {
                $fixedRate = 0;
            } else {
                $fixedRate = floatval(filter_var($post['txtFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            }

            if ($fixedRate < 0) {
                $fixedRate = 0;
            }

            $visit->setPledgedRate($fixedRate);
            $resv->setFixedRoomRate($fixedRate);

        } else {
            $visit->setPledgedRate($resv->getFixedRoomRate());
        }

        // Visit (Cleaning) Fee
        if (isset($post['selVisitFee'])) {
            $visitFeeOption = filter_var($post['selVisitFee'], FILTER_SANITIZE_STRING);

            $vFees = readGenLookupsPDO($dbh, 'Visit_Fee_Code');

            if (isset($vFees[$visitFeeOption])) {
                $resv->setVisitFee($vFees[$visitFeeOption][2]);
            } else {
                throw new Hk_Exception_Runtime('Bad Cleaning Fee Code.  ');
            }
        }



        // Rate Glide
        $visit->setRateGlideCredit(RateChooser::setRateGlideDays($dbh, $reg->getIdRegistration(), $uS->RateGlideExtend));

        // Primary guest
        $visit->setPrimaryGuestId($resv->getIdGuest());

        // Reservation Id
        $visit->setReservationId($resv->getIdReservation());

        // hospital stay id
        $visit->setIdHospital_stay($hospitalStay->getIdHospital_Stay());

        // Add reservation notes
        if ($visit->visitRS->Notes->getStoredVal() == '' && $resv->getNotes() != '') {
            $visit->visitRS->Notes->setNewVal($resv->getNotes());
            VisitView::updatePsgNotes($dbh, $psg, $resv->getNotes());
        }


        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);


        // Save new reservation status
        $resv->setStatus(ReservationStatus::Staying);
        $resv->setActualArrival($visit->getArrivalDate());
        $resv->setExpectedDeparture($visit->getExpectedDeparture());
        $resv->setNumberGuests($numOccupants);
        $resv->setIdResource($resc->getIdResource());
        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);


        //
        // Payment
        //
        $pmp = PaymentChooser::readPostedPayment($dbh, $post);

        // Check for key deposit
        if ($uS->KeyDeposit && is_null($pmp) === FALSE) {

            $depCharge = $resc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]);
            $depBalance = $reg->getDepositBalance($dbh);

            if ($depCharge > 0 && $pmp->getKeyDepositPayment() == 0 && $depBalance > 0) {

                // Pay deposit with registration balance
                if ($depCharge <= $depBalance) {
                    $pmp->setKeyDepositPayment($depCharge);
                } else {
                    $pmp->setKeyDepositPayment($depBalance);
                }



            } else if ($pmp->getKeyDepositPayment() > 0) {

                $visit->visitRS->DepositPayType->setNewVal($pmp->getPayType());
            }

            // Update Pay type.
            $visit->updateVisitRecord($dbh, $uS->username);
        }

        $paymentManager = new PaymentManager($pmp);

        $payResult = self::processPayments($dbh, $paymentManager, $visit, 'CheckedIn.php', $visit->getPrimaryGuestId());

        if ($payResult !== NULL) {

            $reply .= $payResult->getReplyMessage();

            if ($payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $payResult->getForwardHostedPayment();
            }

            // Receipt
            if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());
                Registration::updatePrefTokenId($dbh, $visit->getIdRegistration(), $payResult->getIdToken());
            }

            // New Invoice
            if (is_null($payResult->getInvoiceMarkup()) === FALSE && $payResult->getInvoiceMarkup() != '') {
                $dataArray['invoice'] = HTMLContainer::generateMarkup('div', $payResult->getInvoiceMarkup());
            }
        }



        // waitlist maintenance
        if (isset($post["txtWlId"])) {
            $wlId = intval(filter_var($post["txtWlId"], FILTER_SANITIZE_NUMBER_INT), 10);
            if ($wlId > 0) {
                Waitlist::updateEntry($dbh, $wlId, WL_Status::Stayed, $uS->username);
            }
        }



        // Generate Reg form
        switch ($uS->RegForm) {
            case '1':
                // PIFH
                try {

                    $dataArray['regform'] = RegisterForm::prepareReceipt($dbh, $visit->getIdVisit());
                    $dataArray['style'] = RegisterForm::getStyling();

                } catch (\Hk_Exception_Runtime $hex) {
                    $dataArray['regform'] = $hex->getMessage();
                }

                break;

            case '2':
                // IMDGHf
                $reservArray = ReservationSvcs::generateCkinDoc($dbh, 0, $visit->getIdVisit(), $uS->resourceURL . 'images/receiptlogo.png');

                $dataArray['style'] = $reservArray['style'];
                $dataArray['regform'] = $reservArray['doc'];
                unset($reservArray);

                break;
        }


        // email the form
        if ($uS->adminEmailAddr != '' && $uS->noreplyAddr != '') {

            try {

                $config = new Config_Lite(ciCFG_FILE);
                $mail = prepareEmail($config);

                $mail->From = $uS->noreplyAddr;
                $mail->FromName = $uS->siteName;

                $tos = explode(',', $uS->adminEmailAddr);
                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->addReplyTo($uS->noreplyAddr, $uS->siteName);
                $mail->isHTML(true);

                $mail->Subject = "New Check-In to " . $resc->getTitle() . " by " . $uS->username;

                $notes = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h4', 'Notes') . nl2br($psg->psgRS->Notes->getStoredVal()));

                $mail->msgHTML($dataArray['style'] . $dataArray['regform'] . $notes);
                $mail->send();

            } catch (Exception $ex) {
                $reply .= $ex->getMessage();
            }
        }



        // Credit payment?
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        }

        // reload registration to reflect any new deposit payments.
        $reg2 = new Registration($dbh, $psg->getIdPsg());

        // Checked out already?
        if ($chkoutDT < $today) {
            $dataArray['ckmeout'] = $chkoutDT->format('Y-m-d');
            $dataArray['vid'] = $visit->getIdVisit();
            $dataArray['gid'] = $visit->getPrimaryGuestId();
        }

        $dataArray['regDialog'] = HTMLContainer::generateMarkup('div', $reg2->createRegMarkup($dbh, FALSE), array('class' => "ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"));
        $dataArray['success'] = "Checked-In.  " . $reply;
        $dataArray['reg'] = $reg2->getIdRegistration();
        // Okay
        return $dataArray;
    }

    public static function getMember(\PDO $dbh, $idReserv, $id, $role, $idPrefix, $idPsg, $patientStaying, $havePatient, $addRoom) {

        $uS = Session::getInstance();
        $guest = null;
        $patient = null;
        $psg = null;
        $oldResvId = 0;
        $labels = new Config_Lite(LABEL_FILE);

        // Not Claiming a reservation?
        if ($idReserv == 0 && $addRoom === FALSE) {
            // No reservation indicated

            if ($uS->OpenCheckin === FALSE) {
                return array('error' => 'Our guest must have a reservation in order to check-in.  ');
            }

            // Find idPsg if guest is the patient
            if ($role == 'p' && $id > 0 && $idPsg < 1) {

                $ngRs = new Name_GuestRS();
                $ngRs->idName->setStoredVal($id);
                $ngRs->Relationship_Code->setStoredVal(RelLinkType::Self);
                $rows = EditRS::select($dbh, $ngRs, array($ngRs->idName, $ngRs->Relationship_Code));

                if (count($rows) == 1) {
                    EditRS::loadRow($rows[0], $ngRs);
                    $idPsg = $ngRs->idPsg->getStoredVal();
                }
            }

            // Look for a reservation
            if ($idPsg > 0) {

                $dataArray = ReservationSvcs::reservationChooser($dbh, $id, $idPsg, $uS->guestLookups['ReservStatus'], $labels, $uS->ResvEarlyArrDays);
                if (count($dataArray) > 0) {
                    $dataArray['role'] = $role;
                    return $dataArray;
                }
            }
        }



        // Flag to force a new reservation.
        if ($idReserv == -1) {
            $idReserv = 0;
        }

        // adding a room?
        if ($addRoom && $idReserv > 0) {

            // get the psgId
            $idPsg = Reservation_1::getIdPsgStatic($dbh, $idReserv);
            $idReserv = 0;
        }


        // reservation
        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);
        $dataArray['rid'] = $resv->getIdReservation();


        if ($role == 'r') {
            // existing reservation

            $havePatient = TRUE;

            if ($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::Imediate || $resv->getStatus() == ReservationStatus::Waitlist) {
                // Check in a reservation
                $guest = new Guest($dbh, $idPrefix, $resv->getIdGuest());

            } else if ($id > 0) {
                // add a guest.
                $guest = new Guest($dbh, $idPrefix, $id);

            } else if ($resv->getStatus() == ReservationStatus::Staying) {
                // prepare to add a guest.

                $psg = new Psg($dbh, $resv->getIdPsg($dbh));
                $dataArray['idPsg'] = $psg->getIdPsg();

                $stays = self::loadStays($dbh, $resv->getIdRegistration());
                $patientStaying = FALSE;

                foreach ($stays as $s) {

                    if ($psg->getIdPatient() == $s['idName']) {
                        $patientStaying = TRUE;
                        break;
                    }
                }

                if ($patientStaying === FALSE) {

                    $patient = new Patient($dbh, 'h_', $psg->getIdPatient());
                    $dataArray['patient'] = $patient->createMarkup(FALSE);

                }

                if (count($stays) > 0) {
                    $dataArray['stays'] = HouseServices::getStaysMarkup($stays, $resv->getIdReservation());
                }

                // Hospital markup
                $hospitalStay = new HospitalStay($dbh, $psg->getIdPatient());
                $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);

                $dataArray['addr'] = self::createAddrObj($dbh, $resv->getIdGuest());
                $dataArray['hvPat'] = $havePatient;
                $dataArray['patStay'] = $patientStaying;

                if ($uS->RoomsPerPatient > 1) {

                    $stmt = $dbh->query("select count(*) from reservation where idRegistration = " . $resv->getIdRegistration() . " and `Status` = '" . ReservationStatus::Staying . "'");
                    $rcount = $stmt->fetchAll(PDO::FETCH_NUM);

                    if ($rcount[0][0] < $uS->RoomsPerPatient) {
                        // Include Additional Room Query
                        $dataArray['adnlrm'] = RoomChooser::moreRoomsMarkup($rcount[0][0], $addRoom);
                    } else {
                        $dataArray['adnlrm'] = HTMLContainer::generateMarkup('p', 'Already using the maximum of ' . $uS->RoomsPerPatient . ' rooms per ' . $labels->getString('MemberType', 'patient', 'Patient'), array('style'=>'margin:.3em;'));
                    }
                }

                return $dataArray;

            } else {
                throw new Hk_Exception_InvalidArguement("A Valid Reservation was not found.  Resv. Id = " . $resv->getIdReservation());
            }

        } else if ($role == 'g') {
            // add a guest
            if ($resv->isNew() && $havePatient === FALSE && $idPsg == 0) {

                $ngRss = Psg::getNameGuests($dbh, $id);

                if (count($ngRss) > 0) {
                    // Select psg
                    $mkup = ReservationSvcs::psgChooserMkup($dbh, $ngRss, $uS->PatientAsGuest);
                    return array('choosePsg' => $mkup, 'idGuest' => $id, 'role' => $role);
                }
            }

            $guest = new Guest($dbh, $idPrefix, $id);

        //
        } else if ($role == 'p' && $havePatient === FALSE) {
            // patient

            if ($id == 0) {

                if ($patientStaying && $uS->PatientAsGuest) {

                    $guest = new Guest($dbh, $idPrefix, 0);
                    $guest->setPatientRelationshipCode(RelLinkType::Self);
                    $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, new HospitalStay($dbh, $id));
                    $havePatient = TRUE;

                } else {
                    // Blank patient markup
                    $patient = new Patient($dbh, 'h_', $id);
                    $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, new HospitalStay($dbh, $id));
                    $dataArray['patient'] = $patient->createMarkup();
                    $dataArray['hvPat'] = TRUE;
                    $dataArray['rmvbtnp'] = TRUE;
                    return $dataArray;
                }

            } else {

                // id > 0
                if ($patientStaying === FALSE || $uS->PatientAsGuest === FALSE) {

                    $patient = new Patient($dbh, 'h_', $id);
                    $psg = $patient->getPatientPsg($dbh);
                    $idPsg = $psg->getIdPsg();

                    $dataArray['patient'] = $patient->createMarkup();
                    $dataArray['idPsg'] = $psg->getIdPsg();


                    // Hospital markup
                    $hospitalStay = new HospitalStay($dbh, $patient->getIdName());
                    $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);

                    $stays = self::loadStays($dbh, $resv->getIdRegistration());

                    if (count($stays) > 0) {
                        $dataArray['stays'] = HouseServices::getStaysMarkup($stays, $resv->getIdReservation());
                    }

                    $dataArray['addr'] = self::createAddrObj($dbh, $resv->getIdGuest());
                    $dataArray['hvPat'] = TRUE;

                    return $dataArray;
                }


               // otherwise, the patient is also the guest so let it fall through
                $guest = new Guest($dbh, 'g_', $id);
                $guest->setPatientRelationshipCode(RelLinkType::Self);
                $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, new HospitalStay($dbh, $id));
                $havePatient = TRUE;

            }

        } else {

            throw new Hk_Exception_InvalidArguement("Member role = '" . $role . "', is unknown. Or bad action code.");
        }


        // Hopefully we defined a guest.
        if (is_null($guest)) {
            return array('error' => "Guest is set to NULL.");
        }

        // Exit no return guests.
        if ($guest->getNoReturn() != '') {
            return array('error'=>'Guest "' .$guest->getNameObj()->get_FullName() . '" is flagged for No Return.  Reason: ' . $guest->getNoReturn());
        }

        // guest already staying?
        if ($guest->isCurrentlyStaying($dbh)) {

            $nameObj = $guest->getNameObj();
            return array('error' => $nameObj->get_fullName() . ' is already checked in.  ');
        }

        // Provide addnl room check box
        if ($uS->RoomsPerPatient > 1 && $resv->getStatus() == ReservationStatus::Staying) {

            $stmt = $dbh->query("select count(*) from reservation where idRegistration = " . $resv->getIdRegistration() . " and `Status` = '" . ReservationStatus::Staying . "'");
            $rcount = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($rcount[0][0] < $uS->RoomsPerPatient) {
                // Include Additional Room Query
                $dataArray['adnlrm'] = RoomChooser::moreRoomsMarkup($rcount[0][0], $addRoom);
            } else {
                $dataArray['adnlrm'] = HTMLContainer::generateMarkup('p', 'Already using the maximum of ' . $uS->RoomsPerPatient . ' rooms per ' . $labels->getString('MemberType', 'patient', 'Patient'), array('style'=>'margin:.3em;'));
            }
        }

        $idPatient = 0;


        // Get PSG
        if ($resv->isNew()) {

            // Flag to force a new psg
            if ($idPsg == -1) {
                $idPsg = 0;
            }

            $psg = new Psg($dbh, $idPsg);
            $idPatient = $psg->getIdPatient();
            $hospitalStay = new HospitalStay($dbh, $idPatient);
            $reg = new Registration($dbh, 0, $resv->getIdRegistration());

            // Define the reservation if exists.
            if ($hospitalStay->getIdHospital_Stay() > 0 && $addRoom === FALSE) {

                // check for existing reservation
                $stmt = $dbh->query("Select idReservation from reservation where idHospital_Stay = " . $hospitalStay->getIdHospital_Stay() . " and Status = '" . ReservationStatus::Staying . "';");
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                if (count($rows) > 0) {

                    $resv = Reservation_1::instantiateFromIdReserv($dbh, $rows[0][0]);
                    $reg = new Registration($dbh, 0, $resv->getIdRegistration());
                    $dataArray['rid'] = $resv->getIdReservation();
                    $havePatient = TRUE;
                }

            } else if ($idPsg > 0 && $addRoom === FALSE) {

                // More general check for reservations.
                $resArray = ReservationSvcs::reservationChooser($dbh, 0, $psg->getIdPsg(), $uS->guestLookups['ReservStatus'], $labels, $uS->ResvEarlyArrDays);
                if (count($resArray) > 0) {
                    return $resArray;
                }

            } else if ($guest->getIdName() > 0 && $havePatient === FALSE) {

                // Look for a previous reservation to copy from ...
                $stmt = $dbh->query("select r.idReservation, max(r.Expected_Arrival) from reservation r  where r.idGuest = " . $guest->getIdName() . " order by r.idGuest");
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                if (count($rows > 0)) {
                    $oldResvId = $rows[0][0];
                }

            }

        } else {

            // From valid reservation
            $reg = new Registration($dbh, 0, $resv->getIdRegistration());
            $psg = new Psg($dbh, $reg->getIdPsg());
            $idPatient = $psg->getIdPatient();
            $hospitalStay = new HospitalStay($dbh, $idPatient);

            if ($idPatient > 0) {
                $havePatient = TRUE;
            }

        }

        //Set Rel Code
        if (isset($psg->psgMembers[$guest->getIdName()]) && $guest->getPatientRelationshipCode() == '') {
            $guest->setPatientRelationshipCode($psg->psgMembers[$guest->getIdName()]->Relationship_Code->getStoredVal());
        }


        // Arrival Date
        $arrDate = new \DateTime($resv->getActualArrival() == '' ? $resv->getExpectedArrival() : $resv->getActualArrival());
        $depDate = new \DateTime($resv->getExpectedDeparture());

        if ($resv->getStatus() == ReservationStatus::Staying) {
            $arrDate = new \DateTime();
        }

        $arrDate->setTime(0, 0, 0);
        $depDate->setTime(0, 0, 0);

        $nowDT = new \DateTime();
        $nowDT->setTime(0,0,0);

        if ($depDate <= $arrDate || $depDate <= $nowDT) {
            $depDateStr = '';
        } else {
            $depDateStr = $depDate->format('M j, Y');
        }

        if ($arrDate < $nowDT) {
            $arrDateStr = $arrDate->format('M j, Y');
        } else {
            $arrDateStr = $nowDT->format('M j, Y');
        }

        $guest->setCheckinDate($arrDateStr);
        $guest->setExpectedCheckOut($depDateStr);



        // Patient
        if ($resv->getStatus() == ReservationStatus::Staying) {

            $stays = HouseServices::loadStays($dbh, $reg->getIdRegistration());
            $foundPatient = FALSE;

            foreach ($stays as $s) {
                if ($s['idName'] == $psg->getIdPatient()) {
                    $foundPatient = TRUE;
                }
            }

            if ($foundPatient === FALSE && $guest->getIdName() != $psg->getIdPatient()) {
                // Include a patient section

                $patient = new Patient($dbh, 'h_', $psg->getIdPatient());
                $dataArray['patient'] = $patient->createMarkup($dbh);
                $dataArray['idPsg'] = $psg->getIdPsg();
                $dataArray['rmvbtnp'] = FALSE;
            }

        } else {

            $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);

            // Patient markup
            if ($patientStaying !== TRUE && $idPatient > 0 && ($idPatient != $guest->getIdName() || $uS->PatientAsGuest === FALSE)) {
                // Patient is not a guest
                $patient = new Patient($dbh, 'h_', $idPatient);
                $dataArray['patient'] = $patient->createMarkup($dbh);
                $dataArray['rmvbtnp'] = TRUE;

                if ($idPatient == $guest->getIdName() && $uS->PatientAsGuest === FALSE) {
                    // Return the patient now
                    $dataArray['idPsg'] = $psg->getIdPsg();
                    $stays = self::loadStays($dbh, $reg->getIdRegistration());

                    if (count($stays) > 0) {
                        $dataArray['stays'] = HouseServices::getStaysMarkup($stays, $resv->getIdReservation());
                    }

                    $dataArray['addr'] = self::createAddrObj($dbh, $resv->getIdGuest());
                    $dataArray['patStay'] = FALSE;
                    return $dataArray;
                }

            } else if ($resv->isNew() && $idPatient > 0 && $idPatient != $guest->getIdName() && $patientStaying && $havePatient === FALSE) {

                $patient = new Guest($dbh, $idPrefix.'p', $idPatient);
                $patient->setPatientRelationshipCode(RelLinkType::Self);
                $patient->setCheckinDate($arrDateStr);
                $patient->setExpectedCheckOut($depDateStr);

                $dataArray['guests'][] = $patient->createMarkup($dbh);
                $havePatient = TRUE;
            }

        }




        // Restrict patient relationship chooser
        if ($resv->isNew() && $havePatient === FALSE) {
            $restrictRel = FALSE;
        } else {
            $restrictRel = TRUE;
        }

        $dataArray['guests'][] = $guest->createMarkup($dbh, TRUE, $restrictRel);

        $dataArray['hvPat'] = $havePatient;

        if ($patientStaying != NULL) {
            $dataArray['patStay'] = $patientStaying;
        }


        $numNewGuests = 1;

        $roomChooser = new RoomChooser($dbh, $resv, $numNewGuests, new \DateTime($arrDateStr), new \DateTime($depDateStr));

        if ($resv->getStatus() != ReservationStatus::Staying) {
            // load additional guests
            $guests = ReservationSvcs::getReservGuests($dbh, $resv->getIdReservation());

            if (count($guests) > 1 && $roomChooser->getCurrentGuests() == 0) {

                foreach ($guests as $k => $v) {

                    // Omit selected guest
                    if ($guest->getIdName() == $k) {
                        continue;
                    }

                    // Guest must be in the psg
                    if (isset($psg->psgMembers[$k]) === FALSE) {
                        throw new Hk_Exception_Runtime('Guest is not a member of the PSG! Golly, this is not supposted to happen.');
                    }

                    // omit primary guest
//                    if ($v != '1') {

                        $g = new Guest($dbh, $k, $k);

                        if ($g->isCurrentlyStaying($dbh)) {
                            Continue;
                        }

                        $g->setCheckinDate($arrDateStr);
                        $g->setExpectedCheckOut($depDateStr);
                        $g->setPatientRelationshipCode($psg->getGuestRelationship($k));

                        $dataArray['guests'][] = $g->createMarkup($dbh, TRUE);
                        $numNewGuests ++;

                        if ($psg->getGuestRelationship($k) == RelLinkType::Self) {
                            // remove patient markup.
                            unset($dataArray['patient']);
                            // Put patient on top.
                            array_reverse($dataArray['guests']);
                        }

//                    }
                }

                $roomChooser->setNumNewGuests($numNewGuests);
            }
        }


        // Constraints panel.
        $disableConstraints = FALSE;
        if ($resv->getStatus() == ReservationStatus::Staying || $uS->OpenCheckin === FALSE) {
            $disableConstraints = TRUE;
        }


        if ($resv->isNew()) {

            // Look for a previous reservation to copy from ...
            if ($guest->getIdName() > 0) {
                $stmt = $dbh->query("select r.idReservation, max(r.Expected_Arrival) from reservation r  where r.idGuest = " . $guest->getIdName() . " order by r.idGuest");
                $rows = $stmt->fetchAll(PDO::FETCH_NUM);

                if (count($rows > 0)) {
                    $roomChooser->setOldResvId($rows[0][0]);
                }
            }
        }

        // only show an open chooser a new checkin.
        $dataArray['resc'] = $roomChooser->createConstraintsChooser($dbh, $resv->getIdReservation(), $roomChooser->getTotalGuests(), $disableConstraints, $resv->getRoomTitle($dbh));

        // send resource information
        if (is_null($roomChooser->getSelectedResource()) === FALSE) {

            $dataArray['rmax'] = $roomChooser->getSelectedResource()->getMaxOccupants();
            $dataArray['rcur'] = $roomChooser->getCurrentGuests();
        }


        $stays = self::loadStays($dbh, $reg->getIdRegistration());

        if (count($stays) > 0) {
            $dataArray['stays'] = HouseServices::getStaysMarkup($stays, $resv->getIdReservation());
        }

        $dataArray['addr'] = self::createAddrObj($dbh, $resv->getIdGuest());

        return $dataArray;
    }

    public static function loadStays(\PDO $dbh, $idReg) {

        if ($idReg == 0) {
            return array();
        }

        $query = "select
    s.idName,
    ifnull(r.Title,'') as `Room`,
    (case when m.Name_Nickname = '' then m.Name_First else m.Name_Nickname end) as Name_First,
    (case when m.Name_Suffix = '' then m.Name_Last else concat(m.Name_Last, ' ', m.Name_Suffix) end) as `Name_Last`,
    s.Checkin_Date,
    ng.Relationship_Code,
    ng.idPsg,
    v.idReservation,
    v.idVisit
from
    stays s left join vmember_listing m ON s.idName = m.Id
            left join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
            left join room r on s.idRoom = r.idRoom
            left join registration rg on v.idRegistration = rg.idRegistration
            left join name_guest ng on s.idName = ng.idName and rg.idPsg = ng.idPsg
    where s.`Status` = '" . VisitStatus::CheckedIn . "' and v.idRegistration = $idReg;";
        $stmt = $dbh->query($query);
        $stays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $stays;
    }

    public static function getStaysMarkup($stays, $idResv) {

        if (count($stays) <= 0) {
            return '';
        }

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $uS = Session::getInstance();
        $tbl = new HTMLTable();
        $hasRows = FALSE;
        $markup = '';

        foreach ($stays as $r) {

            if ($idResv != $r['idReservation']) {
                continue;
            }

            $hasRows = TRUE;

            $rel = '';
            if (isset($uS->guestLookups[GL_TableNames::PatientRel][$r['Relationship_Code']])) {
                $rel = $uS->guestLookups[GL_TableNames::PatientRel][$r['Relationship_Code']][1];
            }

            $idMarkup = HTMLContainer::generateMarkup('a', $r['Name_First'] . ' ' . $r['Name_Last'], array('href' => 'GuestEdit.php?id=' . $r['idName'] . '&psg='.$r['idPsg']));

            $tbl->addBodyTr(
                    HTMLTable::makeTd($idMarkup)
                    .HTMLTable::makeTd($rel)
                    .HTMLTable::makeTd($r['Room'])
                    .HTMLTable::makeTd($r['Checkin_Date'] == '' ? '' : date('M j, Y h:i', strtotime($r['Checkin_Date'])))
                    );

        }

        if ($hasRows) {
            $tbl->addHeaderTr(HTMLTable::makeTh('Name').HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient') . ' Relation').HTMLTable::makeTh('Room').HTMLTable::makeTh('Checked In'));

            $markup = HTMLContainer::generateMarkup('div',
                    HTMLContainer::generateMarkup('fieldset',
                            HTMLContainer::generateMarkup('legend', 'Guests in Residence', array('style' => 'font-weight:bold;'))
                    . $tbl->generateMarkup(), array('class' => 'hhk-panel')), array('style' => 'float:left'));
        }

        return $markup;
    }

    public static function saveMembers(\PDO $dbh, $post, $isAuthorized) {

        $uS = Session::getInstance();
        $dataArray = array();
        $dataArray['warning'] = '';
        $labels = new Config_Lite(LABEL_FILE);


        // reservation
        $idReserv = 0;
        if (isset($post['rid'])) {
            $idReserv = intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

        $chkinGroup = new CheckInGroup();
        $chkinGroup->saveMembers($dbh, $resv->getIdHospitalStay(), $post);

        // Do we have any guests?
        if (count($chkinGroup->newGuests) == 0) {
            $dataArray['patient'] = $chkinGroup->patient->createMarkup();
            $dataArray['warning'] = 'No guests specified, or the guests are already checked in.  ';
            return $dataArray;
        }

        // Load Guests
        foreach ($chkinGroup->newGuests as $guest) {
            $dataArray['guests'][] = $guest->createMarkup($dbh, FALSE, TRUE);
        }

        // do we have a patient?
        if (is_null($chkinGroup->patient)) {

            // no patient.
            $dataArray['warning'] = 'A ' . $labels->getString('MemberType', 'patient', 'Patient') . ' must be specified.  ';
            $dataArray['hvPat'] = FALSE;
            return $dataArray;
        }

        // Patient
        if ($chkinGroup->isPatientCkgIn() === FALSE) {
            $dataArray['patient'] = $chkinGroup->patient->createMarkup(FALSE);
        }

        // verify/save psg
        $psg = $chkinGroup->savePsg($dbh, '', $uS->username);

        // Registration
        $reg = new Registration($dbh, $psg->getIdPsg());
        $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), $uS->username);

        // Hospital
        $hospitalStay = new HospitalStay($dbh, $psg->getIdPatient());
        Hospital::saveHospitalMarkup($dbh, $psg, $hospitalStay, $post);

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        // clean Dates
        if (isset($post['ckindt']) && isset($post['ckoutdt'])) {

            $ckinStr = filter_var($post['ckindt'], FILTER_SANITIZE_STRING);
            $ckoutStr = filter_var($post['ckoutdt'], FILTER_SANITIZE_STRING);

            try {
                $chkinDT = setTimeZone($uS, $ckinStr);

                if ($chkinDT < $today) {
                    $chkinDT->setTime(16, 0, 0);
                }

                $chkoutDT = setTimeZone($uS, $ckoutStr);
                $chkoutDT->setTime(10,0,0);

            } catch (Exception $ex) {
                $dataArray['warning'] = 'Problem with visit dates:  ' . $ex->getMessage();
                return $dataArray;
            }

        } else {
            $dataArray['warning'] .= 'Check-in and/or check-out dates are missing!  ';
            return $dataArray;
        }

        // Add new room to the registration?
        $addRoom = FALSE;
        if (isset($post['addRoom'])) {
            $addRoom = filter_Var($post['addRoom'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($addRoom) {
            $idReserv = 0;
            $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);
            $resv->setAddRoom(1);
        }

        $rateChooser = new RateChooser($dbh);

        if ($idReserv == 0) {

            $ids = array_keys($chkinGroup->newGuests);
            $idPrimaryGuest = $ids[0];

            // Make a new reservation.
            $resv->setHospitalStay($hospitalStay);
            $resv->setNumberGuests(count($chkinGroup->newGuests));
            $resv->setIdGuest($idPrimaryGuest);

            if ($uS->RoomPriceModel == ItemPriceCode::Basic) {
                $rateCategory = RoomRateCategorys::Fixed_Rate_Category;
            } else if ($uS->RoomRateDefault != '') {
                $rateCategory = $uS->RoomRateDefault;
            } else {
                $rateCategory = Default_Settings::Rate_Category;
            }


            // Look for an approved rate
            if ($psg->getIdPsg() > 0 && $uS->IncomeRated) {

                if ($reg->getIdRegistration() > 0) {

                    $fin = new FinAssistance($dbh, $reg->getIdRegistration());

                    if ($fin->hasApplied() && $fin->getFaCategory() != '') {
                        $rateCategory = $fin->getFaCategory();
                    }
                }
            }

            // Get the idRoomRate
            $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);
            $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());
            $resv->setRoomRateCategory($rateCategory);

            $resv->setStatus(ReservationStatus::Imediate);
            $resv->setExpectedArrival($chkinDT->format('Y-m-d H:i:s'));
            $resv->setExpectedDeparture($chkoutDT->format('Y-m-d H:i:s'));

            if ($uS->VisitFee) {
                $kFees = readGenLookupsPDO($dbh, 'Visit_Fee_Code');
                $resv->setVisitFee($kFees[$uS->DefaultVisitFee][2]);
            }

            $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

            foreach ($ids as $id) {
                ReservationSvcs::saveReservationGuest($dbh, $resv->getIdReservation(), $id, ($id == $idPrimaryGuest ? TRUE : FALSE));
            }

        }



        // Check for proper reservation status
        if (($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::Waitlist || $resv->getStatus() == ReservationStatus::Staying || $resv->getStatus() == ReservationStatus::Imediate) === FALSE) {

            throw new Hk_Exception_Runtime('The Reservation Status is wrong - ' . $resv->getStatusTitle());
        }


        // Save any constraints
        $resv->saveConstraints($dbh, $post);




        // Reservation dates
        try {
            self::verifyVisitDates($resv, $chkinDT, $chkoutDT, $uS->OpenCheckin);

            // Stay dates
            self::verifyStayDates($chkinGroup->newGuests, $chkinDT, $chkoutDT);

        } catch (Exception $ex) {

            $dataArray['warning'] = 'Problem with dates:  ' . $ex->getMessage();
            return $dataArray;
        }


        // Set up room chooser object
        $roomChooser = new RoomChooser($dbh, $resv, count($chkinGroup->newGuests), $chkinDT, $chkoutDT);

        // Load any stays
        $stays = self::loadStays($dbh, $reg->getIdRegistration());

        // Room empty?
        if ($roomChooser->getCurrentGuests() == 0) {

            // count visits
            $visits = array();
            foreach ($stays as $s) {
                $visits[$s['idVisit']] = 'y';
            }

            // If there are other reservations, then we may be adding a room
            if (count($visits) >= $uS->RoomsPerPatient) {
                throw new Hk_Exception_Runtime('Maximum Rooms per ' . $labels->getString('MemberType', 'patient', 'Patient') . ' is exceeded.  ');
            }

            // We need a primary guest.
            if (isset($chkinGroup->newGuests[$resv->getIdGuest()]) === FALSE) {

                $ids = array_keys($chkinGroup->newGuests);
                $idPrimaryGuest = $ids[0];
                $oldPriGuest = $resv->getIdGuest();

                // Update reservation.
                $resv->setNumberGuests(count($chkinGroup->newGuests));
                $resv->setIdGuest($idPrimaryGuest);
                $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);

                // Remove old primary guest.
                $resGuestRs = new Reservation_GuestRS();
                $resGuestRs->idReservation->setStoredVal($resv->getIdReservation());
                $resGuestRs->idGuest->setStoredVal($oldPriGuest);

                EditRS::delete($dbh, $resGuestRs, array($resGuestRs->idReservation, $resGuestRs->idGuest));


                foreach ($ids as $id) {
                    ReservationSvcs::saveReservationGuest($dbh, $resv->getIdReservation(), $id, ($id == $idPrimaryGuest ? TRUE : FALSE));
                }
            }

            // Show payment, only to the first guest.
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                $resc = $roomChooser->getSelectedResource();
                if (is_null($resc)) {
                    $roomKeyDeps = '';
                } else {
                    $roomKeyDeps = $resc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]);

                }

                // Rate Chooser
                $dataArray['rate'] = $rateChooser->createCheckinMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));

                // Payment Chooser
                if ($uS->PayAtCkin) {
                    $checkinCharges = new CheckinCharges(0, $resv->getVisitFee(), $roomKeyDeps);
                    $checkinCharges->sumPayments($dbh);
                    $dataArray['pay'] = PaymentChooser::createMarkup($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $checkinCharges, $resv->getExpectedPayType(), $uS->KeyDeposit, FALSE, $uS->DefaultVisitFee, $reg->getPreferredTokenId());
                }

            }

        } else {

            // Currunt guests markup
            if (count($stays) > 0) {
                $dataArray['stays'] = HouseServices::getStaysMarkup($stays, $resv->getIdReservation());
            }

        }

        // Omit self only if not adding a room
        $omitSelf = TRUE;
        if ($addRoom) {
            $omitSelf = FALSE;
        }

        // Room Chooser
        $dataArray['resc'] = $roomChooser->CreateCheckinMarkup($dbh, $isAuthorized, TRUE, $omitSelf);

        // Array with key deposit info
        $dataArray['rooms'] = $rateChooser->makeRoomsArray($roomChooser, $uS->guestLookups['Static_Room_Rate'], $uS->guestLookups[GL_TableNames::KeyDepositCode]);
        // Array with amount calculated for each rate.
        $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $reg->getIdRegistration(), $resv->getFixedRoomRate(), ($resv->getNumberGuests() * $resv->getExpectedDays()));

        if ($uS->VisitFee) {
            // Visit Fee Array
            $dataArray['vfee'] = $rateChooser::makeVisitFeeArray($dbh);
        }


        // send resource information
        if (is_null($roomChooser->getSelectedResource()) === FALSE) {

            $dataArray['rmax'] = $roomChooser->getSelectedResource()->getMaxOccupants();
            $dataArray['rcur'] = $roomChooser->getCurrentGuests();
        }



        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = Vehicle::createVehicleMarkup($dbh, $reg->getIdRegistration(), $reg->getNoVehicle());
        }


        // Reservation ID
        $dataArray['rid'] = $resv->getIdReservation();

        return $dataArray;
    }

    public static function deleteUnfinisedCheckins(\PDO $dbh) {

        $dbh->exec("delete from reservation_guest where idReservation in (select idreservation from reservation where idResource = 0 and Status = '" . ReservationStatus::Imediate . "')");
        $numDel = $dbh->exec("delete from reservation where idResource = 0 and Status = '" . ReservationStatus::Imediate . "'");
        return array('success' => $numDel . ' Records Deleted.  ');
    }

    public static function visitChangeLogMarkup(\PDO $dbh, $idReg) {

        $lTable = new HTMLTable();
        if ($idReg > 0) {
            $visLogRS = new Visit_LogRS();
            $visLogRS->idRegistration->setStoredVal($idReg);
            $rows = EditRS::select($dbh, $visLogRS, array($visLogRS->idRegistration), 'and', array($visLogRS->Timestamp), FALSE);
            $cnt = 0;
            foreach ($rows as $r) {
                $vlRS = new Visit_LogRS();
                EditRS::loadRow($r, $vlRS);
                // only show my id or table visit.
                if ($vlRS->Log_Type->getStoredVal() == 'visit') {

                    $lTable->addBodyTr(
                            HTMLTable::makeTd(date('m/d/Y H:i:s', strtotime($vlRS->Timestamp->getStoredVal())))
                            . HTMLTable::makeTd($vlRS->Sub_Type->getStoredVal())
                            . HTMLTable::makeTd($vlRS->User_Name->getStoredVal())
                            . HTMLTable::makeTd($vlRS->idVisit->getStoredVal())
                            . HTMLTable::makeTd($vlRS->Span->getStoredVal())
                            . HTMLTable::makeTd($vlRS->idRr->getStoredVal())
                            . HTMLTable::makeTd(VisitLog::parseLogText($vlRS->Log_Text->getStoredVal()))
                    );
                    if ($cnt++ == 47) {
                        break;
                    }
                }
            }
        }

        $lTable->addHeaderTr(
                HTMLTable::makeTh('Date')
                . HTMLTable::makeTh('Operation')
                . HTMLTable::makeTh('User')
                . HTMLTable::makeTh('Visit Id')
                . HTMLTable::makeTh('Span')
                . HTMLTable::makeTh('Resource Id')
                . HTMLTable::makeTh('Message'));

        return array('vlog' => $lTable->generateMarkup());
    }

    public static function changePatient(\PDO $dbh, $psgId, $newPatientId, $relationships, $username, $replaceRelationship = RelLinkType::Friend) {

        $idPsg = intval($psgId, 10);
        $patientId = intval($newPatientId, 10);
        // Get labels
        $labels = new Config_Lite(LABEL_FILE);


        // Id's valid
        if ($idPsg < 1 || $patientId < 1) {
            return array('warning' => 'PSG or ' . $labels->getString('MemberType', 'patient', 'Patient') . ' not set.  ');
        }

        $stmt = $dbh->query("Select count(idName) from name_guest where idName = $patientId and Relationship_Code = '" . RelLinkType::Self . "';");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        // New patient cannot already be a member of this or another PSG.
        if ($rows[0][0] > 0) {
            return array('warning' => 'Guest is already a ' . $labels->getString('MemberType', 'patient', 'Patient') . ' in this or another PSG.  ');
        }

        $psg = new Psg($dbh, $idPsg);

        // New patient must already be a member of this PSG.
        if (isset($psg->psgMembers[$patientId]) === FALSE) {
            return array('warning' => 'Guest is not a member of this PSG.  ');
        }

        $oldPatient = $psg->getIdPatient();

        // Update the PSG with a the changed patient
        $psg->setNewMember($patientId, RelLinkType::Self);
        $psg->setNewMember($oldPatient, $replaceRelationship);

        $psg->psgRS->idPatient->setNewVal($patientId);

        $psg->savePSG($dbh, $patientId, $username, $labels->getString('MemberType', 'patient', 'Patient') . ' Changed from ' . $oldPatient . ' to ' . $patientId);

        // Hospital stay
        $hsnum = $dbh->exec("update hospital_stay set idPatient = $patientId where idPsg = $idPsg;");

        // member type
        $patientMem = new PatientMember($dbh, MemBasis::Indivual, $patientId);
        $patientMem->saveMemberType($dbh, $username);

        $guestMem = new GuestMember($dbh, MemBasis::Indivual, $oldPatient);
        $guestMem->saveMemberType($dbh, $username);

        return array('result'=> $psg->createEditMarkup($dbh, $relationships, $labels));

    }
}
