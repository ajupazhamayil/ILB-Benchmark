<?php

require 'vendor/autoload.php';

use Google\Cloud\Spanner\SpannerClient;
use Google\Cloud\Spanner\Session\CacheSessionPool;
use Google\Auth\Cache\SysVCacheItemPool;


$instanceId = 'multi-region-db';
$databaseId = 'benchmark-test';
$tableName = 'test';
$n = 48;

$sessionCache = new SysVCacheItemPool([
    'proj' => 'A',
    'memsize' => 250000
]);
$sessionPool = new CacheSessionPool(
    $sessionCache,
    [
        'minSessions' => 50,
        'maxSessions' => 50  // Here it will create 10 sessions under the cover.
    ]
);

function childProcessRead($childId, $instanceId, $databaseId, $tableName, $sessionPool)
{ 
    $client = new SpannerClient();
    $db = $client->connect($instanceId, $databaseId, ['sessionPool' => $sessionPool]);
    // Make the gRPC channel.
    $res = $db->execute("SELECT id from $tableName where id = 1");
    foreach ($res as $row) {
        $i = $row;
    }

    $start = microtime(true);
    $db->runTransaction(function ($t) use ($childId, $tableName) {
        $res = $t->execute("SELECT id from $tableName where id = 1");
        foreach ($res as $row) {
            $i = $row;
        }
        $t->commit();
    });
    $totalTime = (microtime(true) - $start) * 1000;
    $myfile = fopen("output/$childId.txt", "w");
    fwrite($myfile, $totalTime);
    fclose($myfile);
}

function burstRead($instanceId, $databaseId, $tableName, $n, $sessionPool)    
{
    $starTime = microtime(true);

    // Create n child processes
    for ($i = 0; $i < $n; $i++) {
        $pid = pcntl_fork();

        if ($pid == -1) {
            die('Could not fork');
        } elseif ($pid) {
            // Parent process
            // echo "Forked child process $pid.\n";
        } else {
            // Child process
            childProcessRead($i + 1, $instanceId, $databaseId, $tableName, $sessionPool);
            exit; // Child process terminates
        }
    }

    // Parent process waits for all child processes to finish
    $numOfChildrens = 0;
    while (pcntl_waitpid(0, $status) !== -1) {
        // echo sprintf("Child process joined with status %s \n", pcntl_wexitstatus($status));
        $numOfChildrens++;
    }

    $endTime = microtime(true);

    // In seconds
    $totalTime = ($endTime - $starTime) * 1000;
    $fp = fopen('output.txt', 'ab');
    fputcsv($fp, ['Burst Read Write', $totalTime]);
    fclose($fp);

    echo "$numOfChildrens child processes joined.\n";
}

$client = new SpannerClient();
$db = $client->connect($instanceId, $databaseId, ['sessionPool' => $sessionPool]);
    $sessionPool->setDatabase($db);
$sessionPool->warmup();
burstRead($instanceId, $databaseId, $tableName, $n, $sessionPool);
$sessionPool->clear();
