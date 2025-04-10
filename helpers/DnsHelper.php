<?php
/**
 * DnsHelper - Manages DNS operations for the Blackwall module
 */
class DnsHelper
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
     * Get A record IPs for a domain
     * 
     * @param string $domain Domain to lookup
     * @return array Array of IPs found or default IP if lookup fails
     */
    public function getDomainARecords($domain) {
        // Default fallback IP if DNS lookup fails
        $default_ip = ['1.23.45.67'];
        
        try {
            // Log the DNS lookup attempt
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Lookup',
                ['domain' => $domain],
                null,
                null
            );
            
            // Perform DNS lookup for A records
            $dns_records = @dns_get_record($domain, DNS_A);
            
            // Check if we got valid results
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                // Extract IPs from A records
                $ips = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ip']) && !empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                }
                
                // Log the results
                self::save_log(
                    'Product',
                    $this->module_name,
                    'DNS Lookup Results',
                    ['domain' => $domain, 'ips' => $ips],
                    null,
                    null
                );
                
                // If we found IPs, return them
                if (!empty($ips)) {
                    return $ips;
                }
            }
            
            // If lookup failed or returned no results, also try with "www." prefix
            if (strpos($domain, 'www.') !== 0) {
                $www_domain = 'www.' . $domain;
                $www_dns_records = @dns_get_record($www_domain, DNS_A);
                
                if ($www_dns_records && is_array($www_dns_records) && !empty($www_dns_records)) {
                    $www_ips = [];
                    foreach ($www_dns_records as $record) {
                        if (isset($record['ip']) && !empty($record['ip'])) {
                            $www_ips[] = $record['ip'];
                        }
                    }
                    
                    // Log the www results
                    self::save_log(
                        'Product',
                        $this->module_name,
                        'DNS Lookup Results (www)',
                        ['domain' => $www_domain, 'ips' => $www_ips],
                        null,
                        null
                    );
                    
                    if (!empty($www_ips)) {
                        return $www_ips;
                    }
                }
            }
            
            // Fallback - try PHP's gethostbyname as a last resort
            $ip = gethostbyname($domain);
            if ($ip && $ip !== $domain) {
                // Log the gethostbyname result
                self::save_log(
                    'Product',
                    $this->module_name,
                    'DNS Lookup (gethostbyname)',
                    ['domain' => $domain, 'ip' => $ip],
                    null,
                    null
                );
                return [$ip];
            }
            
            // If all lookups failed, return default IP
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Lookup Failed',
                ['domain' => $domain, 'using_default' => $default_ip],
                'DNS lookup failed, using default IP',
                null
            );
            return $default_ip;
        } catch (Exception $e) {
            // Log any errors and return default IP
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Lookup Error',
                ['domain' => $domain, 'error' => $e->getMessage()],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            return $default_ip;
        }
    }

    /**
     * Get AAAA record IPs for a domain (IPv6)
     * 
     * @param string $domain Domain to lookup
     * @return array Array of IPv6 addresses found or default IPv6 if lookup fails
     */
    public function getDomainAAAARecords($domain) {
        // Default fallback IPv6 if DNS lookup fails
        $default_ipv6 = ['2a01:4f8:c2c:5a72::1'];
        
        try {
            // Log the DNS lookup attempt
            self::save_log(
                'Product',
                $this->module_name,
                'DNS AAAA Lookup',
                ['domain' => $domain],
                null,
                null
            );
            
            // Perform DNS lookup for AAAA records
            $dns_records = @dns_get_record($domain, DNS_AAAA);
            
            // Check if we got valid results
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                // Extract IPv6 addresses from AAAA records
                $ipv6s = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                        $ipv6s[] = $record['ipv6'];
                    }
                }
                
                // Log the results
                self::save_log(
                    'Product',
                    $this->module_name,
                    'DNS AAAA Lookup Results',
                    ['domain' => $domain, 'ipv6s' => $ipv6s],
                    null,
                    null
                );
                
                // If we found IPv6 addresses, return them
                if (!empty($ipv6s)) {
                    return $ipv6s;
                }
            }
            
            // If lookup failed or returned no results, also try with "www." prefix
            if (strpos($domain, 'www.') !== 0) {
                $www_domain = 'www.' . $domain;
                $www_dns_records = @dns_get_record($www_domain, DNS_AAAA);
                
                if ($www_dns_records && is_array($www_dns_records) && !empty($www_dns_records)) {
                    $www_ipv6s = [];
                    foreach ($www_dns_records as $record) {
                        if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                            $www_ipv6s[] = $record['ipv6'];
                        }
                    }
                    
                    // Log the www results
                    self::save_log(
                        'Product',
                        $this->module_name,
                        'DNS AAAA Lookup Results (www)',
                        ['domain' => $www_domain, 'ipv6s' => $www_ipv6s],
                        null,
                        null
                    );
                    
                    if (!empty($www_ipv6s)) {
                        return $www_ipv6s;
                    }
                }
            }
            
            // If all lookups failed, return default IPv6
            self::save_log(
                'Product',
                $this->module_name,
                'DNS AAAA Lookup Failed',
                ['domain' => $domain, 'using_default' => $default_ipv6],
                'DNS AAAA lookup failed, using default IPv6',
                null
            );
            return $default_ipv6;
        } catch (Exception $e) {
            // Log any errors and return default IPv6
            self::save_log(
                'Product',
                $this->module_name,
                'DNS AAAA Lookup Error',
                ['domain' => $domain, 'error' => $e->getMessage()],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            return $default_ipv6;
        }
    }

    /**
     * Check if the domain DNS is correctly pointing to our protection servers
     * 
     * @param string $domain Domain to check
     * @return bool True if DNS is correctly configured, false otherwise
     */
    public function checkDomainDnsConfiguration($domain) {
        // Define the required DNS records for Blackwall protection
        $required_records = BlackwallConstants::getDnsRecords();
        
        try {
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Configuration Check',
                ['domain' => $domain, 'required' => $required_records],
                null,
                null
            );
            
            // Get current DNS records for the domain
            $a_records = @dns_get_record($domain, DNS_A);
            $aaaa_records = @dns_get_record($domain, DNS_AAAA);
            
            // Check if any of the required A records match
            $has_valid_a_record = false;
            foreach ($a_records as $record) {
                if (in_array($record['ip'], $required_records['A'])) {
                    $has_valid_a_record = true;
                    break;
                }
            }
            
            // Check if any of the required AAAA records match
            $has_valid_aaaa_record = false;
            foreach ($aaaa_records as $record) {
                if (in_array($record['ipv6'], $required_records['AAAA'])) {
                    $has_valid_aaaa_record = true;
                    break;
                }
            }
            
            $result = $has_valid_a_record && $has_valid_aaaa_record;
            
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Configuration Check Result',
                [
                    'domain' => $domain,
                    'has_valid_a' => $has_valid_a_record,
                    'has_valid_aaaa' => $has_valid_aaaa_record,
                    'result' => $result ? 'Configured correctly' : 'Not configured correctly'
                ],
                null,
                null
            );
            
            return $result;
        } catch (Exception $e) {
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Configuration Check Error',
                ['domain' => $domain, 'error' => $e->getMessage()],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            return false;
        }
    }

    /**
     * Register the DNS check hook for a domain
     * 
     * @param string $domain Domain to check
     * @param int $order_id Order ID
     * @return bool Success status
     */
    public function registerDnsCheckHook($domain, $order_id)
    {
        try {
            self::save_log(
                'Product',
                $this->module_name,
                'Registering DNS Check Hook',
                ['domain' => $domain, 'order_id' => $order_id],
                null,
                null
            );
            
            // Store DNS check meta data for the hook to use
            $meta_data = [
                'domain' => $domain,
                'order_id' => $order_id,
                'check_time' => time(),
                'product_id' => 105, // Hardcoded to match the hook
                'client_id' => $this->user_id
            ];
            
            // Store this in a database table or file for the hook to access
            // For this example, we'll use a simple file-based approach
            $dns_check_file = sys_get_temp_dir() . '/blackwall_dns_check_' . md5($domain . $order_id) . '.json';
            file_put_contents($dns_check_file, json_encode($meta_data));
            
            self::save_log(
                'Product',
                $this->module_name,
                'DNS Check Data Stored',
                ['file' => $dns_check_file, 'data' => $meta_data],
                null,
                null
            );
            
            return true;
        } catch (Exception $e) {
            self::save_log(
                'Product',
                $this->module_name,
                'Error Registering DNS Check Hook',
                ['domain' => $domain, 'order_id' => $order_id],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            return false;
        }
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
