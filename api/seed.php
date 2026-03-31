<?php
// ============================================================================
// seed.php -- One-time seed data insertion for Slash 301 PM
// Run once via browser or CLI: php seed.php
// DELETE THIS FILE AFTER USE (or protect it)
// ============================================================================

require_once __DIR__ . '/db.php';

// Safety check: if data already exists, don't re-seed
$db = getDb();
$check = $db->querySingle('SELECT COUNT(*) FROM users');
if ($check > 0) {
    if (php_sapi_name() === 'cli') {
        echo "Database already seeded ({$check} users). Delete data/slash301pm.db to re-seed.\n";
    } else {
        jsonResponse(['message' => "Database already seeded ({$check} users). Delete data/slash301pm.db to re-seed."]);
    }
    exit;
}

// Default password hash for all seed users: Password123!
$defaultHash = password_hash('Password123!', PASSWORD_DEFAULT);

// ============================================================================
// BRANDS (extracted from frontend "projects" client field)
// ============================================================================
$brands = [
    ['id' => 'brand1',  'name' => 'The Meridian Collection',  'prefix' => 'MERC'],
    ['id' => 'brand2',  'name' => 'Harbour & Helm Hotels',    'prefix' => 'HARB'],
    ['id' => 'brand3',  'name' => 'The Ashford Grand',        'prefix' => 'ASHF'],
    ['id' => 'brand4',  'name' => 'Coastal & Co. Resorts',    'prefix' => 'COAS'],
    ['id' => 'brand5',  'name' => 'The Blackwood Boutique',   'prefix' => 'BLCK'],
    ['id' => 'brand6',  'name' => 'Solaris Hotels & Spa',     'prefix' => 'SOLA'],
    ['id' => 'brand7',  'name' => 'The Northgate Group',      'prefix' => 'NORT'],
    ['id' => 'brand8',  'name' => 'Vela Luxury Resorts',      'prefix' => 'VELA'],
    ['id' => 'brand9',  'name' => 'The Copperleaf Collection', 'prefix' => 'COPP'],
    ['id' => 'brand10', 'name' => 'Aurum City Hotels',        'prefix' => 'AURU'],
];

// ============================================================================
// CAMPAIGNS (= frontend "projects")
// ============================================================================
$campaigns = [
    ['id' => 'proj1',  'brand_id' => 'brand1',  'name' => 'Grand Opening London',       'description' => 'Launch campaign for the new London flagship property',                            'status' => 'active'],
    ['id' => 'proj2',  'brand_id' => 'brand2',  'name' => 'Summer Sailing Season',      'description' => 'Seasonal campaign for marina and harbour packages',                               'status' => 'active'],
    ['id' => 'proj3',  'brand_id' => 'brand3',  'name' => 'Heritage Collection',        'description' => 'Showcase the historic suites and dining experiences',                              'status' => 'active'],
    ['id' => 'proj4',  'brand_id' => 'brand4',  'name' => 'Beach Club Launch',          'description' => 'New beach club opening in Algarve',                                                'status' => 'active'],
    ['id' => 'proj5',  'brand_id' => 'brand5',  'name' => 'Midnight Series',            'description' => 'Dark luxury campaign for the winter collection of experiences',                    'status' => 'active'],
    ['id' => 'proj6',  'brand_id' => 'brand6',  'name' => 'Wellness Retreat 2026',      'description' => 'Spring wellness retreat promotion across Mediterranean properties',                'status' => 'active'],
    ['id' => 'proj7',  'brand_id' => 'brand7',  'name' => 'Conference Season',          'description' => 'Corporate conference and events packages for Q2',                                  'status' => 'active'],
    ['id' => 'proj8',  'brand_id' => 'brand8',  'name' => 'Island Escape Collection',   'description' => 'Premium island getaway campaign for Maldives and Seychelles properties',           'status' => 'active'],
    ['id' => 'proj9',  'brand_id' => 'brand9',  'name' => 'Autumn Harvest',             'description' => 'Seasonal food and wine experience campaign',                                      'status' => 'active'],
    ['id' => 'proj10', 'brand_id' => 'brand10', 'name' => 'City Breaks 2026',           'description' => 'Urban luxury short-stay campaign for European city properties',                    'status' => 'active'],
];

// ============================================================================
// USERS -- 13 Agency Team + 20 Client Contacts
// Username format: firstname-lastname-role (lowercase, hyphenated)
// ============================================================================
function makeUsername(string $name, string $role): string {
    $parts = explode(' ', strtolower(trim($name)));
    // Handle names with special characters
    $parts = array_map(function($p) {
        return preg_replace('/[^a-z]/', '', $p);
    }, $parts);
    return implode('-', $parts) . '-' . strtolower($role);
}

// Brand ID map for clients
$clientBrandMap = [
    'The Meridian Collection'  => 'brand1',
    'Harbour & Helm Hotels'    => 'brand2',
    'The Ashford Grand'        => 'brand3',
    'Coastal & Co. Resorts'    => 'brand4',
    'The Blackwood Boutique'   => 'brand5',
    'Solaris Hotels & Spa'     => 'brand6',
    'The Northgate Group'      => 'brand7',
    'Vela Luxury Resorts'      => 'brand8',
    'The Copperleaf Collection' => 'brand9',
    'Aurum City Hotels'        => 'brand10',
];

$users = [
    // Agency Team (13)
    ['id' => 'p1',  'name' => 'Priya Nair',           'email' => 'priya@slash301.com',              'role' => 'COO',        'color' => '#dc2626', 'brand' => null],
    ['id' => 'p2',  'name' => 'Dominic Walsh',         'email' => 'dominic@slash301.com',            'role' => 'ECD',        'color' => '#8b5cf6', 'brand' => null],
    ['id' => 'p3',  'name' => 'Sarah Chen',            'email' => 'sarah@slash301.com',              'role' => 'PM',         'color' => '#3b82f6', 'brand' => null],
    ['id' => 'p4',  'name' => 'Lena Müller',           'email' => 'lena@slash301.com',               'role' => 'PM',         'color' => '#2563eb', 'brand' => null],
    ['id' => 'p5',  'name' => 'Morgan Davis',          'email' => 'morgan@slash301.com',             'role' => 'Traffic',    'color' => '#0ea5e9', 'brand' => null],
    ['id' => 'p6',  'name' => 'Ryan Okafor',           'email' => 'ryan@slash301.com',               'role' => 'Traffic',    'color' => '#0284c7', 'brand' => null],
    ['id' => 'p7',  'name' => 'Isabelle Fontaine',     'email' => 'isabelle@slash301.com',           'role' => 'CD',         'color' => '#ec4899', 'brand' => null],
    ['id' => 'p8',  'name' => 'James Park',            'email' => 'james@slash301.com',              'role' => 'CD',         'color' => '#db2777', 'brand' => null],
    ['id' => 'p9',  'name' => 'Alex Thompson',         'email' => 'alex@slash301.com',               'role' => 'Copywriter', 'color' => '#f97316', 'brand' => null],
    ['id' => 'p10', 'name' => 'Chloe Mwangi',          'email' => 'chloe@slash301.com',              'role' => 'Copywriter', 'color' => '#ea580c', 'brand' => null],
    ['id' => 'p11', 'name' => 'Kim Lee',               'email' => 'kim@slash301.com',                'role' => 'Designer',   'color' => '#14b8a6', 'brand' => null],
    ['id' => 'p12', 'name' => 'Tariq Hassan',          'email' => 'tariq@slash301.com',              'role' => 'Designer',   'color' => '#0d9488', 'brand' => null],
    ['id' => 'p13', 'name' => 'Jordan Blake',          'email' => 'jordan@slash301.com',             'role' => 'QA',         'color' => '#6366f1', 'brand' => null],

    // Client Contacts (20 = 2 per brand)
    ['id' => 'c1',  'name' => 'Sophie Vandenberg',     'email' => 'sophie@meridiancollection.com',   'role' => 'Client',     'color' => '#b45309', 'brand' => 'The Meridian Collection'],
    ['id' => 'c2',  'name' => 'Charles Meridian III',   'email' => 'charles@meridiancollection.com', 'role' => 'Client',     'color' => '#92400e', 'brand' => 'The Meridian Collection'],
    ['id' => 'c3',  'name' => 'Luca Ferretti',         'email' => 'luca@harbourhelm.com',            'role' => 'Client',     'color' => '#1d4ed8', 'brand' => 'Harbour & Helm Hotels'],
    ['id' => 'c4',  'name' => 'Nina Bjornstad',        'email' => 'nina@harbourhelm.com',            'role' => 'Client',     'color' => '#1e40af', 'brand' => 'Harbour & Helm Hotels'],
    ['id' => 'c5',  'name' => 'Preethi Subramaniam',   'email' => 'preethi@ashfordgrand.com',        'role' => 'Client',     'color' => '#7c3aed', 'brand' => 'The Ashford Grand'],
    ['id' => 'c6',  'name' => 'Oliver Ashford',        'email' => 'oliver@ashfordgrand.com',         'role' => 'Client',     'color' => '#6d28d9', 'brand' => 'The Ashford Grand'],
    ['id' => 'c7',  'name' => 'Mia Johansson',         'email' => 'mia@coastalco.com',               'role' => 'Client',     'color' => '#0891b2', 'brand' => 'Coastal & Co. Resorts'],
    ['id' => 'c8',  'name' => 'Rafael Costa',          'email' => 'rafael@coastalco.com',            'role' => 'Client',     'color' => '#0e7490', 'brand' => 'Coastal & Co. Resorts'],
    ['id' => 'c9',  'name' => 'Yuki Tanaka',           'email' => 'yuki@blackwoodboutique.com',      'role' => 'Client',     'color' => '#374151', 'brand' => 'The Blackwood Boutique'],
    ['id' => 'c10', 'name' => 'Harriet Blackwood',     'email' => 'harriet@blackwoodboutique.com',   'role' => 'Client',     'color' => '#1f2937', 'brand' => 'The Blackwood Boutique'],
    ['id' => 'c11', 'name' => 'Diego Reyes',           'email' => 'diego@solarishotels.com',         'role' => 'Client',     'color' => '#ca8a04', 'brand' => 'Solaris Hotels & Spa'],
    ['id' => 'c12', 'name' => 'Amara Osei',            'email' => 'amara@solarishotels.com',         'role' => 'Client',     'color' => '#a16207', 'brand' => 'Solaris Hotels & Spa'],
    ['id' => 'c13', 'name' => "Fionnuala O'Brien",     'email' => 'fionnuala@northgategroup.com',    'role' => 'Client',     'color' => '#15803d', 'brand' => 'The Northgate Group'],
    ['id' => 'c14', 'name' => 'Callum Northgate',      'email' => 'callum@northgategroup.com',       'role' => 'Client',     'color' => '#166534', 'brand' => 'The Northgate Group'],
    ['id' => 'c15', 'name' => 'Ananya Krishnan',       'email' => 'ananya@velaresorts.com',          'role' => 'Client',     'color' => '#9333ea', 'brand' => 'Vela Luxury Resorts'],
    ['id' => 'c16', 'name' => 'Marco Bianchi',         'email' => 'marco@velaresorts.com',           'role' => 'Client',     'color' => '#7e22ce', 'brand' => 'Vela Luxury Resorts'],
    ['id' => 'c17', 'name' => 'Zara Adeyemi',          'email' => 'zara@copperleaf.com',             'role' => 'Client',     'color' => '#c2410c', 'brand' => 'The Copperleaf Collection'],
    ['id' => 'c18', 'name' => 'Pieter de Vries',       'email' => 'pieter@copperleaf.com',           'role' => 'Client',     'color' => '#9a3412', 'brand' => 'The Copperleaf Collection'],
    ['id' => 'c19', 'name' => 'Ben Whitfield',         'email' => 'ben@aurumcity.com',               'role' => 'Client',     'color' => '#854d0e', 'brand' => 'Aurum City Hotels'],
    ['id' => 'c20', 'name' => 'Valentina Aureli',      'email' => 'valentina@aurumcity.com',         'role' => 'Client',     'color' => '#713f12', 'brand' => 'Aurum City Hotels'],
];

// ============================================================================
// JOBS -- 18 jobs matching frontend seed data exactly
// ============================================================================
$jobs = [
    // The Meridian Collection (proj1)
    ['id' => 'job1',  'job_number' => 'MERC-001', 'campaign_id' => 'proj1',  'title' => 'Grand Opening Social Launch',
     'description' => 'Social launch package for The Meridian London flagship opening. Hero images, carousel, and story content for Instagram, Facebook, and LinkedIn.',
     'status' => 'Inbox', 'delivery_date' => '2026-03-01', 'sort_order' => 0, 'created_at' => '2026-02-15'],
    ['id' => 'job2',  'job_number' => 'MERC-002', 'campaign_id' => 'proj1',  'title' => 'Grand Opening Email Blast',
     'description' => 'Pre-launch email campaign to loyalty members announcing London opening. Two emails: teaser + official invitation.',
     'status' => 'Inbox', 'delivery_date' => '2026-03-05', 'sort_order' => 1, 'created_at' => '2026-02-15'],
    ['id' => 'job3',  'job_number' => 'MERC-003', 'campaign_id' => 'proj1',  'title' => 'Grand Opening Landing Page',
     'description' => 'Dedicated landing page for the London property with booking integration and virtual tour embed.',
     'status' => 'Inbox', 'delivery_date' => '2026-03-10', 'sort_order' => 2, 'created_at' => '2026-02-16'],

    // Harbour & Helm Hotels (proj2)
    ['id' => 'job4',  'job_number' => 'HARB-001', 'campaign_id' => 'proj2',  'title' => 'Marina Weekend Package',
     'description' => 'Social and display campaign for the weekend marina package. Targeting couples and families.',
     'status' => 'In Progress', 'delivery_date' => '2026-02-28', 'sort_order' => 0, 'created_at' => '2026-01-22'],
    ['id' => 'job5',  'job_number' => 'HARB-002', 'campaign_id' => 'proj2',  'title' => 'Sailing Regatta Promo',
     'description' => 'Event promotion for the annual Harbour & Helm sailing regatta. Print and digital.',
     'status' => 'Today', 'delivery_date' => '2026-02-25', 'sort_order' => 1, 'created_at' => '2026-01-23'],

    // The Ashford Grand (proj3)
    ['id' => 'job6',  'job_number' => 'ASHF-001', 'campaign_id' => 'proj3',  'title' => 'Heritage Suite Photography',
     'description' => 'Social content showcasing the renovated heritage suites. Lifestyle photography with copy overlays.',
     'status' => 'In Progress', 'delivery_date' => '2026-02-20', 'sort_order' => 0, 'created_at' => '2026-01-18',
     'all_tasks_completed_at' => '2026-02-10'],
    ['id' => 'job7',  'job_number' => 'ASHF-002', 'campaign_id' => 'proj3',  'title' => 'Fine Dining Menu Launch',
     'description' => 'New seasonal menu launch for The Ashford Grand restaurant. Email and social.',
     'status' => 'Approved (Internal)', 'delivery_date' => '2026-02-22', 'sort_order' => 1, 'created_at' => '2026-01-19',
     'internal_approved_by' => 'p7', 'internal_approved_at' => '2026-02-12', 'all_tasks_completed_at' => '2026-02-11'],

    // Coastal & Co. Resorts (proj4)
    ['id' => 'job8',  'job_number' => 'COAS-001', 'campaign_id' => 'proj4',  'title' => 'Beach Club Teaser Video',
     'description' => 'Short teaser video for the new Algarve beach club. 15s and 30s cuts for social.',
     'status' => 'This Week', 'delivery_date' => '2026-02-27', 'sort_order' => 0, 'created_at' => '2026-01-28'],
    ['id' => 'job9',  'job_number' => 'COAS-002', 'campaign_id' => 'proj4',  'title' => 'Beach Club Social Launch',
     'description' => 'Full social package for the beach club opening day. Carousel, stories, and Reels.',
     'status' => 'Inbox', 'delivery_date' => '2026-03-05', 'sort_order' => 1, 'created_at' => '2026-01-29'],

    // The Blackwood Boutique (proj5)
    ['id' => 'job10', 'job_number' => 'BLCK-001', 'campaign_id' => 'proj5',  'title' => 'Midnight Series Social',
     'description' => 'Dark luxury social campaign for The Blackwood Boutique winter experiences.',
     'status' => 'Approved (External)', 'delivery_date' => '2026-02-05', 'sort_order' => 0, 'created_at' => '2026-01-12',
     'internal_approved_by' => 'p7', 'internal_approved_at' => '2026-01-28',
     'client_approved_by' => 'c9', 'client_approved_at' => '2026-01-30', 'all_tasks_completed_at' => '2026-01-25'],

    // Solaris Hotels & Spa (proj6)
    ['id' => 'job11', 'job_number' => 'SOLA-001', 'campaign_id' => 'proj6',  'title' => 'Wellness Retreat Video',
     'description' => 'Hero video for the spring wellness retreat. Spa, yoga, and mindfulness content.',
     'status' => 'In Progress', 'delivery_date' => '2026-03-01', 'sort_order' => 0, 'created_at' => '2026-02-06'],
    ['id' => 'job12', 'job_number' => 'SOLA-002', 'campaign_id' => 'proj6',  'title' => 'Spa Day Pass Promo',
     'description' => 'Day pass promotion for local residents. Digital display and social.',
     'status' => 'Inbox', 'delivery_date' => '2026-03-08', 'sort_order' => 1, 'created_at' => '2026-02-07'],

    // The Northgate Group (proj7)
    ['id' => 'job13', 'job_number' => 'NORT-001', 'campaign_id' => 'proj7',  'title' => 'Conference Packages Brochure',
     'description' => 'Digital and print brochure for Q2 corporate conference packages.',
     'status' => 'Inbox', 'delivery_date' => '2026-03-15', 'sort_order' => 0, 'created_at' => '2026-02-12'],

    // Vela Luxury Resorts (proj8)
    ['id' => 'job14', 'job_number' => 'VELA-001', 'campaign_id' => 'proj8',  'title' => 'Maldives Villa Collection',
     'description' => 'Premium social and display campaign for the new overwater villas in Maldives.',
     'status' => 'In Progress', 'delivery_date' => '2026-02-28', 'sort_order' => 0, 'created_at' => '2026-01-30'],
    ['id' => 'job15', 'job_number' => 'VELA-002', 'campaign_id' => 'proj8',  'title' => 'Seychelles Honeymoon Package',
     'description' => 'Honeymoon package campaign targeting engaged couples. Email, social, and landing page.',
     'status' => 'Waiting', 'delivery_date' => '2026-03-10', 'sort_order' => 1, 'created_at' => '2026-02-01'],

    // The Copperleaf Collection (proj9)
    ['id' => 'job16', 'job_number' => 'COPP-001', 'campaign_id' => 'proj9',  'title' => 'Autumn Harvest Social',
     'description' => 'Food and wine experience campaign for autumn. Photography-led social content.',
     'status' => 'On Hold', 'delivery_date' => '2026-04-01', 'sort_order' => 0, 'created_at' => '2026-01-08'],

    // Aurum City Hotels (proj10)
    ['id' => 'job17', 'job_number' => 'AURU-001', 'campaign_id' => 'proj10', 'title' => 'Paris City Break',
     'description' => 'City break campaign for the Paris property. Weekend getaway focus.',
     'status' => 'In Progress', 'delivery_date' => '2026-03-01', 'sort_order' => 0, 'created_at' => '2026-02-10'],
    ['id' => 'job18', 'job_number' => 'AURU-002', 'campaign_id' => 'proj10', 'title' => 'London Rooftop Bar Launch',
     'description' => 'New rooftop bar opening at the London property. Event promo and social.',
     'status' => 'Today', 'delivery_date' => '2026-02-26', 'sort_order' => 1, 'created_at' => '2026-02-11'],
];

// ============================================================================
// JOB ASSIGNMENTS -- maps from frontend assignments object
// ============================================================================
$assignments = [
    // MERC-001
    ['job_id' => 'job1', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job1', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job1', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job1', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job1', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job1', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job1', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job1', 'user_id' => 'c1',  'role_on_job' => 'Client'],
    // MERC-002
    ['job_id' => 'job2', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job2', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job2', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job2', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job2', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job2', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job2', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job2', 'user_id' => 'c1',  'role_on_job' => 'Client'],
    // MERC-003
    ['job_id' => 'job3', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job3', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job3', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job3', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job3', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job3', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job3', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job3', 'user_id' => 'c2',  'role_on_job' => 'Client'],
    // HARB-001
    ['job_id' => 'job4', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job4', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job4', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job4', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job4', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job4', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job4', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job4', 'user_id' => 'c3',  'role_on_job' => 'Client'],
    // HARB-002
    ['job_id' => 'job5', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job5', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job5', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job5', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job5', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job5', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job5', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job5', 'user_id' => 'c4',  'role_on_job' => 'Client'],
    // ASHF-001
    ['job_id' => 'job6', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job6', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job6', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job6', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job6', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job6', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job6', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job6', 'user_id' => 'c5',  'role_on_job' => 'Client'],
    // ASHF-002
    ['job_id' => 'job7', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job7', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job7', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job7', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job7', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job7', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job7', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job7', 'user_id' => 'c6',  'role_on_job' => 'Client'],
    // COAS-001
    ['job_id' => 'job8', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job8', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job8', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job8', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job8', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job8', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job8', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job8', 'user_id' => 'c7',  'role_on_job' => 'Client'],
    // COAS-002
    ['job_id' => 'job9', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job9', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job9', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job9', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job9', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job9', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job9', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job9', 'user_id' => 'c8',  'role_on_job' => 'Client'],
    // BLCK-001
    ['job_id' => 'job10', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job10', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job10', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job10', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job10', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job10', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job10', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job10', 'user_id' => 'c9',  'role_on_job' => 'Client'],
    // SOLA-001
    ['job_id' => 'job11', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job11', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job11', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job11', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job11', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job11', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job11', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job11', 'user_id' => 'c11', 'role_on_job' => 'Client'],
    // SOLA-002
    ['job_id' => 'job12', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job12', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job12', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job12', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job12', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job12', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job12', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job12', 'user_id' => 'c12', 'role_on_job' => 'Client'],
    // NORT-001
    ['job_id' => 'job13', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job13', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job13', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job13', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job13', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job13', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job13', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job13', 'user_id' => 'c13', 'role_on_job' => 'Client'],
    // VELA-001
    ['job_id' => 'job14', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job14', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job14', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job14', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job14', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job14', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job14', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job14', 'user_id' => 'c15', 'role_on_job' => 'Client'],
    // VELA-002
    ['job_id' => 'job15', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job15', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job15', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job15', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job15', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job15', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job15', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job15', 'user_id' => 'c16', 'role_on_job' => 'Client'],
    // COPP-001
    ['job_id' => 'job16', 'user_id' => 'p3',  'role_on_job' => 'PM'],
    ['job_id' => 'job16', 'user_id' => 'p5',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job16', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job16', 'user_id' => 'p7',  'role_on_job' => 'CD'],
    ['job_id' => 'job16', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job16', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job16', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job16', 'user_id' => 'c17', 'role_on_job' => 'Client'],
    // AURU-001
    ['job_id' => 'job17', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job17', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job17', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job17', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job17', 'user_id' => 'p10', 'role_on_job' => 'Copywriter'],
    ['job_id' => 'job17', 'user_id' => 'p12', 'role_on_job' => 'Designer'],
    ['job_id' => 'job17', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job17', 'user_id' => 'c19', 'role_on_job' => 'Client'],
    // AURU-002
    ['job_id' => 'job18', 'user_id' => 'p4',  'role_on_job' => 'PM'],
    ['job_id' => 'job18', 'user_id' => 'p6',  'role_on_job' => 'Traffic'],
    ['job_id' => 'job18', 'user_id' => 'p2',  'role_on_job' => 'ECD'],
    ['job_id' => 'job18', 'user_id' => 'p8',  'role_on_job' => 'CD'],
    ['job_id' => 'job18', 'user_id' => 'p9',  'role_on_job' => 'Copywriter'],
    ['job_id' => 'job18', 'user_id' => 'p11', 'role_on_job' => 'Designer'],
    ['job_id' => 'job18', 'user_id' => 'p13', 'role_on_job' => 'QA'],
    ['job_id' => 'job18', 'user_id' => 'c20', 'role_on_job' => 'Client'],
];

// ============================================================================
// TASKS -- 36 tasks (copy + media per job), matching frontend exactly
// Frontend uses templateId ('copy','media') -> maps to tasks.type
// ============================================================================
$tasks = [
    // MERC-001
    ['id' => 't1',  'job_id' => 'job1',  'type' => 'copy',  'status' => 'Not Started', 'assigned_to' => 'p9',  'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't2',  'job_id' => 'job1',  'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // MERC-002
    ['id' => 't3',  'job_id' => 'job2',  'type' => 'copy',  'status' => 'Not Started', 'assigned_to' => 'p9',  'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't4',  'job_id' => 'job2',  'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // MERC-003
    ['id' => 't5',  'job_id' => 'job3',  'type' => 'copy',  'status' => 'Not Started', 'assigned_to' => 'p10', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't6',  'job_id' => 'job3',  'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // HARB-001
    ['id' => 't7',  'job_id' => 'job4',  'type' => 'copy',  'status' => 'In Progress', 'assigned_to' => 'p10', 'character_count' => 180, 'content' => 'Escape to the harbour. Weekend marina packages from £299 with sunset dining and sailing excursions included.', 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't8',  'job_id' => 'job4',  'type' => 'media', 'status' => 'In Progress', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => 'marina-hero-draft.jpg', 'file_type' => 'image', 'completed_at' => null, 'sort_order' => 1],
    // HARB-002
    ['id' => 't9',  'job_id' => 'job5',  'type' => 'copy',  'status' => 'In Progress', 'assigned_to' => 'p9',  'character_count' => 210, 'content' => 'Set sail for the annual Harbour & Helm Regatta. Three days of racing, live music, and gourmet seafood on the waterfront.', 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't10', 'job_id' => 'job5',  'type' => 'media', 'status' => 'In Progress', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => 'regatta-poster-v1.jpg', 'file_type' => 'image', 'completed_at' => null, 'sort_order' => 1],
    // ASHF-001
    ['id' => 't11', 'job_id' => 'job6',  'type' => 'copy',  'status' => 'Done', 'assigned_to' => 'p9',  'character_count' => 320, 'content' => "Step into history. The Ashford Grand's Heritage Suites have been meticulously restored to their 1920s grandeur — original cornicing, hand-painted wallpapers, and antique furnishings paired with every modern comfort.", 'file_url' => null, 'file_type' => null, 'completed_at' => '2026-02-09', 'sort_order' => 0],
    ['id' => 't12', 'job_id' => 'job6',  'type' => 'media', 'status' => 'Done', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => 'heritage-suite-carousel.zip', 'file_type' => 'image', 'completed_at' => '2026-02-10', 'sort_order' => 1],
    // ASHF-002
    ['id' => 't13', 'job_id' => 'job7',  'type' => 'copy',  'status' => 'Done', 'assigned_to' => 'p10', 'character_count' => 280, 'content' => 'A new chapter at The Ashford Grand. Chef Laurent introduces the Spring Tasting Menu — six courses celebrating the best of British produce with a French sensibility.', 'file_url' => null, 'file_type' => null, 'completed_at' => '2026-02-10', 'sort_order' => 0],
    ['id' => 't14', 'job_id' => 'job7',  'type' => 'media', 'status' => 'Done', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => 'menu-launch-email.html', 'file_type' => 'document', 'completed_at' => '2026-02-11', 'sort_order' => 1],
    // COAS-001
    ['id' => 't15', 'job_id' => 'job8',  'type' => 'copy',  'status' => 'In Progress', 'assigned_to' => 'p10', 'character_count' => 90, 'content' => 'Something new is coming to the Algarve. Beach Club by Coastal & Co. — opening March 2026.', 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't16', 'job_id' => 'job8',  'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // COAS-002
    ['id' => 't17', 'job_id' => 'job9',  'type' => 'copy',  'status' => 'Not Started', 'assigned_to' => 'p9',  'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't18', 'job_id' => 'job9',  'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // BLCK-001
    ['id' => 't19', 'job_id' => 'job10', 'type' => 'copy',  'status' => 'Done', 'assigned_to' => 'p9',  'character_count' => 250, 'content' => "After dark, The Blackwood reveals itself. Candlelit tasting rooms, moonlit gardens, and suites dressed in midnight velvet. Winter at The Blackwood is not a season — it's an invitation.", 'file_url' => null, 'file_type' => null, 'completed_at' => '2026-01-24', 'sort_order' => 0],
    ['id' => 't20', 'job_id' => 'job10', 'type' => 'media', 'status' => 'Done', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => 'midnight-series-final.zip', 'file_type' => 'image', 'completed_at' => '2026-01-25', 'sort_order' => 1],
    // SOLA-001
    ['id' => 't21', 'job_id' => 'job11', 'type' => 'copy',  'status' => 'Done', 'assigned_to' => 'p10', 'character_count' => 290, 'content' => 'Breathe in. The Solaris Wellness Retreat is a week of transformation — morning yoga overlooking the Mediterranean, afternoon spa rituals, and evening meditation under the stars.', 'file_url' => null, 'file_type' => null, 'completed_at' => '2026-02-14', 'sort_order' => 0],
    ['id' => 't22', 'job_id' => 'job11', 'type' => 'media', 'status' => 'In Progress', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => 'wellness-hero-v2.mp4', 'file_type' => 'video', 'completed_at' => null, 'sort_order' => 1],
    // SOLA-002
    ['id' => 't23', 'job_id' => 'job12', 'type' => 'copy',  'status' => 'Not Started', 'assigned_to' => 'p9',  'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't24', 'job_id' => 'job12', 'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // NORT-001
    ['id' => 't25', 'job_id' => 'job13', 'type' => 'copy',  'status' => 'Not Started', 'assigned_to' => 'p10', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't26', 'job_id' => 'job13', 'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // VELA-001
    ['id' => 't27', 'job_id' => 'job14', 'type' => 'copy',  'status' => 'In Progress', 'assigned_to' => 'p9',  'character_count' => 340, 'content' => "Suspended between sky and sea. The new Vela Overwater Villas in the Maldives offer private infinity pools, glass-floor living rooms, and sunrise butler service. This is not a hotel — it's a world of its own.", 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't28', 'job_id' => 'job14', 'type' => 'media', 'status' => 'In Progress', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => 'maldives-villa-draft.jpg', 'file_type' => 'image', 'completed_at' => null, 'sort_order' => 1],
    // VELA-002
    ['id' => 't29', 'job_id' => 'job15', 'type' => 'copy',  'status' => 'Waiting', 'assigned_to' => 'p10', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't30', 'job_id' => 'job15', 'type' => 'media', 'status' => 'Waiting', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // COPP-001
    ['id' => 't31', 'job_id' => 'job16', 'type' => 'copy',  'status' => 'On Hold', 'assigned_to' => 'p9',  'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't32', 'job_id' => 'job16', 'type' => 'media', 'status' => 'On Hold', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // AURU-001
    ['id' => 't33', 'job_id' => 'job17', 'type' => 'copy',  'status' => 'In Progress', 'assigned_to' => 'p10', 'character_count' => 200, 'content' => 'Paris for the weekend. The Aurum Rive Gauche puts you steps from Saint-Germain with rooms designed for the modern flaneur.', 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't34', 'job_id' => 'job17', 'type' => 'media', 'status' => 'Not Started', 'assigned_to' => 'p12', 'character_count' => null, 'content' => null, 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 1],
    // AURU-002
    ['id' => 't35', 'job_id' => 'job18', 'type' => 'copy',  'status' => 'In Progress', 'assigned_to' => 'p9',  'character_count' => 160, 'content' => 'Elevate your evening. The Aurum Skyline Bar opens February 28 — craft cocktails, city views, and a DJ residency every Friday.', 'file_url' => null, 'file_type' => null, 'completed_at' => null, 'sort_order' => 0],
    ['id' => 't36', 'job_id' => 'job18', 'type' => 'media', 'status' => 'In Progress', 'assigned_to' => 'p11', 'character_count' => null, 'content' => null, 'file_url' => 'rooftop-bar-v1.jpg', 'file_type' => 'image', 'completed_at' => null, 'sort_order' => 1],
];

// ============================================================================
// ASSETS -- 23 assets matching frontend exactly
// ============================================================================
$assets = [
    ['id' => 'a1',  'job_id' => 'job1',  'campaign_id' => 'proj1',  'name' => 'Instagram Hero Post',           'type' => 'image',    'template_id' => 'social-static',     'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-02-26', 'sort_order' => 0],
    ['id' => 'a2',  'job_id' => 'job1',  'campaign_id' => 'proj1',  'name' => 'Instagram Carousel (5 slides)', 'type' => 'image',    'template_id' => 'social-carousel',   'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-02-27', 'sort_order' => 1],
    ['id' => 'a3',  'job_id' => 'job1',  'campaign_id' => 'proj1',  'name' => 'Instagram Story (3 frames)',    'type' => 'video',    'template_id' => 'social-story',      'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-02-28', 'sort_order' => 2],
    ['id' => 'a4',  'job_id' => 'job1',  'campaign_id' => 'proj1',  'name' => 'Facebook Hero Post',            'type' => 'image',    'template_id' => 'social-static',     'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-02-26', 'sort_order' => 3],
    ['id' => 'a5',  'job_id' => 'job1',  'campaign_id' => 'proj1',  'name' => 'LinkedIn Announcement',         'type' => 'image',    'template_id' => 'social-static',     'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-02-28', 'sort_order' => 4],
    ['id' => 'a6',  'job_id' => 'job2',  'campaign_id' => 'proj1',  'name' => 'Teaser Email',                  'type' => 'document', 'template_id' => 'email-template',    'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-03-02', 'sort_order' => 0],
    ['id' => 'a7',  'job_id' => 'job2',  'campaign_id' => 'proj1',  'name' => 'Official Invitation Email',     'type' => 'document', 'template_id' => 'email-template',    'status' => 'Inbox',        'assigned_to' => 'p11', 'due_date' => '2026-03-04', 'sort_order' => 1],
    ['id' => 'a8',  'job_id' => 'job4',  'campaign_id' => 'proj2',  'name' => 'Marina Instagram Post',         'type' => 'image',    'template_id' => 'social-static',     'status' => 'In Progress',  'assigned_to' => 'p12', 'due_date' => '2026-02-25', 'sort_order' => 0],
    ['id' => 'a9',  'job_id' => 'job4',  'campaign_id' => 'proj2',  'name' => 'Marina Display Banner',         'type' => 'image',    'template_id' => 'banner-display',    'status' => 'In Progress',  'assigned_to' => 'p12', 'due_date' => '2026-02-26', 'sort_order' => 1],
    ['id' => 'a10', 'job_id' => 'job5',  'campaign_id' => 'proj2',  'name' => 'Regatta Poster',                'type' => 'image',    'template_id' => 'print-ooh',         'status' => 'Today',        'assigned_to' => 'p11', 'due_date' => '2026-02-24', 'sort_order' => 0],
    ['id' => 'a11', 'job_id' => 'job5',  'campaign_id' => 'proj2',  'name' => 'Regatta Social Reel',           'type' => 'video',    'template_id' => 'social-story',      'status' => 'Today',        'assigned_to' => 'p11', 'due_date' => '2026-02-25', 'sort_order' => 1],
    ['id' => 'a12', 'job_id' => 'job6',  'campaign_id' => 'proj3',  'name' => 'Heritage Suite Carousel',       'type' => 'image',    'template_id' => 'social-carousel',   'status' => 'Done',         'assigned_to' => 'p11', 'due_date' => '2026-02-18', 'sort_order' => 0],
    ['id' => 'a13', 'job_id' => 'job7',  'campaign_id' => 'proj3',  'name' => 'Menu Launch Email',             'type' => 'document', 'template_id' => 'email-template',    'status' => 'Done',         'assigned_to' => 'p12', 'due_date' => '2026-02-20', 'sort_order' => 0],
    ['id' => 'a14', 'job_id' => 'job7',  'campaign_id' => 'proj3',  'name' => 'Menu Social Post',              'type' => 'image',    'template_id' => 'social-static',     'status' => 'Done',         'assigned_to' => 'p12', 'due_date' => '2026-02-21', 'sort_order' => 1],
    ['id' => 'a15', 'job_id' => 'job8',  'campaign_id' => 'proj4',  'name' => 'Beach Club Teaser 15s',         'type' => 'video',    'template_id' => 'video-edit-short',  'status' => 'This Week',    'assigned_to' => 'p11', 'due_date' => '2026-02-26', 'sort_order' => 0],
    ['id' => 'a16', 'job_id' => 'job8',  'campaign_id' => 'proj4',  'name' => 'Beach Club Teaser 30s',         'type' => 'video',    'template_id' => 'video-edit-short',  'status' => 'This Week',    'assigned_to' => 'p11', 'due_date' => '2026-02-27', 'sort_order' => 1],
    ['id' => 'a17', 'job_id' => 'job10', 'campaign_id' => 'proj5',  'name' => 'Midnight Series Instagram',     'type' => 'image',    'template_id' => 'social-static',     'status' => 'Done',         'assigned_to' => 'p11', 'due_date' => '2026-01-30', 'sort_order' => 0],
    ['id' => 'a18', 'job_id' => 'job10', 'campaign_id' => 'proj5',  'name' => 'Midnight Series Story',         'type' => 'video',    'template_id' => 'social-story',      'status' => 'Done',         'assigned_to' => 'p11', 'due_date' => '2026-02-01', 'sort_order' => 1],
    ['id' => 'a19', 'job_id' => 'job11', 'campaign_id' => 'proj6',  'name' => 'Wellness Hero Video 60s',       'type' => 'video',    'template_id' => 'video-edit-long',   'status' => 'In Progress',  'assigned_to' => 'p12', 'due_date' => '2026-02-28', 'sort_order' => 0],
    ['id' => 'a20', 'job_id' => 'job14', 'campaign_id' => 'proj8',  'name' => 'Maldives Villa Instagram',      'type' => 'image',    'template_id' => 'social-static',     'status' => 'In Progress',  'assigned_to' => 'p11', 'due_date' => '2026-02-26', 'sort_order' => 0],
    ['id' => 'a21', 'job_id' => 'job14', 'campaign_id' => 'proj8',  'name' => 'Maldives Villa Story',          'type' => 'video',    'template_id' => 'social-story',      'status' => 'In Progress',  'assigned_to' => 'p11', 'due_date' => '2026-02-27', 'sort_order' => 1],
    ['id' => 'a22', 'job_id' => 'job18', 'campaign_id' => 'proj10', 'name' => 'Rooftop Bar Event Flyer',       'type' => 'image',    'template_id' => 'print-ad',          'status' => 'Today',        'assigned_to' => 'p11', 'due_date' => '2026-02-25', 'sort_order' => 0],
    ['id' => 'a23', 'job_id' => 'job18', 'campaign_id' => 'proj10', 'name' => 'Rooftop Bar Social Reel',       'type' => 'video',    'template_id' => 'social-story',      'status' => 'Today',        'assigned_to' => 'p11', 'due_date' => '2026-02-26', 'sort_order' => 1],
];

// ============================================================================
// WIKI PAGES -- 5 pages matching frontend
// ============================================================================
$wikiPages = [
    ['id' => 'wiki1', 'title' => 'The Meridian Collection', 'slug' => 'meridian-collection',
     'content' => '<h1>The Meridian Collection — Client Bible</h1><h2>Brand Overview</h2><p>The Meridian Collection is a portfolio of luxury hotels across Europe, known for timeless elegance and impeccable service. Established in 1952 by the Meridian family, now spanning 12 properties.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Sophie Vandenberg (sophie@meridiancollection.com)</li><li><strong>Brand Director:</strong> Charles Meridian III (charles@meridiancollection.com)</li></ul><h2>Brand Colours</h2><p><strong>Primary:</strong> #B45309 (Meridian Gold)</p><p><strong>Secondary:</strong> #1C1917 (Charcoal Black)</p><h2>Tone of Voice</h2><p>Refined, warm, understated luxury. Never flashy. Words like "curated", "heritage", "considered".</p>',
     'template_id' => 'client-bible', 'parent_id' => null, 'type' => 'client',
     'tags' => 'client,hotel,luxury', 'created_by' => 'p3', 'sort_order' => 0,
     'created_at' => '2026-01-05', 'updated_at' => '2026-02-15',
     'linkedJobs' => ['job1', 'job2', 'job3'], 'linkedProjects' => ['proj1']],

    ['id' => 'wiki2', 'title' => 'Grand Opening London Campaign', 'slug' => 'grand-opening-london',
     'content' => '<h1>Grand Opening London — Campaign Brief</h1><h2>Campaign Overview</h2><p><strong>Launch Date:</strong> March 1, 2026</p><p><strong>Objective:</strong> Drive awareness and bookings for the new London flagship property</p><h2>Target Audience</h2><p>Affluent travellers 35-65, UK and European markets, luxury lifestyle interest</p><h2>Creative Approach</h2><p>Elegant photography-led content. Show the property interiors, neighbourhood (Mayfair), and the Meridian service philosophy. Lead with "A new address for timeless luxury."</p>',
     'template_id' => 'campaign-log', 'parent_id' => 'wiki1', 'type' => 'campaign',
     'tags' => 'campaign,launch,london,2026', 'created_by' => 'p3', 'sort_order' => 0,
     'created_at' => '2026-02-01', 'updated_at' => '2026-02-15',
     'linkedJobs' => ['job1', 'job2', 'job3'], 'linkedProjects' => ['proj1']],

    ['id' => 'wiki3', 'title' => 'Harbour & Helm Hotels', 'slug' => 'harbour-helm',
     'content' => '<h1>Harbour & Helm Hotels — Client Bible</h1><h2>Brand Overview</h2><p>Harbour & Helm is a coastal hotel group focused on maritime heritage and seaside luxury. Five properties across the UK and Nordics.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Luca Ferretti (luca@harbourhelm.com)</li><li><strong>Brand Director:</strong> Nina Bjornstad (nina@harbourhelm.com)</li></ul><h2>Tone of Voice</h2><p>Fresh, adventurous, nautical without being kitsch. "Rugged luxury" — salt spray and starched linen.</p>',
     'template_id' => 'client-bible', 'parent_id' => null, 'type' => 'client',
     'tags' => 'client,hotel,coastal', 'created_by' => 'p4', 'sort_order' => 1,
     'created_at' => '2026-01-10', 'updated_at' => '2026-01-22',
     'linkedJobs' => ['job4', 'job5'], 'linkedProjects' => ['proj2']],

    ['id' => 'wiki4', 'title' => 'The Blackwood Boutique', 'slug' => 'blackwood-boutique',
     'content' => '<h1>The Blackwood Boutique — Client Bible</h1><h2>Brand Overview</h2><p>The Blackwood is a single-property boutique hotel in the Cotswolds, known for its dark, atmospheric aesthetic and exclusive experiences. Only 24 rooms.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Yuki Tanaka (yuki@blackwoodboutique.com)</li><li><strong>Brand Director:</strong> Harriet Blackwood (harriet@blackwoodboutique.com)</li></ul><h2>Tone of Voice</h2><p>Moody, intimate, literary. Short sentences. Rich adjectives. Think: "a whisper, not a shout."</p>',
     'template_id' => 'client-bible', 'parent_id' => null, 'type' => 'client',
     'tags' => 'client,boutique,luxury', 'created_by' => 'p3', 'sort_order' => 2,
     'created_at' => '2026-01-08', 'updated_at' => '2026-01-28',
     'linkedJobs' => ['job10'], 'linkedProjects' => ['proj5']],

    ['id' => 'wiki5', 'title' => 'Vela Luxury Resorts', 'slug' => 'vela-luxury',
     'content' => '<h1>Vela Luxury Resorts — Client Bible</h1><h2>Brand Overview</h2><p>Vela is an ultra-premium island resort brand with properties in the Maldives, Seychelles, and Bali. Overwater villas, private beaches, and butler service as standard.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Ananya Krishnan (ananya@velaresorts.com)</li><li><strong>Brand Director:</strong> Marco Bianchi (marco@velaresorts.com)</li></ul><h2>Tone of Voice</h2><p>Aspirational but never pretentious. Sensory language — sky, sea, warmth, silence. "Where the world falls away."</p>',
     'template_id' => 'client-bible', 'parent_id' => null, 'type' => 'client',
     'tags' => 'client,resort,luxury,island', 'created_by' => 'p3', 'sort_order' => 3,
     'created_at' => '2026-01-12', 'updated_at' => '2026-02-01',
     'linkedJobs' => ['job14', 'job15'], 'linkedProjects' => ['proj8']],
];

// ============================================================================
// INSERT ALL DATA
// ============================================================================

$db->exec('BEGIN TRANSACTION');

// 1. Brands
$brandStmt = $db->prepare('INSERT INTO brands (id, name, prefix) VALUES (:id, :name, :prefix)');
foreach ($brands as $b) {
    $brandStmt->bindValue(':id', $b['id'], SQLITE3_TEXT);
    $brandStmt->bindValue(':name', $b['name'], SQLITE3_TEXT);
    $brandStmt->bindValue(':prefix', $b['prefix'], SQLITE3_TEXT);
    $brandStmt->execute();
    $brandStmt->reset();
}

// 2. Campaigns
$campStmt = $db->prepare('INSERT INTO campaigns (id, brand_id, name, description, status) VALUES (:id, :brand_id, :name, :description, :status)');
foreach ($campaigns as $c) {
    $campStmt->bindValue(':id', $c['id'], SQLITE3_TEXT);
    $campStmt->bindValue(':brand_id', $c['brand_id'], SQLITE3_TEXT);
    $campStmt->bindValue(':name', $c['name'], SQLITE3_TEXT);
    $campStmt->bindValue(':description', $c['description'], SQLITE3_TEXT);
    $campStmt->bindValue(':status', $c['status'], SQLITE3_TEXT);
    $campStmt->execute();
    $campStmt->reset();
}

// 3. Users
$userStmt = $db->prepare('INSERT INTO users (id, username, password_hash, name, email, role, color, brand_id) VALUES (:id, :username, :password_hash, :name, :email, :role, :color, :brand_id)');
foreach ($users as $u) {
    $brandId = $u['brand'] ? ($clientBrandMap[$u['brand']] ?? null) : null;
    $username = makeUsername($u['name'], $u['role']);

    $userStmt->bindValue(':id', $u['id'], SQLITE3_TEXT);
    $userStmt->bindValue(':username', $username, SQLITE3_TEXT);
    $userStmt->bindValue(':password_hash', $defaultHash, SQLITE3_TEXT);
    $userStmt->bindValue(':name', $u['name'], SQLITE3_TEXT);
    $userStmt->bindValue(':email', $u['email'], SQLITE3_TEXT);
    $userStmt->bindValue(':role', $u['role'], SQLITE3_TEXT);
    $userStmt->bindValue(':color', $u['color'], SQLITE3_TEXT);
    $userStmt->bindValue(':brand_id', $brandId, SQLITE3_TEXT);
    $userStmt->execute();
    $userStmt->reset();
}

// 4. Jobs
$jobStmt = $db->prepare('INSERT INTO jobs (id, job_number, campaign_id, title, description, status, delivery_date, sort_order, created_at, all_tasks_completed_at, internal_approved_by, internal_approved_at, client_approved_by, client_approved_at) VALUES (:id, :job_number, :campaign_id, :title, :description, :status, :delivery_date, :sort_order, :created_at, :all_tasks_completed_at, :internal_approved_by, :internal_approved_at, :client_approved_by, :client_approved_at)');
foreach ($jobs as $j) {
    $jobStmt->bindValue(':id', $j['id'], SQLITE3_TEXT);
    $jobStmt->bindValue(':job_number', $j['job_number'], SQLITE3_TEXT);
    $jobStmt->bindValue(':campaign_id', $j['campaign_id'], SQLITE3_TEXT);
    $jobStmt->bindValue(':title', $j['title'], SQLITE3_TEXT);
    $jobStmt->bindValue(':description', $j['description'], SQLITE3_TEXT);
    $jobStmt->bindValue(':status', $j['status'], SQLITE3_TEXT);
    $jobStmt->bindValue(':delivery_date', $j['delivery_date'], SQLITE3_TEXT);
    $jobStmt->bindValue(':sort_order', $j['sort_order'], SQLITE3_INTEGER);
    $jobStmt->bindValue(':created_at', $j['created_at'], SQLITE3_TEXT);
    $jobStmt->bindValue(':all_tasks_completed_at', $j['all_tasks_completed_at'] ?? null, SQLITE3_TEXT);
    $jobStmt->bindValue(':internal_approved_by', $j['internal_approved_by'] ?? null, SQLITE3_TEXT);
    $jobStmt->bindValue(':internal_approved_at', $j['internal_approved_at'] ?? null, SQLITE3_TEXT);
    $jobStmt->bindValue(':client_approved_by', $j['client_approved_by'] ?? null, SQLITE3_TEXT);
    $jobStmt->bindValue(':client_approved_at', $j['client_approved_at'] ?? null, SQLITE3_TEXT);
    $jobStmt->execute();
    $jobStmt->reset();
}

// 5. Job Assignments
$assignStmt = $db->prepare('INSERT INTO job_assignments (id, job_id, user_id, role_on_job) VALUES (:id, :job_id, :user_id, :role_on_job)');
$assignCounter = 0;
foreach ($assignments as $a) {
    $assignCounter++;
    $assignStmt->bindValue(':id', 'ja' . $assignCounter, SQLITE3_TEXT);
    $assignStmt->bindValue(':job_id', $a['job_id'], SQLITE3_TEXT);
    $assignStmt->bindValue(':user_id', $a['user_id'], SQLITE3_TEXT);
    $assignStmt->bindValue(':role_on_job', $a['role_on_job'], SQLITE3_TEXT);
    $assignStmt->execute();
    $assignStmt->reset();
}

// 6. Tasks
$taskStmt = $db->prepare('INSERT INTO tasks (id, job_id, type, status, assigned_to, character_count, content, file_url, file_type, completed_at, sort_order) VALUES (:id, :job_id, :type, :status, :assigned_to, :character_count, :content, :file_url, :file_type, :completed_at, :sort_order)');
foreach ($tasks as $t) {
    $taskStmt->bindValue(':id', $t['id'], SQLITE3_TEXT);
    $taskStmt->bindValue(':job_id', $t['job_id'], SQLITE3_TEXT);
    $taskStmt->bindValue(':type', $t['type'], SQLITE3_TEXT);
    $taskStmt->bindValue(':status', $t['status'], SQLITE3_TEXT);
    $taskStmt->bindValue(':assigned_to', $t['assigned_to'], SQLITE3_TEXT);
    $taskStmt->bindValue(':character_count', $t['character_count'], $t['character_count'] !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
    $taskStmt->bindValue(':content', $t['content'], SQLITE3_TEXT);
    $taskStmt->bindValue(':file_url', $t['file_url'], SQLITE3_TEXT);
    $taskStmt->bindValue(':file_type', $t['file_type'], SQLITE3_TEXT);
    $taskStmt->bindValue(':completed_at', $t['completed_at'], SQLITE3_TEXT);
    $taskStmt->bindValue(':sort_order', $t['sort_order'], SQLITE3_INTEGER);
    $taskStmt->execute();
    $taskStmt->reset();
}

// 7. Assets
$assetStmt = $db->prepare('INSERT INTO assets (id, job_id, campaign_id, name, type, template_id, status, assigned_to, due_date, sort_order) VALUES (:id, :job_id, :campaign_id, :name, :type, :template_id, :status, :assigned_to, :due_date, :sort_order)');
foreach ($assets as $a) {
    $assetStmt->bindValue(':id', $a['id'], SQLITE3_TEXT);
    $assetStmt->bindValue(':job_id', $a['job_id'], SQLITE3_TEXT);
    $assetStmt->bindValue(':campaign_id', $a['campaign_id'], SQLITE3_TEXT);
    $assetStmt->bindValue(':name', $a['name'], SQLITE3_TEXT);
    $assetStmt->bindValue(':type', $a['type'], SQLITE3_TEXT);
    $assetStmt->bindValue(':template_id', $a['template_id'], SQLITE3_TEXT);
    $assetStmt->bindValue(':status', $a['status'], SQLITE3_TEXT);
    $assetStmt->bindValue(':assigned_to', $a['assigned_to'], SQLITE3_TEXT);
    $assetStmt->bindValue(':due_date', $a['due_date'], SQLITE3_TEXT);
    $assetStmt->bindValue(':sort_order', $a['sort_order'], SQLITE3_INTEGER);
    $assetStmt->execute();
    $assetStmt->reset();
}

// 8. Wiki Pages
$wikiStmt = $db->prepare('INSERT INTO wiki_pages (id, title, slug, content, template_id, parent_id, type, tags, created_by, sort_order, created_at, updated_at) VALUES (:id, :title, :slug, :content, :template_id, :parent_id, :type, :tags, :created_by, :sort_order, :created_at, :updated_at)');
$wikiLinkStmt = $db->prepare('INSERT INTO wiki_page_links (wiki_page_id, entity_type, entity_id) VALUES (:wiki_id, :type, :eid)');

foreach ($wikiPages as $w) {
    $wikiStmt->bindValue(':id', $w['id'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':title', $w['title'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':slug', $w['slug'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':content', $w['content'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':template_id', $w['template_id'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':parent_id', $w['parent_id'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':type', $w['type'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':tags', $w['tags'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':created_by', $w['created_by'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':sort_order', $w['sort_order'], SQLITE3_INTEGER);
    $wikiStmt->bindValue(':created_at', $w['created_at'], SQLITE3_TEXT);
    $wikiStmt->bindValue(':updated_at', $w['updated_at'], SQLITE3_TEXT);
    $wikiStmt->execute();
    $wikiStmt->reset();

    // Insert links
    foreach ($w['linkedJobs'] as $jid) {
        $wikiLinkStmt->bindValue(':wiki_id', $w['id'], SQLITE3_TEXT);
        $wikiLinkStmt->bindValue(':type', 'job', SQLITE3_TEXT);
        $wikiLinkStmt->bindValue(':eid', $jid, SQLITE3_TEXT);
        $wikiLinkStmt->execute();
        $wikiLinkStmt->reset();
    }
    foreach ($w['linkedProjects'] as $pid) {
        $wikiLinkStmt->bindValue(':wiki_id', $w['id'], SQLITE3_TEXT);
        $wikiLinkStmt->bindValue(':type', 'project', SQLITE3_TEXT);
        $wikiLinkStmt->bindValue(':eid', $pid, SQLITE3_TEXT);
        $wikiLinkStmt->execute();
        $wikiLinkStmt->reset();
    }
}

$db->exec('COMMIT');

// ============================================================================
// REPORT
// ============================================================================
$counts = [
    'brands' => $db->querySingle('SELECT COUNT(*) FROM brands'),
    'campaigns' => $db->querySingle('SELECT COUNT(*) FROM campaigns'),
    'users' => $db->querySingle('SELECT COUNT(*) FROM users'),
    'jobs' => $db->querySingle('SELECT COUNT(*) FROM jobs'),
    'job_assignments' => $db->querySingle('SELECT COUNT(*) FROM job_assignments'),
    'tasks' => $db->querySingle('SELECT COUNT(*) FROM tasks'),
    'assets' => $db->querySingle('SELECT COUNT(*) FROM assets'),
    'wiki_pages' => $db->querySingle('SELECT COUNT(*) FROM wiki_pages'),
    'wiki_page_links' => $db->querySingle('SELECT COUNT(*) FROM wiki_page_links'),
];

if (php_sapi_name() === 'cli') {
    echo "Database seeded successfully!\n";
    foreach ($counts as $table => $count) {
        echo "  {$table}: {$count}\n";
    }
} else {
    jsonResponse([
        'success' => true,
        'message' => 'Database seeded successfully',
        'counts' => $counts,
    ]);
}
