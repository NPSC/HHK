<?php

namespace HHK\Payment\PaymentGateway\Vantiv\Helper;

/**
 * MpReturnCodeValues.php
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

class MpReturnCodeValues {

    public static function returnCodeToText($returnCode) {
        $rmsg = '';

        switch ($returnCode) {

            case '0':
                $rmsg = 'Success';
                break;

            case '100':
                $rmsg = 'Auth Fail (bad merchant password or bad merchant Id).';
                break;

            case '101':
                $rmsg = 'Card Declined – the card was declined for the transaction. Status=Decline';
                break;

            case '102':
                $rmsg = 'Cancel. The user pressed cancel.';
                break;

            case '103':
                $rmsg = 'Session Timeout';
                break;

            case '104':
                $rmsg = '“Payment processing is temporarily unavailable at this time.';
                break;

            case '200':
                $rmsg = 'Mercury Internal Error.';
                break;

            case '203':
                $rmsg = 'Process Payment Fail – unable to process.';
                break;

            case '204':
                $rmsg = 'PreAuth Fail DBErr– internal database error for PreAuth transaction.';
                break;

            case '205':
                $rmsg = 'Sales Not Completed DBErr – internal database error for Sale transaction.';
                break;

            case '206':
                $rmsg = 'Save CardInfo Fail – A transaction error occurred processing the card info.';
                break;

            case '207':
                $rmsg = 'Load CardInfo Fail – Could not retrieve the card info for the supplied CardID.';
                break;

            case '208':
                $rmsg = 'Process CardInfo Fail – unable to process. CardInfo Status=Error.';
                break;

            case '300':
                $rmsg = 'Validation failure – one of the request parameters was either missing or invalid.';
                break;

            case '301':
                $rmsg = 'Validation CC Fail – Credit Card failed Mod10 check multiple times';
                break;

            case '302':
                $rmsg = 'Validation Server Side Failure – possible tampering suspected';
                break;

            case '303':
                $rmsg = 'Validate Name Fail. Invalid data entered in cardholder name field.';
                break;

            default:
                $rmsg = 'Unknown Return Code: '.$returnCode;
        }
        return $rmsg;
    }

    public static function responseCodeToText($responseCode) {
        $rmsg = '';

        switch ($responseCode) {

            case '0':
                $rmsg = 'Success';
                break;

            case '100':
                $rmsg = 'Auth Fail (bad password or id).';
                break;

            case '200':
                $rmsg = 'Mercury Internal Error.  Specific error will be logged in Mercury’s internal error log.';
                break;

            case '300':
                $rmsg = 'Validation failure – one of the request parameters was either missing or invalid.';
                break;

            default:
                $rmsg = 'Unknown Response Code: '.$responseCode;
        }
        return $rmsg;

    }

}
?>