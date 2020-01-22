<?php
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

    public static function storeToken(\PDO $dbh, $idRegistration, $idPayor, iGatewayResponse $vr, $idToken = 0) {

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

        if ($vr->getCardHolderName() != '') {
            $gtRs->CardHolderName->setNewVal($vr->getCardHolderName());
        }
        $gtRs->CardType->setNewVal($vr->getCardType());

        if ($vr->getExpDate() != '') {
            $gtRs->ExpDate->setNewVal($vr->getExpDate());
        }
        $gtRs->Frequency->setNewVal('OneTime');
        $gtRs->Granted_Date->setNewVal(date('Y-m-d H:i:s'));
        $gtRs->LifetimeDays->setNewVal(180);

        if ($cardNum != '') {
            $gtRs->MaskedAccount->setNewVal($cardNum);
        }

        if ($vr->getOperatorID() != '') {
            $gtRs->OperatorID->setNewVal($vr->getOperatorID());
        }
        $gtRs->Response_Code->setNewVal($vr->getResponseCode());
        $gtRs->Status->setNewVal($vr->getStatus());
        $gtRs->StatusMessage->setNewVal($vr->getResponseMessage());
        $gtRs->Tran_Type->setNewVal($vr->getTranType());
        $gtRs->Token->setNewVal($vr->getToken());

        $runTot = self::calculateRunningTotal($gtRs->Running_Total->getStoredVal(), $vr->getAuthorizedAmount(), $vr->getTranType());
        $gtRs->Running_Total->setNewVal($runTot);

        // Write
        if ($gtRs->idGuest_token->getStoredVal() > 0) {
            // Update
            $num = EditRS::update($dbh, $gtRs, array($gtRs->idGuest_token));
            $idToken = $gtRs->idGuest_token->getStoredVal();
        } else {
            //Insert
            $idToken = EditRS::insert($dbh, $gtRs);
        }

        return $idToken;
    }

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


    public static function updateToken(\PDO $dbh, PaymentResponse $vr) {

        $gtRs = new Guest_TokenRS();
        $gtRs->idGuest_token->setStoredVal($vr->idToken);
        $rows = EditRS::select($dbh, $gtRs, array($gtRs->idGuest_token));

        if (count($rows) == 1) {

            EditRS::loadRow($rows[0], $gtRs);

            // Load new values
            $gtRs->Token->setNewVal($vr->response->getToken());
            $gtRs->Response_Code->setNewVal($vr->response->getResponseCode());
            $gtRs->Granted_Date->setNewVal(date('Y-m-d H:i:s'));
            $gtRs->Status->setNewVal($vr->response->getStatus());
            $gtRs->StatusMessage->setNewVal($vr->response->getResponseMessage());

            $runTot = self::calculateRunningTotal($gtRs->Running_Total->getStoredVal(), $vr->getAmount(), $vr->response->getTranType());
            $gtRs->Running_Total->setNewVal($runTot);

            EditRS::update($dbh, $gtRs, array($gtRs->idGuest_token));
            EditRS::updateStoredVals($gtRs);

        }
        return $gtRs;
    }

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
        }

        if ($idGst > 0) {

            $gtRs = new Guest_TokenRS();
            $gtRs->idGuest->setStoredVal($idGst);
            $gtRs->Merchant->setStoredVal($merchant);
            $rows = EditRS::select($dbh, $gtRs, array($gtRs->idGuest, $gtRs->Merchant));

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

        } else if (count($rows) == 0) {

            $gtRs = New Guest_TokenRS();

        } else {

            throw new Hk_Exception_Runtime('Multiple Payment Tokens for guest Id: '.$gid.', ccgw='.$merchant);
        }

        return $gtRs;
    }

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

    public static function hasToken(Guest_TokenRS $tokenRs) {

        if ($tokenRs->idGuest_token->getStoredVal() > 0 && $tokenRs->Token->getStoredVal() != '') {

            $now = new DateTime();

            // Card expired?
            $expDate = $tokenRs->ExpDate->getStoredVal();

            if (strlen($expDate) == 4) {

                $expMonth = $expDate[0] . $expDate[1];
                $expYear = $expDate[2] . $expDate[3];
                $expDT = new DateTime($expYear . '-' . $expMonth . '-01');
                $expDT->add(new DateInterval('P1M'));
                $expDT->sub(new DateInterval('P1D'));

                if ($now > $expDT) {
                    return FALSE;
                }
            }

            // Token Expired?
            $grantedDT = new DateTime($tokenRs->Granted_Date->getStoredVal());
            $p1d = new DateInterval('P' . $tokenRs->LifetimeDays->getStoredVal() . 'D');
            $grantedDT->add($p1d);

            if ($grantedDT > $now) {
                return TRUE;
            }
        }

        return FALSE;
    }


}
