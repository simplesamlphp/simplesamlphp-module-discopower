<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\discopower;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module\discopower\PowerIdPDisco;

#[CoversClass(PowerIdPDisco::class)]
final class PowerIdPDiscoTest extends TestCase
{
    /** @var \SimpleSAML\Module\discopower\PowerIdPDisco */
    private static PowerIdPDisco $discoHandler;

    /** @var array */
    private static array $idpList;


    /**
     */
    public static function setUpBeforeClass(): void
    {
        $config = Configuration::loadFromArray([
            'module.enable' => ['discopower' => true],
            'metadata.sources' => [
                ['type' => 'flatfile', 'directory' => __DIR__ . '/test-metadata'],
            ],
        ], '[ARRAY]', 'simplesaml');
        Configuration::setPreLoadedConfig($config, 'config.php');

        $discoConfig = Configuration::loadFromArray([
            'defaulttab' => 0,
            'taborder' => ['B', 'A'],
        ], '[ARRAY]', 'module_discopower');
        Configuration::setPreLoadedConfig($discoConfig, 'module_discopower.php');

        /* spoof the request*/
        $_GET['entityID'] = 'https://sp01.example.net/sp';
        $_GET['return'] = 'https://sp01.example.net/simplesaml/module.php/saml/sp/discoresp.php';
        $_GET['returnIDParam'] = 'idpentityid';
        $_SERVER['SERVER_NAME'] = 'sp01.example.net';
        $_SERVER['REQUEST_URI'] = '/simplesaml/module.php/discopower/disco.php';

        self::$discoHandler = new PowerIdPDisco(
            ['saml20-idp-remote'],
            'poweridpdisco',
        );

        self::$idpList = MetaDataStorageHandler::getMetadataHandler()->getList('saml20-idp-remote');
    }

    /**
     */
    public function testPowerIdPDisco(): void
    {
        $this->assertInstanceOf(PowerIdPDisco::class, self::$discoHandler);
    }

    /**
     */
    public function testGetIdPList(): void
    {
        $refl = new ReflectionClass(self::$discoHandler);
        $getIdPList = $refl->getMethod('getIdPList');
        $getIdPList->setAccessible(true);
        $idpList = $getIdPList->invoke(self::$discoHandler);

        $this->assertEquals(self::$idpList, $idpList);
    }

    /**
     */
    public function testIdplistStructured(): void
    {
        $refl = new ReflectionClass(self::$discoHandler);
        $idplistStructured = $refl->getMethod('idplistStructured');
        $idplistStructured->setAccessible(true);
        $idpList = $idplistStructured->invokeArgs(self::$discoHandler, [self::$idpList]);

        $expected = [
            'B' => [
                'https://idp04.example.org' => [
                    'name' => ['en' => 'IdP 04'],
                    'tags' => ['A', 'B'],
                    'entityid' => 'https://idp04.example.org',
                    'UIInfo' => ['Keywords' => ['en' => ['aap','noot','mies']]],
                ],
                'https://idp06.example.org' => [
                    'name' => ['en' => 'IdP 06'],
                    'tags' => ['B'],
                    'entityid' => 'https://idp06.example.org',
                    'UIInfo' => ['Keywords' => ['fr' => ['singue','noix','mies'], 'de' => ['Affe', 'Nuss', 'mies']]],
                ],
                'https://idp05.example.org' => [
                    'tags' => ['B'],
                    'entityid' => 'https://idp05.example.org',
                ],
            ],
            'A' => [
                'https://idp03.example.org' => [
                    'name' => ['en' => 'IdP 03'],
                    'discopower.weight' => 100,
                    'tags' => ['A'],
                    'entityid' => 'https://idp03.example.org',
                ],
                'https://idp02.example.org' => [
                    'name' => ['en' => 'IdP 02'],
                    'tags' => ['A'],
                    'entityid' => 'https://idp02.example.org',
                ],
                'https://idp04.example.org' => [
                    'name' => ['en' => 'IdP 04'],
                    'tags' => ['A','B',],
                    'entityid' => 'https://idp04.example.org',
                    'UIInfo' => ['Keywords' => ['en' => ['aap','noot','mies']]],
                ],
                'https://idp01.example.org' => [
                    'name' => ['en' => 'IdP 01'],
                    'discopower.weight' => 1,
                    'tags' => ['A'],
                    'entityid' => 'https://idp01.example.org',
                ],
            ],
        ];
        $this->assertEquals($expected, $idpList);
        $this->assertEquals($expected['A'], $idpList['A']);
        $this->assertEquals($expected['B'], $idpList['B']);
    }

    /**
     */
    public function testmcmp(): void
    {
        $this->assertEquals(
            -1,
            PowerIdPDisco::mcmp(
                ['name' => 'B', 'entityid' => '1'],
                ['name' => 'A', 'entityid' => '2'],
            ),
            'name,name',
        );
        $this->assertEquals(
            -1,
            PowerIdPDisco::mcmp(
                ['entityid' => '1'],
                ['name' => 'A', 'entityid' => '2'],
            ),
            'entityid,name',
        );
        $this->assertEquals(
            1,
            PowerIdPDisco::mcmp(
                ['entityid' => '2'],
                ['entityid' => '1'],
            ),
            'entityid,entityid',
        );
        $this->assertEquals(
            -1,
            PowerIdPDisco::mcmp(
                ['name' => 'B', 'entityid' => '1', 'discopower.weight' => 100],
                ['name' => 'A', 'entityid' => '2'],
            ),
            'weight,name',
        );
        $this->assertEquals(
            1,
            PowerIdPDisco::mcmp(
                ['name' => 'B', 'entityid' => '1', 'discopower.weight' => 100],
                ['name' => 'A', 'entityid' => '2', 'discopower.weight' => 200],
            ),
            'weight,weight',
        );
    }
}
