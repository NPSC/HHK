<?php
/**
 * Receipt.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


Define('NEWLINE', "\n");


/**
 * Description of Receipt
 *
 * @author Eric
 */
class Receipt {

    public static function createSaleMarkup(\PDO $dbh, Invoice $invoice, $siteName, $siteId, PaymentResponse $payResp) {

        // Assemble the statement
        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh($siteName . " Receipt", array('colspan'=>'2')));

        $info = self::getVisitInfo($dbh, $invoice);

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);


        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Guest: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest']));
        }

        if (isset($info['Patient']) && $info['Patient'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('MemberType', 'patient', 'Patient') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Patient']));
        }

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = Member::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        if (isset($info['HospName']) && $info['HospName'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['HospName']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel')) . HTMLTable::makeTd(($payResp->getPaymentDate() == '' ? date('D M jS, Y') : date('D M jS, Y', strtotime($payResp->getPaymentDate())))));

        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoice()));

        foreach ($invoice->getLines($dbh) as $line) {
            $tbl->addBodyTr(HTMLTable::makeTd($line->getDescription() . ':', array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd(number_format($line->getAmount(), 2), array('style'=>'font-size:.8em;')));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Total:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($invoice->getAmount(), 2), array('class'=>'hhk-tdTotals')));


        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);

        if ($invoice->getBalance() > 0 || $invoice->getAmount() != $payResp->getAmount()) {
            $tbl->addBodyTr(HTMLTable::makeTd("Remaining Balance:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format(($invoice->getBalance()), 2)));
        }

        $disclaimer = '';
        $config = new Config_Lite(ciCFG_FILE);
        if ($config->getString('financial', 'PaymentDisclaimer', '') != '') {
            $disclaimer = HTMLContainer::generateMarkup('div', $config->getString('financial', 'PaymentDisclaimer', ''), array('style'=>'font-size:0.7em; text-align:justify'));
        }

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup() . $disclaimer, array('style'=>'margin-bottom:10px;clear:both;float:left;'));
        $rec .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'hhk-receiptMarkup', 'style'=>'display:block;padding:10px;'));
    }

    public static function createVoidMarkup(\PDO $dbh, PaymentResponse $payResp, $siteName, $siteId, $type = 'Void Sale') {

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh($siteName . ' ' . $type . " Receipt", array('colspan'=>'2')));

        $invoice = new Invoice($dbh, $payResp->getInvoice());
        $info = self::getVisitInfo($dbh, $invoice);

        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Guest: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest']));
        }

        if (isset($info['Patient']) && $info['Patient'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('MemberType', 'patient', 'Patient') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Patient']));
        }

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = Member::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        if (isset($info['HospName']) && $info['HospName'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['HospName']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }


        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel'))
                . HTMLTable::makeTd(date('D M jS, Y g:ia')));

        $tbl->addBodyTr(HTMLTable::makeTd("Total Voided:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($payResp->getAmount(), 2)));

        if ($payResp->getPaymentType() == PayType::Charge) {

            $tbl->addBodyTr(HTMLTable::makeTd($payResp->response->getCardType() . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd("xxxx..". $payResp->cardNum));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoice()));

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin-bottom:10px;clear:both;float:left;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'receiptMarkup;', 'style'=>'display:block;padding:10px;'));
    }

    public static function createReturnMarkup(\PDO $dbh, PaymentResponse $payResp, $siteName, $siteId) {

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh($siteName . " Return Receipt", array('colspan'=>'2')));

        $invoice = new Invoice($dbh, $payResp->getInvoice());
        $info = self::getVisitInfo($dbh, $invoice);

        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Guest: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest']));
        }

        if (isset($info['Patient']) && $info['Patient'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('MemberType', 'patient', 'Patient') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Patient']));
        }

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = Member::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        if (isset($info['HospName']) && $info['HospName'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['HospName']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel'))
                . HTMLTable::makeTd(date('D M jS, Y g:ia')));

        $tbl->addBodyTr(HTMLTable::makeTd("Total Returned:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($payResp->getAmount(), 2)));

        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);


        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoice()));

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin-bottom:10px;clear:both;float:left;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'receiptMarkup;', 'style'=>'display:block;padding:10px;'));
    }

    public static function getHouseIconMarkup() {

        $uS = Session::getInstance();
        $config = new Config_Lite(ciCFG_FILE);
        $logoUrl = $config->getString('financial', 'receiptLogoFile', '');
        $rec = '';

        // Don't write img if logo URL not sepcified
        if ($logoUrl != '') {

            $rec .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('img', '', array('src'=>$logoUrl, 'id'=>'hhkrcpt', 'alt'=>$uS->siteName, 'width'=>$config->getString('financial', 'receiptLogoWidth', '150'))),
                array('style'=>'margin-bottom:10px;margin-right:20px;float:left;'));
        }

        return $rec;

    }

    public static function getVisitInfo(\PDO $dbh, Invoice $invoice) {

        try {

                $stmt = $dbh->prepare("select v.idPrimaryGuest,
	n.Name_Full as `Primary_Guest`,
    np.Name_Full as `Patient`,
    r.Title as `Room`,
    ifnull(ha.Title, '') as `Assoc`,
    ifnull(hh.Title, '') as `Hospital`
from
    visit v
        left join
	resource r on v.idResource = r.idResource
		left join
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        left join
    hospital ha ON hs.idAssociation = ha.idHospital
        left join
    hospital hh ON hs.idHospital = hh.idHospital
		left join
	name n on v.idPrimaryGuest = n.idName
		left join
	name np on hs.idPatient = np.idName
where
    v.idVisit = :idv and v.Span = :spn");

            $stmt->execute(array(':idv'=>$invoice->getOrderNumber(), ':spn'=>$invoice->getSuborderNumber()));

            $r = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data = $r[0];

                if ($data['Assoc'] != '' && $data['Assoc'] != '(None)') {
                    $data['HospName'] = $data['Assoc'] . '/' . $data['Hospital'];
                } else {
                    $data['HospName'] = $data['Hospital'];
                }

        } catch (PDOException $pex) {
            $data = array();
        }

        return $data;
    }

    public static function getHospitalNames(\PDO $dbh, $orderNumber) {

        // Find the hospital
        if ($orderNumber > 0) {

            try {

                $stmt = $dbh->prepare("select
    ifnull(ha.Title, '') as `Assoc`,
    ifnull(hh.Title, '') as `Hospital`
from
    visit v
        left join
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        left join
    hospital ha ON hs.idAssociation = ha.idHospital
        left join
    hospital hh ON hs.idHospital = hh.idHospital
where
    v.idVisit = :idv");

                $stmt->execute(array(':idv'=>$orderNumber));

                while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                    if ($r['Assoc'] != '' && $r['Assoc'] != '(None)') {
                        $hsNames = $r['Assoc'] . '/' . $r['Hospital'];
                    } else {
                        $hsNames = $r['Hospital'];
                    }
                }
            } catch (PDOException $pex) {
                $hsNames = '';
            }
        }

        return $hsNames;
    }

    public static function getAddressTable(\PDO $dbh, $idName) {

        $mkup = '';

        if ($idName > 0) {

            $stmt = $dbh->query("SELECT
    n.Company,
    CASE
        WHEN a.Address_2 != '' THEN a.Address_1
        ELSE CONCAT(a.Address_1, ' ', a.Address_2)
    END AS `Address`,
    IFNULL(a.City, '') AS `City`,
    IFNULL(a.State_Province, '') AS `State`,
    IFNULL(a.Postal_Code, '') AS `Zip`,
    IFNULL(p.Phone_Num, '') AS `Phone`,
    IFNULL(e.Email, '') AS `Email`,
    IFNULL(n.Web_Site, '') AS `Web_Site`
FROM
    name n
        LEFT JOIN
    name_address a ON n.idName = a.idName
        AND n.Preferred_Mail_Address = a.Purpose
        LEFT JOIN
    name_phone p ON n.idName = p.idName
        AND n.Preferred_Phone = p.Phone_Code
        LEFT JOIN
    name_email e ON n.idName = e.idName
        AND n.Preferred_Email = e.Purpose
WHERE
    n.idName = $idName");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                $adrTbl = new HTMLTable();

                $street = $rows[0]['Address'];


                if ($rows[0]['City'] != '') {
                    $rows[0]['City'] .= ', ';
                }

                $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['Company'], array('style'=>'font-size:1.1em;')));

                if ($street != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd($street));
                }

                $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['City'] . $rows[0]['State'] . ' ' . $rows[0]['Zip']));

                if ($rows[0]['Phone'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd('Phone: ' . $rows[0]['Phone']));
                }

                if ($rows[0]['Email'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['Email']));
                }

                if ($rows[0]['Web_Site'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['Web_Site']));
                }

                $mkup = $adrTbl->generateMarkup();
            }
        }

        return $mkup;
    }

    public static function processRatesRooms(array $spans) {

        $rates = array();
        $rateCounter = 0;

        foreach ($spans as $v) {

            // Set expected departure to now if earlier than "today"
            $expDepDT = new \DateTime($v['Expected_Departure']);
            $now = new \DateTime();
            $now->setTime(0, 0, 0);

            if ($expDepDT < $now) {
                $expDepStr = $now->format('Y-m-d');
            } else {
                $expDepStr = $expDepDT->format('Y-m-d');
            }


            $rateCounter++;

            $rates[$rateCounter] = array(
                'vid'=>$v['idVisit'],
                'span'=>$v['Span'],
                'status'=>$v['Status'],
                'title'=>$v['Title'],
                'idresc'=>$v['idResource'],
                'psg'=>$v['idPsg'],
                'hosp'=>$v['idHospital'],
                'assoc'=>$v['idAssociation'],
                'cat'=>$v['Rate_Category'],
                'amt'=>$v['Pledged_Rate'],
                'adj'=>$v['Expected_Rate'],
                'glide'=>$v['Rate_Glide_Credit'],
                'idrate'=>$v['idRoom_rate'],
                'start'=>$v['Span_Start'],
                'end'=>$v['Span_End'],
                'arr'=>$v['Arrival_Date'],
                'adep'=>$v['Actual_Departure'],
                'exdep'=>$v['Expected_Departure'],
                'expEnd'=>$expDepStr,
                'days'=>$v['Actual_Span_Nights'],
                'vfa'=>$v['Visit_Fee_Amount']);

            if (isset($v['Name_First']) && isset($v['Name_Last'])) {
                $rates[$rateCounter]['fn'] = $v['Name_First'];
                $rates[$rateCounter]['ln'] = $v['Name_Last'];
                $rates[$rateCounter]['gid'] = $v['idPrimaryGuest'];
            }

            if (isset($v['Deposit_Amount'])) {
                $rates[$rateCounter]['depAmt'] = $v['Deposit_Amount'];
            } else {
                $rates[$rateCounter]['depAmt'] = 0;
            }

            if (isset($v['Guest_Nights'])) {
                $rates[$rateCounter]['gdays'] = $v['Guest_Nights'];
            }

            if (isset($v['AmountPaid'])) {
                $rates[$rateCounter]['paid'] = $v['AmountPaid'];
            }

            if (isset($v['Actual_Month_Nights'])) {
                $rates[$rateCounter]['mdays'] = $v['Actual_Month_Nights'];
            }
            if (isset($v['Actual_Guest_Nights'])) {
                $rates[$rateCounter]['gmdays'] = $v['Actual_Guest_Nights'];
            }
        }

        return $rates;
    }

    public static function processPayments(\PDOStatement $stmt, array $extraCols = array()) {

        $idInvoice = 0;
        $idPayment = 0;
        $idPA = 0;

        $invoices = array();
        $invoice = array();
        $payments = array();
        $paymtAuths = array();
        $houseWaives = array();

        // Organize the data
        while ($p = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($p['idInvoice'] != $idInvoice) {
                // Next Invoice

                if ($idPayment > 0) {
                    // close last payment
                    if ($idPA > 0) {
                        $payments[$idPayment]['auths'] = $paymtAuths;
                    }
                }

                if ($idInvoice > 0) {
                    // close last invoice
                    $invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'h'=>$houseWaives);
                    $houseWaives = array();
                }

                $idInvoice = $p['idInvoice'];

                // new invoice
                $invoice = array(
                    'idInvoice'=>$p['idInvoice'],
                    'Invoice_Number'=>$p['Invoice_Number'],
                    'Invoice_Amount'=>$p['Invoice_Amount'],
                    'Sold_To_Id'=>$p['Sold_To_Id'],
                    'Bill_Agent'=>$p['Bill_Agent'],
                    'idGroup'=>$p['idGroup'],
                    'Order_Number'=>$p['Order_Number'],
                    'Suborder_Number'=>$p['Suborder_Number'],
                    'Invoice_Date'=>$p['Invoice_Date'],
                    'Invoice_Status'=>$p['Invoice_Status'],
                    'Invoice_Status_Title'=>$p['Invoice_Status_Title'],
                    'Carried_Amount'=>$p['Carried_Amount'],
                    'Invoice_Description'=>$p['Description'],
                    'Invoice_Balance'=>$p['Invoice_Balance'],
                    'Delegated_Invoice_Id'=>$p['Delegated_Invoice_Id'],
                    'Invoice_Deleted'=>$p['Deleted'],
                    'Invoice_Updated_By'=>$p['Invoice_Updated_By'],
                    );

                // add extra columns
                foreach ($extraCols as $e) {

                    if (isset($p[$e])) {
                        $invoice[$e] = $p[$e];
                    }
                }

                $idPayment = 0;
                $idPA = 0;
                $payments = array();
                $paymtAuths = array();
            }

            if ($p['idPayment'] != 0) {
                // Payment exists

                if ($idPayment != $p['idPayment']) {
                    // Next Payment

                    if ($idPayment > 0) {
                        // close last payment
                        if ($idPA > 0) {
                            $payments[$idPayment]['auths'] = $paymtAuths;
                        }
                    }


                    $idPayment = $p['idPayment'];

                    $payments[$idPayment] = array('idPayment'=>$p['idPayment'],
                            'Payment_Amount'=>$p['Payment_Amount'],
                            'idPayment_Method'=>$p['idPayment_Method'],
                            'Payment_Method_Title'=>$p['Payment_Method_Title'],
                            'Payment_Status'=>$p['Payment_Status'],
                            'Payment_Status_Title'=>$p['Payment_Status_Title'],
                            'Payment_Date'=>$p['Payment_Date'],
                            'Is_Refund'=>$p['Is_Refund'],
                            'Payment_idPayor'=>$p['Payment_idPayor'],
                            'Payment_Updated_By'=>$p['Payment_Updated_By'],
                            'Payment_Created_By'=>$p['Payment_Created_By'],
                            'Check_Number'=>$p['Check_Number'],
                            'Payment_External_Id'=>$p['Payment_External_Id'],
                            'Payment_Note'=>$p['Payment_Note']
                            );

                    $idPA = 0;
                    $paymtAuths = array();
                }

                // Payment_Auths
                if ($p['idPayment_auth'] != 0 && $idPA != $p['idPayment_auth']) {
                    // next payment auth

                    $idPA = $p['idPayment_auth'];

                    $paymtAuths[$idPA] = array(
                        'idPayment_auth'=>$p['idPayment_auth'],
                        'Charge_Customer_Id'=>$p['Charge_Customer_Id'],
                        'Masked_Account'=>$p['Masked_Account'],
                        'Card_Type'=>$p['Card_Type'],
                        'Approved_Amount'=>$p['Approved_Amount'],
                        'Approval_Code'=>$p['Approval_Code']
                    );
                }
            }

            // House Waive
            if ($p['il_Id'] > 0 && isset($houseWaives[$p['il_Id']]) === FALSE) {
                $houseWaives[$p['il_Id']] = array(
                    'id' => $p['il_Id'],
                    'Amount' => $p['il_Amount'],
                    'Desc' => $p['il_Description']
                );
            }
        }



        // Fiish the last entry of the data
        if ($idPayment > 0) {
            // close last payment
            if ($idPA > 0) {
                $payments[$idPayment]['auths'] = $paymtAuths;
            }
        }

        if ($idInvoice > 0) {
            // close last invoice
            $invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'h'=>$houseWaives);
        }

        return $invoices;

    }

    protected static function addSavedTrs(array $trs, &$tbl) {

        foreach ($trs as $t) {
            $tbl->addBodyTr($t);
        }
    }

    public static function makeOrdersRatesTable($rates, &$totalAmt, PriceModel $priceModel, Config_Lite $labels, array $invLines, &$numberNites) {

        $tbl = new HTMLTable();

        $priceModel->rateHeaderMarkup($tbl, $labels);

        $idVisitTracker = 0;
        $trs = array();
        $separator = '';
        $guestNites = 0;

        // orders and rates
        foreach ($rates as $r) {

            // New Visit
            if ($idVisitTracker != $r['vid']) {

                $idVisitTracker = $r['vid'];

                self::addSavedTrs($trs, $tbl);
                $trs = array();
                $separator = 'border-top: 2px solid #2E99DD;';

            }

            $startDT = new DateTime($r['start']);
            $startDT->setTime(0,0,0);
            $startDateStr = $startDT->format('M j, Y');
            $endDT = ($r['end'] == '' ? new DateTime($r['expEnd']) : new DateTime($r['end']));
            $endDT->setTime(0,0,0);
            $days = $startDT->diff($endDT, TRUE)->days;

            if ($r['days'] > 0 && isset($r['gdays'])) {
                //$guestNites += $r['gdays'];
                $gDayRatio = $r['gdays'] / $r['days'];
            } else {
                $gDayRatio = 1;
            }

            $priceModel->setCreditDays($r['glide']);
            $priceModel->setVisitStatus($r['status']);
            $tiers = $priceModel->tiersCalculation($days, $r['idrate'], $r['cat'], $r['amt'], $r['adj'], floor($days * $gDayRatio));

            $numberNites += $days;

            // Mention rate aging ....
            if ($r['glide'] > 0 && $priceModel->getGlideApplied() && $r['span'] == 0) {
                $tbl->addBodyTr(
                    HTMLTable::makeTd($r['vid'] . '-' . $r['span'])
                    .HTMLTable::makeTd($r['title'])
                    .HTMLTable::makeTd('Room rate aged ' . $r['glide'] . ' days', array('colspan'=>'6', 'style'=>'font-size:small;font-style:italic;'))
                    );

            }

            $priceModel->tiersMarkup($r, $totalAmt, $tbl, $tiers, $startDT, $separator, $guestNites);
            $separator = '';


            // Lay in the visit fee (Cleaning fee)
            if ($r['vfa'] > 0 && $r['span'] == 0) {

                $item = array(
                    'orderNum'=>$r['vid'] . '-' . $r['span'],
                    'date'=>$startDateStr,
                    'desc'=>$labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'),
                    'amt'=>number_format($r['vfa'],2)
                        );

                $trs[] = $priceModel->itemMarkup($item, $tbl);

                $totalAmt += $r['vfa'];

            }

            // Additional Charges/Discounts
            foreach ($invLines as $l) {

                if ($l['Order_Number'] == $r['vid'] && $l['Suborder_Number'] == $r['span']) {

                    if ($l['Item_Id'] == ItemId::AddnlCharge) {

                        $invDate = new DateTime($l['Invoice_Date']);
                        $item = array(
                            'orderNum'=>$r['vid'] . '-' . $r['span'],
                            'date'=>$invDate->format('M j, Y'),
                            'desc'=>$l['Description'],
                            'amt'=>number_format($l['Amount'],2)
                                );

                        $trs[] = $priceModel->itemMarkup($item, $tbl);

                        $totalAmt += floatval($l['Amount']);

                    } else if ($l['Item_Id'] == ItemId::Discount || $l['Item_Id'] == ItemId::Waive) {

                        $discAmt = floatval($l['Amount']);
                        $totalAmt += $discAmt;

                        $invDate = new DateTime($l['Invoice_Date']);
                        $item = array(
                            'orderNum'=>$r['vid'] . '-' . $r['span'],
                            'date'=>$invDate->format('M j, Y'),
                            'desc'=>$l['Description'],
                            'amt'=>number_format($discAmt,2)
                                );

                        $trs[] = $priceModel->itemMarkup($item, $tbl);

                    } else if ($l['Item_Id'] == ItemId::LodgingMOA && $l['Amount'] < 0) {

                        $moaAmt = floatval($l['Amount']);
                        $totalAmt += $moaAmt;

                        $invDate = new DateTime($l['Invoice_Date']);
                        $item = array(
                            'orderNum'=>$r['vid'] . '-' . $r['span'],
                            'date'=>$invDate->format('M j, Y'),
                            'desc'=>$l['Description'],
                            'amt'=>number_format($moaAmt,2)
                                );

                        $trs[] = $priceModel->itemMarkup($item, $tbl);

                    }
                }
            }

        }

        // For the last visit rate.
        self::addSavedTrs($trs, $tbl);

        // Room Fee totals
        $priceModel->rateTotalMarkup($tbl, $labels->getString('statement', 'roomTotalLabel', 'Lodging Total'), $numberNites, number_format($totalAmt, 2), $guestNites);


        // Room Donations & retained loging fees
        $donAmt = 0;
        $donTitle = '';
        $moaAmt = 0;
        $moaTitle = '';
        foreach ($invLines as $l) {

            $itemAmount = floatval($l['Amount']);

            if ($l['Item_Id'] == ItemId::LodgingDonate && $itemAmount != 0) {
                $donTitle = $l['Description'];
                $donAmt += $itemAmount;
            }

            if ($l['Item_Id'] == ItemId::LodgingMOA && $itemAmount > 0) {
                $moaTitle = $l['Description'];
                $moaAmt += $itemAmount;
            }
        }

        // Print Donation total
        if ($donAmt != 0) {

            $totalAmt += $donAmt;

            $priceModel->rateTotalMarkup($tbl, $donTitle, '', number_format($donAmt,2), '');
        }

        // Print MOA total
        if ($moaAmt != 0) {

            $totalAmt += $moaAmt;

            $priceModel->rateTotalMarkup($tbl, $moaTitle, '', number_format($moaAmt,2), '');
        }

        // Second total line
        if ($donAmt + $moaAmt != 0) {

            // Room Fee totals
            $priceModel->rateTotalMarkup($tbl, $labels->getString('statement', 'TotalLabel', 'Total'), '', number_format($totalAmt, 2), '');
        }

        return $tbl;
    }

    protected static function makePaymentLine(array $payLines, &$tbl, $tdAttrs, array $descs, $i) {

        if (count($payLines) == 0 && count($descs) > 0) {
            // fake a payment
            // Add top border for each new invoice.
            $attrs = array('style'=>'border-top: 2px solid #2E99DD;');

            $payLines[] = HTMLTable::makeTd(($i['Invoice_Date'] == '' ? '' : date('M j, Y', strtotime($i['Invoice_Date']))), $attrs)
                .HTMLTable::makeTd('', array_merge($attrs, array('colspan'=>'2')))
                .HTMLTable::makeTd('', $attrs)
                .HTMLTable::makeTd('0.00', array('style'=>'text-align:right;border-top: 2px solid #2E99DD;'));

        }

        $rspan = (count($payLines) + count($descs));
        $firstT = TRUE;

        foreach ($payLines as $t) {

            if ($firstT) {

                $tbl->addBodyTr(
                    HTMLTable::makeTd($i['Order_Number'] . '-' . $i['Suborder_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan", 'style'=>'border-top: 2px solid #2E99DD;')))
                    .HTMLTable::makeTd($i['Invoice_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan", 'style'=>'border-top: 2px solid #2E99DD;')))
                    .$t
                    );

                $firstT = FALSE;

            } else {

                $tbl->addBodyTr($t);
            }
        }

        foreach ($descs as $d) {

             if ($firstT) {

                $tbl->addBodyTr(
                    HTMLTable::makeTd($i['Order_Number'] . '-' . $i['Suborder_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan", 'style'=>'border-top: 2px solid #2E99DD;')))
                    .HTMLTable::makeTd($i['Invoice_Number'], array_merge($tdAttrs, array('rowspan'=>"$rspan", 'style'=>'border-top: 2px solid #2E99DD;')))
                        .$d
                        );

                $firstT = FALSE;

             } else {
                $tbl->addBodyTr($d);
             }
        }

    }

    public static function makePaymentsTable($invoices, $invLines, $subsidyId, $returnId, &$totalAmt, $pmtDisclaimer, \Config_Lite $labels, $tdClass = '') {

        // Markup
        $tbl = new HTMLTable();
        $totalPment = 0.0;
        $totalReimbursment = 0.0;

        $numPayments = 0;

        $tdAttrs = array();
        if ($tdClass != '') {
            $tdAttrs['class'] = $tdClass;
        }


        foreach ($invoices as $r) {

            // House discounts
            if ($r['i']['Sold_To_Id'] == $subsidyId) {
                continue;
            }

            // Third party
            if ($r['i']['Bill_Agent'] == 'a') {
                continue;
            }

            $payLines = array();
            $descs = array();

            // Payments
            foreach ($r['p'] as $p) {

                $amt = floatval($p['Payment_Amount']);  // - floatval($p['Payment_Balance']);

                if ($p['Is_Refund'] > 0) {
                    $amt = 0 - $amt;
                }
                $amtStyle = 'text-align:right;';

                if ($p['Payment_Status'] != PaymentStatusCode::Paid) {
                    $amtMkup = HTMLContainer::generateMarkup('span', number_format(floatval($p['Payment_Amount']), 2), array('style'=>'color:red;'));
                    $amtStyle = 'text-align:left;';
                } else {
                    $amtMkup = number_format($amt, 2);
                    $totalPment += $amt;

                    if ($r['i']['Invoice_Balance'] != 0 && $r['i']['Invoice_Balance'] != $r['i']['Invoice_Amount']) {
                        $p['Payment_Status_Title'] = 'Paying';
                    }

                }

                $addnl = '';
                $numPayments++;

                if ($p['idPayment_Method'] == PaymentMethod::Charge || $p['idPayment_Method'] == PaymentMethod::ChgAsCash) {

                    if (isset($p['auths'])) {

                        foreach ($p['auths'] as $a) {

                            if ($a['Card_Type'] != '') {
                                $addnl = $a['Card_Type'] . ' ' . $a['Masked_Account'];
                            }
                        }
                    }


                    $p['Payment_Method_Title'] = 'Credit Card';


                } else if ($p['idPayment_Method'] == PaymentMethod::Check || $p['idPayment_Method'] == PaymentMethod::Transfer) {

                    $addnl = ($p['Check_Number'] == '' ? ' ' : '#' . $p['Check_Number']);
                }

                // Add top border for each new invoice.
                if (count($payLines) == 0) {
                    $attrs = array_merge($tdAttrs, array('style'=>'border-top: 2px solid #2E99DD;'));
                } else {
                    $attrs = $tdAttrs;
                }

                // Style the amount
                $amtAttrs = $attrs;
                if (isset($amtAttrs['style'])) {
                    $amtAttrs['style'] .= $amtStyle;
                } else {
                    $amtAttrs['style'] = $amtStyle;
                }

                $payStatus = $p['Payment_Status_Title'];

                // Catch House returns/refunds
                if ($r['i']['Sold_To_Id'] == $returnId && $p['Is_Refund'] > 0) {
                    $payStatus = 'Return';
                }


                $payLines[] = HTMLTable::makeTd(($p['Payment_Date'] == '' ? '' : date('M j, Y', strtotime($p['Payment_Date']))), $attrs)
                    .($addnl == '' ? HTMLTable::makeTd($p['Payment_Method_Title'], array_merge($attrs, array('colspan'=>'2'))) :
                        (HTMLTable::makeTd($p['Payment_Method_Title'], $attrs) . HTMLTable::makeTd($addnl, $attrs)))
                    .HTMLTable::makeTd($payStatus, $attrs)
                    .HTMLTable::makeTd($amtMkup, $amtAttrs);
            }

            //
            if (count($payLines) > 0 || $r['i']['Invoice_Status'] == InvoiceStatus::Paid) {

                $myLines = array();
                foreach ($invLines as $l) {

                    if ($l['Invoice_Id'] == $r['i']['idInvoice'] || $l['Delegated_Invoice_Id'] == $r['i']['idInvoice']) {

                        // Replace carried lines
                        if ($l['Type_Id'] != InvoiceLineType::Invoice) {
                            $myLines[] = $l;
                        }
                    }
                }

                $first = TRUE;

                foreach ($myLines as $l) {

                    if ($first) {
                        $initialTd = HTMLTable::makeTd('Item' . (count($myLines) > 1 ? 's:' : ':'), array('rowspan'=>count($myLines), 'style'=>'border: 0 none red; text-align:right; font-size:.8em;'));
                        $first = FALSE;
                    } else {
                        $initialTd = '';
                    }

                    $descs[] = $initialTd . HTMLTable::makeTd('$'.number_format($l['Amount'],2) . ';  ' .$l['Description'], array('colspan'=>'4', 'style'=>'font-size:.8em'));
                    $numPayments++;

                }

            }

            self::makePaymentLine($payLines, $tbl, $tdAttrs, $descs, $r['i']);

        }


        if ($numPayments > 0) {

            $tbl->addHeaderTr(
                    HTMLTable::makeTh('Visit Id', $tdAttrs)
                    .HTMLTable::makeTh('Invoice', $tdAttrs)
                    .HTMLTable::makeTh('Date', $tdAttrs)
                    .HTMLTable::makeTh('Type / Item(s)', array_merge($tdAttrs, array('colspan'=>'2')))
                    .HTMLTable::makeTh('Status', $tdAttrs)
                    .HTMLTable::makeTh($labels->getString('statement', 'paymentHeader', 'Payment'), $tdAttrs));

            $blackLine = 'hhk-tdTotals ';

            if ($totalReimbursment > 0) {

                $tbl->addBodyTr(HTMLTable::makeTd('Total Reimbursed', array('colspan'=>'6', 'class'=>'tdlabel '.$blackLine.$tdClass, 'style'=>'font-weight:bold;'))
                    .HTMLTable::makeTd('$'. number_format($totalReimbursment, 2), array('class'=>'hhk-tdTotals '.$tdClass, 'style'=>'text-align:right;')));

                $blackLine = '';
            }

            $guestPayment = $totalPment - $totalReimbursment;
            $tbl->addBodyTr(HTMLTable::makeTd('Guest ' . $labels->getString('statement', 'paymentTotalLabel', 'Payment Total (Thank You!)'), array('colspan'=>'6', 'class'=>'tdlabel '.$blackLine.$tdClass, 'style'=>'font-weight:bold;'))
                .HTMLTable::makeTd('$'. number_format($guestPayment, 2), array('class'=>$tdClass.$blackLine, 'style'=>'text-align:right;')));

            // Totals Line needed?
            if ($totalReimbursment > 0) {
                $tbl->addBodyTr(HTMLTable::makeTd('Total Payments', array('colspan'=>'6', 'class'=>'tdlabel hhk-tdTotals '.$tdClass, 'style'=>'font-weight:bold;'))
                    .HTMLTable::makeTd('$'. number_format($totalPment, 2), array('class'=>'hhk-tdTotals '.$tdClass, 'style'=>'text-align:right;')));
            }

        } else if ($numPayments == 0) {
            $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('statement', 'noPaymentsRecordedLabel', 'No Payments Recorded'), array('colspan'=>'7', 'style'=>'font-style:italic;', 'class'=>$tdClass)));
        }

        $totalAmt = $totalAmt - $totalPment;
        // Disclaimer
        if ($pmtDisclaimer != '') {
            $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $pmtDisclaimer, array('style'=>'font-size:0.6em;text-align:justify;max-width:600px;')), array('colspan'=>'7', 'class'=>$tdClass)));
        }

        return $tbl;
    }

    public static function makeThirdParyTable($invoices, $invLines,  Config_Lite $labels, &$totAmt, $tdClass = '') {

        $tbl = new HTMLTable();
        $totalPment = 0.0;
        $numPayments = 0;

        $tdAttrs = array();
        if ($tdClass != '') {
            $tdAttrs['class'] = $tdClass;
        }

        foreach ($invoices as $r) {

            // Only billing agents.
            if ($r['i']['Bill_Agent'] != 'a') {
                continue;
            }

            $myLines = array();

            foreach ($invLines as $l) {

                if ($l['Invoice_Id'] == $r['i']['idInvoice'] || $l['Delegated_Invoice_Id'] == $r['i']['idInvoice']) {
                    // Replace carried lines
                    if ($l['Type_Id'] != InvoiceLineType::Invoice) {
                        $myLines[] = $l;
                        $totalPment += $l['Amount'];
                    }
                }
            }

            if (count($myLines) > 0) {

                $numPayments++;
                $first = TRUE;
                $descs = array();

                foreach ($myLines as $l) {

                    if ($first) {

                        $payor = $r['i']['Company'];
                        if ($payor == '') {
                            $payor = $r['i']['First'] . ' ' . $r['i']['Last'];
                        }

                        $mattrs = array_merge($tdAttrs, array('style'=>'border-top: 2px solid #2E99DD;'));
                        $vattrs = array_merge($tdAttrs, array('style'=>'border-top: 2px solid #2E99DD;text-align:right;'));

                        $initialTd = HTMLTable::makeTd($r['i']['Order_Number'] . '-' . $r['i']['Suborder_Number'], array_merge($tdAttrs, array('rowspan'=>count($myLines), 'style'=>'border-top: 2px solid #2E99DD;')))
                        .HTMLTable::makeTd($payor, array_merge($tdAttrs, array('rowspan'=>count($myLines), 'style'=>'border-top: 2px solid #2E99DD;')));

                        $first = FALSE;

                    } else {
                        $initialTd = '';
                        $mattrs = $tdAttrs;
                        $vattrs = array_merge($tdAttrs, array('style'=>'text-align:right;'));
                    }

                    $descs[] = $initialTd . HTMLTable::makeTd(($r['i']['Invoice_Date'] == '' ? '' : date('M j, Y', strtotime($r['i']['Invoice_Date']))), $mattrs)
                        .HTMLTable::makeTd($l['Description'], array_merge($mattrs, array('colspan'=>'3')))
                        .HTMLTable::makeTd('$'.number_format($l['Amount'],2), $vattrs);

                }

                foreach ($descs as $d) {
                    $tbl->addBodyTr($d);
                }
            }

        }

//        $oldAmt = $totAmt;
        $totAmt -= $totalPment;

        if ($numPayments > 0) {
            $tbl->addHeaderTr(
                    HTMLTable::makeTh('Visit Id', $tdAttrs)
                    .HTMLTable::makeTh('Organization', $tdAttrs)
                    .HTMLTable::makeTh('Date', $tdAttrs)
                    .HTMLTable::makeTh('Item', array_merge($tdAttrs, array('colspan'=>'3')))
                    .HTMLTable::makeTh($labels->getString('statement', 'paymentHeader', 'Payment'), $tdAttrs));

            $tbl->addBodyTr(HTMLTable::makeTd('Payment Total', array('colspan'=>'6', 'class'=>'tdlabel hhk-tdTotals '.$tdClass, 'style'=>'font-weight:bold;'))
                .HTMLTable::makeTd('$'. number_format($totalPment, 2), array('class'=>'hhk-tdTotals '.$tdClass, 'style'=>'font-weight:bold;text-align:right;')));

        }


        if ($numPayments > 0) {
            return $tbl->generateMarkup();
        }else {
            return '';
        }
    }

    public static function createComprehensiveStatements(\PDO $dbh, $spans, $idRegistration, $guestName, $priceModel) {

        $uS = Session::getInstance();


        if (count($spans) == 0) {
            return 'Visits Not Found.  ';
        }

        $idPsg = intVal($spans[0]['idPsg']);

        // Hospital
        $hospital = '';
        if ($spans[0]['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idAssociation']][1] . ' / ';
        }
        if ($spans[0]['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idHospital']])) {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idHospital']][1];
        }

        // Collect rates and rooms
        $rates = self::processRatesRooms($spans);

        $totalAmt = 0.00;
        $totalNights = 0;

        // Get labels & config
        $labels = new Config_Lite(LABEL_FILE);
        $config = new Config_Lite(ciCFG_FILE);

        // Payments
        $query = "select lp.*, ifnull(n.Name_First, '') as `First`,
    ifnull(n.Name_Last, '') as `Last`,
    ifnull(n.Company, '') as `Company`
from vlist_inv_pments lp
    left join
    `name` n ON lp.Sold_To_Id = n.idName
 where lp.idGroup = $idRegistration and lp.Deleted = 0 ";
        $stmt = $dbh->query($query);

        $pments = self::processPayments($stmt, array('Last', 'First', 'Company'));

        // items
        $ilStmt = $dbh->query("select il.Invoice_Id, il.idInvoice_line, il.Type_Id, il.Amount, il.Description, il.Item_Id, i.Delegated_Invoice_Id, i.Order_Number, i.Suborder_Number, i.Invoice_Date
from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
where i.Deleted = 0 and il.Deleted = 0 and i.idGroup = $idRegistration order by i.idGroup, il.Invoice_Id, il.idInvoice_line");

        $invLines = $ilStmt->fetchAll(\PDO::FETCH_ASSOC);


        // Visits and Rates
        $tbl = self::makeOrdersRatesTable($rates, $totalAmt, $priceModel, $labels, $invLines, $totalNights);
        $totalCharge = $totalAmt;

        // Thirdparty payments
        $tpTbl = self::makeThirdParyTable($pments, $invLines, $labels, $totalAmt);
        $totalThirdPayments = $totalCharge - $totalAmt;

        $ptbl = self::makePaymentsTable($pments, $invLines, $uS->subsidyId, $uS->returnId, $totalAmt, $config->getString('financial', 'PaymentDisclaimer', ''), $labels);
        $totalGuestPayments = $totalCharge - $totalThirdPayments - $totalAmt;

        // Find patient name
        $patientName = '';
        if ($idPsg > 0){

            $pstmt = $dbh->query("select n.Name_First, n.Name_Last from name n left join hospital_stay hs on n.idName = hs.idPatient where hs.idPsg = $idPsg");
            $rows = $pstmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $patientName = $rows[0]['Name_First'] . ' ' . $rows[0]['Name_Last'];
            }
        }


        // Build the statement
        $logoUrl = $config->getString('financial', 'statementLogoFile', '');
        $rec = '';

        // Don't write img if logo URL not sepcified
        if ($logoUrl != '') {

            $rec .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('img', '', array('src'=>$logoUrl, 'id'=>'hhkrcpt', 'alt'=>$uS->siteName, 'width'=>$config->getString('financial', 'statementLogoWidth', '220'))),
                array('style'=>'margin-bottom:10px;margin-right:20px;float:left;'));
        }

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $uS->sId), array('style'=>'float:left;margin-bottom:1em;'));
        $rec .= HTMLContainer::generateMarkup('h2', 'Comprehensive Statement of Account', array('style'=>'clear:both;margin-bottom:1em;'));


        $rec .= self::makeSummaryDiv($guestName, $patientName, $hospital, $idPsg, $labels, $totalCharge, $totalThirdPayments, $totalGuestPayments, Registration::loadLodgingBalance($dbh, $idRegistration), $totalNights);

        $rec .= HTMLContainer::generateMarkup('h4', $labels->getString('statement', 'datesChargesCaption', 'Visit Dates & Room Charges'), array('style'=>'margin-top:25px;'));
        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('class'=>'hhk-tdbox'));

        if ($tpTbl != '') {
            $rec .= HTMLContainer::generateMarkup('h4', $labels->getString('statement', 'thirdParty', '3rd Party'). ' Payments', array('style'=>'margin-top:15px;'));
            $rec .= HTMLContainer::generateMarkup('div', $tpTbl, array('style'=>'margin-bottom:10px;', 'class'=>'hhk-tdbox'));
        }

        $rec .= HTMLContainer::generateMarkup('h4', $labels->getString('statement', 'paymentsCaption', 'Payments'), array('style'=>'margin-top:15px;'));
        $rec .= HTMLContainer::generateMarkup('div', $ptbl->generateMarkup(), array('style'=>'margin-bottom:10px;', 'class'=>'hhk-tdbox'));

        return $rec;

    }

    public static function createStatementMarkup(\PDO $dbh, $idVisit, $guestName) {

        $uS = Session::getInstance();
        $spans = array();
        $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        if ($idVisit > 0) {
            $spans = $priceModel->loadVisitNights($dbh, $idVisit);
        } else {
            return 'Missing Input pararmeters.  ';
        }


        if (count($spans) == 0) {
            return 'Visit Not Found.  ';
        }

        $idPsg = intval($spans[0]['idPsg']);
        $idRegistration = intval($spans[0]['idRegistration']);

        // Hospital
        $hospital = '';
        if ($spans[0]['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idAssociation']][1] != '(None)') {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idAssociation']][1] . ' / ';
        }
        if ($spans[0]['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idHospital']])) {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$spans[0]['idHospital']][1];
        }

        // Payments
        $query = "select lp.*, ifnull(n.Name_First, '') as `First`,
    ifnull(n.Name_Last, '') as `Last`,
    ifnull(n.Company, '') as `Company`
from vlist_inv_pments lp
    left join
    `name` n ON lp.Sold_To_Id = n.idName
 where lp.Order_Number = $idVisit and lp.Deleted = 0 ";
        $stmt = $dbh->query($query);

        $pments = self::processPayments($stmt, array('Last', 'First', 'Company'));

        // Items
        $ilStmt = $dbh->query("select il.Invoice_Id, il.idInvoice_line, il.Type_Id, il.Amount, il.Description, il.Item_Id, i.Delegated_Invoice_Id, i.Order_Number, i.Suborder_Number, i.Invoice_Date
from invoice_line il join invoice i on il.Invoice_Id = i.idInvoice
where i.Deleted = 0 and il.Deleted = 0 and i.Order_Number = $idVisit order by il.Invoice_Id, il.idInvoice_line");

        $invLines = $ilStmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalAmt = 0.00;
        $totalNights = 0;

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);
        $config = new Config_Lite(ciCFG_FILE);

        // Visits and Rates
        $tbl = self::makeOrdersRatesTable(self::processRatesRooms($spans), $totalAmt, $priceModel, $labels, $invLines, $totalNights);
        $totalCharge = $totalAmt;

        // Thirdparty payments
        $tpTbl = self::makeThirdParyTable($pments, $invLines, $labels, $totalAmt);
        $totalThirdPayments = $totalCharge - $totalAmt;

        // Payments
        $ptbl = self::makePaymentsTable($pments, $invLines, $uS->subsidyId, $uS->returnId, $totalAmt, $config->getString('financial', 'PaymentDisclaimer', ''), $labels);
        $totalGuestPayments = $totalCharge - $totalThirdPayments - $totalAmt;

        // Find patient name
        $patientName = '';
        if ($idPsg > 0){

            $pstmt = $dbh->query("select n.Name_First, n.Name_Last from name n left join hospital_stay hs on n.idName = hs.idPatient where hs.idPsg = $idPsg");
            $rows = $pstmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                $patientName = $rows[0]['Name_First'] . ' ' . $rows[0]['Name_Last'];
            }
        }


        // Build the statement
        $logoUrl = $config->getString('financial', 'statementLogoFile', '');
        $rec = '';

        // Don't write img if logo URL not sepcified
        if ($logoUrl != '') {

            $rec .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('img', '', array('src'=>$logoUrl, 'id'=>'hhkrcpt', 'alt'=>$uS->siteName, 'width'=>$config->getString('financial', 'statementLogoWidth', '220'))),
                array('style'=>'margin-bottom:10px;margin-right:20px;float:left;'));
        }

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $uS->sId), array('style'=>'float:left;margin-bottom:1em;'));

        $rec .= HTMLContainer::generateMarkup('h2', 'Statement of Account', array('style'=>'clear:both;margin-bottom:1em;'));

        $rec .= self::makeSummaryDiv($guestName, $patientName, $hospital, $idPsg, $labels, $totalCharge, $totalThirdPayments, $totalGuestPayments, Registration::loadLodgingBalance($dbh, $idRegistration), $totalNights);

        $rec .= HTMLContainer::generateMarkup('h4', $labels->getString('statement', 'datesChargesCaption', 'Visit Dates & Room Charges'), array('style'=>'clear:both;margin-top:25px;'));
        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('class'=>'hhk-tdbox'));

        if ($tpTbl != '') {
            $rec .= HTMLContainer::generateMarkup('h4', $labels->getString('statement', 'thirdParty', '3rd Party'). ' Payments', array('style'=>'clear:both;margin-top:15px;'));
            $rec .= HTMLContainer::generateMarkup('div', $tpTbl, array('style'=>'margin-bottom:10px;', 'class'=>'hhk-tdbox'));
        }

        $rec .= HTMLContainer::generateMarkup('h4', $labels->getString('statement', 'paymentsCaption', 'Payments'), array('style'=>'margin-top:15px;'));
        $rec .= HTMLContainer::generateMarkup('div', $ptbl->generateMarkup(), array('style'=>'margin-bottom:10px;', 'class'=>'hhk-tdbox'));

        return $rec;

    }

    protected static function makeSummaryDiv($guestName, $patientName, $hospital, $idPsg, $labels, $totalCharge, $totalThirdPayments, $totalGuestPayments, $MOABalance, $totalNights) {

        $tbl = new HTMLTable();

        $tbl->addBodyTr(HTMLTable::makeTd('Guest:', array('class'=>'tdlabel')) . HTMLTable::makeTd($guestName));
        $tbl->addBodyTr(HTMLTable::makeTd($labels->getString('MemberType', 'patient', 'Patient') . ':', array('class'=>'tdlabel')) . HTMLTable::makeTd($patientName));
        //$tbl->addBodyTr(HTMLTable::makeTd($labels->getString('statement', 'psgLabel', 'Patient Support Group') . ' Id: ' . $idPsg, array('colspan'=>'2', 'style'=>'font-size:.8em;')));
        $tbl->addBodyTr(HTMLTable::makeTd('Provider:', array('class'=>'tdlabel')) . HTMLTable::makeTd($hospital));

        // Set up balance prompt ..
        $bal = $totalCharge - ($totalThirdPayments + $totalGuestPayments);
        if ($bal > 0) {
            $finalWord = $labels->getString('statement', 'balanceDueLabel', 'Current Balance Due');
        } else if ($bal == 0) {
            $finalWord = $labels->getString('statement', 'zeroBalanceLabel', 'Current Balance');
        } else {
            $finalWord = $labels->getString('statement', 'guestCreditLabel', 'Guest Credit');
            $bal = abs($bal);
        }

        $sTbl = new HTMLTable();

        $sTbl->addBodyTr(
                HTMLTable::makeTd('Total Nights:', array('class'=>'tdlabel', 'style'=>'border-bottom: 2px solid #2E99DD;'))
                . HTMLTable::makeTd(number_format($totalNights, 0), array('style'=>'text-align:center;border-bottom: 2px solid #2E99DD;')));

        $sTbl->addBodyTr(
                HTMLTable::makeTd($labels->getString('statement', 'TotalLabel', 'Total') . ':', array('class'=>'tdlabel', 'style'=>'border-bottom: 2px solid #2E99DD;'))
                . HTMLTable::makeTd('$'. number_format($totalCharge, 2), array('style'=>'text-align:right;border-bottom: 2px solid #2E99DD;')));

        if ($totalThirdPayments > 0) {
            $sTbl->addBodyTr(
                HTMLTable::makeTd('3rd Party Payments:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('$'. number_format($totalThirdPayments, 2), array('style'=>'text-align:right;')));
        }

        $sTbl->addBodyTr(
                HTMLTable::makeTd('Guest Payments:', array('class'=>'tdlabel', 'style'=>'border-bottom: 2px solid #2E99DD;'))
                . HTMLTable::makeTd('$'. number_format($totalGuestPayments, 2), array('style'=>'text-align:right;border-bottom: 2px solid #2E99DD;')));

        $sTbl->addBodyTr(
                HTMLTable::makeTd($finalWord . ':', array('class'=>'tdlabel', 'style'=>'font-weight:bold;font-size:1.2em;'))
                . HTMLTable::makeTd('$'. number_format($bal, 2), array('style'=>'text-align:right;font-weight:bold;font-size:1.2em;')));


        if ($MOABalance > 0) {
            $sTbl->addBodyTr(
                HTMLTable::makeTd('Money on Account:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd('($'. number_format($MOABalance, 2) . ')', array('style'=>'text-align:right;')));
        }

        $rec = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(array(),
                    HTMLContainer::generateMarkup('span', 'Prepared '.date('M jS, Y'), array('style'=>'font-weight:bold;')))
                    , array('style'=>'float:left;'))
                . HTMLContainer::generateMarkup('div', $sTbl->generateMarkup(array(),
                    HTMLContainer::generateMarkup('span', 'Statement Summary', array('style'=>'font-weight:bold;')))
                    , array('style'=>'float:left;margin-left:100px;'));

        return HTMLContainer::generateMarkup('div', $rec, array('style'=>'clear:both;')).HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));
    }

}
