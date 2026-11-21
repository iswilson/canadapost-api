<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Platform\DevProgram;

use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\Platform\DevProgram\XmlResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlResponse::class)]
class XmlResponseTest extends TestCase
{
    public function testInternalServerErrorResponse(): void
    {
        try {
            $this->xmlResponseToDOMDocument('', 500);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(500, $e->httpStatusCode);
        }
    }

    public function testBadRequestAPIResponse(): void
    {
        try {
            $this->xmlResponseToDOMDocument(file_get_contents(__DIR__ . '/../../Mocks/rating-response-error.xml'), 400);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(400, $e->httpStatusCode);
            self::assertCount(2, $e->errors);
        }
    }

    public function testBadRequestInvalidXmlResponse(): void
    {
        try {
            $this->xmlResponseToDOMDocument('this is not an XML string', 400);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(400, $e->httpStatusCode);
        }
    }

    public function testBadRequestNoXmlResponse(): void
    {
        try {
            $this->xmlResponseToDOMDocument('', 400);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(400, $e->httpStatusCode);
        }
    }

    public function testTryAgainLaterResponse(): void
    {
        try {
            $this->xmlResponseToDOMDocument(<<<'EOF'
                <messages>
                    <message>
                        <code>7010</code>
                        <description>We are unable to provide you with a price at this time. Please try again later.</description>
                    </message>
                </messages>
            EOF, 202);
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertSame(202, $e->httpStatusCode);
            self::assertTrue($e->hasErrorCode('7010'));
        }
    }

    public function testEmptyResponse(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('empty response');
        $this->xmlResponseToDOMDocument('');
    }

    public function testInvalidResponse(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('invalid XML');
        $this->xmlResponseToDOMDocument('this is not an XML string');
    }

    public function testKnownXmlRootElement(): void
    {
        $doc = $this->xmlResponseToDOMDocument('<test></test>');
        self::assertSame('test', $doc->documentElement->localName);
    }

    public function testUnknownXmlRootElement(): void
    {
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('returned valid XML but required elements are missing');
        $this->expectExceptionMessage('expected test, got testing');
        $this->xmlResponseToDOMDocument('<testing></testing>');
    }

    public function testResponseWithMultipleErrorMessages(): void
    {
        try {
            $this->xmlResponseToDOMDocument(file_get_contents(__DIR__ . '/../../Mocks/rating-response-error.xml'));
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertCount(2, $e->errors);
            self::assertTrue($e->hasErrorCode('7007'));
            self::assertTrue($e->hasErrorCode('1722'));
            self::assertStringContainsString('7007, 1722', $e->getMessage());
        }
    }

    public function testErrorResponseWithNoErrorMessages(): void
    {
        try {
            $this->xmlResponseToDOMDocument('<messages></messages>');
            self::fail('Expected CanadaPostException exception to be thrown.');
        } catch (CanadaPostException $e) {
            self::assertCount(0, $e->errors);
            self::assertStringContainsString('no error code', $e->getMessage());
        }
    }

    public function testExtractedTextIsTrimmed(): void
    {
        $doc = XmlResponse::load(<<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <origin-postal-id>  ON   </origin-postal-id>
                </pin-summary>
            </tracking-summary>
        EOF);

        $text = XmlResponse::extractText(
            $doc->getElementsByTagName('pin-summary')->item(0),
            'origin-postal-id',
        );
        self::assertSame('ON', $text);
    }

    public function testEmptyElement(): void
    {
        $doc = XmlResponse::load(<<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <origin-postal-id></origin-postal-id>
                </pin-summary>
            </tracking-summary>
        EOF);

        $text = XmlResponse::extractText(
            $doc->getElementsByTagName('pin-summary')->item(0),
            'origin-postal-id',
        );
        self::assertNull($text);
    }

    public function testMissingElement(): void
    {
        $doc = XmlResponse::load(<<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <origin-postal-id></origin-postal-id>
                </pin-summary>
            </tracking-summary>
        EOF);

        $text = XmlResponse::extractText(
            $doc->getElementsByTagName('pin-summary')->item(0),
            'destination-postal-id',
        );
        self::assertNull($text);
    }

    public function testMalformedDate(): void
    {
        $doc = XmlResponse::load(<<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <mailed-on-date>not a date</mailed-on-date>
                </pin-summary>
            </tracking-summary>
        EOF);
        self::assertInstanceOf(\DOMDocument::class, $doc);
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('malformed date');
        XmlResponse::extractDateTime(
            $doc->getElementsByTagName('pin-summary')->item(0),
            'mailed-on-date',
        );
    }

    public function testInvalidDate(): void
    {
        $doc = XmlResponse::load(<<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <mailed-on-date>2026-02-30</mailed-on-date>
                </pin-summary>
            </tracking-summary>
        EOF);
        self::assertInstanceOf(\DOMDocument::class, $doc);
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('invalid date');
        XmlResponse::extractDateTime(
            $doc->getElementsByTagName('pin-summary')->item(0),
            'mailed-on-date',
        );
    }

    public function testInvalidDateTime(): void
    {
        $doc = XmlResponse::load(<<<'EOF'
            <tracking-summary>
                <pin-summary>
                    <event-date-time>2026-02-01:999999</event-date-time>
                </pin-summary>
            </tracking-summary>
        EOF);
        self::assertInstanceOf(\DOMDocument::class, $doc);
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('invalid date');
        XmlResponse::extractDateTime(
            $doc->getElementsByTagName('pin-summary')->item(0),
            'event-date-time',
            '!Y-m-d:His',
        );
    }

    #[DataProvider('getValidISODateTimes')]
    public function testValidISODateTime(string $dateTime, \DateTimeImmutable $expectedDateTime): void
    {
        $element = XmlResponse::load("<test-wrapper><test>{$dateTime}</test></test-wrapper>")
            ->getElementsByTagName('test-wrapper')->item(0);
        self::assertInstanceOf(\DOMElement::class, $element);
        $result = XmlResponse::extractIsoDateTime($element, 'test');
        self::assertSame($expectedDateTime->format(\DateTimeInterface::ATOM), $result->format(\DateTimeInterface::ATOM));
    }

    /**
     * @return iterable<string, array{string, \DateTimeImmutable}>
     */
    public static function getValidISODateTimes(): iterable
    {
        yield 'Negative time offset' => ['2026-11-20T20:00:00-05:00', new \DateTimeImmutable('2026-11-20T20:00:00-05:00')];
        yield 'Positive time offset' => ['2026-11-20T20:00:00+05:00', new \DateTimeImmutable('2026-11-20T20:00:00+05:00')];
        yield 'UTC zero offset' => ['2026-11-20T20:00:00+00:00', new \DateTimeImmutable('2026-11-20T20:00:00+00:00')];
        yield 'UTC Zulu offset' => ['2026-11-20T20:00:00Z', new \DateTimeImmutable('2026-11-20T20:00:00+00:00')];
        yield 'UTC with milliseconds' => ['2026-11-20T20:00:00.500+00:00', new \DateTimeImmutable('2026-11-20T20:00:00+00:00')];
    }

    public function testISODateTimeElementMissing(): void
    {
        $element = XmlResponse::load("<test-wrapper></test-wrapper>")
            ->getElementsByTagName('test-wrapper')->item(0);
        self::assertInstanceOf(\DOMElement::class, $element);
        $result = XmlResponse::extractIsoDateTime($element, 'test');
        self::assertNull($result);
    }

    public function testISODateTimeElementEmpty(): void
    {
        $element = XmlResponse::load("<test-wrapper><test></test></test-wrapper>")
            ->getElementsByTagName('test-wrapper')->item(0);
        self::assertInstanceOf(\DOMElement::class, $element);
        $result = XmlResponse::extractIsoDateTime($element, 'test');
        self::assertNull($result);
    }

    public function testMalformedISODateTime(): void
    {
        $element = XmlResponse::load("<test-wrapper><test>not a date</test></test-wrapper>")
            ->getElementsByTagName('test-wrapper')->item(0);
        self::assertInstanceOf(\DOMElement::class, $element);
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('malformed date');
        XmlResponse::extractIsoDateTime($element, 'test');
    }

    public function testInvalidISODateTime(): void
    {
        $element = XmlResponse::load("<test-wrapper><test>2026-02-30T20:00:00+05:00</test></test-wrapper>")
            ->getElementsByTagName('test-wrapper')->item(0);
        self::assertInstanceOf(\DOMElement::class, $element);
        $this->expectException(CanadaPostException::class);
        $this->expectExceptionMessage('invalid date');
        XmlResponse::extractIsoDateTime($element, 'test');
    }

    private function xmlResponseToDOMDocument(string $xml, int $statusCode = 200, string $expectedRoot = 'test'): \DOMDocument
    {
        return XmlResponse::toDOMDocument(new Response($statusCode, [], $xml), $expectedRoot);
    }
}
