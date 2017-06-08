<?php

/*
 * The MIT License
 *
 * Copyright 2017 Eric.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of WordXML
 *
 * @author Eric
 */

require '../vendor/autoload.php';

class WordXML {
    
    protected $templateProcessor;
    
    public function createNewDoc() {
        
                
        $this->templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor(REL_BASE_DIR . 'conf' . DS .'ConfirmationLetter.dotx');

        $vals = $this->templateProcessor->getVariables();
        
        $this->templateProcessor->setValue('GuestName', 'Mr. John Doe');
        $this->templateProcessor->setValue('AddressBlock', 'Detroit'); 
        $this->templateProcessor->setValue('expectedArrival', 'June 25, 1970');
        $this->templateProcessor->setValue('dateToday', date('M j, Y'));
        $this->templateProcessor->setValue('GreetingLine', 'Dear ' . 'John');
        
        $this->templateProcessor->saveAs('../patch/confdoc.dotx');
    }
    
    public function finalize() {
        
//        header("Content-Description: File Transfer");
//        header('Content-Disposition: attachment; filename="' . $fileName . '"');
//        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
//        header('Content-Transfer-Encoding: binary');
//        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
//        header('Expires: 0');
 

        $xmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($this->templateProcessor, 'HTML');
        $xmlWriter->save("php://output");

    }
}
