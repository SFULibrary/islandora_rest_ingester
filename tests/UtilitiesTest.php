<?php

namespace islandora_rest_client\ingesters;

use Monolog\Logger;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class Utilities extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        require_once 'includes/utilites.inc';

        $this->log = new \Monolog\Logger('Null handler');
        $handler = new \Monolog\Handler\NullHandler(Logger::INFO);
    }

    public function testGetValueFromMods()
    {
        $path_to_mods = 'tests/assets/MODS_get_title.xml';
        $xpath_expression = '//mods:titleInfo/mods:title';
        $value = get_value_from_mods($path_to_mods, $xpath_expression, $this->log);
        $this->assertEquals('Sample title', $value);
    }

    public function testGetLocalChecksum()
    {
        $path_to_file = 'tests/assets/MODS_get_title.xml';
        $cmd = array('c' => 'SHA-1');
        $checksum = get_local_checksum($path_to_file, $cmd);
        $this->assertEquals('4ee39cced5c3510d83fbf03d8351eff6ac6bcfde', $checksum);
    }

    public function testIsValidPid()
    {
        $pid = 'islandora:100';
        $ret = is_valid_pid($pid);
        $this->assertTrue($ret);

        $pid = 'islandora100';
        $ret = is_valid_pid($pid);
        $this->assertFalse($ret);
    }

    public function testGetPageLabel()
    {
        $page_dir = 'tests/assets/3';
        $label = get_page_label($page_dir, $this->log);
        $this->assertEquals('Page 3', $label);
    }

    public function testGetPropertiesFromFoxml()
    {
        $path_to_foxml= 'tests/assets/foxml.xml';
        $properties = get_properties_from_foxml($path_to_foxml);
        $this->assertEquals('admin', $properties['object']['ownerId']);
    }

    public function testGetCmodelFromExtension()
    {
        $extension = 'pdf';
        $cmodel = get_cmodel_from_extension($extension);
        $this->assertEquals('islandora:sp_pdf', $cmodel);
    }

    public function testGetCmodelFromCmodelTxt()
    {
        $dir = 'tests/assets/getcmodeltest';
        $cmodel = get_cmodel_from_cmodel_txt($dir);
        $this->assertEquals('foo:bar', $cmodel);
    }
}
