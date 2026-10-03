<?php

$crud = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];
$page = ['view' => 'View', 'edit' => 'Edit'];

return [
    // Super admin always has every permission (Gate::before) and cannot be edited or deleted.
    'super_role' => 'super-admin',

    // Times in emails and the scheduler UI are shown in the business's local time.
    'timezone' => env('ADMIN_TIMEZONE', 'Asia/Singapore'),
    'timezone_label' => env('ADMIN_TIMEZONE_LABEL', 'Singapore time'),

    /*
    | Every admin area and what can be done in it. Permission name = "{module}.{action}".
    | The Roles screen shows this as a matrix; routes check the same names.
    | Standard actions are view/create/edit/delete; anything else is a special right.
    */
    'modules' => [
        'Content' => [
            'articles' => ['label' => 'Articles', 'help' => 'Blog posts and cost guides', 'actions' => [
                'create' => 'Write own', 'edit_all' => "Edit everyone's", 'delete' => 'Delete', 'publish' => 'Publish, schedule & approve',
            ]],
            'services' => ['label' => 'Services', 'help' => 'Service pages', 'actions' => $crud],
            'categories' => ['label' => 'Service categories', 'actions' => $crud],
            'locations' => ['label' => 'Locations', 'help' => 'Area pages', 'actions' => $crud],
            'pricing' => ['label' => 'Price list', 'actions' => $crud],
            'faqs' => ['label' => 'FAQs', 'actions' => $crud],
            'projects' => ['label' => 'Projects', 'actions' => $crud],
            'reviews' => ['label' => 'Reviews', 'actions' => $crud + ['google' => 'Connect Google reviews']],
        ],
        'Website' => [
            'homepage' => ['label' => 'Homepage', 'help' => 'Hero slides, sections, counters, partner logos', 'actions' => $page],
            'about' => ['label' => 'About page', 'actions' => $page],
            'banners' => ['label' => 'Page banners', 'help' => 'Header images of listing pages', 'actions' => $page],
            'media' => ['label' => 'Media library', 'actions' => ['view' => 'View', 'create' => 'Upload', 'edit' => 'Edit alt text', 'delete' => 'Delete unused']],
        ],
        'SEO' => [
            'seo_health' => ['label' => 'SEO Health', 'actions' => ['view' => 'View']],
            'page_seo' => ['label' => 'Page SEO', 'help' => 'Titles of fixed pages', 'actions' => $page],
            'redirects' => ['label' => 'Redirects & 404 log', 'actions' => $crud],
            'seo_settings' => ['label' => 'Schema & robots', 'actions' => $page],
        ],
        'Insights' => [
            'analytics' => ['label' => 'Search & visitor reports', 'help' => 'Search Console, Analytics and index status', 'actions' => ['view' => 'View reports', 'connect' => 'Connect Google accounts']],
        ],
        'Leads' => [
            'enquiries' => ['label' => 'Enquiries', 'actions' => ['view' => 'View', 'edit' => 'Mark read', 'delete' => 'Delete']],
        ],
        'Administration' => [
            'settings' => ['label' => 'Business settings', 'help' => 'Contact details, footer, tracking', 'actions' => $page],
            'users' => ['label' => 'Users', 'actions' => $crud],
            'roles' => ['label' => 'Roles & permissions', 'actions' => $crud],
            'system' => ['label' => 'System', 'actions' => ['cache' => 'Clear cache', 'settings' => 'Maintenance, debug, email & country access', 'update' => 'Update from GitHub']],
        ],
    ],

    // Articles or changes waiting for approval longer than this are emailed to the hidden
    // maintenance account (App\Support\ReviewReminder), at most once per this many hours each.
    'review_reminder_hours' => 12,

    // Article audit (App\Support\ArticleAuditor): clicks and impressions are over 16 months.
    'audit' => [
        'keep_clicks' => 10,       // this many clicks: keep the article
        'seen_impressions' => 200, // this many impressions: Google shows it, give it its own angle
        'thin_words' => 300,       // fewer words: thin
        'merge_overlap' => 40,     // this much of the same text as a stronger article: merge
    ],

    // Byline on articles that have no author of their own and no default author: the team.
    'team_byline' => [
        'title' => 'Plumbing specialists, Singapore',
        'bio' => 'Written by the :brand team. We handle plumbing repairs and installations for homes and businesses across Singapore, and our guides come from the jobs we do on site.',
    ],

    'roles' => [
        'super-admin' => ['label' => 'Super Admin', 'description' => 'Owner. Everything, including publishing, users and system updates.'],
        'admin' => ['label' => 'Admin', 'description' => 'Runs the site day to day. Articles go for approval unless the role is given "Publish & approve". Cannot manage the team or update the system.'],
        'editor' => ['label' => 'Editor', 'description' => 'Content team: services, catalog, website pages and all articles. Articles still need approval. Cannot delete.'],
        'writer' => ['label' => 'Writer', 'description' => 'Writes own articles and submits them for approval.'],
    ],

    // Used only when a built-in role has no permissions yet (fresh install).
    'default_role_permissions' => [
        'admin' => ['*', '!articles.publish', '!users.*', '!roles.*', '!system.update', '!system.settings'],
        'editor' => [
            'articles.create', 'articles.edit_all',
            'services.view', 'services.create', 'services.edit',
            'categories.view', 'categories.create', 'categories.edit',
            'locations.view', 'locations.create', 'locations.edit',
            'pricing.view', 'pricing.create', 'pricing.edit',
            'faqs.view', 'faqs.create', 'faqs.edit',
            'projects.view', 'projects.create', 'projects.edit',
            'reviews.view', 'reviews.create', 'reviews.edit',
            'homepage.view', 'homepage.edit', 'about.view', 'about.edit', 'banners.view', 'banners.edit',
            'media.view', 'media.create', 'media.edit',
            'seo_health.view', 'page_seo.view', 'page_seo.edit', 'enquiries.view',
        ],
        'writer' => ['articles.create', 'media.view', 'media.create', 'seo_health.view'],
    ],
];
