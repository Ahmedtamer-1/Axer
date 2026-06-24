<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class PageController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $pages = [];
        try {
            $pages = QueryBuilder::table('pages')->orderBy('sort_order', 'asc')->get();
        } catch (\Exception $e) {
            // Keep pages empty if table doesn't exist
        }

        return $this->renderAdmin('pages/index', [
            'title' => 'Manage Pages',
            'pages' => $pages
        ]);
    }

    public function create(Request $request): Response
    {
        $this->checkAuth($request);
        
        if ($request->method() === 'POST') {
            $title = $request->post('title');
            $slug = $request->post('slug') ?: strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $content = $request->post('content');
            $template = $request->post('template', 'page');
            $status = $request->post('status', 'draft');

            try {
                QueryBuilder::table('pages')->insert([
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'template' => $template,
                    'status' => $status,
                    'builder_data' => json_encode([])
                ]);
                return $this->redirect('/admin/pages');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->renderAdmin('pages/create', [
            'title' => 'Create Page',
            'error' => $error ?? null
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        
        $page = QueryBuilder::table('pages')->where('id', $id)->first();
        if (!$page) {
            return $this->redirect('/admin/pages');
        }

        if ($request->method() === 'POST') {
            $title = $request->post('title');
            $slug = $request->post('slug') ?: $page['slug'];
            $content = $request->post('content');
            $template = $request->post('template', 'page');
            $status = $request->post('status', 'draft');

            try {
                QueryBuilder::table('pages')->where('id', $id)->update([
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'template' => $template,
                    'status' => $status
                ]);
                return $this->redirect('/admin/pages');
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->renderAdmin('pages/edit', [
            'title' => 'Edit Page',
            'page' => $page,
            'error' => $error ?? null
        ]);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        try {
            QueryBuilder::table('pages')->where('id', $id)->delete();
        } catch (\Exception $e) {}
        
        return $this->redirect('/admin/pages');
    }

    // Builder endpoint
    public function builder(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        $page = QueryBuilder::table('pages')->where('id', $id)->first();
        if (!$page) {
            return $this->redirect('/admin/pages');
        }
        
        return $this->renderAdmin('pages/builder', [
            'title' => 'Visual Page Builder - ' . htmlspecialchars($page['title']),
            'page' => $page
        ], false);
    }

    // Save Builder data via AJAX
    public function saveBuilder(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        $data = $request->json('builder_data');
        
        try {
            QueryBuilder::table('pages')->where('id', $id)->update([
                'builder_data' => json_encode($data)
            ]);
            return Response::json(['success' => true]);
        } catch (\Exception $e) {
            return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Live preview endpoint
    public function preview(Request $request): Response
    {
        $this->checkAuth($request);
        $blocks = $request->json('builder_data') ?? [];

        // Simple default styles and fonts for the preview iframe
        $html = "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Preview</title>
            <link rel='preconnect' href='https://fonts.googleapis.com'>
            <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
            <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
            <style>
                body {
                    font-family: 'Outfit', sans-serif;
                    margin: 0;
                    padding: 0;
                    background-color: #ffffff;
                    color: #1e293b;
                }
                .section {
                    padding: 4rem 2rem;
                    box-sizing: border-box;
                }
                .hero-section {
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-height: 400px;
                }
                .hero-section h1 { font-size: 3rem; margin-bottom: 1rem; font-weight: 700; }
                .hero-section p { font-size: 1.25rem; margin-bottom: 2rem; max-width: 600px; opacity: 0.9; }
                .btn {
                    display: inline-block;
                    padding: 0.75rem 2rem;
                    font-weight: 600;
                    text-decoration: none;
                    border-radius: 0.375rem;
                    transition: all 0.2s;
                }
                .grid-products {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 2rem;
                    max-width: 1200px;
                    margin: 2rem auto 0;
                }
                .product-card {
                    border: 1px solid #e2e8f0;
                    border-radius: 0.5rem;
                    padding: 1rem;
                    text-align: center;
                }
                .product-img {
                    background: #f1f5f9;
                    height: 200px;
                    border-radius: 0.25rem;
                    margin-bottom: 1rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #94a3b8;
                }
                .product-title { font-weight: 600; margin-bottom: 0.5rem; }
                .product-price { color: #6366f1; font-weight: 700; }
                .rich-text-section { max-width: 800px; margin: 0 auto; }
                .newsletter-section { background-color: #f1f5f9; text-align: center; }
                .newsletter-section h2 { font-size: 1.75rem; margin-bottom: 0.5rem; }
                .newsletter-form { display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.5rem; }
                .newsletter-input { padding: 0.5rem 1rem; border-radius: 0.25rem; border: 1px solid #cbd5e1; width: 260px; }
            </style>
        </head>
        <body>";

        foreach ($blocks as $block) {
            $type = $block['type'];
            $settings = $block['settings'] ?? [];
            
            // Look for LumeScript section template in the active theme
            $themePath = BASE_PATH . '/content/themes/default';
            $sectionFile = $themePath . "/sections/{$type}.lume";
            
            if (file_exists($sectionFile)) {
                try {
                    $engine = new \Axer\Template\Engine([$themePath], BASE_PATH . '/storage/cache');
                    $html .= $engine->render("sections/{$type}", $settings);
                } catch (\Throwable $e) {
                    $html .= "<div style='padding: 2rem; color: red; border: 1px dashed red;'>Error rendering {$type}: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                // Fallback representation
                if ($type === 'hero') {
                    $html .= "<div class='section hero-section' style='background-color: " . ($settings['bg_color'] ?? '#6366f1') . "; color: " . ($settings['text_color'] ?? '#ffffff') . ";'>";
                    $html .= "<h1>" . htmlspecialchars($settings['title'] ?? 'Welcome to Lume') . "</h1>";
                    $html .= "<p>" . htmlspecialchars($settings['subtitle'] ?? '') . "</p>";
                    if (!empty($settings['button_text'])) {
                        $html .= "<a href='" . htmlspecialchars($settings['button_url'] ?? '#') . "' class='btn' style='background-color: " . ($settings['text_color'] ?? '#ffffff') . "; color: " . ($settings['bg_color'] ?? '#6366f1') . ";'>" . htmlspecialchars($settings['button_text']) . "</a>";
                    }
                    $html .= "</div>";
                } elseif ($type === 'featured-products') {
                    $html .= "<div class='section'>";
                    $html .= "<h2 style='text-align: center; font-size: 2rem;'>" . htmlspecialchars($settings['title'] ?? 'Featured Products') . "</h2>";
                    $cols = $settings['columns'] ?? 4;
                    $limit = $settings['limit'] ?? 4;
                    $html .= "<div class='grid-products' style='grid-template-columns: repeat({$cols}, 1fr);'>";
                    for ($i = 1; $i <= $limit; $i++) {
                        $html .= "<div class='product-card'>
                            <div class='product-img'>Product Image Placeholder</div>
                            <div class='product-title'>Sample Product {$i}</div>
                            <div class='product-price'>999.00 EGP</div>
                        </div>";
                    }
                    $html .= "</div></div>";
                } elseif ($type === 'rich-text') {
                    $html .= "<div class='section rich-text-section' style='text-align: " . ($settings['align'] ?? 'center') . ";'>";
                    if (!empty($settings['title'])) {
                        $html .= "<h2 style='font-size: 2rem; margin-bottom: 1rem;'>" . htmlspecialchars($settings['title']) . "</h2>";
                    }
                    $html .= "<p style='font-size: 1.125rem; line-height: 1.7; color: #475569;'>" . nl2br(htmlspecialchars($settings['content'] ?? '')) . "</p>";
                    $html .= "</div>";
                } elseif ($type === 'newsletter') {
                    $html .= "<div class='section newsletter-section'>";
                    $html .= "<h2>" . htmlspecialchars($settings['title'] ?? 'Join Our Newsletter') . "</h2>";
                    $html .= "<p style='color: #475569;'>" . htmlspecialchars($settings['subtitle'] ?? '') . "</p>";
                    $html .= "<div class='newsletter-form'>
                        <input type='email' class='newsletter-input' placeholder='your@email.com'>
                        <button class='btn' style='background-color: #6366f1; color: white;'>Subscribe</button>
                    </div>";
                    $html .= "</div>";
                }
            }
        }

        $html .= "</body></html>";
        return new Response($html);
    }
}
