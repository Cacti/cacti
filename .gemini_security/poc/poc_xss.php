<?php
// Mock Cacti environment
define('MESSAGE_LEVEL_NONE', 0);
define('MESSAGE_LEVEL_INFO', 1);
define('MESSAGE_LEVEL_WARN', 2);
define('MESSAGE_LEVEL_ERROR', 3);
define('MESSAGE_LEVEL_CSRF', 4);

function __($str, ...$args) {
    return vsprintf($str, $args);
}

function get_message_level($predefined) {
    return is_array($predefined) ? ($predefined['level'] ?? MESSAGE_LEVEL_INFO) : MESSAGE_LEVEL_INFO;
}

function get_message_max_type() {
    return MESSAGE_LEVEL_INFO;
}

function get_format_message_instance($current_message) {
    if (is_array($current_message)) {
        $fmessage = isset($current_message['message']) ? $current_message['message'] : 'Message Not Found.';
    } else {
        $fmessage = $current_message;
    }

    $level = get_message_level($current_message);

    switch ($level) {
        case MESSAGE_LEVEL_NONE:
            $message = '<span>' . $fmessage . '</span>';
            break;
        case MESSAGE_LEVEL_INFO:
            $message = '<span class="deviceUp">' . $fmessage . '</span>';
            break;
        case MESSAGE_LEVEL_WARN:
            $message = '<span class="deviceWarning">' . $fmessage . '</span>';
            break;
        case MESSAGE_LEVEL_ERROR:
            $message = '<span class="deviceDown">' . $fmessage . '</span>';
            break;
        default:
            $message = '<span class="deviceUnknown">' . $fmessage . '</span>';
            break;
    }

    return $message;
}

function raise_message($message_id, $message = '', $message_level = MESSAGE_LEVEL_NONE) {
    global $_SESSION;
    $_SESSION['sess_messages'][$message_id] = array('message' => $message, 'level' => $message_level);
}

function display_output_messages() {
    global $_SESSION;
    $omessage = array();
    if (isset($_SESSION['sess_messages'])) {
        $omessage['level'] = get_message_max_type();
        foreach ($_SESSION['sess_messages'] as $current_message_id => $current_message) {
            $message = get_format_message_instance($current_message);
            if (!empty($message)) {
                $omessage['message'] = (isset($omessage['message']) && $omessage['message'] != '' ? $omessage['message'] . '<br>':'') . $message;
            }
        }
    }
    return json_encode($omessage);
}

// Reproduction
$_SESSION = [];
$xml_payload = "<script>alert('XSS')</script>";
$package_name = $xml_payload;

echo "Simulating import of package: $package_name\n";
raise_message('import_success', __("The Package %s Imported Successfully", $package_name), MESSAGE_LEVEL_INFO);

$output = display_output_messages();
echo "Output JSON:\n$output\n\n";

// The script tag is present in the message string within the JSON
if (strpos($output, "<script") !== false) {
    echo "VULNERABILITY REPRODUCED: XSS payload found unescaped in output message.\n";
    exit(0);
} else {
    echo "VULNERABILITY NOT REPRODUCED: XSS payload was escaped.\n";
    exit(1);
}
