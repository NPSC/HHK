<?php
/**
 * ChallengeGenerator.php
 *
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

class ChallengeGenerator{

  function __ChallengeGenerator($clearSession=true){
    if($clearSession){
      $this->clearVars();
    }
  }

  function clearVars(){
    // destroy existing session
    $ssn = Session::getInstance();
    $ssn->destroy();
  }

  function setChallengeVar(){
    // register session variable
    $ssn = Session::getInstance();
    $ssn->challenge = $this->getRandomString();
    return $ssn->challenge;
  }

  function incrementTries() {
    $ssn = Session::getInstance();
    if (isset($ssn->Challtries) === FALSE) {
        $ssn->Challtries = 0;
    }
    $ssn->Challtries++;
    return $ssn->Challtries;
  }

  function testTries($max = 3) {
      $ssn = Session::getInstance();
      if (isset($ssn->Challtries) && $ssn->Challtries > $max) {
          return FALSE;
      }
      return TRUE;
  }

  function getChallengeVar(){
    $ssn = Session::getInstance();
    return $ssn->challenge;
  }

  function deleteChallengeVar(){
    $ssn = Session::getInstance();
    if($ssn->challenge){
        unset($ssn->challenge);
    }
  }
  // private method "getRandomString()"
  function getRandomString($length=40){
    if(!is_int($length)||$length<1){
      $length = 40;
    }
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $randstring = '';
    $maxvalue = strlen($chars) - 1;
    for($i=0; $i<$length; $i++){
      $randstring .= substr($chars, rand(0,$maxvalue), 1);
    }
    return $randstring;
  }
}

