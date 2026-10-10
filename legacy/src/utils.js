// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

const generateId = () => Math.random().toString(36).substr(2, 9);

// Shared data-lookup helpers -- call makeDataHelpers(data) inside a component
// to get closures that mirror the old per-component definitions.
const makeDataHelpers = data => ({
  getJobTasks: jobId => (data.tasks || []).filter(t => t.jobId === jobId),
  getProject: projectId => data.projects.find(p => p.id === projectId),
  getPerson: personId => data.people.find(p => p.id === personId)
});

// ============================================================================
// ASSET NAMING CONVENTION
// ============================================================================
// Pattern: {JobNumber}-{Client}-{CampaignShortName}-{AssetType}{Sequence}-{Version}-{YYYYMMDD}_{Size}
// Example: SUMM-001-Acme-Summer-Hero1-v1-20260121_1x1

const ASSET_TYPES = ['Hero1', 'Hero2', 'Tactical1', 'Tactical2', 'Tactical3', 'Tactical4', 'Organic1', 'Organic2', 'Competition', 'Comp-Winners', 'Wrapup'];
const ASSET_SIZES = ['1x1', '4x5', '16x9', '9x16'];

// Generate a compliant asset name
const generateAssetName = ({
  jobNumber,
  client,
  campaignName,
  assetType,
  sequence = 1,
  version = 1,
  size
}) => {
  const clientShort = (client || 'Client').replace(/[^a-zA-Z0-9]/g, '').slice(0, 10);
  const campaignShort = (campaignName || 'Campaign').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join('').replace(/[^a-zA-Z0-9]/g, '').slice(0, 15);
  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const typeWithSeq = assetType || `Asset${sequence}`;
  const versionStr = `v${version}`;
  const sizeStr = size ? `_${size}` : '';
  return `${jobNumber}-${clientShort}-${campaignShort}-${typeWithSeq}-${versionStr}-${dateStr}${sizeStr}`;
};

// Validate if a name follows the convention
const validateAssetName = name => {
  // Pattern: XXXX-NNN-Client-Campaign-Type-vN-YYYYMMDD(_Size)?
  const pattern = /^[A-Z]{2,4}-\d{3}-[A-Za-z0-9]+-[A-Za-z0-9]+-[A-Za-z0-9]+-v\d+-\d{8}(_\d+x\d+)?$/;
  return pattern.test(name);
};

// Parse an existing asset name into components
const parseAssetName = name => {
  const parts = name.split('-');
  if (parts.length < 7) return null;
  const sizePart = parts[parts.length - 1];
  const hasSize = sizePart.includes('_');
  const dateAndSize = hasSize ? sizePart.split('_') : [sizePart, null];
  return {
    jobNumber: `${parts[0]}-${parts[1]}`,
    client: parts[2],
    campaign: parts[3],
    assetType: parts[4],
    version: parts[5],
    date: dateAndSize[0],
    size: dateAndSize[1]
  };
};
const generateJobNumber = (projectCode, jobCount) => {
  return `${projectCode}-${String(jobCount).padStart(3, '0')}`;
};
const getProjectCode = projectName => {
  return projectName.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 4);
};
const formatDate = date => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  });
};

// Capacity calculation utilities
const DAILY_CAPACITY = 7; // hours
const WEEKLY_CAPACITY = 35; // hours

const calculateJobHours = (job, assets) => {
  const jobAssets = assets.filter(a => a.jobId === job.id);
  return Math.max(jobAssets.length * 0.25, 0.25);
};
const getPersonJobs = (personId, jobs) => {
  return jobs.filter(job => Object.values(job.assignments || {}).includes(personId));
};
const categorizeJobsByCapacity = (jobs, assets) => {
  const today = [];
  const thisWeek = [];
  const overflow = [];
  let todayHours = 0;
  let weekHours = 0;

  // Sort by status priority: Today first, then This Week, then others
  const statusPriority = {
    'Today': 1,
    'In Progress': 2,
    'This Week': 3
  };
  const sortedJobs = [...jobs].sort((a, b) => {
    const aPriority = statusPriority[a.status] || 99;
    const bPriority = statusPriority[b.status] || 99;
    return aPriority - bPriority;
  });
  for (const job of sortedJobs) {
    const hours = calculateJobHours(job, assets);
    const isToday = job.status === 'Today' || job.status === 'In Progress';
    const isThisWeek = job.status === 'This Week';
    if (isToday) {
      if (todayHours + hours <= DAILY_CAPACITY) {
        today.push({
          ...job,
          hours
        });
        todayHours += hours;
        weekHours += hours;
      } else {
        overflow.push({
          ...job,
          hours
        });
      }
    } else if (isThisWeek) {
      if (weekHours + hours <= WEEKLY_CAPACITY) {
        thisWeek.push({
          ...job,
          hours
        });
        weekHours += hours;
      } else {
        overflow.push({
          ...job,
          hours
        });
      }
    }
  }
  return {
    today,
    thisWeek,
    overflow,
    todayHours,
    weekHours: todayHours + thisWeek.reduce((sum, j) => sum + j.hours, 0),
    overflowHours: overflow.reduce((sum, j) => sum + j.hours, 0)
  };
};
const getNextWeekdayAt9am = () => {
  const now = new Date();
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setHours(9, 0, 0, 0);
  const day = tomorrow.getDay();
  if (day === 0) tomorrow.setDate(tomorrow.getDate() + 1); // Sunday -> Monday
  if (day === 6) tomorrow.setDate(tomorrow.getDate() + 2); // Saturday -> Monday

  return tomorrow;
};
const generateCapacityEmailContent = (person, categorizedJobs, data) => {
  const {
    today,
    thisWeek,
    todayHours,
    weekHours
  } = categorizedJobs;
  const todayDate = new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric'
  });
  const getJobDetails = job => {
    const project = data.projects.find(p => p.id === job.projectId);
    return `• ${job.jobNumber} - ${job.name}
  Client: ${project?.client || 'N/A'} | Campaign: ${project?.name || 'N/A'}
  Hours: ${job.hours}h | Due: ${formatDate(job.dueDate)}`;
  };
  let content = `Hi ${person.name.split(' ')[0]},

Here's your workload summary:

TODAY - ${todayHours.toFixed(1)} hours
─────────────────────
${today.length > 0 ? today.map(getJobDetails).join('\n\n') : 'No jobs scheduled'}

THIS WEEK - ${(weekHours - todayHours).toFixed(1)} hours
─────────────────────
${thisWeek.length > 0 ? thisWeek.map(getJobDetails).join('\n\n') : 'No additional jobs this week'}

Total: ${weekHours.toFixed(1)} hours scheduled

---
Generated by Slash 301 PM`;
  return {
    subject: `Your Workload for ${todayDate}`,
    body: content
  };
};
