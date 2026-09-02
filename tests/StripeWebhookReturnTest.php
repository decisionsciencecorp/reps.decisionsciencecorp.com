<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StripeWebhookReturnTest extends TestCase
{
    protected function tearDown(): void
    {
        repsStripeClearHttpMock();
        putenv('STRIPE_SECRET_KEY');
        unset($_ENV['STRIPE_SECRET_KEY']);
        putenv('REPS_STRIPE_WEBHOOK_INSECURE');
        unset($_ENV['REPS_STRIPE_WEBHOOK_INSECURE']);
        parent::tearDown();
    }

    private function seedPayee(string $accountId, string $status = 'pending'): int
    {
        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT INTO payout_payees (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('user', ?, 'Webhook Payee', 'wh@example.com', ?, ?, 0)"
        )->execute([900000 + random_int(1, 99999), $accountId, $status]);
        return (int) $pdo->lastInsertId();
    }

    public function testAcceptWebhookRejectsEmptyAndBadSig(): void
    {
        repsStripeStoreSecretsInDb([
            'webhook_secret' => 'whsec_unit_a',
            'secret_key' => 'sk_test_unit',
            'mode' => 'test',
        ]);
        $this->assertSame('empty_body', repsStripeAcceptWebhookPayload('', '')['error'] ?? null);
        $bad = repsStripeAcceptWebhookPayload('{"id":"evt_1"}', 't=1,v1=dead');
        $this->assertFalse($bad['ok']);
        $this->assertSame('invalid_signature', $bad['error']);
    }

    public function testAcceptWebhookValidSignatureAndInsecureFlag(): void
    {
        $secret = 'whsec_unit_ok';
        repsStripeStoreSecretsInDb([
            'webhook_secret' => $secret,
            'secret_key' => 'sk_test_unit',
            'mode' => 'test',
        ]);
        $payload = json_encode([
            'id' => 'evt_signed_' . uniqid(),
            'type' => 'ping',
            'data' => ['object' => []],
        ], JSON_UNESCAPED_SLASHES);
        $header = repsStripeSignWebhookPayload((string) $payload, $secret);
        $ok = repsStripeAcceptWebhookPayload((string) $payload, $header);
        $this->assertTrue($ok['ok']);
        $this->assertSame('ping', $ok['event']['type'] ?? null);

        putenv('REPS_STRIPE_WEBHOOK_INSECURE=1');
        $_ENV['REPS_STRIPE_WEBHOOK_INSECURE'] = '1';
        $insecure = repsStripeAcceptWebhookPayload('{"id":"evt_inse","type":"ping","data":{"object":{}}}', '');
        $this->assertTrue($insecure['ok']);
        $this->assertSame('evt_inse', $insecure['event']['id'] ?? null);
    }

    public function testHandleAccountUpdatedMarksPayeeReady(): void
    {
        $acct = 'acct_wh_' . uniqid();
        $payeeId = $this->seedPayee($acct);
        $eventId = 'evt_acct_' . uniqid();
        $r = repsStripeHandleWebhookEvent([
            'id' => $eventId,
            'type' => 'account.updated',
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => $acct,
                    'payouts_enabled' => true,
                    'charges_enabled' => true,
                ],
            ],
        ]);
        $this->assertTrue($r['received']);
        $this->assertSame('account.updated', $r['type']);
        $payee = repsStripePayeeById($payeeId);
        $this->assertSame(1, (int) ($payee['payouts_enabled'] ?? 0));
        $this->assertSame('ready', (string) ($payee['onboarding_status'] ?? ''));

        $dup = repsStripeHandleWebhookEvent([
            'id' => $eventId,
            'type' => 'account.updated',
            'data' => ['object' => ['id' => $acct]],
        ]);
        $this->assertTrue($dup['duplicate'] ?? false);
    }

    public function testHandleTransferAndTopupEvents(): void
    {
        $tr = 'tr_wh_' . uniqid();
        $tu = 'tu_wh_' . uniqid();
        // transfer with no ledger row still acknowledged
        $r1 = repsStripeHandleWebhookEvent([
            'id' => 'evt_tr_' . uniqid(),
            'type' => 'transfer.created',
            'data' => ['object' => ['id' => $tr]],
        ]);
        $this->assertTrue($r1['received']);
        $r2 = repsStripeHandleWebhookEvent([
            'id' => 'evt_tu_' . uniqid(),
            'type' => 'topup.succeeded',
            'data' => ['object' => ['id' => $tu, 'amount' => 5000, 'currency' => 'usd']],
        ]);
        $this->assertTrue($r2['received']);
        $r3 = repsStripeHandleWebhookEvent([
            'id' => 'evt_unk_' . uniqid(),
            'type' => 'customer.created',
            'data' => ['object' => []],
        ]);
        $this->assertSame('customer.created', $r3['type']);
    }

    public function testRefreshPayeeFromAccountMock(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_refresh');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_refresh';
        $acct = 'acct_ref_' . uniqid();
        $payeeId = $this->seedPayee($acct);
        repsStripeSetHttpMock(static function (string $method, string $path) use ($acct) {
            if ($method === 'GET' && str_contains($path, '/v1/accounts/')) {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => [
                        'id' => $acct,
                        'payouts_enabled' => true,
                        'charges_enabled' => false,
                    ],
                    'raw' => '{}',
                ];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'nope'];
        });
        $r = repsStripeRefreshPayeeFromAccount($payeeId);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, (int) ($r['payee']['payouts_enabled'] ?? 0));
        $this->assertSame('ready', (string) ($r['payee']['onboarding_status'] ?? ''));

        $missing = repsStripeRefreshPayeeFromAccount(0);
        $this->assertFalse($missing['ok']);
        $this->assertSame('payee_not_found', $missing['error']);
    }

    public function testRefreshPayeeFetchFailure(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_refresh_fail');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_refresh_fail';
        $acct = 'acct_fail_' . uniqid();
        $payeeId = $this->seedPayee($acct);
        repsStripeSetHttpMock(static function () {
            return ['ok' => false, 'status' => 500, 'body' => [], 'raw' => '', 'error' => 'boom'];
        });
        $r = repsStripeRefreshPayeeFromAccount($payeeId);
        $this->assertFalse($r['ok']);
        $this->assertSame('boom', $r['error']);
    }

    public function testRefreshMissingAccountAndNotConfigured(): void
    {
        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT INTO payout_payees (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('user', ?, 'No Acct', 'na@example.com', '', 'pending', 0)"
        )->execute([800001]);
        $id = (int) $pdo->lastInsertId();
        $r = repsStripeRefreshPayeeFromAccount($id);
        $this->assertTrue($r['ok']);
        $this->assertSame('missing_account_id', $r['error']);

        putenv('STRIPE_SECRET_KEY');
        unset($_ENV['STRIPE_SECRET_KEY']);
        repsDashAppMetaSet(REPS_STRIPE_META_KEYS['secret'], '');
        $acctId = $this->seedPayee('acct_noconf_' . uniqid());
        $r2 = repsStripeRefreshPayeeFromAccount($acctId);
        $this->assertTrue($r2['ok']);
        $this->assertSame('stripe_not_configured', $r2['error']);
    }

    public function testAcceptWebhookViaConnectSecret(): void
    {
        repsStripeStoreSecretsInDb([
            'webhook_secret' => 'whsec_platform',
            'connect_webhook_secret' => 'whsec_connect_only',
            'secret_key' => 'sk_test_unit',
            'mode' => 'test',
        ]);
        $payload = json_encode([
            'id' => 'evt_conn_' . uniqid(),
            'type' => 'account.updated',
            'data' => ['object' => ['id' => 'acct_x', 'payouts_enabled' => false]],
        ], JSON_UNESCAPED_SLASHES);
        $header = repsStripeSignWebhookPayload((string) $payload, 'whsec_connect_only');
        $ok = repsStripeAcceptWebhookPayload((string) $payload, $header);
        $this->assertTrue($ok['ok'], json_encode($ok));
    }

    public function testHandleTransferReversedAndBalanceAndBadObject(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_bal');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_bal';
        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/balance') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => [
                        'available' => [['amount' => 100, 'currency' => 'usd']],
                        'pending' => [],
                    ],
                    'raw' => '{}',
                ];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'x'];
        });
        $r1 = repsStripeHandleWebhookEvent([
            'id' => 'evt_bal_' . uniqid(),
            'type' => 'balance.available',
            'data' => ['object' => []],
        ]);
        $this->assertTrue($r1['received']);
        $r2 = repsStripeHandleWebhookEvent([
            'id' => 'evt_rev_' . uniqid(),
            'type' => 'transfer.reversed',
            'data' => ['object' => ['id' => 'tr_rev_' . uniqid()]],
        ]);
        $this->assertTrue($r2['received']);
        $r3 = repsStripeHandleWebhookEvent([
            'id' => 'evt_badobj_' . uniqid(),
            'type' => 'account.updated',
            'data' => ['object' => 'not-an-array'],
        ]);
        $this->assertTrue($r3['received']);
    }
}
