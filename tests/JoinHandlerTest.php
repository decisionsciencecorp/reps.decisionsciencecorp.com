<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JoinHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/public/join-handler.php';
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testCsrfFailure(): void
    {
        $_POST = [
            'name' => 'A',
            'phone' => '1',
            'email' => 'a@example.com',
            'path' => 'on_job',
            'expectations_ack' => '1',
        ];
        $r = repsJoinHandlePost('operator');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('token', strtolower($r['error'] ?? ''));
    }

    public function testHoneypotSucceedsSilently(): void
    {
        $token = repsDashCsrfToken();
        $_POST = [
            'csrf_token' => $token,
            'company_website' => 'http://spam.example',
            'name' => 'Bot',
            'phone' => '1',
            'email' => 'bot@example.com',
            'path' => 'on_job',
            'expectations_ack' => '1',
        ];
        $r = repsJoinHandlePost('operator');
        $this->assertTrue($r['ok']);
        $this->assertSame(0, $r['id'] ?? -1);
    }

    public function testOperatorAndPartnerCreate(): void
    {
        repsJoinBootstrap();
        $token = repsDashCsrfToken();
        $_POST = [
            'csrf_token' => $token,
            'name' => 'Join Op',
            'phone' => '2145554444',
            'email' => 'joinop@example.com',
            'path' => 'on_job',
            'metro' => 'DFW',
            'notes' => 'hi',
            'affiliate_code' => 'jim',
            'expectations_ack' => '1',
            'company_website' => '',
        ];
        $r = repsJoinHandlePost('operator');
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertGreaterThan(0, $r['id'] ?? 0);

        $token2 = repsDashCsrfToken();
        $_POST = [
            'csrf_token' => $token2,
            'name' => 'Join Partner',
            'phone' => '2145554445',
            'email' => 'joinpartner@example.com',
            'metro' => 'Austin',
            'notes' => 'network',
            'expectations_ack' => '1',
            'company_website' => '',
        ];
        $p = repsJoinHandlePost('partner');
        $this->assertTrue($p['ok'], $p['error'] ?? '');
        $lead = repsDashFindApplyLead((int) $p['id']);
        $this->assertSame('affiliate', $lead['join_kind']);
        $this->assertNull($lead['assigned_sales_rep']);
    }

    public function testOperatorUsesGetRepFallback(): void
    {
        repsJoinBootstrap();
        $token = repsDashCsrfToken();
        $_GET['rep'] = 'seven';
        $_POST = [
            'csrf_token' => $token,
            'name' => 'Get Rep',
            'phone' => '2145554499',
            'email' => 'getrep@example.com',
            'path' => 'at_home',
            'expectations_ack' => '1',
            'company_website' => '',
        ];
        unset($_POST['affiliate_code']);
        $r = repsJoinHandlePost('operator');
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $lead = repsDashFindApplyLead((int) $r['id']);
        $this->assertSame('seven', $lead['assigned_sales_rep']);
        $this->assertSame('referral', $lead['assign_source']);
    }
}
