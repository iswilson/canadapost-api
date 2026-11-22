<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Support;

/**
 * Helper for creating variations of XML fixtures.
 */
final class XmlFixture
{
    /**
     * Return XML with first instance of matching element emptied.
     */
    public static function withEmptyElement(string $xml, string $elementName): string
    {
        $doc = self::load($xml);

        $doc->getElementsByTagName($elementName)->item(0)->textContent = '';
        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \LogicException('Failed to build XML from DOMDocument.');
        }

        return $xml;
    }

    /**
     * Return XML with first instance of matching element removed.
     */
    public static function withoutElement(string $xml, string $elementName): string
    {
        $doc = self::load($xml);

        $doc->getElementsByTagName($elementName)->item(0)->remove();
        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \LogicException('Failed to build XML from DOMDocument.');
        }

        return $xml;
    }

    /**
     * Return XML with text replaced on first instance of matching element.
     */
    public static function withElementText(string $xml, string $elementName, string $replacement): string
    {
        $doc = self::load($xml);

        $doc->getElementsByTagName($elementName)->item(0)->textContent = $replacement;
        $xml = $doc->saveXML();
        if ($xml === false) {
            throw new \LogicException('Failed to build XML from DOMDocument.');
        }

        return $xml;
    }

    /**
     * Load provided XML string into DOMDocument.
     */
    private static function load(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();

        // Suppress libxml errors while reading data.
        $previousSetting = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);
        if (!$loaded) {
            throw new \InvalidArgumentException('Failed to parse XML into DOMDocument.');
        }

        return $doc;
    }
}
