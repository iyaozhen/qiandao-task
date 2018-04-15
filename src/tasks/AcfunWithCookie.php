<?php
/**
 * @author iyaozhen
 * Date: 2018-04-10
 */

namespace Qiandao\tasks;

use Qiandao\common\Helper;


class AcfunWithCookie extends AbstractTask
{
    /**
     * 检查参数
     * @return array
     */
    public function checkParams()
    {
        if (!empty($this->params['cookie'])) {
            if (strpos($this->params['cookie'], 'auth_key') !== false) {
                return [
                    'code' => 200,
                    'msg' => 'ok'
                ];
            }
            else {
                return [
                    'code' => 402,
                    'msg' => 'no auth_key in cookie'
                ];
            }
        }
        else {
            return [
                'code' => 401,
                'msg' => 'cookie is empty'
            ];
        }
    }

    /**
     * 运行
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function run()
    {
        $client = Helper::getNormalHttpClient('http://www.acfun.cn');

        $response = $client->request('POST', '/webapi/record/actions/signin', [
            'headers' => [
                'Cookie' => $this->params['cookie'],
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.186 Safari/537.36',
                'Referer' => 'http://www.acfun.cn/member/'
            ],
            'query' => [
                'channel' => 0,
                'date' => time() * 1000
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (in_array($data['code'], [200, 410004])) {
            return [
                'code' => 200,
                'msg' => 'ok'
            ];
        }
        else {
            return [
                'code' => 500,
                'msg' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ];
        }
    }
}