<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\Exception\CanadaPostException;
use Psr\Http\Message\ResponseInterface;

/**
 * Process responses from Canada Post Developer Program (XML) platform.
 *
 * @internal
 */
final class XmlResponse
{
    /**
     * Turn an API response into a DOMDocument (or throw exceptions trying).
     *
     * @throws CanadaPostException
     */
    public static function toDOMDocument(ResponseInterface $response, string $expectedRootElement): \DOMDocument
    {
        $responseBody = (string) $response->getBody();
        // Response is empty.
        if ($responseBody === '') {
            throw new CanadaPostException(
                'Canada Post API returned an empty response.',
                httpStatusCode: $response->getStatusCode(),
            );
        }

        $doc = self::load($responseBody);
        // Response is not valid XML.
        if ($doc === null) {
            throw new CanadaPostException(
                'Canada Post API returned invalid XML.',
                httpStatusCode: $response->getStatusCode(),
            );
        }

        // Response contains error codes/messages.
        if ($doc->documentElement?->localName === 'messages') {
            $errorMessages = self::parseErrorMessages($doc);
            $errorCodes = (count($errorMessages) > 0) ? array_column($errorMessages, 'code') : ['no error code'];

            throw new CanadaPostException(
                sprintf('Canada Post API request failed (%s).', implode(', ', $errorCodes)),
                errors: $errorMessages,
                httpStatusCode: $response->getStatusCode(),
            );
        }

        // Response is valid XML but doesn't contain the expected root element.
        if ($doc->documentElement === null || $doc->documentElement->localName !== $expectedRootElement) {
            throw new CanadaPostException(
                vsprintf('Canada Post API returned valid XML but required elements are missing, expected %s, got %s.', [
                    $expectedRootElement,
                    $doc->documentElement->localName ?? 'no root element',
                ]),
                httpStatusCode: $response->getStatusCode(),
            );
        }

        return $doc;
    }

    /**
     * Load an XML response string into a DOMDocument.
     */
    public static function load(string $xml): ?\DOMDocument
    {
        $doc = new \DOMDocument();

        // Suppress libxml errors while reading data.
        $previousSetting = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        return $loaded ? $doc : null;
    }

    /**
     * Parse an XML response into an array of error codes + messages.
     *
     * @return list<array{code: string, message: string}>
     */
    public static function parseErrorMessages(\DOMDocument $doc): array
    {
        $messages = [];
        // Extract error message data.
        $nodes = $doc->getElementsByTagName('message');
        foreach ($nodes as $node) {
            $messages[] = [
                'code' => $node->getElementsByTagName('code')->item(0)->textContent ?? '',
                'message' => $node->getElementsByTagName('description')->item(0)->textContent ?? '',
            ];
        }

        return $messages;
    }

    /**
     * @return string|null
     *   Returns null if element is empty or missing.
     */
    public static function extractText(\DOMElement $element, string $qualifiedName): ?string
    {
        $text = trim($element->getElementsByTagName($qualifiedName)->item(0)->textContent ?? '');
        return $text === '' ? null : $text;
    }

    /**
     * @return \DateTimeImmutable|null
     *   Returns null if element is empty or missing.
     *
     * @throws CanadaPostException
     *   On invalid or malformed date.
     */
    public static function extractDateTime(\DOMElement $element, string $qualifiedName, string $format = '!Y-m-d'): ?\DateTimeImmutable
    {
        $text = self::extractText($element, $qualifiedName);
        if ($text === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat($format, $text, new \DateTimeZone('UTC'));
        if ($date === false) {
            throw new CanadaPostException('Canada Post API returned a malformed date.');
        }

        // Formatted date (minus ! field reset char) should match text.
        if ($date->format(ltrim($format, '!')) !== $text) {
            throw new CanadaPostException('Canada Post API returned an invalid date.');
        }

        return $date;
    }

    /**
     * Convert ISO datetime string to DateTimeImmutable, truncated to seconds.
     *
     * @return \DateTimeImmutable|null
     *   Returns null if element is empty or missing.
     *
     * @throws CanadaPostException
     *   On invalid or malformed date.
     */
    public static function extractIsoDateTime(\DOMElement $element, string $qualifiedName): ?\DateTimeImmutable
    {
        $text = self::extractText($element, $qualifiedName);
        if ($text === null) {
            return null;
        }

        // Cut anything smaller than a second.
        $text = preg_replace('/(T\d{2}:\d{2}:\d{2})([\d.]?)+/', '$1', $text)
            ?? throw new CanadaPostException('Canada Post API returned a malformed date.');

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $text);
        if ($date === false) {
            throw new CanadaPostException('Canada Post API returned a malformed date.');
        }

        // Formatted date should match text (convert Z to timezone offset).
        if ($date->format(\DateTimeInterface::ATOM) !== str_replace('Z', '+00:00', $text)) {
            throw new CanadaPostException('Canada Post API returned an invalid date.');
        }

        return $date;
    }
}
