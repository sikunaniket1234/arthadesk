<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artha-Desk — Notice</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .error-card {
            max-width: 540px;
            margin: 6rem auto;
            background: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            border-top: 5px solid #ef4444;
        }
        .error-icon { font-size: 3rem; margin-bottom: 1rem; }
        .error-title { font-size: 1.4rem; font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; }
        .error-desc { font-size: 0.95rem; color: #64748b; line-height: 1.6; margin-bottom: 1.75rem; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">Notice</h1>
        <p class="error-desc"><?= e($message ?? 'An error occurred while processing your request.') ?></p>
        <a href="/app/pos" class="btn btn-primary">Return to POS</a>
    </div>
</body>
</html>
