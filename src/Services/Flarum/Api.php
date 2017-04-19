<?php namespace Flaportum\Services\Flarum;

use Flagrow\Flarum\Api\Flarum;

class Api
{
    protected $hostname;

    protected $instances = [];

    public function __construct($hostname, $token)
    {
        $this->hostname = $hostname;

        $this->token = $token;
    }

    public function instance($userId = 1)
    {
        if (!array_key_exists($userId, $this->instances)) {
            $this->instances[$userId] = new Flarum($this->hostname, [
                'token' => "Token {$this->token}; userId={$userId}"
            ]);
        }

        return $this->instances[$userId];
    }
}
