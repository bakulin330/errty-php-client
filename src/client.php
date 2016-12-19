<?php

class ErrorCatcher
{

    private static
        $base_url = '',
        $api_key = '',
        $user_id = '',
        $user_params = array(),
        $show_connection_error = true,
        $client_name = 'php-client',
        $client_version = '0.1';

    /**
     * Setting start configuration
     *
     * @param $base_url - base url of server
     * @param $api_key - API key of your project
     * @param $user_id - internal user Id
     * @param $user_params - array of custom user params (for ex. email, tags etc)
     * @throws Exception
     */
    public static function config($base_url, $api_key, $user_id, $user_params, $show_connection_error = true)
    {
        self::$base_url = $base_url;
        self::$api_key = $api_key;
        self::$user_id = $user_id;
        self::$user_params = $user_params;
        self::$show_connection_error = $show_connection_error;

        if(empty(self::$base_url)){
            throw new \Exception('Error Catcher: empty $base_url param');
        }

        if(empty(self::$api_key)){
            throw new \Exception('Error Catcher: empty $api_key param');
        }

        self::$base_url = rtrim(self::$base_url, '/') . '/api/';
    }

    /**
     * Setting PHP error catcher (this action is not required)
     *
     */
    public static function setErrorHandler()
    {
        set_error_handler(array(__CLASS__, 'errorHandler'));
        register_shutdown_function(array(__CLASS__, 'fatalErrorCatcher'));
    }

    /**
     * Point for registration custom error
     *
     * @param null $ex - exception object
     * @param null $custom_error_id - custom error Id
     * @param null $custom_params - array of custom error params (for ex. tags, time etc)
     * @return bool
     */
    public static function registerError($ex = null, $custom_error_id = null, $custom_params = null)
    {
        if (empty($ex)) {
            return false;
        }

        self::sendError($ex->getMessage(), $ex->getCode(), $ex->getFile(), $ex->getLine(), $ex->getTrace(), $custom_error_id, $custom_params);

        return true;
    }

    /**
     * Callback for set_error_handler function
     *
     * @param $error_number
     * @param $error_message
     * @param $error_file
     * @param $error_line
     * @return bool
     */
    public static function errorHandler($error_number, $error_message, $error_file, $error_line)
    {
        if (__FILE__ != $error_file) {
            self::sendError($error_message, $error_number, $error_file, $error_line);
        }

        return false;
    }

    /**
     * Callback for register_shutdown_function function
     *
     * @return bool
     */
    public static function fatalErrorCatcher()
    {
        $error = error_get_last();
        if (!$error || !isset($error['type']) || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return false;
        }

        self::sendError($error['message'], $error['type'], $error['file'], $error['line']);

        return true;
    }

    /**
     * Formatting and sending error information to remote server
     *
     * @param $message - error message
     * @param $code - PHP error code (like 'E_NOTICE')
     * @param $file - file name of error
     * @param $line - line of error
     * @param null $trace - stack trace
     * @param null $custom_error_id
     * @param null $custom_params
     */
    private static function sendError($message, $code, $file, $line, $trace = null, $custom_error_id = null, $custom_params = null)
    {
        // Message
        if (empty($message)) {
            $message = '';
        }

        if (!is_string($message)) {
            $message = (string)$message;
        }

        if (mb_strlen($message) > 2048) {
            $message = mb_substr($message, 0, 2048);
        }

        // Code
        $code = self::formatErrorCode($code);

        // File
        if (empty($file)) {
            $file = '';
        }

        if (!is_string($file)) {
            $file = (string)$file;
        }

        // Line
        $line = (int)$line;
        if ($line < 1) {
            $line = 1;
        }

        // Stack Trace
        $trace = self::getStackTrace($trace, $file, $line);

        $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === false ? 'http' : 'https';
        $host = self::getEnvValue($_SERVER, 'HTTP_HOST');
        $script = self::getEnvValue($_SERVER, 'SCRIPT_NAME');
        $url = $protocol . '://' . $host . $script;

        $query_string = self::getGlobalVar('get');
        $post = self::getGlobalVar('post');
        $cookies = self::getGlobalVar('cookie');
        $session = self::getGlobalVar('session');
        $files = self::getGlobalVar('files');
        $client_headers = self::getClientHeaders();

        $payload = array(
            'api_key' => self::$api_key,

            // Error Catcher client version info
            'client' => array(
                'name' => self::$client_name,
                'version' => self::$client_version,
            ),

            'date' => date('D M d Y H:i:s O'),

            // Error details
            'error' => array(
                'message' => $message,
                'stack_trace' => $trace,
                'code' => $code,
            ),

            // Request details
            'request' => array(
                'url' => $url,
                'query_string' => $query_string,
                'http_method' => self::getEnvValue($_SERVER, 'REQUEST_METHOD'),
                'post' => $post,
                'session' => $session,
                'cookies' => $cookies,
                'files' => $files,
                'client_headers' => $client_headers,

                'headers' => array(
                    'user_agent' => self::getEnvValue($_SERVER, 'HTTP_USER_AGENT'),
                    'referrer' => self::getEnvValue($_SERVER, 'HTTP_REFERER'),
                    'host' => $host,
                ),
            ),

            // User info
            'user' => array(
                'user_ip' => self::getEnvValue($_SERVER, 'REMOTE_ADDR'),
                'uid' => self::$user_id,
                'user_params' => self::$user_params,
            ),

            'custom_error_id' => $custom_error_id,
            'custom_params' => $custom_params,
        );

        // sending info to remote server
        self::sendErrorInfoToServer($payload);
    }

    /**
     * Helper function
     *
     * @param $env_var
     * @param $param
     * @return string
     */
    private static function getEnvValue($env_var, $param)
    {
        $res = isset($env_var[$param]) ? $env_var[$param] : '';

        if (is_string($res) and mb_strlen($res) > 2048) {
            $res = mb_substr($res, 0, 2048);
        }

        return $res;
    }

    /**
     * Helper function
     *
     * @param $var
     * @return array
     */
    private static function formatVar($var)
    {
        if (!is_array($var)) {
            return $var;
        }

        foreach ($var as $key => $value) {
            if (is_array($value)) {
                $var[$key] = self::formatVar($value);
            } else if (is_string($value) and mb_strlen($value) > 512) {
                $var[$key] = mb_substr($value, 0, 512);
            }
        }

        return $var;
    }

    /**
     * Helper function
     *
     * @param $env_var
     * @return array
     */
    private static function getGlobalVar($env_var)
    {
        $env_var = '_' . strtoupper($env_var);

        if (!isset($GLOBALS[$env_var])) {
            return array();
        }

        $global_var = $GLOBALS[$env_var];

        return self::formatVar($global_var);
    }

    /**
     * Getting and formatting client HTTP Headers
     *
     * @return array
     */
    private static function getClientHeaders()
    {
        $var = self::getGlobalVar('server');
        if (!is_array($var)) {
            return array();
        }

        $headers = array();
        foreach ($var as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Formatting stack trace from error
     *
     * @param $stack_trace
     * @param $file
     * @param $line
     * @return array
     */
    private static function getStackTrace($stack_trace, $file, $line)
    {

        if (empty($stack_trace) or !is_array($stack_trace)) {
            $stack_trace = array();
        }

        array_unshift($stack_trace, array(
            'file' => $file,
            'line' => $line,
            'function' => '',
            'args' => array(),
        ));

        $out = array();
        foreach ($stack_trace as $i => $item) {
            $context = self::getStackTraceContext($item['file'], $item['line']);
            $out[] = array(
                'line' => $item['line'],
                'file_name' => $item['file'],
                'method_name' => $item['function'],
                'args' => array(), //$item['args'],
                'context' => $context['context'],
                'first_line_index' => $context['first_line_index'],
            );
        }

        return $out;
    }

    /**
     * Getting context for stack trace
     *
     * @param $file_name
     * @param $line
     * @return array
     */
    private static function getStackTraceContext($file_name, $line)
    {
        $result = array(
            'context' => null,
            'first_line_index' => null,
        );

        $handle = @fopen($file_name, "r");
        if (!$handle) {
            return $result;
        }

        $line_index = 0;
        $context = array();
        $first_line_index = null;
        $max_lines = 5;

        while (($buffer = fgets($handle, 4096)) !== false) {
            $line_index++;

            if ($line_index > $line + $max_lines) {
                break;
            }

            if ($line_index >= $line - $max_lines) {
                if ($first_line_index === null) {
                    $first_line_index = $line_index;
                }
                $str_line = trim($buffer, "\r\n");
                if (mb_strlen($str_line) > 300) {
                    $str_line = mb_substr($str_line, 0, 300) . '...';
                }

                $context[] = $str_line;
            }
        }

        $result['context'] = $context;
        $result['first_line_index'] = $first_line_index;

        fclose($handle);
        return $result;
    }

    /**
     * Convert PHP Error code to string
     *
     * @param $error_code
     * @return string
     */
    private static function formatErrorCode($error_code)
    {
        switch ($error_code) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
            default:
                return $error_code; // custom error code
        }
    }

    /**
     * Sending error info to remote server
     *
     * @param $error_info
     */
    private static function sendErrorInfoToServer($error_info)
    {
        $url = self::$base_url . 'client/error';

        $context = stream_context_create(
            array('http' =>
                array(
                    'timeout' => 10,
                    'method' => 'POST',
                    'header' => 'Content-type: application/json; charset=UTF-8',
                    'content' => json_encode($error_info)
                )
            )
        );

        if (self::$show_connection_error) {
            file_get_contents($url, false, $context);
        } else {
            @file_get_contents($url, false, $context);
        }
    }
}