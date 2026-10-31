

## [v0.6.0] - 2026-10-27 - Enhancements & Mobile Responsiveness

### Added
- **Walk-in & Customer Enhancements**: POS allows adding walk-in name/phone, plus quick add customer modal now captures name/phone/email/GSTIN/address/state with GSTIN validation; Customers page supports adding full details including GSTIN validation.
- **Accounting & Returns**: Verified returns/exchanges flow (credit notes, stock restock/deduction), accounting with expenses and trial balance, GST summaries.
- **Products/Dealers**: Supplier link maintained on products; dealer/supplier details integrated across product/add flows.
- **Settings & Profile**: Settings tab with Business/GST/Operations/Users and Profile page with password/name update maintained; added mobile responsive styling for topbar/nav/cards/tables.
- **Docs**: Created/updated HISTORY.md and ensured USERS.md is present with admin/dummy credentials.



## [v0.7.0] - 2026-10-27 - Production Readiness, Template Designer & Role Fixes

### Added
- **Bill/Receipt Template Designer** (inside Settings > Bill/Receipt Designer, also /app/templates) for thermal (58/80mm) and normal/A4 bills with HTML/CSS editor.
- **Production checklist** (PRODUCTION_READY.md).
- **Migration 004** for bill_templates table.
- **TemplateController** with index/editor/save.

### Changed
- **Role/Menu routing**: Super Admin now sees Platform Admin and POS (for selected store); tenant users see business menus only.
- **Tenant context**: Allows super admin to access /app routes when a tenant is selected via switch-tenant.
- **POS/Customer/Settings** UI polish, form styles, modal styles, mobile responsiveness.
