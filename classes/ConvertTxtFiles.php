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

require CLASSES . 'Markdownify.php';
require CLASSES . 'Parsedown.php';

/**
 * Description of ConvertTxtFiles
 *
 * @author Eric & Will
 */
class ConvertTxtFiles {

    public static function doMarkdownify(\PDO $dbh) {

        // Run forms editor update
        $converter = new Markdownify\Converter;
        $result = '';

        if (file_exists('../conf/agreement.txt')) {

            $result .= '<br/>agreement.txt file found.  ';

            $stmt = $dbh->query("select idDocument from document where `Title` = 'Registration Document' and `Category` = 'form' and `Type` = 'md' and `Status` = 'a'");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($stmt->rowCount() == 0) { //if agreement document cannot be found
                $htmlcontent = file_get_contents("../conf/agreement.txt");
                $mdcontent = $converter->parseString($htmlcontent);
                $agreeCt = $dbh->exec("INSERT INTO document (`Title`, `Abstract`, `Category`, `Type`, `Doc`, `Status`, `Last_Updated`, `Created_By`) VALUES ('Registration Document', '', 'form', 'md', " . $mdcontent . ", 'a', NOW(), 'admin'),");

                if ($agreeCt > 0) {
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

            $stmt = $dbh->query("select idDocument from document where `Title` = 'Confirmation Document' and `Category` = 'form' and `Type` = 'md' and `Status` = 'a'");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($stmt->rowCount() == 0) {//if confirmation document cannot be found
                $htmlcontent = file_get_contents("../conf/confirmation.txt");
                $mdcontent = $converter->parseString($htmlcontent);
                $agreeCt = $dbh->exec("INSERT INTO document (`Title`, `Abstract`, `Category`, `Type`, `Doc`, `Status`, `Last_Updated`, `Created_By`) VALUES ('Registration Document', '', 'form', 'md', " . $mdcontent . ", 'a', NOW(), 'admin'),");

                if ($agreeCt > 0) {
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

            $stmt = $dbh->query("select idDocument from document where `Title` = 'Survey Document' and `Category` = 'form' and `Type` = 'md' and `Status` = 'a'");
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($stmt->rowCount() == 0) {//if survey document cannot be found
                $htmlcontent = file_get_contents("../conf/survey.txt");
                $mdcontent = $converter->parseString($htmlcontent);
                $agreeCt = $dbh->exec("INSERT INTO document (`Title`, `Abstract`, `Category`, `Type`, `Doc`, `Status`, `Last_Updated`, `Created_By`) VALUES ('Registration Document', '', 'form', 'md', " . $mdcontent . ", 'a', NOW(), 'admin'),");

                if ($agreeCt > 0) {
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
