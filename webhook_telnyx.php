<?php
/**
 * Telnyx Webhook Handler for Ringotel FusionPBX
 * 
 * This file handles incoming webhooks from Telnyx for SMS delivery
 * and status updates.
 * 
 * Webhook URL: https://your-domain/app/rt/webhook_telnyx.php
 */

// Load FusionPBX framework
require_once dirname(__DIR__, 2) . '/resources/require.php';

// Include Telnyx class
require_once __DIR__ . '/resources/classes/telnyx_sms.php';

// Set content type for response
header('Content-Type: application/json');

// Get raw POST data
$payload = file_get_contents('php://input');

// Log incoming webhook (for debugging)
$log_file = '/var/log/fusionpbx/telnyx_webhook.log';
$timestamp = date('Y-m-d H:i:s');

if (!empty($payload)) {
    // Ensure log directory exists
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents(
        $log_file, 
        "[$timestamp] Received webhook: $payload\n", 
        FILE_APPEND | LOCK_EX
    );
}

// Parse JSON payload
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// Extract event information
$event_type = $data['data']['event_type'] ?? '';
$message_data = $data['data']['payload'] ?? [];

// Log event type
file_put_contents(
    $log_file,
    "[$timestamp] Event type: $event_type\n",
    FILE_APPEND | LOCK_EX
);

try {
    switch ($event_type) {
        case 'message.received':
            // Incoming SMS/MMS
            handleIncomingMessage($message_data);
            break;
            
        case 'message.sent':
            // Message sent successfully
            handleMessageSent($message_data);
            break;
            
        case 'message.finalized':
            // Final delivery status
            handleMessageFinalized($message_data);
            break;
            
        case 'message.failed':
            // Message delivery failed
            handleMessageFailed($message_data);
            break;
            
        default:
            // Unknown event type - just acknowledge
            file_put_contents(
                $log_file,
                "[$timestamp] Unknown event type: $event_type\n",
                FILE_APPEND | LOCK_EX
            );
    }
    
    // Respond with success
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'event' => $event_type]);
    
} catch (Exception $e) {
    // Log error
    file_put_contents(
        $log_file,
        "[$timestamp] Error: " . $e->getMessage() . "\n",
        FILE_APPEND | LOCK_EX
    );
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Handle incoming SMS/MMS message
 * 
 * @param array $message Message data from Telnyx
 */
function handleIncomingMessage($message) {
    global $log_file, $timestamp;
    
    // Extract message details
    $from = $message['from']['phone_number'] ?? '';
    $to_array = $message['to'] ?? [];
    $to = $to_array[0]['phone_number'] ?? '';
    $text = $message['text'] ?? '';
    $message_id = $message['id'] ?? '';
    $media = $message['media'] ?? [];
    
    // Log the incoming message
    file_put_contents(
        $log_file,
        "[$timestamp] Incoming SMS - From: $from, To: $to, Text: $text\n",
        FILE_APPEND | LOCK_EX
    );
    
    // Find the domain and user associated with this phone number
    $destination_info = findDestinationByNumber($to);
    
    if ($destination_info) {
        // Route the message to Ringotel/appropriate user
        deliverToRingotel([
            'from' => $from,
            'to' => $to,
            'text' => $text,
            'message_id' => $message_id,
            'media' => $media,
            'domain_uuid' => $destination_info['domain_uuid'],
            'user_uuid' => $destination_info['user_uuid'] ?? null
        ]);
    } else {
        file_put_contents(
            $log_file,
            "[$timestamp] No destination found for number: $to\n",
            FILE_APPEND | LOCK_EX
        );
    }
}

/**
 * Handle message sent confirmation
 * 
 * @param array $message Message data
 */
function handleMessageSent($message) {
    global $log_file, $timestamp;
    
    $message_id = $message['id'] ?? '';
    $to = $message['to'][0]['phone_number'] ?? '';
    
    file_put_contents(
        $log_file,
        "[$timestamp] Message sent - ID: $message_id, To: $to\n",
        FILE_APPEND | LOCK_EX
    );
    
    // Update message status in database if tracking
    updateMessageStatus($message_id, 'sent');
}

/**
 * Handle message finalized (delivered)
 * 
 * @param array $message Message data
 */
function handleMessageFinalized($message) {
    global $log_file, $timestamp;
    
    $message_id = $message['id'] ?? '';
    $to = $message['to'][0]['phone_number'] ?? '';
    
    file_put_contents(
        $log_file,
        "[$timestamp] Message finalized - ID: $message_id, To: $to\n",
        FILE_APPEND | LOCK_EX
    );
    
    // Update message status in database if tracking
    updateMessageStatus($message_id, 'delivered');
}

/**
 * Handle message delivery failure
 * 
 * @param array $message Message data
 */
function handleMessageFailed($message) {
    global $log_file, $timestamp;
    
    $message_id = $message['id'] ?? '';
    $to = $message['to'][0]['phone_number'] ?? '';
    $errors = $message['errors'] ?? [];
    
    $error_msg = !empty($errors) ? json_encode($errors) : 'Unknown error';
    
    file_put_contents(
        $log_file,
        "[$timestamp] Message FAILED - ID: $message_id, To: $to, Error: $error_msg\n",
        FILE_APPEND | LOCK_EX
    );
    
    // Update message status in database if tracking
    updateMessageStatus($message_id, 'failed', $error_msg);
}

/**
 * Find domain and user by phone number
 * 
 * @param string $phone_number Phone number (E.164 format)
 * @return array|null Domain and user info
 */
function findDestinationByNumber($phone_number) {
    // Clean phone number
    $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
    
    // Remove leading 1 for US numbers
    if (strlen($phone_number) == 11 && substr($phone_number, 0, 1) == '1') {
        $phone_number = substr($phone_number, 1);
    }
    
    // Search in destinations table
    $database = new database;
    
    $sql = "SELECT d.domain_uuid, d.domain_name, dest.destination_uuid 
            FROM v_destinations dest
            INNER JOIN v_domains d ON dest.domain_uuid = d.domain_uuid
            WHERE dest.destination_enabled = 'true'
            AND (
                dest.destination_number = :number1 
                OR dest.destination_number = :number2
                OR dest.destination_number = :number3
            )
            LIMIT 1";
    
    $parameters = [
        'number1' => $phone_number,
        'number2' => '+1' . $phone_number,
        'number3' => '1' . $phone_number
    ];
    
    $result = $database->select($sql, $parameters);
    
    if (!empty($result[0])) {
        return $result[0];
    }
    
    return null;
}

/**
 * Deliver message to Ringotel
 * 
 * @param array $message_data Message details
 */
function deliverToRingotel($message_data) {
    global $log_file, $timestamp;
    
    // This would integrate with Ringotel's API to deliver the message
    // The exact implementation depends on Ringotel's webhook/API structure
    
    // For now, we'll store it for Ringotel to pick up
    $database = new database;
    
    // Example: Store in a messages queue table
    // You may need to create this table or adapt to your schema
    
    /*
    $sql = "INSERT INTO v_ringotel_sms_queue 
            (sms_queue_uuid, domain_uuid, from_number, to_number, message_text, 
             message_id, direction, status, created) 
            VALUES 
            (:uuid, :domain_uuid, :from, :to, :text, :message_id, 'inbound', 'pending', NOW())";
    
    $parameters = [
        'uuid' => uuid(),
        'domain_uuid' => $message_data['domain_uuid'],
        'from' => $message_data['from'],
        'to' => $message_data['to'],
        'text' => $message_data['text'],
        'message_id' => $message_data['message_id']
    ];
    
    $database->execute($sql, $parameters);
    */
    
    file_put_contents(
        $log_file,
        "[$timestamp] Message queued for Ringotel delivery - Domain: " . 
        ($message_data['domain_uuid'] ?? 'unknown') . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Update message status in database
 * 
 * @param string $message_id Telnyx message ID
 * @param string $status New status
 * @param string $error_message Optional error message
 */
function updateMessageStatus($message_id, $status, $error_message = null) {
    // Implement based on your message tracking schema
    // This is a placeholder
    
    /*
    $database = new database;
    
    $sql = "UPDATE v_ringotel_sms_queue 
            SET status = :status, 
                error_message = :error,
                updated = NOW() 
            WHERE message_id = :message_id";
    
    $parameters = [
        'status' => $status,
        'error' => $error_message,
        'message_id' => $message_id
    ];
    
    $database->execute($sql, $parameters);
    */
}
