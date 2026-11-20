<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating;
use iswilson\CanadaPost\Tracking\TrackingClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Canada Post Developer Program (XML).
 */
final readonly class DevProgram
{
    public const string BASE_URL_DEVELOPMENT = 'https://ct.soa-gw.canadapost.ca';
    public const string BASE_URL_PRODUCTION = 'https://soa-gw.canadapost.ca';

    /** Customer number of the owner of the mail (mailed on behalf of customer). */
    private string $customerNumber;

    /** Optional contract ID for commercial customers. */
    private ?string $contractId;

    private Transport $transport;

    /**
     * @internal Use CanadaPost::devProgram().
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public function __construct(
        /** Developer Program API user ID. */
        string $username,
        /** Developer Program API password. */
        #[\SensitiveParameter]
        string $password,
        string $customerNumber,
        ?string $contractId,
        private bool $sandbox,
        private ?LoggerInterface $logger = null,
        ?float $timeout = null,
        /** Internal: Optional Guzzle handler for tests. */
        ?callable $handler = null,
    ) {
        // Validate username.
        $username = trim($username);
        if ($username === '') {
            throw new InvalidArgumentException('Username is a required field.');
        }

        // Validate password.
        $password = trim($password);
        if ($password === '') {
            throw new InvalidArgumentException('Password is a required field.');
        }

        // Validate customer number.
        $this->customerNumber = trim($customerNumber);
        if ($this->customerNumber === '') {
            throw new InvalidArgumentException('Customer number is a required field.');
        }
        if (preg_match('/^[0-9]{1,10}$/', $this->customerNumber) !== 1) {
            throw new InvalidArgumentException('Customer number is invalid, expected 1 to 10 digits.');
        }

        // Clear contract ID if empty.
        $this->contractId = ($contractId === null || trim($contractId) === '') ? null : trim($contractId);
        // Validate contract ID.
        if ($this->contractId !== null && preg_match('/^[0-9]{1,10}$/', $this->contractId) !== 1) {
            throw new InvalidArgumentException('Contract ID is invalid, expected 1 to 10 digits.');
        }

        $this->transport = new Transport(
            $this->sandbox ? self::BASE_URL_DEVELOPMENT : self::BASE_URL_PRODUCTION,
            $username,
            $password,
            guzzleOptions: [
                'handler' => $handler,
            ],
            timeout: $timeout,
            logger: $logger,
        );
    }

    /**
     * Rating endpoint.
     */
    public function rating(): Rating
    {
        return new Rating($this->transport, $this->customerNumber, $this->contractId);
    }

    /**
     * Tracking endpoint.
     */
    public function tracking(): TrackingClientInterface
    {
        return new TrackingClient($this->transport);
    }
}
