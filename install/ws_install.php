<?php
use HHK\Update\Install;

/**
 * ws_install.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK

 */

require_once ("InstallIncludes.php");

//Check request
if(filter_input(INPUT_SERVER, "REQUEST_METHOD", FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== "POST"){
    http_response_code(415);
    echo "Error 415: Method Not Allowed";
    die();
}
if(!str_contains(filter_input(INPUT_SERVER, "HTTP_ACCEPT"), "application/json")){
    http_response_code(406);
    echo "Error 406: Request Not Acceptable";
    die();
}

header('Content-Type: application/json; charset=utf-8');
$c = "";
if (filter_has_var(INPUT_POST, 'cmd')) {
    $c = filter_input(INPUT_POST, 'cmd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$events = array();
$installer = new Install();

try{
    switch ($c) {
        case 'isAuto': //tell Manager it can complete a full install
            $events = ["isAuto"=>true];
            break;
        case 'getNextStep': //check install status
            $events = ["nextStep"=>$installer->getNextStep()];
            break;
        case "testdb":
            $events = $installer->testDB($_POST);
            break;
        case "installdb":
            $events = $installer->installDB();
            break;
        case "loadmd":
            $adminPW = filter_input(INPUT_POST, 'new', FILTER_UNSAFE_RAW);
            $npscUserPW = filter_input(INPUT_POST, 'npscuserpw', FILTER_UNSAFE_RAW);
            if($adminPW){
                $events = $installer->loadMetadata($adminPW, $npscUserPW);
            }else{
                $events = ['error'=>"admin password is required"];
            }
            break;
        case "uploadZipCodFile":
            if(isset($_FILES['zipfile'])){
                $events = $installer->loadZipFile($_FILES['zipfile']);
            }else{
                $events = ['error'=>"zipFile is required"];
            }
            break;
        case "installRooms":
            $post = filter_input_array(INPUT_POST, [
                "txtRooms"=>FILTER_SANITIZE_NUMBER_INT,
                "selModel"=>FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                "cbFin"=>FILTER_VALIDATE_BOOLEAN
            ]);
            if($post["txtRooms"] && $post['selModel']){
                $events = $installer->installRooms($post['txtRooms'], $post["selModel"], $post["cbFin"]);
            }else{
                $events = ['error'=> 'Fields txtRooms and selModel are required'];
            }
            
            break;
        default:
            $events = ["error"=>"Bad Command"];
    }
}catch(Exception $e){
    http_response_code(500);
    $events = ["server_error"=> $e->getMessage()];
}

// return results.
if(isset($events['error']) || isset($events['errors'])){
    http_response_code(422); // error 422: unprocessable Entity
}
echo( json_encode($events));
exit();