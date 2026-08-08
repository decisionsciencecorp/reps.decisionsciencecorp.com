<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AffiliatePagesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/public/includes/affiliate_pages.php';
    }

    public function testReservedSlugsBlockProductHosts(): void
    {
        $this->assertFalse(reps_affiliate_slug_valid('www'));
        $this->assertFalse(reps_affiliate_slug_valid('dashboard'));
        $this->assertFalse(reps_affiliate_slug_valid('api'));
        $this->assertFalse(reps_affiliate_slug_valid('join'));
        $this->assertFalse(reps_affiliate_slug_valid('a'));
        $this->assertTrue(reps_affiliate_slug_valid('chuck'));
        $this->assertTrue(reps_affiliate_slug_valid('jim'));
    }

    public function testHostParseSingleLabel(): void
    {
        $this->assertSame(
            'chuck',
            reps_affiliate_slug_from_host('chuck.reps.decisionsciencecorp.com')
        );
        $this->assertSame(
            'jim',
            reps_affiliate_slug_from_host('jim.reps.decisionsciencecorp.com:443')
        );
        $this->assertNull(reps_affiliate_slug_from_host('reps.decisionsciencecorp.com'));
        $this->assertNull(reps_affiliate_slug_from_host('www.reps.decisionsciencecorp.com'));
        $this->assertNull(reps_affiliate_slug_from_host('dashboard.reps.decisionsciencecorp.com'));
        $this->assertNull(reps_affiliate_slug_from_host('evil.chuck.reps.decisionsciencecorp.com'));
    }

    public function testPathParse(): void
    {
        $this->assertSame('chuck', reps_affiliate_slug_from_path('/a/chuck/'));
        $this->assertSame('seven', reps_affiliate_slug_from_path('/a/seven'));
        $this->assertNull(reps_affiliate_slug_from_path('/a/www/'));
        $this->assertNull(reps_affiliate_slug_from_path('/join.php'));
    }

    public function testResolveSalesUser(): void
    {
        $chuck = reps_affiliate_resolve_sales_user('chuck');
        $this->assertNotNull($chuck);
        $this->assertSame('sales', $chuck['role']);
        $this->assertNull(reps_affiliate_resolve_sales_user('mark')); // admin, not sales
        $this->assertNull(reps_affiliate_resolve_sales_user('www'));
    }

    public function testCanonicalUrls(): void
    {
        $this->assertSame(
            'https://chuck.reps.decisionsciencecorp.com/',
            reps_affiliate_canonical_url('chuck')
        );
        $this->assertSame(
            'https://reps.decisionsciencecorp.com/a/chuck/',
            reps_affiliate_path_url('chuck')
        );
        $this->assertStringContainsString(
            'rep=chuck',
            reps_affiliate_join_url('chuck')
        );
    }

    public function testReservedHostIsNotAffiliateSlug(): void
    {
        $this->assertNull(reps_affiliate_slug_from_host('dashboard.reps.decisionsciencecorp.com'));
        $this->assertContains('dashboard', reps_affiliate_reserved_slugs());
    }
}
