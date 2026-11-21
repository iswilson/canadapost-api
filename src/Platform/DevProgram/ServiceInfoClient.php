<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\Exception\CanadaPostException;
use iswilson\CanadaPost\ServiceInfo\InfoMessage;
use iswilson\CanadaPost\ServiceInfo\ServiceInfoClientInterface;

final readonly class ServiceInfoClient implements ServiceInfoClientInterface
{
    public function __construct(
        private Transport $transport,
    ) {}

    public function getServiceInfo(): array
    {
        $response = $this->transport->send(
            'GET',
            // The message type is always SO (scheduled outage).
            'rs/serviceinfo/shipment?messageType=SO',
            [
                'Accept-Language' => 'en-CA',
                'Accept' => 'application/vnd.cpc.serviceinfo-v2+xml',
            ],
        );

        $doc = XmlResponse::toDOMDocument($response, 'info-messages');

        return self::parseInfoMessages($doc);
    }

    /**
     * Parse an XML response into an array of InfoMessage.
     *
     * @return list<InfoMessage>
     *
     * @throws \iswilson\CanadaPost\Exception\CanadaPostException
     */
    private static function parseInfoMessages(\DOMDocument $doc): array
    {
        $infoMessages = [];

        $nodes = $doc->getElementsByTagName('info-message');
        foreach ($nodes as $node) {
            $messageText = XmlResponse::extractText($node, 'message-text');
            if ($messageText === null) {
                throw new CanadaPostException('Canada Post API returned an empty info message.');
            }

            $fromDateTime = XmlResponse::extractIsoDateTime($node, 'from-datetime');
            if ($fromDateTime === null) {
                throw new CanadaPostException('Canada Post API returned an empty start date and time.');
            }

            $toDateTime = XmlResponse::extractIsoDateTime($node, 'to-datetime');
            if ($toDateTime === null) {
                throw new CanadaPostException('Canada Post API returned an empty end date and time.');
            }

            $infoMessages[] = new InfoMessage(
                messageText: $messageText,
                fromDateTime: $fromDateTime,
                toDateTime: $toDateTime,
            );
        }

        return $infoMessages;
    }
}
