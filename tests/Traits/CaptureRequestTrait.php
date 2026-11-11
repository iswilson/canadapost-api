<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Tests\Traits;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use iswilson\CanadaPost\Platform\DevProgram\DevProgram;
use iswilson\CanadaPost\Rating\Destination;
use iswilson\CanadaPost\Rating\Parcel;
use Psr\Http\Message\RequestInterface;

/**
 * Capture a request from getRates() without contacting Canada Post APIs.
 */
trait CaptureRequestTrait
{
    private function getSentRequest(DevProgram $platform): RequestInterface
    {
        $mock = new MockHandler([
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/rating-response-success.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);

        // Add history middleware that will record each request.
        $historyContainer = [];
        $history = Middleware::history($historyContainer);
        $handlerStack->push($history);

        $platform->rating()->getRates('H0H0H0', new Destination('CA', 'H0H0H0'), new Parcel(500), ['handler' => $handlerStack]);

        return $historyContainer[0]['request'];
    }
}
