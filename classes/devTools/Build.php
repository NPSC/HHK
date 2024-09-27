<?php
namespace HHK\devTools;

use Composer\Script\Event;

/**
 * Class for preparing a new build for production including minifing, etc
 *
 */
class Build {

    /**
     * list all files to be minified, path is relative to HHK root, "in" is input path, "out" is output path
     */
    const MINIFYFILES = [
        ["in"=>"house/js/resvManager.js","out"=>"house/js/resvManager-min.js"]
    ];

    /**
     * @param Event $event - Composer Event
     */
    public static function run(Event $event){
        self::minify($event);
    }

    /**
     * @param Event $event - Composer Event
     */
    public static function minify(Event $event){
        $event->getIO()->write("Minifying " . count(self::MINIFYFILES) . " files...");
        $successCount = 0;
        foreach(self::MINIFYFILES as $file){
            try{
                $info = pathinfo($file['in']);
                switch($info['extension']){
                    case "js":
                        $minifier = new Minify\JS($file['in']);
                        break;
                    default:
                        $minifier = false;
                }

                if($minifier){
                    $minifier->minify($file['out']);
                    $event->getIO()->write("Minified: " . $file['out']);
                    $successCount++;
                }
            }catch(\Exception $e){
                $event->getIO()->write($e->getMessage());
            }
        }
        $event->getIO()->write("Done. Minified " . $successCount . " files");
    }

}

?>