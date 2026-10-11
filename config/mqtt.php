<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MQTT Broker Connection
    |--------------------------------------------------------------------------
    |
    | Used both by the mqtt:listen daemon (one persistent connection,
    | subscribed to parkir/+/scan and parkir/+/status) and by
    | MqttPublisherService (a short-lived connect-publish-disconnect
    | per call, from the HTTP process handling approve/reject/simulate
    | requests). Username/password may be left blank for a local broker
    | that has no auth configured yet.
    |
    */

    'host' => env('MQTT_HOST', 'localhost'),
    'port' => env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Client ID Prefix
    |--------------------------------------------------------------------------
    |
    | The daemon and the publisher service must never share a literal
    | client ID — most brokers disconnect whichever client held an ID
    | first when a second client connects with the same one. Each
    | connector appends its own suffix to this prefix (e.g. "-listener"
    | for the daemon, "-publisher-{uniqid}" per publish call).
    |
    */

    'client_id_prefix' => env('MQTT_CLIENT_ID_PREFIX', 'two-guards'),

    /*
    |--------------------------------------------------------------------------
    | Publisher Connect Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds MqttPublisherService waits to connect to the broker before
    | giving up. It runs inside the approve/reject HTTP request, so this
    | is how long a user is left waiting when the broker is unreachable —
    | php-mqtt's own default of 60 seconds is far too long for that.
    |
    */

    'connect_timeout' => env('MQTT_CONNECT_TIMEOUT', 3),

];
