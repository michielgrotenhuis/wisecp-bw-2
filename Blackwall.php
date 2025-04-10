<?php
/**
 * Blackwall (BotGuard) Product Module for WISECP
 * This module allows WISECP to provision and manage BotGuard website protection services
 */
class Blackwall extends ProductModule
{
    private $helpers = [];
    
    function __construct(){
        $this->_name = __CLASS__;
        parent::__construct();
        
        // Load helpers
        $this->loadHelpers();
    }
    
    /**
     * Load helper classes
     */
    private function loadHelpers() {
        // Load helper files if they exist
        $helper_dir = __DIR__ . '/helpers/';
        
        // DnsHelper
        if(file_exists($helper_dir . 'DnsHelper.php')) {
            require_once($helper_dir . 'DnsHelper.php');
            $this->helpers['dns'] = new DnsHelper($this->_name);
        }
        
        // ApiHelper
        if(file_exists($helper_dir . 'ApiHelper.php')) {
            require_once($helper_dir . 'ApiHelper.php');
            $this->helpers['api'] = new ApiHelper($this->config["settings"]["api_key"], $this->_name);
        }
        
        // LogHelper
        if(file_exists($helper_dir . 'LogHelper.php')) {
            require_once($helper_dir . 'LogHelper.php');
            $this->helpers['log'] = new LogHelper($this->_name);
        }
    }

    /**
     * Module Configuration Page
     */
    public function configuration()
    {
        $action = isset($_GET["action"]) ? $_GET["action"] : false;
        $action = Filter::letters_numbers($action);

        $vars = [
            'm_name'    => $this->_name,
            'area_link' => $this->area_link,
            'lang'      => $this->lang,
            'config'    => $this->config,
        ];
        
        return $this->get_page("views/admin/configuration".($action ? "-".$action : ''),$vars);
    }

    /**
     * Save Module Configuration
     */
    public function controller_save()
    {
        // Use raw POST data to preserve the exact API key format
        $api_key = isset($_POST["api_key"]) ? $_POST["api_key"] : "";
        
        // Use Filter for the other fields
        $primary_server = Filter::init("POST/primary_server", "hclear");
        $secondary_server = Filter::init("POST/secondary_server", "hclear");

        // Log the received API key for debugging
        if(isset($this->helpers['log'])) {
            $this->helpers['log']->debug("Received API Key: " . substr($api_key, 0, 5) . '...');
        }

        $set_config = $this->config;

        if($set_config["settings"]["api_key"] != $api_key) 
            $set_config["settings"]["api_key"] = $api_key;
        
        if($set_config["settings"]["primary_server"] != $primary_server) 
            $set_config["settings"]["primary_server"] = $primary_server;
        
        if($set_config["settings"]["secondary_server"] != $secondary_server) 
            $set_config["settings"]["secondary_server"] = $secondary_server;

        if(Validation::isEmpty($api_key))
        {
            echo Utility::jencode([
                'status' => "error",
                'message' => $this->lang["error_api_key_required"],
            ]);
            return false;
        }

        // Save the configuration
        $this->save_config($set_config);
        
        // Log the saved API key for verification
        if(isset($this->helpers['log'])) {
            $this->helpers['log']->debug("Saved API Key: " . substr($set_config["settings"]["api_key"], 0, 5) . '...');
        }

        echo Utility::jencode([
            'status' => "successful",
            'message' => $this->lang["success_settings_saved"],
        ]);

        return true;
    }
    
    /**
     * Generate a support ticket for DNS configuration
     * This is called by the hook when DNS is not configured correctly
     * 
     * @param string $domain Domain name
     * @param int $client_id Client ID
     * @param int $order_id Order ID
     * @return bool Success status
     */
    public function create_dns_configuration_ticket($domain, $client_id, $order_id)
    {
        try {
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Creating DNS Configuration Ticket',
                    ['domain' => $domain, 'client_id' => $client_id, 'order_id' => $order_id]
                );
            }
            
            // Define the required DNS records for Blackwall protection
            $required_records = BlackwallConstants::getDnsRecords();
            
            // Get client language preference
            $client = User::getData($client_id);
            $client_lang = isset($client['lang']) ? $client['lang'] : 'en';
            
            // Get localized title
            $title_locale = [
                'en' => "DNS Configuration Required for {$domain}",
                'de' => "DNS-Konfiguration erforderlich für {$domain}",
                'fr' => "Configuration DNS requise pour {$domain}",
                'es' => "Configuración DNS requerida para {$domain}",
                'nl' => "DNS-configuratie vereist voor {$domain}",
            ];
            
            // Default to English if language not found
            $title = isset($title_locale[$client_lang]) ? $title_locale[$client_lang] : $title_locale['en'];
            
            // Create the ticket message with Markdown formatting
            $message = $this->get_dns_configuration_message($client_lang, $domain, $required_records);
            
            // Prepare ticket data
            $ticket_data = [
                'user_id' => $client_id,
                'did' => 1, // Department ID - adjust as needed
                'priority' => 2, // Medium priority
                'status' => 'process', // In progress
                'title' => $title,
                'message' => $message,
                'service' => $order_id // Order ID
            ];
            
            // Create the ticket
            if (class_exists('Models\\Tickets\\Tickets')) {
                $ticket_id = \Models\Tickets\Tickets::insert($ticket_data);
            } elseif (class_exists('Tickets')) {
                $ticket_id = Tickets::insert($ticket_data);
            } else {
                throw new Exception("Ticket system not found");
            }
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'DNS Configuration Ticket Created',
                    ['ticket_id' => $ticket_id]
                );
            }
            
            return true;
        } catch (Exception $e) {
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    'Error Creating DNS Configuration Ticket',
                    ['domain' => $domain, 'client_id' => $client_id],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }

    /**
     * Get localized DNS configuration message
     * 
     * @param string $lang Language code
     * @param string $domain Domain name
     * @param array $required_records Required DNS records
     * @return string Localized message content
     */
    private function get_dns_configuration_message($lang, $domain, $required_records)
    {
        // Basic English template for all messages
        $message = "# DNS Configuration Instructions for {$domain}\n\n";
        $message .= "⚠️ **Important Notice:** Your domain **{$domain}** is not correctly configured for Blackwall protection.\n\n";
        $message .= "For Blackwall to protect your website, you need to point your domain to our protection servers using the DNS settings below:\n\n";
        
        // A Records section
        $message .= "## A Records\n\n";
        $message .= "| Record Type | Name | Value |\n";
        $message .= "|------------|------|-------|\n";
        foreach ($required_records['A'] as $ip) {
            $message .= "| A | @ | {$ip} |\n";
        }
        
        // AAAA Records section
        $message .= "\n## AAAA Records (IPv6)\n\n";
        $message .= "| Record Type | Name | Value |\n";
        $message .= "|------------|------|-------|\n";
        foreach ($required_records['AAAA'] as $ipv6) {
            $message .= "| AAAA | @ | {$ipv6} |\n";
        }
        
        // Instructions for www subdomain
        $message .= "\n## www Subdomain\n\n";
        $message .= "If you want to use www.{$domain}, you should also add the same records for the www subdomain or create a CNAME record:\n\n";
        $message .= "| Record Type | Name | Value |\n";
        $message .= "|------------|------|-------|\n";
        $message .= "| CNAME | www | {$domain} |\n";
        
        // DNS propagation note
        $message .= "\n## DNS Propagation\n\n";
        $message .= "After updating your DNS settings, it may take up to 24-48 hours for the changes to propagate globally. During this time, you may experience intermittent connectivity to your website.\n\n";
        
        // Support note
        $message .= "## Need Help?\n\n";
        $message .= "If you need assistance with these settings, please reply to this ticket. Our team will be happy to guide you through the process.\n\n";
        $message .= "You can also check your current DNS configuration using online tools like [MXToolbox](https://mxtoolbox.com/DNSLookup.aspx) or [DNSChecker](https://dnschecker.org/).\n\n";
        
        // Localize the message based on language if needed
        switch ($lang) {
            case 'de':
                // German translation would go here
                break;
            case 'fr':
                // French translation would go here
                break;
            case 'es':
                // Spanish translation would go here
                break;
            case 'nl':
                // Dutch translation would go here
                break;
        }
        
        return $message;
    }
    
    /**
     * Create new Blackwall service
     */
    public function create($order_options=[])
    {
        try {
            // First try to get domain from order options
            $user_domain = isset($this->order["options"]["domain"]) 
                ? $this->order["options"]["domain"] 
                : false;
            
            // If not found, try getting from requirements
            if(!$user_domain && isset($this->val_of_requirements["user_domain"])) {
                $user_domain = $this->val_of_requirements["user_domain"];
            }
            
            // Get user information from WISECP user data
            $user_email = $this->user["email"];
            $first_name = $this->user["name"];
            $last_name = $this->user["surname"];

            // Validate inputs
            if(!$user_domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }
            
            // Log the values for debugging
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'create_values',
                    [
                        'domain' => $user_domain,
                        'email' => $user_email,
                        'name' => $first_name,
                        'surname' => $last_name,
                        'order_options' => $order_options
                    ],
                    'Values being used for service creation'
                );
            }

            try {
                // Step 1: Create a subaccount in Botguard
                $subaccount_data = [
                    'email' => $user_email,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Creating subaccount',
                        $subaccount_data
                    );
                }
                
                $subaccount_result = $this->helpers['api']->request('/user', 'POST', $subaccount_data);
                
                // Extract the user ID and API key from the response
                $user_id = isset($subaccount_result['id']) ? $subaccount_result['id'] : null;
                $user_api_key = isset($subaccount_result['api_key']) ? $subaccount_result['api_key'] : null;
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Subaccount created',
                        [
                            'user_id' => $user_id,
                            'api_key_first_chars' => $user_api_key ? substr($user_api_key, 0, 5) . '...' : 'null'
                        ]
                    );
                }
                
                if (!$user_id) {
                    throw new Exception("Failed to get user ID from Botguard API response");
                }
                
                // Step 2: Also create user in GateKeeper
                try {
                    $gatekeeper_user_data = [
                        'id' => $user_id,
                        'tag' => 'wisecp'
                    ];
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Creating user in GateKeeper',
                            $gatekeeper_user_data
                        );
                    }
                    
                    $gatekeeper_user_result = $this->helpers['api']->gatekeeperRequest('/user', 'POST', $gatekeeper_user_data);
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'User created in GateKeeper',
                            $gatekeeper_user_result
                        );
                    }
                } catch (Exception $gk_user_e) {
                    // Log error but continue - the user might already exist in GateKeeper
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'Error creating user in GateKeeper',
                            ['error' => $gk_user_e->getMessage()],
                            $gk_user_e->getMessage(),
                            $gk_user_e->getTraceAsString()
                        );
                    }
                    // Continue execution - don't break the process for this
                }
                
                // Step 3: Add the domain to the subaccount in Botguard
                $website_data = [
                    'domain' => $user_domain,
                    'user' => $user_id
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Creating domain in Botguard',
                        $website_data
                    );
                }
                
                $website_result = $this->helpers['api']->request('/website', 'POST', $website_data);
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain created in Botguard',
                        $website_result
                    );
                }
                
                // Step 4: Also add the domain in GateKeeper - UPDATED WITH DNS LOOKUP
                try {
                    // Get the A records for the domain
                    $domain_ips = $this->helpers['dns']->getDomainARecords($user_domain);
                    // Get AAAA records if available
                    $domain_ipv6s = $this->helpers['dns']->getDomainAAAARecords($user_domain);
                    
                    $gatekeeper_website_data = [
                        'domain' => $user_domain,
                        'subdomain' => ['www'],
                        'ip' => $domain_ips, // Use the dynamically looked up IPs
                        'ipv6' => $domain_ipv6s, // Use the dynamically looked up IPv6 addresses
                        'user_id' => $user_id,
                        'tag' => ['wisecp'],
                        'status' => 'setup',
                        'settings' => BlackwallConstants::getDefaultWebsiteSettings()
                    ];
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Creating domain in GateKeeper',
                            $gatekeeper_website_data
                        );
                    }
                    
                    $gatekeeper_website_result = $this->helpers['api']->gatekeeperRequest('/website', 'POST', $gatekeeper_website_data);
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Domain created in GateKeeper',
                            $gatekeeper_website_result
                        );
                    }
                } catch (Exception $gk_website_e) {
                    // Log error but continue - the domain might already exist in GateKeeper
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'Error creating domain in GateKeeper',
                            ['domain' => $user_domain, 'error' => $gk_website_e->getMessage()],
                            $gk_website_e->getMessage(),
                            $gk_website_e->getTraceAsString()
                        );
                    }
                    // Continue execution - don't break the process for this
                }
                
                // Step 5: Add a delay before updating the domain status
                sleep(2);
                
                // Step 6: Activate the domain by setting status to online in Botguard
                $update_data = [
                    'status' => BlackwallConstants::STATUS_ONLINE
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Updating domain status to online in Botguard',
                        ['domain' => $user_domain, 'data' => $update_data]
                    );
                }
                
                try {
                    $update_result = $this->helpers['api']->request('/website/' . $user_domain, 'PUT', $update_data);
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Domain status updated in Botguard',
                            $update_result
                        );
                    }
                } catch (Exception $update_e) {
                    // Log the error but continue
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'Error updating domain status in Botguard',
                            ['domain' => $user_domain, 'error' => $update_e->getMessage()],
                            $update_e->getMessage(),
                            $update_e->getTraceAsString()
                        );
                    }
                }
                
                // Step 7: Also update the domain status in GateKeeper - UPDATED WITH DNS LOOKUP
                try {
                    // Get the A records for the domain (refreshed)
                    $domain_ips = $this->helpers['dns']->getDomainARecords($user_domain);
                    // Get AAAA records if available (refreshed)
                    $domain_ipv6s = $this->helpers['dns']->getDomainAAAARecords($user_domain);
                    
                    // Get the default website settings but update status to online
                    $gatekeeper_settings = BlackwallConstants::getDefaultWebsiteSettings();
                    
                    $gatekeeper_update_data = [
                        'domain' => $user_domain,
                        'user_id' => $user_id,
                        'subdomain' => ['www'],
                        'ip' => $domain_ips, // Use the dynamically looked up IPs
                        'ipv6' => $domain_ipv6s, // Use the dynamically looked up IPv6 addresses
                        'status' => BlackwallConstants::STATUS_ONLINE,
                        'settings' => $gatekeeper_settings
                    ];
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Updating domain status to online in GateKeeper',
                            ['domain' => $user_domain, 'data' => $gatekeeper_update_data]
                        );
                    }
                    
                    $gatekeeper_update_result = $this->helpers['api']->gatekeeperRequest('/website/' . $user_domain, 'PUT', $gatekeeper_update_data);
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Domain status updated in GateKeeper',
                            $gatekeeper_update_result
                        );
                    }
                    
                    // Step 8: Register hook for DNS verification after creation
                    if(isset($this->helpers['dns'])) {
                        $this->helpers['dns']->registerDnsCheckHook($user_domain, $this->order["id"]);
                    }
                    
                } catch (Exception $gk_update_e) {
                    // Log the error but continue
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'Error updating domain status in GateKeeper',
                            ['domain' => $user_domain, 'error' => $gk_update_e->getMessage()],
                            $gk_update_e->getMessage(),
                            $gk_update_e->getTraceAsString()
                        );
                    }
                }
                
                // Return the successful data to store in the service
                return [
                    'config' => [
                        'blackwall_domain' => $user_domain,
                        'blackwall_user_id' => $user_id,
                        'blackwall_api_key' => $user_api_key,
                    ],
                    'creation_info' => []
                ];
            } catch (Exception $api_e) {
                // If there's an API-specific error, log it
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->error(
                        'API Error',
                        [
                            'domain' => $user_domain,
                            'email' => $user_email
                        ],
                        $api_e->getMessage(),
                        $api_e->getTraceAsString()
                    );
                }
                
                $this->error = $api_e->getMessage();
                return false;
            }
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    __FUNCTION__,
                    [
                        'order' => $this->order, 
                        'requirements' => $this->val_of_requirements,
                        'user' => [
                            'email' => isset($this->user["email"]) ? $this->user["email"] : null,
                            'name' => isset($this->user["name"]) ? $this->user["name"] : null,
                            'surname' => isset($this->user["surname"]) ? $this->user["surname"] : null
                        ]
                    ],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    
    /**
     * Renewal of service
     */
    public function renewal($order_options=[])
    {
        try {
            // For renewal, we just need to verify the domain is still active
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }
            
            // Call the Botguard API to verify the domain exists
            $result = $this->helpers['api']->request('/website/' . $domain, 'GET');
            
            // Check if domain is paused and reactivate if needed
            if(isset($result['status']) && $result['status'] === BlackwallConstants::STATUS_PAUSED) {
                $update_data = [
                    'status' => BlackwallConstants::STATUS_ONLINE
                ];
                
                // Update in Botguard
                $result = $this->helpers['api']->request('/website/' . $domain, 'PUT', $update_data);
                
                // Also update in GateKeeper - UPDATED WITH DNS LOOKUP
                try {
                    // Get the A records for the domain
                    $domain_ips = $this->helpers['dns']->getDomainARecords($domain);
                    // Get AAAA records if available
                    $domain_ipv6s = $this->helpers['dns']->getDomainAAAARecords($domain);
                    
                    // Get default settings but update status to online
                    $gatekeeper_settings = BlackwallConstants::getDefaultWebsiteSettings();
                    
                    $gatekeeper_update_data = [
                        'domain' => $domain,
                        'subdomain' => ['www'],
                        'ip' => $domain_ips, // Use the dynamically looked up IPs
                        'ipv6' => $domain_ipv6s, // Use the dynamically looked up IPv6 addresses
                        'user_id' => $user_id,
                        'status' => BlackwallConstants::STATUS_ONLINE,
                        'settings' => $gatekeeper_settings
                    ];
                    
                    $this->helpers['api']->gatekeeperRequest('/website/' . $domain, 'PUT', $gatekeeper_update_data);
                    
                    // Register hook for DNS verification after renewal
                    if(isset($this->helpers['dns'])) {
                        $this->helpers['dns']->registerDnsCheckHook($domain, $this->order["id"]);
                    }
                } catch (Exception $gk_e) {
                    // Log but continue
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'GateKeeper update error during renewal',
                            ['domain' => $domain, 'error' => $gk_e->getMessage()],
                            $gk_e->getMessage(),
                            $gk_e->getTraceAsString()
                        );
                    }
                }
            }
            
            return true;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    __FUNCTION__,
                    ['order' => $this->order],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    
    /**
     * Suspend service
     */
    public function suspend()
    {
        try {
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }

            // Step 1: Call the Botguard API to set domain status to 'paused'
            $update_data = [
                'status' => BlackwallConstants::STATUS_PAUSED
            ];
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Setting domain status to paused in Botguard',
                    ['domain' => $domain, 'data' => $update_data]
                );
            }
            
            $result = $this->helpers['api']->request('/website/' . $domain, 'PUT', $update_data);
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Domain status set to paused in Botguard',
                    $result
                );
            }
            
            // Step 2: Also update the domain status in GateKeeper - UPDATED WITH DNS LOOKUP
            try {
                // Get the A records for the domain
                $domain_ips = $this->helpers['dns']->getDomainARecords($domain);
                // Get AAAA records if available
                $domain_ipv6s = $this->helpers['dns']->getDomainAAAARecords($domain);
                
                // Get default settings but update status to paused
                $gatekeeper_settings = BlackwallConstants::getDefaultWebsiteSettings();
                
                $gatekeeper_update_data = [
                    'domain' => $domain,
                    'user_id' => $user_id,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips, // Use the dynamically looked up IPs
                    'ipv6' => $domain_ipv6s, // Use the dynamically looked up IPv6 addresses
                    'status' => BlackwallConstants::STATUS_PAUSED,
                    'settings' => $gatekeeper_settings
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Setting domain status to paused in GateKeeper',
                        ['domain' => $domain, 'data' => $gatekeeper_update_data]
                    );
                }
                
                $gatekeeper_result = $this->helpers['api']->gatekeeperRequest('/website/' . $domain, 'PUT', $gatekeeper_update_data);
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status set to paused in GateKeeper',
                        $gatekeeper_result
                    );
                }
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper update fails
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error setting domain status in GateKeeper',
                        ['domain' => $domain, 'error' => $gk_e->getMessage()],
                        $gk_e->getMessage(),
                        $gk_e->getTraceAsString()
                    );
                }
            }
            
            return true;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    __FUNCTION__,
                    ['order' => $this->order],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }

    /**
     * Unsuspend service
     */
    public function unsuspend()
    {
        try {
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }

            // Step 1: Call the Botguard API to set domain status to 'online'
            $update_data = [
                'status' => BlackwallConstants::STATUS_ONLINE
            ];
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Setting domain status to online in Botguard',
                    ['domain' => $domain, 'data' => $update_data]
                );
            }
            
            $result = $this->helpers['api']->request('/website/' . $domain, 'PUT', $update_data);
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Domain status set to online in Botguard',
                    $result
                );
            }
            
            // Step 2: Also update the domain status in GateKeeper - UPDATED WITH DNS LOOKUP
            try {
                // Get the A records for the domain
                $domain_ips = $this->helpers['dns']->getDomainARecords($domain);
                // Get AAAA records if available
                $domain_ipv6s = $this->helpers['dns']->getDomainAAAARecords($domain);
                
                // Get default settings but update status to online
                $gatekeeper_settings = BlackwallConstants::getDefaultWebsiteSettings();
                
                $gatekeeper_update_data = [
                    'domain' => $domain,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips, // Use the dynamically looked up IPs
                    'ipv6' => $domain_ipv6s, // Use the dynamically looked up IPv6 addresses
                    'user_id' => $user_id,
                    'status' => BlackwallConstants::STATUS_ONLINE,
                    'settings' => $gatekeeper_settings
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Setting domain status to online in GateKeeper',
                        ['domain' => $domain, 'data' => $gatekeeper_update_data]
                    );
                }
                
                $gatekeeper_result = $this->helpers['api']->gatekeeperRequest('/website/' . $domain, 'PUT', $gatekeeper_update_data);
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status set to online in GateKeeper',
                        $gatekeeper_result
                    );
                }
                
                // Register hook for DNS verification after unsuspension
                if(isset($this->helpers['dns'])) {
                    $this->helpers['dns']->registerDnsCheckHook($domain, $this->order["id"]);
                }
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper update fails
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error setting domain status in GateKeeper',
                        ['domain' => $domain, 'error' => $gk_e->getMessage()],
                        $gk_e->getMessage(),
                        $gk_e->getTraceAsString()
                    );
                }
            }
            
            return true;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    __FUNCTION__,
                    ['order' => $this->order],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    
    /**
     * Delete service
     */
    public function delete()
    {
        try {
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }

            // Step 1: Delete the domain from Botguard
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Deleting domain from Botguard',
                    ['domain' => $domain]
                );
            }
            
            $result = $this->helpers['api']->request('/website/' . $domain, 'DELETE');
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Domain deleted from Botguard',
                    $result
                );
            }
            
            // Step 2: Also delete the domain from GateKeeper
            try {
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Deleting domain from GateKeeper',
                        ['domain' => $domain]
                    );
                }
                
                $gatekeeper_result = $this->helpers['api']->gatekeeperRequest('/website/' . $domain, 'DELETE');
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain deleted from GateKeeper',
                        $gatekeeper_result
                    );
                }
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper deletion fails
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error deleting domain from GateKeeper',
                        ['domain' => $domain, 'error' => $gk_e->getMessage()],
                        $gk_e->getMessage(),
                        $gk_e->getTraceAsString()
                    );
                }
            }
            
            return true;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    __FUNCTION__,
                    ['order' => $this->order],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    
    /**
     * Client Area Display
     */
    public function clientArea()
    {
        $content = $this->clientArea_buttons_output();
        $_page   = $this->page;

        if(!$_page) $_page = 'home';

        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;
        
        // Use master API key from module settings
        $api_key = $this->config["settings"]["api_key"];
        
        $variables = [
            'domain' => $domain,
            'api_key' => $api_key,
            'lang' => $this->lang,
        ];

        $content .= $this->get_page('views/client/'.$_page, $variables);
        return $content;
    }

    /**
     * Client Area Buttons
     */
    public function clientArea_buttons()
    {
        $buttons = [];
        
        if($this->page && $this->page != "home")
        {
            $buttons['home'] = [
                'text' => $this->lang["turn_back"],
                'type' => 'page-loader',
            ];
        }
        return $buttons;
    }

    /**
     * Admin Area Service Fields
     */
    public function adminArea_service_fields(){
        $config = $this->options["config"];
        
        $user_domain = isset($config["blackwall_domain"]) ? $config["blackwall_domain"] : NULL;
        
        return [
            'blackwall_domain' => [
                'wrap_width' => 100,
                'name' => $this->lang["domain_name"],
                'description' => $this->lang["domain_description"],
                'type' => "text",
                'value' => $user_domain,
            ],
        ];
    }

    /**
     * Save Admin Area Service Fields
     */
    public function save_adminArea_service_fields($data=[]){
        /* OLD DATA */
        $o_config = $data['old']['config'];
        
        /* NEW DATA */
        $n_config = $data['new']['config'];
        
        // Validate domain
        if(!isset($n_config['blackwall_domain']) || $n_config['blackwall_domain'] == '') {
            $this->error = $this->lang["error_missing_domain"];
            return false;
        }
        
        // Check if domain needs updating
        if($o_config['blackwall_domain'] != $n_config['blackwall_domain']) {
            // This would be complex to implement since it requires recreating
            // the domain in Blackwall. For simplicity, we'll disallow this.
            $this->error = $this->lang["error_cannot_change_domain"];
            return false;
        }
        
        return [
            'config' => $n_config,
        ];
    }

    /**
     * Admin Area Buttons
     */
    public function adminArea_buttons()
    {
        $buttons = [];
        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;
        
        if($domain) {
            $buttons['view_in_blackwall'] = [
                'text'  => $this->lang["view_in_blackwall"],
                'type'  => 'link',
                'url'   => 'https://apiv2.botguard.net/en/website/'.$domain.'/statistics?api-key='.$this->config["settings"]["api_key"],
                'target_blank' => true,
            ];
            
            $buttons['check_status'] = [
                'text'  => $this->lang["check_status"],
                'type'  => 'transaction',
            ];
            
            $buttons['check_dns'] = [
                'text'  => $this->lang["check_dns"],
                'type'  => 'transaction',
            ];
        }

        return $buttons;
    }

    /**
     * Admin Area Check Status
     */
    public function use_adminArea_check_status()
    {
        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;

        if(!$domain) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $this->lang["error_missing_domain"],
            ]);
            return false;
        }

        try {
            // Call the Botguard API to get the domain status
            $result = $this->helpers['api']->request('/website/' . $domain, 'GET');
            
            $status = isset($result['status']) ? $result['status'] : 'unknown';
            
            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["domain_status"] . ": " . $status,
            ]);
            return true;
        } catch (Exception $e) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Admin Area Check DNS Configuration
     */
    public function use_adminArea_check_dns()
    {
        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;

        if(!$domain) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $this->lang["error_missing_domain"],
            ]);
            return false;
        }

        try {
            // Check if the domain's DNS is properly configured
            $is_configured = $this->helpers['dns']->checkDomainDnsConfiguration($domain);
            
            if ($is_configured) {
                echo Utility::jencode([
                    'status' => "successful",
                    'message' => $this->lang["dns_configured_correctly"],
                ]);
            } else {
                echo Utility::jencode([
                    'status' => "warning",
                    'message' => $this->lang["dns_not_configured_correctly"],
                ]);
            }
            return true;
        } catch (Exception $e) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Get asset URL
     * 
     * @param string $path Asset path
     * @return string Full asset URL
     */
    public function asset_url($path) {
        return '/modules/Blackwall/assets/' . $path;
    }
}

/**
 * Constants for Blackwall
 */
class BlackwallConstants {
    // Status constants
    const STATUS_ONLINE = 'online';
    const STATUS_PAUSED = 'paused';
    const STATUS_SETUP = 'setup';
    
    // GateKeeper node IPs
    const GATEKEEPER_NODE_1_IPV4 = '49.13.161.213';
    const GATEKEEPER_NODE_1_IPV6 = '2a01:4f8:c2c:5a72::1';
    const GATEKEEPER_NODE_2_IPV4 = '116.203.242.28';
    const GATEKEEPER_NODE_2_IPV6 = '2a01:4f8:1c1b:7008::1';
    
    /**
     * Get the required DNS records
     * 
     * @return array DNS records
     */
    public static function getDnsRecords() {
        return [
            'A' => [self::GATEKEEPER_NODE_1_IPV4, self::GATEKEEPER_NODE_2_IPV4],
            'AAAA' => [self::GATEKEEPER_NODE_1_IPV6, self::GATEKEEPER_NODE_2_IPV6]
        ];
    }
    
    /**
     * Get default website settings for GateKeeper
     * 
     * @return array Default website settings
     */
    public static function getDefaultWebsiteSettings() {
        return [
            'rulesets' => [
                'wordpress' => false,
                'joomla' => false,
                'drupal' => false,
                'cpanel' => false,
                'bitrix' => false,
                'dokuwiki' => false,
                'xenforo' => false,
                'nextcloud' => false,
                'prestashop' => false
            ],
            'rules' => [
                'search_engines' => 'grant',
                'social_networks' => 'grant',
                'services_and_payments' => 'grant',
                'humans' => 'grant',
                'security_issues' => 'deny',
                'content_scrapers' => 'deny',
                'emulated_humans' => 'captcha',
                'suspicious_behaviour' => 'captcha'
            ],
            'loadbalancer' => [
                'upstreams_use_https' => true,
                'enable_http3' => true,
                'force_https' => true,
                'cache_static_files' => true,
                'cache_dynamic_pages' => false,
                'ddos_protection' => false,
                'ddos_protection_advanced' => false,
                'botguard_protection' => true,
                'certs_issuer' => 'letsencrypt',
                'force_subdomains_redirect' => false
            ]
        ];
    }
}

// Register the DNS check hook at module load time
require_once(__DIR__ . '/hooks/DnsHook.php');
Hook::add("OrderActivated", 1, [BlackwallDnsHook::class, 'handleOrderActivated']);
