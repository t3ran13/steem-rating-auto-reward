<?php


namespace MyApp\Handlers;


use GolosPhpEventListener\app\handlers\HandlerAbstract;
use GolosPhpEventListener\app\process\ProcessInterface;
use MyApp\Db\RedisManager;

/**
 *
 * @method RedisManager getDBManager()
 */
class GotTransferHandler extends HandlerAbstract
{
    const COMMAND_REWARDS_ON = 'on';
    const COMMAND_REWARDS_OFF = 'off';
    protected $memoRewardsOff = 'Rating rewards are OFF for you. You can ON rewards by transfer with "' . self::COMMAND_REWARDS_ON .'" memo';
    protected $memoRewardsOn = 'Rating rewards are ON for you. You can OFF rewards by transfer with "' . self::COMMAND_REWARDS_OFF .'" memo';
    protected $priority = 15;

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
//        echo PHP_EOL . ' --- listener with id/pid=' . $listenerId . '/' . $this->getPid() . ' is running';
        $events = $this->getDBManager()->eventsListByListenerId($listenerId);
        foreach ($events as $key => $event) {
            $event = json_decode($event, true);
            $blockN = $event['block'];
            $trxInBlock = $event['trx_in_block'];
            $this->getDBManager()->eventDelete($listenerId, $blockN, $trxInBlock);
//            echo PHP_EOL . ' --- listener with id=' . $listenerId . ' handle and deleted event with key=' . $key;

            $memo = strtolower($event['op'][1]['memo']);
            if (
                $event['op'][1]['to'] === getenv('REWARD_POOL_NAME')
                && (
                    strpos($memo, self::COMMAND_REWARDS_ON) === 0
                    || strpos($memo, self::COMMAND_REWARDS_OFF) === 0
                )
            ) {
                $fromUser = $event['op'][1]['from'];
                if (strpos($memo, self::COMMAND_REWARDS_OFF) === 0) {
                    $this->getDBManager()->ratingUsersRewardsStopListAddUser($fromUser);
                    $memo = $this->memoRewardsOff;
                } elseif (strpos($memo, self::COMMAND_REWARDS_ON) === 0) {
                    $this->getDBManager()->ratingUsersRewardsStopListRemoveUser($fromUser);
                    $memo = $this->memoRewardsOn;
                }

                $dataForReward = [
                    'author'  => $fromUser,
                    'rewards' => [$event['op'][1]['amount']],
                    'memo'    => $memo,
                ];
                $this->getDBManager()->ratingUsersRewardAddToQueue($dataForReward);

                echo PHP_EOL . date('Y-m-d H:i:s') . ' - user=' . $fromUser . ' set rating rewards to "' . $event['op'][1]['memo'] . '"';
            }
        }

        $eventsTotal = count($events);
        echo PHP_EOL . date('Y-m-d H:i:s') .  " GotTransferHandler handled {$eventsTotal} events";
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
                && $this->getDBManager()->eventsCountByListenerId($this->getId()) > 0
            );
    }
}