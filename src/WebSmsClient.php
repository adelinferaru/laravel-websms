<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms;

use Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Illuminate\Contracts\Cache\Repository as Cache;
use SoapClient;
use SoapFault;

class WebSmsClient
{
    /**
     * @param  array{wsdl: string, username: string|null, password: string|null, session: array{store?: string|null, key: string, ttl: int}}  $config
     */
    public function __construct(
        private readonly Cache $cache,
        private readonly array $config,
        private ?SoapClient $soapClient = null,
    ) {}

    /**
     * Send an SMS to one or more recipients.
     *
     * @param  string|list<string>  $to
     */
    public function sendSms(string $from, string|array $to, string $message, string $encoding = 'GSM'): object
    {
        $response = $this->call('sendSM', [
            'session_id' => $this->sessionId(),
            'from' => $from,
            'to' => is_array($to) ? $to : [$to],
            'message' => $message,
            'data_coding' => $encoding,
        ]);

        $this->extendSession();

        return $response;
    }

    /**
     * Get the remaining SMS credits for the account.
     */
    public function getCredits(): object
    {
        $response = $this->call('getCredits', [
            'session_id' => $this->sessionId(),
        ]);

        $this->extendSession();

        return $response;
    }

    /**
     * Get the delivery status of a previously sent batch.
     */
    public function getBatchStatus(int|string $batchId): object
    {
        $response = $this->call('getBatchStatus', [
            'sessionId' => $this->sessionId(),
            'batchId' => $batchId,
        ]);

        $this->extendSession();

        return $response;
    }

    /**
     * Authenticate against the gateway and cache the session ID.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): string
    {
        $response = $this->call('Authenticate', [
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
     * @param  array<string, mixed>  $parameters
     *
     * @throws WebSmsException
     */
    private function call(string $operation, array $parameters): object
    {
        try {
            $response = $this->soapClient()->__soapCall($operation, [$parameters]);
        } catch (SoapFault $fault) {
            throw new WebSmsException(
                sprintf('WebSMS "%s" call failed: %s', $operation, $fault->getMessage()),
                0,
                $fault
            );
        }

        return is_object($response) ? $response : (object) $response;
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
