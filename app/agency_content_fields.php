<?php

/**
 * Registry of every editable landing-page content section and its fields,
 * driving both the backoffice content editor (backoffice/agency_branding.php)
 * and, indirectly, which section key each public page reads via
 * agencyPageContent($agencyId, $sectionKey, $language).
 *
 * Sections are keyed by content, not by physical page file: 'counters',
 * 'team', and 'testimonials' each render identically on multiple pages
 * (index.php + about.php + their own dedicated page), so they share one
 * underlying content row rather than needing to be edited separately per
 * page. See migration 010's comment for why content is a JSON blob rather
 * than one column per field.
 */
function agencyContentSections()
{
    return [
        'hero' => [
            'label' => 'Home - Hero banner',
            'fields' => [
                ['key' => 'headline', 'label' => 'Headline', 'type' => 'text'],
                ['key' => 'subtext', 'label' => 'Subtext', 'type' => 'text'],
            ],
        ],
        'about' => [
            'label' => 'About section',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'vision_title', 'label' => 'Vision - title', 'type' => 'text'],
                ['key' => 'vision_text', 'label' => 'Vision - text', 'type' => 'textarea'],
                ['key' => 'mission_title', 'label' => 'Mission - title', 'type' => 'text'],
                ['key' => 'mission_text', 'label' => 'Mission - text', 'type' => 'textarea'],
                ['key' => 'extra_text', 'label' => 'Additional paragraph', 'type' => 'textarea'],
                ['key' => 'years_experience', 'label' => 'Years of experience (number)', 'type' => 'text'],
                ['key' => 'bullet_1', 'label' => 'Bullet point 1', 'type' => 'text'],
                ['key' => 'bullet_2', 'label' => 'Bullet point 2', 'type' => 'text'],
                ['key' => 'bullet_3', 'label' => 'Bullet point 3', 'type' => 'text'],
                ['key' => 'bullet_4', 'label' => 'Bullet point 4', 'type' => 'text'],
                ['key' => 'founder_name', 'label' => 'Founder - name', 'type' => 'text'],
                ['key' => 'founder_title', 'label' => 'Founder - title', 'type' => 'text'],
            ],
        ],
        'features' => [
            'label' => 'Features section',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'item_1_title', 'label' => 'Feature 1 - title', 'type' => 'text'],
                ['key' => 'item_1_text', 'label' => 'Feature 1 - text', 'type' => 'textarea'],
                ['key' => 'item_2_title', 'label' => 'Feature 2 - title', 'type' => 'text'],
                ['key' => 'item_2_text', 'label' => 'Feature 2 - text', 'type' => 'textarea'],
                ['key' => 'item_3_title', 'label' => 'Feature 3 - title', 'type' => 'text'],
                ['key' => 'item_3_text', 'label' => 'Feature 3 - text', 'type' => 'textarea'],
                ['key' => 'item_4_title', 'label' => 'Feature 4 - title', 'type' => 'text'],
                ['key' => 'item_4_text', 'label' => 'Feature 4 - text', 'type' => 'textarea'],
            ],
        ],
        'counters' => [
            'label' => 'Stats counter (shown on Home, About, Service, Blog)',
            'fields' => [
                ['key' => 'counter_1_value', 'label' => 'Counter 1 - value', 'type' => 'text'],
                ['key' => 'counter_1_label', 'label' => 'Counter 1 - label', 'type' => 'text'],
                ['key' => 'counter_2_value', 'label' => 'Counter 2 - value', 'type' => 'text'],
                ['key' => 'counter_2_label', 'label' => 'Counter 2 - label', 'type' => 'text'],
                ['key' => 'counter_3_value', 'label' => 'Counter 3 - value', 'type' => 'text'],
                ['key' => 'counter_3_label', 'label' => 'Counter 3 - label', 'type' => 'text'],
                ['key' => 'counter_4_value', 'label' => 'Counter 4 - value', 'type' => 'text'],
                ['key' => 'counter_4_label', 'label' => 'Counter 4 - label', 'type' => 'text'],
            ],
        ],
        'services' => [
            'label' => 'Services section',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'item_1_title', 'label' => 'Service 1 - title', 'type' => 'text'],
                ['key' => 'item_1_text', 'label' => 'Service 1 - text', 'type' => 'textarea'],
                ['key' => 'item_2_title', 'label' => 'Service 2 - title', 'type' => 'text'],
                ['key' => 'item_2_text', 'label' => 'Service 2 - text', 'type' => 'textarea'],
                ['key' => 'item_3_title', 'label' => 'Service 3 - title', 'type' => 'text'],
                ['key' => 'item_3_text', 'label' => 'Service 3 - text', 'type' => 'textarea'],
                ['key' => 'item_4_title', 'label' => 'Service 4 - title', 'type' => 'text'],
                ['key' => 'item_4_text', 'label' => 'Service 4 - text', 'type' => 'textarea'],
                ['key' => 'item_5_title', 'label' => 'Service 5 - title', 'type' => 'text'],
                ['key' => 'item_5_text', 'label' => 'Service 5 - text', 'type' => 'textarea'],
                ['key' => 'item_6_title', 'label' => 'Service 6 - title', 'type' => 'text'],
                ['key' => 'item_6_text', 'label' => 'Service 6 - text', 'type' => 'textarea'],
            ],
        ],
        'steps' => [
            'label' => 'Process steps section',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'step_1_title', 'label' => 'Step 1 - title', 'type' => 'text'],
                ['key' => 'step_1_text', 'label' => 'Step 1 - text', 'type' => 'textarea'],
                ['key' => 'step_2_title', 'label' => 'Step 2 - title', 'type' => 'text'],
                ['key' => 'step_2_text', 'label' => 'Step 2 - text', 'type' => 'textarea'],
                ['key' => 'step_3_title', 'label' => 'Step 3 - title', 'type' => 'text'],
                ['key' => 'step_3_text', 'label' => 'Step 3 - text', 'type' => 'textarea'],
            ],
        ],
        'team' => [
            'label' => 'Team section (shown on Home, About, Team)',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'member_1_name', 'label' => 'Member 1 - name', 'type' => 'text'],
                ['key' => 'member_1_role', 'label' => 'Member 1 - role', 'type' => 'text'],
                ['key' => 'member_2_name', 'label' => 'Member 2 - name', 'type' => 'text'],
                ['key' => 'member_2_role', 'label' => 'Member 2 - role', 'type' => 'text'],
                ['key' => 'member_3_name', 'label' => 'Member 3 - name', 'type' => 'text'],
                ['key' => 'member_3_role', 'label' => 'Member 3 - role', 'type' => 'text'],
                ['key' => 'member_4_name', 'label' => 'Member 4 - name', 'type' => 'text'],
                ['key' => 'member_4_role', 'label' => 'Member 4 - role', 'type' => 'text'],
            ],
        ],
        'testimonials' => [
            'label' => 'Testimonials section (shown on Home, Service, Testimonial)',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'item_1_name', 'label' => 'Review 1 - name', 'type' => 'text'],
                ['key' => 'item_1_role', 'label' => 'Review 1 - role', 'type' => 'text'],
                ['key' => 'item_1_text', 'label' => 'Review 1 - text', 'type' => 'textarea'],
                ['key' => 'item_2_name', 'label' => 'Review 2 - name', 'type' => 'text'],
                ['key' => 'item_2_role', 'label' => 'Review 2 - role', 'type' => 'text'],
                ['key' => 'item_2_text', 'label' => 'Review 2 - text', 'type' => 'textarea'],
                ['key' => 'item_3_name', 'label' => 'Review 3 - name', 'type' => 'text'],
                ['key' => 'item_3_role', 'label' => 'Review 3 - role', 'type' => 'text'],
                ['key' => 'item_3_text', 'label' => 'Review 3 - text', 'type' => 'textarea'],
            ],
        ],
        'contact' => [
            'label' => 'Contact page',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'address', 'label' => 'Address', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'map_embed_url', 'label' => 'Google Maps embed URL', 'type' => 'text'],
            ],
        ],
        'blog' => [
            'label' => 'Blog page',
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'intro', 'label' => 'Intro paragraph', 'type' => 'textarea'],
                ['key' => 'banner_title', 'label' => 'Banner - small title', 'type' => 'text'],
                ['key' => 'banner_subtitle', 'label' => 'Banner - headline', 'type' => 'text'],
                ['key' => 'banner_text', 'label' => 'Banner - text', 'type' => 'text'],
            ],
        ],
    ];
}
