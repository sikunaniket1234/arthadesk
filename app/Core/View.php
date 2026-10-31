<?php
namespace App\Core;

class View {
    public static function render(string $template, array $data = []): void {
        $content = self::renderToString($template, $data);

        if (str_starts_with($template, 'layouts/') || isset($data['noLayout']) && $data['noLayout']) {
            echo $content;
            return;
        }

        // Render main layout
        extract($data);
        $viewsPath = dirname(__DIR__) . '/Views/';
        $layoutPath = $viewsPath . 'layouts/app.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            echo $content;
        }
    }

    public static function renderToString(string $template, array $data = []): string {
        extract($data);
        $viewsPath = dirname(__DIR__) . '/Views/';
        $templatePath = $viewsPath . ltrim($template, '/') . '.php';

        if (!file_exists($templatePath)) {
            throw new \RuntimeException('View template not found: ' . $template);
        }

        ob_start();
        include $templatePath;
        return (string)ob_get_clean();
    }
}
