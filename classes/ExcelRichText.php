<?php

namespace HHK;

/**
 * ExcelRichText.php
 *
 * A cell value made of differently colored text runs, written by ExcelHelper as an
 * inline rich text string.  Pass it as a cell value to ExcelHelper::writeSheetRow().
 *
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ExcelRichText {

    /** @var array<int,array{0:string,1:string}> [text, '#RRGGBB' or '' for the default color] */
    protected array $runs = [];

    /**
     * @param string $text
     * @param string $color '#RRGGBB', or '' for the default font color
     * @return ExcelRichText
     */
    public function addRun(string $text, string $color = ''): ExcelRichText {
        if ($text !== '') {
            $this->runs[] = [$text, $color];
        }
        return $this;
    }

    /**
     * Returns a copy with $fn applied to the text of every run, keeping the colors.
     *
     * @param callable $fn string => string
     * @return ExcelRichText
     */
    public function mapText(callable $fn): ExcelRichText {
        $copy = new ExcelRichText();
        foreach ($this->runs as [$text, $color]) {
            $copy->addRun($fn($text), $color);
        }
        return $copy;
    }

    public function getRuns(): array {
        return $this->runs;
    }

    public function isEmpty(): bool {
        return count($this->runs) == 0;
    }
}
