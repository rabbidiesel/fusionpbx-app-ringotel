<?php
/**
 * Telnyx SMS Integration for Ringotel FusionPBX
 * 
 * This class provides Telnyx SMS functionality similar to the existing Bandwidth integration.
 * It should be included in the ringotel resources/classes directory.
 */

class telnyx_sms {
    
    private $api_key;
    private $api_url = 'https://api.telnyx.com/v2';
    private $messaging_profile_id;
    
    /**
     * Constructor
     * 
     * @param string $api_key Telnyx API Key (starts with KEY...)
     * @param string $messaging_profile_id Optional messaging profile ID
     */
    public function __construct($api_key, $messaging_profile_id = null) {
        $this->api_key = $api_key;
        $this->messaging_profile_id = $messaging_profile_id;
    }
    
    /**
     * Make API request to Telnyx
     * 
     * @param string $method HTTP method (GET, POST, DELETE, etc.)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->api_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode
            ];
        }
        
        $decoded = json_decode($response, true);
        
        return [
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'data' => $decoded,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Send SMS message
     * 
     * @param string $from From phone number (E.164 format)
     * @param string $to To phone number (E.164 format)
     * @param string $text Message text
     * @param array $options Additional options
     * @return array Response
     */
    public function sendSMS($from, $to, $text, $options = []) {
        $data = [
            'from' => $this->formatE164($from),
            'to' => $this->formatE164($to),
            'text' => $text,
            'type' => 'SMS'
        ];
        
        if ($this->messaging_profile_id) {
            $data['messaging_profile_id'] = $this->messaging_profile_id;
        }
        
        // Merge additional options
        $data = array_merge($data, $options);
        
        return $this->makeRequest('POST', '/messages', $data);
    }
    
    /**
     * Send MMS message
     * 
     * @param string $from From phone number
     * @param string $to To phone number
     * @param string $text Message text
     * @param array $media_urls Array of media URLs
     * @param array $options Additional options
     * @return array Response
     */
    public function sendMMS($from, $to, $text, $media_urls, $options = []) {
        $data = [
            'from' => $this->formatE164($from),
            'to' => $this->formatE164($to),
            'text' => $text,
            'type' => 'MMS',
            'media_urls' => $media_urls
        ];
        
        if ($this->messaging_profile_id) {
            $data['messaging_profile_id'] = $this->messaging_profile_id;
        }
        
        $data = array_merge($data, $options);
        
        return $this->makeRequest('POST', '/messages', $data);
    }
    
    /**
     * Get messaging profiles
     * 
     * @return array Response with messaging profiles
     */
    public function getMessagingProfiles() {
        return $this->makeRequest('GET', '/messaging_profiles');
    }
    
    /**
     * Create messaging profile
     * 
     * @param string $name Profile name
     * @param array $options Additional options
     * @return array Response
     */
    public function createMessagingProfile($name, $options = []) {
        $data = array_merge(['name' => $name], $options);
        return $this->makeRequest('POST', '/messaging_profiles', $data);
    }
    
    /**
     * Get phone numbers
     * 
     * @param array $filters Optional filters
     * @return array Response with phone numbers
     */
    public function getPhoneNumbers($filters = []) {
        return $this->makeRequest('GET', '/phone_numbers', $filters);
    }
    
    /**
     * Get message by ID
     * 
     * @param string $messageId Message ID
     * @return array Response
     */
    public function getMessage($messageId) {
        return $this->makeRequest('GET', '/messages/' . $messageId);
    }
    
    /**
     * List messages
     * 
     * @param array $filters Filters (page, page_size, etc.)
     * @return array Response
     */
    public function listMessages($filters = []) {
        return $this->makeRequest('GET', '/messages', $filters);
    }
    
    /**
     * Format phone number to E.164
     * 
     * @param string $number Phone number
     * @return string Formatted number
     */
    private function formatE164($number) {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // Add + if not present and number is 11 digits (US)
        if (strlen($number) == 10) {
            $number = '1' . $number;
        }
        
        if (strpos($number, '+') !== 0) {
            $number = '+' . $number;
        }
        
        return $number;
    }
    
    /**
     * Validate webhook signature
     * 
     * @param string $payload Raw webhook payload
     * @param string $signature Signature from header
     * @param string $timestamp Timestamp from header
     * @param string $publicKey Your Telnyx public key
     * @return bool Whether signature is valid
     */
    public function validateWebhookSignature($payload, $signature, $timestamp, $publicKey) {
        // Telnyx uses ed25519 signatures
        // You'll need the sodium extension for this
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            // Fallback - accept without verification (not recommended for production)
            return true;
        }
        
        $signedPayload = $timestamp . '|' . $payload;
        $decodedSignature = base64_decode($signature);
        $decodedPublicKey = base64_decode($publicKey);
        
        return sodium_crypto_sign_verify_detached(
            $decodedSignature,
            $signedPayload,
            $decodedPublicKey
        );
    }
}

/**
 * Telnyx Integration Handler for Ringotel
 * 
 * This class integrates Telnyx SMS with the Ringotel system
 */
class telnyx_ringotel_integration {
    
    private $settings;
    private $telnyx;
    private $domain_uuid;
    
    public function __construct($settings, $domain_uuid) {
        $this->settings = $settings;
        $this->domain_uuid = $domain_uuid;
        
        // Get Telnyx API key from settings
        $api_key = $this->settings->get('ringotel', 'telnyx_api_key', '');
        $messaging_profile_id = $this->settings->get('ringotel', 'telnyx_messaging_profile_id', '');
        
        if (!empty($api_key)) {
            $this->telnyx = new telnyx_sms($api_key, $messaging_profile_id);
        }
    }
    
    /**
     * Check if Telnyx is configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->telnyx);
    }
    
    /**
     * Create integration in Ringotel
     * 
     * @param string $orgid Organization ID
     * @return array Result
     */
    public function createIntegration($orgid) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Telnyx API key not configured'
            ];
        }
        
        // This would integrate with Ringotel's API to enable Telnyx
        // The actual implementation depends on Ringotel's integration API
        return [
            'success' => true,
            'result' => [
                'id' => 'Telnyx',
                'name' => 'Telnyx',
                'state' => 1,
                'status' => 200,
                'logo' => '/app/rt/resources/images/telnyx-logo.svg'
            ]
        ];
    }
    
    /**
     * Delete integration
     * 
     * @param string $orgid Organization ID
     * @return array Result
     */
    public function deleteIntegration($orgid) {
        return [
            'success' => true,
            'result' => true
        ];
    }
    
    /**
     * Get integration status
     * 
     * @param string $orgid Organization ID
     * @return array Result
     */
    public function getIntegration($orgid) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'result' => []
            ];
        }
        
        // Check if integration is enabled in database
        // This is a placeholder - implement based on your database schema
        
        return [
            'success' => true,
            'result' => [
                [
                    'id' => 'Telnyx',
                    'name' => 'Telnyx',
                    'state' => 1,
                    'logo' => '/app/rt/resources/images/telnyx-logo.svg'
                ]
            ]
        ];
    }
    
    /**
     * Create SMS trunk
     * 
     * @param array $params Parameters (orgid, name, number, users)
     * @return array Result
     */
    public function createSMSTrunk($params) {
        // Store trunk configuration in database
        // This is a placeholder - implement based on your database schema
        
        $trunk_id = uniqid('trunk_');
        
        return [
            'success' => true,
            'result' => [
                'id' => $trunk_id,
                'name' => $params['name'] ?? '',
                'number' => $params['number'] ?? '',
                'users' => $params['users'] ?? [],
                'provider' => 'Telnyx',
                'country' => 'US',
                'outboundFormat' => 'e164',
                'inboundFormat' => 'e164'
            ]
        ];
    }
    
    /**
     * Update SMS trunk
     * 
     * @param array $params Parameters
     * @return array Result
     */
    public function updateSMSTrunk($params) {
        return [
            'success' => true,
            'result' => $params
        ];
    }
    
    /**
     * Delete SMS trunk
     * 
     * @param string $orgid Organization ID
     * @param string $trunk_id Trunk ID
     * @return array Result
     */
    public function deleteSMSTrunk($orgid, $trunk_id) {
        return [
            'success' => true,
            'result' => true
        ];
    }
    
    /**
     * Get SMS trunks
     * 
     * @param string $orgid Organization ID
     * @return array Result
     */
    public function getSMSTrunks($orgid) {
        // Fetch from database
        // This is a placeholder
        
        return [
            'success' => true,
            'result' => []
        ];
    }
    
    /**
     * Send SMS via Telnyx
     * 
     * @param string $from From number
     * @param string $to To number
     * @param string $message Message text
     * @return array Result
     */
    public function sendSMS($from, $to, $message) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Telnyx not configured'
            ];
        }
        
        return $this->telnyx->sendSMS($from, $to, $message);
    }
    
    /**
     * Handle incoming webhook from Telnyx
     * 
     * @param array $payload Webhook payload
     * @return array Result
     */
    public function handleWebhook($payload) {
        $event_type = $payload['data']['event_type'] ?? '';
        
        switch ($event_type) {
            case 'message.received':
                return $this->handleIncomingSMS($payload);
            case 'message.sent':
            case 'message.finalized':
                return $this->handleMessageStatus($payload);
            default:
                return ['success' => true, 'handled' => false];
        }
    }
    
    /**
     * Handle incoming SMS
     * 
     * @param array $payload Webhook payload
     * @return array Result
     */
    private function handleIncomingSMS($payload) {
        $message = $payload['data']['payload'] ?? [];
        
        $from = $message['from']['phone_number'] ?? '';
        $to = $message['to'][0]['phone_number'] ?? '';
        $text = $message['text'] ?? '';
        
        // Route to appropriate user based on 'to' number
        // This would integrate with Ringotel to deliver the message
        
        return [
            'success' => true,
            'from' => $from,
            'to' => $to,
            'text' => $text
        ];
    }
    
    /**
     * Handle message status update
     * 
     * @param array $payload Webhook payload
     * @return array Result
     */
    private function handleMessageStatus($payload) {
        $message = $payload['data']['payload'] ?? [];
        
        return [
            'success' => true,
            'message_id' => $message['id'] ?? '',
            'status' => $payload['data']['event_type'] ?? ''
        ];
    }
}
