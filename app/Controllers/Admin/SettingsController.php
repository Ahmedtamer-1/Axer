<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class SettingsController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $error = null;
        $success = null;

        if ($request->method() === 'POST') {
            $settingsData = $request->post('settings') ?? [];
            try {
                foreach ($settingsData as $group => $keys) {
                    foreach ($keys as $key => $value) {
                        // Check if exists
                        $existing = QueryBuilder::table('settings')
                            ->where('group', $group)
                            ->where('key', $key)
                            ->first();

                        if ($existing) {
                            QueryBuilder::table('settings')
                                ->where('group', $group)
                                ->where('key', $key)
                                ->update(['value' => $value]);
                        } else {
                            QueryBuilder::table('settings')->insert([
                                'group' => $group,
                                'key' => $key,
                                'value' => $value,
                                'type' => 'string'
                            ]);
                        }
                    }
                }
                $success = "Settings updated successfully.";
            } catch (\Exception $e) {
                $error = "Failed to update settings: " . $e->getMessage();
            }
        }

        $settingsList = QueryBuilder::table('settings')->get();
        $settings = [];
        foreach ($settingsList as $s) {
            $settings[$s['group']][$s['key']] = $s['value'];
        }

        return $this->renderAdmin('settings/index', [
            'title' => 'Settings',
            'settings' => $settings,
            'error' => $error,
            'success' => $success
        ]);
    }
}
