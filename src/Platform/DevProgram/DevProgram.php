<?php

declare(strict_types=1);

namespace iswilson\CanadaPost\Platform\DevProgram;

use iswilson\CanadaPost\ClientBase;
use iswilson\CanadaPost\Exception\InvalidArgumentException;
use iswilson\CanadaPost\Rating;
use iswilson\CanadaPost\Tracking;

/**
 * Canada Post Developer Program (XML).
 */
final readonly class DevProgram
{
    /**
     * Developer Program API user ID.
     */
    private string $username;

    /**
     * Developer Program API password.
     */
    private string $password;

    /**
     * Customer number of the owner of the mail (mailed on behalf of customer).
     */
    private string $customerNumber;

    /**
     * Optional contract ID for commercial customers.
     */
    private ?string $contractId;

    /**
     * @internal Use CanadaPost::devProgram().
     *
     * @throws \iswilson\CanadaPost\Exception\InvalidArgumentException
     */
    public function __construct(
        string $username,
        #[\SensitiveParameter]
        string $password,
        string $customerNumber,
        ?string $contractId,
        private bool $sandbox,
    ) {
        // Validate username.
        $this->username = trim($username);
        if ($this->username === '') {
            throw new InvalidArgumentException('Username is a required field.');
        }

        // Validate password.
        $this->password = trim($password);
        if ($this->password === '') {
            throw new InvalidArgumentException('Password is a required field.');
        }

        // Validate customer number.
        $this->customerNumber = trim($customerNumber);
        if ($this->customerNumber === '') {
            throw new InvalidArgumentException('Customer number is a required field.');
        }
        if (preg_match('/^[0-9]{1,10}$/', $this->customerNumber) !== 1) {
            throw new InvalidArgumentException(sprintf('Customer number is invalid, got: %s.', $customerNumber));
        }

        // Clear contract ID if empty.
        $this->contractId = ($contractId === null || trim($contractId) === '') ? null : trim($contractId);
        // Validate contract ID.
        if ($this->contractId !== null && preg_match('/^[0-9]{1,10}$/', $this->contractId) !== 1) {
            throw new InvalidArgumentException(sprintf('Contract ID is invalid, got: %s.', $contractId));
        }
    }

    /**
     * Rating endpoint.
     */
    public function rating(): Rating
    {
        return new Rating($this->getConfig());
    }

    /**
     * Tracking endpoint.
     */
    public function tracking(): Tracking
    {
        return new Tracking($this->getConfig());
    }

    /**
     * Configuration array for ClientBase class.
     *
     * @return array{username: string, password: string, customer_number: string, contract_id: ?string, env: string}
     */
    private function getConfig(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'customer_number' => $this->customerNumber,
            'contract_id' => $this->contractId,
            'env' => $this->sandbox ? ClientBase::ENV_DEVELOPMENT : ClientBase::ENV_PRODUCTION,
        ];
    }
}
