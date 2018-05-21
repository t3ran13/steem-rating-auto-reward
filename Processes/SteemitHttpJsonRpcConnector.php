<?php

namespace MyApp\Processes;


use GrapheneNodeClient\Connectors\Http\HttpJsonRpcConnectorAbstract;

class SteemitHttpJsonRpcConnector extends HttpJsonRpcConnectorAbstract
{
    /**
     * @var string
     */
    protected $platform = self::PLATFORM_STEEMIT;

    /**
     * waiting answer from Node during $wsTimeoutSeconds seconds
     *
     * @var int
     */
    protected $wsTimeoutSeconds = 10;

    /**
     * https or http server
     *
     * if you set several nodes urls, if with first node will be trouble
     * it will connect after $maxNumberOfTriesToCallApi tries to next node
     *
     * @var string
     */
    protected static $nodeURL = [
        'https://rpc.buildteam.io',
        'https://rpc.steemviz.com',
        'https://steemd.privex.io',
        'https://api.steemit.com'
//        'https://steemd.pevo.science' //too often 503
//        'https://steemd.minnowsupportproject.org' //not full answers
    ];
}