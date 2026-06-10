<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests;

use Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Adelinferaru\LaravelWebSms\WebSmsClient;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SoapClient;
use SoapFault;

class WebSmsClientTest extends TestCase
{
    private Repository $cache;

    private SoapClient&MockObject $soapClient;

    private WebSmsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Repository(new ArrayStore);
        $this->soapClient = $this->createMock(SoapClient::class);

        $this->client = new WebSmsClient($this->cache, [
            'wsdl' => 'https://example.com/websms.wsdl',
            'username' => 'john',
            'password' => 'secret',
            'session' => [
                'store' => null,
                'key' => 'websms.session_id',
                'ttl' => 1500,
            ],
        ], $this->soapClient);
    }

    public function test_authenticate_caches_and_returns_the_session_id(): void
    {
        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('Authenticate', [['username' => 'john', 'password' => 'secret']])
            ->willReturn((object) ['success' => 1, 'session_id' => 'sess-123']);

        $this->assertSame('sess-123', $this->client->authenticate());
        $this->assertSame('sess-123', $this->cache->get('websms.session_id'));
    }

    public function test_authenticate_throws_on_invalid_credentials(): void
    {
        $this->soapClient->method('__soapCall')
            ->willReturn((object) ['success' => 0]);

        $this->expectException(AuthenticationException::class);

        $this->client->authenticate();
    }

    public function test_send_sms_reuses_the_cached_session(): void
    {
        $this->cache->put('websms.session_id', 'sess-123', 1500);

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('sendSM', [[
                'session_id' => 'sess-123',
                'from' => 'ACME',
                'to' => ['+35799123456'],
                'message' => 'Hello',
                'data_coding' => 'GSM',
            ]])
            ->willReturn((object) ['success' => 1, 'batch_id' => 42]);

        $response = $this->client->sendSms('ACME', '+35799123456', 'Hello');

        $this->assertSame(42, $response->batch_id);
    }

    public function test_send_sms_authenticates_first_when_no_session_is_cached(): void
    {
        $calls = [];

        $this->soapClient->method('__soapCall')
            ->willReturnCallback(function (string $operation, array $arguments) use (&$calls): object {
                $calls[] = [$operation, $arguments];

                return match ($operation) {
                    'Authenticate' => (object) ['success' => 1, 'session_id' => 'sess-new'],
                    'sendSM' => (object) ['success' => 1, 'batch_id' => 7],
                    default => self::fail("Unexpected SOAP operation: {$operation}"),
                };
            });

        $this->client->sendSms('ACME', ['+35799123456', '+35799654321'], 'Hello');

        $this->assertSame('Authenticate', $calls[0][0]);
        $this->assertSame('sendSM', $calls[1][0]);
        $this->assertSame(['+35799123456', '+35799654321'], $calls[1][1][0]['to']);
        $this->assertSame('sess-new', $calls[1][1][0]['session_id']);
    }

    public function test_get_credits_sends_the_session_id(): void
    {
        $this->cache->put('websms.session_id', 'sess-123', 1500);

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('getCredits', [['session_id' => 'sess-123']])
            ->willReturn((object) ['credits' => 99]);

        $this->assertSame(99, $this->client->getCredits()->credits);
    }

    public function test_get_batch_status_sends_session_and_batch_ids(): void
    {
        $this->cache->put('websms.session_id', 'sess-123', 1500);

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('getBatchStatus', [['sessionId' => 'sess-123', 'batchId' => 42]])
            ->willReturn((object) ['status' => 'DELIVERED']);

        $this->assertSame('DELIVERED', $this->client->getBatchStatus(42)->status);
    }

    public function test_soap_faults_are_wrapped_in_a_websms_exception(): void
    {
        $this->cache->put('websms.session_id', 'sess-123', 1500);

        $this->soapClient->method('__soapCall')
            ->willThrowException(new SoapFault('Server', 'boom'));

        $this->expectException(WebSmsException::class);
        $this->expectExceptionMessage('WebSMS "sendSM" call failed: boom');

        $this->client->sendSms('ACME', '+35799123456', 'Hello');
    }

    public function test_a_successful_call_keeps_the_session_cached(): void
    {
        $this->cache->put('websms.session_id', 'sess-123', 1500);

        $this->soapClient->method('__soapCall')
            ->willReturn((object) ['credits' => 99]);

        $this->client->getCredits();

        $this->assertSame('sess-123', $this->cache->get('websms.session_id'));
    }

    public function test_array_responses_are_normalised_to_objects(): void
    {
        $this->cache->put('websms.session_id', 'sess-123', 1500);

        $this->soapClient->method('__soapCall')
            ->willReturn(['credits' => 12]);

        $this->assertSame(12, $this->client->getCredits()->credits);
    }
}
