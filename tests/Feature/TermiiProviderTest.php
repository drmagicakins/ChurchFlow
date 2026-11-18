<?php

namespace Tests\Feature;

use App\Domains\Communication\Providers\NullSmsProvider;
use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Providers\TermiiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Termii integration (Session handoff Â§1x).
 *
 * Everything here is proven against Http::fake(): the suite must never depend on
 * a network, and a real send costs real credit. What the tests pin:
 *
 *   - the request is the shape Termii documents (path, JSON body, channel);
 *   - the number is normalised the way the provider expects (international, no +);
 *   - a success maps to success WITH the provider's message id;
 *   - a rejection, a 200-with-a-bad-code and a dropped connection all map to a
 *     FAILURE with a reason â€” never to a silent "sent";
 *   - the app's own number rules win: a scientific-notation number is refused
 *     before Termii ever sees it, so no unit is spent on a send that cannot work;
 *   - a misconfiguration throws (so nothing is charged) rather than "succeeding";
 *   - the default binding is untouched: no key, no Termii.
 */
class TermiiProviderTest extends TestCase
{
    private function provider(array $overrides = []): TermiiProvider
    {
        // array_key_exists, NOT `??`: a null override is the whole point of two
        // of these tests, and `$overrides['x'] ?? $default` silently replaces an
        // explicit null with the default.
        return new TermiiProvider(
            apiKey: array_key_exists('apiKey', $overrides) ? $overrides['apiKey'] : 'test-key',
            senderId: array_key_exists('senderId', $overrides) ? $overrides['senderId'] : 'ChurchFlow',
            baseUrl: $overrides['baseUrl'] ?? 'https://v4.api.termii.com',
            channel: $overrides['channel'] ?? 'dnd',
        );
    }

    /** Termii's documented success body. */
    private function okBody(): array
    {
        return ['code' => 'ok', 'message_id' => 'msg-123', 'message' => 'Successfully Sent, Thank you'];
    }

    // --- the request ---------------------------------------------------------

    public function test_it_posts_the_documented_request_to_the_single_message_endpoint(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        $this->provider()->send('08031234567', 'Service starts at 9am.');

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://v4.api.termii.com/api/sms/send'
                && $body['api_key'] === 'test-key'
                && $body['from'] === 'ChurchFlow'
                && $body['sms'] === 'Service starts at 9am.'
                && $body['channel'] === 'dnd'
                && $body['type'] === 'plain';
        });
    }

    public function test_the_number_is_sent_internationally_without_a_plus(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        // The three shapes a Nigerian church actually stores.
        $this->provider()->send('0803 123 4567', 'a');
        $this->provider()->send('+2348031234567', 'b');
        $this->provider()->send('2348031234567', 'c');

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data()['to'])->all();

        $this->assertSame(['2348031234567', '2348031234567', '2348031234567'], $sent);
    }

    public function test_the_base_url_is_configurable(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        $this->provider(['baseUrl' => 'https://api.termii.com/'])->send('08031234567', 'hi');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.termii.com/api/sms/send');
    }

    // --- the sender name -----------------------------------------------------

    public function test_the_callers_church_name_is_used_as_the_sender(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        $this->provider()->send('08031234567', 'hi', 'Grace Chapel');

        Http::assertSent(fn (Request $r) => $r->data()['from'] === 'Grace Chapel');
    }

    public function test_without_a_church_name_the_configured_sender_is_used(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        $this->provider()->send('08031234567', 'hi', null);

        Http::assertSent(fn (Request $r) => $r->data()['from'] === 'ChurchFlow');
    }

    public function test_a_blank_church_name_falls_back_rather_than_sending_whitespace(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        $this->provider()->send('08031234567', 'hi', "   \n  ");

        Http::assertSent(fn (Request $r) => $r->data()['from'] === 'ChurchFlow');
    }

    // --- success -------------------------------------------------------------

    public function test_a_successful_send_carries_termiis_message_id(): void
    {
        Http::fake(['*' => Http::response($this->okBody())]);

        $result = $this->provider()->send('08031234567', 'hi');

        $this->assertTrue($result->success);
        $this->assertSame('msg-123', $result->providerMessageId);
        $this->assertNull($result->failureReason);
    }

    // --- every failure is a failure, and says why ----------------------------

    public function test_an_http_error_is_reported_as_unsuccessful_and_quotes_termii(): void
    {
        Http::fake(['*' => Http::response([
            'code' => 'invalid_sender_id',
            'message' => 'Sender Id is not approved',
        ], 400)]);

        $result = $this->provider()->send('08031234567', 'hi');

        $this->assertFalse($result->success);
        $this->assertNull($result->providerMessageId);
        $this->assertStringContainsString('not approved', $result->failureReason);
    }

    public function test_a_200_with_a_non_ok_code_is_still_a_failure(): void
    {
        // Termii does answer 200 while refusing the message. Trusting the status
        // code alone would mark this "sent" and never refund the church.
        Http::fake(['*' => Http::response(['code' => 'error', 'message' => 'Insufficient balance'], 200)]);

        $result = $this->provider()->send('08031234567', 'hi');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Insufficient balance', $result->failureReason);
    }

    public function test_a_dropped_connection_is_a_failure_not_an_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $result = $this->provider()->send('08031234567', 'hi');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not reach', $result->failureReason);
    }

    // --- the app's number rules win -----------------------------------------

    public function test_a_spreadsheet_damaged_number_is_refused_before_termii_is_called(): void
    {
        Http::fake();

        $result = $this->provider()->send('2.3481E+12', 'hi');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('scientific notation', $result->failureReason);
        Http::assertNothingSent();
    }

    public function test_an_empty_message_is_refused_without_calling_termii(): void
    {
        Http::fake();

        $result = $this->provider()->send('08031234567', '   ');

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }

    // --- misconfiguration throws before a unit is spent ----------------------

    public function test_a_missing_sender_id_throws_and_names_the_variable(): void
    {
        Http::fake();

        // Assert BEFORE expectException: expectException stops the test the
        // moment the throw happens, so a finally block would never run.
        try {
            $this->provider(['senderId' => null])->send('08031234567', 'hi');
            $this->fail('A send with no sender id must not proceed.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('TERMII_SENDER_ID', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_a_missing_api_key_throws_and_names_the_variable(): void
    {
        Http::fake();

        try {
            $this->provider(['apiKey' => null])->send('08031234567', 'hi');
            $this->fail('A send with no API key must not proceed.');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('TERMII_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    // --- the default is untouched -------------------------------------------

    public function test_without_an_explicit_gateway_the_app_still_uses_the_null_provider(): void
    {
        config(['services.sms.gateway' => 'null']);

        $this->assertInstanceOf(NullSmsProvider::class, app(SmsProviderInterface::class));
    }

    public function test_naming_termii_selects_the_real_provider(): void
    {
        config([
            'services.sms.gateway' => 'termii',
            'services.sms.termii.api_key' => 'k',
            'services.sms.termii.sender_id' => 'ChurchFlow',
            'services.sms.termii.base_url' => 'https://v4.api.termii.com',
        ]);

        // A fresh container without the instance that may already be resolved.
        $this->refreshApplication();

        config([
            'services.sms.gateway' => 'termii',
            'services.sms.termii.api_key' => 'k',
            'services.sms.termii.sender_id' => 'ChurchFlow',
        ]);

        $this->assertInstanceOf(TermiiProvider::class, app(SmsProviderInterface::class));
    }
}

/**
 * Whose NAME the text is sent under (2026-11-17): the church's, or its
 * parish's when the message belongs to one. Kept in this file because it is the
 * rule TermiiProvider::send() acts on.
 */
