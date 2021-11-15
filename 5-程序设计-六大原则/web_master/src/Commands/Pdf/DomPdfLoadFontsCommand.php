<?php

namespace App\Commands\Pdf;

use Dompdf\Dompdf;
use Exception;
use FontLib\Font;
use Illuminate\Console\Command;

class DomPdfLoadFontsCommand extends Command
{
    protected $signature = 'pdf:dompdf-load-fonts {--quiet: 不输出日志}';
    protected $description = '加载 dompdf 可用的字体，从 public/fonts/dompdf 目录下';
    protected $help = '';

    public function handle()
    {
        // 实现参考：https://github.com/dompdf/utils/edit/master/load_font.php

        $dompdf = app('dompdf.instance');
        $fontMetrics = $dompdf->getFontMetrics();

        $files = glob(app()->pathAliases->get('@public/fonts/dompdf/*.ttf'));
        $fonts = [];
        foreach ($files as $file) {
            $font = Font::load($file);
            $records = $font->getData('name', 'records');
            $type = $fontMetrics->getType($records[2]);
            $fonts[mb_strtolower($records[1])][$type] = $file;
            $font->close();
        }

        foreach ($fonts as $family => $files) {
            $this->writeLine(" >> Installing '$family'... ");

            if (!isset($files["normal"])) {
                $this->writeLine("No 'normal' style font file");
            } else {
                $this->install_font_family($dompdf, $family, @$files["normal"], @$files["bold"], @$files["italic"], @$files["bold_italic"]);
                $this->writeLine("Done !\n");
            }

            $this->writeLine();
        }

        $this->writeLine('实际可用字体名请见：' . app()->pathAliases->get('@vendor/dompdf/dompdf/lib/fonts/dompdf_font_family_cache.php'));

        return 0;
    }

    private function writeLine($msg = '')
    {
        if ($this->option('quiet')) {
            return;
        }
        echo $msg . "\n";
    }

    /**
     * Installs a new font family
     * This function maps a font-family name to a font.  It tries to locate the
     * bold, italic, and bold italic versions of the font as well.  Once the
     * files are located, ttf versions of the font are copied to the fonts
     * directory.  Changes to the font lookup table are saved to the cache.
     *
     * @param Dompdf $dompdf dompdf main object
     * @param string $fontname the font-family name
     * @param string $normal the filename of the normal face font subtype
     * @param string $bold the filename of the bold face font subtype
     * @param string $italic the filename of the italic face font subtype
     * @param string $bold_italic the filename of the bold italic face font subtype
     *
     * @throws Exception
     */
    function install_font_family($dompdf, $fontname, $normal, $bold = null, $italic = null, $bold_italic = null)
    {
        $fontMetrics = $dompdf->getFontMetrics();

        // Check if the base filename is readable
        if (!is_readable($normal))
            throw new Exception("Unable to read '$normal'.");

        $dir = dirname($normal);
        $basename = basename($normal);
        $last_dot = strrpos($basename, '.');
        if ($last_dot !== false) {
            $file = substr($basename, 0, $last_dot);
            $ext = strtolower(substr($basename, $last_dot));
        } else {
            $file = $basename;
            $ext = '';
        }

        if (!in_array($ext, array(".ttf", ".otf"))) {
            throw new Exception("Unable to process fonts of type '$ext'.");
        }

        // Try $file_Bold.$ext etc.
        $path = "$dir/$file";

        $patterns = array(
            "bold" => array("_Bold", "b", "B", "bd", "BD"),
            "italic" => array("_Italic", "i", "I"),
            "bold_italic" => array("_Bold_Italic", "bi", "BI", "ib", "IB"),
        );

        foreach ($patterns as $type => $_patterns) {
            if (!isset($$type) || !is_readable($$type)) {
                foreach ($_patterns as $_pattern) {
                    if (is_readable("$path$_pattern$ext")) {
                        $$type = "$path$_pattern$ext";
                        break;
                    }
                }

                if (is_null($$type))
                    $this->writeLine("Unable to find $type face file.");
            }
        }

        $fonts = compact("normal", "bold", "italic", "bold_italic");
        $entry = array();

        // Copy the files to the font directory.
        foreach ($fonts as $var => $src) {
            if (is_null($src)) {
                $entry[$var] = $dompdf->getOptions()->get('fontDir') . '/' . mb_substr(basename($normal), 0, -4);
                continue;
            }

            // Verify that the fonts exist and are readable
            if (!is_readable($src))
                throw new Exception("Requested font '$src' is not readable");

            $dest = $dompdf->getOptions()->get('fontDir') . '/' . basename($src);

            if (!is_writeable(dirname($dest)))
                throw new Exception("Unable to write to destination '$dest'.");

            $this->writeLine("Copying $src to $dest...");

            if (!copy($src, $dest))
                throw new Exception("Unable to copy '$src' to '$dest'");

            $entry_name = mb_substr($dest, 0, -4);

            $this->writeLine("Generating Adobe Font Metrics for $entry_name...");

            $font_obj = Font::load($dest);
            $font_obj->saveAdobeFontMetrics("$entry_name.ufm");
            $font_obj->close();

            $entry[$var] = $entry_name;
        }

        // Store the fonts in the lookup table
        $fontMetrics->setFontFamily($fontname, $entry);

        // Save the changes
        $fontMetrics->saveFontFamilies();
    }
}
