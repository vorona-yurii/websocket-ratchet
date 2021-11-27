# [WebSocketServer](/WebSocketServer.php)

[![Latest Stable Version](https://poser.pugx.org/consik/yii2-websocket/v/stable)](https://packagist.org/packages/consik/yii2-websocket)
[![Total Downloads](https://poser.pugx.org/consik/yii2-websocket/downloads)](https://packagist.org/packages/consik/yii2-websocket)
[![License](https://poser.pugx.org/consik/yii2-websocket/license)](https://packagist.org/packages/consik/yii2-websocket)

Used [Ratchet](http://socketo.me/)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require consik/yii2-websocket
```

or add

```json
"vorona-yurii/websocket-ratcher": "dev-master"
```

## WebSocketServer class description

### Properties

1. ``` int $port = 8080``` - Port number for websocket server
2. ``` bool $closeConnectionOnError = true``` - Close connection or not when error occurs with it
3. ``` bool $runClientCommands = true``` - Check client's messages for commands or not
4. ``` null|IoServer $server = null``` - IOServer object
5. ``` null|\SplObjectStorage $clients = null``` - Storage of connected clients

### Methods

## Examples

### Simple echo server

Create your server class based on WebSocketServer. For example ```daemons\EchoServer.php```:

```php
<?php
namespace app\daemons;

use websocketRatchet\WebSocketServer;

class EchoServer extends WebSocketServer
{

    public function init($client, $message)
    {
        parent::init();
        $client->send( $message );
    }

}
```

Create yii2 console controller for starting server:

```php
<?php
namespace app\commands;

use app\daemons\EchoServer;
use yii\console\Controller;

class ServerController extends Controller
{
    public function actionStart($port = null)
    {
        $server = new EchoServer();
        if ($port) {
            $server->port = $port;
        }
        $server->start();
    }
}
```

Start your server using console:

> php yii server/start

Now let's check our server via js connection:

```javascript
var conn = new WebSocket('ws://localhost:8080');
    conn.onmessage = function(e) {
        console.log('Response:' + e.data);
    };
    conn.onopen = function(e) {
        console.log("Connection established!");
        console.log('Hey!');
        conn.send('Hey!');
    };
```

Console result must be:

> Connection established!

> Hey!

> Response:Hey!

### Handle server starting success and error events

Now we try handle socket binding error and open it on other port, when error occurs;

Create yii2 console controller for starting server:

```php
<?php
namespace app\commands;

use websocketRatchet\WebSocketServer;
use yii\console\Controller;

class ServerController extends Controller
{
    public function actionStart()
    {
        $server = new WebSocketServer();
        $server->port = 80; //This port must be busy by WebServer and we handle an error

        $server->start();
    }
}
```

Start your server using console command:

> php yii server/start

Server console result must be:

> Error opening port 80

> Server started at port 81

### Recieving client commands

You can implement methods that will be runned after some of user messages automatically;

Server class ```daemons\CommandsServer.php```:

```php
<?php
namespace app\daemons;

use websocketRatchet\WebSocketServer;
use Ratchet\ConnectionInterface;

class CommandsServer extends WebSocketServer
{

    /**
     * override method getCommand( ... )
     *
     * For example, we think that all user's message is a command
     */
    protected function getCommand(ConnectionInterface $from, $msg)
    {
        return $msg;
    }

    /**
     * Implement command's method using "command" as prefix for method name
     *
     * method for user's command "ping"
     */
    function commandPing(ConnectionInterface $client, $msg)
    {
        $client->send('Pong');
    }

}
```

Run the server like in examples above

Check connection and command working by js script:

```javascript
    var conn = new WebSocket('ws://localhost:8080');
    conn.onmessage = function(e) {
        console.log('Response:' + e.data);
    };
    conn.onopen = function(e) {
        console.log('ping');
        conn.send('ping');
    };
```

Console result must be:

> ping

> Response:Pong

### Chat example

In the end let's make simple chat with sending messages and function to change username;

Code without comments, try to understand it by youself ;)

* Server class ```daemons\ChatServer.php```:

```php
<?php
namespace app\daemons;

use websocketRatchet\WebSocketServer;
use Ratchet\ConnectionInterface;

class ChatServer extends WebSocketServer
{

    public function init($client)
    {
        parent::init();

        $client->name = null;
    }


    protected function getCommand(ConnectionInterface $from, $msg)
    {
        $request = json_decode($msg, true);
        return !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);
    }

    public function commandChat(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!$client->name) {
            $result['message'] = 'Set your name';
        } elseif (!empty($request['message']) && $message = trim($request['message']) ) {
            foreach ($this->clients as $chatClient) {
                $chatClient->send( json_encode([
                    'type' => 'chat',
                    'from' => $client->name,
                    'message' => $message
                ]) );
            }
        } else {
            $result['message'] = 'Enter message';
        }

        $client->send( json_encode($result) );
    }

    public function commandSetName(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => 'Username updated'];

        if (!empty($request['name']) && $name = trim($request['name'])) {
            $usernameFree = true;
            foreach ($this->clients as $chatClient) {
                if ($chatClient != $client && $chatClient->name == $name) {
                    $result['message'] = 'This name is used by other user';
                    $usernameFree = false;
                    break;
                }
            }

            if ($usernameFree) {
                $client->name = $name;
            }
        } else {
            $result['message'] = 'Invalid username';
        }

        $client->send( json_encode($result) );
    }

}
```

* Simple html form ```chat.html```:

```html
Username:<br />
<input id="username" type="text"><button id="btnSetUsername">Set username</button>

<div id="chat" style="width:400px; height: 250px; overflow: scroll;"></div>

Message:<br />
<input id="message" type="text"><button id="btnSend">Send</button>
<div id="response" style="color:#D00"></div>
```

* JS code for chat with [jQuery](https://jquery.com/):

```javascript
<script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script>
    $(function() {
        var chat = new WebSocket('ws://localhost:8080');
        chat.onmessage = function(e) {
            $('#response').text('');

            var response = JSON.parse(e.data);
            if (response.type && response.type == 'chat') {
                $('#chat').append('<div><b>' + response.from + '</b>: ' + response.message + '</div>');
                $('#chat').scrollTop = $('#chat').height;
            } else if (response.message) {
                $('#response').text(response.message);
            }
        };
        chat.onopen = function(e) {
            $('#response').text("Connection established! Please, set your username.");
        };
        $('#btnSend').click(function() {
            if ($('#message').val()) {
                chat.send( JSON.stringify({'action' : 'chat', 'message' : $('#message').val()}) );
            } else {
                alert('Enter the message')
            }
        })

        $('#btnSetUsername').click(function() {
            if ($('#username').val()) {
                chat.send( JSON.stringify({'action' : 'setName', 'name' : $('#username').val()}) );
            } else {
                alert('Enter username')
            }
        })
    })
</script>
```

Enjoy ;)

## Other 

### Starting yii2 console application as daemon using [nohup](https://en.wikipedia.org/wiki/Nohup)

```nohup php yii _ControllerName_/_ActionName_ &```
