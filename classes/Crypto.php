<?php

namespace HHK;

use Illuminate\Encryption\Encrypter;

/**
 * Crypto.php
 * 
 * Contains methods for handling encryption
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class Crypto {

    protected const KEY = "017d609a4b2d8910685595C8df";

    protected const IV = "fYfhHeDmf j98UUy4";

    public const string CIPHER = "aes-256-gcm";

    public static function encryptMessage(#[\SensitiveParameter] string $input): string
    {
        $encrypter = new Encrypter(static::decodeKey($_ENV["APP_KEY"]), static::CIPHER);
        return $encrypter->encryptString($input);
    }

    public static function decryptMessage(string $encrypt): string
    {
        try{
            $encrypter = new Encrypter(static::decodeKey($_ENV['APP_KEY']), static::CIPHER);
            
            if(isset($_ENV['APP_PREVIOUS_KEYS'])){
                $previousKeys = explode(',', $_ENV['APP_PREVIOUS_KEYS']);
                
                foreach($previousKeys as $k=>$key){
                    $previousKeys[$k] = static::decodeKey($key);
                }

                $encrypter->previousKeys($previousKeys);
            }

            return $encrypter->decryptString($encrypt);
        }catch(\Exception $e){
            return static::encrypt_decrypt('decrypt', $encrypt, static::KEY, static::IV);
        }
        
    }

    protected static function decodeKey(#[\SensitiveParameter] string $key){
        try{
            if(str_starts_with($key, "base64:")){
                $base64 = substr($key, strlen("base64:"));
                return base64_decode($base64);
            }
        }catch(\Exception){

        }

        return "";
    }

    /**
     * simple method to encrypt or decrypt a plain text string
     * initialization vector(IV) has to be the same when encrypting and decrypting
     *
     * @param string $action:
     *            can be 'encrypt' or 'decrypt'
     * @param string $string:
     *            string to encrypt or decrypt
     *
     * @return string
     */
    protected static function encrypt_decrypt(string $action, string $string, string $secret_key, string $secret_iv): string
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        // $secret_key = 'This is my secret key';
        // $secret_iv = 'This is my secret iv';
        // hash
        $key = hash('sha256', $secret_key);

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action == 'encrypt') {
            $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
        } else if ($action == 'decrypt') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }

}