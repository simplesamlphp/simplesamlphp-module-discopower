<?php

namespace SimpleSAML\Test\Module\discopower;

use PHPUnit\Framework\TestCase;
use SAML2\Constants;
use SimpleSAML\Module\discopower\PowerIdPDisco;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;

class PowerIdPDiscoTest extends TestCase
{
    private $discoHandler;
    private $config;
    private $discoConfig;
    private $idpList;

    /**
     */
    protected function setUp(): void
    {
        $this->config = Configuration::loadFromArray([
            'module.enable' => ['discopower' => true],
            'metadata.sources' => [
                ['type' => 'flatfile', 'directory' => __DIR__ . '/test-metadata'],
            ],
        ], '[ARRAY]', 'simplesaml');
        Configuration::setPreLoadedConfig($this->config, 'config.php');

        $this->discoConfig = Configuration::loadFromArray([
            'defaulttab' => 0,
            'taborder' => ['B', 'A'],
        ], '[ARRAY]', 'module_discopower');
        Configuration::setPreLoadedConfig($this->discoConfig, 'module_discopower.php');

        /* spoof the request*/
        $_GET['entityID'] = 'https://sp01.example.net/sp';
        $_GET['return'] = 'https://sp01.example.net/simplesaml/module.php/saml/sp/discoresp.php';
        $_GET['returnIDParam'] = 'idpentityid';
        $_SERVER['SERVER_NAME'] = 'sp01.example.net';
        $_SERVER['REQUEST_URI'] = '/simplesaml/module.php/discopower/disco.php';

        $this->discoHandler = new PowerIdPDisco(
            ['saml20-idp-remote'],
            'poweridpdisco'
        );

        $this->idpList = MetaDataStorageHandler::getMetadataHandler()->getList('saml20-idp-remote');
    }

    /**
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco::__construct
     */
    public function testPowerIdPDisco(): void
    {
        $this->assertInstanceOf('\SimpleSAML\Module\discopower\PowerIdPDisco', $this->discoHandler);
    }

    /**
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco::getIdPList
     */
    public function testGetIdPList(): void
    {
        $refl = new \ReflectionClass($this->discoHandler);
        $getIdPList = $refl->getMethod('getIdPList');
        $getIdPList->setAccessible(true);
        $idpList = $getIdPList->invoke($this->discoHandler);

        $this->assertEquals($this->idpList, $idpList);
    }

    /**
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco::idplistStructured
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco::getIdPList
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco::mcmp
     */
    public function testIdplistStructured(): void
    {
        $refl = new \ReflectionClass($this->discoHandler);
        $idplistStructured = $refl->getMethod('idplistStructured');
        $idplistStructured->setAccessible(true);
        $idpList = $idplistStructured->invokeArgs($this->discoHandler, [$this->idpList]);

        $expected = [
            'B' => [
                'https://idp04.example.org' => [
                    'name' => ['en' => 'IdP 04'],
                    'tags' => ['A', 'B'],
                    'entityid' => 'https://idp04.example.org'
                ],
                'https://idp06.example.org' => [
                    'name' => ['en' => 'IdP 06'],
                    'tags' => ['B'],
                    'entityid' => 'https://idp06.example.org'
                ],
                'https://idp05.example.org' => [
                    'tags' => ['B'],
                    'entityid' => 'https://idp05.example.org'
                ],
            ],
            'A' => [
                'https://idp03.example.org' => [
                    'name' => ['en' => 'IdP 03'],
                    'discopower.weight' => 100,
                    'tags' => ['A'],
                    'entityid' => 'https://idp03.example.org'
                ],
                'https://idp02.example.org' => [
                    'name' => ['en' => 'IdP 02'],
                    'tags' => ['A'],
                    'entityid' => 'https://idp02.example.org'
                ],
                'https://idp04.example.org' => [
                    'name' => ['en' => 'IdP 04'],
                    'tags' => ['A','B',],
                    'entityid' => 'https://idp04.example.org'
                ],
                'https://idp01.example.org' => [
                    'name' => ['en' => 'IdP 01'],
                    'discopower.weight' => 1,
                    'tags' => ['A'],
                    'entityid' => 'https://idp01.example.org'
                ],
            ],
        ];
        $this->assertEquals($expected, $idpList);
        $this->assertEquals($expected['A'], $idpList['A']);
        $this->assertEquals($expected['B'], $idpList['B']);
    }

    /**
     * @covers \SimpleSAML\Module\discopower\PowerIdPDisco::mcmp
     */
    public function testmcmp(): void
    {
        $this->assertEquals(
            -1,
            PowerIdPDisco::mcmp(
                ['name' => 'B', 'entityid' => '1'],
                ['name' => 'A', 'entityid' => '2']
            ),
            'name,name'
        );
        $this->assertEquals(
            -1,
            PowerIdPDisco::mcmp(
                ['entityid' => '1'],
                ['name' => 'A', 'entityid' => '2']
            ),
            'entityid,name'
        );
        $this->assertEquals(
            1,
            PowerIdPDisco::mcmp(
                ['entityid' => '2'],
                ['entityid' => '1']
            ),
            'entityid,entityid'
        );
        $this->assertEquals(
            -1,
            PowerIdPDisco::mcmp(
                ['name' => 'B', 'entityid' => '1', 'discopower.weight' => 100],
                ['name' => 'A', 'entityid' => '2']
            ),
            'weight,name'
        );
        $this->assertEquals(
            1,
            PowerIdPDisco::mcmp(
                ['name' => 'B', 'entityid' => '1', 'discopower.weight' => 100],
                ['name' => 'A', 'entityid' => '2', 'discopower.weight' => 200]
            ),
            'weight,weight'
        );
    }
}
