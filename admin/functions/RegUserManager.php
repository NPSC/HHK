<?php

/**
 * RegUserManager.php
 *
 * @category  wolunteers
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */
function manageRegistration(PDO $dbh, $n, $admin) {

    if (isset($_POST["txtfb$n"])) {
        $fbId = strtolower(filter_var($_POST["txtfb$n"], FILTER_SANITIZE_STRING));
    } else {
        return array("error" => "Bad fb Id.");
    }

    $config = new Config_Lite(ciCFG_FILE);
    $rtnMsg = "";

//// check for valid radio button - the selected user match
    if (isset($_POST["b$n"])) {

        $id = intval(filter_var($_POST["b$n"], FILTER_VALIDATE_INT), 10);

        if ($id < 1) {
            return array("error" => "Bad Id.");
        }

        $fbRs = new FbxRS();
        $fbRs->fb_id->setStoredVal($fbId);
        $fbxRows = EditRS::select($dbh, $fbRs, array($fbRs->fb_id));

        if (count($fbxRows) === 1) {
            EditRS::loadRow($fbxRows[0], $fbRs);
        } else {
            return array("error" => "Record not found.  ");
        }


        $apprvd = $admin;
        $orgCode = "p";
        $roleCode = WebRole::DefaultRole;
        $fbEmail = strtolower($fbRs->fb_Email->getStoredVal());
        $uname = $fbRs->PIFH_Username->getStoredVal();
        $pw = $fbRs->Enc_Password->getStoredVal();


        if ($uname == "") {
            //die(" username is blank");
            return array("error" => "No Username");
        }

        // Register the user as a Volunteer (Group_Code = v)(Verify_address = y)
        $query = "call register_web_user($id, '$fbId', '$uname', '$apprvd', '$orgCode', '$roleCode', '$pw', 'v');";
        $dbh->exec($query);

        $rtnMsg .= "Web user " . $uname . " is registered.  ";

        if ($fbEmail != '') {
            $rtnMsg .= checkTheEmail($dbh, $id, $fbEmail);
        }

        if ($fbEmail != "") {

            $mail = prepareEmail($config);

            $mail->From = $config->getString("vol_email", "ReturnAddress", "");
            $mail->addReplyTo($config->getString("vol_email", "ReturnAddress", ""));
            $mail->FromName = $config->getString('site', 'Site_Name', 'Hospitality HouseKeeper');
            $mail->addAddress($fbEmail);     // Add a recipient
            $mail->addBCC($config->getString("vol_email", "ReturnAddress", ""));
            $mail->isHTML(true);

            $mail->Subject = $config->get("vol_email", "RegSubj", "Volunteer Registration Confirmation");
            $mail->msgHTML(getRegConfBody($config, $fbRs, $uname));

            if($mail->send()) {
                $rtnMsg .= "Email sent.  ";
            } else {
                $rtnMsg .= "Warning: Email confirmation message failed: " . $mail->ErrorInfo;
            }

        } else {
            $rtnMsg .= "Warning: Email confirmation message failed: No email address.  ";
        }
    }

    return array("success" => $rtnMsg);
}

function getRegConfBody($config, $fbRs, $uname) {

    return '<html><head>
    <style type="text/css">
    h4 {font-family: Arial, Helvetica, sans-serif;
        font-size: 16px;
        font-weight: bold;
        color: #FB883C;
        margin: 0px;
        padding: 0px;}
    </style>
    </head>
    <body>
    <h4>Your ' . $config->getString("site", "Site_Name", "House") . ' Volunteer Web Registration is Complete</h4>
        <p>' . $fbRs->fb_First_Name->getStoredVal() . ' ' . $fbRs->fb_Last_Name->getStoredVal() . ' is approved for the ' . $config->getString("site", "Site_Name", "House") . ' Volunteer Website at:  <a href="' . $config->getString("site", "Volunteer_URL", "") . '?u=' . $uname . '" >Volunteer Website</a>;  with user name: ' . $uname . '</p>
    </body>
    </html>';

}



function checkTheEmail(PDO $dbh, $id, $fbEmail) {

    $uS = Session::getInstance();

    $name = Member::GetDesignatedMember($dbh, $id, MemBasis::Indivual);
    $emails = new Emails($dbh, $name, $uS->nameLookups[GL_TableNames::EmailPurpose]);


    $desc = '';
    // Look for an existing email address
    foreach ($emails->get_CodeArray() as $cd => $val) {
        $code = (string)$cd;
        $emArray = $emails->get_Data($code);

        if ($fbEmail == $emArray['Email']) {
            return '';
        }
    }

    $rtnMsg = '';
    $emCode = '';

    if (count($emails) > 0) {
        // find the next empty slot...
        foreach ($emails->get_CodeArray() as $cd => $val) {
            $code = (string)$cd;

            if ($emails->isRecordSetDefined($code) === FALSE) {
                $emCode = $code;
                $desc = $val[Member::DESC];
                break;
            }
        }
    } else {
        // no email addressess defined yet.
        $emCode = Email_Purpose::Home;
    }

    if ($emCode == '') {
        // No slots available.
        return 'All email slots are filled, this email address is not saved:  ' . $fbEmail . ".  ";
    }

    $a = $emails->get_recordSet($emCode);

    if (is_null($a)) {
        $a = new NameEmailRS();
    }

    $a->Email->setNewVal(trim($fbEmail));
    $a->Status->setNewVal("a");
    $a->Updated_By->setNewVal($uS->username);
    $a->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

    if ($a->idName->getStoredVal() > 0) {
        // update
        $numRows = EditRS::update($dbh, $a, array($a->idName, $a->Purpose));
        if ($numRows > 0) {
            NameLog::writeUpdate($dbh, $a, $id, $uS->username, $desc);
            $rtnMsg .= 'Email Address Updated.  ';
        }
    } else {
        $a->idName->setNewVal($id);
        $a->Purpose->setNewVal($emCode);
        EditRS::insert($dbh, $a);

        NameLog::writeInsert($dbh, $a, $id, $uS->username, $desc);
        $rtnMsg .= 'Email Address Added.  ';
    }

    $rtnMsg .= $name->verifyPreferredAddress($dbh, $emails, $uS->username);

}

