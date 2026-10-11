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
    | giving up. Every attempt of the PublishDeviceCommand job has to fit
    | inside the command's short TTL below, so php-mqtt's own default of
    | 60 seconds is far too long for it.
    |
    */

    'connect_timeout' => env('MQTT_CONNECT_TIMEOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | Publisher PUBACK Timeout
    |--------------------------------------------------------------------------
    |
    | Whole seconds MqttPublisherService waits for the broker to acknowledge
    | a QoS 1 command before treating it as not delivered (the job then
    | retries until the TTL). A local broker answers within milliseconds.
    |
    */

    'puback_timeout' => env('MQTT_PUBACK_TIMEOUT', 2),

    /*
    |--------------------------------------------------------------------------
    | Command Time-To-Live
    |--------------------------------------------------------------------------
    |
    | Seconds after the decision (the command's issued_at) during which an
    | open/deny command may still be sent. Past that, every sender drops
    | it instead: a gate opening long after the driver gave up is worse
    | than a command that never arrives. The ESP32 applies its own,
    | looser threshold on top of this (see docs/mqtt-topics.md).
    |
    */

    'command_ttl' => env('MQTT_COMMAND_TTL', 10),

];
