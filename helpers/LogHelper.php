<?php
/**
 * LogHelper - Manages logging for the Blackwall module
 */
class LogHelper
{
    private $module_name;
    
    /**
     * Constructor
     * 
     * @param string $module_name Module name for logging
     */
    public function __construct($module_name)
    {
        $this->module_name = $module_name;
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Message to log
     * @param array $data Additional data
     * @return bool Success status
     */
    public function debug($message, $data = [])
    {
        return $this->log('Debug', $message, $data);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message to log
     * @param array $data Additional data
     * @return bool Success status
     */
    public function info($message, $data = [], $additional_message = null)
    {
        return $this->log('Info', $message, $data, $additional_message);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Message to log
     * @param array $data Additional data
     * @param string $additional_message Additional message
     * @param string $trace Error trace
     * @return bool Success status
     */
    public function warning($message, $data = [], $additional_message = null, $trace = null)
    {
        return $this->log('Warning', $message, $data, $additional_message, $trace);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Message to log
     * @param array $data Additional data
     * @param string $additional_message Additional message
     * @param string $trace Error trace
     * @return bool Success status
     */
    public function error($message, $data = [], $additional_message = null, $trace = null)
    {
        return $this->log('Error', $message, $data, $additional_message, $trace);
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Message
     * @param array $data Additional data
     * @param string $additional_message Additional message
     * @param string $trace Error trace
     * @return bool Success status
     */
    private function log($level, $message, $data = [], $additional_message = null, $trace = null)
    {
        return self::save_log(
            'Product',
            $this->module_name,
            "{$level}: {$message}",
            $data,
            $additional_message,
            $trace
        );
    }
    
    /**
     * Save a log entry
     * This is a copy of the WISECP core method for compatibility
     */
    private static function save_log($type = NULL, $name = NULL, $action = NULL, $detail = [], $message = NULL, $trace = NULL)
    {
        if (class_exists('Events')) {
            return Events::add($type, $name, $action, $detail, $message, $trace);
        }
        return true;
    }
}
