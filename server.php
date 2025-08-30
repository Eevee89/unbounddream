<?php
require __DIR__.'/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Factory;
use React\Socket\SecureServer;
use React\Socket\TcpServer;

$env = parse_ini_file(".env");
$loop = Factory::create();
$port = '8888';
$tcp = new TcpServer('127.0.0.1:'.$port, $loop);

$secureTcp = new SecureServer($tcp, $loop, [
    'local_cert' => $env["SSL_CERT"],
    'local_pk' => $env["SSL_KEY"],
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => false
]);

$logFile = '/var/log/websocket/photoroulette.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] | " . $message . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

class ServerImpl implements MessageComponentInterface {
    protected $clients;
    protected $images = [];
    protected $countImg = 0;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send("Hello");
        logMessage("New connection! ({$conn->resourceId})");
    }

    public function onMessage(ConnectionInterface $conn, $raw) {
        $msg = json_decode($raw, true);

        if ($msg["type"] === "UPIMG") {
            logMessage(sprintf("New image message from '%s': length %s", $conn->resourceId, strlen($msg["payload"])));
        } else {
            logMessage(sprintf("New message from '%s': %s", $conn->resourceId, $raw));
        }

        if ($msg["type"] == "UPIMG") {
            $fileData = $msg["payload"];
            $images[$counting] = $fileData;

            $conn->send(json_encode([
                "type" => "IMGUP",
                "payload" => $counting++
            ]));
        }

        if ($msg["type"] == "DOWNIMG") {
            $id = $msg["payload"];
            $fileData = $images[$id];

            $conn->send(json_encode([
                "type" => "IMGDOWN",
                "payload" => $fileData
            ]));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        logMessage("Connection {$conn->resourceId} is gone");
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        logMessage("An error occured on connection {$conn->resourceId}: {$e->getMessage()}");
        $conn->close();
    }
}

$server = new IoServer(
    new HttpServer(
        new WsServer(
            new ServerImpl()
        )
    ),
    $secureTcp,
    $loop
);
echo "Server created on port $port\n\n";
$server->run();