<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\discopower\Controller;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module\discopower\Controller;
use SimpleSAML\Session;
use SimpleSAML\TestUtils\ClearStateTestCase;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Set of tests for the controllers in the "discopwer" module.
 *
 * @covers \SimpleSAML\Module\discopower\Controller\DiscoPower
 */
class DiscoPowerTest extends ClearStateTestCase
{
    /** @var \SimpleSAML\Configuration */
    private static Configuration $discoconfig;


    /**
     * Set up for before tests.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUp();

        $config = Configuration::loadFromArray(
            [
                'module.enable' => ['discopower' => true],
                'trusted.url.domains' => ['example.com'],
            ],
            '[ARRAY]',
            'simplesaml'
        );

        Configuration::setPreLoadedConfig($config, 'config.php');

        self::$discoconfig = Configuration::loadFromArray(
            [
                'defaulttab' => 0,
                'trusted.url.domains' => ['example.com'],
            ],
            '[ARRAY]',
            'simplesaml'
        );
    }

    public function testDiscoPowerNoDiscoParams(): void
    {
        $request = Request::create(
            '/disco.php',
            'GET'
        );

        $c = new Controller\DiscoPower();

        $this->expectException(Error\Error::class);
        $this->expectExceptionMessage("DISCOPARAMS");
        $r = $c->main($request);
    }

    public function testDiscoPowerHasDiscoParams(): void
    {
        Configuration::setPreLoadedConfig(self::$discoconfig, 'module_discopower.php');

        $request = Request::create(
            '/disco.php',
            'GET',
        );
        $_GET = [
            'entityID' => 'https://example.com/sp',
            'return' => 'https://example.com/acs',
            'returnIDParam' => 'idpentityid'
        ];
        $_SERVER['REQUEST_URI'] = '/disco.php';

        $c = new Controller\DiscoPower();

        $r = $c->main($request);
        $this->assertInstanceOf(RunnableResponse::class, $r);
        $this->assertTrue($r->isSuccessful());
    }

    public function testDiscoPowerReturnUrlDisallowed(): void
    {
        Configuration::setPreLoadedConfig(self::$discoconfig, 'module_discopower.php');

        $request = Request::create(
            '/disco.php',
            'GET',
        );
        $_GET = [
            'entityID' => 'https://example.com/sp',
            'return' => 'https://attacker.example.org/acs',
            'returnIDParam' => 'idpentityid'
        ];
        $_SERVER['REQUEST_URI'] = '/disco.php';

        $c = new Controller\DiscoPower();

        // All exceptions in this stage are flattened into DISCOPARAMS
        $this->expectException(Error\Error::class);
        $this->expectExceptionMessage("DISCOPARAMS");
        $c->main($request);
    }

    public function testTablistJson(): void
    {
        $session = Session::getSessionFromRequest();
        $session->setData('discopower:tabList', 'faventry', 'http://example.org/idp');
        $session->setData('discopower:tabList', 'tabs', ['Frankrijk', 'Nederland', 'Duitsland']);
        $session->setData('discopower:tabList', 'defaulttab', 'Nederland');

        $request = Request::create(
            '/tablist',
            'GET'
        );

        $c = new Controller\DiscoPower();

        $r = $c->tablist($request);
        $this->assertTrue($r->isSuccessful());
        $this->assertEquals('application/json', $r->headers->get('Content-Type'));
        $this->assertEquals(
            '{"faventry":"http:\/\/example.org\/idp","default":"Nederland","tabs":["Frankrijk","Nederland","Duitsland"]}',
            $r->getContent(),
        );

        $request = Request::create(
            '/tablist',
            'GET',
            ['callback' => 'aapnoot'],
        );

        $c = new Controller\DiscoPower();

        $r = $c->tablist($request);
        $this->assertTrue($r->isSuccessful());
        $this->assertEquals('text/javascript', $r->headers->get('Content-Type'));
        $this->assertEquals(
            '/**/aapnoot({"faventry":"http:\/\/example.org\/idp","default":"Nederland","tabs":["Frankrijk","Nederland","Duitsland"]});',
            $r->getContent(),
        );
    }

    public function testTablistJsonNoSession(): void
    {
        $request = Request::create(
            '/tablist',
            'GET',
        );

        $c = new Controller\DiscoPower();

        $this->expectException(Error\Exception::class);
        $this->expectExceptionMessage("Could not get tab list from session");
        $r = $c->tablist($request);
    }

    public function testTablistJsonUnsafeCallback(): void
    {
        $session = Session::getSessionFromRequest();
        $session->setData('discopower:tabList', 'faventry', 'http://example.org/idp');
        $session->setData('discopower:tabList', 'tabs', ['Frankrijk', 'Nederland', 'Duitsland']);
        $session->setData('discopower:tabList', 'defaulttab', 'Nederland');

        $request = Request::create(
            '/tablist',
            'GET',
            ['callback' => 'alert("hallo")'],
        );

        $c = new Controller\DiscoPower();

        $this->expectException(Error\Exception::class);
        $this->expectExceptionMessage("Unsafe JSONP callback");
        $r = $c->tablist($request);
    }
}
