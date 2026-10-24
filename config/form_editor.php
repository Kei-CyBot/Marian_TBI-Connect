<?php

/**
 * Editable field schema for the submission edit modal.
 *
 * Mirrors the input types and dropdown/radio option lists declared by the
 * React form catalog (resources/js/components/StartupForms.tsx). Keys are
 * data keys inside FormSubmission::$data. Any scalar key present in a
 * submission but not claimed here falls back to a plain text input, so no
 * saved value ever becomes uneditable.
 *
 * Types: text | email | date | textarea | select | radio | checkbox | readonly
 */
$sectors = [
    'Education',
    'Systems & Computing',
    'Agriculture',
    'Materials Engineering',
    'Disease Management',
    'Health Security, Emergency, and Disaster Risk Management',
    'Health Technology and Innovation',
    'Health Promotion',
    'Maternal, Newborn and Child Health',
    'Mental Health',
    'Nutrition and Food Security',
    'Sexual and Reproductive Health',
];

$trlStatuses = [
    'Idea Stage',
    'Working Prototype',
    'Lab Tests',
    'Industrial Tests',
    'Early Market Traction',
    'Early Revenue',
    'Breakeven',
    'Profitable',
    'Scale-up',
];

$incubationStages = [
    'Pre-incubation (Ideation & Feasibility)',
    'Incubation – Phase 1: Development (Prototype/MVP/TRL Readiness Level)',
    'Incubation – Phase 2: Growth (Market Pilot & Traction)',
    'Graduation (Seed or Series A Funding)',
];

return [
    'forms' => [
        1 => [
            'selectionOutcome' => [
                'label' => 'Selection Outcome',
                'type' => 'radio',
                'options' => [
                    'incubation' => 'Recommended for Incubation: (Score ≥ 70)',
                    'pre-incubation' => 'Recommended for Pre-Incubation: (Score 55−69 and with high potential)',
                    'not-recommended' => 'Not Recommended: (Score ≤ 54 or critical flaws identified)',
                ],
            ],
        ],

        2 => [
            'dateOfApplication' => ['label' => 'Date of Application', 'type' => 'date'],
            'applicantName' => ['label' => 'Name of Applicant (Last Name, First Name, Middle Name)', 'type' => 'text'],
            'uicAffiliation' => ['label' => 'UIC Affiliation (if any)', 'type' => 'text'],
            'address' => ['label' => 'Address', 'type' => 'text'],
            'mobileNumber' => ['label' => 'Mobile Number', 'type' => 'text'],
            'emailAddress' => ['label' => 'Email Address', 'type' => 'email'],
            'biography' => ['label' => 'Biography (at most 150 words)', 'type' => 'textarea'],
            'keyExperience' => ['label' => 'Key Experience / Expertise to share', 'type' => 'textarea'],
            'sectorInterest' => ['label' => 'Sectors of Interest', 'type' => 'text'],
            'startupName' => ['label' => 'Proposed / Working Name of your Startup/Company', 'type' => 'text'],
            'orgWebsite' => ['label' => 'Organization Website (if any)', 'type' => 'text'],
            'companyMembers' => ['label' => 'Company Members (Other than the applicant)', 'type' => 'textarea'],
            'innovationTitle' => ['label' => 'Title of your Innovation', 'type' => 'text'],
            'firstSector' => ['label' => 'First Relevant Sector', 'type' => 'select', 'options' => $sectors],
            'secondSector' => ['label' => 'Second Relevant Sector', 'type' => 'select', 'options' => $sectors],
            'theProblem' => ['label' => 'The Problem', 'type' => 'textarea'],
            'theSolution' => ['label' => 'The Solution', 'type' => 'textarea'],
            'keyBenefits' => ['label' => 'Key benefits or competitive advantage', 'type' => 'textarea'],
            'targetMarket' => ['label' => 'Target Market', 'type' => 'textarea'],
            'trlStatus' => ['label' => 'Status (TRL)', 'type' => 'select', 'options' => $trlStatuses],
            'commercializationRoute' => [
                'label' => 'Commercialization Route',
                'type' => 'select',
                'options' => ['Startup', 'Spinoff', 'Licensing', 'Undecided Yet'],
            ],
            'ipStatus' => [
                'label' => 'Intellectual Property (IP) Status',
                'type' => 'select',
                'options' => ['None', 'Patent/Trademark Pending', 'Granted/Registered', 'Protected by NDA/Trade Secret'],
            ],
            'fundingStatus' => [
                'label' => 'Funding Status',
                'type' => 'select',
                'options' => ['Self-funded', 'Applied for Grant', 'Received Grant', 'Seed Funding', 'No Funding yet'],
            ],
            'revenueModel' => ['label' => 'Revenue Model', 'type' => 'textarea'],
            'searchKeywords' => ['label' => 'Search Keywords', 'type' => 'text'],
            'innovationDevelopment' => ['label' => 'Innovation/Company Development', 'type' => 'textarea'],
            'tbiGoal' => ['label' => 'Goal for the MARIAN TBI Incubation Program', 'type' => 'textarea'],
            'tbiAssistance' => ['label' => 'Assistance you are looking for from MARIAN TBI', 'type' => 'textarea'],
            'sdgs' => ['label' => 'UN Sustainable Development Goals (SDGs)', 'type' => 'textarea'],
            'mediaUrl1' => ['label' => 'Media Description and URL (first entry)', 'type' => 'text'],
            'mediaUrl2' => ['label' => 'Media Description and URL (second entry)', 'type' => 'text'],
        ],

        3 => [
            'teamLeadName' => ['label' => 'Team Lead Name', 'type' => 'text'],
            'courseYear' => ['label' => 'Course / Year', 'type' => 'text'],
            'emailMobile' => ['label' => 'Email & Mobile', 'type' => 'text'],
            'coFounder1' => ['label' => 'Co-Founder / Member 1', 'type' => 'text'],
            'coFounder2' => ['label' => 'Co-Founder / Member 2', 'type' => 'text'],
            'coFounder3' => ['label' => 'Co-Founder / Member 3', 'type' => 'text'],
            'thesisTitle' => ['label' => 'Thesis Title', 'type' => 'text'],
            'thesisAdvisor' => ['label' => 'Thesis Advisor', 'type' => 'text'],
            'projectStage' => [
                'label' => 'Where is the project right now?',
                'type' => 'radio',
                'options' => [
                    'concept' => 'Concept Only: We have the math/theory, but no physical model yet.',
                    'rough' => "Rough Prototype: We built it, but it's messy and needs fixing.",
                    'lab' => 'Lab Tested (TRL 4): It works in the lab under controlled conditions.',
                ],
            ],
            'whoHasProblem' => ['label' => 'Who has the problem you are solving?', 'type' => 'text'],
            'whyBetter' => ['label' => 'Why is your solution better than what they use now?', 'type' => 'text'],
            'hasThesisAbstract' => ['label' => 'Thesis Abstract (Print or Digital Copy)', 'type' => 'checkbox'],
            'commitAgreed' => [
                'label' => 'I/We understand that building a product takes time and we are willing to attend coaching sessions.',
                'type' => 'checkbox',
            ],
            'receivedBy' => ['label' => 'Received by', 'type' => 'text'],
            'receivedDate' => ['label' => 'Date received', 'type' => 'date'],
        ],

        7 => [
            'startupName' => ['label' => 'Name of Startup/Company', 'type' => 'text'],
            'otherSkills' => ['label' => 'Other critical skills / training', 'type' => 'text'],
        ],

        8 => [
            'feedback9' => ['label' => 'Most valuable part of the training (Question 9)', 'type' => 'text'],
            'feedback10' => ['label' => 'Future training topics (Question 10)', 'type' => 'text'],
        ],

        9 => [
            'startupName' => ['label' => 'Name of Startup / Company', 'type' => 'text'],
            'reportDate' => ['label' => 'Date of Report', 'type' => 'date'],
            'periodFrom' => ['label' => 'Reporting Period – From', 'type' => 'date'],
            'periodTo' => ['label' => 'Reporting Period – To', 'type' => 'date'],
            'trlStart' => ['label' => 'TRL Start', 'type' => 'text'],
            'trlAchieved' => ['label' => 'TRL Achieved', 'type' => 'text'],
            'trlTarget' => ['label' => 'TRL Target', 'type' => 'text'],
            'overallPerf' => [
                'label' => 'Overall Performance',
                'type' => 'radio',
                'options' => ['Excellent', 'Good', 'Satisfactory', 'Needs Attention'],
            ],
            'evalName' => ['label' => "Evaluator's Name", 'type' => 'text'],
            'evalTitle' => ['label' => "Evaluator's Title", 'type' => 'text'],
            'evalDate' => ['label' => 'Date of Evaluation', 'type' => 'date'],
        ],

        10 => [
            'startupName' => ['label' => 'Name of Startup / Company', 'type' => 'text'],
            'periodFrom' => ['label' => 'Period Covered – From', 'type' => 'date'],
            'periodTo' => ['label' => 'Period Covered – To', 'type' => 'date'],
            'incubationStage' => ['label' => 'Stage in Incubation', 'type' => 'radio', 'options' => $incubationStages],
            'founderName' => ['label' => 'Prepared by (Founder/CEO) – Name', 'type' => 'text'],
            'tbiManagerName' => ['label' => 'TBI Manager – Name', 'type' => 'text'],
            'goalTech' => ['label' => 'Top Priority Technical Goal', 'type' => 'text'],
            'goalMarket' => ['label' => 'Top Priority Market Goal', 'type' => 'text'],
            'goalFunding' => ['label' => 'Top Priority Funding/Operational Goal', 'type' => 'text'],
            'authDate1' => ['label' => 'Startup CEO/Founder – Date', 'type' => 'date'],
            'authDate2' => ['label' => 'MARIAN TBI Manager – Date', 'type' => 'date'],
        ],

        11 => [
            'startupName' => ['label' => 'Name of Startup / Company', 'type' => 'text'],
            'techFocus' => ['label' => 'Technology / Focus Area', 'type' => 'text'],
            'evalDate' => ['label' => 'Date of Evaluation', 'type' => 'date'],
            'tenancyLength' => ['label' => 'Length of Tenancy', 'type' => 'text'],
            'incubationStage' => ['label' => 'Stage in Incubation', 'type' => 'radio', 'options' => $incubationStages],
            'recArea' => ['label' => 'Recommendation – Area', 'type' => 'text'],
            'recSpecific' => ['label' => 'Recommendation – Specific Recommendation', 'type' => 'text'],
            'recIncubationStatus' => ['label' => 'Recommendation – Incubation Status', 'type' => 'text'],
            'recNextSteps' => ['label' => 'Recommendation – Next Steps / Action Plan', 'type' => 'text'],
            'evalByName' => ['label' => 'Evaluated by – Name', 'type' => 'text'],
            'evalByDate' => ['label' => 'Evaluated by – Date', 'type' => 'date'],
            'mgrName' => ['label' => 'MARIAN TBI Manager – Name', 'type' => 'text'],
            'mgrDate' => ['label' => 'MARIAN TBI Manager – Date', 'type' => 'date'],
        ],

        12 => [
            'startupName' => ['label' => 'Name of Startup / Company', 'type' => 'text'],
            'evalDate' => ['label' => 'Date of Evaluation', 'type' => 'date'],
            'incubationStage' => [
                'label' => 'Current Incubation Stage',
                'type' => 'select',
                'options' => [
                    '1' => '1 - Application',
                    '2' => '2 - Pre-incubation',
                    '3' => '3 - Active Incubation',
                ],
            ],
            'selectedRec' => [
                'label' => 'Final Recommendation',
                'type' => 'radio',
                'options' => [
                    'Pre-Incubation (Ideation/Validation)',
                    'Incubation (MVP/Growth)',
                    'Acceleration (Market/Scale)',
                    'On Hold / Not Recommended',
                ],
            ],
            'evaluatorName' => ['label' => "Evaluator's Name", 'type' => 'text'],
            'evaluatorDate' => ['label' => 'Date', 'type' => 'date'],
        ],

        13 => [
            'startupName' => ['label' => 'Name of Startup', 'type' => 'text'],
            'legalEntity' => [
                'label' => 'Legal Entity',
                'type' => 'select',
                'options' => ['Corporation', 'Sole Proprietorship'],
            ],
            'programCohort' => ['label' => 'Program / Cohort', 'type' => 'text'],
            'dateEntry' => ['label' => 'Date of Entry', 'type' => 'date'],
            'dateExit' => ['label' => 'Date of Exit / Graduation', 'type' => 'date'],
            'exitReason' => [
                'label' => 'Reason for Exit',
                'type' => 'radio',
                'options' => [
                    'graduation' => 'Successful Graduation: Achieved market viability and TRL 7 or higher.',
                    'commercial' => 'Full Commercial Deployment: Operating independently and generating sustained revenue.',
                    'other' => 'Other (Specify):',
                ],
            ],
            'exitOtherSpecify' => ['label' => 'Other reason (specify)', 'type' => 'text'],
            'tbiManagerName' => ['label' => 'TBI Manager – Printed Name', 'type' => 'text'],
            'tbiManagerDate' => ['label' => 'TBI Manager – Date', 'type' => 'date'],
            'uttoHeadName' => ['label' => 'UTTO Head – Printed Name', 'type' => 'text'],
            'uttoHeadDate' => ['label' => 'UTTO Head – Date', 'type' => 'date'],
        ],

        15 => [
            'startupName' => ['label' => 'Name of Startup / Company', 'type' => 'text'],
            'email' => ['label' => 'Email Address', 'type' => 'email'],
            'contactNumber' => ['label' => 'Contact Number', 'type' => 'text'],
            'allotmentNumber' => ['label' => 'Incubation Support Allotment Number', 'type' => 'text'],
            'evalDate' => ['label' => 'Date of Final Evaluation', 'type' => 'date'],
            'primaryTrigger' => ['label' => 'Primary Exit Trigger (Summary)', 'type' => 'textarea'],
            'remarksPerformance' => ['label' => 'Remarks on Performance', 'type' => 'textarea'],
            'postIncubationPlan' => ['label' => 'Post-Incubation Plan (Future Tracking)', 'type' => 'textarea'],
            'tbiManagerName' => ['label' => 'TBI Manager – Printed Name', 'type' => 'text'],
            'tbiManagerDate' => ['label' => 'TBI Manager – Date', 'type' => 'date'],
            'rpicDirectorName' => ['label' => 'RPIC Director – Printed Name', 'type' => 'text'],
            'rpicDirectorDate' => ['label' => 'RPIC Director – Date', 'type' => 'date'],
        ],

        17 => [
            'witnessName' => ['label' => 'Witnessed by: TBI Program Manager', 'type' => 'text'],
        ],

        21 => [
            'employeeName' => ['label' => 'Employee Name', 'type' => 'text'],
            'position' => ['label' => 'Position', 'type' => 'text'],
            'department' => ['label' => 'Department/Unit', 'type' => 'readonly'],
            'effectivityDate' => ['label' => 'Effectivity Date', 'type' => 'date'],
            'sec1Files' => ['label' => 'All project files migrated to TBI Official Cloud Drive (Attach List)', 'type' => 'checkbox'],
            'sec1Credentials' => ['label' => 'Login credentials for official social media/portals surrendered', 'type' => 'checkbox'],
            'sec1StatusReport' => ['label' => 'Status Report (Attach list) submitted and discussed with successor', 'type' => 'checkbox'],
            'sec1TechAsst' => ['label' => 'Verified by (Technical Asst.)', 'type' => 'text'],
            'sec1TbiManager' => ['label' => 'Verified by (TBI Manager)', 'type' => 'text'],
            'laptopChecked' => ['label' => 'Laptop issued', 'type' => 'checkbox'],
            'laptopSerial' => ['label' => 'Laptop Serial No.', 'type' => 'text'],
            'laptopCondition' => ['label' => 'Laptop Condition', 'type' => 'select', 'options' => ['Good', 'Damaged']],
            'tabletChecked' => ['label' => 'Tablet issued', 'type' => 'checkbox'],
            'tabletSerial' => ['label' => 'Tablet Serial No.', 'type' => 'text'],
            'tabletCondition' => ['label' => 'Tablet Condition', 'type' => 'select', 'options' => ['Good', 'Damaged']],
            'keysChecked' => ['label' => 'Office Keys / ID Badge / Access Cards', 'type' => 'checkbox'],
            'equipChecked' => ['label' => 'Specialized TBI Equipment', 'type' => 'checkbox'],
            'equipSpecify' => ['label' => 'Equipment (specify)', 'type' => 'text'],
            'sec2Liaison' => ['label' => 'Verified by (Liaison Staff)', 'type' => 'text'],
            'sec2Custodian' => ['label' => 'Verified by (Property Custodian)', 'type' => 'text'],
            'mgrName' => ['label' => 'MARIAN TBI Manager', 'type' => 'text'],
            'mgrDate' => ['label' => 'MARIAN TBI Manager – Date', 'type' => 'date'],
            'hrName' => ['label' => 'HR/Admin Officer', 'type' => 'text'],
            'hrDate' => ['label' => 'HR/Admin Officer – Date', 'type' => 'date'],
        ],
    ],
];
