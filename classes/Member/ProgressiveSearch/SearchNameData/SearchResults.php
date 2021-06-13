<?php
namespace HHK\Member\ProgressiveSearch\SearchNameData;

class SearchResults extends SearchNameData
{
    
    protected $noReturn;
    
    public function setNameFirst($nameFirst) {
        $this->nameFirst = preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameFirst
            );
        return $this;
    }
    
    /**
     * @param string $nameMiddle
     */
    public function setNameMiddle($nameMiddle) {
        $this->nameMiddle =  preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameMiddle
            );
        return $this;
    }
    
    /**
     * @param string $nameLast
     */
    public function setNameLast($nameLast) {
        $this->nameLast =  preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nameLast
            );
        return $this;
    }
    
    /**
     * @param string $nickname
     */
    public function setNickname($nickname) {
        $this->nickname =  preg_replace_callback("/(&#[0-9]+;)/",
            function($m) {
                return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
            },
            $nickname
            );
        return $this;
    }
    
    /**
     * @param string $nickname
     */
    public function setNoReturn($noReturn) {
        $this->noReturn = $noReturn;
        return $this;
    }
    
    
}

