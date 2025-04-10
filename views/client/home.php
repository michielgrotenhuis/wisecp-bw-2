<?php if(!defined("CORE_FOLDER")) return false; 

// Define the IP addresses for the GateKeeper nodes
$gatekeeper_nodes = [
    'bg-gk-01' => [
        'ipv4' => BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
        'ipv6' => BlackwallConstants::GATEKEEPER_NODE_1_IPV6
    ],
    'bg-gk-02' => [
        'ipv4' => BlackwallConstants::GATEKEEPER_NODE_2_IPV4,
        'ipv6' => BlackwallConstants::GATEKEEPER_NODE_2_IPV6
    ]
];

// Function to check DNS records
function checkDNSRecords($domain, $gatekeeper_nodes) {
    $results = [
        'status' => false,
        'connected_to' => null,
        'ipv4_status' => false,
        'ipv6_status' => false,
        'ipv4_records' => [],
        'ipv6_records' => [],
        'missing_records' => []
    ];
    
    // Get the A records (IPv4)
    $a_records = @dns_get_record($domain, DNS_A);
    if ($a_records) {
        $results['ipv4_records'] = array_column($a_records, 'ip');
    }
    
    // Get the AAAA records (IPv6)
    $aaaa_records = @dns_get_record($domain, DNS_AAAA);
    if ($aaaa_records) {
        $results['ipv6_records'] = array_column($aaaa_records, 'ipv6');
    }
    
    // Check if the domain is connected to any of the GateKeeper nodes
    foreach ($gatekeeper_nodes as $node_name => $node_ips) {
        $ipv4_match = in_array($node_ips['ipv4'], $results['ipv4_records']);
        $ipv6_match = in_array($node_ips['ipv6'], $results['ipv6_records']);
        
        if ($ipv4_match || $ipv6_match) {
            $results['connected_to'] = $node_name;
            $results['ipv4_status'] = $ipv4_match;
            $results['ipv6_status'] = $ipv6_match;
            $results['status'] = true;
            
            // Check for missing records
            if (!$ipv4_match) {
                $results['missing_records'][] = [
                    'type' => 'A',
                    'value' => $node_ips['ipv4']
                ];
            }
            
            if (!$ipv6_match) {
                $results['missing_records'][] = [
                    'type' => 'AAAA',
                    'value' => $node_ips['ipv6']
                ];
            }
            
            break;
        }
    }
    
    // If not connected to any node, provide recommendations
    if (!$results['status']) {
        // Recommend the first node by default
        $recommended_node = 'bg-gk-01';
        $results['missing_records'] = [
            [
                'type' => 'A',
                'value' => $gatekeeper_nodes[$recommended_node]['ipv4']
            ],
            [
                'type' => 'AAAA',
                'value' => $gatekeeper_nodes[$recommended_node]['ipv6']
            ]
        ];
    }
    
    return $results;
}

// Check the DNS records for the domain
$dns_check = checkDNSRecords($domain, $gatekeeper_nodes);

?>

<div class="moderncardcon">
    <h4><?php echo $lang["service_info"]; ?></h4>
    
    <div class="singlecardinfo">
        <div class="cardbody">
            <div class="row">
                <div class="padding20">
                    <div class="formcon">
                        <div class="yuzde30"><?php echo $lang["protected_domain"]; ?></div>
                        <div class="yuzde70"><?php echo $domain; ?></div>
                    </div>
                    
                    <?php if(isset($service_status)): ?>
                    <div class="formcon">
                        <div class="yuzde30"><?php echo $lang["status"]; ?></div>
                        <div class="yuzde70"><?php echo $service_status; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="formcon">
                        <div class="yuzde30">DNS Configuration Status</div>
                        <div class="yuzde70">
                            <?php if($dns_check['status']): ?>
                                <span style="color: green; font-weight: bold;">✓ Correctly configured</span>
                                <p>Your domain is connected to node <?php echo $dns_check['connected_to']; ?></p>
                                <?php if(!empty($dns_check['missing_records'])): ?>
                                    <div style="margin-top: 10px; color: orange;">
                                        <p><strong>Note:</strong> For optimal protection, please add these missing records:</p>
                                        <ul>
                                            <?php foreach($dns_check['missing_records'] as $record): ?>
                                                <li>Add <?php echo $record['type']; ?> record: <code><?php echo $record['value']; ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">✕ Not properly configured</span>
                                <p>Your domain is not correctly pointed to the Blackwall protection servers. 
                                <a href="javascript:void(0);" onclick="openBlackwallTab('setup');" class="lbtn red">Click here for setup instructions</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="clear"></div><br>

<?php include('partials/tabs.php'); ?>

<div class="clear"></div>
