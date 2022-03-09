<?php

namespace App\Controllers\Cli;

use App\Core\CliController;
use App\Core\DB;
use App\Events\EventInterface;
use Dibi\Exception;

class EventServer extends CliController
{

	/** @var resource[] */
	private array $clients = [];

	public function start() : void {
		$this->echo('Starting server', 'info');
		$null = null;
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($sock, '0.0.0.0', EVENT_PORT);
		socket_listen($sock);
		socket_set_block($sock);

		do {
			$newSockets = $this->clients;
			$newSockets[] = $sock;

			// The socket select function will check for incoming messages and connections
			// It will also serve as an interval timer for DB polling
			socket_select($newSockets, $null, $null, 1);

			// If the main socket received a new connect message -> open a new client socket
			if (in_array($sock, $newSockets, true)) {
				$client = socket_accept($sock);
				//socket_set_nonblock($client);
				socket_getpeername($client, $clientIP);
				$this->echo('Client connected.', $clientIP);

				// Send WebSocket handshake headers.
				$request = @socket_read($client, 10000);
				if ($request === false) {
					$this->echo("socket_read() failed; reason: ".socket_strerror(socket_last_error($client)), 'error');
				}
				else {
					preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
					$key = base64_encode(pack(
																 'H*',
																 sha1(($matches[1] ?? '').'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
															 ));
					$headers = "HTTP/1.1 101 Switching Protocols\r\n";
					$headers .= "Upgrade: websocket\r\n";
					$headers .= "Connection: Upgrade\r\n";
					$headers .= "Sec-WebSocket-Version: 13\r\n";
					$headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
					socket_write($client, $headers, strlen($headers));
					$this->clients[] = $client;
				}
			}

			// Read from all sockets that sent a message
			foreach ($newSockets as $readClient) {
				if ($readClient === $sock) {
					continue;
				}
				$this->clientRead($readClient);
			}

			// Db polling
			$this->sentUnsentMessages();
		} while (true);
	}

	public function echo(string $message, string $clientIP = '') : void {
		echo date('[Y-m-d H:i:s]').' '.$clientIP.' '.trim($message).PHP_EOL;
	}

	/**
	 * Wait for messages to
	 *
	 * @param resource $client
	 *
	 * @return void
	 */
	private function clientRead($client) : void {
		socket_getpeername($client, $clientIP);
		if (socket_recv($client, $socketData, 1024, 0) >= 1) {
			$message = $this->unseal($socketData);
			$validUTF8 = mb_check_encoding($message, 'UTF-8');
			if ($validUTF8) {
				$this->echo($message, $clientIP);
				$this->broadcast($message);
			}
		}

		$test = [$client];
		$null = null;
		socket_select($test, $null, $null, 0, 10);
		if (count($test) > 0) {
			$socketData = @socket_read($client, 1024, PHP_NORMAL_READ);
			if ($socketData === false) {
				$this->echo('Client disconnected.', $clientIP);
				$key = array_search($client, $this->clients, true);
				if (isset($this->clients[$key])) {
					unset($this->clients[$key]);
				}
				socket_close($client);
			}
		}
	}

	public function unseal(string $socketData) : string {
		$length = ord($socketData[1]) & 127;
		if ($length === 126) {
			$masks = substr($socketData, 4, 4);
			$data = substr($socketData, 8);
		}
		elseif ($length === 127) {
			$masks = substr($socketData, 10, 4);
			$data = substr($socketData, 14);
		}
		else {
			$masks = substr($socketData, 2, 4);
			$data = substr($socketData, 6);
		}
		$socketData = "";
		$len = strlen($data);
		for ($i = 0; $i < $len; ++$i) {
			$socketData .= $data[$i] ^ $masks[$i % 4];
		}
		return $socketData;
	}

	/**
	 * Send a message to all listening clients
	 *
	 * @param EventInterface $event
	 *
	 * @return void
	 */
	public function broadcast(string $message) : void {
		foreach ($this->clients as $key => $client) {
			// Send a message to socket
			$this->sendTo($client, $message);
		}
	}

	public function sendTo($client, string $message) : bool {
		return socket_write($client, chr(129).chr(strlen($message)).$message) > 0;
	}

	/**
	 * @return void
	 */
	private function sentUnsentMessages() : void {
		$events = DB::select('events', '*')->where('sent = 0')->orderBy('datetime')->desc()->fetchAll();
		$ids = [];
		foreach ($events as $event) {
			$this->echo($event->message, 'event');
			$this->broadcast($event->message);
			$ids[] = $event->id_event;
		}
		if (!empty($ids)) {
			try {
				DB::update('events', ['sent' => 1], ['id_event IN %in', $ids]);
			} catch (Exception $e) {
				$this->echo($e->getMessage(), 'error');
			}
		}
	}

	public function seal(string $socketData) : string {
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($socketData);

		$header = '';
		if ($length <= 125) {
			$header = pack('CC', $b1, $length);
		}
		elseif ($length < 65536) {
			$header = pack('CCn', $b1, 126, $length);
		}
		else {
			$header = pack('CCNN', $b1, 127, $length);
		}
		return $header.$socketData;
	}

}