<?php
/**
 * @author iyaozhen
 * Date: 2018-04-10
 */

namespace Qiandao\tasks;


abstract class AbstractTask implements InterfaceTask
{
    protected $params = [];

    /**
     * 设置请求参数
     * @param array $params
     */
    function __construct(array $params)
    {
        $this->params = $params;
    }
}