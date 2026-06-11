<?php

declare(strict_types=1);

namespace Adelinferaru\LaravelWebSms\Tests;

use Adelinferaru\LaravelWebSms\DataCoding;
use Adelinferaru\LaravelWebSms\Exceptions\AuthenticationException;
use Adelinferaru\LaravelWebSms\Exceptions\WebSmsException;
use Adelinferaru\LaravelWebSms\WebSmsRestClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WebSmsRestClientTest extends TestCase
{
    private Factory $http;

    private WebSmsRestClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new Factory;

        $this->client = new WebSmsRestClient($this->http, [
            'url' => 'https://websms.com.cy/api',
            'key' => 'XXX-XXX-XXXXX',
        ]);
    }

    public function test_send_sms_posts_form_parameters_and_returns_the_response(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => Factory::response(
                '{"batchId":4733775,"status":"ok","error":"","credits":1,"to":["35799123456"]}'
            ),
        ]);

        $response = $this->client->sendSms('ACME', '35799123456', 'Hello');

        $this->assertSame(4733775, $response->batchId);
        $this->assertSame(1, $response->credits);

        $this->http->assertSent(function (Request $request): bool {
            return $request->url() === 'https://websms.com.cy/api/send-sm'
                && $request->method() === 'POST'
                && $request->isForm()
                && $request['to'] === '35799123456'
                && $request['from'] === 'ACME'
                && $request['encoding'] === 'GSM'
                && $request['message'] === 'Hello'
                && $request['key'] === 'XXX-XXX-XXXXX';
        });
    }

    public function test_send_sms_strips_the_unsupported_plus_prefix(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => Factory::response('{"batchId":1,"status":"ok"}'),
        ]);

        $this->client->sendSms('ACME', '+35799123456', 'Hello');

        $this->http->assertSent(fn (Request $request): bool => $request['to'] === '35799123456');
    }

    public function test_send_sms_passes_the_ucs2_encoding(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => Factory::response('{"batchId":1,"status":"ok"}'),
        ]);

        $this->client->sendSms('ACME', '35799123456', 'Γειά σου', DataCoding::Ucs2);

        $this->http->assertSent(fn (Request $request): bool => $request['encoding'] === 'UCS2');
    }

    public function test_send_sms_validates_the_sender_length(): void
    {
        $this->http->fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('3 to 11 characters');

        $this->client->sendSms('AB', '35799123456', 'Hello');
    }

    public function test_send_sms_rejects_an_unknown_encoding(): void
    {
        $this->http->fake();

        $this->expectException(InvalidArgumentException::class);

        $this->client->sendSms('ACME', '35799123456', 'Hello', 'UTF-16');
    }

    public function test_send_sms_to_many_sends_one_request_per_recipient(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => $this->http->sequence()
                ->push('{"batchId":1,"status":"ok"}')
                ->push('{"batchId":2,"status":"ok"}'),
        ]);

        $responses = $this->client->sendSmsToMany('ACME', ['35799123456', '35799654321'], 'Hello');

        $this->assertCount(2, $responses);
        $this->assertSame(1, $responses[0]->batchId);
        $this->assertSame(2, $responses[1]->batchId);
        $this->http->assertSentCount(2);
    }

    public function test_a_gateway_error_response_throws_a_websms_exception(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => Factory::response(
                '{"batchId":"","status":"error","error":"Low credits","credits":0,"to":[]}'
            ),
        ]);

        $this->expectException(WebSmsException::class);
        $this->expectExceptionMessage('Low credits');

        $this->client->sendSms('ACME', '35799123456', 'Hello');
    }

    public function test_a_not_authorized_response_throws_an_authentication_exception(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => Factory::response('{"status":"error","error":"Not authorized request"}'),
        ]);

        $this->expectException(AuthenticationException::class);

        $this->client->sendSms('ACME', '35799123456', 'Hello');
    }

    public function test_an_http_error_status_throws_a_websms_exception(): void
    {
        $this->http->fake([
            'websms.com.cy/api/send-sm' => Factory::response('Server error', 500),
        ]);

        $this->expectException(WebSmsException::class);
        $this->expectExceptionMessage('HTTP status 500');

        $this->client->sendSms('ACME', '35799123456', 'Hello');
    }

    public function test_a_missing_api_key_throws_an_authentication_exception(): void
    {
        $this->http->fake();

        $client = new WebSmsRestClient($this->http, [
            'url' => 'https://websms.com.cy/api',
            'key' => null,
        ]);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('WEBSMS_API_KEY');

        $client->getCredits();
    }

    public function test_check_key_returns_true_for_a_valid_key(): void
    {
        $this->http->fake([
            'websms.com.cy/api/check-key' => Factory::response('{"status":"ok","is_valid":true}'),
        ]);

        $this->assertTrue($this->client->checkKey());
    }

    public function test_check_key_returns_false_for_a_rejected_key(): void
    {
        $this->http->fake([
            'websms.com.cy/api/check-key' => Factory::response('{"status":"error","error":"Not authorized request"}'),
        ]);

        $this->assertFalse($this->client->checkKey());
    }

    public function test_get_credits_returns_the_balance_as_float(): void
    {
        $this->http->fake([
            'websms.com.cy/api/get-credits' => Factory::response('{"status":"ok","credits":109.5}'),
        ]);

        $this->assertSame(109.5, $this->client->getCredits());

        $this->http->assertSent(fn (Request $request): bool => $request['key'] === 'XXX-XXX-XXXXX');
    }

    public function test_get_batch_status_sends_a_get_request_with_query_parameters(): void
    {
        $this->http->fake([
            'websms.com.cy/api/batch-status*' => Factory::response(
                '{"created_on":"2017-01-16T08:06:40","status":"COMPLETE","total_messages":"1","message_status":[{"to":"35799123456","status":"DELIVERED"}]}'
            ),
        ]);

        $status = $this->client->getBatchStatus(4733775);

        $this->assertSame('COMPLETE', $status->status);
        $this->assertSame('DELIVERED', $status->message_status[0]->status);

        $this->http->assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://websms.com.cy/api/batch-status?')
                && str_contains($request->url(), 'batch_id=4733775')
                && str_contains($request->url(), 'key=XXX-XXX-XXXXX');
        });
    }
}
