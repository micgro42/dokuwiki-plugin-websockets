<?php

namespace dokuwiki\plugin\websockets\test;

use dokuwiki\plugin\websockets\app\utils;

/**
 * tests for the websockets plugin
 *
 * @author  Michael Große <mic.grosse@posteo.de>
 *
 * @group plugin_websockets
 * @group plugins
 */
class websockets_test extends \DokuWikiTest
{

    protected $pluginsEnabled = array('websockets');

    public function setUp(): void {
        parent::setUp();
    }


    /**
     * @todo: add more tests from https://tools.ietf.org/html/rfc6455#section-5.7
     *
     * @return array
     */
    public function decodeDataFrameProvider() {
        return array(
            // same as integer:
            array('0x81 0x05 0x48 0x65 0x6c 0x6c 0x6f', 'Hello', 'A single-frame unmasked text message'),
            array('0x81 0x85 0x37 0xfa 0x21 0x3d 0x7f 0x9f 0x4d 0x51 0x58', 'Hello', 'A single-frame masked text message')
        );
    }

    /**
     * @dataProvider decodeDataFrameProvider
     */
    public function test_decodeDataFrame($inputAsHex, $expected_output, $msg) {
        $util = new utils();
        $data = hex2bin(join('',array_filter(array_map('trim',explode('0x',$inputAsHex)))));
        $this->assertEquals($expected_output, $util->decodeDataFrame($data), $msg);
    }


    public function encodeDataFrameProvider() {
        return array(
            // same as integer:
            array('Hello', '810548656c6c6f', 'A single-frame unmasked text message')
        );
    }

    /**
     * @dataProvider encodeDataFrameProvider
     *
     * @param $input
     * @param $expected_output
     * @param $msg
     */
    public function test_encodeDataFrame($input, $expected_output, $msg) {
        $util = new utils();
        $encodedData = $util->encodeDataFrame($input, 'text', false);
        $this->assertEquals($expected_output, bin2hex($encodedData), $msg);
    }
}
