<?php
/**
 * @author iyaozhen
 * Date: 2018-04-06
 */

namespace Qiandao\redis;

use \Redis;
use \RedisException;

class RedisWrap
{
    private static $_instance;
    /**
     * @var Redis
     */
    private static $_redis;
    public $host;
    public $port;
    public $password;
    public $dbIndex;

    /**
     * RedisWrap constructor.
     *
     * @param $host
     * @param $port
     * @param null $password
     * @param null $dbIndex
     * @throws RedisException
     */
    protected function __construct($host, $port, $password = null, $dbIndex = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->dbIndex = $dbIndex;
        $result = $this->connRedis();
        if ($result !== true) {
            throw $result;
        };
    }

    /**
     * RedisWrap 单例
     *
     * @param $host
     * @param $port
     * @param string $password
     * @param int $dbIndex
     * @return RedisWrap
     * @throws RedisException
     */
    public static function getInstance($host, $port, $password = null, $dbIndex = null)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new RedisWrap($host, $port, $password, $dbIndex);
        }

        return self::$_instance;
    }

    /**
     * 接管redis方法调用
     * 在断线时重连一次
     *
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws RedisException
     */
    public function __call($name, $arguments)
    {
        // 重连
        if (self::$_redis === null) {
            $result = $this->connRedis();
            // 重连仍然错误则抛出异常（为了效率仅重连一次）
            if ($result !== true) {
                throw $result;
            }
        }

        if (method_exists(self::$_redis, $name)) {
            try {
                return call_user_func_array([self::$_redis, $name], $arguments);
            } catch (RedisException $e) {
                self::$_redis = null;
                if ($e->getMessage() == 'Redis server went away') {
                    // 断线则重试
                    return call_user_func_array([$this, $name], $arguments);
                } else {
                    // 非预期错误，抛出异常
                    throw $e;
                }
            }
        } else {
            throw new RedisException("No method $name");
        }
    }

    /**
     * 连接redis
     *
     * @return bool|RedisException
     */
    private function connRedis()
    {
        try {
            self::$_redis = new Redis();
            @self::$_redis->pconnect($this->host, $this->port);
            if (!empty($this->password)) {
                self::$_redis->auth($this->password);
            }
            if (!empty($this->dbIndex)) {
                self::$_redis->select($this->dbIndex);
            }

            return self::$_redis->ping() === '+PONG';
        } catch (RedisException $e) {
            self::$_redis = null;

            return $e;
        }
    }

    /**
     * 私有克隆方法
     * 禁止clone
     */
    private function __clone()
    {
    }
}