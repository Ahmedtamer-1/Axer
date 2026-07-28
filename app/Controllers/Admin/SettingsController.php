<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;

class SettingsController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request, 'superadmin');

        $error = null;
        $success = null;

        if ($request->method() === 'POST') {
            $settingsData = $request->post('settings') ?? [];

            try {
                $this->saveSettings(is_array($settingsData) ? $settingsData : []);

                // Store name/currency feed the storefront's global context,
                // which memoizes per request — flush so the change is
                // visible without waiting for a template file edit.
                ThemeService::flushCaches();

                $success = 'Settings updated successfully.';
            } catch (\Throwable $e) {
                Logger::exception($e);
                $error = 'Failed to update settings. Please try again.';
            }
        }

        $settings = [];

        try {
            foreach (QueryBuilder::table('settings')->get() as $row) {
                $settings[$row['group']][$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not load settings: ' . $e->getMessage());
        }

        return $this->renderAdmin('settings/index', [
            'title' => 'Settings',
            'settings' => $settings,
            'error' => $error,
            'success' => $success,
        ]);
    }

    /**
     * Upsert every posted setting.
     *
     * The previous version ran a SELECT followed by an INSERT or UPDATE
     * for every single key, inside a group/key double loop — a form with
     * twenty fields cost up to forty queries. Existing rows are now loaded
     * once, and everything happens inside a transaction so a partial
     * failure cannot leave the settings half-written.
     */
    protected function saveSettings(array $settingsData): void
    {
        $flat = [];

        foreach ($settingsData as $group => $keys) {
            if (!is_array($keys)) {
                continue;
            }

            foreach ($keys as $key => $value) {
                $flat[(string) $group][(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
            }
        }

        if ($flat === []) {
            return;
        }

        QueryBuilder::transaction(function () use ($flat) {
            $groups = array_keys($flat);

            $existing = QueryBuilder::table('settings')->whereIn('group', $groups)->get();
            $existingKeys = [];

            foreach ($existing as $row) {
                $existingKeys[$row['group'] . '::' . $row['key']] = true;
            }

            foreach ($flat as $group => $keys) {
                foreach ($keys as $key => $value) {
                    if (isset($existingKeys[$group . '::' . $key])) {
                        QueryBuilder::table('settings')
                            ->where('group', $group)
                            ->where('key', $key)
                            ->update(['value' => $value]);
                    } else {
                        QueryBuilder::table('settings')->insert([
                            'group' => $group,
                            'key' => $key,
                            'value' => $value,
                            'type' => 'string',
                        ]);
                    }
                }
            }
        });
    }
}
