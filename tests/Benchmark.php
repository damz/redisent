<?php
$bench = isset($argv[1])? $argv[1]: null;
$fast_iterations = isset($argv[2])? $argv[2]: 5000;
$slow_iterations = isset($argv[3])? $argv[3]: 2500;
$repeat = isset($argv[4])? $argv[4]: 3;
$verbose = isset($argv[5])? true: false;

$totalexecutiontime=0;

$keyscommand="keys";
$deletecommand="del";
$mgetkeys =  array('1', 'foo');

switch ($bench){
    case "redisent":
        require '../redisent.php';
        $redis = new Redisent('localhost',6379);    
        $mgetkeys="1 foo";
    break;
    case "redisentwrap":
        require '../redisent.php';
        $redis = new RedisentWrap('localhost',6379,true);    
        $keyscommand="getkeys";
        $deletecommand="delete";
    break;
    case "phpredis":
        $redis = new Redis();
        $redis->connect('localhost',6379);
        $keyscommand="getkeys";
        $deletecommand="delete";
    break;
    case "predis":
        include '../../predis/lib/Predis.php';
        $single_server = array(
            'host'     => '127.0.0.1', 
            'port'     => 6379, 
            'database' => 2
        );
        $redis = new Predis\Client($single_server);
        $mgetkeys =  array('1', 'foo');

    break;
    case "rediska":
        echo "Not yet supported.. the interface is really different...\n";    
    /*
    include '../library/Rediska.php';
    $redis = new Rediska(array(
        'servers' => array(
            array('host' => '127.0.0.1', 'port' => 6379),
        )
    ));
    */
    default:
        echo "\nusage:\nphp Benchmark.php redisent|redisentwrap|phpredis|predis numberoffastiterations numberofslowiterations repeatbench verbose\n";    
        echo "example:\nphp Benchmark.php redisent 5000 500 3 verbose\n";    
    exit;
    break;
}
for ($j = 1; $j <= $repeat; $j++){
    if ($verbose)    echo "\n--- Benchmark for $bench ---\n";
    $start_time = microtime(true);
    $redis->select(2);
    $redis->flushdb();
    if ($verbose) echo "Fast stuff\n";
    for ($i = 1; $i <= $fast_iterations; $i++){
        $redis->set($i, 'bar' .$i);
        $redis->set("foo", 'bar');
        $res = $redis->get('baz') ;
        $res = $redis->get('foo') ;
        $redis->$deletecommand('foo');
        $res = $redis->mget($mgetkeys);
    if ($verbose && !($i % 100)) echo ".";
    }
    $end_time_fast = microtime(true);

    if ($verbose) echo sprintf("\nFast stuff completed in %f seconds\n", $end_time_fast-$start_time);
    if ($verbose) echo "Slow stuff:\n";
    $redis->flushdb();
    for ($i = 1; $i <=  $slow_iterations; $i++){
        $redis->set($i, 'bar' .$i);
        $res = $redis->$keyscommand('*');
        $res = $redis->randomkey();
        if ($verbose && !($i % 10)) echo ".";
    }
    $end_time = microtime(true);
    if ($verbose) echo sprintf("\nSlow stuff completed in %f seconds\n", $end_time-$end_time_fast);
    $totalexecutiontime+=$end_time-$start_time;
    if ($verbose) echo sprintf("Tests completed in %f seconds\n", $end_time-$start_time);
    if ($verbose) echo sprintf("Memory Usage %s bytes\n", memory_get_peak_usage(true));
}
echo sprintf("-- Bottom Line for $bench: Tests completed in %f seconds in average, with %.2f mb memory usage\n", $totalexecutiontime/$j,memory_get_peak_usage(true)/1000000);