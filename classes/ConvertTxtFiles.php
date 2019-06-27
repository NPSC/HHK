<?php

/*
 * The MIT License
 *
 * Copyright 2019 Eric.
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

require CLASSES . 'Markdownify/Converter.php';
require CLASSES . 'Markdownify/Parser.php';


/**
 * Description of ConvertTxtFiles
 *
 * @author Eric & Will
 */
class ConvertTxtFiles {

    public static function doMarkdownify(\PDO $dbh) {

        // Run forms editor update
        $converter = new \Markdownify\Converter();
        $result = '';

        if (file_exists('../conf/agreement.txt')) {

            $result .= '<br/>agreement.txt file found.  ';

            $doc = new Document($dbh, Document::findDocumentId($dbh, '', '', '', Document_Name::Registration));

            if (!$doc->isValid()) { //if agreement document cannot be found

                $htmlcontent = file_get_contents("../conf/agreement.txt");
                $mdcontent = $converter->parseString($htmlcontent);

                $doc->createNew($mdcontent, 'House Registration', 'md', 'form', '', Document_Name::Registration);
                $idDoc = $doc->save($dbh, 'admin');

                if ($idDoc > 0) {
                    $result .= 'agreement.txt converted.  ';
                }

            } else {
                $result .= 'agreement.txt already loaded into DB.  ';
            }
        } else {
            $result .= 'agreement.txt file not found.  ';
        }


        if (file_exists('../conf/confirmation.txt')) {

            $result .= '<br/>confirmation.txt file found.  ';

            $doc = new Document($dbh, Document::findDocumentId($dbh, '', '', '', Document_Name::Confirmation));

            if (!$doc->isValid()) {//if confirmation document cannot be found

                $htmlcontent = file_get_contents("../conf/confirmation.txt");
                $mdcontent = $converter->parseString($htmlcontent);

                $doc->createNew($mdcontent, 'Confirmation Form', 'md', 'form', '', Document_Name::Confirmation);
                $idDoc = $doc->save($dbh, 'admin');

                if ($idDoc > 0) {
                    $result .= 'confirmation.txt converted.  ';
                }
            } else {
                $result .= 'confirmation.txt already loaded into DB.  ';
            }
        } else {
            $result .= 'confirmation.txt file not found.  ';
        }


        if (file_exists('../conf/survey.txt')) {

            $result .= '<br/>survey.txt file found.  ';

            $doc = new Document($dbh, Document::findDocumentId($dbh, '', '', '', Document_Name::Survey));

            if (!$doc->isValid()) {//if survey document cannot be found

                $htmlcontent = file_get_contents("../conf/survey.txt");
                $mdcontent = $converter->parseString($htmlcontent);

                $doc->createNew($mdcontent, 'Survey', 'md', 'form', '', Document_Name::Survey);
                $idDoc = $doc->save($dbh, 'admin');

                if ($idDoc > 0) {
                    $result .= 'survey.txt converted.  ';
                }
            } else {
                $result .= 'survey.txt already loaded into DB.  ';
            }
        } else {
            $result .= 'survey.txt file not found.  ';
        }

        return $result;
    }

}
