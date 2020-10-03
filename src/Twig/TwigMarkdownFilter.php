<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigMarkdownFilter extends AbstractExtension
{

    public function getFilters()
    {
        return array(
            new TwigFilter('markdown', array($this, 'markdown')),
        );
    }

    public function markdown($var, $prop = null)
    {
        $parser = new class extends \ParsedownExtraPlugin {
            public $array_lines = [];

            protected function blockHeader($Line)
            {
                $Block = parent::blockHeader($Line);

                // Set headings
                if (isset($Block['element']['name'])) {
                    $Level = (integer) trim($Block['element']['name'], 'h');
                    $this->array_lines[] = $Block;
                }

                return $Block;
            }
        };
        $parser->setSafeMode(true);
        $parser->table_class = 'ui celled table';
        $html = $parser->text(trim($var));
        return '<!-- '.json_encode($parser->array_lines, JSON_PRETTY_PRINT).' -->'.$html;
    }
}