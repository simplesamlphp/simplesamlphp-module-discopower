<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\discopower\Controller;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
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
    /**
     * Set up for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Configuration::loadFromArray(
            [
                'module.enable' => ['discopower' => true],
            ],
            '[ARRAY]',
            'simplesaml'
        );

        Configuration::setPreLoadedConfig($this->config, 'config.php');
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
        $this->assertEquals('{"faventry":"http:\/\/example.org\/idp","default":"Nederland","tabs":["Frankrijk","Nederland","Duitsland"]}', $r->getContent());

        $request = Request::create(
            '/tablist',
            'GET',
            ['callback' => 'aapnoot'],
        );

        $c = new Controller\DiscoPower();

        $r = $c->tablist($request);
        $this->assertTrue($r->isSuccessful());
        $this->assertEquals('text/javascript', $r->headers->get('Content-Type'));
        $this->assertEquals('/**/aapnoot({"faventry":"http:\/\/example.org\/idp","default":"Nederland","tabs":["Frankrijk","Nederland","Duitsland"]});', $r->getContent());
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
