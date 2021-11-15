<?php
class ModelToolBarcode extends Model {

    public function __construct($registry)
    {
        parent::__construct($registry);
        $_GET['filetype'] = 'PNG';
        $_GET['dpi'] = 120;
        $_GET['scale'] = 4;
        $_GET['rotation'] = 0;
        $_GET['font_family'] = '0';
        $_GET['font_size'] = 10;
        $_GET['thickness'] = 25;
        $_GET['start'] = null;
        $_GET['code'] = 'BCGcode128';

        define('CODEGE_DIR', DIR_STORAGE . 'vendor/barcodegen/barcodegen');
        define('CODEGE_HTML_DIR', CODEGE_DIR . '/html');
        define('CODEGE_CLASS_DIR', CODEGE_DIR . '/class');
        define('IN_CB', true);
        include_once(CODEGE_HTML_DIR . '/include/function.php');
        $requiredKeys = ['code', 'filetype', 'dpi', 'scale', 'rotation', 'font_family', 'font_size'];
        foreach ($requiredKeys as $key) {
            if (!isset($_GET[$key])) {
                //$this->redirectError();
            }
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $_GET['code'])) {
            //$this->redirectError();
        }




    }

    public function setCode($text,$name){
        $_GET['text']    = $text;
        $code = $_GET['code'];
        if (!file_exists(CODEGE_HTML_DIR . '/config' . DIRECTORY_SEPARATOR . $code . '.php')) {
            $this->redirectError();
        }
        include_once(CODEGE_HTML_DIR . '/config' . DIRECTORY_SEPARATOR . $code . '.php');
        $class_dir = CODEGE_CLASS_DIR;
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGColor.php');
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGBarcode.php');
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGDrawing.php');
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGFontFile.php');
        include_once($class_dir . DIRECTORY_SEPARATOR . 'BCGcode128.barcode.php');
        include_once(CODEGE_HTML_DIR . '/config' . DIRECTORY_SEPARATOR . 'BCGBarcode1D.php');
        $filetypes = ['PNG' => BCGDrawing::IMG_FORMAT_PNG, 'JPEG' => BCGDrawing::IMG_FORMAT_JPEG, 'GIF' => BCGDrawing::IMG_FORMAT_GIF];
        $drawException = null;
        try {
            $color_black = new BCGColor(0, 0, 0);
            $color_white = new BCGColor(255, 255, 255);
            $code_generated = new BCGcode128();
            if (function_exists('baseCustomSetup')) {
                baseCustomSetup($code_generated, $_GET);
            }
            if (function_exists('customSetup')) {
                customSetup($code_generated, $_GET);
            }
            $code_generated->setScale(max(1, min(4, $_GET['scale'])));
            $code_generated->setBackgroundColor($color_white);
            $code_generated->setForegroundColor($color_black);
            if ($_GET['text'] !== '') {
                $text = convertText($_GET['text']);
                $code_generated->parse($text);
            }

        } catch (Exception $exception) {
            $drawException = $exception;
        }
        $drawing = new BCGDrawing('', $color_white);
        if ($drawException) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code_generated);
            $drawing->setRotationAngle($_GET['rotation']);
            $drawing->setDPI($_GET['dpi'] === 'NULL' ? null : max(72, min(300, intval($_GET['dpi']))));
            $drawing->setFilename($name);
            $drawing->draw();
        }
        //switch ($_GET['filetype']) {
        //    case 'PNG':
        //        header('Content-Type: image/png');
        //        break;
        //    case 'JPEG':
        //        header('Content-Type: image/jpeg');
        //        break;
        //    case 'GIF':
        //        header('Content-Type: image/gif');
        //        break;
        //}
        //
        $drawing->finish($filetypes[$_GET['filetype']]);

    }





}



?>