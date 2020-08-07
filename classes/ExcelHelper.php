<?php

namespace HHK;

/**
 * Excel.php
 *
 * Helper class for mk-j\XLSXWriter
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ExcelHelper {
    
    CONST hdrStyle = ['font-style'=>'bold', 'halign'=>'center', 'auto_filter'=>true, 'widths'=>[]];
    
    public static function download(\XLSXWriter $writer, string $filename){
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->writeToStdOut();
        die();
    }
    
    public static function convertStrings(array $hdr, array $row){
        $n = 0;
        
        foreach($hdr as $val){
            if($val = "string" && isset($row[$n])){
                $row[$n] = html_entity_decode(strval($row[$n]), ENT_QUOTES, 'UTF-8');
            }
            $n++;
        }
        
        return $row;
    }
    
    public static function getHdrStyle(array $colWidths){
        $hdrStyle = self::hdrStyle;
        
        foreach($colWidths as $width){
            $hdrStyle['widths'][] = $width;
        }
        
        return $hdrStyle;
    }
    
}

?>