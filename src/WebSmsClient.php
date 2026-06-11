<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms;

use Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use InvalidArgumentException;
use SoapClient;
use SoapFault;

class WebSmsClient
{
    /**
     * The gateway accepts at most this many recipients per sendSM call
     * (maxOccurs="100" in the WSDL schema).
     */
    public const MAX_RECIPIENTS = 100;

    /**
     * @param  array{wsdl: string, username: string|null, password: string|null, from?: string|null, session: array{store?: string|null, key: string, ttl: int}}  $config
     */
    public function __construct(
        private readonly Cache $cache,
        private readonly array $config,
        private ?SoapClient $soapClient = null,
    ) {}

    /**
     * Send an SMS to one or more recipients, optionally scheduled for later delivery.
     *
     * @param  string|list<string>  $to
     */
    public function sendSms(
        string $from,
        string|array $to,
        string $message,
        string|DataCoding $encoding = DataCoding::Gsm,
        ?DateTimeInterface $scheduledFor = null,
    ): object {
        $recipients = is_array($to) ? $to : [$to];

        if (count($recipients) < 1 || count($recipients) > self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException(sprintf(
                'sendSms() requires between 1 and %d recipients, %d given.',
                self::MAX_RECIPIENTS,
                count($recipients),
            ));
        }

        $parameters = [
            'session_id' => $this->sessionId(),
            'from' => $from,
            'message' => $message,
            'data_coding' => $this->dataCoding($encoding)->value,
            'to' => $recipients,
        ];

        if ($scheduledFor !== null) {
            $parameters['scheduled_for'] = $scheduledFor->format(DATE_ATOM);
        }

        $response = $this->callForObject('sendSM', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Cancel a batch that was scheduled with sendSms(..., scheduledFor: ...).
     */
    public function cancelScheduledBatch(string $batchId): object
    {
        $response = $this->callForObject('cancelScheduledBatch', [
            'session_id' => $this->sessionId(),
            'batch_id' => $batchId,
        ]);

        $this->extendSession();

        return $response;
    }

    /**
     * Get the remaining SMS credits for the account.
     */
    public function getCredits(): float
    {
        $response = $this->call('getCredits', [$this->sessionId()]);

        if (! is_numeric($response)) {
            throw new WebSmsException('WebSMS "getCredits" returned a non-numeric response.');
        }

        $this->extendSession();

        return (float) $response;
    }

    /**
     * Get the delivery status of a previously sent batch.
     */
    public function getBatchStatus(int|string $batchId): object
    {
        $response = $this->callForObject('getBatchStatus', [
            'sessionId' => $this->sessionId(),
            'batchId' => $batchId,
        ]);

        $this->extendSession();

        return $response;
    }

    /**
     * Poll the inbox for incoming (two-way) SMS messages. Pass the cursors
     * from a previous response to fetch only newer messages; the gateway
     * returns up to 100 messages per call with a hasMore flag.
     */
    public function getIncomingMessages(?DateTimeInterface $since = null, ?int $afterId = null): object
    {
        $parameters = ['sessionId' => $this->sessionId()];

        if ($since !== null) {
            $parameters['lastMessageDate'] = $since->format(DATE_ATOM);
        }

        if ($afterId !== null) {
            $parameters['lastMessageId'] = $afterId;
        }

        $response = $this->callForObject('getIncomingMessages', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Send (optionally scheduled) an SMS to a stored contact.
     */
    public function pushSms(
        string $contactPhone,
        string $message,
        ?string $to = null,
        ?DateTimeInterface $sendAt = null,
    ): object {
        $parameters = [
            'sessionId' => $this->sessionId(),
            'contactPhone' => $contactPhone,
            'message' => $message,
        ];

        if ($to !== null) {
            $parameters['to'] = $to;
        }

        if ($sendAt !== null) {
            $parameters['dateTime'] = $sendAt->format(DATE_ATOM);
        }

        $response = $this->callForObject('pushSMS', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Create a contact group.
     */
    public function createContactGroup(string $groupName): object
    {
        $response = $this->callForObject('createContactGroup', [
            'sessionId' => $this->sessionId(),
            'groupName' => $groupName,
        ]);

        $this->extendSession();

        return $response;
    }

    /**
     * List contact groups, optionally paginated.
     */
    public function listContactGroups(?int $start = null, ?int $number = null): object
    {
        $parameters = ['sessionId' => $this->sessionId()];

        if ($start !== null) {
            $parameters['start'] = $start;
        }

        if ($number !== null) {
            $parameters['number'] = $number;
        }

        $response = $this->callForObject('listContactGroups', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Add a contact, optionally into a specific group.
     */
    public function addContact(string $contactName, string $contactPhone, ?int $groupId = null): object
    {
        $parameters = [
            'sessionId' => $this->sessionId(),
            'contactName' => $contactName,
            'contactPhone' => $contactPhone,
        ];

        if ($groupId !== null) {
            $parameters['groupId'] = $groupId;
        }

        $response = $this->callForObject('addContact', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Check whether a phone number is a member of a contact group.
     */
    public function checkContactInGroup(string $contactPhone, ?int $groupId = null): object
    {
        $parameters = [
            'sessionId' => $this->sessionId(),
            'contactPhone' => $contactPhone,
        ];

        if ($groupId !== null) {
            $parameters['groupId'] = $groupId;
        }

        $response = $this->callForObject('checkContactInGroup', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Remove a contact from a group, identified by phone number or contact ID.
     */
    public function removeContactFromGroup(
        ?string $contactPhone = null,
        ?int $contactId = null,
        ?int $groupId = null,
    ): object {
        if ($contactPhone === null && $contactId === null) {
            throw new InvalidArgumentException(
                'removeContactFromGroup() requires a contact phone or a contact ID.'
            );
        }

        $parameters = ['sessionId' => $this->sessionId()];

        if ($contactPhone !== null) {
            $parameters['contactPhone'] = $contactPhone;
        }

        if ($contactId !== null) {
            $parameters['contactId'] = $contactId;
        }

        if ($groupId !== null) {
            $parameters['groupId'] = $groupId;
        }

        $response = $this->callForObject('removeContactFromGroup', $parameters);

        $this->extendSession();

        return $response;
    }

    /**
     * Check whether the cached gateway session is still valid. Returns false
     * without calling the gateway when no session is cached.
     */
    public function isSessionValid(): bool
    {
        $sessionId = $this->cache->get($this->sessionCacheKey());

        if (! is_string($sessionId) || $sessionId === '') {
            return false;
        }

        return (bool) $this->call('isSessionValid', [$sessionId]);
    }

    /**
     * Authenticate against the gateway and cache the session ID.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): string
    {
        $response = $this->callForObject('Authenticate', [
            'username' => $this->config['username'],
            'password' => $this->config['password'],
        ]);

        $success = $response->success ?? null;

        if (! is_numeric($success) || (int) $success !== 1) {
            throw new AuthenticationException(
                'WebSMS authentication failed. Check WEBSMS_USERNAME and WEBSMS_PASSWORD.'
            );
        }

        $sessionId = $response->session_id ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw new AuthenticationException(
                'WebSMS authentication succeeded but the gateway returned no session ID.'
            );
        }

        $this->cache->put($this->sessionCacheKey(), $sessionId, $this->sessionTtl());

        return $sessionId;
    }

    private function dataCoding(string|DataCoding $encoding): DataCoding
    {
        if ($encoding instanceof DataCoding) {
            return $encoding;
        }

        return DataCoding::tryFrom($encoding) ?? throw new InvalidArgumentException(sprintf(
            'Invalid encoding "%s"; the gateway supports GSM and UCS2.',
            $encoding,
        ));
    }

    /**
     * Return the cached session ID, authenticating if it has expired.
     */
    private function sessionId(): string
    {
        $sessionId = $this->cache->get($this->sessionCacheKey());

        if (is_string($sessionId) && $sessionId !== '') {
            return $sessionId;
        }

        return $this->authenticate();
    }

    /**
     * Reset the session TTL after a successful gateway call, mirroring
     * the gateway's sliding session expiration.
     */
    private function extendSession(): void
    {
        $sessionId = $this->cache->get($this->sessionCacheKey());

        if (is_string($sessionId) && $sessionId !== '') {
            $this->cache->put($this->sessionCacheKey(), $sessionId, $this->sessionTtl());
        }
    }

    /**
     * Call an operation whose request and response are complex types.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function callForObject(string $operation, array $parameters): object
    {
        $response = $this->call($operation, [$parameters]);

        return is_object($response) ? $response : (object) $response;
    }

    /**
     * @param  list<mixed>  $arguments  Positional SOAP arguments; bare scalars
     *                                  for simple-element operations.
     *
     * @throws WebSmsException
     */
    private function call(string $operation, array $arguments): mixed
    {
        try {
            return $this->soapClient()->__soapCall($operation, $arguments);
        } catch (SoapFault $fault) {
            throw new WebSmsException(
                sprintf('WebSMS "%s" call failed: %s', $operation, $fault->getMessage()),
                0,
                $fault
            );
        }
    }

    private function soapClient(): SoapClient
    {
        return $this->soapClient ??= new SoapClient($this->config['wsdl'], [
            'encoding' => 'UTF-8',
            'exceptions' => true,
        ]);
    }

    private function sessionCacheKey(): string
    {
        return $this->config['session']['key'];
    }

    private function sessionTtl(): int
    {
        return $this->config['session']['ttl'];
    }
}
