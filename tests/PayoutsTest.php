<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PayoutsTest extends TestCase
{
    public function testSplitOneHourWithAffiliateShop(): void
    {
        $s = repsDashSplitAcceptedHours(1.0, true, true);
        $this->assertSame(2000, $s['gross_cents']);
        $this->assertSame(500, $s['dsc_cents']);
        $this->assertSame(500, $s['affiliate_cents']);
        $this->assertSame(1000, $s['capture_cents']);
        $this->assertSame('shop', $s['capture_payee']);
        $this->assertFalse($s['affiliate_to_dsc']);
        $this->assertSame(2000, $s['dsc_cents'] + $s['affiliate_cents'] + $s['capture_cents']);
    }

    public function testSplitNoAffiliateRollsToDsc(): void
    {
        $s = repsDashSplitAcceptedHours(1.0, false, false);
        $this->assertSame(1000, $s['dsc_cents']);
        $this->assertSame(0, $s['affiliate_cents']);
        $this->assertSame(1000, $s['capture_cents']);
        $this->assertSame('operator', $s['capture_payee']);
        $this->assertTrue($s['affiliate_to_dsc']);
    }

    public function testSplitFractionalHoursSums(): void
    {
        $s = repsDashSplitAcceptedHours(1.5, true, true);
        $this->assertSame(3000, $s['gross_cents']);
        $this->assertSame(
            $s['gross_cents'],
            $s['dsc_cents'] + $s['affiliate_cents'] + $s['capture_cents']
        );
    }

    public function testShopEconomicsUsesLockedPie(): void
    {
        $shop = [
            'accepted_hours_7d' => 2.0,
            'assigned_sales_rep' => 'jim',
            'agreed_shop_split' => 0.5,
            'name' => 'Fleet',
        ];
        $e = repsDashMoneyShopEconomics($shop, 999.0);
        $this->assertSame(40.0, $e['gross']);
        $this->assertSame(10.0, $e['dsc_pay']);
        $this->assertSame(10.0, $e['your_affiliate']);
        $this->assertSame(20.0, $e['shop_pay']);
        $this->assertFalse($e['internal']);
    }

    public function testLedgerPostIdempotent(): void
    {
        $key = 'test_hour_' . uniqid('', true);
        $a = repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => 1.0,
            'shop_id' => 104,
            'has_shop' => true,
            'has_affiliate' => true,
            'affiliate_username' => 'jim',
        ]);
        $this->assertTrue($a['ok']);
        $this->assertTrue($a['created']);
        $this->assertCount(3, $a['line_ids']);

        $b = repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => 1.0,
            'shop_id' => 104,
            'has_shop' => true,
            'has_affiliate' => true,
        ]);
        $this->assertTrue($b['ok']);
        $this->assertFalse($b['created']);
        $this->assertSame($a['line_ids'], $b['line_ids']);
    }

    public function testSettlementIdempotent(): void
    {
        $key = 'shift_test_' . uniqid('', true);
        $a = repsSettlementImportShift($key, 50000, ['week' => 'W99']);
        $this->assertTrue($a['ok']);
        $this->assertTrue($a['created']);
        $b = repsSettlementImportShift($key, 50000);
        $this->assertTrue($b['ok']);
        $this->assertFalse($b['created']);
        $this->assertSame($a['id'], $b['id']);
    }

    public function testDisburseDryWithoutPayeesSkips(): void
    {
        $key = 'disburse_hour_' . uniqid('', true);
        repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => 1.0,
            'shop_id' => 999001,
            'has_shop' => true,
            'has_affiliate' => false,
        ]);
        $res = repsDisburseRunBatch('phpunit', true);
        $this->assertTrue($res['ok']);
        $this->assertTrue($res['dry_run']);
    }

    public function testWebhookVerifyRejectsBadSig(): void
    {
        $this->assertNull(repsStripeVerifyWebhook('{}', 't=1,v1=deadbeef', 'whsec_test'));
    }

    public function testWebhookVerifyAcceptsValid(): void
    {
        $payload = '{"id":"evt_test","type":"ping"}';
        $secret = 'whsec_test_secret';
        $t = time();
        $sig = hash_hmac('sha256', $t . '.' . $payload, $secret);
        $header = 't=' . $t . ',v1=' . $sig;
        $event = repsStripeVerifyWebhook($payload, $header, $secret);
        $this->assertNotNull($event);
        $this->assertSame('evt_test', $event['id']);
    }

    public function testMigration005TablesExist(): void
    {
        $pdo = repsDashDb();
        foreach (['settlement_events', 'payout_payees', 'ledger_lines', 'disbursement_batches', 'disbursement_transfers', 'stripe_webhook_events'] as $t) {
            $n = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($t))->fetchColumn();
            $this->assertSame(1, $n, $t);
        }
        $v = $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version='005_payouts'")->fetchColumn();
        $this->assertSame(1, (int) $v);
    }

    public function testIndividualEconomics(): void
    {
        $e = repsDashMoneyIndividualEconomics([
            'accepted_7d' => 2.0,
            'assigned_sales_rep' => 'jim',
        ], 20.0);
        $this->assertSame(40.0, $e['gross']);
        $this->assertSame(10.0, $e['your_affiliate']);
        $this->assertSame(20.0, $e['capture_pay']);
        $this->assertSame(10.0, $e['dsc_pay']);
    }

    public function testInternalShopNoAffiliate(): void
    {
        $e = repsDashMoneyShopEconomics([
            'accepted_hours_7d' => 1.0,
            'assigned_sales_rep' => '',
            'agreed_shop_split' => 0.0,
            'name' => 'Empanada Lab',
        ], 20.0);
        $this->assertTrue($e['internal']);
        $this->assertSame(10.0, $e['dsc_pay']);
        $this->assertSame(0.0, $e['your_affiliate']);
        $this->assertSame(10.0, $e['shop_pay']);
    }

    public function testSettlementReconcileWithoutKeys(): void
    {
        putenv('STRIPE_SECRET_KEY=');
        $_ENV['STRIPE_SECRET_KEY'] = '';
        $r = repsSettlementReconcileStripeBalance('phpunit');
        $this->assertTrue($r['ok']);
        $this->assertArrayHasKey('error', $r);
    }

    public function testSettlementCoverageAndList(): void
    {
        $cov = repsSettlementCoverage();
        $this->assertArrayHasKey('ledger_owed_cents', $cov);
        $list = repsSettlementList(5);
        $this->assertIsArray($list);
        $this->assertTrue(repsSettlementMarkReconciled((int) ($list[0]['id'] ?? 1), 'phpunit'));
    }

    public function testLedgerSeedAndTotals(): void
    {
        $seed = repsLedgerSeedFromMockShops();
        $this->assertTrue($seed['ok']);
        $t = repsLedgerTotals();
        $this->assertGreaterThan(0, $t['retained_cents'] + $t['owed_cents']);
        $this->assertNotEmpty(repsLedgerListRecent(10));
        $this->assertIsArray(repsLedgerListPending(10));
    }

    public function testPayeeUpsertWithoutStripeKeys(): void
    {
        putenv('STRIPE_SECRET_KEY=');
        $_ENV['STRIPE_SECRET_KEY'] = '';
        $r = repsStripeEnsurePayee('shop', 104, 'shop@example.com', 'Fleet Wash');
        $this->assertFalse($r['ok']);
        $this->assertSame('stripe_not_configured', $r['error']);
    }

    public function testDisburseResolveAndWebhookMark(): void
    {
        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT OR REPLACE INTO payout_payees
             (id, entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES (9001, 'shop', 104, 'Fleet', 'f@example.com', 'acct_test_fleet', 'ready', 1)"
        )->execute();
        $dest = repsDisburseResolveDestination([
            'bucket' => 'capture',
            'capture_payee' => 'shop',
            'shop_id' => 104,
        ]);
        $this->assertSame('acct_test_fleet', $dest);

        $pdo->prepare(
            "INSERT INTO disbursement_transfers (batch_id, ledger_line_id, stripe_transfer_id, amount_cents, destination, status)
             VALUES (1, 1, 'tr_test_1', 1000, 'acct_test_fleet', 'created')"
        )->execute();
        repsDisburseMarkTransferFromWebhook('tr_test_1', 'reversed');
        $st = $pdo->query("SELECT status FROM disbursement_transfers WHERE stripe_transfer_id='tr_test_1'")->fetchColumn();
        $this->assertSame('reversed', $st);
        $this->assertIsArray(repsDisburseListBatches(3));
    }

    public function testStripeClientHelpers(): void
    {
        // Isolate from ~/.ssh/reps-stripe.pass if present on the machine.
        putenv('REPS_STRIPE_PASS_FILE=/tmp/reps-stripe-empty-phpunit.pass');
        $_ENV['REPS_STRIPE_PASS_FILE'] = '/tmp/reps-stripe-empty-phpunit.pass';
        @unlink('/tmp/reps-stripe-empty-phpunit.pass');
        putenv('STRIPE_SECRET_KEY');
        putenv('STRIPE_PUBLISHABLE_KEY');
        unset($_ENV['STRIPE_SECRET_KEY'], $_ENV['STRIPE_PUBLISHABLE_KEY']);

        $this->assertFalse(repsStripeConfigured());
        $this->assertSame('', repsStripePublishableKey());
        $this->assertStringContainsString('api.stripe.com', repsStripeApiBase());
        $bal = repsStripeBalance();
        $this->assertFalse($bal['ok']);
        $req = repsStripeRequest('GET', '/v1/balance');
        $this->assertFalse($req['ok']);
        $this->assertNull(repsStripePayeeById(999999));
        repsStripeMarkPayeeFromAccountObject(['id' => 'acct_test_fleet', 'payouts_enabled' => true, 'charges_enabled' => false]);
        $row = repsDashDb()->query("SELECT payouts_enabled, onboarding_status FROM payout_payees WHERE stripe_account_id='acct_test_fleet'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['payouts_enabled']);
        $this->assertSame('ready', $row['onboarding_status']);
    }

    public function testConfiguredAcceptsRestrictedKey(): void
    {
        putenv('REPS_STRIPE_PASS_FILE=/tmp/reps-stripe-empty-phpunit.pass');
        $_ENV['REPS_STRIPE_PASS_FILE'] = '/tmp/reps-stripe-empty-phpunit.pass';
        putenv('STRIPE_SECRET_KEY=rk_test_phpunit_fake');
        $_ENV['STRIPE_SECRET_KEY'] = 'rk_test_phpunit_fake';
        $this->assertTrue(repsStripeConfigured());
        putenv('STRIPE_SECRET_KEY');
        unset($_ENV['STRIPE_SECRET_KEY']);
        $this->assertFalse(repsStripeConfigured());
    }

    public function testOpsByShopId(): void
    {
        $by = repsDashMoneyOpsByShopId([
            ['shop_id' => 1, 'id' => 10],
            ['shop_id' => 1, 'id' => 11],
            ['shop_id' => 2, 'id' => 12],
        ]);
        $this->assertCount(2, $by[1]);
        $this->assertCount(1, $by[2]);
    }

    protected function tearDown(): void
    {
        repsStripeClearHttpMock();
        putenv('STRIPE_SECRET_KEY');
        unset($_ENV['STRIPE_SECRET_KEY']);
        parent::tearDown();
    }

    public function testConnectOnboardingWithHttpMock(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';
        putenv('REPS_PUBLIC_BASE=https://reps.decisionsciencecorp.com');

        repsStripeSetHttpMock(static function (string $method, string $path, array $params) {
            if ($path === '/v1/accounts') {
                return ['ok' => true, 'status' => 200, 'body' => ['id' => 'acct_mock_1'], 'raw' => '{}'];
            }
            if ($path === '/v1/account_links') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => ['url' => 'https://connect.stripe.com/setup/e/mock'],
                    'raw' => '{}',
                ];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'unexpected ' . $path];
        });

        $created = repsStripeCreateExpressRecipient('a@example.com');
        $this->assertTrue($created['ok']);
        $this->assertSame('acct_mock_1', $created['account_id']);

        $link = repsStripeAccountOnboardingLink('acct_mock_1', 'https://x/r', 'https://x/ret');
        $this->assertTrue($link['ok']);
        $this->assertStringContainsString('connect.stripe.com', (string) $link['url']);

        $ensured = repsStripeEnsurePayee('operator', 9, 'pat@example.com', 'Pat Solo');
        $this->assertTrue($ensured['ok']);
        $this->assertSame('acct_mock_1', $ensured['account_id']);
        $this->assertNotEmpty($ensured['onboarding_url'] ?? '');
    }

    public function testBalanceAndTransferWithHttpMock(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';

        repsStripeSetHttpMock(static function (string $method, string $path, array $params) {
            if ($path === '/v1/balance') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => [
                        'available' => [['amount' => 50000, 'currency' => 'usd']],
                        'pending' => [['amount' => 1000, 'currency' => 'usd']],
                    ],
                    'raw' => '{}',
                ];
            }
            if ($path === '/v1/transfers') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => ['id' => 'tr_mock_' . ($params['metadata[reps_ledger_line_id]'] ?? 'x')],
                    'raw' => '{}',
                ];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'unexpected'];
        });

        $bal = repsStripeBalance();
        $this->assertTrue($bal['ok']);
        $this->assertSame(50000, $bal['available_cents']);

        $snap = repsSettlementReconcileStripeBalance('mock');
        $this->assertTrue($snap['ok']);
        $this->assertSame(50000, $snap['available_cents']);

        $pdo = repsDashDb();
        $pdo->exec("DELETE FROM payout_payees WHERE entity_type='shop' AND entity_id=777");
        $pdo->prepare(
            "INSERT INTO payout_payees (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('shop', 777, 'Mock Shop', 'm@example.com', 'acct_dest', 'ready', 1)"
        )->execute();

        $key = 'transfer_hour_' . uniqid('', true);
        repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => 1.0,
            'shop_id' => 777,
            'has_shop' => true,
            'has_affiliate' => false,
        ]);

        $batch = repsDisburseRunBatch('mock_batch', false);
        $this->assertTrue($batch['ok']);
        $this->assertGreaterThan(0, $batch['transferred']);
        $this->assertFalse($batch['dry_run']);
    }

    public function testJsonRequestMockAndWebhookSecret(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';
        putenv('STRIPE_WEBHOOK_SECRET=whsec_plat');
        putenv('STRIPE_CONNECT_WEBHOOK_SECRET=whsec_conn');
        $_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_plat';
        $_ENV['STRIPE_CONNECT_WEBHOOK_SECRET'] = 'whsec_conn';

        repsStripeSetHttpMock(static function () {
            return ['ok' => true, 'status' => 200, 'body' => ['id' => 'acct_v2'], 'raw' => '{}'];
        });
        $r = repsStripeRequestJson('POST', '/v2/core/accounts', ['display_name' => 'x']);
        $this->assertTrue($r['ok']);
        $this->assertSame('whsec_plat', repsStripeWebhookSecret(false));
        $this->assertSame('whsec_conn', repsStripeWebhookSecret(true));
    }

    public function testDisburseAffiliateAndOperatorDestinations(): void
    {
        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT OR IGNORE INTO users (username, email, password_hash, display_name, role)
             VALUES ('affmock', 'aff@mock', 'x', 'Aff', 'sales')"
        )->execute();
        $uid = (int) $pdo->query("SELECT id FROM users WHERE username='affmock'")->fetchColumn();
        $pdo->prepare(
            "INSERT OR REPLACE INTO payout_payees
             (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('user', ?, 'Aff', 'aff@mock', 'acct_aff', 'ready', 1)"
        )->execute([$uid]);
        $pdo->prepare(
            "INSERT OR REPLACE INTO payout_payees
             (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('operator', 42, 'Op', 'o@mock', 'acct_op', 'ready', 1)"
        )->execute();

        $this->assertSame('acct_aff', repsDisburseResolveDestination([
            'bucket' => 'affiliate',
            'affiliate_user_id' => $uid,
        ]));
        $this->assertSame('acct_aff', repsDisburseResolveDestination([
            'bucket' => 'affiliate',
            'affiliate_username' => 'affmock',
        ]));
        $this->assertSame('acct_op', repsDisburseResolveDestination([
            'bucket' => 'capture',
            'capture_payee' => 'operator',
            'operator_id' => 42,
        ]));
        $this->assertNull(repsDisburseResolveDestination(['bucket' => 'dsc']));
    }

    public function testConnectCreateFailuresAndExistingPayee(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';

        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/accounts') {
                return ['ok' => false, 'status' => 400, 'body' => ['error' => ['message' => 'bad']], 'raw' => '', 'error' => 'bad'];
            }
            return ['ok' => false, 'status' => 500, 'body' => [], 'raw' => '', 'error' => 'nope'];
        });
        $bad = repsStripeCreateExpressRecipient('x@y.com');
        $this->assertFalse($bad['ok']);

        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/accounts') {
                return ['ok' => true, 'status' => 200, 'body' => [], 'raw' => '{}'];
            }
            return ['ok' => false, 'status' => 500, 'body' => [], 'raw' => '', 'error' => 'nope'];
        });
        $missing = repsStripeCreateExpressRecipient('x@y.com');
        $this->assertFalse($missing['ok']);

        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/account_links') {
                return ['ok' => false, 'status' => 400, 'body' => [], 'raw' => '', 'error' => 'link_bad'];
            }
            return ['ok' => true, 'status' => 200, 'body' => ['id' => 'acct_x'], 'raw' => '{}'];
        });
        $linkBad = repsStripeAccountOnboardingLink('acct_x', 'https://a', 'https://b');
        $this->assertFalse($linkBad['ok']);

        // Existing payee row — update path + link failure still returns ok with account
        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT OR REPLACE INTO payout_payees
             (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('shop', 888, 'Old', 'old@x.com', 'acct_exist', 'pending', 0)"
        )->execute();
        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/account_links') {
                return ['ok' => false, 'status' => 400, 'body' => [], 'raw' => '', 'error' => 'link_failed'];
            }
            return ['ok' => true, 'status' => 200, 'body' => ['url' => 'https://x'], 'raw' => '{}'];
        });
        $ens = repsStripeEnsurePayee('shop', 888, 'new@x.com', 'New Name');
        $this->assertTrue($ens['ok']);
        $this->assertSame('acct_exist', $ens['account_id']);
        $this->assertArrayHasKey('error', $ens);
    }

    public function testDisburseFailedTransferAndEmptyBatch(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';
        $empty = repsDisburseRunBatch('empty', false);
        // may have leftover pending from other tests — still ok
        $this->assertTrue($empty['ok']);

        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT OR REPLACE INTO payout_payees
             (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('shop', 778, 'Fail Shop', 'f@x.com', 'acct_fail', 'ready', 1)"
        )->execute();
        $key = 'fail_hour_' . uniqid('', true);
        repsLedgerPostAcceptedHour([
            'hour_key' => $key,
            'hours' => 1.0,
            'shop_id' => 778,
            'has_shop' => true,
            'has_affiliate' => false,
        ]);
        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/transfers') {
                return ['ok' => false, 'status' => 402, 'body' => ['error' => ['message' => 'insufficient']], 'raw' => '', 'error' => 'insufficient'];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'x'];
        });
        $batch = repsDisburseRunBatch('fail_batch', false);
        $this->assertTrue($batch['ok']);
        $this->assertGreaterThan(0, $batch['failed']);
    }

    public function testSettlementBalanceErrorWhenConfigured(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';
        repsStripeSetHttpMock(static function () {
            return ['ok' => false, 'status' => 500, 'body' => [], 'raw' => '', 'error' => 'stripe_down'];
        });
        $r = repsSettlementReconcileStripeBalance('err');
        $this->assertFalse($r['ok']);
    }

    public function testSettlementCoverageWithStripeOk(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';
        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/balance') {
                return [
                    'ok' => true,
                    'status' => 200,
                    'body' => [
                        'available' => [['amount' => 9999, 'currency' => 'usd']],
                        'pending' => [],
                    ],
                    'raw' => '{}',
                ];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'x'];
        });
        $cov = repsSettlementCoverage();
        $this->assertTrue($cov['stripe_ok']);
        $this->assertSame(9999, $cov['stripe_available_cents']);
        $this->assertTrue(repsSettlementMarkReconciled(1, ''));
        $this->assertTrue(repsSettlementMarkReconciled(1, 'note'));
        $bad = repsSettlementRecord('shift', '', -1);
        $this->assertFalse($bad['ok']);
    }

    public function testEnsurePayeeCreateFailedAndEmptyAccountMark(): void
    {
        putenv('STRIPE_SECRET_KEY=sk_test_mock');
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_mock';
        $pdo = repsDashDb();
        $pdo->prepare(
            "INSERT OR REPLACE INTO payout_payees
             (entity_type, entity_id, display_name, email, stripe_account_id, onboarding_status, payouts_enabled)
             VALUES ('shop', 889, 'Blank', 'b@x.com', '', 'none', 0)"
        )->execute();
        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/accounts') {
                return ['ok' => false, 'status' => 400, 'body' => [], 'raw' => '', 'error' => 'create_failed'];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'x'];
        });
        $r = repsStripeEnsurePayee('shop', 889, 'b@x.com', 'Blank');
        $this->assertFalse($r['ok']);

        repsStripeSetHttpMock(static function (string $method, string $path) {
            if ($path === '/v1/accounts') {
                return ['ok' => true, 'status' => 200, 'body' => ['id' => 'acct_filled'], 'raw' => '{}'];
            }
            if ($path === '/v1/account_links') {
                return ['ok' => true, 'status' => 200, 'body' => ['url' => 'https://onboard'], 'raw' => '{}'];
            }
            return ['ok' => false, 'status' => 404, 'body' => [], 'raw' => '', 'error' => 'x'];
        });
        $ok = repsStripeEnsurePayee('shop', 889, 'b@x.com', 'Blank');
        $this->assertTrue($ok['ok']);
        $this->assertSame('acct_filled', $ok['account_id']);

        repsStripeMarkPayeeFromAccountObject([]);
        repsStripeMarkPayeeFromAccountObject(['id' => 'acct_filled', 'payouts_enabled' => false]);
        $row = repsStripePayeeById((int) $ok['payee_id']);
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['payouts_enabled']);
    }

    public function testChicagoWeekContainingAndCashMonday(): void
    {
        $w = repsSettlementChicagoWeekContaining('2026-08-02T22:14:32-05:00');
        $this->assertSame('2026-07-27', $w['week_start']);
        $this->assertSame('2026-08-02', $w['week_end']);
        $this->assertSame('2026-08-03', $w['cash_monday']);

        $forCash = repsSettlementWeekForCashMonday('2026-08-03');
        $this->assertSame('2026-07-27', $forCash['week_start']);
        $this->assertSame('2026-08-02', $forCash['week_end']);
        $this->assertSame(22, repsSettlementBatchCutoffHourCt());
    }

    public function testAccrueMondayBatchMatchesCalibrationCutoff(): void
    {
        // Calibrated Doc #1036: Aug 3 deposit $46.40 = 2.32h; Sun 22:14 session carried.
        $sessions = [
            [
                'session_id' => 'sess_in_1',
                'user_id' => 'u_mark',
                'first_name' => 'Mark',
                'last_name' => 'Hopkins',
                'status' => 'completed',
                'completed_at' => '2026-08-02T20:56:24-05:00',
                'accepted_hours' => 0.48,
            ],
            [
                'session_id' => 'sess_late',
                'user_id' => 'u_mark',
                'first_name' => 'Mark',
                'last_name' => 'Hopkins',
                'status' => 'completed',
                'completed_at' => '2026-08-02T22:14:32-05:00',
                'accepted_hours' => 0.16,
            ],
            [
                'session_id' => 'sess_in_2',
                'user_id' => 'u_mark',
                'first_name' => 'Mark',
                'last_name' => 'Hopkins',
                'status' => 'completed',
                'completed_at' => '2026-08-01T19:56:41-05:00',
                'accepted_hours' => 1.84,
            ],
        ];
        $acc = repsSettlementAccrueForCashMonday($sessions, '2026-08-03');
        $this->assertTrue($acc['ok']);
        $this->assertSame(4640, $acc['amount_cents']); // $46.40
        $this->assertEqualsWithDelta(2.32, $acc['accepted_hours'], 0.0001);
        $this->assertCount(2, $acc['sessions']);
        $this->assertCount(1, $acc['carried']);
        $this->assertSame('sess_late', $acc['carried'][0]['session_id']);
    }

    public function testProcessCashMondayBooksAndLedgers(): void
    {
        $sessions = [
            [
                'session_id' => 'proc_' . uniqid('', true),
                'user_id' => 'u_mark',
                'first_name' => 'Mark',
                'last_name' => 'H',
                'status' => 'completed',
                'completed_at' => '2026-07-18T12:00:00-05:00',
                'accepted_hours' => 2.75,
            ],
        ];
        $r = repsSettlementProcessCashMonday($sessions, '2026-07-20', [
            'has_shop' => false,
            'has_affiliate' => false,
        ]);
        $this->assertTrue($r['ok']);
        $this->assertSame(5500, $r['accrual']['amount_cents']);
        $this->assertTrue($r['settlement']['ok']);
        $this->assertSame(1, $r['ledger']['posted']);

        // Idempotent re-run
        $r2 = repsSettlementProcessCashMonday($sessions, '2026-07-20', [
            'has_shop' => false,
            'has_affiliate' => false,
        ]);
        $this->assertTrue($r2['ok']);
        $this->assertFalse($r2['settlement']['created']);
        $this->assertSame(0, $r2['ledger']['posted']);
        $this->assertSame(1, $r2['ledger']['skipped']);
    }
}
