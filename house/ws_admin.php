<?php

use HHK\Notification\Mail\HHKMailer;
use HHK\sec\{Session, WebInit};
use HHK\SysConst\WebPageCode;
use HHK\Member\MemberSearch;
use HHK\Member\Relation\AbstractRelation;
use HHK\sec\UserClass;
use HHK\sec\MFA\GoogleAuthenticator;
use HHK\sec\MFA\Backup;
use HHK\sec\MFA\Email;
use HHK\sec\MFA\Remember;
use HHK\Member\Address\Emails;
use HHK\Member\IndivMember;
use HHK\SysConst\MemBasis;
use HHK\SysConst\GLTableNames;


/**
 * ws_admin.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("homeIncludes.php");

// Set page type for AdminPageCommon
$wInit = new webInit(WebPageCode::Service);

$dbh = $wInit->dbh;


// get session instance
$uS = Session::getInstance();


if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}else {
    $c = "";
}

$events = [];
try {


switch ($c) {

    case "delRel":

        $id = 0;
        $rId = 0;

        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }
        if (isset($_POST['rId'])) {
            $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['rc'])) {
            $rc = filter_var($_POST['rc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = deleteRelationLink($dbh, $id, $rId, $rc);
        break;

    case "newRel":

        $id = 0;
        $rId = 0;

        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }
        if (isset($_POST['rId'])) {
            $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['rc'])) {
            $rc = filter_var($_POST['rc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = newRelationLink($dbh, $id, $rId, $rc);
        break;

    case "addcareof":

        $id = 0;
        $rId = 0;

        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }
        if (isset($_POST['rId'])) {
            $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['rc'])) {
            $rc = filter_var($_POST['rc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = changeCareOfFlag($dbh, $id, $rId, $rc, TRUE);
        break;

    case "delcareof":

        $id = 0;
        $rId = 0;

        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }
        if (isset($_POST['rId'])) {
            $rId = intval(filter_var($_POST['rId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['rc'])) {
            $rc = filter_var($_POST['rc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = changeCareOfFlag($dbh, $id, $rId, $rc, FALSE);
        break;

    case 'srchName':

        if (isset($_POST['md'])) {
            $md = filter_var($_POST['md'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $nameLast = (isset($_POST['nl']) ? filter_var($_POST['nl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '');
            $nameFirst = (isset($_POST['nf']) ? filter_var($_POST['nf'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '');
            $email = (isset($_POST['em']) ? filter_var($_POST['em'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '');
            $indx = (isset($_POST['indx']) ? filter_var($_POST['indx'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '');

            // Check for duplicate member records
            $dups = MemberSearch::searchName($dbh, $md, $nameLast, $nameFirst, $email);

            if (count($dups) > 0) {
                $events = [
                        'success' => 'Returned ' . count($dups) . ' duplicates',
                        'dups' => MemberSearch::createDuplicatesDiv($dups),
                        'indx' => $indx
                    ];
            }

        } else {
            $events = ['error' => 'Search Names: must supply a member designation.  '];
        }

        break;

    case 'schzip':

        if (isset($_GET['zip'])) {
            $zip = filter_var($_GET['zip'], FILTER_SANITIZE_NUMBER_INT);
            $events = searchZip($dbh, $zip);
        }
        break;

    case 'getcounties':

        if(isset($_GET['state'])) {
            $state = filter_var($_GET['state'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $events = getCounties($dbh, $state);
        }
        break;

    case "chgpw":

        $input = filter_input_array(INPUT_POST, ["old" => FILTER_UNSAFE_RAW, "newer" => FILTER_SANITIZE_ADD_SLASHES]);

        $events = changePW($dbh, $input['old'], $input['newer'], $uS->username, $uS->uid);

        break;

    case "gen2fa":

        $method = '';
        if(isset($_POST['method'])){
            $method = filter_var($_POST['method'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = generateTwoFA($dbh, $uS->username, $method, $_POST);
        break;

    case "get2fa":
        $events = getTwoFA($dbh, $uS->username);
        break;

    case "save2fa":

        $method = '';
        if(isset($_POST['method'])){
            $method = filter_var($_POST['method'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $secret = '';
        if (isset($_POST["secret"])) {
            $secret = filter_var($_POST["secret"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $otp = '';
        if (isset($_POST["otp"])) {
            $otp = filter_var($_POST["otp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = saveTwoFA($dbh, $secret, $otp, $method);
        break;

    case "disable2fa":
        $method = '';
        if(isset($_POST['method'])){
            $method = filter_var($_POST['method'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $userAr = UserClass::getUserCredentials($dbh, $uS->username);

        if($method == "email"){
            $mfa = new Email($userAr);
        }

        if($method == "authenticator"){
            $mfa = new GoogleAuthenticator($userAr);
            $backup = new Backup($userAr);
            $backup->disable($dbh);
        }

        if(isset($mfa)){
            $success = $mfa->disable($dbh);
            $events = ["success" => $success, "mkup" => $mfa->getEditMarkup($dbh)];
        }else{
            $events = ["error" => "Invalid method"];
        }
        break;

    case "clear2faTokens" :
        $userAr = UserClass::getUserCredentials($dbh, $uS->username);
        $remember = new Remember($userAr);
        $events = ["success" => $remember->deleteTokens($dbh, TRUE)];
        break;
    case "reportError" :
        $message = filter_input(INPUT_POST, "message", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $info = json_decode(base64_decode($_POST['info']), true);
        $info = filter_var_array($info, FILTER_SANITIZE_ADD_SLASHES);
        reportError($message, $info);
        break;
    default:
        $events = ["error" => "Bad Command"];
}

} catch (PDOException $ex) {

    $events = ["error" => "Database Error" . $ex->getMessage()];

} catch (Exception $ex) {

    $events = ["error" => "HouseKeeper Error" . $ex->getMessage()];
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();


function searchZip(PDO $dbh, $zip) {

    $query = "select * from postal_codes where Zip_Code like :zip LIMIT 10";
    $stmt = $dbh->prepare($query);
    $stmt->execute([':zip' => $zip . "%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($rows as $r) {
        $ent = [];

        $ent['value'] = $r['Zip_Code'];
        $ent['label'] = $r['City'] . ', ' . $r['State'] . ', ' . $r['Zip_Code'];
        $ent['City'] = $r['City'];
        $ent['County'] = $r['County'];
        $ent['State'] = $r['State'];

        $events[] = $ent;
    }

    return $events;
}

function getCounties(PDO $dbh, $state) {
    $query = "select `County`, `State` from `postal_codes` where `State` = :state and  `County` != '' group by `County`";
    $stmt = $dbh->prepare($query);
    $stmt->execute([':state' => strtoupper($state)]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = ['success'=>$rows];

    return $events;
}

function changeCareOfFlag(PDO $dbh, $id, $rId, $relCode, $flag) {

    $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

    if (is_null($rel) === FALSE) {
        $uS = Session::getInstance();
        $msh = $rel->setCareOf($dbh, $rId, $flag, $uS->username);

        $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

        return ['success' => $msh, 'rc' => $relCode, 'markup' => $rel->createMarkup()];
    }
    return ['error' => 'Relationship is Undefined.'];

}

function deleteRelationLink(PDO $dbh, $id, $rId, $relCode) {

    $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

    if (is_null($rel) === FALSE) {

        $msh = $rel->removeRelationship($dbh, $rId);

        $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

        return ['success' => $msh, 'rc' => $relCode, 'markup' => $rel->createMarkup()];
    }
    return ['error' => 'Relationship is Undefined.'];

}

function newRelationLink(PDO $dbh, $id, $rId, $relCode) {

    $uS = Session::getInstance();

    $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);

    if (is_null($rel) === FALSE) {
        $msh = $rel->addRelationship($dbh, $rId, $uS->username);

        $rel = AbstractRelation::instantiateRelation($dbh, $relCode, $id);
        return ['success' => $msh, 'rc' => $relCode, 'markup' => $rel->createMarkup()];
    }

    return ['error' => 'Relationship is Undefined.'];

}


function changePW(\PDO $dbh, $oldPw, $newPw, $uname, $id) {

    $event = [];

    $u = new UserClass();

    $event = ($u->updateDbPassword($dbh, $id, $oldPw, $newPw, $uname) === TRUE) ? ['success' => 'User Password updated.'] : ['warning' => $u->logMessage];

    return $event;
}

function changeQuestions(\PDO $dbh, array $questions) {

    $event = [];

    $u = new UserClass();

    $event = ($u->updateSecurityQuestions($dbh, $questions) === TRUE) ? ['success' => 'User Security Questions Updated.'] : ['warning' => $u->logMessage];

    return $event;
}

function generateTwoFA($dbh, $uname, string $method, array $post = []){

    $uS = Session::getInstance();

    switch ($method) {
        case "authenticator":
            try{
                $ga = new GoogleAuthenticator(['User_Name' => $uname, 'totpSecret' => '']);
                $uS = Session::getInstance();

                $ga->createSecret();
                $qrCodeUrl = $ga->getQRCodeImage("HHK - " . $uS->siteName);

                $event = ['success' => true, 'secret' => $ga->getSecret(), 'url' => $qrCodeUrl];
            }catch(Exception $e){
                $event = ['error' => $e->getMessage()];
            }
            break;
        case "email":
            try{
                $u = new UserClass();
                $userAr = $u->getUserCredentials($dbh, $uname);

                $userMem = new IndivMember($dbh, MemBasis::Indivual, $userAr['idName']);
                $emails = new Emails($dbh, $userMem, $uS->nameLookups[GLTableNames::EmailPurpose]);
                $emails->savePost($dbh, $post, $uS->username);
                $userMem->saveChanges($dbh, $post);

                $email = new Email($userAr);
                $email->createSecret();
                $email->sendCode($dbh);
                $event = ['success' => true, 'secret' => $email->getSecret()];
            }catch(Exception $e){
                $event = ['error' => $e->getMessage()];
            }
    }



    return $event;
}

function saveTwoFA(\PDO $dbh, $secret, $OTP, $method){
    $uS = Session::getInstance();

    switch ($method) {
        case "authenticator":
            try{

                $ga = new GoogleAuthenticator(['User_Name' => $uS->username, 'totpSecret' => $secret]);
                $backup = new Backup(['idName' => $uS->uid, 'User_Name' => $uS->username, 'backupSecret' => '']);
                $backup->createSecret();

                if($ga->verifyCode($dbh, $OTP) == false){
                    $events = ['error' => "One Time Code is invalid"];
                }elseif($backup->saveSecret($dbh) && $ga->saveSecret($dbh)){
                    $events = ['success' => 'Two Factor Authentication enabled', 'backupCodes' => $backup->getCode()];
                }else{
                    $events = ['error' => "Unable to enable Two factor Authentication"];
                }
            }catch(Exception $e){
                $events = ['error' => 'Error: ' . $e->getMessage()];
            }
            break;
        case "email":

            $email = new Email(['User_Name' => $uS->username, 'emailSecret' => $secret]);

            if($email->verifyCode($dbh, $OTP) == false){
                $events = ['error' => "One Time Code is invalid"];
            }elseif($email->saveSecret($dbh)){
                $events = ['success' => 'Two Factor Authentication enabled'];
            }else{
                $events = ['error' => "Unable to enable Two factor Authentication"];
            }
            break;
    }

    return $events;
}

function getTwoFA(\PDO $dbh, $username){
    $uS = Session::getInstance();

    $u = new UserClass();
    $user = $u->getUserCredentials($dbh, $username);
    if($user['totpSecret'] != ''){
        $ga = new GoogleAuthenticator($user);
        $qrCodeUrl = $ga->getQRCodeImage("HHK - " . $uS->siteName);
        $event = ['success' => true, 'url' => $qrCodeUrl];
    }else{
        $event = ['error' => 'Two Factor authentication not configured'];
    }
    return $event;
}

function reportError(string $message, array $info){
    $uS = Session::getInstance();

    $body = "New bug report received from " . $uS->siteName . "\r\n\r\n";
    $body .= "Request Type: AJAX\r\n\r\n";
    $body .= "Details: \r\n\r\n";
    $body .= "Message: " . $message . "\r\n\r\n";
    $body .= "User: " . (isset($uS->username) ? $uS->username : "unknown") . "\r\n\r\n";
    foreach($info as $k=>$v){
        $body .= $k . ": " . $v . "\r\n\r\n";
    }

    $subject = "New bug report received from " . $uS->siteName;
    $headers = "From: BugReporter<noreply@nonprofitsoftwarecorp.org>\r\n";

    mail($uS->errorReportEmail, $subject, $body, $headers);

}