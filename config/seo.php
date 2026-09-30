<?php

/*
| Site-wide SEO / business settings, editable in Admin > SEO & Business Settings.
| Phone, email, address and social links stay in Admin > Contact & Map (single source);
| GTM / GA4 IDs stay in Admin > Footer & Tracking.
*/

return [
    'settings' => [
        'business' => [
            'label' => 'Business identity',
            'help' => 'Must match Google Business Profile and directory listings exactly (NAP consistency).',
            'fields' => [
                'business.brand_name' => ['label' => 'Brand name', 'type' => 'text', 'default' => 'Tasfia Plumbing'],
                'business.legal_name' => ['label' => 'Legal company name', 'type' => 'text', 'default' => 'Tasfia Plumbing Service Singapore'],
                'business.alternate_name' => ['label' => 'Alternate name', 'type' => 'text', 'default' => '', 'help' => 'Other name customers search for. Shown by Google as site name alternative.'],
                'business.uen' => ['label' => 'UEN', 'type' => 'text', 'default' => ''],
                'business.founded_year' => ['label' => 'Year founded', 'type' => 'text', 'default' => '2024', 'help' => 'Used in the footer copyright (e.g. © 2016–2026) and as foundingDate in the schema.'],
                'business.schema_type' => ['label' => 'Business type (schema)', 'type' => 'select', 'default' => 'Plumber', 'options' => [
                    'Plumber', 'HomeAndConstructionBusiness', 'GeneralContractor', 'LocalBusiness',
                ]],
                'business.postal_code' => ['label' => 'Postal code', 'type' => 'text', 'default' => '218309'],
                'business.latitude' => ['label' => 'Latitude', 'type' => 'text', 'default' => ''],
                'business.longitude' => ['label' => 'Longitude', 'type' => 'text', 'default' => ''],
                'business.area_served' => ['label' => 'Area served', 'type' => 'text', 'default' => 'Singapore'],
                'business.price_range' => ['label' => 'Price range', 'type' => 'text', 'default' => '$$'],
            ],
        ],

        // Edited in Admin > Business settings > Contact & social. One place for every profile link:
        // the footer icons, the schema sameAs list and the WhatsApp buttons all read from here.
        'social' => [
            'label' => 'Social profiles',
            'hidden' => true,
            'fields' => [
                'social.whatsapp' => ['type' => 'text', 'default' => ''],
                'social.facebook' => ['type' => 'text', 'default' => ''],
                'social.instagram' => ['type' => 'text', 'default' => ''],
                'social.linkedin' => ['type' => 'text', 'default' => ''],
                'social.x' => ['type' => 'text', 'default' => ''],
                'social.youtube' => ['type' => 'text', 'default' => ''],
                'social.tiktok' => ['type' => 'text', 'default' => ''],
                'social.pinterest' => ['type' => 'text', 'default' => ''],
                'business.gbp_url' => ['type' => 'text', 'default' => ''],
            ],
        ],
        'hours' => [
            'label' => 'Opening hours',
            'help' => 'Use 24h format like 08:00-20:00, or "closed". Keep identical to Google Business Profile.',
            'fields' => [
                'hours.mon_fri' => ['label' => 'Monday – Friday', 'type' => 'text', 'default' => '08:00-20:00'],
                'hours.saturday' => ['label' => 'Saturday', 'type' => 'text', 'default' => '08:00-20:00'],
                'hours.sunday' => ['label' => 'Sunday', 'type' => 'text', 'default' => '08:00-20:00'],
                'hours.note' => ['label' => 'Note shown on site', 'type' => 'text', 'default' => ''],
            ],
        ],
        'search' => [
            'label' => 'Search appearance',
            'help' => 'Fallbacks used when a page has no own meta title or description.',
            'fields' => [
                'seo.title_suffix' => ['label' => 'Title suffix', 'type' => 'text', 'default' => ' | Tasfia Plumbing Singapore'],
                'seo.default_meta_desc' => ['label' => 'Default meta description', 'type' => 'textarea', 'default' => 'Tasfia Plumbing: leak repair, pipe and toilet repair, tap and water heater installation, drain clearing across Singapore. Clear prices, fast response.'],
                'seo.default_og_image' => ['label' => 'Default social share image path', 'type' => 'text', 'default' => '/logo.png'],
                'seo.faq_schema' => ['label' => 'Output FAQPage schema', 'type' => 'toggle', 'default' => '1', 'help' => 'Google stopped showing FAQ rich results on 7 May 2026. The markup is still valid; keep on unless you prefer less markup.'],
                'seo.gsc_verification' => ['label' => 'Google Search Console verification code', 'type' => 'text', 'default' => ''],
                'seo.bing_verification' => ['label' => 'Bing Webmaster verification code', 'type' => 'text', 'default' => ''],
            ],
        ],
        'crawlers' => [
            'label' => 'Crawlers & robots.txt',
            'help' => 'Search crawlers decide if you can appear in AI answers. Training crawlers do not affect rankings.',
            'fields' => [
                'robots.allow_ai_search' => ['label' => 'Allow AI search crawlers (OAI-SearchBot, PerplexityBot, ChatGPT-User)', 'type' => 'toggle', 'default' => '1'],
                'robots.allow_ai_training' => ['label' => 'Allow AI training crawlers (GPTBot, Google-Extended, CCBot)', 'type' => 'toggle', 'default' => '1', 'help' => 'Business decision only. No effect on Google Search or AI Overviews ranking.'],
                'robots.extra' => ['label' => 'Extra robots.txt rules', 'type' => 'textarea', 'default' => '', 'help' => 'Added under "User-agent: *". Example: Disallow: /search'],
            ],
        ],
        // Edited in Admin > Business settings > Logo, footer & tracking.
        'tracking' => [
            'label' => 'Tracking & chat',
            'hidden' => true,
            'fields' => [
                'tracking.tawk_enabled' => ['label' => 'Show Tawk.to live chat', 'type' => 'toggle', 'default' => '0'],
                'tracking.tawk_id' => ['label' => 'Tawk.to property/widget ID', 'type' => 'text', 'default' => ''],
                'tracking.events' => ['label' => 'Send conversion events to GTM', 'type' => 'toggle', 'default' => '1'],
            ],
        ],

        // Edited in Admin > Website > Website text. Headings and short texts used on several pages.
        'texts' => [
            'label' => 'Website text',
            'hidden' => true,
            'fields' => [
                'text.steps_title' => ['section' => 'Homepage: How it works', 'label' => 'Heading', 'type' => 'text', 'default' => 'From message to finished job in three steps', 'page' => '/'],
                'text.step1_title' => ['section' => 'Homepage: How it works', 'label' => 'Step 1 title', 'type' => 'text', 'default' => 'Tell us the problem'],
                'text.step1_text' => ['section' => 'Homepage: How it works', 'label' => 'Step 1 text', 'type' => 'textarea', 'default' => 'Send a message, photo or WhatsApp. We ask the right questions so the first visit is the right one.'],
                'text.step2_title' => ['section' => 'Homepage: How it works', 'label' => 'Step 2 title', 'type' => 'text', 'default' => 'Get a clear price'],
                'text.step2_text' => ['section' => 'Homepage: How it works', 'label' => 'Step 2 text', 'type' => 'textarea', 'default' => 'You get a price range before any work starts. No surprise charges on the day.'],
                'text.step3_title' => ['section' => 'Homepage: How it works', 'label' => 'Step 3 title', 'type' => 'text', 'default' => 'We fix it, you check it'],
                'text.step3_text' => ['section' => 'Homepage: How it works', 'label' => 'Step 3 text', 'type' => 'textarea', 'default' => 'Our team does the job, cleans up and walks you through the result before we leave.'],
                'text.areas_title' => ['section' => 'Homepage: Areas we serve', 'label' => 'Heading', 'type' => 'text', 'default' => 'Across Singapore, from Jurong to Changi', 'page' => '/'],
                'text.areas_lead' => ['section' => 'Homepage: Areas we serve', 'label' => 'Text', 'type' => 'textarea', 'default' => 'HDB flats, condominiums, landed homes and commercial units. Pick your area to see what we do there.'],
                'text.cta_title' => ['section' => 'Call-to-action banner (bottom of most pages)', 'label' => 'Heading', 'type' => 'text', 'default' => 'Tell us what needs fixing', 'page' => '/about'],
                'text.cta_text' => ['section' => 'Call-to-action banner (bottom of most pages)', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Send a photo or a short description on WhatsApp. We reply with a clear price range, usually the same day.'],
                'text.cta_services_title' => ['section' => 'Call-to-action banner: Services page', 'label' => 'Heading', 'type' => 'text', 'default' => 'Not sure which service you need?', 'page' => '/services'],
                'text.cta_services_text' => ['section' => 'Call-to-action banner: Services page', 'label' => 'Text', 'type' => 'textarea', 'default' => 'Describe the problem or send a photo. We tell you what it is, what it costs and how soon we can come.'],
                'text.cta_projects_title' => ['section' => 'Call-to-action banner: Projects & Reviews pages', 'label' => 'Heading', 'type' => 'text', 'default' => 'Want a result like this at your place?', 'page' => '/projects'],
                'text.cta_locations_title' => ['section' => 'Call-to-action banner: Areas page', 'label' => 'Heading', 'type' => 'text', 'default' => 'Your area not listed?', 'page' => '/locations'],
                'text.cta_locations_text' => ['section' => 'Call-to-action banner: Areas page', 'label' => 'Text', 'type' => 'textarea', 'default' => 'We cover all of Singapore. Send us your postal code and what needs doing.'],
                'text.quote_intro' => ['section' => 'Quote form', 'label' => 'Text under "Get a free quote"', 'type' => 'text', 'default' => 'Clear price range, usually the same day.', 'page' => '/contact'],
                'text.contact_lead' => ['section' => 'Contact page', 'label' => 'Text under the page title', 'type' => 'textarea', 'default' => 'WhatsApp is the fastest: send a photo and we reply with a price range, usually the same working day.', 'page' => '/contact'],
                'text.contact_whatsapp' => ['section' => 'Contact page', 'label' => 'Text on the green WhatsApp card', 'type' => 'text', 'default' => 'Send photos of the problem. Most customers get a price range within a few hours.'],
                'text.about_lead' => ['section' => 'About page', 'label' => 'Text under the page title (when the About subtitle is empty)', 'type' => 'text', 'default' => '', 'page' => '/about'],
            ],
        ],

        // Edited in Admin > System > Updates. The token is stored encrypted.
        'deploy' => [
            'label' => 'GitHub connection',
            'hidden' => true,
            'fields' => [
                'deploy.repo_url' => ['type' => 'text', 'default' => ''],
                'deploy.branch' => ['type' => 'text', 'default' => ''],
                'deploy.token' => ['type' => 'secret', 'default' => ''],
                'deploy.username' => ['type' => 'text', 'default' => ''],
            ],
        ],

        // Edited in Admin > System > Site status & email.
        'system' => [
            'label' => 'System',
            'hidden' => true,
            'fields' => [
                'system.maintenance' => ['type' => 'toggle', 'default' => '0'],
                'system.maintenance_message' => ['type' => 'text', 'default' => ''],
                'system.maintenance_back' => ['type' => 'text', 'default' => ''],
                'system.debug_until' => ['type' => 'text', 'default' => ''],
                'system.app_url' => ['type' => 'text', 'default' => ''],
                'system.env' => ['type' => 'text', 'default' => ''],
                'mail.enabled' => ['type' => 'toggle', 'default' => '0'],
                'mail.host' => ['type' => 'text', 'default' => ''],
                'mail.port' => ['type' => 'text', 'default' => '587'],
                'mail.encryption' => ['type' => 'text', 'default' => 'tls'],
                'mail.username' => ['type' => 'text', 'default' => ''],
                'mail.password' => ['type' => 'secret', 'default' => ''],
                'mail.from_address' => ['type' => 'text', 'default' => ''],
                'mail.from_name' => ['type' => 'text', 'default' => ''],
                'geo.mode' => ['type' => 'text', 'default' => 'off'],
                'geo.countries' => ['type' => 'text', 'default' => ''],
                'geo.message' => ['type' => 'text', 'default' => ''],
            ],
        ],

        // Edited in Admin > Insights > Google connections. Secrets are stored encrypted.
        'google' => [
            'label' => 'Google connections',
            'hidden' => true,
            'fields' => [
                'google.client_id' => ['type' => 'text', 'default' => ''],
                'google.client_secret' => ['type' => 'secret', 'default' => ''],
                'google.refresh_token' => ['type' => 'secret', 'default' => ''],
                'google.connected_email' => ['type' => 'text', 'default' => ''],
                'google.gbp_location' => ['type' => 'text', 'default' => ''],
                'google.gbp_location_name' => ['type' => 'text', 'default' => ''],
                'google.gbp_place_id' => ['type' => 'text', 'default' => ''],
                'google.gbp_maps_uri' => ['type' => 'text', 'default' => ''],
                'google.gbp_review_uri' => ['type' => 'text', 'default' => ''],
                'google.gbp_rating' => ['type' => 'text', 'default' => ''],
                'google.gbp_total' => ['type' => 'text', 'default' => ''],
                'google.gbp_synced_at' => ['type' => 'text', 'default' => ''],
                'google.gsc_property' => ['type' => 'text', 'default' => ''],
                'google.ga4_property' => ['type' => 'text', 'default' => ''],
                'google.last_sync' => ['type' => 'text', 'default' => ''],
                'google.last_error' => ['type' => 'text', 'default' => ''],
            ],
        ],

        // Edited in Admin > Homepage > Hero.
        'home' => [
            'label' => 'Homepage hero',
            'hidden' => true,
            'fields' => [
                'home.eyebrow' => ['label' => 'Small line above the headline', 'type' => 'text', 'default' => ''],
                'home.badges' => ['label' => 'Trust badges (one per line)', 'type' => 'textarea', 'default' => ''],
                'home.show_quote_form' => ['label' => 'Show quote form next to the headline', 'type' => 'toggle', 'default' => '1'],
            ],
        ],

        // Edited in Admin > Reviews (hidden from the SEO settings page).
        'reviews' => [
            'label' => 'Google reviews',
            'hidden' => true,
            'fields' => [
                'reviews.google_enabled' => ['label' => 'Show Google reviews', 'type' => 'toggle', 'default' => '0'],
                'reviews.google_place_id' => ['label' => 'Google Place ID', 'type' => 'text', 'default' => ''],
                'reviews.google_api_key' => ['label' => 'Places API key (encrypted)', 'type' => 'secret', 'default' => ''],
                'reviews.google_min_rating' => ['label' => 'Only show reviews with at least', 'type' => 'select', 'default' => '4', 'options' => ['1', '2', '3', '4', '5']],
                'reviews.show_own' => ['label' => 'Also show your own reviews', 'type' => 'toggle', 'default' => '1'],
            ],
        ],
    ],

    // Fixed pages whose SEO is edited in Admin > Page SEO. "route" is the Laravel route name.
    'static_pages' => [
        'home' => ['label' => 'Homepage', 'path' => '/', 'route' => 'home', 'title' => 'Plumbing Services in Singapore'],
        'about' => ['label' => 'About us', 'path' => '/about', 'route' => 'about', 'title' => 'About Tasfia Plumbing Singapore'],
        'services' => ['label' => 'All services', 'path' => '/services', 'route' => 'services', 'title' => 'Plumbing Services'],
        'pricing' => ['label' => 'Price list', 'path' => '/pricing', 'route' => 'pricing', 'title' => 'Price List'],
        'locations' => ['label' => 'Areas we serve', 'path' => '/locations', 'route' => 'locations', 'title' => 'Areas We Serve in Singapore'],
        'projects' => ['label' => 'Projects', 'path' => '/projects', 'route' => 'projects', 'title' => 'Recent Plumbing Jobs'],
        'articles' => ['label' => 'Articles', 'path' => '/blogs', 'route' => 'blogs', 'title' => 'Plumbing Guides & Articles'],
        'reviews' => ['label' => 'Reviews', 'path' => '/reviews', 'route' => 'reviews', 'title' => 'Customer Reviews'],
        'contact' => ['label' => 'Contact', 'path' => '/contact', 'route' => 'contact', 'title' => 'Contact Us'],
        'privacy' => ['label' => 'Privacy policy', 'path' => '/privacy-policy', 'route' => 'privacy-policy', 'title' => 'Privacy Policy'],
        'terms' => ['label' => 'Terms of service', 'path' => '/terms-of-service', 'route' => 'terms-of-service', 'title' => 'Terms of Service'],
    ],

    'audit' => [
        'title_max' => 60,
        'desc_min' => 70,
        'desc_max' => 160,
        'min_words' => ['service' => 300, 'article' => 600, 'location' => 250, 'category' => 120],
        'min_faqs' => ['service' => 3, 'location' => 2],
    ],
];
