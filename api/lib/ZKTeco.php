<?php
/**
 * ZKTeco Device Communication Library
 * 
 * Communicates with ZKTeco fingerprint/face devices via UDP protocol.
 * Compatible with: K40, K50, U160, U360, iClock, UA760, X628, etc.
 * 
 * Default device port: 4370
 */

class ZKTeco {

    // Protocol commands
    const CMD_CONNECT      = 1000;
    const CMD_EXIT         = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE= 1003;
    const CMD_RESTART      = 1004;
    const CMD_POWEROFF     = 1005;
    const CMD_ACK_OK       = 2000;
    const CMD_ACK_ERROR    = 2001;
    const CMD_ACK_DATA     = 2002;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA         = 1501;
    const CMD_ATTLOG_RRQ   = 13;   // Get attendance logs
    const CMD_CLEAR_ATTLOG = 14;   // Clear attendance logs
    const CMD_USER_WRQ     = 8;    // Set user info
    const CMD_USERTEMP_RRQ = 9;    // Get user templates
    const CMD_USER_RRQ     = 9;    // Get users
    const CMD_VERSION      = 1100;
    const CMD_DEVICE_NAME  = 11;
    const CMD_GET_TIME     = 201;
    const CMD_SET_TIME     = 202;
    const CMD_SERIAL_NUMBER= 1101;

    // Connection
    private $ip;
    private $port;
    private $socket;
    private $session_id = 0;
    private $reply_id   = -1;
    private $timeout;
    private $connected  = false;

    public $error_message = '';

    /**
     * @param string $ip      Device IP address
     * @param int    $port    Device port (default 4370)
     * @param int    $timeout Socket timeout in seconds
     */
    public function __construct($ip, $port = 4370, $timeout = 5) {
        $this->ip      = $ip;
        $this->port    = $port;
        $this->timeout = $timeout;
    }

    /**
     * Connect to the ZKTeco device
     */
    public function connect() {
        $this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if (!$this->socket) {
            $this->error_message = "Failed to create socket: " . socket_strerror(socket_last_error());
            return false;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $command = $this->createHeader(self::CMD_CONNECT, chr(0).chr(0));
        $response = $this->send($command);

        if ($response && strlen($response) >= 8) {
            $this->session_id = unpack('v', substr($response, 4, 2))[1];
            $reply = unpack('v', substr($response, 0, 2))[1];

            if ($reply == self::CMD_ACK_OK) {
                $this->connected = true;
                $this->reply_id = unpack('v', substr($response, 6, 2))[1];
                return true;
            }
        }

        $this->error_message = "Connection failed — check device IP ({$this->ip}) and port ({$this->port})";
        return false;
    }

    /**
     * Disconnect from device
     */
    public function disconnect() {
        if ($this->connected) {
            $command = $this->createHeader(self::CMD_EXIT);
            $this->send($command);
            @socket_close($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Check if connected
     */
    public function isConnected() {
        return $this->connected;
    }

    /**
     * Get device version
     */
    public function getVersion() {
        $command = $this->createHeader(self::CMD_VERSION);
        $response = $this->send($command);

        if ($response && strlen($response) > 8) {
            return trim(substr($response, 8));
        }
        return false;
    }

    /**
     * Get device serial number
     */
    public function getSerialNumber() {
        $command = $this->createHeader(self::CMD_SERIAL_NUMBER);
        $response = $this->send($command);

        if ($response && strlen($response) > 8) {
            return trim(substr($response, 8));
        }
        return false;
    }

    /**
     * Get device name/platform
     */
    public function getDeviceName() {
        $command = $this->createHeader(self::CMD_DEVICE_NAME, chr(0));
        $response = $this->send($command);

        if ($response && strlen($response) > 8) {
            return trim(substr($response, 8));
        }
        return "ZKTeco Device";
    }

    /**
     * Get the device time
     */
    public function getTime() {
        $command = $this->createHeader(self::CMD_GET_TIME);
        $response = $this->send($command);

        if ($response && strlen($response) >= 12) {
            $timestamp = unpack('V', substr($response, 8, 4))[1];
            return $this->decodeTime($timestamp);
        }
        return false;
    }

    /**
     * Get attendance log records from device
     * 
     * Returns array of records:
     * [
     *   ['uid' => int, 'id' => string, 'state' => int, 'timestamp' => string],
     *   ...
     * ]
     * 
     * state: 0=Check-In, 1=Check-Out, 2=Break-Out, 3=Break-In, 4=OT-In, 5=OT-Out
     */
    public function getAttendance() {
        $command = $this->createHeader(self::CMD_ATTLOG_RRQ);
        $response = $this->send($command);

        if (!$response) {
            $this->error_message = "No response when requesting attendance logs";
            return false;
        }

        $records = [];
        $cmd = unpack('v', substr($response, 0, 2))[1];

        if ($cmd == self::CMD_PREPARE_DATA) {
            // Large data — need to receive in chunks
            $size = unpack('V', substr($response, 8, 4))[1];
            $data = $this->receiveChunkedData($size);
        } elseif ($cmd == self::CMD_ACK_OK || $cmd == self::CMD_ACK_DATA) {
            if (strlen($response) <= 8) {
                return []; // No records
            }
            $data = substr($response, 8);
        } else {
            $this->error_message = "Unexpected response command: $cmd";
            return false;
        }

        if (empty($data)) {
            return [];
        }

        $records = $this->parseAttendanceData($data);
        return $records;
    }

    /**
     * Get registered users from device
     */
    public function getUsers() {
        $command = $this->createHeader(self::CMD_USER_RRQ);
        $response = $this->send($command);

        if (!$response) {
            $this->error_message = "No response when requesting user list";
            return false;
        }

        $users = [];
        $cmd = unpack('v', substr($response, 0, 2))[1];

        if ($cmd == self::CMD_PREPARE_DATA) {
            $size = unpack('V', substr($response, 8, 4))[1];
            $data = $this->receiveChunkedData($size);
        } elseif ($cmd == self::CMD_ACK_OK || $cmd == self::CMD_ACK_DATA) {
            if (strlen($response) <= 8) {
                return [];
            }
            $data = substr($response, 8);
        } else {
            return false;
        }

        if (empty($data)) {
            return [];
        }

        return $this->parseUserData($data);
    }

    /**
     * Clear all attendance logs from device
     */
    public function clearAttendance() {
        $command = $this->createHeader(self::CMD_CLEAR_ATTLOG);
        $response = $this->send($command);

        if ($response) {
            $cmd = unpack('v', substr($response, 0, 2))[1];
            return ($cmd == self::CMD_ACK_OK);
        }
        return false;
    }

    /**
     * Enable the device (unlock after disable)
     */
    public function enableDevice() {
        $command = $this->createHeader(self::CMD_ENABLEDEVICE);
        $response = $this->send($command);

        if ($response) {
            $cmd = unpack('v', substr($response, 0, 2))[1];
            return ($cmd == self::CMD_ACK_OK);
        }
        return false;
    }

    /**
     * Disable the device (freeze screen)
     */
    public function disableDevice() {
        $command = $this->createHeader(self::CMD_DISABLEDEVICE);
        $response = $this->send($command);

        if ($response) {
            $cmd = unpack('v', substr($response, 0, 2))[1];
            return ($cmd == self::CMD_ACK_OK);
        }
        return false;
    }

    /**
     * Restart the device
     */
    public function restart() {
        $command = $this->createHeader(self::CMD_RESTART);
        $this->send($command);
        $this->connected = false;
        @socket_close($this->socket);
    }

    // ===================================================================
    // PRIVATE METHODS
    // ===================================================================

    /**
     * Create protocol header
     */
    private function createHeader($command, $data = '') {
        $buf = pack('SSSS', $command, 0, $this->session_id, $this->reply_id);
        $buf .= $data;

        // Calculate checksum
        $checksum = $this->calculateChecksum($buf);
        $reply_id = ++$this->reply_id;

        // Rebuild with checksum and reply_id
        $buf = pack('SSSS', $command, $checksum, $this->session_id, $reply_id);
        $buf .= $data;

        // Recalculate checksum
        $buf = substr_replace($buf, pack('S', 0), 2, 2);
        $checksum = $this->calculateChecksum($buf);
        $buf = substr_replace($buf, pack('S', $checksum), 2, 2);

        return $buf;
    }

    /**
     * Calculate packet checksum
     */
    private function calculateChecksum($data) {
        $checksum = 0;
        $len = strlen($data);

        for ($i = 0; $i < $len; $i += 2) {
            if ($i == $len - 1) {
                $checksum += ord($data[$i]);
            } else {
                $checksum += unpack('v', substr($data, $i, 2))[1];
            }
        }

        $checksum = ($checksum % 65536);
        $checksum = 65536 - $checksum;
        $checksum -= 1;

        if ($checksum < 0) {
            $checksum += 65536;
        }

        return $checksum & 0xFFFF;
    }

    /**
     * Send data to device and receive response
     */
    private function send($command) {
        $sent = @socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);

        if ($sent === false) {
            $this->error_message = "Failed to send: " . socket_strerror(socket_last_error($this->socket));
            return false;
        }

        $response = '';
        $from = '';
        $port = 0;
        $bytes = @socket_recvfrom($this->socket, $response, 65535, 0, $from, $port);

        if ($bytes === false || $bytes === 0) {
            $this->error_message = "No response from device (timeout or unreachable)";
            return false;
        }

        return $response;
    }

    /**
     * Receive chunked data for large responses
     */
    private function receiveChunkedData($totalSize) {
        $data = '';
        $received = 0;

        while ($received < $totalSize) {
            $response = '';
            $from = '';
            $port = 0;
            $bytes = @socket_recvfrom($this->socket, $response, 65535, 0, $from, $port);

            if ($bytes === false || $bytes === 0) {
                break;
            }

            $cmd = unpack('v', substr($response, 0, 2))[1];

            if ($cmd == self::CMD_DATA) {
                $chunk = substr($response, 8);
                $data .= $chunk;
                $received += strlen($chunk);
            } elseif ($cmd == self::CMD_ACK_OK) {
                break;
            }
        }

        // Send free data confirmation
        $confirm = $this->createHeader(self::CMD_ACK_OK);
        @socket_sendto($this->socket, $confirm, strlen($confirm), 0, $this->ip, $this->port);

        return $data;
    }

    /**
     * Parse raw attendance log data
     */
    private function parseAttendanceData($data) {
        $records = [];
        $len = strlen($data);

        // Attendance record size varies: 40 bytes (newer) or 16 bytes (older)
        $recordSize = ($len > 40 && $len % 40 == 0) ? 40 : 16;

        // Try to detect record size
        if ($len >= 40) {
            $recordSize = 40;
        }

        for ($i = 0; $i < $len; $i += $recordSize) {
            $record = substr($data, $i, $recordSize);
            if (strlen($record) < $recordSize) break;

            if ($recordSize == 40) {
                // New format (40 bytes)
                $uid   = unpack('v', substr($record, 0, 2))[1];
                $id    = trim(substr($record, 2, 9), "\x00 ");
                $state = ord($record[26]);
                $time  = unpack('V', substr($record, 27, 4))[1];
            } else {
                // Old format (16 bytes)
                $uid   = unpack('v', substr($record, 0, 2))[1];
                $id    = trim(substr($record, 2, 5), "\x00 ");
                $state = ord($record[10]);
                $time  = unpack('V', substr($record, 12, 4))[1];
            }

            if (!empty($id) && $time > 0) {
                $records[] = [
                    'uid'       => $uid,
                    'id'        => $id,          // Matches staff.fingerprint_id
                    'state'     => $state,        // 0=In, 1=Out
                    'timestamp' => $this->decodeTime($time),
                    'raw_time'  => $time
                ];
            }
        }

        return $records;
    }

    /**
     * Parse raw user data
     */
    private function parseUserData($data) {
        $users = [];
        $len = strlen($data);
        $recordSize = 72; // Standard user record size

        if ($len < $recordSize) {
            // Try 28-byte records (older devices)
            $recordSize = 28;
        }

        for ($i = 0; $i < $len; $i += $recordSize) {
            $record = substr($data, $i, $recordSize);
            if (strlen($record) < $recordSize) break;

            if ($recordSize == 72) {
                $uid      = unpack('v', substr($record, 0, 2))[1];
                $role     = ord($record[2]);
                $name     = trim(substr($record, 11, 24), "\x00 ");
                $user_id  = trim(substr($record, 3, 8), "\x00 ");
            } else {
                $uid      = unpack('v', substr($record, 0, 2))[1];
                $role     = ord($record[2]);
                $name     = trim(substr($record, 8, 20), "\x00 ");
                $user_id  = trim(substr($record, 3, 5), "\x00 ");
            }

            if (!empty($user_id)) {
                $users[] = [
                    'uid'     => $uid,
                    'user_id' => $user_id,
                    'name'    => $name,
                    'role'    => $role  // 0=User, 14=Admin
                ];
            }
        }

        return $users;
    }

    /**
     * Decode ZKTeco timestamp to datetime string
     */
    private function decodeTime($t) {
        $second = $t % 60;
        $t = ($t - $second) / 60;
        $minute = $t % 60;
        $t = ($t - $minute) / 60;
        $hour = $t % 24;
        $t = ($t - $hour) / 24;
        $day = ($t % 31) + 1;
        $t = ($t - ($day - 1)) / 31;
        $month = ($t % 12) + 1;
        $t = ($t - ($month - 1)) / 12;
        $year = $t + 2000;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
