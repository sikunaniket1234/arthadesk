# Artha-Desk Landing Page

Responsive static marketing website built from the HTML reference supplied in the conversation, with refreshed branding and generated image assets.

## Files
- `index.html` — landing page structure and SEO/social metadata
- `assets/css/style.css` — responsive styling
- `assets/js/main.js` — mobile navigation, tenant login preview, FAQ/browser interactions and form validation
- `assets/images/artha-desk-logo.svg` — editable vector logo
- `assets/images/favicon.svg` — browser icon
- `assets/images/shopkeeper-pos.webp` — updated generated campaign banner used in the hero
- `assets/images/og-cover.png` — 1200×630 social sharing image

## Run locally
Open `index.html` directly, or run a local static server from this directory:

```bash
python -m http.server 8000
```

Then visit `http://localhost:8000`.

## Before production
1. Confirm the final domain and replace `https://arthadesk.in/` in canonical and Open Graph metadata if needed.
2. Configure the PHP `POST /leads` endpoint. The HTML form currently posts to `/leads`; this static package does not include the backend.
3. Connect the language toggle to real English/Odia translation files. It is currently a placeholder notice.
4. Confirm tenant login routing and wildcard DNS/TLS for `*.arthadesk.in` before enabling the tenant sign-in flow.
5. Replace illustrative dashboard figures and product examples with approved product screenshots before making feature claims.
6. Validate GST descriptions, tax behaviour and invoice formats with a qualified GST practitioner.
7. Configure privacy policy, terms, contact details and a real support email before launch.

## Verification caveat
This package is a static HTML/CSS/JS deliverable. It has not been executed in a PHP runtime, and no backend lead endpoint is included.
