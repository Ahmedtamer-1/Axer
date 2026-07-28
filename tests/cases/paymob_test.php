<?php

use Axer\Services\PaymobService;

group('Paymob callback verification');

$secret = 'test-hmac-secret';
$gateway = new PaymobService('key', 'integration', 'iframe', $secret);

/**
 * Build a callback body plus the signature Paymob would send with it.
 */
$sign = static function (array $overrides = []) use ($secret): array {
    $obj = array_merge([
        'amount_cents' => 15000,
        'created_at' => '2026-07-28T10:00:00.000000',
        'currency' => 'EGP',
        'error_occured' => false,
        'has_parent_transaction' => false,
        'id' => 987654,
        'integration_id' => 111222,
        'is_3d_secure' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_refunded' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'order' => ['id' => 555444],
        'owner' => 333,
        'pending' => false,
        'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
        'success' => true,
    ], $overrides);

    $fields = [
        'amount_cents', 'created_at', 'currency', 'error_occured',
        'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
        'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan',
        'source_data.sub_type', 'source_data.type', 'success',
    ];

    $concatenated = '';

    foreach ($fields as $field) {
        $value = $obj;

        foreach (explode('.', $field) as $segment) {
            $value = is_array($value) && array_key_exists($segment, $value) ? $value[$segment] : null;
        }

        if (is_bool($value)) {
            $concatenated .= $value ? 'true' : 'false';
        } else {
            $concatenated .= (string) ($value ?? '');
        }
    }

    return [
        'payload' => ['obj' => $obj],
        'hmac' => hash_hmac('sha512', $concatenated, $secret),
    ];
};

test('a correctly signed callback is accepted', function () use ($gateway, $sign) {
    $signed = $sign();

    assertTrue($gateway->verifyHmac($signed['payload'], $signed['hmac']));
});

test('a forged callback with no signature is rejected', function () use ($gateway) {
    // This is exactly what the old handler accepted: any JSON body with an
    // obj.order.id was enough to mark an order paid.
    $forged = ['obj' => ['order' => ['id' => 555444], 'success' => true]];

    assertFalse($gateway->verifyHmac($forged, null));
    assertFalse($gateway->verifyHmac($forged, ''));
});

test('a tampered amount invalidates the signature', function () use ($gateway, $sign) {
    $signed = $sign();
    $signed['payload']['obj']['amount_cents'] = 1;

    assertFalse($gateway->verifyHmac($signed['payload'], $signed['hmac']));
});

test('flipping success to true invalidates the signature', function () use ($gateway, $sign) {
    $signed = $sign(['success' => false]);
    $signed['payload']['obj']['success'] = true;

    assertFalse($gateway->verifyHmac($signed['payload'], $signed['hmac']));
});

test('a signature from a different secret is rejected', function () use ($gateway, $sign) {
    $signed = $sign();
    $wrong = new PaymobService('key', 'integration', 'iframe', 'someone-elses-secret');
    $forgedHmac = 'ff' . substr($signed['hmac'], 2);

    assertFalse($gateway->verifyHmac($signed['payload'], $forgedHmac));
    assertFalse($wrong->verifyHmac($signed['payload'], $signed['hmac']));
});

test('verification fails closed when no secret is configured', function () use ($sign) {
    $unconfigured = new PaymobService('key', 'integration', 'iframe', '');
    $signed = $sign();

    assertFalse($unconfigured->verifyHmac($signed['payload'], $signed['hmac']));
});

test('a missing boolean field does not raise a warning', function () use ($gateway) {
    // The previous implementation compared $obj['error_occured'] === true
    // directly, warning whenever the key was absent.
    $partial = ['obj' => ['order' => ['id' => 1], 'success' => true]];

    assertFalse($gateway->verifyHmac($partial, str_repeat('a', 128)));
});

test('isConfigured reflects missing credentials', function () {
    assertTrue((new PaymobService('a', 'b', 'c', 'd'))->isConfigured());
    assertFalse((new PaymobService('', 'b', 'c', 'd'))->isConfigured());
    assertFalse((new PaymobService('a', '', 'c', 'd'))->isConfigured());
    assertFalse((new PaymobService('a', 'b', '', 'd'))->isConfigured());
});
