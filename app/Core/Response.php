<?php
namespace App\Core;

class Response {
    public static function json($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    public static function html(string $content, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function error(string $message, int $statusCode = 500): void {
        http_response_code($statusCode);
        View::render('layouts/error', [
            'statusCode' => $statusCode,
            'message' => $message,
        ]);
        exit;
    }
}
