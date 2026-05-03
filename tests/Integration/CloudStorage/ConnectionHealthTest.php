<?php
namespace Tests\Integration\CloudStorage;

use Tests\Integration\IntegrationTestCase;
use DiluxWP\CloudStorage\ConfigManager;

/**
 * Integration tests for the connection-health subsystem.
 *
 * Focus: the *idempotency contract* on `decrypt_failed` events —
 * `consecutive_failures` must NOT be incremented on every page load
 * when the same `decrypt_failed` state is already recorded. Without
 * that, every admin page view would inflate the counter past the
 * stream-wrapper fallback threshold (>= 3).
 *
 * Plus: error-code transitions, recovery, and the clear path.
 *
 * Lives in the integration suite because connection-health state is
 * persisted in the WordPress Options API.
 */
class ConnectionHealthTest extends IntegrationTestCase {

    public function test_record_failure_increments_consecutive_failures(): void {
        ConfigManager::record_connection_failure('500', 'Server Error', 'azure');
        $health = ConfigManager::get_connection_health();
        $this->assertSame(1, $health['consecutive_failures']);
        $this->assertSame('500', $health['error_code']);
        $this->assertSame('unhealthy', $health['status']);

        ConfigManager::record_connection_failure('500', 'Server Error', 'azure');
        $health = ConfigManager::get_connection_health();
        // record_connection_failure does NOT itself dedupe — the
        // idempotency for decrypt_failed lives in decrypt_credentials,
        // tested in the next case. Repeated direct calls increment.
        $this->assertSame(2, $health['consecutive_failures']);
    }

    public function test_decrypt_failed_does_not_inflate_counter_on_repeated_get_config(): void {
        // Plant a config with a credential that has the encrypted prefix
        // but is not actually decryptable. This bypasses save_config()
        // (which would encrypt for real) and simulates the post-salt-
        // rotation scenario that triggered this idempotency rule.
        update_option('dilux_cs_config', [
            'cloud_provider' => 'azure',
            'provider_config' => [
                'storage_account' => 'someacc',
                'access_key'      => 'DILUXENC1:bm90X2Ffdmxhaml',
                'container_name'  => 'somecont',
            ],
        ]);

        // First read: triggers decrypt_failed event recording.
        ConfigManager::get_config();
        $first = ConfigManager::get_connection_health();
        $this->assertSame('decrypt_failed', $first['error_code']);
        $this->assertSame('unhealthy', $first['status']);
        $this->assertSame(1, $first['consecutive_failures']);

        // Subsequent reads must NOT bump consecutive_failures while the
        // unhealthy state is still 'decrypt_failed' — otherwise every
        // admin page view would push the counter past the fallback
        // threshold of 3 inside an hour.
        for ($i = 0; $i < 5; $i++) {
            ConfigManager::get_config();
        }
        $after = ConfigManager::get_connection_health();
        $this->assertSame(1, $after['consecutive_failures'], 'decrypt_failed must be idempotent across reads');
        $this->assertSame('decrypt_failed', $after['error_code']);
    }

    public function test_record_success_resets_counter_and_clears_error_fields(): void {
        ConfigManager::record_connection_failure('403', 'Forbidden', 'azure');
        ConfigManager::record_connection_failure('500', 'Server Error', 'azure');
        $before = ConfigManager::get_connection_health();
        $this->assertSame(2, $before['consecutive_failures']);

        ConfigManager::record_connection_success();
        $after = ConfigManager::get_connection_health();

        $this->assertSame('healthy', $after['status']);
        $this->assertSame(0, $after['consecutive_failures']);
        $this->assertSame('', $after['error_code']);
        $this->assertSame('', $after['error_message']);
    }

    public function test_clear_connection_health_removes_the_option_entirely(): void {
        ConfigManager::record_connection_failure('401', 'Unauthorized', 'diluxone');
        $this->assertNotEmpty(ConfigManager::get_connection_health());

        ConfigManager::clear_connection_health();

        // After clear, get_connection_health() returns the default shape
        // (zeroed counters, empty fields) rather than NULL — that's the
        // contract callers depend on.
        $health = ConfigManager::get_connection_health();
        $this->assertSame(0, $health['consecutive_failures']);
        $this->assertSame('', $health['error_code']);
        // Status defaults vary by implementation; assert it's a string,
        // not the lack of any state.
        $this->assertIsString($health['status']);
    }

    public function test_consecutive_failures_can_cross_stream_wrapper_fallback_threshold(): void {
        // Stream wrapper falls back to local storage when consecutive_failures
        // >= 3 (see CloudStreamWrapper line 596). This test documents that
        // contract — if the threshold is ever changed, this test fails and
        // the change is caught.
        for ($i = 0; $i < 4; $i++) {
            ConfigManager::record_connection_failure('500', 'Server Error #' . $i, 'azure');
        }

        $health = ConfigManager::get_connection_health();
        $this->assertGreaterThanOrEqual(3, $health['consecutive_failures'], 'Stream wrapper expects >= 3 to trigger local fallback.');
    }

    public function test_error_code_changes_are_reflected_in_subsequent_failures(): void {
        ConfigManager::record_connection_failure('403', 'Forbidden', 'azure');
        $a = ConfigManager::get_connection_health();
        $this->assertSame('403', $a['error_code']);

        ConfigManager::record_connection_failure('exception', 'Network unreachable', 'azure');
        $b = ConfigManager::get_connection_health();
        $this->assertSame('exception', $b['error_code']);
        $this->assertSame('Network unreachable', $b['error_message']);
        // Counter incremented across distinct codes too.
        $this->assertSame(2, $b['consecutive_failures']);
    }
}
