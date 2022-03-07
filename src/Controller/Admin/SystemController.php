<?php

namespace App\Controller\Admin;

use App\Base\RenoController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SystemController extends RenoController
{

    /**
     * @Route("/!phpinfo", name="app_admin_phpinfo", priority=10)
     */
    public function phpinfo()
    {
        $this->requireAdminRole();
        $this->title = "PHP Info";
        $this->addCrumb('PHP Info', $this->nav->path('app_admin_phpinfo'), 'php');
        return $this->render("renobase.html.twig", array(
                'content' => '<iframe id="topmargin" style="position:fixed; top:0px; left:0; bottom:0; right:0; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" src="'.$this->nav->path('app_admin_phpinfo_content').'" />'
        ));
    }

    /**
     * @Route("/!phpinfo!", name="app_admin_phpinfo_content", priority=10)
     */
    public function phpinfo_content()
    {
        $this->requireAdminRole();
        ob_start();
        phpinfo();
        $html = ob_get_contents();
        ob_end_clean();
        return new Response($html);
    }

    /**
     * @Route("/!viewlog", name="app_admin_viewlog", priority=10)
     */
    public function viewlog(\App\Kernel $kernel)
    {
        $this->requireAdminRole();
        $this->title = "View Log";
        $this->addCrumb('View Log', $this->nav->path('app_admin_viewlog'), 'bug');
        $logfile = 'prod.log';
        return $this->render("renobase.html.twig", [
                'content' => '<h3>Showing Last 100 Lines of <em>'.$logfile.'</em></h3><pre style="overflow:auto">'.htmlentities(static::tailCustom($kernel->getLogDir()."/$logfile", 100)).'</pre>'
        ]);
    }

    /**
     * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * @author Torleif Berger, Lorenzo Stanco
     * @link http://stackoverflow.com/a/15025877/995958
     * @license http://creativecommons.org/licenses/by/3.0/
     */
    static protected function tailCustom($filepath, $lines = 1, $adaptive = true)
    {
        // Open file
        $f = @fopen($filepath, "rb");
        if ($f === false) return false;

        // Sets buffer size, according to the number of lines to retrieve.
        // This gives a performance boost when reading a few lines from the file.
        if (!$adaptive) $buffer = 4096;
        else $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") $lines -= 1;

        // Start reading
        $output = '';
        $chunk = '';

        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {

            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);

            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);

            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)).$output;

            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {

            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }

        // Close file and return
        fclose($f);
        return trim($output);
    }
}