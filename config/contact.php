<?php
return [
    // The mailbox that receives contact-form submissions from the landing page.
    'email' => (string)env('CONTACT_EMAIL', ''),

    // Optional public contact details rendered on the landing page.
    'phone' => (string)env('CONTACT_PHONE', ''),
    'whatsapp' => (string)env('CONTACT_WHATSAPP', ''),

    // Mail delivery driver: smtp | mail | log
    //   smtp - authenticated SMTP (recommended in production)
    //   mail - PHP mail(), works on Hostinger but less reliable
    //   log  - write to storage/logs, never sends (development/testing)
    'mail_driver' => (string)env('MAIL_DRIVER', 'log'),

    'from_email' => (string)env('SMTP_FROM', ''),
    'from_name' => (string)env('SMTP_FROM_NAME', 'Artha-Desk Website'),

    'smtp_host' => (string)env('SMTP_HOST', ''),
    'smtp_port' => (int)env('SMTP_PORT', 465),
    'smtp_encryption' => (string)env('SMTP_ENCRYPTION', 'ssl'), // ssl | tls | none
    'smtp_user' => (string)env('SMTP_USER', ''),
    'smtp_pass' => (string)env('SMTP_PASS', ''),

    // Rate limit for the public contact form (attempts per IP per hour).
    'rate_limit' => (int)env('CONTACT_RATE_LIMIT', 5),
    'rate_window' => (int)env('CONTACT_RATE_WINDOW', 3600),
];
