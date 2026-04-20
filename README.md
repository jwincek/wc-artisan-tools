# WC Artisan Tools

A simplified WooCommerce product dashboard and commission system for artisans and makers.

WooCommerce is built for every kind of store. This plugin strips it down to just what a maker needs: a clean product grid, an 8-field add form, and a full commission workflow from request to finished piece.

## Requirements

- WordPress 6.9+
- PHP 8.1+
- WooCommerce 8.0+

## Quick Start

1. Clone or upload to `wp-content/plugins/`
2. Activate in WordPress admin
3. Go to **My Crafts > Settings** and select your craft profile
4. Add your first product from **My Crafts > Add New**
5. Drop the **Commission Request Form** block on any page

## Features

### Simplified Product Dashboard

A visual grid of your products with photo cards, type/status filters, and quick actions (Mark Sold, Edit, Delete). The add/edit form has 8 fields instead of WooCommerce's 40+:

- Name (auto-suggested from type + material selections)
- Price
- Type, Material, Finish, Component (taxonomy dropdowns)
- Description
- Photo
- Sold / Featured toggles

### Craft Profiles

Seven built-in profiles that relabel taxonomies and seed default terms for your craft:

| Profile | Material Label | Component Label | Example Terms |
|---------|---------------|-----------------|---------------|
| Woodworking | Wood Species | Hardware | Black Walnut, Slimline, CA Glue |
| Pottery | Clay Body | Firing Method | Stoneware, Cone 6 Electric |
| Jewelry | Metal | Stone / Setting | Sterling Silver, Bezel Set |
| Metalwork | Steel / Metal | Handle / Accessory | Damascus, Walnut Handle |
| Fiber Arts | Fiber | Technique | Merino Wool, Hand Knit |
| Leather | Leather Type | Hardware | Vegetable Tanned, Solid Brass |
| General | Material | Component | (blank slate) |

Changing profiles adds new terms without removing existing ones.

### Commission System

Full request-to-finished-piece workflow:

1. Customer submits a request via the **Commission Request Form** block
2. Maker receives email notification and sends a quote from the admin
3. Customer receives email with quote details and accept/decline links
4. On accept: a hidden WooCommerce product is created at the quoted price
5. Customer is redirected to checkout to pay
6. On order completion: commission status moves to In Progress
7. Maker marks the piece as complete
8. Product becomes visible in the shop catalog as a completed commission

Customers with accounts can view their commission history in **My Account > My Commissions**.

### Commission Security

- Accept/decline uses cryptographically random tokens with configurable expiration
- Actions require POST with nonce verification (not GET links)
- Rate limiting: one request per IP per hour (proxy-aware)
- Optional Simple Spam Shield integration (graceful degradation if not installed)
- Guest token flow works without requiring account creation

### Config-Driven Email System

Five emails built on WooCommerce's email infrastructure, all defined in JSON:

| Email | Recipient | Trigger |
|-------|-----------|---------|
| New Commission Request | Maker | Customer submits form |
| Quote Sent | Customer | Maker sends quote |
| Quote Accepted | Maker | Customer accepts |
| Quote Declined | Maker | Customer declines |
| Commission Complete | Customer | Maker marks done |

All emails appear in **WooCommerce > Settings > Emails** where subjects and headings can be customized. HTML and plain text templates included.

## Architecture

```
wc-artisan-tools/
├── wc-artisan-tools.php              # Bootstrap, autoloader, init
├── config/                           # JSON-driven configuration
│   ├── post-types.json               # Commission CPT
│   ├── taxonomies.json               # 5 taxonomies
│   ├── entities.json                 # Commission entity schema
│   ├── emails.json                   # 5 email definitions
│   ├── settings.json                 # Defaults, statuses, budget ranges
│   └── crafts/                       # 7 craft profiles
├── includes/
│   ├── core/                         # Config, CPT Registry, Entity Hydrator, Query
│   ├── admin/                        # Dashboard, Commission Admin, Settings, Menu
│   ├── commission/                   # Commission lifecycle handler
│   ├── woocommerce/                  # Product Manager, Order Handler, My Account
│   ├── emails/                       # Config-driven email factory
│   ├── integrations/                 # Simple Spam Shield
│   └── rest/                         # Commission REST API
├── blocks/commission-form/           # No-build Gutenberg block
├── templates/emails/                 # 10 email templates (HTML + plain)
└── assets/                           # Admin CSS/JS, frontend CSS
```

### Key Patterns

- **Config-driven**: CPTs, taxonomies, meta, emails, and craft profiles defined in JSON
- **No build step**: Editor block uses plain IIFE JS with `wp.blocks` globals
- **WooCommerce native**: Products, orders, checkout, and emails all use WooCommerce infrastructure
- **Graceful degradation**: Simple Spam Shield integration works if present, skips if not

## REST API

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | `/wc-artisan-tools/v1/commissions` | Public | Submit commission request |
| GET | `/wc-artisan-tools/v1/commissions/{id}` | Editor+ | Get commission details |

## Taxonomies

All registered against WooCommerce's `product` post type:

| Taxonomy | Default Label | Purpose |
|----------|--------------|---------|
| `wcat_product_type` | Product Type | Pen, Bowl, Ring, etc. |
| `wcat_material` | Material | Wood species, clay body, metal |
| `wcat_finish` | Finish | Surface treatment |
| `wcat_component` | Component | Hardware, stones, handles |
| `wcat_product_origin` | Origin | Shop vs. Commission (internal) |

Labels adapt based on the selected craft profile.

## Optional Integrations

### Simple Spam Shield

If the [Simple Spam Shield](https://github.com/jwincek/simple-spam-shield) plugin is active, the commission form is automatically protected by its guard pipeline (honeypot, time gate, nonce, link limit, keyword blocking, duplicate detection, behavioral analysis). No configuration needed.

## Settings

**My Crafts > Settings** provides:

- Craft profile selection with term seeding
- Commission enable/disable toggle
- Quote expiry period (7-90 days)
- Products per page in dashboard

## Hooks

### Actions

| Hook | Args | When |
|------|------|------|
| `wcat_commission_created` | `$commission_id` | After form submission |
| `wcat_quote_sent` | `$commission_id` | After maker sends quote |
| `wcat_quote_accepted` | `$commission_id` | After customer accepts |
| `wcat_quote_declined` | `$commission_id` | After customer declines |
| `wcat_commission_completed` | `$commission_id` | After maker marks complete |

### Filters

| Filter | Args | Purpose |
|--------|------|---------|
| `wcat_commission_spam_check` | `$result, $data` | Spam check before commission creation |

## License

GPL-2.0-or-later
