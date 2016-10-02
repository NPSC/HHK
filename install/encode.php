<?php
require_once ("../functions/commonFunc.php");

        $encrypted = "";
        $unencrypted = "";
        if (isset($_POST['clearTxt']) || isset($_POST['encriptedTxt'])) {
            if ($_POST['clearTxt'] != '') {
                $encrypted = encryptMessage($_POST['clearTxt']);
            }

            if ($_POST['encriptedTxt'] != '') {
                $unencrypted = decryptMessage($_POST['encriptedTxt']);
            }
        }
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title></title>
    </head>
    <body>
         <form action="encode.php" method="post"  id="form1" name="form1" >
             <input type="text" name="clearTxt" value="" /><span style="margin-left: 25px;"><?php echo $encrypted; ?></span>
             <input type="text" name="encriptedTxt" value="" /><span style="margin-left: 25px;"><?php echo $unencrypted; ?></span>
             <input type="submit" name="btnSubmit" value="Enter"/>
         </form>

    </body>
</html>
