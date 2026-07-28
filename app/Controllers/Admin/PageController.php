<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;
use Axer\Support\Str;

class PageController extends AdminController
{
    private const STATUSES = ['published', 'draft'];

    public function index(Request $request): Response
    {
        $this->checkAuth($request);

        $pages = [];

        try {
            $pages = QueryBuilder::table('pages')->orderBy('sort_order', 'asc')->get();
        } catch (\Throwable $e) {
            Logger::warning('Could not load pages: ' . $e->getMessage());
        }

        return $this->renderAdmin('pages/index', [
            'title' => 'Manage Pages',
            'pages' => $pages,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->checkAuth($request);
        $error = null;

        if ($request->method() === 'POST') {
            $title = $request->string('title');
            $slug = $request->string('slug') !== ''
                ? Str::slug($request->string('slug'))
                : Str::slugOrFallback($title, 'page');
            $content = $request->post('content');
            $template = $request->string('template', 'page');
            $status = $request->string('status', 'draft');
            $status = in_array($status, self::STATUSES, true) ? $status : 'draft';

            if ($title === '') {
                $error = 'Page title is required.';
            } else {
                try {
                    $existing = QueryBuilder::table('pages')->where('slug', $slug)->first();

                    if ($existing !== null) {
                        $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 6);
                    }

                    $id = QueryBuilder::table('pages')->insertGetId([
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'template' => $template,
                        'status' => $status,
                        'builder_data' => json_encode([]),
                    ]);

                    Session::flash('success', 'Page created.');

                    return $this->redirect('/admin/pages/edit/' . $id);
                } catch (\Throwable $e) {
                    Logger::exception($e);
                    $error = 'Could not create the page. The slug may already be in use.';
                }
            }
        }

        return $this->renderAdmin('pages/create', [
            'title' => 'Create Page',
            'error' => $error,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        $error = null;
        $pageId = (int) $id;

        $page = QueryBuilder::table('pages')->where('id', $pageId)->first();

        if ($page === null) {
            Session::flash('error', 'That page no longer exists.');

            return $this->redirect('/admin/pages');
        }

        if ($request->method() === 'POST') {
            $title = $request->string('title');
            $slug = $request->string('slug') !== '' ? Str::slug($request->string('slug')) : $page['slug'];
            $content = $request->post('content');
            $template = $request->string('template', 'page');
            $status = $request->string('status', 'draft');
            $status = in_array($status, self::STATUSES, true) ? $status : 'draft';

            if ($title === '') {
                $error = 'Page title is required.';
            } else {
                try {
                    QueryBuilder::table('pages')->where('id', $pageId)->update([
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'template' => $template,
                        'status' => $status,
                    ]);

                    Session::flash('success', 'Page updated.');

                    return $this->redirect('/admin/pages');
                } catch (\Throwable $e) {
                    Logger::exception($e);
                    $error = 'Could not update the page. The slug may already be in use.';
                }
            }
        }

        return $this->renderAdmin('pages/edit', [
            'title' => 'Edit Page',
            'page' => $page,
            'error' => $error,
        ]);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        try {
            QueryBuilder::table('pages')->where('id', (int) $id)->delete();
            Session::flash('success', 'Page deleted.');
        } catch (\Throwable $e) {
            Logger::exception($e);
            Session::flash('error', 'Could not delete the page.');
        }

        return $this->redirect('/admin/pages');
    }

    public function builder(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        $page = QueryBuilder::table('pages')->where('id', (int) $id)->first();

        if ($page === null) {
            Session::flash('error', 'That page no longer exists.');

            return $this->redirect('/admin/pages');
        }

        return $this->renderAdmin('pages/builder', [
            'title' => 'Visual Page Builder — ' . $page['title'],
            'page' => $page,
        ], false);
    }

    public function saveBuilder(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        $data = $request->json('builder_data');

        if (!is_array($data)) {
            return Response::json(['success' => false, 'message' => 'Invalid builder data.'], 400);
        }

        try {
            QueryBuilder::table('pages')->where('id', (int) $id)->update([
                'builder_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);

            return Response::json(['success' => true]);
        } catch (\Throwable $e) {
            Logger::exception($e);

            return Response::json(['success' => false, 'message' => 'Could not save the page.'], 500);
        }
    }

    /**
     * Live preview endpoint for the visual builder.
     *
     * Renders exactly one theme's section templates when they exist, with
     * a plain-HTML fallback otherwise — the same rule the real storefront
     * page renderer applies, so what the merchant previews matches what
     * gets published.
     */
    public function preview(Request $request): Response
    {
        $this->checkAuth($request);

        $blocks = $request->json('builder_data');
        $blocks = is_array($blocks) ? $blocks : [];

        $themePath = BASE_PATH . '/content/themes/default';
        $engine = new \Axer\Template\Engine([$themePath], BASE_PATH . '/storage/cache/templates');

        $html = $this->previewShell();

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];

            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $type)) {
                continue;
            }

            if (is_file($themePath . "/sections/{$type}.lume")) {
                try {
                    $html .= $engine->render("sections/{$type}", $settings);
                } catch (\Throwable $e) {
                    $html .= '<div style="padding:2rem;color:#b91c1c;border:1px dashed #b91c1c;">'
                        . 'Error rendering ' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</div>';
                }

                continue;
            }

            $html .= $this->fallbackBlock($type, $settings);
        }

        return new Response($html . '</body></html>');
    }

    protected function previewShell(): string
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Preview</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Outfit', sans-serif; margin: 0; padding: 0; background: #fff; color: #1e293b; }
                .section { padding: 4rem 2rem; box-sizing: border-box; }
                .hero-section { text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; }
                .hero-section h1 { font-size: 3rem; margin-bottom: 1rem; font-weight: 700; }
                .hero-section p { font-size: 1.25rem; margin-bottom: 2rem; max-width: 600px; opacity: 0.9; }
                .btn { display: inline-block; padding: 0.75rem 2rem; font-weight: 600; text-decoration: none; border-radius: 0.375rem; }
                .grid-products { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; max-width: 1200px; margin: 2rem auto 0; }
                .product-card { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; text-align: center; }
                .product-img { background: #f1f5f9; height: 200px; border-radius: 0.25rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
                .product-title { font-weight: 600; margin-bottom: 0.5rem; }
                .product-price { color: #6366f1; font-weight: 700; }
                .rich-text-section { max-width: 800px; margin: 0 auto; }
                .newsletter-section { background-color: #f1f5f9; text-align: center; }
            </style>
        </head>
        <body>
        HTML;
    }

    protected function fallbackBlock(string $type, array $settings): string
    {
        $escape = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        // Colour settings still reach a style attribute, but only after
        // being constrained to a value that actually looks like a colour —
        // the previous version spliced $settings['bg_color'] straight into
        // the attribute, so a page builder field was a stored-XSS vector.
        $color = static function ($value, string $fallback) {
            $value = (string) $value;

            return preg_match('/^#[0-9a-fA-F]{3,8}$|^[a-zA-Z]{3,20}$|^rgba?\([0-9.,%\s]+\)$/', $value)
                ? $value
                : $fallback;
        };

        if ($type === 'hero') {
            $bg = $color($settings['bg_color'] ?? '#6366f1', '#6366f1');
            $fg = $color($settings['text_color'] ?? '#ffffff', '#ffffff');

            $html = "<div class='section hero-section' style='background-color: {$bg}; color: {$fg};'>";
            $html .= '<h1>' . $escape($settings['title'] ?? 'Welcome to Axer') . '</h1>';
            $html .= '<p>' . $escape($settings['subtitle'] ?? '') . '</p>';

            if (!empty($settings['button_text'])) {
                $url = filter_var($settings['button_url'] ?? '#', FILTER_VALIDATE_URL) ? $settings['button_url'] : '#';
                $html .= "<a href='" . $escape($url) . "' class='btn' style='background-color: {$fg}; color: {$bg};'>"
                    . $escape($settings['button_text']) . '</a>';
            }

            return $html . '</div>';
        }

        if ($type === 'featured-products') {
            $cols = max(1, min(6, (int) ($settings['columns'] ?? 4)));
            $limit = max(1, min(12, (int) ($settings['limit'] ?? 4)));

            $html = "<div class='section'><h2 style='text-align:center;font-size:2rem;'>"
                . $escape($settings['title'] ?? 'Featured Products') . '</h2>';
            $html .= "<div class='grid-products' style='grid-template-columns: repeat({$cols}, 1fr);'>";

            for ($i = 1; $i <= $limit; $i++) {
                $html .= "<div class='product-card'><div class='product-img'>Product Image Placeholder</div>"
                    . "<div class='product-title'>Sample Product {$i}</div>"
                    . "<div class='product-price'>999.00 EGP</div></div>";
            }

            return $html . '</div></div>';
        }

        if ($type === 'rich-text') {
            $align = in_array($settings['align'] ?? 'center', ['left', 'center', 'right'], true)
                ? $settings['align']
                : 'center';

            $html = "<div class='section rich-text-section' style='text-align: {$align};'>";

            if (!empty($settings['title'])) {
                $html .= "<h2 style='font-size:2rem;margin-bottom:1rem;'>" . $escape($settings['title']) . '</h2>';
            }

            return $html . "<p style='font-size:1.125rem;line-height:1.7;color:#475569;'>"
                . nl2br($escape($settings['content'] ?? '')) . '</p></div>';
        }

        if ($type === 'newsletter') {
            return "<div class='section newsletter-section'><h2>" . $escape($settings['title'] ?? 'Join Our Newsletter')
                . '</h2><p style="color:#475569;">' . $escape($settings['subtitle'] ?? '') . '</p></div>';
        }

        return '';
    }
}
