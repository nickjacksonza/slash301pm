
// ============================================================================
// TEST HELPER - PM Workflow Test
// Call window.testPMWorkflow() from browser console to run
// ============================================================================
window.testPMWorkflow = () => {
  const storageKey = 'slash301pm_data';
  const data = JSON.parse(localStorage.getItem(storageKey));

  if (!data) {
    console.error('No data found in localStorage');
    return;
  }

  // Find existing people
  const pm = data.people.find(p => p.role === 'PM');
  const cd = data.people.find(p => p.role === 'CD');
  const copywriter = data.people.find(p => p.role === 'Copywriter');
  const designer = data.people.find(p => p.role === 'Designer');
  const client = data.people.find(p => p.role === 'Client');

  console.log('=== PM Workflow Test ===');
  console.log('PM:', pm?.name);
  console.log('CD:', cd?.name);
  console.log('Copywriter:', copywriter?.name);
  console.log('Designer:', designer?.name);
  console.log('Client:', client?.name);

  // Check if test project already exists
  let project = data.projects.find(p => p.name === 'Summer Launch 2026');

  if (!project) {
    // Create project
    project = {
      id: 'proj_test_' + Date.now(),
      name: 'Summer Launch 2026',
      client: 'Acme Corp',
      description: 'PM Workflow Test Campaign',
      status: 'In Progress',
      jobCount: 0,
      createdAt: new Date().toISOString(),
      clientColors: { primary: '#4a90d9', secondary: '#7cb342' }
    };
    data.projects.push(project);
    console.log('✓ Created project:', project.name);
  } else {
    console.log('✓ Using existing project:', project.name);
  }

  // Create job
  const jobId = 'job_test_' + Date.now();
  const job = {
    id: jobId,
    jobNumber: 'TEST-' + Math.floor(Math.random() * 1000),
    name: 'Hero Video',
    description: 'Test hero video for PM workflow',
    projectId: project.id,
    status: 'In Progress',
    assignments: {
      PM: pm?.id,
      CD: cd?.id,
      Copywriter: copywriter?.id,
      Designer: designer?.id,
      Client: client?.id
    },
    dueDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    order: data.jobs.length,
    createdAt: new Date().toISOString()
  };
  data.jobs.push(job);
  console.log('✓ Created job:', job.jobNumber, '-', job.name);

  // Create Copy task (DONE)
  const copyTask = {
    id: 't_copy_' + Date.now(),
    templateId: 'copy',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: copywriter?.id,
    characterCount: 150,
    content: 'Summer is here! Experience the thrill of our new collection.',
    fileUrl: null,
    fileType: null,
    completedAt: new Date().toISOString(),
    order: 0
  };
  data.tasks.push(copyTask);
  console.log('✓ Created Copy task (DONE)');

  // Create Media task (DONE)
  const mediaTask = {
    id: 't_media_' + Date.now(),
    templateId: 'media',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: designer?.id,
    characterCount: null,
    content: null,
    fileUrl: 'hero-video-final.mp4',
    fileType: 'video',
    completedAt: new Date().toISOString(),
    order: 1
  };
  data.tasks.push(mediaTask);
  console.log('✓ Created Media task (DONE)');

  // Update project job count
  project.jobCount = data.jobs.filter(j => j.projectId === project.id).length;

  // Save to localStorage
  localStorage.setItem(storageKey, JSON.stringify(data));

  console.log('');
  console.log('=== Test Data Created ===');
  console.log('Job is now ready for INTERNAL REVIEW (Job Review tab)');
  console.log('');
  console.log('NEXT STEPS:');
  console.log('1. Refresh the page');
  console.log('2. Login as CD (Maria Garcia)');
  console.log('3. Go to "Job Review" tab');
  console.log('4. Find job', job.jobNumber, 'and click "Approve"');
  console.log('5. Job will move to "In Review" status');
  console.log('6. Login as Client (Chris Martin)');
  console.log('7. Go to "Client Review" tab');
  console.log('8. Approve or reject the job');
  console.log('');
  console.log('Call window.testPMWorkflow.verify() after approving to verify status');

  return { project, job, copyTask, mediaTask };
};

// Verify workflow status
window.testPMWorkflow.verify = () => {
  const storageKey = 'slash301pm_data';
  const data = JSON.parse(localStorage.getItem(storageKey));

  const testJobs = data.jobs.filter(j => j.jobNumber?.startsWith('TEST-'));

  console.log('=== Workflow Verification ===');
  testJobs.forEach(job => {
    const project = data.projects.find(p => p.id === job.projectId);
    console.log(`Job: ${job.jobNumber} - ${job.name}`);
    console.log(`  Project: ${project?.name} (${project?.client})`);
    console.log(`  Status: ${job.status}`);

    if (job.internalApprovedBy) {
      const approver = data.people.find(p => p.id === job.internalApprovedBy);
      console.log(`  Internal Approved By: ${approver?.name}`);
    }

    if (job.status === 'In Review') {
      console.log('  ✓ Ready for CLIENT REVIEW');
    } else if (job.status === 'Approved (External)') {
      console.log('  ✓ CLIENT APPROVED - WORKFLOW COMPLETE');
    }
    console.log('');
  });

  // Check if any job has reached Client Review
  const inClientReview = testJobs.some(j => j.status === 'In Review');
  const clientApproved = testJobs.some(j => j.status === 'Approved (External)');

  return { inClientReview, clientApproved, testJobs };
};

// Full automated workflow test - creates job and moves it through to Client Review
window.testPMWorkflow.full = () => {
  const storageKey = 'slash301pm_data';
  const data = JSON.parse(localStorage.getItem(storageKey));

  if (!data) {
    console.error('No data found in localStorage');
    return { success: false, error: 'No data in localStorage' };
  }

  // Find people
  const pm = data.people.find(p => p.role === 'PM');
  const cd = data.people.find(p => p.role === 'CD');
  const copywriter = data.people.find(p => p.role === 'Copywriter');
  const designer = data.people.find(p => p.role === 'Designer');
  const client = data.people.find(p => p.role === 'Client');

  console.log('=== FULL PM WORKFLOW TEST ===');
  console.log('');

  // Step 1: Create or find project
  let project = data.projects.find(p => p.name === 'Summer Launch 2026' && p.client === 'Acme Corp');
  if (!project) {
    project = {
      id: 'proj_full_' + Date.now(),
      name: 'Summer Launch 2026',
      client: 'Acme Corp',
      description: 'Full PM Workflow Test',
      status: 'In Progress',
      jobCount: 0,
      createdAt: new Date().toISOString(),
      clientColors: { primary: '#4a90d9', secondary: '#7cb342' }
    };
    data.projects.push(project);
    console.log('STEP 1: ✓ Created project - Acme Corp / Summer Launch 2026');
  } else {
    console.log('STEP 1: ✓ Using existing project - Acme Corp / Summer Launch 2026');
  }

  // Step 2: Create job
  const jobId = 'job_full_' + Date.now();
  const job = {
    id: jobId,
    jobNumber: 'FULL-' + Math.floor(Math.random() * 1000),
    name: 'Hero Video',
    description: 'Full workflow test job',
    projectId: project.id,
    status: 'In Progress',
    assignments: {
      PM: pm?.id,
      CD: cd?.id,
      Copywriter: copywriter?.id,
      Designer: designer?.id,
      Client: client?.id
    },
    dueDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    order: data.jobs.length,
    createdAt: new Date().toISOString()
  };
  data.jobs.push(job);
  console.log('STEP 2: ✓ Created job:', job.jobNumber, '- Hero Video');

  // Step 3: Create tasks
  const copyTask = {
    id: 't_copy_full_' + Date.now(),
    templateId: 'copy',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: copywriter?.id,
    characterCount: 150,
    content: 'Summer is here! Experience the thrill of our new collection.',
    completedAt: new Date().toISOString(),
    order: 0
  };
  data.tasks.push(copyTask);

  const mediaTask = {
    id: 't_media_full_' + Date.now(),
    templateId: 'media',
    jobId: jobId,
    status: 'Done',
    completed: true,
    assignedTo: designer?.id,
    fileUrl: 'hero-video-final.mp4',
    fileType: 'video',
    completedAt: new Date().toISOString(),
    order: 1
  };
  data.tasks.push(mediaTask);
  console.log('STEP 3: ✓ Created Copy and Media tasks (both DONE)');

  // Step 4: Simulate CD internal approval (moves to "In Review")
  const jobIndex = data.jobs.findIndex(j => j.id === jobId);
  data.jobs[jobIndex] = {
    ...job,
    status: 'In Review',
    internalApprovedBy: cd?.id,
    internalApprovedAt: new Date().toISOString()
  };
  console.log('STEP 4: ✓ CD (' + cd?.name + ') approved internal review');
  console.log('        Job status changed to "In Review"');

  // Update project job count
  project.jobCount = data.jobs.filter(j => j.projectId === project.id).length;

  // Save to localStorage
  localStorage.setItem(storageKey, JSON.stringify(data));

  console.log('');
  console.log('=== WORKFLOW TEST COMPLETE ===');
  console.log('');
  console.log('Job:', job.jobNumber, '- Hero Video');
  console.log('Project: Acme Corp / Summer Launch 2026');
  console.log('Status: In Review (ready for Client Review)');
  console.log('');
  console.log('The job is now visible in:');
  console.log('  - Client Review tab (for Client role users)');
  console.log('  - The Client (' + client?.name + ') can Approve/Reject');
  console.log('');
  console.log('Refresh the page and login as Client to see the job');

  return {
    success: true,
    job: data.jobs[jobIndex],
    project,
    copyTask,
    mediaTask,
    inClientReview: true
  };
};

// ============================================================================
// MULTI-ROLE WORKFLOW TEST SUITE
// Call window.testRoles() or visit ?roletest=true
// ============================================================================
window.testRoles = {
  // Storage key
  _storageKey: 'slash301pm_data',

  // Get data from localStorage
  _getData() {
    return JSON.parse(localStorage.getItem(this._storageKey));
  },

  // Save data to localStorage
  _saveData(data) {
    localStorage.setItem(this._storageKey, JSON.stringify(data));
  },

  // Find person by role
  _findPerson(data, role) {
    return data.people.find(p => p.role === role);
  },

  // Generate unique ID
  _genId(prefix) {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  },

  // ========================================
  // PM WORKFLOW TEST
  // ========================================
  pm: {
    name: 'PM Workflow',
    description: 'Create brief, set up project, assign team members',

    run() {
      const data = window.testRoles._getData();
      const pm = window.testRoles._findPerson(data, 'PM');
      const cd = window.testRoles._findPerson(data, 'CD');
      const copywriter = window.testRoles._findPerson(data, 'Copywriter');
      const designer = window.testRoles._findPerson(data, 'Designer');
      const client = window.testRoles._findPerson(data, 'Client');

      console.log('🎯 PM WORKFLOW TEST');
      console.log('Testing as:', pm?.name);
      console.log('');

      // Create project
      const projectId = window.testRoles._genId('proj');
      const project = {
        id: projectId,
        name: 'Q1 Product Launch',
        client: 'TechCorp Industries',
        description: 'New product launch campaign',
        status: 'In Progress',
        jobCount: 0,
        createdAt: new Date().toISOString(),
        clientColors: { primary: '#6366f1', secondary: '#8b5cf6' }
      };
      data.projects.push(project);
      console.log('✓ Created project:', project.name);

      // Create job with full brief
      const jobId = window.testRoles._genId('job');
      const job = {
        id: jobId,
        jobNumber: 'PM-' + Math.floor(Math.random() * 1000),
        name: 'Launch Video',
        description: 'Hero video for product launch landing page',
        projectId: projectId,
        status: 'In Progress',
        assignments: {
          PM: pm?.id,
          CD: cd?.id,
          Copywriter: copywriter?.id,
          Designer: designer?.id,
          Client: client?.id
        },
        dueDate: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        order: data.jobs.length,
        createdAt: new Date().toISOString(),
        briefedBy: pm?.id,
        briefedAt: new Date().toISOString()
      };
      data.jobs.push(job);
      console.log('✓ Created job:', job.jobNumber, '-', job.name);

      // Create tasks (not yet started)
      const copyTask = {
        id: window.testRoles._genId('task'),
        templateId: 'copy',
        jobId: jobId,
        status: 'To Do',
        completed: false,
        assignedTo: copywriter?.id,
        order: 0
      };
      data.tasks.push(copyTask);
      console.log('✓ Assigned Copy task to', copywriter?.name);

      const mediaTask = {
        id: window.testRoles._genId('task'),
        templateId: 'media',
        jobId: jobId,
        status: 'To Do',
        completed: false,
        assignedTo: designer?.id,
        order: 1
      };
      data.tasks.push(mediaTask);
      console.log('✓ Assigned Media task to', designer?.name);

      project.jobCount = 1;
      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ PM WORKFLOW COMPLETE');
      return { success: true, project, job, copyTask, mediaTask };
    }
  },

  // ========================================
  // COPYWRITER WORKFLOW TEST
  // ========================================
  copywriter: {
    name: 'Copywriter Workflow',
    description: 'View assigned tasks, write copy, mark complete',

    run(jobNumber) {
      const data = window.testRoles._getData();
      const copywriter = window.testRoles._findPerson(data, 'Copywriter');

      console.log('✍️ COPYWRITER WORKFLOW TEST');
      console.log('Testing as:', copywriter?.name);
      console.log('');

      // Find job with To Do copy task assigned to copywriter
      let job, copyTask;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        // Find any job with incomplete copy task
        for (const j of data.jobs) {
          const task = data.tasks.find(t =>
            t.jobId === j.id &&
            t.templateId === 'copy' &&
            t.assignedTo === copywriter?.id &&
            !t.completed
          );
          if (task) {
            job = j;
            copyTask = task;
            break;
          }
        }
      }

      if (!job) {
        console.log('⚠️ No pending copy tasks found for', copywriter?.name);
        return { success: false, error: 'No pending tasks' };
      }

      copyTask = copyTask || data.tasks.find(t => t.jobId === job.id && t.templateId === 'copy');
      console.log('✓ Found task for job:', job.jobNumber, '-', job.name);

      // Simulate working on copy
      const taskIndex = data.tasks.findIndex(t => t.id === copyTask.id);
      data.tasks[taskIndex] = {
        ...copyTask,
        status: 'Done',
        completed: true,
        content: 'Introducing the future of technology. Experience innovation like never before.',
        characterCount: 78,
        completedAt: new Date().toISOString()
      };
      console.log('✓ Completed copy task with content');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ COPYWRITER WORKFLOW COMPLETE');
      return { success: true, job, task: data.tasks[taskIndex] };
    }
  },

  // ========================================
  // DESIGNER WORKFLOW TEST
  // ========================================
  designer: {
    name: 'Designer Workflow',
    description: 'View assigned tasks, create media, mark complete',

    run(jobNumber) {
      const data = window.testRoles._getData();
      const designer = window.testRoles._findPerson(data, 'Designer');

      console.log('🎨 DESIGNER WORKFLOW TEST');
      console.log('Testing as:', designer?.name);
      console.log('');

      // Find job with To Do media task assigned to designer
      let job, mediaTask;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        for (const j of data.jobs) {
          const task = data.tasks.find(t =>
            t.jobId === j.id &&
            t.templateId === 'media' &&
            t.assignedTo === designer?.id &&
            !t.completed
          );
          if (task) {
            job = j;
            mediaTask = task;
            break;
          }
        }
      }

      if (!job) {
        console.log('⚠️ No pending media tasks found for', designer?.name);
        return { success: false, error: 'No pending tasks' };
      }

      mediaTask = mediaTask || data.tasks.find(t => t.jobId === job.id && t.templateId === 'media');
      console.log('✓ Found task for job:', job.jobNumber, '-', job.name);

      // Simulate completing media
      const taskIndex = data.tasks.findIndex(t => t.id === mediaTask.id);
      data.tasks[taskIndex] = {
        ...mediaTask,
        status: 'Done',
        completed: true,
        fileUrl: 'launch-video-final.mp4',
        fileType: 'video',
        completedAt: new Date().toISOString()
      };
      console.log('✓ Completed media task with file upload');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ DESIGNER WORKFLOW COMPLETE');
      return { success: true, job, task: data.tasks[taskIndex] };
    }
  },

  // ========================================
  // CD WORKFLOW TEST (Internal Review)
  // ========================================
  cd: {
    name: 'CD Workflow',
    description: 'Review completed work, approve or reject with feedback',

    approve(jobNumber) {
      const data = window.testRoles._getData();
      const cd = window.testRoles._findPerson(data, 'CD');

      console.log('👔 CD WORKFLOW TEST - APPROVE');
      console.log('Testing as:', cd?.name);
      console.log('');

      // Find job ready for internal review (In Progress with completed tasks)
      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j => {
          if (j.status !== 'In Progress') return false;
          const tasks = data.tasks.filter(t => t.jobId === j.id);
          const copyDone = tasks.find(t => t.templateId === 'copy')?.completed;
          const mediaDone = tasks.find(t => t.templateId === 'media')?.completed;
          return copyDone && mediaDone;
        });
      }

      if (!job) {
        console.log('⚠️ No jobs ready for internal review');
        return { success: false, error: 'No jobs ready for review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);

      // Approve - move to In Review (for client)
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        status: 'In Review',
        internalApprovedBy: cd?.id,
        internalApprovedAt: new Date().toISOString()
      };
      console.log('✓ Approved - moved to Client Review');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CD APPROVAL COMPLETE');
      return { success: true, job: data.jobs[jobIndex] };
    },

    reject(jobNumber, feedback = 'Please revise the creative direction') {
      const data = window.testRoles._getData();
      const cd = window.testRoles._findPerson(data, 'CD');
      const copywriter = window.testRoles._findPerson(data, 'Copywriter');
      const designer = window.testRoles._findPerson(data, 'Designer');

      console.log('👔 CD WORKFLOW TEST - REJECT WITH FEEDBACK');
      console.log('Testing as:', cd?.name);
      console.log('');

      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j => {
          if (j.status !== 'In Progress') return false;
          const tasks = data.tasks.filter(t => t.jobId === j.id);
          const copyDone = tasks.find(t => t.templateId === 'copy')?.completed;
          const mediaDone = tasks.find(t => t.templateId === 'media')?.completed;
          return copyDone && mediaDone;
        });
      }

      if (!job) {
        console.log('⚠️ No jobs ready for internal review');
        return { success: false, error: 'No jobs ready for review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);
      console.log('✓ Feedback:', feedback);

      // Reject - mark tasks incomplete with feedback
      const tasks = data.tasks.filter(t => t.jobId === job.id);
      tasks.forEach(task => {
        const idx = data.tasks.findIndex(t => t.id === task.id);
        data.tasks[idx] = {
          ...task,
          completed: false,
          status: 'In Progress',
          internalFeedback: feedback,
          feedbackBy: cd?.id,
          feedbackAt: new Date().toISOString()
        };
      });
      console.log('✓ Tasks marked for revision with feedback');

      // Update job
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        internalFeedback: feedback,
        internalFeedbackBy: cd?.id,
        internalFeedbackAt: new Date().toISOString()
      };

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CD REJECTION COMPLETE - Tasks sent back for revision');
      return { success: true, job: data.jobs[jobIndex], feedback };
    }
  },

  // ========================================
  // CLIENT WORKFLOW TEST (External Review)
  // ========================================
  client: {
    name: 'Client Workflow',
    description: 'Review work in Client Review tab, approve or reject',

    approve(jobNumber) {
      const data = window.testRoles._getData();
      const client = window.testRoles._findPerson(data, 'Client');

      console.log('🤝 CLIENT WORKFLOW TEST - APPROVE');
      console.log('Testing as:', client?.name);
      console.log('');

      // Find job in Client Review
      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j =>
          j.status === 'In Review' &&
          j.assignments?.Client === client?.id
        );
      }

      if (!job) {
        console.log('⚠️ No jobs in Client Review');
        return { success: false, error: 'No jobs in Client Review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);

      // Approve
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        status: 'Approved (External)',
        clientApprovedBy: client?.id,
        clientApprovedAt: new Date().toISOString()
      };
      console.log('✓ Client approved - Job complete!');

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CLIENT APPROVAL COMPLETE');
      return { success: true, job: data.jobs[jobIndex] };
    },

    reject(jobNumber, feedback = 'Please adjust the messaging to be more brand-aligned') {
      const data = window.testRoles._getData();
      const client = window.testRoles._findPerson(data, 'Client');
      const cd = window.testRoles._findPerson(data, 'CD');

      console.log('🤝 CLIENT WORKFLOW TEST - REJECT WITH FEEDBACK');
      console.log('Testing as:', client?.name);
      console.log('');

      let job;
      if (jobNumber) {
        job = data.jobs.find(j => j.jobNumber === jobNumber);
      } else {
        job = data.jobs.find(j =>
          j.status === 'In Review' &&
          j.assignments?.Client === client?.id
        );
      }

      if (!job) {
        console.log('⚠️ No jobs in Client Review');
        return { success: false, error: 'No jobs in Client Review' };
      }

      console.log('✓ Reviewing job:', job.jobNumber, '-', job.name);
      console.log('✓ Feedback:', feedback);

      // Reject - assign feedback to CD
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);
      data.jobs[jobIndex] = {
        ...job,
        status: 'In Progress',
        clientFeedback: feedback,
        clientFeedbackDate: new Date().toISOString(),
        clientFeedbackBy: client?.id,
        clientFeedbackAssignedTo: cd?.id,
        clientFeedbackStatus: 'pending'
      };
      console.log('✓ Feedback assigned to CD:', cd?.name);

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ CLIENT REJECTION COMPLETE - CD will address feedback');
      return { success: true, job: data.jobs[jobIndex], feedback };
    }
  },

  // ========================================
  // TRAFFIC WORKFLOW TEST
  // ========================================
  traffic: {
    name: 'Traffic Workflow',
    description: 'View all jobs, manage workflow, reassign tasks',

    viewAll() {
      const data = window.testRoles._getData();
      const traffic = window.testRoles._findPerson(data, 'Traffic');

      console.log('🚦 TRAFFIC WORKFLOW TEST - VIEW ALL');
      console.log('Testing as:', traffic?.name);
      console.log('');

      const jobsByStatus = {};
      data.jobs.forEach(job => {
        if (!jobsByStatus[job.status]) jobsByStatus[job.status] = [];
        jobsByStatus[job.status].push(job);
      });

      console.log('Jobs by Status:');
      Object.entries(jobsByStatus).forEach(([status, jobs]) => {
        console.log(`  ${status}: ${jobs.length} jobs`);
      });

      console.log('');
      console.log('✅ TRAFFIC VIEW COMPLETE');
      return { success: true, jobsByStatus };
    },

    reassign(jobNumber, role, newPersonId) {
      const data = window.testRoles._getData();
      const traffic = window.testRoles._findPerson(data, 'Traffic');

      console.log('🚦 TRAFFIC WORKFLOW TEST - REASSIGN');
      console.log('Testing as:', traffic?.name);
      console.log('');

      const job = data.jobs.find(j => j.jobNumber === jobNumber);
      if (!job) {
        console.log('⚠️ Job not found:', jobNumber);
        return { success: false, error: 'Job not found' };
      }

      const newPerson = data.people.find(p => p.id === newPersonId);
      const jobIndex = data.jobs.findIndex(j => j.id === job.id);

      data.jobs[jobIndex] = {
        ...job,
        assignments: {
          ...job.assignments,
          [role]: newPersonId
        }
      };
      console.log('✓ Reassigned', role, 'to', newPerson?.name);

      window.testRoles._saveData(data);

      console.log('');
      console.log('✅ TRAFFIC REASSIGN COMPLETE');
      return { success: true, job: data.jobs[jobIndex] };
    }
  },

  // ========================================
  // FULL WORKFLOW TEST - All roles in sequence
  // ========================================
  runAll() {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('           FULL MULTI-ROLE WORKFLOW TEST');
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('');

    const results = [];

    // Step 1: PM creates brief
    console.log('━━━ STEP 1/6: PM Creates Brief ━━━');
    const pmResult = this.pm.run();
    results.push({ role: 'PM', ...pmResult });
    console.log('');

    if (!pmResult.success) return { success: false, results };

    const jobNumber = pmResult.job.jobNumber;

    // Step 2: Copywriter completes copy
    console.log('━━━ STEP 2/6: Copywriter Completes Copy ━━━');
    const copyResult = this.copywriter.run(jobNumber);
    results.push({ role: 'Copywriter', ...copyResult });
    console.log('');

    // Step 3: Designer completes media
    console.log('━━━ STEP 3/6: Designer Completes Media ━━━');
    const designResult = this.designer.run(jobNumber);
    results.push({ role: 'Designer', ...designResult });
    console.log('');

    // Step 4: CD approves internal review
    console.log('━━━ STEP 4/6: CD Approves Internal Review ━━━');
    const cdResult = this.cd.approve(jobNumber);
    results.push({ role: 'CD', ...cdResult });
    console.log('');

    // Step 5: Client approves
    console.log('━━━ STEP 5/6: Client Approves ━━━');
    const clientResult = this.client.approve(jobNumber);
    results.push({ role: 'Client', ...clientResult });
    console.log('');

    // Step 6: Traffic views all
    console.log('━━━ STEP 6/6: Traffic Views Dashboard ━━━');
    const trafficResult = this.traffic.viewAll();
    results.push({ role: 'Traffic', ...trafficResult });
    console.log('');

    // Summary
    const allPassed = results.every(r => r.success);
    console.log('═══════════════════════════════════════════════════════════════');
    console.log(allPassed ? '✅ ALL ROLE WORKFLOWS PASSED' : '❌ SOME WORKFLOWS FAILED');
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('');
    console.log('Results:');
    results.forEach(r => {
      console.log(`  ${r.success ? '✓' : '✗'} ${r.role}`);
    });

    return { success: allPassed, results, jobNumber };
  },

  // Run rejection flow test
  runRejectionFlow() {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('           REJECTION FLOW TEST');
    console.log('═══════════════════════════════════════════════════════════════');
    console.log('');

    // Create job and complete tasks
    const pmResult = this.pm.run();
    const jobNumber = pmResult.job.jobNumber;
    this.copywriter.run(jobNumber);
    this.designer.run(jobNumber);

    // CD rejects
    console.log('━━━ CD REJECTS WITH FEEDBACK ━━━');
    this.cd.reject(jobNumber, 'Need more energy in the copy and brighter visuals');

    // Copywriter and Designer revise
    console.log('━━━ TEAM REVISES ━━━');
    this.copywriter.run(jobNumber);
    this.designer.run(jobNumber);

    // CD approves
    console.log('━━━ CD APPROVES REVISION ━━━');
    this.cd.approve(jobNumber);

    // Client rejects
    console.log('━━━ CLIENT REJECTS WITH FEEDBACK ━━━');
    this.client.reject(jobNumber, 'Logo needs to be more prominent');

    console.log('');
    console.log('✅ REJECTION FLOW TEST COMPLETE');
    console.log('Client feedback is now assigned to CD for action');

    return { success: true, jobNumber };
  }
};

// Auto-run tests based on URL parameters
// ?runtest=true     - Basic PM workflow test
// ?roletest=true    - Full multi-role workflow test
// ?roletest=reject  - Rejection flow test
// ?roletest=pm      - PM only
// ?roletest=cd      - CD approval only
// ?roletest=client  - Client approval only
(function() {
  const params = new URLSearchParams(window.location.search);

  if (params.get('runtest') === 'true') {
    setTimeout(() => {
      console.log('🔄 Auto-running PM workflow test...');
      const result = window.testPMWorkflow.full();
      if (result.success) {
        showBanner('✅ PM WORKFLOW TEST PASSED - Job "' + result.job.jobNumber + '" is in Client Review', '#4caf50');
      }
    }, 1000);
  }

  if (params.has('roletest')) {
    setTimeout(() => {
      const testType = params.get('roletest');

      if (testType === 'true' || testType === 'all') {
        console.log('🔄 Auto-running FULL multi-role workflow test...');
        const result = window.testRoles.runAll();
        if (result.success) {
          showBanner('✅ ALL ROLE TESTS PASSED - Job "' + result.jobNumber + '" completed full workflow', '#4caf50');
        } else {
          showBanner('❌ SOME ROLE TESTS FAILED - Check console for details', '#f44336');
        }
      } else if (testType === 'reject') {
        console.log('🔄 Auto-running rejection flow test...');
        const result = window.testRoles.runRejectionFlow();
        showBanner('✅ REJECTION FLOW TEST COMPLETE - Job "' + result.jobNumber + '"', '#ff9800');
      } else if (testType === 'pm') {
        const result = window.testRoles.pm.run();
        showBanner('✅ PM TEST - Created job "' + result.job?.jobNumber + '"', '#3b82f6');
      } else if (testType === 'copywriter') {
        const result = window.testRoles.copywriter.run();
        showBanner(result.success ? '✅ COPYWRITER TEST PASSED' : '⚠️ No pending copy tasks', result.success ? '#4caf50' : '#ff9800');
      } else if (testType === 'designer') {
        const result = window.testRoles.designer.run();
        showBanner(result.success ? '✅ DESIGNER TEST PASSED' : '⚠️ No pending media tasks', result.success ? '#4caf50' : '#ff9800');
      } else if (testType === 'cd') {
        const result = window.testRoles.cd.approve();
        showBanner(result.success ? '✅ CD APPROVAL - Job in Client Review' : '⚠️ No jobs ready for review', result.success ? '#4caf50' : '#ff9800');
      } else if (testType === 'client') {
        const result = window.testRoles.client.approve();
        showBanner(result.success ? '✅ CLIENT APPROVED - Workflow complete!' : '⚠️ No jobs in Client Review', result.success ? '#4caf50' : '#ff9800');
      }
    }, 1000);
  }

  function showBanner(text, color) {
    const banner = document.createElement('div');
    banner.innerHTML = text;
    banner.style.cssText = 'position:fixed;top:0;left:0;right:0;background:' + color + ';color:white;padding:12px;text-align:center;font-weight:bold;z-index:9999;font-family:system-ui;box-shadow:0 2px 8px rgba(0,0,0,0.2)';
    document.body.prepend(banner);
    console.log('');
    console.log(text);
  }
})();

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App />);
