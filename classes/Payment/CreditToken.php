<?php

namespace HHK\Payment;

use HHK\SysConst\{MpTranType};
use HHK\Tables\EditRS;
use HHK\Tables\PaymentGW\Guest_TokenRS;
use HHK\Exception\RuntimeException;
use HHK\Payment\GatewayResponse\GatewayResponseInterface;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Labels;

/**
 * CreditToken.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * Description of CreditToken
 *
 * @author Eric
 */
class CreditToken {


    const TOKEN_LIFE_DAYS = 365;

    /**
     * Summary of storeToken
     * @param \PDO $dbh
     * @param int $idRegistration
     * @param int $idPayor
     * @param \HHK\Payment\GatewayResponse\GatewayResponseInterface $vr
     * @param int $idToken
     * @return int
     */
    public static function storeToken(\PDO $dbh, $idRegistration, $idPayor, GatewayResponseInterface $vr, $idToken = 0) {

        if ($vr->saveCardonFile() === FALSE || $vr->getToken() == '') {
            return 0;
        }

        $cardNum = $vr->getMaskedAccount();

        if ($idToken > 0) {
            $gtRs = self::getTokenRsFromId($dbh, $idToken);
        } else {
            $gtRs = self::findTokenRS($dbh, $idPayor, $vr->getCardHolderName(), $vr->getCardType(), $cardNum, $vr->getMerchant());
        }

        // Load values
        $gtRs->idGuest->setNewVal($idPayor);
        $gtRs->idRegistration->setNewVal($idRegistration);
        $gtRs->Merchant->setNewVal($vr->getMerchant());

        if (trim($vr->getCardHolderName()) != '') {
            $gtRs->CardHolderName->setNewVal($vr->getCardHolderName());
        }
        $gtRs->CardType->setNewVal($vr->getCardType());

        if ($vr->getExpDate() != '') {
            $gtRs->ExpDate->setNewVal($vr->getExpDate());
        }
        $gtRs->Frequency->setNewVal('OneTime');
        $gtRs->Granted_Date->setNewVal(date('Y-m-d H:i:s'));
        $gtRs->LifetimeDays->setNewVal(self::TOKEN_LIFE_DAYS);

        if ($cardNum != '') {
            $gtRs->MaskedAccount->setNewVal($cardNum);
        }

        if ($vr->getOperatorID() != '') {
            $gtRs->OperatorID->setNewVal($vr->getOperatorID());
        }
        $gtRs->Response_Code->setNewVal($vr->getResponseCode());
        $gtRs->Status->setNewVal($vr->getStatus());
        $gtRs->StatusMessage->setNewVal($vr->getMessage());
        $gtRs->Tran_Type->setNewVal($vr->getTranType());
        
        if($gtRs->Token->getStoredVal() == ''){
            $gtRs->Token->setNewVal($vr->getToken());
        }

        $runTot = self::calculateRunningTotal($gtRs->Running_Total->getStoredVal(), $vr->getAuthorizedAmount(), $vr->getTranType());
        $gtRs->Running_Total->setNewVal($runTot);

        // Write
        if ($gtRs->idGuest_token->getStoredVal() > 0) {
            // Update
        	$gtRs->Last_Updated->resetNewVal();
        	EditRS::update($dbh, $gtRs, array($gtRs->idGuest_token));
            $idToken = $gtRs->idGuest_token->getStoredVal();
        } else {
            //Insert
            $idToken = EditRS::insert($dbh, $gtRs);
        }

        return $idToken;
    }

    /**
     * Summary of calculateRunningTotal
     * @param mixed $runTot
     * @param mixed $rawAmount
     * @param mixed $tranType
     * @return mixed
     */
    protected static function calculateRunningTotal($runTot, $rawAmount, $tranType) {

        $total = 0;
        $amount = abs($rawAmount);

        switch ($tranType) {

            case MpTranType::Sale:
                $total = max(array( ($runTot + $amount), 0) );
                break;

            case MpTranType::ReturnSale:
            case MpTranType::ReturnAmt:
                $total = max(array( ($runTot - $amount), 0) );
                break;

            case MpTranType::Void:
            case MpTranType::Reverse:
                $total = max(array( ($runTot - $amount), 0) );
                break;

            case MpTranType::VoidReturn:

                $total = max(array( ($runTot + $amount), 0) );
                break;

        }

        return $total;
    }


    // public static function updateToken(\PDO $dbh, GatewayResponseInterface $vr) {

    //     $gtRs = new Guest_TokenRS();
    //     $gtRs->idGuest_token->setStoredVal($vr->getIdToken());
    //     $rows = EditRS::select($dbh, $gtRs, array($gtRs->idGuest_token));

    //     if (count($rows) == 1) {

    //         EditRS::loadRow($rows[0], $gtRs);

    //         // Load new values
    //         $gtRs->Token->setNewVal($vr->response->getToken());
    //         $gtRs->Response_Code->setNewVal($vr->response->getResponseCode());
    //         $gtRs->Granted_Date->setNewVal(date('Y-m-d H:i:s'));
    //         $gtRs->Status->setNewVal($vr->response->getStatus());
    //         $gtRs->StatusMessage->setNewVal($vr->response->getResponseMessage());

    //         $runTot = self::calculateRunningTotal($gtRs->Running_Total->getStoredVal(), $vr->getAmount(), $vr->response->getTranType());
    //         $gtRs->Running_Total->setNewVal($runTot);

    //         EditRS::update($dbh, $gtRs, array($gtRs->idGuest_token));
    //         EditRS::updateStoredVals($gtRs);

    //     }
    //     return $gtRs;
    // }

    /**
     * Summary of getGuestTokenRSs
     * @param \PDO $dbh
     * @param mixed $idGuest
     * @return array<Guest_TokenRS>
     */
    public static function getGuestTokenRSs(\PDO $dbh, $idGuest) {

        $rsRows = array();
        $idGst = intval($idGuest);

        if ($idGst > 0) {

            $gtRs = new Guest_TokenRS();
            $gtRs->idGuest->setStoredVal($idGst);
            $rows = EditRS::select($dbh, $gtRs, array($gtRs->idGuest), 'and', array($gtRs->Merchant));

            foreach ($rows as $r) {
                $gtRs = new Guest_TokenRS();
                EditRS::loadRow($r, $gtRs);

                if (self::hasToken($gtRs)) {
                    $rsRows[$gtRs->idGuest_token->getStoredVal()] = $gtRs;
                }
            }
        }
        return $rsRows;
    }

    /**
     * Summary of getRegTokenRSs
     * @param \PDO $dbh
     * @param int $idRegistration
     * @param mixed $merchant
     * @param int $idGuest
     * @return array<Guest_TokenRS>
     */
    public static function getRegTokenRSs(\PDO $dbh, $idRegistration, $merchant, $idGuest = 0) {

        $rsRows = array();
        $idReg = intval($idRegistration);
        $idGst = intval($idGuest);

        $whMerchant = '';

        if ($merchant != '') {
            $whMerchant = " and t.Merchant = '$merchant' ";
        }


        // Get Billing Agent tokens
        if ($idReg > 0) {

            $stmt = $dbh->query("select t.* from guest_token t left join name_volunteer2 nv on t.idGuest = nv.idName and nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = 'ba'
where t.idRegistration = $idReg $whMerchant and nv.idName is null order by t.Merchant");

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $gtRs = new Guest_TokenRS();
                EditRS::loadRow($r, $gtRs);

                if (self::hasToken($gtRs)) {
                    $rsRows[$gtRs->idGuest_token->getStoredVal()] = $gtRs;
                }
            }
        }else if ($idGst > 0) {

            $gtRs = new Guest_TokenRS();
            $gtRs->idGuest->setStoredVal($idGst);
            $gtRs->Merchant->setStoredVal($merchant);
            $searchAr = array($gtRs->idGuest);
            if($merchant != ""){
                $searchAr[] = $gtRs->Merchant;
            }
            $rows = EditRS::select($dbh, $gtRs, $searchAr);

            if (count($rows) > 0) {

	            foreach ($rows as $r) {

	                $gtRs = new Guest_TokenRS();
	                EditRS::loadRow($r, $gtRs);

	                if (self::hasToken($gtRs)) {
	                    $rsRows[$gtRs->idGuest_token->getStoredVal()] = $gtRs;
	                }
	            }
            }
        }

        return $rsRows;
    }

    /**
     * Summary of findTokenRS
     * @param \PDO $dbh
     * @param int $gid
     * @param string $cardHolderName
     * @param string $cardType
     * @param string $maskedAccount
     * @param mixed $merchant
     * @throws \HHK\Exception\RuntimeException
     * @return Guest_TokenRS
     */
    public static function findTokenRS(\PDO $dbh, $gid, $cardHolderName, $cardType, $maskedAccount, $merchant) {

        $gtRs = new Guest_TokenRS();
        $gtRs->idGuest->setStoredVal($gid);
        $gtRs->CardHolderName->setStoredVal($cardHolderName);
        $gtRs->CardType->setStoredVal($cardType);
        $gtRs->MaskedAccount->setStoredVal($maskedAccount);

        if ($merchant != '') {
            $gtRs->Merchant->setStoredVal($merchant);
        }

        $rows = EditRS::select($dbh, $gtRs, array($gtRs->idGuest, $gtRs->CardHolderName, $gtRs->CardType, $gtRs->MaskedAccount, $gtRs->Merchant));

        if (count($rows) == 1) {

            EditRS::loadRow($rows[0], $gtRs);

        } else if (count($rows) == 0 || $merchant == '') {  // fix for local gateway

            $gtRs = New Guest_TokenRS();

        } else {

            throw new RuntimeException('Multiple Payment Tokens for guest Id: '.$gid.', ccgw='.$merchant);
        }

        return $gtRs;
    }

    /**
     * Summary of getTokenRsFromId
     * @param \PDO $dbh
     * @param int $idToken
     * @return Guest_TokenRS
     */
    public static function getTokenRsFromId(\PDO $dbh, $idToken) {

        $gtRs = new Guest_TokenRS();

        if ($idToken > 0) {

            $gtRs->idGuest_token->setStoredVal($idToken);
            $rows = EditRS::select($dbh, $gtRs, array($gtRs->idGuest_token));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $gtRs);
            } else {
                $gtRs = New Guest_TokenRS();
            }
        }

        return $gtRs;
    }

    /**
     * Summary of hasToken
     * @param \HHK\Tables\PaymentGW\Guest_TokenRS $tokenRs
     * @return bool
     */
    public static function hasToken(Guest_TokenRS $tokenRs) {

        if ($tokenRs->idGuest_token->getStoredVal() > 0 && $tokenRs->Token->getStoredVal() != '') {

            $now = new \DateTime();

            // Card expired?
            $expDate = $tokenRs->ExpDate->getStoredVal();

            if (strlen($expDate) == 4) {

                $expMonth = $expDate[0] . $expDate[1];
                $expYear = $expDate[2] . $expDate[3];
                $expDT = new \DateTime($expYear . '-' . $expMonth . '-01');
                $expDT->add(new \DateInterval('P1M'));
                $expDT->sub(new \DateInterval('P1D'));

                if ($now > $expDT) {
                    return FALSE;
                }
            }

            // Token Expired?
            $grantedDT = new \DateTime($tokenRs->Granted_Date->getStoredVal());
            $p1d = new \DateInterval('P' . $tokenRs->LifetimeDays->getStoredVal() . 'D');
            $grantedDT->add($p1d);

            if ($grantedDT > $now) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Summary of deleteToken
     * @param \PDO $dbh
     * @param int $guestTokenId
     * @return bool
     */
    public static function deleteToken(\PDO $dbh, $guestTokenId) {

    	$gtRs = new Guest_TokenRS();
    	$gtRs->idGuest_token->setStoredVal(intval($guestTokenId, 10));
    	$cnt = EditRS::delete($dbh, $gtRs, array($gtRs->idGuest_token));

    	if ($cnt == 1) {
    		return TRUE;
    	}

    	return FALSE;
    }

    /**
     * Summary of getCardsOnFile
     * @param \PDO $dbh
     * @param mixed $page
     * @return string HTML table
     */
    public static function getCardsOnFile(\PDO $dbh, $page) {

    	$tbl = new HTMLTable();

    	$stmt = $dbh->query("select t.*, n.Name_Full
from guest_token t JOIN `name` n on t.idGuest = n.idName
order by n.Name_Last, n.Name_First, t.Merchant");

    	while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

    		$gtRs = new Guest_TokenRS();
    		EditRS::loadRow($r, $gtRs);

    		if (self::hasToken($gtRs)) {
    			$tbl->addBodyTr(
    					HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $r['Name_Full'], array('href'=>$page.$r['idGuest'])))
    					.HTMLTable::makeTd($r['CardHolderName'])
    					.HTMLTable::makeTd($r['CardType'])
    					.HTMLTable::makeTd($r['MaskedAccount'])
    					.HTMLTable::makeTd(date('M d, Y', strtotime($r['Granted_Date'])))
    					.HTMLTable::makeTd($r['Merchant'])
    					.HTMLTable::makeTd($r['Running_Total'], array('style'=>'text-align:right;'))
    					);
    		}
    	}

    	$tbl->addHeaderTr(
    			HTMLTable::makeTh(Labels::getString('MemberType', 'primaryGuest', 'Primary Guest'))
    			.HTMLTable::makeTh('Card Holder')
    			.HTMLTable::makeTh('Type')
    			.HTMLTable::makeTh('Account')
    			.HTMLTable::makeTh('Granted')
    			.HTMLTable::makeTh('Merchant')
    			.HTMLTable::makeTh('Running Total'));

    	return $tbl->generateMarkup();

    }

}
