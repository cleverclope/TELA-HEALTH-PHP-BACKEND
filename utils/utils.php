<?php

    namespace tenna_health;

    use Exception;
    use PDO;

    define('current_time', date('Y-m-d H:i:s'));

    require_once 'env.php';

    function connect_database(): ?PDO {
        try {
            $options = [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'];
            $connection = new PDO("mysql:host=" . host . ";dbname=" . database . ";charset=utf8",
                username, password, $options);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $connection;
        } catch (Exception $error) {
            return NULL;
        }
    }

    function server_error(Exception $exception) {
        $message = [
            'error_trace' => $exception->getTraceAsString(),
            'trace_array' => [
                'array'  => $exception->getTrace(),
                'string' => $exception->getMessage()
            ],
            'time_logged' => date('Y-m-d H:i:s')
        ];

        //write_file(json_encode($message));
        //slack_notification($message);

        return server_response(400, $exception->getMessage(), $message);
    }

    function server_response(int $code, string $message, array $data = []) {
        return json_encode(array_merge(['msg' => $message, 'code' => $code], $data), JSON_NUMERIC_CHECK);
    }

    function get_bearer_token(): string {
        $headers = getallheaders();
        return isset($headers['Authorization']) ? trim(substr($headers['Authorization'], 7)) : "";
    }

    function init_path(string $path) {
        if (!is_dir("$path")) {
            mkdir("$path", 0755, true);
        }
    }
