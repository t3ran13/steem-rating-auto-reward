<?php


namespace MyApp\Db;


class RedisManager extends \GolosPhpEventListener\app\db\RedisManager
{
    /**
     * @param array $data
     *
     * @return int the length of the list after the push operation.
     */
    public function ratingPostRewardAddToQueue($data)
    {
        $status = $this->connect->rPush("myapp:rewards-post-list", json_encode($data));

        return $status;
    }

    /**
     * @return int
     */
    public function ratingPostRewardGetQueueLength()
    {
        return $this->connect->llen("myapp:rewards-post-list");
    }

    /**
     * @return bool|array as key => json, if nothing return false
     */
    public function ratingPostRewardGetFirstFromQueue()
    {
        $json = $this->connect->lIndex("myapp:rewards-post-list", 0);
        return $json === null ? false : json_decode($json, true);
    }

    /**
     * @return bool
     */
    public function ratingPostRewardRemoveFirstFromQueue()
    {
        return $this->connect->lPop("myapp:rewards-post-list") === false ? false : true;
    }

    /**
     * @param array $data
     *
     * @return int the length of the list after the push operation.
     */
    public function ratingUsersRewardAddToQueue($data)
    {
        $status = $this->connect->rPush("myapp:rewards-users-list", json_encode($data));

        return $status;
    }
}