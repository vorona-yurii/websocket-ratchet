<?php
/**
 * @link https://github.com/vorona-yurii/websocket-ratchet
 * @category library
 * @package vorona-yurii/websocket-ratchet
 * 
 * @author Yurii Vorona <voronayu1331@gmail.com>
 * @copyright Copyright (c) 2021
 */

namespace websocketRatchet;

use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class WebSocketServer implements MessageComponentInterface
{
    /**
     * @var int $port
     */
    public $port = 8080;

    /**
     * @var bool $closeConnectionOnError
     */
    protected $closeConnectionOnError = true;

    /**
     * @var bool $runMessageCommands
     */
    protected $runClientCommands = true;

    /**
     * @var IoServer|null $server
     */
    protected $server = null;

    /**
     * @var null|\SplObjectStorage $clients
     */
    protected $clients = null;

    /**
     * @return bool
     *
     */
    public function start()
    {
        try {
            $this->server = IoServer::factory(
                new HttpServer(
                    new WsServer(
                        $this
                    )
                ),
                $this->port
            );
            $this->clients = new \SplObjectStorage();
            $this->server->run();

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return void
     */
    public function stop()
    {
        $this->server->socket->shutdown();
    }

    /**
     * @param ConnectionInterface $conn
     */
    function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
    }

    /**
     * @param ConnectionInterface $conn
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
    }

    /**
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        if ($this->closeConnectionOnError) {
            $conn->close();
        }
    }

    /**
     * @param ConnectionInterface $from
     * @param string $msg
     */
    function onMessage(ConnectionInterface $from, $msg)
    {
        if ($this->runClientCommands) {
            $command = $this->getCommand($from, $msg);

            if ($command && method_exists($this, 'command' . ucfirst($command))) {
                call_user_func([$this, 'command' . ucfirst($command)], $from, $msg);
            }
        }
    }

    /**
     * @param ConnectionInterface $from
     * @param $msg
     * @return null|string - _NAME_ of command that implemented in class method command_NAME_()
     */
    protected function getCommand(ConnectionInterface $from, $msg)
    {
        return null;
    }
}
