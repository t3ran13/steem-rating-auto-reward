<?php



namespace MyApp;


use GolosPhpEventListener\app\AppConfig;
use GolosPhpEventListener\app\process\BlockchainExplorerProcess;
use GolosPhpEventListener\app\process\EventsHandlersProcess;
use GolosPhpEventListener\app\process\MainProcess;
use MyApp\Db\RedisManager;
use MyApp\Handlers\RatingGotRewardHandler;
use MyApp\Processes\RatingRewardUsersQueueMakerProcess;


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define('PATH', __DIR__);
require __DIR__ . "/Autoloader.php"; // only in GrapheneNodeClient project
require __DIR__ . '/vendor/autoload.php';

echo PHP_EOL . '------ StartApp.php ------' . PHP_EOL;

$appConfig = new AppConfig();
$appConfig->addListener(['op:1:author' => 't3ran13', 'op:0' => 'author_reward'], new RatingGotRewardHandler());

$mainProcess = new MainProcess(
    $appConfig,
    New RedisManager()
);

$className = get_class(New RedisManager());
$blockchainExplorerProcess = new BlockchainExplorerProcess($className);
$blockchainExplorerProcess->setLastBlock(16146490);

$mainProcess->processesList = [
    $blockchainExplorerProcess,
    new EventsHandlersProcess($className),
    new RatingRewardUsersQueueMakerProcess()
];
$mainProcess->start();

echo PHP_EOL . PHP_EOL;