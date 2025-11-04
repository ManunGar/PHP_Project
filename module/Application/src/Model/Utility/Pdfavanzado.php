<?php
namespace Application\Model\Utility;

use Zend\Pdf\Resourse\Adapter;
use ZendPdf\Resource\Font;
/**
 * Advanced PDF functionnalities
 */
class Pdfavanzado
{
    /**
     * Align text at left of provided coordinates
     */
    const TEXT_ALIGN_LEFT = 'left';

    /**
     * Align text at right of provided coordinates
     */
    const TEXT_ALIGN_RIGHT = 'right';

    /**
     * Center-text horizontally within provided coordinates
     */
    const TEXT_ALIGN_CENTER = 'center';

    /**
     * Extension of basic draw-text function to allow it to horizontally center text
     */
    public static function drawTextWithPosition(Zend_Pdf_Page $page, $text, $y1, $xOffset = 0, $position = self::TEXT_ALIGN_LEFT, $encoding = null)
    {
        $bottom = $y1; // could do the same for vertical-centering
        $text_width = $this->getTextWidth($text, $page->getFont(), $page->getFontSize());

        switch ($position) {
            case self::TEXT_ALIGN_LEFT:
                $left = 60 + $xOffset;
                break;
            case self::TEXT_ALIGN_RIGHT:
                $left = $page->getWidth() - $text_width - $page->getFontSize() - 35 + $xOffset;
                break;
            case self::TEXT_ALIGN_CENTER:
                $left = ($page->getWidth() / 2) - ($text_width / 2) + $xOffset;
                break;
            default:
                throw new Exception("Invalid position value \"$position\"");
        }

        // display multi-line text
        foreach (explode(PHP_EOL, $text) as $i => $line) {
            $page->drawText($line,$left,$y1,$encoding);
        }
        return $this;
    }

    /**
     * Return length of generated string in points
     *
     * @param string $string
     * @param Zend_Pdf_Resource_Font $font
     * @param int $font_size
     * @return double
     */
	public static function getTextWidth($text, $font, $font_size) 
    {
        $drawing_text = iconv('ISO-8859-1', 'UTF-16BE//IGNORE', $text);
        $characters    = array();
        for ($i = 0; $i < strlen($drawing_text); $i++) {
            $characters[] = (ord($drawing_text[$i++]) << 8) | ord ($drawing_text[$i]);
        }
        $glyphs        = $font->glyphNumbersForCharacters($characters);
        $widths        = $font->widthsForGlyphs($glyphs);
        $text_width   = (array_sum($widths) / $font->getUnitsPerEm()) * $font_size;
        return $text_width; 
    }
    /**
     * Return length of generated string in points
     *
     * @param string $string
     * @param Zend_Pdf_Resource_Font $font
     * @param int $font_size
     * @return double
     */
    public static function getTextWidth2($text, $font_size) 
    {
        $drawing_text = iconv('', 'UTF-16BE', $text);
        $characters    = array();
        for ($i = 0; $i < strlen($drawing_text); $i++) {
            $characters[] = (ord($drawing_text[$i++]) << 8) | ord ($drawing_text[$i]);
        }
        var_dump($characters);die();
        $glyphs        = $characters;//$font->glyphNumbersForCharacters($characters);
        $widths        = $glyphs; //$font->widthsForGlyphs($glyphs);
        $text_width   = $characters;
        return $text_width; 
    }
}
?>