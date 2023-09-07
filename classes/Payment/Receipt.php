<?php

namespace HHK\Payment;

use HHK\Payment\Invoice\Invoice;
use HHK\Payment\PaymentResponse\AbstractPaymentResponse;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\SysConst\{ItemId};
use HHK\SysConst\MemBasis;
use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\House\Registration;
use HHK\Member\AbstractMember;

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

    /**
     * Summary of makeInvoiceLineMarkup
     * @param \PDO $dbh
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @param mixed $tbl
     * @return void
     */
	protected static function makeInvoiceLineMarkup(\PDO $dbh, Invoice $invoice, &$tbl) {
		$uS = Session::getInstance();

		// Taxes
		$tax = floatval($uS->ImpliedTaxRate)/100;

		if ($tax > 0) {
			// Implement tax
			$taxAmt = 0;

			foreach ($invoice->getLines($dbh) as $line) {

				$lineAmt = $line->getAmount();

				// Tax on lodging only
				if ($line->getItemId() == ItemId::Lodging) {
					$lineAmt = round($line->getAmount() / (1 + $tax), 2);
					$taxAmt += $line->getAmount() - $lineAmt;
				}

				$tbl->addBodyTr(HTMLTable::makeTd($line->getDescription() . ':', array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd(number_format($lineAmt, 2), array('style'=>'font-size:.8em;')));
			}

			// Tax amount
			if ($taxAmt > 0) {
				$tbl->addBodyTr(HTMLTable::makeTd('Taxes (' . $tax*100 . '%):', array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd(number_format($taxAmt, 2), array('style'=>'font-size:.8em;')));
			}

		} else {
			// No taxes.
			foreach ($invoice->getLines($dbh) as $line) {
				$tbl->addBodyTr(HTMLTable::makeTd($line->getDescription() . ':', array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd(number_format($line->getAmount(), 2), array('style'=>'font-size:.8em;')));
			}
		}
	}

    /**
     * Summary of createSaleMarkup
     * @param \PDO $dbh
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @param mixed $siteName
     * @param mixed $siteId
     * @param \HHK\Payment\PaymentResponse\AbstractPaymentResponse $payResp
     * @return string
     */
    public static function createSaleMarkup(\PDO $dbh, Invoice $invoice, $siteName, $siteId, AbstractPaymentResponse $payResp) {

        $uS = Session::getInstance();

        // Assemble the statement
        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh($siteName . " Receipt", array('colspan'=>'2')));

        $info = self::getVisitInfo($dbh, $invoice);

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd( Labels::getString('MemberType', 'primaryGuest', 'Primary Guest') . ": ", array('class'=>'tdlabel', 'style'=>"vertical-align: top;"))
                . HTMLTable::makeTd($info['Primary_Guest'] . ($uS->showAddressReceipt ? self::getAddressTable($dbh, $idPriGuest, false) : '')));
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = AbstractMember::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel')) . HTMLTable::makeTd(date('D M jS, Y', strtotime($payResp->getPaymentDate()))));

        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getInvoiceNumber()));

        self::makeInvoiceLineMarkup($dbh, $invoice, $tbl);

        //Total Amount
        $tbl->addBodyTr(HTMLTable::makeTd("Total:", array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format($invoice->getAmount(), 2), array('class'=>'hhk-tdTotals')));


        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);

        if ($invoice->getBalance() > 0 || $invoice->getAmount() != $payResp->getAmount()) {
            $tbl->addBodyTr(HTMLTable::makeTd("Remaining Balance:", array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format(($invoice->getBalance()), 2)));
        }

        $disclaimer = '';

        if ($uS->PaymentDisclaimer != '') {
            $disclaimer = HTMLContainer::generateMarkup('div', $uS->PaymentDisclaimer, array('style'=>'font-size:0.7em; text-align:justify'));
        }

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup() . $disclaimer, array('style'=>'margin-bottom:10px;clear:both;float:left;'));
        $rec .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'hhk-receiptMarkup', 'style'=>'display:block;padding:10px;'));
    }

    /**
     * Summary of createVoidMarkup
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractPaymentResponse $payResp
     * @param mixed $siteName
     * @param mixed $siteId
     * @param mixed $type
     * @return string
     */
    public static function createVoidMarkup(\PDO $dbh, AbstractPaymentResponse $payResp, $siteName, $siteId, $type = 'Void Sale') {

        $uS = Session::getInstance();

        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh($siteName . ' ' . $type . " Receipt", array('colspan'=>'2')));

        $invoice = new Invoice($dbh, $payResp->getInvoiceNumber());
        $info = self::getVisitInfo($dbh, $invoice);

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd(Labels::getString('MemberType', 'primaryGuest', 'Primary Guest') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest'] . ($uS->showAddressReceipt ? self::getAddressTable($dbh, $idPriGuest, false) : '')));
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = AbstractMember::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }


        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel'))
                . HTMLTable::makeTd(date('D M jS, Y', strtotime($payResp->getPaymentDate()))));

        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoiceNumber()));

        $tbl->addBodyTr(HTMLTable::makeTd("Total Voided:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($payResp->getAmount(), 2)));

        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin-bottom:10px;clear:both;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'receiptMarkup;', 'style'=>'display:block;padding:10px;'));
    }

    // Return a Payment
    /**
     * Summary of createReturnMarkup
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractPaymentResponse $payResp
     * @param mixed $siteName
     * @param mixed $siteId
     * @return string
     */
    public static function createReturnMarkup(\PDO $dbh, AbstractPaymentResponse $payResp, $siteName, $siteId) {

        $uS = Session::getInstance();

        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();

        $tbl->addBodyTr(HTMLTable::makeTh($siteName . " Return Receipt", array('colspan'=>'2')));

        $invoice = new Invoice($dbh, $payResp->getInvoiceNumber());
        $info = self::getVisitInfo($dbh, $invoice);

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd(Labels::getString('MemberType', 'primaryGuest', 'Primary Guest') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest'] . ($uS->showAddressReceipt ? self::getAddressTable($dbh, $idPriGuest, false) : '')));
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = AbstractMember::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel'))
                . HTMLTable::makeTd(date('D M jS, Y', strtotime($payResp->getPaymentDate()))));

        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoiceNumber()));

        self::makeInvoiceLineMarkup($dbh, $invoice, $tbl);

        $tbl->addBodyTr(HTMLTable::makeTd("Total Returned:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($payResp->getAmount(), 2)));

        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin-bottom:10px;clear:both;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'receiptMarkup;', 'style'=>'display:block;padding:10px;'));
    }

    // Refund arbitrary Amount
    /**
     * Summary of createRefundAmtMarkup
     * @param \PDO $dbh
     * @param \HHK\Payment\PaymentResponse\AbstractPaymentResponse $payResp
     * @param mixed $siteName
     * @param mixed $siteId
     * @return string
     */
    public static function createRefundAmtMarkup(\PDO $dbh, AbstractPaymentResponse $payResp, $siteName, $siteId) {

        $uS = Session::getInstance();

        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();

        $tbl->addBodyTr(HTMLTable::makeTh($siteName . " Refund Receipt", array('colspan'=>'2')));

        $invoice = new Invoice($dbh, $payResp->getInvoiceNumber());
        $info = self::getVisitInfo($dbh, $invoice);

        $idPriGuest = 0;
        if (isset($info['idPrimaryGuest'])) {
            $idPriGuest = $info['idPrimaryGuest'];
        }

        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd(Labels::getString('MemberType', 'primaryGuest', 'Primary Guest') . ": ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest'] . ($uS->showAddressReceipt ? self::getAddressTable($dbh, $idPriGuest, false) : '')));
        }

        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
            $payor = AbstractMember::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));

        if (isset($info['Room']) && $info['Room'] != '') {
            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
        }

        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel'))
                . HTMLTable::makeTd(date('D M jS, Y g:ia', strtotime($payResp->getPaymentDate()))));

        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoiceNumber()));

        self::makeInvoiceLineMarkup($dbh, $invoice, $tbl);

        $tbl->addBodyTr(HTMLTable::makeTd("Total Refunded:", array('class'=>'tdlabel')) . HTMLTable::makeTd(number_format($payResp->getAmount(), 2)));

        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin-bottom:10px;clear:both;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'receiptMarkup;', 'style'=>'display:block;padding:10px;'));
    }

    /**
     * Summary of createDeclinedMarkup
     * @param \PDO $dbh
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @param mixed $siteName
     * @param int $siteId
     * @param \HHK\Payment\PaymentResponse\AbstractPaymentResponse $payResp
     * @return string
     */
    public static function createDeclinedMarkup(\PDO $dbh, Invoice $invoice, $siteName, $siteId, AbstractPaymentResponse $payResp) {

        // Assemble the statement
        $rec = self::getHouseIconMarkup();

        $rec .= HTMLContainer::generateMarkup('div', self::getAddressTable($dbh, $siteId), array('style'=>'float:left;margin-bottom:10px;'));

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh($siteName . " Receipt", array('colspan'=>'2')));

//        $info = self::getVisitInfo($dbh, $invoice);
//
//        if (isset($info['Primary_Guest']) && $info['Primary_Guest'] != '') {
//            $tbl->addBodyTr(HTMLTable::makeTd("Guest: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Primary_Guest']));
//        }
//
//        $idPriGuest = 0;
//        if (isset($info['idPrimaryGuest'])) {
//            $idPriGuest = $info['idPrimaryGuest'];
//        }
//
//        if ($payResp->idPayor > 0 && $payResp->idPayor != $idPriGuest) {
//            $payor = Member::GetDesignatedMember($dbh, $payResp->idPayor, MemBasis::Indivual);
//            $tbl->addBodyTr(HTMLTable::makeTd("Payor: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($payor->getMemberName()));
//        }
//
//        $tbl->addBodyTr(HTMLTable::makeTd("Visit Id: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($invoice->getOrderNumber() . '-' . $invoice->getSuborderNumber()));
//
//        if (isset($info['Room']) && $info['Room'] != '') {
//            $tbl->addBodyTr(HTMLTable::makeTd("Room: ", array('class'=>'tdlabel')) . HTMLTable::makeTd($info['Room']));
//        }

        $tbl->addBodyTr(HTMLTable::makeTd("Date: ", array('class'=>'tdlabel')) . HTMLTable::makeTd(date('D M jS, Y', strtotime($payResp->getPaymentDate()))));

//        $tbl->addBodyTr(HTMLTable::makeTd("Invoice:", array('class'=>'tdlabel')) . HTMLTable::makeTd($payResp->getInvoiceNumber()));
//
//        foreach ($invoice->getLines($dbh) as $line) {
//            $tbl->addBodyTr(HTMLTable::makeTd($line->getDescription() . ':', array('class'=>'tdlabel', 'style'=>'font-size:.8em;')) . HTMLTable::makeTd(number_format($line->getAmount(), 2), array('style'=>'font-size:.8em;')));
//        }

        $tbl->addBodyTr(HTMLTable::makeTd("Total:", array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format($invoice->getAmount(), 2), array('class'=>'hhk-tdTotals')));

        $tbl->addBodyTr(HTMLTable::makeTd("Payment Declined", array('colspan'=>'2', 'style'=>'font-weight:bold; text-align:center;')));

        // Create pay type determined markup
        $payResp->receiptMarkup($dbh, $tbl);

        if ($invoice->getBalance() > 0 || $invoice->getAmount() != $payResp->getAmount()) {
            $tbl->addBodyTr(HTMLTable::makeTd("Remaining Balance:", array('class'=>'tdlabel')) . HTMLTable::makeTd('$'.number_format(($invoice->getBalance()), 2)));
        }


        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'margin-bottom:10px;clear:both;background-color:pink;'));
        $rec .= HTMLContainer::generateMarkup('div', '', array('style'=>'clear:both;'));

        return HTMLContainer::generateMarkup('div', $rec, array('id'=>'hhk-receiptMarkup', 'style'=>'display:block;padding:10px;'));
    }

    /**
     * Summary of getHouseIconMarkup
     * @return string
     */
    public static function getHouseIconMarkup() {

        $uS = Session::getInstance();

        $logoUrl = $uS->resourceURL . 'conf/' . $uS->receiptLogoFile;
        $rec = '';

        // Don't write img if logo URL not sepcified
        if ($logoUrl != '') {

            $rec .= HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('img', '', array('src'=>$logoUrl, 'id'=>'hhkrcpt', 'alt'=>$uS->siteName, 'width'=>$uS->receiptLogoWidth)),
                array('style'=>'margin-bottom:10px;margin-right:20px;float:left;'));
        }

        return $rec;

    }

    /**
     * Summary of getVisitInfo
     * @param \PDO $dbh
     * @param \HHK\Payment\Invoice\Invoice $invoice
     * @return mixed
     */
    public static function getVisitInfo(\PDO $dbh, Invoice $invoice) {

        $data = array();

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

            if (isset($r[0])) {
                $data = $r[0];

                if ($data['Assoc'] != '' && $data['Assoc'] != '(None)') {
                    $data['HospName'] = $data['Assoc'] . '/' . $data['Hospital'];
                } else {
                    $data['HospName'] = $data['Hospital'];
                }
            }

        } catch (\PDOException $pex) {
            $data = array();
        }

        return $data;
    }

    /**
     * Summary of getHospitalNames
     * @param \PDO $dbh
     * @param mixed $orderNumber
     * @return mixed
     */
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
            } catch (\PDOException $pex) {
                $hsNames = '';
            }
        }

        return $hsNames;
    }

    /**
     * Summary of getAddressTable
     * @param \PDO $dbh
     * @param int $idName
     * @param bool $includeContact
     * @return string
     */
    public static function getAddressTable(\PDO $dbh, $idName, $includeContact = true) {

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

                if($includeContact){
                    if ($rows[0]['Phone'] != '') {
                        $adrTbl->addBodyTr(HTMLTable::makeTd('Phone: ' . $rows[0]['Phone']));
                    }

                    if ($rows[0]['Email'] != '') {
                        $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['Email']));
                    }

                    if ($rows[0]['Web_Site'] != '') {
                        $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['Web_Site']));
                    }
                }

                $mkup = $adrTbl->generateMarkup();
            }
        }

        return $mkup;
    }


}
?>