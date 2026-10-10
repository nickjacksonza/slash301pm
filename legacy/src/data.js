// ============================================================================
// SCHEMA VERSION & MIGRATIONS
// ============================================================================

const SCHEMA_VERSION = 1;

// Migration functions: each migrates from version N to N+1
const migrations = {
  // Example for future use:
  // 1: (data) => {
  //   // Migrate from v1 to v2
  //   data.newField = 'default';
  //   return data;
  // },
};

// Run all necessary migrations to bring data up to current version
const migrateData = data => {
  const dataVersion = data._schemaVersion || 0;
  if (dataVersion === SCHEMA_VERSION) {
    return data; // Already up to date
  }
  if (dataVersion > SCHEMA_VERSION) {
    console.warn(`Data schema version (${dataVersion}) is newer than app version (${SCHEMA_VERSION}). This may cause issues.`);
    return data;
  }
  let migratedData = {
    ...data
  };

  // Run each migration in sequence
  for (let v = dataVersion; v < SCHEMA_VERSION; v++) {
    if (migrations[v]) {
      console.log(`Migrating data from schema v${v} to v${v + 1}`);
      migratedData = migrations[v](migratedData);
    }
  }
  migratedData._schemaVersion = SCHEMA_VERSION;
  return migratedData;
};

// ============================================================================
// INITIAL/MOCK DATA
// ============================================================================

const createInitialData = () => ({
  _schemaVersion: SCHEMA_VERSION,

  // ========================================================================
  // PEOPLE: 13 Agency Team + 20 Client Contacts (2 per hotel brand)
  // ========================================================================
  people: [
  // --- Agency Team (13) ---
  { id: 'p1', name: 'Priya Nair', email: 'priya@slash301.com', role: 'COO', color: '#dc2626' },
  { id: 'p2', name: 'Dominic Walsh', email: 'dominic@slash301.com', role: 'ECD', color: '#8b5cf6' },
  { id: 'p3', name: 'Sarah Chen', email: 'sarah@slash301.com', role: 'PM', color: '#3b82f6' },
  { id: 'p4', name: 'Lena Müller', email: 'lena@slash301.com', role: 'PM', color: '#2563eb' },
  { id: 'p5', name: 'Morgan Davis', email: 'morgan@slash301.com', role: 'Traffic', color: '#0ea5e9' },
  { id: 'p6', name: 'Ryan Okafor', email: 'ryan@slash301.com', role: 'Traffic', color: '#0284c7' },
  { id: 'p7', name: 'Isabelle Fontaine', email: 'isabelle@slash301.com', role: 'CD', color: '#ec4899' },
  { id: 'p8', name: 'James Park', email: 'james@slash301.com', role: 'CD', color: '#db2777' },
  { id: 'p9', name: 'Alex Thompson', email: 'alex@slash301.com', role: 'Copywriter', color: '#f97316' },
  { id: 'p10', name: 'Chloe Mwangi', email: 'chloe@slash301.com', role: 'Copywriter', color: '#ea580c' },
  { id: 'p11', name: 'Kim Lee', email: 'kim@slash301.com', role: 'Designer', color: '#14b8a6' },
  { id: 'p12', name: 'Tariq Hassan', email: 'tariq@slash301.com', role: 'Designer', color: '#0d9488' },
  { id: 'p13', name: 'Jordan Blake', email: 'jordan@slash301.com', role: 'QA', color: '#6366f1' },

  // --- Client Contacts (20 = 2 per brand) ---
  // 1. The Meridian Collection
  { id: 'c1', name: 'Sophie Vandenberg', email: 'sophie@meridiancollection.com', role: 'Client', color: '#b45309', brand: 'The Meridian Collection' },
  { id: 'c2', name: 'Charles Meridian III', email: 'charles@meridiancollection.com', role: 'Client', color: '#92400e', brand: 'The Meridian Collection' },
  // 2. Harbour & Helm Hotels
  { id: 'c3', name: 'Luca Ferretti', email: 'luca@harbourhelm.com', role: 'Client', color: '#1d4ed8', brand: 'Harbour & Helm Hotels' },
  { id: 'c4', name: 'Nina Bjornstad', email: 'nina@harbourhelm.com', role: 'Client', color: '#1e40af', brand: 'Harbour & Helm Hotels' },
  // 3. The Ashford Grand
  { id: 'c5', name: 'Preethi Subramaniam', email: 'preethi@ashfordgrand.com', role: 'Client', color: '#7c3aed', brand: 'The Ashford Grand' },
  { id: 'c6', name: 'Oliver Ashford', email: 'oliver@ashfordgrand.com', role: 'Client', color: '#6d28d9', brand: 'The Ashford Grand' },
  // 4. Coastal & Co. Resorts
  { id: 'c7', name: 'Mia Johansson', email: 'mia@coastalco.com', role: 'Client', color: '#0891b2', brand: 'Coastal & Co. Resorts' },
  { id: 'c8', name: 'Rafael Costa', email: 'rafael@coastalco.com', role: 'Client', color: '#0e7490', brand: 'Coastal & Co. Resorts' },
  // 5. The Blackwood Boutique
  { id: 'c9', name: 'Yuki Tanaka', email: 'yuki@blackwoodboutique.com', role: 'Client', color: '#374151', brand: 'The Blackwood Boutique' },
  { id: 'c10', name: 'Harriet Blackwood', email: 'harriet@blackwoodboutique.com', role: 'Client', color: '#1f2937', brand: 'The Blackwood Boutique' },
  // 6. Solaris Hotels & Spa
  { id: 'c11', name: 'Diego Reyes', email: 'diego@solarishotels.com', role: 'Client', color: '#ca8a04', brand: 'Solaris Hotels & Spa' },
  { id: 'c12', name: 'Amara Osei', email: 'amara@solarishotels.com', role: 'Client', color: '#a16207', brand: 'Solaris Hotels & Spa' },
  // 7. The Northgate Group
  { id: 'c13', name: 'Fionnuala O\'Brien', email: 'fionnuala@northgategroup.com', role: 'Client', color: '#15803d', brand: 'The Northgate Group' },
  { id: 'c14', name: 'Callum Northgate', email: 'callum@northgategroup.com', role: 'Client', color: '#166534', brand: 'The Northgate Group' },
  // 8. Vela Luxury Resorts
  { id: 'c15', name: 'Ananya Krishnan', email: 'ananya@velaresorts.com', role: 'Client', color: '#9333ea', brand: 'Vela Luxury Resorts' },
  { id: 'c16', name: 'Marco Bianchi', email: 'marco@velaresorts.com', role: 'Client', color: '#7e22ce', brand: 'Vela Luxury Resorts' },
  // 9. The Copperleaf Collection
  { id: 'c17', name: 'Zara Adeyemi', email: 'zara@copperleaf.com', role: 'Client', color: '#c2410c', brand: 'The Copperleaf Collection' },
  { id: 'c18', name: 'Pieter de Vries', email: 'pieter@copperleaf.com', role: 'Client', color: '#9a3412', brand: 'The Copperleaf Collection' },
  // 10. Aurum City Hotels
  { id: 'c19', name: 'Ben Whitfield', email: 'ben@aurumcity.com', role: 'Client', color: '#854d0e', brand: 'Aurum City Hotels' },
  { id: 'c20', name: 'Valentina Aureli', email: 'valentina@aurumcity.com', role: 'Client', color: '#713f12', brand: 'Aurum City Hotels' }
  ],

  // ========================================================================
  // PROJECTS (Campaigns) -- one per hotel brand, various statuses
  // ========================================================================
  projects: [
  // 1. The Meridian Collection -- Grand Opening (Demo Script B)
  {
    id: 'proj1', name: 'Grand Opening London', client: 'The Meridian Collection',
    description: 'Launch campaign for the new London flagship property',
    status: 'In Progress', jobCount: 3, createdAt: '2026-02-01',
    clientColors: { primary: '#b45309', secondary: '#d4a574' }
  },
  // 2. Harbour & Helm Hotels
  {
    id: 'proj2', name: 'Summer Sailing Season', client: 'Harbour & Helm Hotels',
    description: 'Seasonal campaign for marina and harbour packages',
    status: 'In Progress', jobCount: 2, createdAt: '2026-01-20',
    clientColors: { primary: '#1d4ed8', secondary: '#93c5fd' }
  },
  // 3. The Ashford Grand
  {
    id: 'proj3', name: 'Heritage Collection', client: 'The Ashford Grand',
    description: 'Showcase the historic suites and dining experiences',
    status: 'In Review', jobCount: 2, createdAt: '2026-01-15',
    clientColors: { primary: '#7c3aed', secondary: '#c4b5fd' }
  },
  // 4. Coastal & Co. Resorts
  {
    id: 'proj4', name: 'Beach Club Launch', client: 'Coastal & Co. Resorts',
    description: 'New beach club opening in Algarve',
    status: 'In Progress', jobCount: 2, createdAt: '2026-01-25',
    clientColors: { primary: '#0891b2', secondary: '#a5f3fc' }
  },
  // 5. The Blackwood Boutique
  {
    id: 'proj5', name: 'Midnight Series', client: 'The Blackwood Boutique',
    description: 'Dark luxury campaign for the winter collection of experiences',
    status: 'Done', jobCount: 1, createdAt: '2026-01-10',
    clientColors: { primary: '#374151', secondary: '#9ca3af' }
  },
  // 6. Solaris Hotels & Spa
  {
    id: 'proj6', name: 'Wellness Retreat 2026', client: 'Solaris Hotels & Spa',
    description: 'Spring wellness retreat promotion across Mediterranean properties',
    status: 'In Progress', jobCount: 2, createdAt: '2026-02-05',
    clientColors: { primary: '#ca8a04', secondary: '#fde68a' }
  },
  // 7. The Northgate Group
  {
    id: 'proj7', name: 'Conference Season', client: 'The Northgate Group',
    description: 'Corporate conference and events packages for Q2',
    status: 'Inbox', jobCount: 1, createdAt: '2026-02-10',
    clientColors: { primary: '#15803d', secondary: '#86efac' }
  },
  // 8. Vela Luxury Resorts
  {
    id: 'proj8', name: 'Island Escape Collection', client: 'Vela Luxury Resorts',
    description: 'Premium island getaway campaign for Maldives and Seychelles properties',
    status: 'In Progress', jobCount: 2, createdAt: '2026-01-28',
    clientColors: { primary: '#9333ea', secondary: '#d8b4fe' }
  },
  // 9. The Copperleaf Collection
  {
    id: 'proj9', name: 'Autumn Harvest', client: 'The Copperleaf Collection',
    description: 'Seasonal food and wine experience campaign',
    status: 'On Hold', jobCount: 1, createdAt: '2026-01-05',
    clientColors: { primary: '#c2410c', secondary: '#fdba74' }
  },
  // 10. Aurum City Hotels
  {
    id: 'proj10', name: 'City Breaks 2026', client: 'Aurum City Hotels',
    description: 'Urban luxury short-stay campaign for European city properties',
    status: 'In Progress', jobCount: 2, createdAt: '2026-02-08',
    clientColors: { primary: '#854d0e', secondary: '#fcd34d' }
  }
  ],

  // ========================================================================
  // JOBS -- spanning 6+ brands, 5+ team members, various statuses
  // Jobs for Demo Script B (MERC-001) + realistic jobs across brands
  // ========================================================================
  jobs: [
  // === The Meridian Collection (proj1) -- Demo Script B ===
  {
    id: 'job1', jobNumber: 'MERC-001', name: 'Grand Opening Social Launch',
    description: 'Social launch package for The Meridian London flagship opening. Hero images, carousel, and story content for Instagram, Facebook, and LinkedIn.',
    projectId: 'proj1', status: 'Inbox',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c1', Producer: null },
    dueDate: '2026-03-01', order: 0, createdAt: '2026-02-15'
  },
  {
    id: 'job2', jobNumber: 'MERC-002', name: 'Grand Opening Email Blast',
    description: 'Pre-launch email campaign to loyalty members announcing London opening. Two emails: teaser + official invitation.',
    projectId: 'proj1', status: 'Inbox',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c1' },
    dueDate: '2026-03-05', order: 1, createdAt: '2026-02-15'
  },
  {
    id: 'job3', jobNumber: 'MERC-003', name: 'Grand Opening Landing Page',
    description: 'Dedicated landing page for the London property with booking integration and virtual tour embed.',
    projectId: 'proj1', status: 'Inbox',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c2' },
    dueDate: '2026-03-10', order: 2, createdAt: '2026-02-16'
  },

  // === Harbour & Helm Hotels (proj2) ===
  {
    id: 'job4', jobNumber: 'HARB-001', name: 'Marina Weekend Package',
    description: 'Social and display campaign for the weekend marina package. Targeting couples and families.',
    projectId: 'proj2', status: 'In Progress',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c3' },
    dueDate: '2026-02-28', order: 0, createdAt: '2026-01-22'
  },
  {
    id: 'job5', jobNumber: 'HARB-002', name: 'Sailing Regatta Promo',
    description: 'Event promotion for the annual Harbour & Helm sailing regatta. Print and digital.',
    projectId: 'proj2', status: 'Today',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c4' },
    dueDate: '2026-02-25', order: 1, createdAt: '2026-01-23'
  },

  // === The Ashford Grand (proj3) -- In Review ===
  {
    id: 'job6', jobNumber: 'ASHF-001', name: 'Heritage Suite Photography',
    description: 'Social content showcasing the renovated heritage suites. Lifestyle photography with copy overlays.',
    projectId: 'proj3', status: 'In Progress',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c5' },
    allTasksCompletedAt: '2026-02-10',
    dueDate: '2026-02-20', order: 0, createdAt: '2026-01-18'
  },
  {
    id: 'job7', jobNumber: 'ASHF-002', name: 'Fine Dining Menu Launch',
    description: 'New seasonal menu launch for The Ashford Grand restaurant. Email and social.',
    projectId: 'proj3', status: 'Approved (Internal)',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c6' },
    internalApprovedBy: 'p7', internalApprovedAt: '2026-02-12',
    allTasksCompletedAt: '2026-02-11',
    dueDate: '2026-02-22', order: 1, createdAt: '2026-01-19'
  },

  // === Coastal & Co. Resorts (proj4) ===
  {
    id: 'job8', jobNumber: 'COAS-001', name: 'Beach Club Teaser Video',
    description: 'Short teaser video for the new Algarve beach club. 15s and 30s cuts for social.',
    projectId: 'proj4', status: 'This Week',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p10', Designer: 'p11', QA: 'p13', Client: 'c7' },
    dueDate: '2026-02-27', order: 0, createdAt: '2026-01-28'
  },
  {
    id: 'job9', jobNumber: 'COAS-002', name: 'Beach Club Social Launch',
    description: 'Full social package for the beach club opening day. Carousel, stories, and Reels.',
    projectId: 'proj4', status: 'Inbox',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p9', Designer: 'p12', QA: 'p13', Client: 'c8' },
    dueDate: '2026-03-05', order: 1, createdAt: '2026-01-29'
  },

  // === The Blackwood Boutique (proj5) -- Done/Approved ===
  {
    id: 'job10', jobNumber: 'BLCK-001', name: 'Midnight Series Social',
    description: 'Dark luxury social campaign for The Blackwood Boutique winter experiences.',
    projectId: 'proj5', status: 'Approved (External)',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c9' },
    internalApprovedBy: 'p7', internalApprovedAt: '2026-01-28',
    clientApprovedBy: 'c9', clientApprovedAt: '2026-01-30',
    allTasksCompletedAt: '2026-01-25',
    dueDate: '2026-02-05', order: 0, createdAt: '2026-01-12'
  },

  // === Solaris Hotels & Spa (proj6) ===
  {
    id: 'job11', jobNumber: 'SOLA-001', name: 'Wellness Retreat Video',
    description: 'Hero video for the spring wellness retreat. Spa, yoga, and mindfulness content.',
    projectId: 'proj6', status: 'In Progress',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c11' },
    dueDate: '2026-03-01', order: 0, createdAt: '2026-02-06'
  },
  {
    id: 'job12', jobNumber: 'SOLA-002', name: 'Spa Day Pass Promo',
    description: 'Day pass promotion for local residents. Digital display and social.',
    projectId: 'proj6', status: 'Inbox',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c12' },
    dueDate: '2026-03-08', order: 1, createdAt: '2026-02-07'
  },

  // === The Northgate Group (proj7) -- Inbox ===
  {
    id: 'job13', jobNumber: 'NORT-001', name: 'Conference Packages Brochure',
    description: 'Digital and print brochure for Q2 corporate conference packages.',
    projectId: 'proj7', status: 'Inbox',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c13' },
    dueDate: '2026-03-15', order: 0, createdAt: '2026-02-12'
  },

  // === Vela Luxury Resorts (proj8) ===
  {
    id: 'job14', jobNumber: 'VELA-001', name: 'Maldives Villa Collection',
    description: 'Premium social and display campaign for the new overwater villas in Maldives.',
    projectId: 'proj8', status: 'In Progress',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c15' },
    dueDate: '2026-02-28', order: 0, createdAt: '2026-01-30'
  },
  {
    id: 'job15', jobNumber: 'VELA-002', name: 'Seychelles Honeymoon Package',
    description: 'Honeymoon package campaign targeting engaged couples. Email, social, and landing page.',
    projectId: 'proj8', status: 'Waiting',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c16' },
    dueDate: '2026-03-10', order: 1, createdAt: '2026-02-01'
  },

  // === The Copperleaf Collection (proj9) -- On Hold ===
  {
    id: 'job16', jobNumber: 'COPP-001', name: 'Autumn Harvest Social',
    description: 'Food and wine experience campaign for autumn. Photography-led social content.',
    projectId: 'proj9', status: 'On Hold',
    assignments: { PM: 'p3', Traffic: 'p5', ECD: 'p2', CD: 'p7', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c17' },
    dueDate: '2026-04-01', order: 0, createdAt: '2026-01-08'
  },

  // === Aurum City Hotels (proj10) ===
  {
    id: 'job17', jobNumber: 'AURU-001', name: 'Paris City Break',
    description: 'City break campaign for the Paris property. Weekend getaway focus.',
    projectId: 'proj10', status: 'In Progress',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p10', Designer: 'p12', QA: 'p13', Client: 'c19' },
    dueDate: '2026-03-01', order: 0, createdAt: '2026-02-10'
  },
  {
    id: 'job18', jobNumber: 'AURU-002', name: 'London Rooftop Bar Launch',
    description: 'New rooftop bar opening at the London property. Event promo and social.',
    projectId: 'proj10', status: 'Today',
    assignments: { PM: 'p4', Traffic: 'p6', ECD: 'p2', CD: 'p8', Copywriter: 'p9', Designer: 'p11', QA: 'p13', Client: 'c20' },
    dueDate: '2026-02-26', order: 1, createdAt: '2026-02-11'
  }
  ],

  // ========================================================================
  // ASSETS -- representative assets across jobs
  // ========================================================================
  assets: [
  // MERC-001 assets
  { id: 'a1', name: 'Instagram Hero Post', type: 'image', templateId: 'social-static', jobId: 'job1', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-02-26', order: 0 },
  { id: 'a2', name: 'Instagram Carousel (5 slides)', type: 'image', templateId: 'social-carousel', jobId: 'job1', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-02-27', order: 1 },
  { id: 'a3', name: 'Instagram Story (3 frames)', type: 'video', templateId: 'social-story', jobId: 'job1', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-02-28', order: 2 },
  { id: 'a4', name: 'Facebook Hero Post', type: 'image', templateId: 'social-static', jobId: 'job1', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-02-26', order: 3 },
  { id: 'a5', name: 'LinkedIn Announcement', type: 'image', templateId: 'social-static', jobId: 'job1', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-02-28', order: 4 },

  // MERC-002 assets
  { id: 'a6', name: 'Teaser Email', type: 'document', templateId: 'email-template', jobId: 'job2', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-03-02', order: 0 },
  { id: 'a7', name: 'Official Invitation Email', type: 'document', templateId: 'email-template', jobId: 'job2', projectId: 'proj1', status: 'Inbox', assignedTo: 'p11', dueDate: '2026-03-04', order: 1 },

  // HARB-001 assets
  { id: 'a8', name: 'Marina Instagram Post', type: 'image', templateId: 'social-static', jobId: 'job4', projectId: 'proj2', status: 'In Progress', assignedTo: 'p12', dueDate: '2026-02-25', order: 0 },
  { id: 'a9', name: 'Marina Display Banner', type: 'image', templateId: 'banner-display', jobId: 'job4', projectId: 'proj2', status: 'In Progress', assignedTo: 'p12', dueDate: '2026-02-26', order: 1 },

  // HARB-002 assets
  { id: 'a10', name: 'Regatta Poster', type: 'image', templateId: 'print-ooh', jobId: 'job5', projectId: 'proj2', status: 'Today', assignedTo: 'p11', dueDate: '2026-02-24', order: 0 },
  { id: 'a11', name: 'Regatta Social Reel', type: 'video', templateId: 'social-story', jobId: 'job5', projectId: 'proj2', status: 'Today', assignedTo: 'p11', dueDate: '2026-02-25', order: 1 },

  // ASHF-001 assets
  { id: 'a12', name: 'Heritage Suite Carousel', type: 'image', templateId: 'social-carousel', jobId: 'job6', projectId: 'proj3', status: 'Done', assignedTo: 'p11', dueDate: '2026-02-18', order: 0 },

  // ASHF-002 assets
  { id: 'a13', name: 'Menu Launch Email', type: 'document', templateId: 'email-template', jobId: 'job7', projectId: 'proj3', status: 'Done', assignedTo: 'p12', dueDate: '2026-02-20', order: 0 },
  { id: 'a14', name: 'Menu Social Post', type: 'image', templateId: 'social-static', jobId: 'job7', projectId: 'proj3', status: 'Done', assignedTo: 'p12', dueDate: '2026-02-21', order: 1 },

  // COAS-001 assets
  { id: 'a15', name: 'Beach Club Teaser 15s', type: 'video', templateId: 'video-edit-short', jobId: 'job8', projectId: 'proj4', status: 'This Week', assignedTo: 'p11', dueDate: '2026-02-26', order: 0 },
  { id: 'a16', name: 'Beach Club Teaser 30s', type: 'video', templateId: 'video-edit-short', jobId: 'job8', projectId: 'proj4', status: 'This Week', assignedTo: 'p11', dueDate: '2026-02-27', order: 1 },

  // BLCK-001 assets
  { id: 'a17', name: 'Midnight Series Instagram', type: 'image', templateId: 'social-static', jobId: 'job10', projectId: 'proj5', status: 'Done', assignedTo: 'p11', dueDate: '2026-01-30', order: 0 },
  { id: 'a18', name: 'Midnight Series Story', type: 'video', templateId: 'social-story', jobId: 'job10', projectId: 'proj5', status: 'Done', assignedTo: 'p11', dueDate: '2026-02-01', order: 1 },

  // SOLA-001 assets
  { id: 'a19', name: 'Wellness Hero Video 60s', type: 'video', templateId: 'video-edit-long', jobId: 'job11', projectId: 'proj6', status: 'In Progress', assignedTo: 'p12', dueDate: '2026-02-28', order: 0 },

  // VELA-001 assets
  { id: 'a20', name: 'Maldives Villa Instagram', type: 'image', templateId: 'social-static', jobId: 'job14', projectId: 'proj8', status: 'In Progress', assignedTo: 'p11', dueDate: '2026-02-26', order: 0 },
  { id: 'a21', name: 'Maldives Villa Story', type: 'video', templateId: 'social-story', jobId: 'job14', projectId: 'proj8', status: 'In Progress', assignedTo: 'p11', dueDate: '2026-02-27', order: 1 },

  // AURU-002 assets
  { id: 'a22', name: 'Rooftop Bar Event Flyer', type: 'image', templateId: 'print-ad', jobId: 'job18', projectId: 'proj10', status: 'Today', assignedTo: 'p11', dueDate: '2026-02-25', order: 0 },
  { id: 'a23', name: 'Rooftop Bar Social Reel', type: 'video', templateId: 'social-story', jobId: 'job18', projectId: 'proj10', status: 'Today', assignedTo: 'p11', dueDate: '2026-02-26', order: 1 }
  ],

  // ========================================================================
  // TASKS -- copy + media for each job
  // ========================================================================
  tasks: [
  // MERC-001 -- Grand Opening Social Launch (Inbox, not started)
  { id: 't1', templateId: 'copy', jobId: 'job1', status: 'Not Started', assignedTo: 'p9', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't2', templateId: 'media', jobId: 'job1', status: 'Not Started', assignedTo: 'p11', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // MERC-002 -- Grand Opening Email Blast (Inbox)
  { id: 't3', templateId: 'copy', jobId: 'job2', status: 'Not Started', assignedTo: 'p9', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't4', templateId: 'media', jobId: 'job2', status: 'Not Started', assignedTo: 'p11', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // MERC-003 -- Grand Opening Landing Page (Inbox)
  { id: 't5', templateId: 'copy', jobId: 'job3', status: 'Not Started', assignedTo: 'p10', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't6', templateId: 'media', jobId: 'job3', status: 'Not Started', assignedTo: 'p12', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // HARB-001 -- Marina Weekend Package (In Progress)
  { id: 't7', templateId: 'copy', jobId: 'job4', status: 'In Progress', assignedTo: 'p10', characterCount: 180, content: 'Escape to the harbour. Weekend marina packages from £299 with sunset dining and sailing excursions included.', fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't8', templateId: 'media', jobId: 'job4', status: 'In Progress', assignedTo: 'p12', characterCount: null, content: null, fileUrl: 'marina-hero-draft.jpg', fileType: 'image', completedAt: null, order: 1 },

  // HARB-002 -- Sailing Regatta Promo (Today)
  { id: 't9', templateId: 'copy', jobId: 'job5', status: 'In Progress', assignedTo: 'p9', characterCount: 210, content: 'Set sail for the annual Harbour & Helm Regatta. Three days of racing, live music, and gourmet seafood on the waterfront.', fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't10', templateId: 'media', jobId: 'job5', status: 'In Progress', assignedTo: 'p11', characterCount: null, content: null, fileUrl: 'regatta-poster-v1.jpg', fileType: 'image', completedAt: null, order: 1 },

  // ASHF-001 -- Heritage Suite Photography (In Progress, tasks done -> pending review)
  { id: 't11', templateId: 'copy', jobId: 'job6', status: 'Done', assignedTo: 'p9', characterCount: 320, content: 'Step into history. The Ashford Grand\'s Heritage Suites have been meticulously restored to their 1920s grandeur — original cornicing, hand-painted wallpapers, and antique furnishings paired with every modern comfort.', fileUrl: null, fileType: null, completedAt: '2026-02-09', order: 0 },
  { id: 't12', templateId: 'media', jobId: 'job6', status: 'Done', assignedTo: 'p11', characterCount: null, content: null, fileUrl: 'heritage-suite-carousel.zip', fileType: 'image', completedAt: '2026-02-10', order: 1 },

  // ASHF-002 -- Fine Dining Menu Launch (Approved Internal)
  { id: 't13', templateId: 'copy', jobId: 'job7', status: 'Done', assignedTo: 'p10', characterCount: 280, content: 'A new chapter at The Ashford Grand. Chef Laurent introduces the Spring Tasting Menu — six courses celebrating the best of British produce with a French sensibility.', fileUrl: null, fileType: null, completedAt: '2026-02-10', order: 0 },
  { id: 't14', templateId: 'media', jobId: 'job7', status: 'Done', assignedTo: 'p12', characterCount: null, content: null, fileUrl: 'menu-launch-email.html', fileType: 'document', completedAt: '2026-02-11', order: 1 },

  // COAS-001 -- Beach Club Teaser Video (This Week)
  { id: 't15', templateId: 'copy', jobId: 'job8', status: 'In Progress', assignedTo: 'p10', characterCount: 90, content: 'Something new is coming to the Algarve. Beach Club by Coastal & Co. — opening March 2026.', fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't16', templateId: 'media', jobId: 'job8', status: 'Not Started', assignedTo: 'p11', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // COAS-002 -- Beach Club Social Launch (Inbox)
  { id: 't17', templateId: 'copy', jobId: 'job9', status: 'Not Started', assignedTo: 'p9', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't18', templateId: 'media', jobId: 'job9', status: 'Not Started', assignedTo: 'p12', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // BLCK-001 -- Midnight Series Social (Approved External)
  { id: 't19', templateId: 'copy', jobId: 'job10', status: 'Done', assignedTo: 'p9', characterCount: 250, content: 'After dark, The Blackwood reveals itself. Candlelit tasting rooms, moonlit gardens, and suites dressed in midnight velvet. Winter at The Blackwood is not a season — it\'s an invitation.', fileUrl: null, fileType: null, completedAt: '2026-01-24', order: 0 },
  { id: 't20', templateId: 'media', jobId: 'job10', status: 'Done', assignedTo: 'p11', characterCount: null, content: null, fileUrl: 'midnight-series-final.zip', fileType: 'image', completedAt: '2026-01-25', order: 1 },

  // SOLA-001 -- Wellness Retreat Video (In Progress)
  { id: 't21', templateId: 'copy', jobId: 'job11', status: 'Done', assignedTo: 'p10', characterCount: 290, content: 'Breathe in. The Solaris Wellness Retreat is a week of transformation — morning yoga overlooking the Mediterranean, afternoon spa rituals, and evening meditation under the stars.', fileUrl: null, fileType: null, completedAt: '2026-02-14', order: 0 },
  { id: 't22', templateId: 'media', jobId: 'job11', status: 'In Progress', assignedTo: 'p12', characterCount: null, content: null, fileUrl: 'wellness-hero-v2.mp4', fileType: 'video', completedAt: null, order: 1 },

  // SOLA-002 -- Spa Day Pass Promo (Inbox)
  { id: 't23', templateId: 'copy', jobId: 'job12', status: 'Not Started', assignedTo: 'p9', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't24', templateId: 'media', jobId: 'job12', status: 'Not Started', assignedTo: 'p11', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // NORT-001 -- Conference Packages Brochure (Inbox)
  { id: 't25', templateId: 'copy', jobId: 'job13', status: 'Not Started', assignedTo: 'p10', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't26', templateId: 'media', jobId: 'job13', status: 'Not Started', assignedTo: 'p12', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // VELA-001 -- Maldives Villa Collection (In Progress)
  { id: 't27', templateId: 'copy', jobId: 'job14', status: 'In Progress', assignedTo: 'p9', characterCount: 340, content: 'Suspended between sky and sea. The new Vela Overwater Villas in the Maldives offer private infinity pools, glass-floor living rooms, and sunrise butler service. This is not a hotel — it\'s a world of its own.', fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't28', templateId: 'media', jobId: 'job14', status: 'In Progress', assignedTo: 'p11', characterCount: null, content: null, fileUrl: 'maldives-villa-draft.jpg', fileType: 'image', completedAt: null, order: 1 },

  // VELA-002 -- Seychelles Honeymoon Package (Waiting)
  { id: 't29', templateId: 'copy', jobId: 'job15', status: 'Waiting', assignedTo: 'p10', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't30', templateId: 'media', jobId: 'job15', status: 'Waiting', assignedTo: 'p12', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // COPP-001 -- Autumn Harvest Social (On Hold)
  { id: 't31', templateId: 'copy', jobId: 'job16', status: 'On Hold', assignedTo: 'p9', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't32', templateId: 'media', jobId: 'job16', status: 'On Hold', assignedTo: 'p11', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // AURU-001 -- Paris City Break (In Progress)
  { id: 't33', templateId: 'copy', jobId: 'job17', status: 'In Progress', assignedTo: 'p10', characterCount: 200, content: 'Paris for the weekend. The Aurum Rive Gauche puts you steps from Saint-Germain with rooms designed for the modern flaneur.', fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't34', templateId: 'media', jobId: 'job17', status: 'Not Started', assignedTo: 'p12', characterCount: null, content: null, fileUrl: null, fileType: null, completedAt: null, order: 1 },

  // AURU-002 -- London Rooftop Bar Launch (Today)
  { id: 't35', templateId: 'copy', jobId: 'job18', status: 'In Progress', assignedTo: 'p9', characterCount: 160, content: 'Elevate your evening. The Aurum Skyline Bar opens February 28 — craft cocktails, city views, and a DJ residency every Friday.', fileUrl: null, fileType: null, completedAt: null, order: 0 },
  { id: 't36', templateId: 'media', jobId: 'job18', status: 'In Progress', assignedTo: 'p11', characterCount: null, content: null, fileUrl: 'rooftop-bar-v1.jpg', fileType: 'image', completedAt: null, order: 1 }
  ],

  // ========================================================================
  // WIKI PAGES -- Client Bibles for top brands + campaign log
  // ========================================================================
  wikiPages: [
  {
    id: 'wiki1', title: 'The Meridian Collection', slug: 'meridian-collection',
    content: '<h1>The Meridian Collection — Client Bible</h1><h2>Brand Overview</h2><p>The Meridian Collection is a portfolio of luxury hotels across Europe, known for timeless elegance and impeccable service. Established in 1952 by the Meridian family, now spanning 12 properties.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Sophie Vandenberg (sophie@meridiancollection.com)</li><li><strong>Brand Director:</strong> Charles Meridian III (charles@meridiancollection.com)</li></ul><h2>Brand Colours</h2><p><strong>Primary:</strong> #B45309 (Meridian Gold)</p><p><strong>Secondary:</strong> #1C1917 (Charcoal Black)</p><h2>Tone of Voice</h2><p>Refined, warm, understated luxury. Never flashy. Words like "curated", "heritage", "considered".</p>',
    templateId: 'client-bible', parentId: null, type: 'client',
    linkedJobs: ['job1', 'job2', 'job3'], linkedProjects: ['proj1'],
    tags: ['client', 'hotel', 'luxury'], createdAt: '2026-01-05', updatedAt: '2026-02-15', createdBy: 'p3', order: 0
  },
  {
    id: 'wiki2', title: 'Grand Opening London Campaign', slug: 'grand-opening-london',
    content: '<h1>Grand Opening London — Campaign Brief</h1><h2>Campaign Overview</h2><p><strong>Launch Date:</strong> March 1, 2026</p><p><strong>Objective:</strong> Drive awareness and bookings for the new London flagship property</p><h2>Target Audience</h2><p>Affluent travellers 35-65, UK and European markets, luxury lifestyle interest</p><h2>Creative Approach</h2><p>Elegant photography-led content. Show the property interiors, neighbourhood (Mayfair), and the Meridian service philosophy. Lead with "A new address for timeless luxury."</p>',
    templateId: 'campaign-log', parentId: 'wiki1', type: 'campaign',
    linkedJobs: ['job1', 'job2', 'job3'], linkedProjects: ['proj1'],
    tags: ['campaign', 'launch', 'london', '2026'], createdAt: '2026-02-01', updatedAt: '2026-02-15', createdBy: 'p3', order: 0
  },
  {
    id: 'wiki3', title: 'Harbour & Helm Hotels', slug: 'harbour-helm',
    content: '<h1>Harbour & Helm Hotels — Client Bible</h1><h2>Brand Overview</h2><p>Harbour & Helm is a coastal hotel group focused on maritime heritage and seaside luxury. Five properties across the UK and Nordics.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Luca Ferretti (luca@harbourhelm.com)</li><li><strong>Brand Director:</strong> Nina Bjornstad (nina@harbourhelm.com)</li></ul><h2>Tone of Voice</h2><p>Fresh, adventurous, nautical without being kitsch. "Rugged luxury" — salt spray and starched linen.</p>',
    templateId: 'client-bible', parentId: null, type: 'client',
    linkedJobs: ['job4', 'job5'], linkedProjects: ['proj2'],
    tags: ['client', 'hotel', 'coastal'], createdAt: '2026-01-10', updatedAt: '2026-01-22', createdBy: 'p4', order: 1
  },
  {
    id: 'wiki4', title: 'The Blackwood Boutique', slug: 'blackwood-boutique',
    content: '<h1>The Blackwood Boutique — Client Bible</h1><h2>Brand Overview</h2><p>The Blackwood is a single-property boutique hotel in the Cotswolds, known for its dark, atmospheric aesthetic and exclusive experiences. Only 24 rooms.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Yuki Tanaka (yuki@blackwoodboutique.com)</li><li><strong>Brand Director:</strong> Harriet Blackwood (harriet@blackwoodboutique.com)</li></ul><h2>Tone of Voice</h2><p>Moody, intimate, literary. Short sentences. Rich adjectives. Think: "a whisper, not a shout."</p>',
    templateId: 'client-bible', parentId: null, type: 'client',
    linkedJobs: ['job10'], linkedProjects: ['proj5'],
    tags: ['client', 'boutique', 'luxury'], createdAt: '2026-01-08', updatedAt: '2026-01-28', createdBy: 'p3', order: 2
  },
  {
    id: 'wiki5', title: 'Vela Luxury Resorts', slug: 'vela-luxury',
    content: '<h1>Vela Luxury Resorts — Client Bible</h1><h2>Brand Overview</h2><p>Vela is an ultra-premium island resort brand with properties in the Maldives, Seychelles, and Bali. Overwater villas, private beaches, and butler service as standard.</p><h2>Key Contacts</h2><ul><li><strong>Marketing Manager:</strong> Ananya Krishnan (ananya@velaresorts.com)</li><li><strong>Brand Director:</strong> Marco Bianchi (marco@velaresorts.com)</li></ul><h2>Tone of Voice</h2><p>Aspirational but never pretentious. Sensory language — sky, sea, warmth, silence. "Where the world falls away."</p>',
    templateId: 'client-bible', parentId: null, type: 'client',
    linkedJobs: ['job14', 'job15'], linkedProjects: ['proj8'],
    tags: ['client', 'resort', 'luxury', 'island'], createdAt: '2026-01-12', updatedAt: '2026-02-01', createdBy: 'p3', order: 3
  }
  ],

  scheduledEmails: []
});

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

const STORAGE_KEY = 'slash301pm_data';
const loadFromStorage = () => {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      let data = JSON.parse(saved);
      // Check and run migrations if needed
      const migratedData = migrateData(data);
      // Save migrated data if version changed
      if (migratedData._schemaVersion !== data._schemaVersion) {
        saveToStorage(migratedData);
      }
      return migratedData;
    }
  } catch (e) {
    console.error('Failed to load from storage:', e);
  }
  return createInitialData();
};
const saveToStorage = data => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    return {
      success: true
    };
  } catch (e) {
    console.error('Failed to save to storage:', e);

    // Handle QuotaExceededError
    if (e.name === 'QuotaExceededError' || e.code === 22 ||
    // Legacy Chrome
    e.code === 1014 ||
    // Legacy Firefox
    e.name === 'NS_ERROR_DOM_QUOTA_REACHED') {
      return {
        success: false,
        error: 'quota_exceeded',
        message: 'Storage quota exceeded. Your data may not be saved.'
      };
    }
    return {
      success: false,
      error: 'unknown',
      message: 'Failed to save data: ' + e.message
    };
  }
};

// Track last storage error for UI notification
let lastStorageError = null;
const getLastStorageError = () => lastStorageError;
const clearLastStorageError = () => {
  lastStorageError = null;
};
const dataReducer = (state, action) => {
  let newState;
  switch (action.type) {
    case 'SET_DATA':
      newState = action.payload;
      break;
    case 'ADD_PROJECT':
      newState = {
        ...state,
        projects: [...state.projects, action.payload]
      };
      break;
    case 'UPDATE_PROJECT':
      newState = {
        ...state,
        projects: state.projects.map(p => p.id === action.payload.id ? action.payload : p)
      };
      break;
    case 'DELETE_PROJECT': {
      const projectJobIds = state.jobs.filter(j => j.projectId === action.payload).map(j => j.id);
      newState = {
        ...state,
        projects: state.projects.filter(p => p.id !== action.payload),
        jobs: state.jobs.filter(j => j.projectId !== action.payload),
        assets: state.assets.filter(a => !projectJobIds.includes(a.jobId)),
        tasks: (state.tasks || []).filter(t => !projectJobIds.includes(t.jobId))
      };
      break;
    }
    case 'ADD_JOB':
      newState = {
        ...state,
        jobs: [...state.jobs, action.payload]
      };
      break;
    case 'UPDATE_JOB':
      newState = {
        ...state,
        jobs: state.jobs.map(j => j.id === action.payload.id ? action.payload : j)
      };
      // BUG-04b fix: Preserve auto-transition when panel Save overwrites job.
      // If all tasks for this job are Done, ensure status reflects review state.
      // Do NOT override Approved (Internal) or Approved (External) statuses
      // since those are set by the review workflow.
      {
        const jobId = action.payload.id;
        const jobTasks = newState.tasks.filter(t => t.jobId === jobId);
        const allDone = jobTasks.length > 0 && jobTasks.every(t => t.status === 'Done');
        if (allDone) {
          const activeStatuses = ['Today', 'This Week', 'Inbox'];
          const currentJob = newState.jobs.find(j => j.id === jobId);
          if (currentJob && activeStatuses.includes(currentJob.status)) {
            newState = {
              ...newState,
              jobs: newState.jobs.map(j => j.id === jobId ? { ...j, status: 'In Progress', allTasksCompletedAt: j.allTasksCompletedAt || new Date().toISOString() } : j)
            };
          }
        }
      }
      break;
    case 'DELETE_JOB':
      newState = {
        ...state,
        jobs: state.jobs.filter(j => j.id !== action.payload),
        assets: state.assets.filter(a => a.jobId !== action.payload),
        tasks: (state.tasks || []).filter(t => t.jobId !== action.payload)
      };
      break;
    case 'REORDER_JOBS':
      newState = {
        ...state,
        jobs: action.payload
      };
      break;
    case 'ADD_ASSET':
      newState = {
        ...state,
        assets: [...state.assets, action.payload]
      };
      break;
    case 'UPDATE_ASSET':
      newState = {
        ...state,
        assets: state.assets.map(a => a.id === action.payload.id ? action.payload : a)
      };
      break;
    case 'DELETE_ASSET':
      newState = {
        ...state,
        assets: state.assets.filter(a => a.id !== action.payload)
      };
      break;
    case 'REORDER_ASSETS':
      newState = {
        ...state,
        assets: action.payload
      };
      break;
    case 'ADD_PERSON':
      newState = {
        ...state,
        people: [...state.people, action.payload]
      };
      break;
    case 'UPDATE_PERSON':
      newState = {
        ...state,
        people: state.people.map(p => p.id === action.payload.id ? action.payload : p)
      };
      break;
    case 'DELETE_PERSON': {
      const personId = action.payload;
      newState = {
        ...state,
        people: state.people.filter(p => p.id !== personId),
        jobs: state.jobs.map(j => {
          if (!j.assignments) return j;
          const cleaned = { ...j.assignments };
          Object.keys(cleaned).forEach(role => {
            if (cleaned[role] === personId) delete cleaned[role];
          });
          return { ...j, assignments: cleaned };
        }),
        assets: state.assets.map(a =>
          a.assignedTo === personId ? { ...a, assignedTo: null } : a
        )
      };
      break;
    }
    // Wiki actions
    case 'ADD_WIKI_PAGE':
      newState = {
        ...state,
        wikiPages: [...(state.wikiPages || []), action.payload]
      };
      break;
    case 'UPDATE_WIKI_PAGE':
      newState = {
        ...state,
        wikiPages: (state.wikiPages || []).map(p => p.id === action.payload.id ? action.payload : p)
      };
      break;
    case 'DELETE_WIKI_PAGE':
      newState = {
        ...state,
        wikiPages: (state.wikiPages || []).filter(p => p.id !== action.payload)
      };
      break;
    // Scheduled email actions
    case 'ADD_SCHEDULED_EMAIL':
      newState = {
        ...state,
        scheduledEmails: [...(state.scheduledEmails || []), action.payload]
      };
      break;
    case 'DELETE_SCHEDULED_EMAIL':
      newState = {
        ...state,
        scheduledEmails: (state.scheduledEmails || []).filter(e => e.id !== action.payload)
      };
      break;
    // Task actions
    case 'ADD_TASK':
      newState = {
        ...state,
        tasks: [...(state.tasks || []), action.payload]
      };
      break;
    case 'UPDATE_TASK':
      newState = {
        ...state,
        tasks: (state.tasks || []).map(t => t.id === action.payload.id ? action.payload : t)
      };
      // Review routing logic: route tasks through Internal Review → Client Review
      // based on WHO edited the task and whether all tasks are Done.
      if (action.payload.jobId) {
        const jobId = action.payload.jobId;
        const jobTasks = newState.tasks.filter(t => t.jobId === jobId);
        const allDone = jobTasks.length > 0 && jobTasks.every(t => t.status === 'Done');
        const job = newState.jobs.find(j => j.id === jobId);
        const editedByRole = action.payload.editedByRole;

        if (allDone && job) {
          // Determine routing based on who made the edit
          const cdRoles = ['CD', 'ECD'];
          const creativeRoles = ['Copywriter', 'Designer'];
          const activeStatuses = ['In Progress', 'Today', 'This Week', 'Inbox'];

          if (cdRoles.includes(editedByRole)) {
            // CD/ECD edited a task and all tasks are Done:
            // Auto-approve internally → route to Client Review
            newState = {
              ...newState,
              jobs: newState.jobs.map(j => j.id === jobId ? {
                ...j,
                status: 'Approved (Internal)',
                internalApprovedBy: action.payload.editedBy,
                internalApprovedAt: new Date().toISOString(),
                allTasksCompletedAt: j.allTasksCompletedAt || new Date().toISOString()
              } : j)
            };
          } else if (creativeRoles.includes(editedByRole)) {
            // Copywriter/Designer edited a task and all tasks are Done:
            // Clear internal approval → route to Internal Review (CD)
            newState = {
              ...newState,
              jobs: newState.jobs.map(j => j.id === jobId ? {
                ...j,
                status: 'In Progress',
                internalApprovedBy: null,
                internalApprovedAt: null,
                allTasksCompletedAt: new Date().toISOString()
              } : j)
            };
          } else if (activeStatuses.includes(job.status)) {
            // Other roles (Traffic, PM, etc.) editing tasks:
            // Keep In Progress, clear internal approval so it goes to review
            newState = {
              ...newState,
              jobs: newState.jobs.map(j => j.id === jobId ? {
                ...j,
                status: 'In Progress',
                internalApprovedBy: null,
                internalApprovedAt: null,
                allTasksCompletedAt: new Date().toISOString()
              } : j)
            };
          }
        } else if (!allDone && job) {
          // If tasks are no longer all Done (e.g. unchecked), ensure job stays In Progress
          // and clear approvals so it needs to go through review again
          const approvedStatuses = ['Approved (Internal)', 'Approved (External)'];
          if (approvedStatuses.includes(job.status)) {
            newState = {
              ...newState,
              jobs: newState.jobs.map(j => j.id === jobId ? {
                ...j,
                status: 'In Progress',
                internalApprovedBy: null,
                internalApprovedAt: null
              } : j)
            };
          }
        }
      }
      break;
    case 'DELETE_TASK':
      newState = {
        ...state,
        tasks: (state.tasks || []).filter(t => t.id !== action.payload)
      };
      break;
    default:
      return state;
  }
  // Dual-write: save to localStorage (immediate fallback) + API (persistent)
  const saveResult = saveToStorage(newState);
  if (!saveResult.success) {
    lastStorageError = saveResult;
  }
  // Fire-and-forget API sync for write actions
  if (typeof api !== 'undefined' && action.type !== 'SET_DATA') {
    api.syncAction(action.type, action.payload);
  }
  return newState;
};
