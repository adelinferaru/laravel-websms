<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests;

use Adelinferaru\LaravelWebSms\DataCoding;
use Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Adelinferaru\LaravelWebSms\WebSmsClient;
use DateTimeImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use InvalidArgumentException;
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

    private function cacheSession(string $sessionId = 'sess-123'): void
    {
        $this->cache->put('websms.session_id', $sessionId, 1500);
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
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('sendSM', [[
                'session_id' => 'sess-123',
                'from' => 'ACME',
                'message' => 'Hello',
                'data_coding' => 'GSM',
                'to' => ['+35799123456'],
            ]])
            ->willReturn((object) ['batchId' => '42', 'status' => 'OK']);

        $response = $this->client->sendSms('ACME', '+35799123456', 'Hello');

        $this->assertSame('42', $response->batchId);
    }

    public function test_send_sms_authenticates_first_when_no_session_is_cached(): void
    {
        $calls = [];

        $this->soapClient->method('__soapCall')
            ->willReturnCallback(function (string $operation, array $arguments) use (&$calls): object {
                $calls[] = [$operation, $arguments];

                return match ($operation) {
                    'Authenticate' => (object) ['success' => 1, 'session_id' => 'sess-new'],
                    'sendSM' => (object) ['batchId' => '7', 'status' => 'OK'],
                    default => self::fail("Unexpected SOAP operation: {$operation}"),
                };
            });

        $this->client->sendSms('ACME', ['+35799123456', '+35799654321'], 'Hello');

        $this->assertSame('Authenticate', $calls[0][0]);
        $this->assertSame('sendSM', $calls[1][0]);
        $this->assertSame(['+35799123456', '+35799654321'], $calls[1][1][0]['to']);
        $this->assertSame('sess-new', $calls[1][1][0]['session_id']);
    }

    public function test_send_sms_passes_a_scheduled_delivery_time(): void
    {
        $this->cacheSession();
        $scheduledFor = new DateTimeImmutable('2026-07-01T10:30:00+03:00');

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('sendSM', [[
                'session_id' => 'sess-123',
                'from' => 'ACME',
                'message' => 'Hello',
                'data_coding' => 'GSM',
                'to' => ['+35799123456'],
                'scheduled_for' => '2026-07-01T10:30:00+03:00',
            ]])
            ->willReturn((object) ['batchId' => '42', 'status' => 'SCHEDULED']);

        $this->client->sendSms('ACME', '+35799123456', 'Hello', scheduledFor: $scheduledFor);
    }

    public function test_send_sms_accepts_the_data_coding_enum_and_ucs2(): void
    {
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->willReturnCallback(function (string $operation, array $arguments): object {
                $this->assertSame('UCS2', $arguments[0]['data_coding']);

                return (object) ['batchId' => '42'];
            });

        $this->client->sendSms('ACME', '+35799123456', 'Γειά σου', DataCoding::Ucs2);
    }

    public function test_send_sms_rejects_an_unknown_encoding(): void
    {
        $this->cacheSession();
        $this->soapClient->expects($this->never())->method('__soapCall');

        $this->expectException(InvalidArgumentException::class);

        $this->client->sendSms('ACME', '+35799123456', 'Hello', 'UTF-16');
    }

    public function test_send_sms_rejects_more_than_one_hundred_recipients(): void
    {
        $this->cacheSession();
        $this->soapClient->expects($this->never())->method('__soapCall');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('between 1 and 100 recipients, 101 given');

        $this->client->sendSms('ACME', array_fill(0, 101, '+35799123456'), 'Hello');
    }

    public function test_send_sms_rejects_an_empty_recipient_list(): void
    {
        $this->cacheSession();
        $this->soapClient->expects($this->never())->method('__soapCall');

        $this->expectException(InvalidArgumentException::class);

        $this->client->sendSms('ACME', [], 'Hello');
    }

    public function test_cancel_scheduled_batch_sends_the_batch_id(): void
    {
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('cancelScheduledBatch', [['session_id' => 'sess-123', 'batch_id' => '42']])
            ->willReturn((object) ['status' => 'CANCELLED', 'batch_id' => '42']);

        $this->assertSame('CANCELLED', $this->client->cancelScheduledBatch('42')->status);
    }

    public function test_get_credits_sends_the_bare_session_id_and_returns_a_float(): void
    {
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('getCredits', ['sess-123'])
            ->willReturn(99.5);

        $this->assertSame(99.5, $this->client->getCredits());
    }

    public function test_get_credits_rejects_a_non_numeric_response(): void
    {
        $this->cacheSession();

        $this->soapClient->method('__soapCall')->willReturn('not-a-number');

        $this->expectException(WebSmsException::class);

        $this->client->getCredits();
    }

    public function test_get_batch_status_sends_session_and_batch_ids(): void
    {
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('getBatchStatus', [['sessionId' => 'sess-123', 'batchId' => 42]])
            ->willReturn((object) ['status' => 'DELIVERED']);

        $this->assertSame('DELIVERED', $this->client->getBatchStatus(42)->status);
    }

    public function test_get_incoming_messages_without_cursors_sends_only_the_session(): void
    {
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('getIncomingMessages', [['sessionId' => 'sess-123']])
            ->willReturn((object) ['status' => 'OK', 'hasMore' => false]);

        $this->assertFalse($this->client->getIncomingMessages()->hasMore);
    }

    public function test_get_incoming_messages_passes_the_pagination_cursors(): void
    {
        $this->cacheSession();
        $since = new DateTimeImmutable('2026-06-01T00:00:00+00:00');

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('getIncomingMessages', [[
                'sessionId' => 'sess-123',
                'lastMessageDate' => '2026-06-01T00:00:00+00:00',
                'lastMessageId' => 17,
            ]])
            ->willReturn((object) ['status' => 'OK', 'hasMore' => true]);

        $this->client->getIncomingMessages($since, 17);
    }

    public function test_push_sms_sends_optional_fields_only_when_set(): void
    {
        $this->cacheSession();
        $sendAt = new DateTimeImmutable('2026-07-01T08:00:00+00:00');

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('pushSMS', [[
                'sessionId' => 'sess-123',
                'contactPhone' => '+35799123456',
                'message' => 'Hello',
                'to' => '+35799654321',
                'dateTime' => '2026-07-01T08:00:00+00:00',
            ]])
            ->willReturn((object) ['returnCode' => 0, 'messageId' => 5]);

        $this->client->pushSms('+35799123456', 'Hello', '+35799654321', $sendAt);
    }

    public function test_contact_group_operations_map_their_parameters(): void
    {
        $this->cacheSession();
        $calls = [];

        $this->soapClient->method('__soapCall')
            ->willReturnCallback(function (string $operation, array $arguments) use (&$calls): object {
                $calls[$operation] = $arguments[0];

                return (object) ['returnCode' => 0, 'errorMessage' => ''];
            });

        $this->client->createContactGroup('Customers');
        $this->client->listContactGroups(0, 25);
        $this->client->addContact('John', '+35799123456', 3);
        $this->client->checkContactInGroup('+35799123456', 3);
        $this->client->removeContactFromGroup('+35799123456', null, 3);

        $this->assertSame(['sessionId' => 'sess-123', 'groupName' => 'Customers'], $calls['createContactGroup']);
        $this->assertSame(['sessionId' => 'sess-123', 'start' => 0, 'number' => 25], $calls['listContactGroups']);
        $this->assertSame(
            ['sessionId' => 'sess-123', 'contactName' => 'John', 'contactPhone' => '+35799123456', 'groupId' => 3],
            $calls['addContact']
        );
        $this->assertSame(
            ['sessionId' => 'sess-123', 'contactPhone' => '+35799123456', 'groupId' => 3],
            $calls['checkContactInGroup']
        );
        $this->assertSame(
            ['sessionId' => 'sess-123', 'contactPhone' => '+35799123456', 'groupId' => 3],
            $calls['removeContactFromGroup']
        );
    }

    public function test_remove_contact_from_group_requires_a_phone_or_contact_id(): void
    {
        $this->cacheSession();
        $this->soapClient->expects($this->never())->method('__soapCall');

        $this->expectException(InvalidArgumentException::class);

        $this->client->removeContactFromGroup(null, null, 3);
    }

    public function test_is_session_valid_returns_false_without_calling_the_gateway_when_no_session_is_cached(): void
    {
        $this->soapClient->expects($this->never())->method('__soapCall');

        $this->assertFalse($this->client->isSessionValid());
    }

    public function test_is_session_valid_asks_the_gateway_about_the_cached_session(): void
    {
        $this->cacheSession();

        $this->soapClient->expects($this->once())
            ->method('__soapCall')
            ->with('isSessionValid', ['sess-123'])
            ->willReturn(true);

        $this->assertTrue($this->client->isSessionValid());
    }

    public function test_soap_faults_are_wrapped_in_a_websms_exception(): void
    {
        $this->cacheSession();

        $this->soapClient->method('__soapCall')
            ->willThrowException(new SoapFault('Server', 'boom'));

        $this->expectException(WebSmsException::class);
        $this->expectExceptionMessage('WebSMS "sendSM" call failed: boom');

        $this->client->sendSms('ACME', '+35799123456', 'Hello');
    }

    public function test_a_successful_call_keeps_the_session_cached(): void
    {
        $this->cacheSession();

        $this->soapClient->method('__soapCall')
            ->willReturn(99.5);

        $this->client->getCredits();

        $this->assertSame('sess-123', $this->cache->get('websms.session_id'));
    }

    public function test_array_responses_are_normalised_to_objects(): void
    {
        $this->cacheSession();

        $this->soapClient->method('__soapCall')
            ->willReturn(['status' => 'DELIVERED']);

        $this->assertSame('DELIVERED', $this->client->getBatchStatus(42)->status);
    }
}
