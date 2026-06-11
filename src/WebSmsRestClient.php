<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms;

use Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;

/**
 * Client for the WebSMS.com.cy REST API — a lighter alternative to the SOAP
 * transport that authenticates with an API key instead of a username/password
 * session and does not require the soap extension. The REST API only covers
 * sending (one recipient per request), credits, and batch status.
 */
class WebSmsRestClient
{
    /**
     * @param  array{url: string, key: string|null}  $config
     */
    public function __construct(
        private readonly Http $http,
        private readonly array $config,
    ) {}

    /**
     * Send an SMS to a single recipient. The REST API accepts one phone per
     * request; use sendSmsToMany() for multiple recipients.
     */
    public function sendSms(
        string $from,
        string $to,
        string $message,
        string|DataCoding $encoding = DataCoding::Gsm,
    ): object {
        if (strlen($from) < 3 || strlen($from) > 11) {
            throw new InvalidArgumentException('The sender ID must be 3 to 11 characters.');
        }

        if ($to === '') {
            throw new InvalidArgumentException('The recipient phone number is required.');
        }

        return $this->post('/send-sm', [
            'to' => $this->normalizePhone($to),
            'from' => $from,
            'encoding' => $this->dataCoding($encoding)->value,
            'message' => $message,
        ]);
    }

    /**
     * Send the same SMS to several recipients, one REST request each.
     *
     * @param  list<string>  $to
     * @return list<object> One response per recipient, in the same order.
     */
    public function sendSmsToMany(string $from, array $to, string $message, string|DataCoding $encoding = DataCoding::Gsm): array
    {
        return array_map(
            fn (string $recipient): object => $this->sendSms($from, $recipient, $message, $encoding),
            $to,
        );
    }

    /**
     * Check whether the configured API key is valid.
     */
    public function checkKey(): bool
    {
        try {
            $response = $this->post('/check-key', []);
        } catch (AuthenticationException) {
            return false;
        }

        return (bool) ($response->is_valid ?? false);
    }

    /**
     * Get the remaining SMS credits for the account.
     */
    public function getCredits(): float
    {
        $response = $this->post('/get-credits', []);

        $credits = $response->credits ?? null;

        if (! is_numeric($credits)) {
            throw new WebSmsException('WebSMS "/get-credits" returned a non-numeric credits value.');
        }

        return (float) $credits;
    }

    /**
     * Get the delivery status of a previously sent batch.
     */
    public function getBatchStatus(int|string $batchId): object
    {
        return $this->parse($this->request(fn (): Response => $this->http->get(
            $this->url('/batch-status'),
            ['batch_id' => $batchId, 'key' => $this->key()],
        )), '/batch-status');
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function post(string $endpoint, array $parameters): object
    {
        return $this->parse($this->request(fn (): Response => $this->http->asForm()->post(
            $this->url($endpoint),
            $parameters + ['key' => $this->key()],
        )), $endpoint);
    }

    /**
     * @param  callable(): Response  $send
     */
    private function request(callable $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException $exception) {
            throw new WebSmsException(
                'Could not reach the WebSMS REST API: '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    private function parse(Response $response, string $endpoint): object
    {
        if ($response->failed()) {
            throw new WebSmsException(sprintf(
                'WebSMS "%s" request failed with HTTP status %d.',
                $endpoint,
                $response->status(),
            ));
        }

        $data = $response->object();

        if (! is_object($data)) {
            throw new WebSmsException(sprintf('WebSMS "%s" returned a non-JSON response.', $endpoint));
        }

        if (($data->status ?? null) === 'error') {
            $error = is_string($data->error ?? null) ? $data->error : 'Unknown error';

            if (str_contains(strtolower($error), 'not authorized')) {
                throw new AuthenticationException(
                    'WebSMS rejected the API key. Check WEBSMS_API_KEY.'
                );
            }

            throw new WebSmsException(sprintf('WebSMS "%s" call failed: %s', $endpoint, $error));
        }

        return $data;
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
     * The REST API accepts "357..." and "00357..." prefixes but rejects
     * "+357...", so a leading plus is stripped.
     */
    private function normalizePhone(string $phone): string
    {
        return ltrim($phone, '+');
    }

    private function url(string $endpoint): string
    {
        return rtrim($this->config['url'], '/').$endpoint;
    }

    private function key(): string
    {
        $key = $this->config['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new AuthenticationException(
                'No WebSMS API key configured. Set WEBSMS_API_KEY to use the REST client.'
            );
        }

        return $key;
    }
}
