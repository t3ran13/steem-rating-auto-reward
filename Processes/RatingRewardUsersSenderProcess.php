<?php



namespace MyApp\Processes;


use GolosPhpEventListener\app\process\ProcessAbstract;
use GolosPhpEventListener\app\process\ProcessInterface;
use GrapheneNodeClient\Commands\CommandQueryData;
use GrapheneNodeClient\Commands\Single\BroadcastTransactionSynchronousCommand;
use GrapheneNodeClient\Connectors\ConnectorInterface;
use GrapheneNodeClient\Connectors\Http\SteemitHttpJsonRpcConnector;
use GrapheneNodeClient\Tools\Bandwidth;
use GrapheneNodeClient\Tools\Transaction;
use MyApp\Db\RedisManager;

/**
 *
 * @method RedisManager getDBManager()
 */
class RatingRewardUsersSenderProcess extends ProcessAbstract
{
    protected $isRunning = true;
    private $rewardPoolName;
    private $rewardPoolWif;
    protected $priority = 17;
    protected $connectorClassName = 'GrapheneNodeClient\Connectors\WebSocket\SteemitWSConnector';

    /**
     * RatingRewardUsersSenderProcess constructor.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->rewardPoolName = getenv('REWARD_POOL_NAME');
        $this->rewardPoolWif = getenv('REWARD_POOL_WIF');
    }

    /**
     * @return ConnectorInterface|null
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     *
     */
    public function initConnector()
    {
        $this->connector = new $this->connectorClassName(2);
    }

    /**
     * run before process start
     *
     * @return void
     */
    public function init()
    {
        $this->setDBManager(new RedisManager());
    }


    public function initSignalsHandlers()
    {
        pcntl_signal(SIGTERM, [$this, 'signalsHandlers']);
        pcntl_signal(SIGINT, [$this, 'signalsHandlers']); //ctrl+c
        pcntl_signal(SIGHUP, [$this, 'signalsHandlers']); //restart process
    }

    public function signalsHandlers($signo, $signinfo)
    {
        echo PHP_EOL . ' --- process with pid=' . $this->getPid() . ' got signal=' . $signo . ' and signinfo='
            . print_r($signinfo, true);

        switch ($signo) {
            case SIGTERM:
                $this->isRunning = false;
                break;
            default:
        }
    }

    public function start()
    {
        pcntl_setpriority($this->priority, getmypid());

        echo PHP_EOL . date('Y-m-d H:i:s') . ' RatingRewardUsersSenderProcess is running';

        $total = $this->getDBManager()->ratingUsersRewardGetQueueLength();
        $connector = null;
        $command = null;
        $this->initConnector();
        if ($total > 0) {
            $connector = $this->getConnector();
            $command = new BroadcastTransactionSynchronousCommand($connector);
        }
        for ($i = 50; $this->isRunning && ($i < $total || $i - $total < 50); $i += 50) {
            $list = $this->getDBManager()->ratingUsersRewardGetFirstNFromQueue(50);

            //transfer agregation to few users
            $chainName = $connector->getPlatform();
            /** @var CommandQueryData $tx */
            $tx = Transaction::init($connector, 'PT4M');
            $opNumber = 0;
            foreach ($list as $data) {
                foreach ($data['rewards'] as $reward) {
                    $tx->setParamByKey(
                        '0:operations:' . ($opNumber++),
                        [
                            'transfer',
                            [
                                'from'   => $this->rewardPoolName,
                                'to'     => $data['author'],
                                'amount' => $reward,
                                'memo'   => $data['memo']
                            ]
                        ]
                    );
                    break;
                }
            }
            Transaction::sign($chainName, $tx, ['active' => $this->rewardPoolWif]);

            $bandwidth = $this->getTrxBandwidth(json_encode($tx->getParams(),JSON_UNESCAPED_UNICODE));
            if ($bandwidth['used'] >= $bandwidth['available']) {
                echo PHP_EOL . date('Y-m-d H:i:s') . ' - account bandwith is not enought for trx : ' . round($bandwidth['used'] / $bandwidth['available'], 3);
                break;
            }


            $connector->setConnectionTimeoutSeconds(20);
            $connector->setMaxNumberOfTriesToReconnect(2);
            $answer = $command->execute(
                $tx
            );

            if (isset($answer['result']['block_num']) && $answer['result']['block_num'] > 0) {
                foreach ($list as $data) {
                    $this->getDBManager()->ratingUsersRewardRemoveFromQueue($data);
                }
                $usersTotal = count($list);
                echo PHP_EOL . date('Y-m-d H:i:s') . " - {$usersTotal} users got reward in block {$answer['result']['block_num']}";
            } else {
                echo PHP_EOL . date('Y-m-d H:i:s') . ' - error during sending tokens ';
                //log about error
            }
        }

        echo PHP_EOL . date('Y-m-d H:i:s') . ' RatingRewardUsersSenderProcess did work';
    }

    /**
     * ask process to start
     *
     * @return bool
     */
    public function isStartNeeded()
    {
        $status = $this->getStatus();
        return $status === ProcessInterface::STATUS_RUN
            || (
                $status === ProcessInterface::STATUS_STOPPED
                && $this->getMode() === ProcessInterface::MODE_REPEAT
                && $this->getDBManager()->ratingUsersRewardGetQueueLength()
            );
    }

    /**
     * clear parent resourses in child process
     *
     * @return void
     */
    public function clearParentResources()
    {
    }

    /**
     * @param string $trxString
     *
     * @return array
     * @throws \Exception
     */
    protected function getTrxBandwidth($trxString)
    {
        $connector = $this->getConnector();
        $connector->setConnectionTimeoutSeconds(3);
        $trxString = mb_strlen($trxString, '8bit');
        $bandwidth = Bandwidth::getBandwidthByAccountName($this->rewardPoolName, 'market', $connector);

        $bandwidth['used'] = $trxString * 10 + $bandwidth['used'];

        return $bandwidth;
    }
}