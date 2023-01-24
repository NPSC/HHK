<?php

namespace HHK\devTools\Minify;

use GuzzleHttp\Client;

class JS extends AbstractMinify{

    public function minify(string $outFilePath = ""){

        if(strlen($this->contents) > 0){
            $client = new Client();
            $response = $client->post("https://www.toptal.com/developers/javascript-minifier/api/raw", [
                "form_params"=>["input"=>$this->contents]
            ]);

            if($response->getStatusCode() == 200){
                //save file
                file_put_contents(REL_BASE_DIR . $outFilePath, $response->getBody());
                return "File saved successfully";
            }
        }
        return false;
    }
}

?>