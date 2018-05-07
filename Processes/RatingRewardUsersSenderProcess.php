<?php



namespace MyApp\Processes;


use GolosPhpEventListener\app\process\ProcessAbstract;
use GolosPhpEventListener\app\process\ProcessInterface;
use GrapheneNodeClient\Commands\CommandQueryData;
use GrapheneNodeClient\Commands\Single\GetContentCommand;
use GrapheneNodeClient\Connectors\WebSocket\GolosWSConnector;
use MyApp\Db\RedisManager;

/**
 *
 * @method RedisManager getDBManager()
 */
class RatingRewardUsersSenderProcess extends ProcessAbstract
{
    protected $isRunning = true;
    protected $priority = 17;

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

        $listenerId = $this->getId();
        echo PHP_EOL . ' - RatingRewardUsersSenderProcess is running';
        $total = $this->getDBManager()->ratingUsersRewardGetQueueLength();
        for ($i = 50; $this->isRunning && ($i < $total || $i - $total < 50); $i += 50) {
            $list = $this->getDBManager()->ratingUsersRewardGetFirstNFromQueue(50);
            echo PHP_EOL . ' - users=' . print_r($list, true);
            foreach ($list as $data) {
                $this->getDBManager()->ratingUsersRewardRemoveFromQueue($data);
            }

//            $connector = new GolosWSConnector();
//
//            $commandQuery = new CommandQueryData();
//            $commandQuery->setParamByKey('0', $first['author']);//blockNum
//            $commandQuery->setParamByKey('1', $first['permlink']);//onlyVirtual
//
//            $command = new GetContentCommand($connector);
//            $data = $command->execute(
//                $commandQuery,
//                'result'
//            );
        }

        echo PHP_EOL . ' - RatingRewardUsersSenderProcess did work';
    }

    /**
     * ask process to start
     *
     * @return bool
     */
    public function isStartNeeded()
    {
        echo PHP_EOL . ' - RatingRewardUsersSenderProcess is start needed=' . print_r($this->getDBManager()->ratingPostRewardGetQueueLength() > 0, true);
        return $this->getStatus() === ProcessInterface::STATUS_RUN
            && $this->getDBManager()->ratingUsersRewardGetQueueLength() > 0;
    }

    /**
     * clear parent resourses in child process
     *
     * @return void
     */
    public function clearParentResources()
    {
    }
}