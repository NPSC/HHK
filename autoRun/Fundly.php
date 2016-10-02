<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Fundly
 *
 * @author Eric
 */
include('../thirdParty/httpful.phar');

/*
 * https//api.npez.net/api/individual/SaveContact

Authorization: Bearer accesstoken
 */

//$vars = urlencode("grant_type=password&username=admin@nonprofitsoftwarecorp.org&password=Huron790&client_id=VEAPI&client_secret=secret&scope=openid profile roles Api");
//
//$url = "https://api.npez.net/identity/connect/token" ;
//
//$response = \Httpful\Request::post($url)
//    ->addHeader('Content-type', 'application/x-www-form-urlencoded')
//    ->send($vars);
//
//var_dump($response->body);
//
//exit('Done');

$url = "https://api.npez.net/api/Individual/GetContact?contactId=349196";

$response = \Httpful\Request::get($url)
    ->addHeader('APIKey', 'afa84807-51a1-4f4a-85fe-8a20567a3b58')
    ->send();

$contact = $response->body;
var_dump($response->body);

$contact->NickName = 'Peter';

$url = "https://api.npez.net/api/Individual/SaveContact";

$response = \Httpful\Request::post($url)
    ->addHeader('APIKey', 'afa84807-51a1-4f4a-85fe-8a20567a3b58')
    ->addHeader('User-Agent', 'Non-Profit Software Corporation')
    ->addHeader('Content-type', 'application/json')
    ->body(json_encode($contact))
    ->send();

var_dump($response->body);

