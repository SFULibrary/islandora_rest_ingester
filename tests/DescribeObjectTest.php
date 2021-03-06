<?php

namespace islandora_rest_ingester\ingesters;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class DescribeObjectTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
         $this->islandoraObject = '{
             "pid": "islandora:100",
             "label": "Mock Object",
             "owner": "fedoraAdmin",
             "models": ["islandora:collectionCModel"],
             "state": "A",
             "created": "2013-05-27T09:53:39.286Z",
             "modified": "2013-06-24T04:20:26.190Z",
             "datastreams": [{
               "dsid": "RELS-EXT",
               "label": "Fedora Object to Object Relationship Metadata.",
               "state": "A",
               "size": 1173,
               "mimeType": "application\/rdf+xml",
               "controlGroup": "X",
               "created": "2013-06-23T07:28:32.787Z",
               "versionable": true,
               "versions": []
             }]
            }';
    }

    public function testGet()
    {
        // Create a mock response.
        $mock = new MockHandler([
            new Response(200, [], $this->islandoraObject),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        // Queue a single response.
        $body = $client->request('GET', 'http://example.org/islandora/rest/v1/object/islandora:100')->getBody();
        $body = (string) $body;
        $describe_object = json_decode($body, true);

        // Make an assertion.
        $this->assertEquals('fedoraAdmin', $describe_object['owner']);
    }
}
