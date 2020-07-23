<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Helper;

/**
 * AVSResult.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
* Non Profit Software Corporation HostedCheckout PHP Client
*
* ©2013-2017 Non Profit Software Corporation - all rights reserved.
*
* Disclaimer:
* This software and all specifications and documentation contained
* herein or provided to you hereunder (the "Software") are provided
* free of charge strictly on an "AS IS" basis. No representations or
* warranties are expressed or implied, including, but not limited to,
* warranties of suitability, quality, merchantability, or fitness for a
* particular purpose (irrespective of any course of dealing, custom or
* usage of trade), and all such warranties are expressly and
* specifically disclaimed. on Profit Software Corporation shall have no
* liability or responsibility to you nor any other person or entity
* with respect to any liability, loss, or damage, including lost
* profits whether foreseeable or not, or other obligation for any cause
* whatsoever, caused or alleged to be caused directly or indirectly by
* the Software. Use of the Software signifies agreement with this
* disclaimer notice.
*/

// Mercury helper classes

class AVSResult {

    const RC_A = '(A) Address matches, Zip does not.';
    const RC_B = '(B) Street address match. Postal code not verified: incompatible formats.';
    const RC_C = '(C) Street address and postal code not verified: incompatible formats.';
    const RC_D = '(D) Street Address and postal codes match (International transactions).';
    const RC_G = '(G) Address information not verified for International transaction.';
    const RC_I = '(I) Address information not verified (International transaction).';
    const RC_M = '(M) Street address and postal code match (International transactions).';
    const RC_N = '(N) AVS No Match.';
    const RC_P = '(P) Postal code match. Street address not verified due to incompatible formats.';
    const RC_R = '(R) AVS Retry; System unavailable or timed out.';
    const RC_S = '(S) AVS not supported.';
    const RC_T = '(T) 9-digit ZIP matches, address does not.';
    const RC_U = '(U) AVS No data from issuer/ authorization system.';
    const RC_W = '(W) Nine-digit Zip Code matches, address does not.';
    const RC_X = '(X) 9-digit postal code and address match.';
    const RC_Y = '(Y) Street address and postal code match.';
    const RC_Z = '(Z) 5-digit ZIP code matches, address does not.';
    const BLANK = '';

    const MATCH = 'm';
    const NO_MATCH = 'nm';
    const NOT_VERIFIED = 'nv';
    const NO_ADVISE = 'na';

    protected $code;
    protected $resultMessage;
    protected $zipResult;
    protected $addrResult;


    function __construct($AVScode) {
        $this->code = $AVScode;
        $this->setAVSResult($AVScode);
    }


    protected function setAVSResult($code) {

        $avsResult = $this;

        switch ($code) {
            case 'A':
                $avsResult->resultMessage = self::RC_A;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::NO_MATCH;
                break;

            case 'B':
                $avsResult->resultMessage = self::RC_B;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::NOT_VERIFIED;
                break;

            case 'C':
                $avsResult->resultMessage = self::RC_C;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::NOT_VERIFIED;
                break;

            case 'D':
                $avsResult->resultMessage = self::RC_D;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'G':
                $avsResult->resultMessage = self::RC_G;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'I':
                $avsResult->resultMessage = self::RC_I;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'M':
                $avsResult->resultMessage = self::RC_M;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'N':
                $avsResult->resultMessage = self::RC_N;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::NO_MATCH;
                break;

            case 'P':
                $avsResult->resultMessage = self::RC_P;
                $avsResult->addrResult = self::NOT_VERIFIED;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'R':
                $avsResult->resultMessage = self::RC_R;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'S':
                $avsResult->resultMessage = self::RC_S;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'T':
                $avsResult->resultMessage = self::RC_T;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'U':
                $avsResult->resultMessage = self::RC_U;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            case 'W':
                $avsResult->resultMessage = self::RC_W;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'X':
                $avsResult->resultMessage = self::RC_X;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'Y':
                $avsResult->resultMessage = self::RC_Y;
                $avsResult->addrResult = self::MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case 'Z':
                $avsResult->resultMessage = self::RC_Z;
                $avsResult->addrResult = self::NO_MATCH;
                $avsResult->zipResult = self::MATCH;
                break;

            case '':
                $avsResult->resultMessage = self::BLANK;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;
                break;

            default:
                $avsResult->resultMessage = 'Unknown Code: ' . $code;
                $avsResult->addrResult = self::NO_ADVISE;
                $avsResult->zipResult = self::NO_ADVISE;

        }

    }

    public function isZipMatch() {
        if ($this->getZipResult() == self::MATCH) {
            return TRUE;
        }

        return FALSE;
    }

    public function isAddrMatch() {
        if ($this->getAddrResult() == self::MATCH) {
            return TRUE;
        }

        return FALSE;

    }

    public function getAVScode() {
        return $this->code;
    }

    public function getResultMessage() {
        return $this->resultMessage;
    }

    public function getZipResult() {
        return $this->zipResult;
    }

    public function getAddrResult() {
        return $this->addrResult;
    }

}
?>