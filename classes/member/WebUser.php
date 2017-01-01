<?php
/**
 * WebUser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of WebUser
 * @package name
 * @author Eric
 */
class WebUser {

    public static function loadWebUserRS(\PDO $dbh, $id) {
        $wUserRS = new W_usersRS();

        if ($id > 0) {

            $wUserRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $wUserRS, array($wUserRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $wUserRS);
            } else {
                $wUserRS = new W_usersRS();
            }
        }

        return $wUserRS;
    }

    public static function getWebUserMarkup(\PDO $dbh, $id, $maintFlag, $wUserRS = NULL) {
        // Web User page
        if (is_null($wUserRS)) {
            $wUserRS = self::loadWebUserRs($dbh, $id);
        }


        $lastWebAccess = $wUserRS->Last_Login->getStoredVal() == '' ? '' : date('M j, Y', strtotime($wUserRS->Last_Login->getStoredVal()));

        $wAuthRS = new W_authRS();
        $wAuthRS->idName->setStoredVal($id);
        $rws = EditRS::select($dbh, $wAuthRS, array($wAuthRS->idName));

        if (count($rws) > 0) {
            EditRS::loadRow($rws[0], $wAuthRS);
        }

        $wVerifyAddr = readGenLookupsPDO($dbh, 'Verify_User_Address');
        $wStatusMkup = readGenLookupsPDO($dbh, 'Web_User_Status');
        $roleMkup = readGenLookupsPDO($dbh, 'Role_Codes');

        $tbl = new HTMLTable();
        $tbl->addBodyTr(HTMLTable::makeTh("Web Access", array('colspan'=>'2')));

        // Values for new web user
        if (count($rws) == 0 && $maintFlag) {
            $webUserName = HTMLInput::generateMarkup('', array('id'=>'txtwUserName', 'class'=>'ignrSave'));
        } else {
            $webUserName = $wUserRS->User_Name->getStoredVal();
        }

        $tbl->addBodyTr(
                HTMLTable::makeTd("User Name:", array('class'=>'tdlable'))
                .HTMLTable::makeTd($webUserName)
                );

        // Password - add a row
        if (count($rws) == 0 && $maintFlag) {
            $tbl->addBodyTr(HTMLTable::makeTd('Password:'). HTMLTable::makeTd(HTMLInput::generateMarkup('', array('id'=>'txtwUserPW', 'type'=>'password', 'class'=>'ignrSave'))));
        }

         $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('style'=>'color:red;font-size:.9em;', 'id'=>'hhk-wuprompt')), array('colspan'=>'2')));


        $tbl->addBodyTr(
                HTMLTable::makeTd("Web Status:", array('class'=>'tdlable'))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($wStatusMkup, $wUserRS->Status->getStoredVal()), array('id'=>'selwStatus', 'class'=>'ignrSave')))
                );
        $tbl->addBodyTr(
                HTMLTable::makeTd("Last Login:", array('class'=>'tdlable'))
                .HTMLTable::makeTd($lastWebAccess)
                );
        $tbl->addBodyTr(
                HTMLTable::makeTd("Verify Address:", array('class'=>'tdlable'))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($wVerifyAddr, $wUserRS->Verify_Address->getStoredVal()), array('id'=>'selwVerify', 'class'=>'ignrSave')))
                );

        $attr = array('id'=>'selwRole', 'class'=>'ignrSave');
        if ($maintFlag === FALSE) {
            $attr['disabled'] = 'disabled';
        }
        $tbl->addBodyTr(
                HTMLTable::makeTd("Role:", array('class'=>'tdlable'))
                .HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($roleMkup, $wAuthRS->Role_Id->getStoredVal()), $attr))
                );

        if ($maintFlag !== FALSE && count($rws) > 0) {
            $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Change Password... ', array('id'=>"chgPW", 'type'=>'button')), array('colspan'=>'2')));
        }

        $webAlert = new alertMessage("webContainer");
        $webAlert->set_DisplayAttr("none");
        $webAlert->set_Context(alertMessage::Success);
        $webAlert->set_iconId("webIcon");
        $webAlert->set_styleId("webResponse");
        $webAlert->set_txtSpanId("webMessage");
        $webAlert->set_Text("oh-oh");

        $tbl->addBodyTr(HTMLTable::makeTh($webAlert->createMarkup(), array('colspan'=>'2')));

        return HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('style'=>'float:left;max-width:400px;'));

    }


    public static function getSecurityGroupMarkup(\PDO $dbh, $id, $allowFlag) {

        $stmt = $dbh->query("select `Group_Code` as `Code`, `Title` as `Description` from w_groups");
        $grps = $stmt->fetchAll();
        foreach ($grps as $g) {
            $sArray[$g['Code']] = $g;
        }

        $aArray = array();

        $query = "select Group_Code, Timestamp from id_securitygroup where idName = $id;";
        $stmt = $dbh->query($query);

//        if ($stmt->rowCount() == 0) {
//            return "";
//        }

        foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $r) {
            $aArray[$r[0]] = $r[1];
        }


        $m = "<table><tr><th colspan='2'>Security Groups</th><th>Date</th></tr>";

        foreach ($sArray as $g) {
            if (isset($aArray[$g["Code"]])) {
                $checked = " checked='checked' ";
                $LastUpdate = date("M j, Y", strtotime($aArray[$g["Code"]]));
            } else {
                $checked = "";
                $LastUpdate = "";
            }

            if ($allowFlag) {
                $enabled = "";
            } else {
                $enabled = " disabled='disabled' ";
            }

            $m .= "<tr><td><input type='checkbox' class='grpSec ignrsave' $enabled id='grpSec_" . $g["Code"] . "' $checked /></td><td>" . $g["Description"] . "</td><td>$LastUpdate</td></tr>";
        }

        return HTMLContainer::generateMarkup('div', $m . "</table>", array('style'=>'float:left;'));

    }


    public static function saveUname(\PDO $dbh, $admin, $parms, $maintFlag) {

        $reply = array();
        $success = '';

        $vaddr = "";
        if (isset($parms["vaddr"])) {
            $vaddr = $parms["vaddr"];
        }

        $role = '';
        if (isset($parms["role"])) {
            $role = $parms["role"];
        }

        $id = 0;
        if (isset($parms["uid"])) {
            $id = intval(filter_var($parms["uid"], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $status = '';
        if (isset($parms["status"])) {
            $status = filter_var($parms["status"], FILTER_SANITIZE_STRING);
        }

        $wUserName = "";
        if (isset($parms["wuname"])) {
            $wUserName = filter_var($parms["wuname"], FILTER_SANITIZE_STRING);
        }

        $wUserPw = "";
        if (isset($parms["wupw"])) {
            $wUserPw = filter_var($parms["wupw"], FILTER_SANITIZE_STRING);
        }

        if ($role == '') {
            $role = WebRole::WebUser;
        }

        if ($vaddr == '') {
            $vaddr = 'y';
        }

        if ($status == '') {
            $status = 'a';
        }


        // w_users table
        $usersRS = self::loadWebUserRs($dbh, $id);

        if ($usersRS->idName->getStoredVal() > 0) {

            // update existing entry

            $usersRS->Status->setNewVal($status);
            $usersRS->Verify_Address->setNewVal($vaddr);
            $usersRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $usersRS->Updated_By->setNewVal($admin);

            $n = EditRS::update($dbh, $usersRS, array($usersRS->idName));

            if ($n == 1) {

                NameLog::writeUpdate($dbh, $usersRS, $id, $admin);
                $success .= "Updated web user.  ";
            }

        } else if ($wUserName != '' && $wUserPw != '' && $id > 0 && $maintFlag) {

            // Register the user as a Volunteer (Group_Code = v)(Verify_address = y)
            $query = "call register_web_user($id, '', '$wUserName', '$admin', 'p', '$role', '$wUserPw', 'v');";

            if ($dbh->exec($query) === false) {
                $err = $dbh->errorInfo();
                return array("error", $err[0] . "; " . $err[2]);
            }

            $success .= "New Web User.  ";

        } else {
            return array("error", "W-Users Record not found");
        }



        if ($maintFlag) {

            // update w_auth table with new role
            $authRS = new W_authRS();
            $authRS->idName->setStoredVal($id);
            $authRows = EditRS::select($dbh, $authRS, array($authRS->idName));

            if (count($authRows) == 1) {
                // update existing entry
                EditRS::loadRow($authRows[0], $authRS);

                $authRS->Role_Id->setNewVal($role);

                $authRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                $authRS->Updated_By->setNewVal($admin);

                $n = EditRS::update($dbh, $authRS, array($authRS->idName));

                if ($n == 1) {

                    NameLog::writeUpdate($dbh, $authRS, $id, $admin);
                    $success .= "Update web authorization.  ";
                }
            } else {
                $reply["error"] = "Auth Record not found";
                return $reply;
            }


            // Group Code security table
            //$sArray = readGenLookups($dbh, "Group_Code");
            $stmt = $dbh->query("select Group_Code as Code, Description from w_groups");
            $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($groups as $g) {
                $sArray[$g['Code']] = $g;
            }



            $secRS = new Id_SecurityGroupRS();
            $secRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $secRS, array($secRS->idName));

            foreach ($rows as $r) {
                $sArray[$r['Group_Code']]["exist"] = "t";
            }

            $updtd = FALSE;

            foreach ($sArray as $g) {

                if (isset($parms["grpSec_" . $g["Code"]])) {

                    if (!isset($g["exist"]) && $parms["grpSec_" . $g["Code"]] == "checked") {

                        // new group code to put into the database
                        $secRS = new Id_SecurityGroupRS();
                        $secRS->idName->setNewVal($id);
                        $secRS->Group_Code->setNewVal($g["Code"]);
                        $n = EditRS::insert($dbh, $secRS);

                        NameLog::writeInsert($dbh, $secRS, $id, $admin);
                        $updtd = TRUE;

                    } else if (isset($g["exist"]) && $parms["grpSec_" . $g["Code"]] != "checked") {

                        // group code to delete from the database.
                        $secRS = new Id_SecurityGroupRS();
                        $secRS->idName->setStoredVal($id);
                        $secRS->Group_Code->setStoredVal($g["Code"]);
                        $n = EditRS::delete($dbh, $secRS, array($secRS->idName, $secRS->Group_Code));

                        if ($n == 1) {
                            NameLog::writeDelete($dbh, $secRS, $id, $admin);
                            $updtd = TRUE;
                        }
                    }
                }
            }

            if ($updtd) {
                $success .= 'Security Groups Updated.';
            }
        }

        if ($success != '') {
            $reply['success'] = $success;
        }

        return $reply;
    }

}

