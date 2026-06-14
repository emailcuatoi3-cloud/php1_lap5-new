<?php

/**
 * Gửi sự kiện realtime lên Pusher
 *
 * @param string $eventName
 * @param array $data
 * @return bool
 */

function broadcastRealtime($eventName, $data = [])
{
    $APP_ID     = "2165330";
    $APP_KEY    = "94c4c17f4353f8cdc5af";
    $APP_SECRET = "dd8e86dc55a80ca269fa";
    $CLUSTER    = "ap1";

    try {

        $payload = json_encode([
            "name" => $eventName,
            "channels" => ["store-channel"],
            "data" => json_encode($data, JSON_UNESCAPED_UNICODE)
        ], JSON_UNESCAPED_UNICODE);

        $body_md5 = md5($payload);
        $timestamp = time();

        $string_to_sign =
            "POST\n" .
            "/apps/{$APP_ID}/events\n" .
            "auth_key={$APP_KEY}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$body_md5}";

        $auth_signature = hash_hmac(
            "sha256",
            $string_to_sign,
            $APP_SECRET
        );

        $url =
            "https://api-{$CLUSTER}.pusher.com/apps/{$APP_ID}/events" .
            "?auth_key={$APP_KEY}" .
            "&auth_timestamp={$timestamp}" .
            "&auth_version=1.0" .
            "&body_md5={$body_md5}" .
            "&auth_signature={$auth_signature}";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Content-Length: " . strlen($payload)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        file_put_contents(
            __DIR__ . "/pusher_log.txt",
            "\n============================\n" .
            date("Y-m-d H:i:s") . "\n" .
            "EVENT: {$eventName}\n" .
            "HTTP CODE: {$httpCode}\n" .
            "CURL ERROR: {$curlError}\n" .
            "RESPONSE: {$response}\n",
            FILE_APPEND
        );

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        return false;

    } catch (Exception $e) {

        file_put_contents(
            __DIR__ . "/pusher_log.txt",
            "\n============================\n" .
            date("Y-m-d H:i:s") . "\n" .
            "EXCEPTION: " . $e->getMessage() . "\n",
            FILE_APPEND
        );

        return false;
    }
}
?>