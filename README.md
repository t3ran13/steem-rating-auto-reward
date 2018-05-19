# steem-rating-auto-reward
Auto-reward of users which got to The Alternative Steem Top based on t3ran13/golos-php-event-listener

When post with The Alternative Steem Top get reward then the app get part of the reward and send it to users from post. It is 80% SBD/STEEM by default.

## Basic Usage

- install docker and docker-compose
- in MyApp\Processes\RatingRewardUsersQueueMakerProcess set $rewardUserPercent. It is percent from post reward which will be sent to users from post.
- copy file env.env to .env, open it and fill fields
  - REDIS_PSWD - password for you redis database
  - REWARD_POOL_NAME - user which will pay to users
  - REWARD_POOL_WIF - active key of user which will pay to users
  - LAST_BLOCK - start blockchain block, used for the first run application
- copy file docker-compose.yml.example to docker-compose.yml
- run docker `sudo docker-compose up -d`

Application logs are placed to var/log/cron/cron-auto-reward.log

## Application Capabilities

Each user can on/off rewards for itself. For it user have to make transfer to REWARD_POOL_NAME with memo "on"/"off". The application executes commands and sent back tokens with current status.

## RedisManager

DB structure MyApp:
- DB0
    - myapp:rewards-post-list //list of json ( there are 'author', 'permlink', 'sbd_payout', 'steem_payout' fields)
    - myapp:rewards-users-list //list of json ( there are 'author', 'rewards', 'memo' fields)
    - myapp:rewards-users-stop-list //list of users names

DB structure t3ran13/golos-php-event-listener:
- DB0
    - app:processes:last_id
    - app:processes:{id}:last_update_datetime
    - app:processes:{id}:status
    - app:processes:{id}:mode
    - app:processes:{id}:pid
    - app:processes:{id}:handler
    - app:processes:{id}:data:last_block
    
    - app:listeners:last_id
    - app:listeners:{id}:last_update_datetime
    - app:listeners:{id}:status
    - app:listeners:{id}:mode
    - app:listeners:{id}:pid
    - app:listeners:{id}:handler
    - app:listeners:{id}:data:last_block
    - app:listeners:{id}:conditions:{n}:key
    - app:listeners:{id}:conditions:{n}:value
    
    - app:events:{listener_id}:{block_n}:{trx_n_in_block}
    
    

### for run docker-compose for cron in win
```bash
$Env:COMPOSE_CONVERT_WINDOWS_PATHS=1
docker-compose down && Docker-compose up -d
```
    
    