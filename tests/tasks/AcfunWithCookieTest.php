<?php
/**
 * @author iyaozhen
 * Date: 2018-04-10
 */

namespace Qiandao\Tests\tasks;

use Qiandao\tasks\AcfunWithCookie;
use PHPUnit\Framework\TestCase;

class AcfunWithCookieTest extends TestCase
{
    public function testCheckParams()
    {
        $acfun = new AcfunWithCookie([
            'cookie' => ""
        ]);
        $result = $acfun->checkParams();
        self::assertEquals(401, $result['code']);

        $acfun = new AcfunWithCookie([
            'cookie' => "test"
        ]);
        $result = $acfun->checkParams();
        self::assertEquals(402, $result['code']);

        $acfun = new AcfunWithCookie([
            'cookie' => "auth_key=xxx; auth_key_ac_sha1=xxx; auth_key_ac_sha1_=xxx; notice_status=1"
        ]);
        $result = $acfun->checkParams();
        self::assertEquals(200, $result['code']);
    }

    public function testRun()
    {
        $acfun = new AcfunWithCookie([
            'cookie' => "auth_key=xxx; auth_key_ac_sha1=xxx; auth_key_ac_sha1_=xxx; notice_status=1"
        ]);
        $result = $acfun->run();

        self::assertEquals(500, $result['code']);
    }
}
