<?php
namespace geocoucou\redlockLaravel;

use Illuminate\Support\Facades\Redis;

class RedLock
{
    private $retryDelay;
    private $retryCount;
    private $clockDriftFactor = 0.01;

    function __construct($retryDelay = 200, $retryCount = 100)
    {
        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;
    }

    public function lock($resource, $ttl)
    {
        $token = uniqid();
        $retry = $this->retryCount;

        do {
            $isLock = false;

            if ( Redis::set($resource, $token, 'EX', $ttl, 'NX')) {
                $isLock = true;
            }

            if ($isLock ) {
                return [
                    'resource' => $resource,
                    'token'    => $token,
                ];
            }

            // Wait a random delay before to retry
            $delay = mt_rand(floor($this->retryDelay / 2), $this->retryDelay);
            usleep($delay * 1000);

            $retry--;

        } while ($retry > 0);

        return false;
    }

    public function unlock(array $lock)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';
        return Redis::eval($script, 1, $lock['resource'], $lock['token'], 1);

    }
}
