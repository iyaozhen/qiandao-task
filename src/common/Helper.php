<?php
/**
 * @author iyaozhen
 * Date: 2018-04-15
 */

namespace Qiandao\common;

use GuzzleHttp\Client;


class Helper
{
    /**
     * 获取一个通用的GuzzleHttp Client
     *
     * @param string $baseUri
     * @param array $config
     *
     * @return Client
     */
    static function getNormalHttpClient($baseUri = null, array $config = [])
    {
        return new Client($config + [
                'base_uri' => $baseUri,
                'cookies' => true,
                'timeout' => 60,
                'verify' => false,
                'proxy' => self::getHttpProxyConf()
            ]);
    }

    /**
     * 获取http代理配置
     * 首先尝试从环境变量获取，没有的话，若是Windows平台则从Internet Settings获取
     *
     * @return array
     */
    static function getHttpProxyConf()
    {
        $httpProxy = '';
        $httpsProxy = '';

        $proxyEnv = getenv('HTTP_PROXY');
        if (php_sapi_name() == 'cli' && $proxyEnv !== false) {
            $httpProxy = $proxyEnv;
        }
        $proxyEnv = getenv('HTTPS_PROXY');
        if ($proxyEnv !== false) {
            $httpsProxy = $proxyEnv;
        }

        if (empty($httpProxy) && empty($httpsProxy) && strpos(PHP_OS, 'WIN') !== false) {
            /**
             * @link https://stackoverflow.com/a/12618950
             */
            $proxyServer = shell_exec('reg query "HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Internet Settings" | find /i "proxyserver"');
            if (!empty($proxyServer)) {
                if (preg_match("/ProxyServer *REG_SZ *?(.+)/i", $proxyServer, $matches)) {
                    $proxyConf = explode(';', $matches[1]);
                    foreach ($proxyConf as $item) {
                        if (preg_match("/http=.+/", $item)) {
                            $httpProxy = str_replace('http=', 'http://', $item);
                        }
                        if (preg_match("/https=.+/", $item)) {
                            $httpsProxy = str_replace('https=', 'https://', $item);;
                        }
                    }
                }
            }
        }

        return [
            'http' => trim($httpProxy),
            'https' => trim($httpsProxy),
        ];
    }
}