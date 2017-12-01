<?php
namespace app\push\controller;
use think\worker\Server;

class Worker extends Server {
    protected $socket = 'websocket://127.0.0.1:2346';
    public $onlineUidToConnectionMap = array();
//    public $notdeliveredmessages = array();
    const NOT_DELIVER_UID_SET_REDIS = 'notdeliveredto';
    public $client;

    public function onMessage($connection, $data) {
        $msg = json_decode($data, true);
        $uid = intval($msg['uid']);
        switch($msg['type']) {
            case 'login':
                $this->onlineUidToConnectionMap[$uid] = $connection->id;
                echo "uid to connection mapping: ";
                var_dump($this->onlineUidToConnectionMap);
//                // use array for notdeliveredmsg
//                if (array_key_exists($uid, $this->notdeliveredmessages)) {
//                    foreach ($this->notdeliveredmessages[$uid] as $msgtext) {
//                        $connection->send($msgtext);
//                    }
//                    unset($this->notdeliveredmessages[$uid]);
//                }

                // redis implementation
                if ($this->client->sismember(self::NOT_DELIVER_UID_SET_REDIS, $uid)) {
                    $messages = $this->client->lrange($uid, 0, -1);
                    foreach ($messages as $msg) {
                        $connection->send($msg);
                    }
                    $this->client->ltrim($uid, 1, 0);  // delete the whole list
                    $this->client->srem(self::NOT_DELIVER_UID_SET_REDIS, $uid);
                }
                break;
            case 'logout':
                echo "$uid logged out\n";
                unset($this->onlineUidToConnectionMap[$uid]);
                echo "remaining uid to connection mapping: ";
                var_dump($this->onlineUidToConnectionMap);
                break;
            case 'say':
                $to = intval($msg['sendtouid']);
                echo "msg from " . $uid . " to " . $to .": " . $msg['msgtext'] ."\n";
                if (array_key_exists($to, $this->onlineUidToConnectionMap)) {
                    $this->worker->connections[$this->onlineUidToConnectionMap[$to]]->send("msg from " .$uid . ": " .$msg['msgtext']);
                } else {
                    echo $to . " offline\n";
//                    // notdeliveredmsg array
//                    if (!array_key_exists($to, $this->notdeliveredmessages)) {
//                        $this->notdeliveredmessages[$to] = array();
//                    }
//                    array_push($this->notdeliveredmessages[$to], $msg['msgtext']);
//                    var_dump($this->notdeliveredmessages);

                    // redis implementation
                    $this->client->rpush($to, $msg['msgtext']);
                    $this->client->sadd(self::NOT_DELIVER_UID_SET_REDIS, $to);

                    echo "messages not yet delivered to " . $to;
                    var_dump($this->client->lrange($to, 0, -1));
                    echo "whose messages are not delivered yet: ";
                    var_dump($this->client->smembers(self::NOT_DELIVER_UID_SET_REDIS));
                }
                break;
        };

    }

    public function onConnect($connection) {
        echo "onConnect $connection->id \n";
    }

    public function onClose($connection) {
        echo "onClose\n";
        $uid = array_search($connection->id, $this->onlineUidToConnectionMap);
        unset($this->onlineUidToConnectionMap[$uid]);
    }

    public function onError($connection, $code, $msg) {
        echo "error $code $msg\n";
    }

    public function onWorkerStart($worker) {
        echo "onWorkerStart\n";
        $this->client = new \Predis\Client(array(
            'host' => '127.0.0.1',
            'port' => 6379
        ));
        $this->client->connect();
    }

    public function onWorkerStop() {
        echo "onWorkerStop\n";
        $this->client->disconnect();
    }
}