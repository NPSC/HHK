<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
 <script type="text/javascript">
        function DoWindowOnLoad() {
            var w = opener;
            if (!w) {
                w = parent.window;
            }
            else {
                w = opener.window;
            }
            w.location = 'register.php?imt=imsale&imres=c';

        }
    </script>
    </head>
    <body onload="DoWindowOnLoad(); return false; ">

        <?php
        // put your code here
        ?>
    </body>
</html>
