<?php
/**
 * helpers.php - minimal helpers for LifeLedger
 */

if (!function_exists('db_connect')) {
    function db_connect()
    {
        static $conn = null;
        if ($conn !== null) {
            return $conn;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $db   = getenv('DB_NAME') ?: 'lifeledger';

        $conn = new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }

        $conn->set_charset('utf8mb4');
        return $conn;
    }
}

/* -------- JSON INPUT -------- */
if (!function_exists('get_json_input')) {
    function get_json_input()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

/* -------- STANDARD RESPONSE -------- */
if (!function_exists('respond')) {
    function respond($success, $message, $data = null, $code = 200)
    {
        http_response_code($code);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }
}

/* -------- TOKEN GENERATOR (FIX) -------- */
if (!function_exists('generate_token')) {
    function generate_token()
    {
        return bin2hex(random_bytes(32)); // secure 64-char token
    }
}

/* -------- TOKEN VALIDATION -------- */
if (!function_exists('validate_token')) {
    function validate_token($conn)
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (empty($headers)) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        $token = null;
        if (!empty($headers['Authorization']) && preg_match('/Bearer\s+(\S+)/i', $headers['Authorization'], $m)) {
            $token = $m[1];
        }

        if (!$token) return false;

        // Use users.auth_token (this matches login.php)
        $stmt = $conn->prepare(
            "SELECT id, name, email
             FROM users
             WHERE auth_token = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();

        $res = $stmt->get_result();
        if ($res->num_rows === 1) {
            return $res->fetch_assoc();
        }

        return false;
    }
}

