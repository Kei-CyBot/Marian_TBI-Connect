import { X, FileText, Paperclip, Printer, Eye, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, useRef, useEffect, useMemo, useContext, createContext, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

export type FormFieldValue = string | boolean | string[] | number | Record<string, unknown>;
export type FormValues = Record<string, FormFieldValue>;

interface FormRegistry {
  register: (formId: number, read: () => FormValues) => void;
  unregister: (formId: number) => void;
  read: (formId: number) => FormValues | null;
}

const FormRegistryContext = createContext<FormRegistry | null>(null);

export function FormRegistryProvider({ children }: { children: ReactNode }) {
  const entries = useRef(new Map<number, () => FormValues>());
  const registry = useMemo<FormRegistry>(() => ({
    register: (formId, read) => {
      entries.current.set(formId, read);
    },
    unregister: (formId) => {
      entries.current.delete(formId);
    },
    read: (formId) => entries.current.get(formId)?.() ?? null,
  }), []);

  return <FormRegistryContext.Provider value={registry}>{children}</FormRegistryContext.Provider>;
}

export function useFormRegistry(): FormRegistry | null {
  return useContext(FormRegistryContext);
}

export function useFormValues(formId: number, read: () => FormValues): void {
  const registry = useContext(FormRegistryContext);
  useEffect(() => {
    registry?.register(formId, read);
    return () => {
      registry?.unregister(formId);
    };
  });
}

export interface RequiredFormField {
  key: string;
  label: string;
  isEmpty?: (value: FormFieldValue | undefined) => boolean;
}

const isEmptyFieldValue = (value: FormFieldValue | undefined): boolean => {
  if (value === undefined || value === null) {
    return true;
  }
  if (typeof value === 'string') {
    return value.trim() === '';
  }
  if (typeof value === 'boolean') {
    return !value;
  }
  if (Array.isArray(value)) {
    return value.length === 0;
  }
  if (typeof value === 'object') {
    return Object.keys(value).length === 0;
  }
  return false;
};

const missingTrainingCategory = (value: FormFieldValue | undefined): boolean => {
  if (value === undefined || value === null || typeof value !== 'object' || Array.isArray(value)) {
    return true;
  }
  const keys = Object.keys(value);
  return [0, 1, 2, 3, 4, 5].some((category) => !keys.some((key) => key.startsWith(`${category}-`)));
};

export const FORM_REQUIRED_FIELDS: Record<number, RequiredFormField[]> = {
  2: [
    { key: 'dateOfApplication', label: 'Date of Application' },
    { key: 'applicantName', label: 'Name of Applicant' },
    { key: 'address', label: 'Address' },
    { key: 'mobileNumber', label: 'Mobile Number' },
    { key: 'emailAddress', label: 'Email Address' },
    { key: 'biography', label: 'Biography' },
    { key: 'keyExperience', label: 'Key Experience / Expertise to share' },
    { key: 'sectorInterest', label: 'Sectors of Interest' },
    { key: 'startupName', label: 'Proposed / Working Name of your Startup/Company' },
    { key: 'orgWebsite', label: 'Organization Website' },
    { key: 'companyMembers', label: 'Company Members' },
    { key: 'innovationTitle', label: 'Title of your Innovation' },
    { key: 'firstSector', label: 'First Relevant Sector' },
    { key: 'secondSector', label: 'Second Relevant Sector' },
    { key: 'theProblem', label: 'The Problem' },
    { key: 'theSolution', label: 'The Solution' },
    { key: 'keyBenefits', label: 'Key benefits or competitive advantage' },
    { key: 'targetMarket', label: 'Target Market' },
    { key: 'trlStatus', label: 'Status (TRL)' },
    { key: 'commercializationRoute', label: 'Commercialization Route' },
    { key: 'ipStatus', label: 'Intellectual Property (IP) Status' },
    { key: 'fundingStatus', label: 'Funding Status' },
    { key: 'revenueModel', label: 'Revenue Model' },
    { key: 'searchKeywords', label: 'Search Keywords' },
    { key: 'innovationDevelopment', label: 'Innovation/Company Development' },
    { key: 'tbiGoal', label: 'Goal for the MARIAN TBI Incubation Program' },
    { key: 'tbiAssistance', label: 'Assistance you are looking for from MARIAN TBI' },
    { key: 'sdgs', label: 'UN Sustainable Development Goals (SDGs)' },
    { key: 'mediaUrl1', label: 'Media Description and URL (first entry)' },
    { key: 'mediaUrl2', label: 'Media Description and URL (second entry)' },
  ],
  3: [
    { key: 'teamLeadName', label: 'Team Lead Name' },
    { key: 'courseYear', label: 'Course / Year' },
    { key: 'emailMobile', label: 'Email & Mobile' },
    { key: 'thesisTitle', label: 'Thesis Title' },
    { key: 'thesisAdvisor', label: 'Thesis Advisor' },
    { key: 'projectStage', label: 'Where is the project right now?' },
    { key: 'whoHasProblem', label: 'Who has the problem you are solving?' },
    { key: 'whyBetter', label: 'Why is your solution better than what they use now?' },
    { key: 'whyJoin', label: 'Why do you want to join M-TSA?' },
  ],
  7: [
    { key: 'startupName', label: 'Name of Startup/Company' },
    { key: 'members', label: 'Members' },
    { key: 'ratings', label: 'Future training area ratings', isEmpty: missingTrainingCategory },
    { key: 'otherSkills', label: 'Other critical skills / training' },
  ],
  8: [
    { key: 'preRatings', label: 'Pre-training survey ratings' },
    { key: 'postContentRatings', label: 'Content Quality ratings' },
    { key: 'postInstructorRatings', label: 'Instructor/Facilitator ratings' },
    { key: 'postImpactRatings', label: 'Impact and Value ratings' },
    { key: 'feedback10', label: 'Future training topics (Question 10)' },
  ],
};

export function findMissingRequiredFields(formId: number, values: FormValues): RequiredFormField[] {
  return (FORM_REQUIRED_FIELDS[formId] ?? []).filter((field) => {
    const value = values[field.key];
    return field.isEmpty ? field.isEmpty(value) : isEmptyFieldValue(value);
  });
}

// ──────────────────────────────────────────────
//  Master list of all 21 startup forms
// ──────────────────────────────────────────────
export interface StartupForm {
  id: number;
  name: string;
  attachmentOnly: boolean;
}

export const STARTUP_FORMS: StartupForm[] = [
  { id: 1, name: 'Form 1 - Startup Selection Criteria', attachmentOnly: false },
  { id: 2, name: 'Form 2 - Application Form and Innovation Profile', attachmentOnly: false },
  { id: 3, name: 'Form 3 - M-TSA Intent To Incubate Form', attachmentOnly: false },
  { id: 4, name: 'Form 4 - Incubation Agreement (PDF Attachment)', attachmentOnly: true },
  { id: 5, name: 'Form 5 - Confidentiality and Non-Disclosure Agreement', attachmentOnly: true },
  { id: 6, name: 'Form 6 - Memorandum of Understanding', attachmentOnly: true },
  { id: 7, name: 'Form 7 - Training Needs Assessment', attachmentOnly: false },
  { id: 8, name: 'Form 8 - Training/Event Evaluation Survey', attachmentOnly: false },
  { id: 9, name: 'Form 9 - Progress Monitoring Report', attachmentOnly: false },
  { id: 10, name: 'Form 10 - Quarterly Work Plan (QWP)', attachmentOnly: false },
  { id: 11, name: 'Form 11 - Incubation Evaluation Form', attachmentOnly: false },
  { id: 12, name: 'Form 12 - Startup Incubation Evaluation', attachmentOnly: false },
  { id: 13, name: 'Form 13 - Clearance Certificate', attachmentOnly: false },
  { id: 14, name: 'Form 14 - Business Plan Format (for Incubation)', attachmentOnly: false },
  { id: 15, name: 'Form 15 - Incubation Exit (Graduation/Exit) Form', attachmentOnly: false },
  { id: 16, name: 'Form 16 - TBI Code of Conduct, MARIAN TBI, UIC', attachmentOnly: false },
  { id: 17, name: 'Form 17 - Incubatee Code of Conduct, MARIAN TBI, UIC', attachmentOnly: false },
  { id: 18, name: 'Form 18 - Deed of Assignment of Startup Assets and Intellectual Property', attachmentOnly: true },
  { id: 19, name: 'Form 19 - Steering Committee Charter', attachmentOnly: false },
  { id: 20, name: 'Form 20 - ICT for Health Ethics & Compliance Checklist', attachmentOnly: false },
  { id: 21, name: 'Form 21 - Personnel Transition, Document Turnover, and Asset Endorsement Form', attachmentOnly: false },
];

// ──────────────────────────────────────────────
//  Scrollable list component
// ──────────────────────────────────────────────
interface StartupFormsListProps {
  onSelectForm: (formId: number | null) => void;
  onAttachmentOnly: (formId: number) => void;
}

export function StartupFormsList({ onSelectForm, onAttachmentOnly }: StartupFormsListProps) {
  const [selectedFormId, setSelectedFormId] = useState<number | null>(null);
  const closeSelectedForm = () => {
    setSelectedFormId(null);
    onSelectForm(null);
  };

  const modalContent = (
    <>
      {/* Form detail modals */}
      {selectedFormId === 1 && (
        <Form1Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 2 && (
        <Form2Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 3 && (
        <Form3Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 7 && (
        <Form7Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 8 && (
        <Form8Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 9 && (
        <Form9Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 10 && (
        <Form10Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 11 && (
        <Form11Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 12 && (
        <Form12Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 13 && (
        <Form13Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 14 && (
        <Form14Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 15 && (
        <Form15Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 16 && (
        <Form16Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 17 && (
        <Form17Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 19 && (
        <Form19Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 20 && (
        <Form20Modal onClose={closeSelectedForm} />
      )}

      {selectedFormId === 21 && (
        <Form21Modal onClose={closeSelectedForm} />
      )}

      {/* Placeholder for forms that don't have content yet */}
      {selectedFormId !== null && ![1, 2, 3, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 19, 20, 21].includes(selectedFormId) && !STARTUP_FORMS.find(f => f.id === selectedFormId)?.attachmentOnly && (
        <FormPlaceholderModal
          form={STARTUP_FORMS.find(f => f.id === selectedFormId)!}
          onClose={closeSelectedForm}
        />
      )}
    </>
  );

  return (
    <>
      <div>
        <div className="mb-3 flex items-center justify-between gap-3">
          <div>
            <label className="block text-sm font-semibold text-slate-800">
              Startup Forms
            </label>
            <p className="mt-0.5 text-xs text-slate-500">Choose a form to open, or upload a required PDF.</p>
          </div>
          <span className="rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 ring-1 ring-inset ring-teal-200">
            {STARTUP_FORMS.length} forms
          </span>
        </div>
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="max-h-64 divide-y divide-slate-100 overflow-y-auto">
            {STARTUP_FORMS.map((form) => (
              <button
                key={form.id}
                type="button"
                aria-label={`${form.name}${form.attachmentOnly ? ', PDF upload required' : ', open form'}`}
                onClick={() => {
                  if (!form.attachmentOnly) {
                    setSelectedFormId(form.id);
                    onSelectForm(form.id);
                  } else {
                    onAttachmentOnly(form.id);
                  }
                }}
                className={`group flex w-full items-center gap-3 border-l-[3px] px-4 py-3 text-left text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-teal-600 ${
                  form.attachmentOnly
                    ? 'border-l-amber-400 bg-amber-50/30 text-slate-700 hover:bg-amber-50'
                    : selectedFormId === form.id
                      ? 'border-l-teal-600 bg-teal-50 text-teal-950'
                      : 'border-l-transparent text-slate-700 hover:border-l-teal-500 hover:bg-teal-50/70 hover:text-teal-950'
                }`}
              >
                {form.attachmentOnly ? (
                  <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800">
                    <Paperclip className="h-4 w-4" />
                  </span>
                ) : (
                  <span className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg ${
                    selectedFormId === form.id ? 'bg-teal-200 text-teal-900' : 'bg-teal-50 text-teal-700 group-hover:bg-teal-100'
                  }`}>
                    <FileText className="h-4 w-4" />
                  </span>
                )}
                <span className="min-w-0 flex-1 truncate font-medium">{form.name}</span>
                {form.attachmentOnly ? (
                  <span className="whitespace-nowrap rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-900 ring-1 ring-inset ring-amber-200">
                    PDF upload
                  </span>
                ) : (
                  <Eye className="h-4 w-4 flex-shrink-0 text-slate-400 transition-colors group-hover:text-teal-700" />
                )}
              </button>
            ))}
          </div>
        </div>
      </div>

      {typeof document !== 'undefined'
        ? createPortal(<div className="startup-source-form-portal">{modalContent}</div>, document.body)
        : null}
    </>
  );
}

// ──────────────────────────────────────────────
//  Placeholder modal for forms not yet implemented
// ──────────────────────────────────────────────
function FormPlaceholderModal({ form, onClose }: { form: StartupForm; onClose: () => void }) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-md overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">{form.name}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2">
            <X className="w-5 h-5" />
          </button>
        </div>
        <div className="p-6 text-center">
          <FileText className="w-12 h-12 text-gray-300 mx-auto mb-3" />
          <p className="text-sm text-gray-500">This form will be available soon.</p>
        </div>
        <div className="p-4 border-t border-gray-200 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 1 – Startup Selection Criteria
// ──────────────────────────────────────────────
interface CriteriaRow {
  subCriteria: string;
  description: string;
  points: string;
}

interface CriteriaCategory {
  category: string;
  categoryDescription: string;
  totalPoints: string;
  rows: CriteriaRow[];
}

const FORM1_CRITERIA: CriteriaCategory[] = [
  {
    category: '1. Business Idea & Impact',
    categoryDescription: 'Assessment of the Problem and Solution Alignment.',
    totalPoints: '30 points',
    rows: [
      {
        subCriteria: 'Innovation & Health-Tech Relevance',
        description:
          'Does the venture address a significant health problem or opportunity through a novel and creative technology-based solution (ICT-driven)? Does the proposed solution have the potential to disrupt or significantly improve existing healthcare processes?',
        points: '10 points',
      },
      {
        subCriteria: 'Market Need & Validation',
        description:
          'Is there a clear, well-defined target market (e.g., hospitals, clinics, patients, health regions)? Is the market large, accessible, and is there evidence of problem validation from potential users in the healthcare community?',
        points: '10 points',
      },
      {
        subCriteria: 'Value Proposition & Social Impact',
        description:
          'What unique value does the solution offer (e.g., cost-reduction, improved access, better outcomes)? Does it demonstrate a clear potential for positive social and strategic national impact?',
        points: '10 points',
      },
    ],
  },
  {
    category: '2. Team',
    categoryDescription: 'Assessment of Capability, Experience, and Execution Potential.',
    totalPoints: '30 points',
    rows: [
      {
        subCriteria: 'Founding Team Expertise',
        description:
          'Does the team possess the necessary technical (ICT/Health Science) and business skills? Is there a designated team member with domain expertise relevant to the proposed health solution?',
        points: '15 points',
      },
      {
        subCriteria: 'Team Strength & Cohesion',
        description:
          'Does the team demonstrate strong commitment, passion, and work ethic? Are roles and responsibilities clear, and does the team structure support the rapid development of the health-tech product?',
        points: '15 points',
      },
    ],
  },
  {
    category: '3. Business Model & Growth Potential',
    categoryDescription: 'Assessment of Economic Viability and Scalability.',
    totalPoints: '20 points',
    rows: [
      {
        subCriteria: 'Financial Sustainability',
        description:
          'Does the business model demonstrate a clear and viable path towards profitability within the projected timeline? Are the revenue projections realistic and achievable for a health-tech enterprise?',
        points: '10 points',
      },
      {
        subCriteria: 'Scalability & Replication',
        description:
          'Does the venture have the potential to scale rapidly (regional, national, or global)? Are the key factors for facilitating rapid growth (e.g., partnerships, cloud infrastructure) clearly defined?',
        points: '10 points',
      },
    ],
  },
  {
    category: '4. Technology & Readiness',
    categoryDescription: 'Assessment of Technical Foundation and Competitive Edge.',
    totalPoints: '20 points',
    rows: [
      {
        subCriteria: 'Technical Feasibility & Readiness',
        description:
          'Is the underlying technology sound and achievable? What is the current estimated Technology Readiness Level (TRL)? Does the team possess the capability to reach TRL 5-7 during incubation?',
        points: '10 points',
      },
      {
        subCriteria: 'Competitive Advantage & IP',
        description:
          'Does the technology offer a significant, defensible competitive advantage (e.g., unique algorithm, data-driven insights)? Is the technology protected or is there a clear plan for Intellectual Property (IP) protection?',
        points: '10 points',
      },
    ],
  },
];

function Form1Modal({ onClose }: { onClose: () => void }) {
  // Score state for each sub-criteria (keyed by subCriteria name)
  const [scores, setScores] = useState<Record<string, string>>({});
  // Selection outcome
  const [selectionOutcome, setSelectionOutcome] = useState('');
  // Evaluators (3 rows)
  const [evaluators, setEvaluators] = useState([
    { name: '', role: '', date: '' },
    { name: '', role: '', date: '' },
    { name: '', role: '', date: '' },
  ]);

  const updateScore = (key: string, value: string) => {
    // Only allow numbers 0-max
    const num = value.replace(/[^0-9]/g, '');
    setScores(prev => ({ ...prev, [key]: num }));
  };

  const updateEvaluator = (index: number, field: 'name' | 'role' | 'date', value: string) => {
    setEvaluators(prev => prev.map((ev, i) => i === index ? { ...ev, [field]: value } : ev));
  };

  // Calculate total score
  const totalScore = Object.values(scores).reduce((sum, val) => sum + (parseInt(val) || 0), 0);

  const handlePrint = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    const content = document.getElementById('form1-print-area')?.innerHTML || '';
    printWindow.document.write(`
      <html>
        <head>
          <title>Form 1 - Startup Selection Criteria</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a; font-size: 12px; }
            h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
            h2 { font-size: 14px; margin-top: 16px; margin-bottom: 8px; }
            .subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 16px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
            th { background: #f5f5f5; font-weight: 600; }
            .checkbox { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 6px; vertical-align: middle; text-align: center; font-size: 10px; }
            .checked { background: #333; color: #fff; }
            ol { padding-left: 20px; }
            ol li { margin-bottom: 6px; }
            @media print { body { padding: 0; } }
          </style>
        </head>
        <body>${content}</body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  };

  const inputClass =
    'w-full px-2 py-1 bg-white border border-gray-200 rounded text-sm text-center focus:outline-none focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent';
  const evalInputClass =
    'w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent';

  useFormValues(1, () => ({
    scores,
    selectionOutcome,
    evaluators,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">Form 1 - Startup Selection Criteria</h2>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#E6275A] transition-colors"
            >
              <Printer className="w-4 h-4" />
              Print
            </button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Scrollable body */}
        <div className="flex-1 overflow-y-auto p-6">
          {/* Title */}
          <h1 className="text-xl font-bold text-center text-gray-900 mb-1">
            Startup Selection Criteria
          </h1>
          <p className="text-center text-sm text-gray-500 mb-6">
            MARIAN Technology Business Incubator
          </p>

          {/* Intro */}
          <p className="text-sm text-gray-700 mb-6 leading-relaxed">
            The MARIAN TBI prioritizes selecting startups with the highest potential for success, economic
            impact, and alignment with the mission of transforming the healthcare sector through ICT-based
            solutions. Applications are evaluated based on a comprehensive set of criteria:
          </p>

          {/* Criteria Table */}
          <div className="overflow-x-auto mb-8">
            <table className="w-full text-sm border-collapse border border-gray-300">
              <thead>
                <tr className="bg-gray-100">
                  <th className="border border-gray-300 px-3 py-2 text-left font-semibold w-[180px]">Category</th>
                  <th className="border border-gray-300 px-3 py-2 text-left font-semibold">Sub-Criteria &amp; MARIAN TBI Focus</th>
                  <th className="border border-gray-300 px-3 py-2 text-center font-semibold w-[90px]">Points</th>
                  <th className="border border-gray-300 px-3 py-2 text-center font-semibold w-[90px]">Score</th>
                </tr>
              </thead>
              <tbody>
                {FORM1_CRITERIA.map((cat) => (
                  <>
                    {/* Category header row */}
                    <tr key={`cat-${cat.category}`} className="bg-gray-50">
                      <td className="border border-gray-300 px-3 py-2 font-semibold text-gray-900" rowSpan={cat.rows.length + 1}>
                        {cat.category}
                      </td>
                      <td className="border border-gray-300 px-3 py-2 italic text-gray-600">
                        {cat.categoryDescription}
                      </td>
                      <td className="border border-gray-300 px-3 py-2 text-center font-semibold text-gray-900">
                        {cat.totalPoints}
                      </td>
                      <td className="border border-gray-300 px-3 py-2 text-center font-semibold text-[#FF2B5E]">
                        {cat.rows.reduce((sum, r) => sum + (parseInt(scores[r.subCriteria] || '0') || 0), 0) || ''}
                      </td>
                    </tr>
                    {/* Sub-criteria rows */}
                    {cat.rows.map((row) => (
                      <tr key={row.subCriteria}>
                        <td className="border border-gray-300 px-3 py-2">
                          <span className="font-medium text-gray-900">{row.subCriteria}</span>
                          <p className="mt-1 text-xs text-gray-500 leading-relaxed">{row.description}</p>
                        </td>
                        <td className="border border-gray-300 px-3 py-2 text-center text-gray-700">
                          ({row.points})
                        </td>
                        <td className="border border-gray-300 px-3 py-2 text-center">
                          <input data-field="scores"
                            type="text"
                            value={scores[row.subCriteria] || ''}
                            onChange={e => updateScore(row.subCriteria, e.target.value)}
                            placeholder="0"
                            className={inputClass}
                            style={{ width: '60px' }}
                          />
                        </td>
                      </tr>
                    ))}
                  </>
                ))}
                {/* Total row */}
                <tr className="bg-gray-100 font-bold">
                  <td className="border border-gray-300 px-3 py-2 text-right" colSpan={2}>
                    Total Score
                  </td>
                  <td className="border border-gray-300 px-3 py-2 text-center">100</td>
                  <td className="border border-gray-300 px-3 py-2 text-center text-[#FF2B5E] text-lg">
                    {totalScore || 0}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          {/* Evaluation Summary */}
          <h2 className="text-base font-bold text-gray-900 mb-3">Evaluation Summary</h2>
          <div className="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6 space-y-3">
            <div className="text-sm text-gray-700">
              <span className="font-semibold">Final Score Calculation</span>
              <div className="mt-1 space-y-1">
                <p>Total Points Possible: <span className="font-semibold">100 points</span></p>
                <p>Minimum Passing Score: <span className="font-semibold">70 points</span></p>
                <p>Your Total: <span className={`font-bold text-lg ${totalScore >= 70 ? 'text-green-600' : totalScore >= 55 ? 'text-yellow-600' : 'text-red-500'}`}>{totalScore} / 100</span></p>
              </div>
            </div>
            <div className="text-sm text-gray-700">
              <span className="font-semibold">Selection Outcome</span>
              <div className="mt-2 space-y-2">
                {[
                  { value: 'incubation', label: 'Recommended for Incubation:', desc: '(Score ≥ 70)' },
                  { value: 'pre-incubation', label: 'Recommended for Pre-Incubation:', desc: '(Score 55−69 and with high potential)' },
                  { value: 'not-recommended', label: 'Not Recommended:', desc: '(Score ≤ 54 or critical flaws identified)' },
                ].map(opt => (
                  <label key={opt.value} className="flex items-center gap-2 cursor-pointer group">
                    <input data-field="selectionOutcome"
                      type="radio"
                      name="selectionOutcome"
                      value={opt.value}
                      checked={selectionOutcome === opt.value}
                      onChange={e => setSelectionOutcome(e.target.value)}
                      className="accent-[#FF2B5E]"
                    />
                    <span className="group-hover:text-gray-900"><strong>{opt.label}</strong> {opt.desc}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>

          {/* Evaluation Process */}
          <h2 className="text-base font-bold text-gray-900 mb-3">Evaluation Process (For Reference)</h2>
          <ol className="list-decimal list-inside text-sm text-gray-700 space-y-2 mb-6">
            <li>
              <strong>Initial Screening</strong> – TBI Management screens applications for completeness and basic alignment with MARIAN TBI's mission (Health-Tech/ICT focus).
            </li>
            <li>
              <strong>Scoring &amp; Review</strong> – Applications are scored independently by a minimum of two (2) evaluators (TBI Manager, Technical Expert, Industry Mentor) using the 100-point criteria above.
            </li>
            <li>
              <strong>Pitch &amp; Interview</strong> – Shortlisted applicants undergo an interview and pitch session with the TBI Selection Committee to validate the scores and assess team cohesion.
            </li>
            <li>
              <strong>Final Recommendation</strong> – The TBI Manager consolidates all scores and committee feedback for final approval.
            </li>
          </ol>

          {/* Evaluated By */}
          <h2 className="text-base font-bold text-gray-900 mb-3">Evaluated by:</h2>
          <div className="overflow-x-auto mb-4">
            <table className="w-full text-sm border-collapse border border-gray-300">
              <thead>
                <tr className="bg-gray-100">
                  <th className="border border-gray-300 px-3 py-2 text-left font-semibold">Evaluator Name</th>
                  <th className="border border-gray-300 px-3 py-2 text-left font-semibold">Role/Expertise</th>
                  <th className="border border-gray-300 px-3 py-2 text-center font-semibold">Signature</th>
                  <th className="border border-gray-300 px-3 py-2 text-center font-semibold w-[130px]">Date</th>
                </tr>
              </thead>
              <tbody>
                {evaluators.map((ev, i) => (
                  <tr key={i}>
                    <td className="border border-gray-300 px-2 py-2">
                      <input data-field="evaluators"
                        type="text"
                        value={ev.name}
                        onChange={e => updateEvaluator(i, 'name', e.target.value)}
                        placeholder="Evaluator name"
                        className={evalInputClass}
                      />
                    </td>
                    <td className="border border-gray-300 px-2 py-2">
                      <input data-field="evaluators"
                        type="text"
                        value={ev.role}
                        onChange={e => updateEvaluator(i, 'role', e.target.value)}
                        placeholder="Role / Expertise"
                        className={evalInputClass}
                      />
                    </td>
                    <td className="border border-gray-300 px-3 py-2 text-center">
                      <p className="text-[10px] text-gray-400">(Signature Over Printed Name)</p>
                    </td>
                    <td className="border border-gray-300 px-2 py-2">
                      <input data-field="evaluators"
                        type="date"
                        value={ev.date}
                        onChange={e => updateEvaluator(i, 'date', e.target.value)}
                        className={evalInputClass}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Hidden print area */}
          <div id="form1-print-area" className="hidden">
            <h1>Startup Selection Criteria</h1>
            <p className="subtitle">MARIAN Technology Business Incubator</p>
            <p>The MARIAN TBI prioritizes selecting startups with the highest potential for success, economic impact, and alignment with the mission of transforming the healthcare sector through ICT-based solutions.</p>
            <table>
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Sub-Criteria &amp; MARIAN TBI Focus</th>
                  <th style={{ textAlign: 'center' }}>Points</th>
                  <th style={{ textAlign: 'center' }}>Score</th>
                </tr>
              </thead>
              <tbody>
                {FORM1_CRITERIA.map((cat) => (
                  <>
                    <tr key={`p-cat-${cat.category}`}>
                      <td rowSpan={cat.rows.length + 1}><strong>{cat.category}</strong></td>
                      <td><em>{cat.categoryDescription}</em></td>
                      <td style={{ textAlign: 'center' }}><strong>{cat.totalPoints}</strong></td>
                      <td style={{ textAlign: 'center' }}><strong>{cat.rows.reduce((s, r) => s + (parseInt(scores[r.subCriteria] || '0') || 0), 0) || ''}</strong></td>
                    </tr>
                    {cat.rows.map((row) => (
                      <tr key={`p-${row.subCriteria}`}>
                        <td><strong>{row.subCriteria}</strong><br /><span style={{ fontSize: '10px' }}>{row.description}</span></td>
                        <td style={{ textAlign: 'center' }}>({row.points})</td>
                        <td style={{ textAlign: 'center' }}>{scores[row.subCriteria] || ''}</td>
                      </tr>
                    ))}
                  </>
                ))}
                <tr>
                  <td colSpan={2} style={{ textAlign: 'right' }}><strong>Total Score</strong></td>
                  <td style={{ textAlign: 'center' }}><strong>100</strong></td>
                  <td style={{ textAlign: 'center' }}><strong>{totalScore}</strong></td>
                </tr>
              </tbody>
            </table>
            <h2>Evaluation Summary</h2>
            <p>Total Points Possible: 100 &nbsp;|&nbsp; Minimum Passing Score: 70 &nbsp;|&nbsp; Your Total: <strong>{totalScore}</strong></p>
            <p>Selection Outcome:</p>
            <div><span className={`checkbox ${selectionOutcome === 'incubation' ? 'checked' : ''}`}>{selectionOutcome === 'incubation' ? '✓' : ' '}</span> <strong>Recommended for Incubation</strong> (Score ≥ 70)</div>
            <div><span className={`checkbox ${selectionOutcome === 'pre-incubation' ? 'checked' : ''}`}>{selectionOutcome === 'pre-incubation' ? '✓' : ' '}</span> <strong>Recommended for Pre-Incubation</strong> (Score 55−69)</div>
            <div><span className={`checkbox ${selectionOutcome === 'not-recommended' ? 'checked' : ''}`}>{selectionOutcome === 'not-recommended' ? '✓' : ' '}</span> <strong>Not Recommended</strong> (Score ≤ 54)</div>
            <h2>Evaluated by:</h2>
            <table>
              <thead>
                <tr><th>Evaluator Name</th><th>Role/Expertise</th><th>Signature</th><th>Date</th></tr>
              </thead>
              <tbody>
                {evaluators.map((ev, i) => (
                  <tr key={`p-ev-${i}`}>
                    <td>{ev.name || ' '}</td>
                    <td>{ev.role || ' '}</td>
                    <td style={{ textAlign: 'center' }}><span style={{ fontSize: '10px' }}>(Signature Over Printed Name)</span></td>
                    <td>{ev.date || ' '}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-200 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 2 – Application Form and Innovation Profile
// ──────────────────────────────────────────────

const SECTOR_OPTIONS = [
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

const TRL_OPTIONS = [
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

const COMMERCIALIZATION_OPTIONS = ['Startup', 'Spinoff', 'Licensing', 'Undecided Yet'];

const IP_STATUS_OPTIONS = [
  'None',
  'Patent/Trademark Pending',
  'Granted/Registered',
  'Protected by NDA/Trade Secret',
];

const FUNDING_OPTIONS = [
  'Self-funded',
  'Applied for Grant',
  'Received Grant',
  'Seed Funding',
  'No Funding yet',
];

const FORM2_TOTAL_STEPS = 7;

function Form2Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  // Applicant & Team Info
  const [dateOfApplication, setDateOfApplication] = useState('');
  const [applicantName, setApplicantName] = useState('');
  const [uicAffiliation, setUicAffiliation] = useState('');
  const [address, setAddress] = useState('');
  const [mobileNumber, setMobileNumber] = useState('');
  const [emailAddress, setEmailAddress] = useState('');
  const [biography, setBiography] = useState('');
  const [keyExperience, setKeyExperience] = useState('');
  const [sectorInterest, setSectorInterest] = useState('');
  // Your Organization
  const [startupName, setStartupName] = useState('');
  const [orgWebsite, setOrgWebsite] = useState('');
  const [companyMembers, setCompanyMembers] = useState('');
  // Innovation Profile
  const [innovationTitle, setInnovationTitle] = useState('');
  const [firstSector, setFirstSector] = useState('');
  const [secondSector, setSecondSector] = useState('');
  const [theProblem, setTheProblem] = useState('');
  const [theSolution, setTheSolution] = useState('');
  const [keyBenefits, setKeyBenefits] = useState('');
  const [targetMarket, setTargetMarket] = useState('');
  // Market & Development
  const [trlStatus, setTrlStatus] = useState('');
  const [commercializationRoute, setCommercializationRoute] = useState('');
  const [ipStatus, setIpStatus] = useState('');
  const [fundingStatus, setFundingStatus] = useState('');
  const [revenueModel, setRevenueModel] = useState('');
  const [searchKeywords, setSearchKeywords] = useState('');
  const [innovationDevelopment, setInnovationDevelopment] = useState('');
  // TBI Goals
  const [tbiGoal, setTbiGoal] = useState('');
  const [tbiAssistance, setTbiAssistance] = useState('');
  const [sdgs, setSdgs] = useState('');
  // Media
  const [mediaUrl1, setMediaUrl1] = useState('');
  const [mediaUrl2, setMediaUrl2] = useState('');

  const handlePrint = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    const content = document.getElementById('form2-print-area')?.innerHTML || '';
    printWindow.document.write(`
      <html>
        <head>
          <title>Form 2 - Application Form and Innovation Profile</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a; font-size: 12px; }
            h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
            h2 { font-size: 14px; margin-top: 18px; margin-bottom: 10px; color: #9b1b5a; border-bottom: 2px solid #9b1b5a; padding-bottom: 4px; }
            .subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 16px; }
            .field { margin-bottom: 12px; }
            .field-label { font-weight: 600; margin-bottom: 2px; }
            .field-value { border-bottom: 1px solid #999; min-height: 20px; padding: 2px 0; }
            .privacy { font-size: 11px; color: #555; margin-bottom: 16px; line-height: 1.5; }
            @media print { body { padding: 0; } }
          </style>
        </head>
        <body>${content}</body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  };

  const inputClass =
    'w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent';
  const selectClass =
    'w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent appearance-none';
  const textareaClass =
    'w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent resize-none';
  const sectionHeaderClass =
    'text-white text-sm font-semibold px-4 py-2 rounded-lg mb-4';

  const renderStep = () => {
    switch (step) {
      // ── Step 0: Privacy Notice ──────────────────
      case 0:
        return (
          <div className="space-y-4">
            <h3 className="text-lg font-bold text-gray-900">Application Form and Innovation Profile</h3>
            <p className="text-sm text-gray-600 leading-relaxed">
              MARIAN TBI is committed to protecting your privacy. We take all necessary steps to safeguard your personal information. This includes processing it manually or electronically, only with your consent, as required by law, or upon your request. We may share your information within our community for essential operational purposes, but will always obtain your written consent before sharing it with anyone outside.
            </p>
            <p className="text-sm text-gray-600 leading-relaxed">
              As a data subject under the Data Privacy Act (Republic Act No. 10173, Section 16), you have certain rights. These include the right to be informed, to object, to access, rectify, erase or block your data, ensure data portability, file a complaint, and seek compensation for damages. To update your information, you can message us on Facebook at www.facebook.com/mariantbi or email us at mariantbi@uic.edu.ph
            </p>
          </div>
        );

      // ── Step 1: Applicant and Team Information ──
      case 1:
        return (
          <div className="space-y-4">
            <div className={sectionHeaderClass} style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
              Applicant and Team Information
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date of Application <span className="text-red-500">*</span></label>
              <input data-field="dateOfApplication" type="date" value={dateOfApplication} onChange={e => setDateOfApplication(e.target.value)} className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Name of Applicant (Last Name, First Name, Middle Name) <span className="text-red-500">*</span></label>
              <input data-field="applicantName" type="text" value={applicantName} onChange={e => setApplicantName(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">UIC Affiliation (if any)</label>
              <input data-field="uicAffiliation" type="text" value={uicAffiliation} onChange={e => setUicAffiliation(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Address <span className="text-red-500">*</span></label>
              <input data-field="address" type="text" value={address} onChange={e => setAddress(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span className="text-red-500">*</span></label>
              <input data-field="mobileNumber" type="text" value={mobileNumber} onChange={e => setMobileNumber(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email Address <span className="text-red-500">*</span></label>
              <input data-field="emailAddress" type="email" value={emailAddress} onChange={e => setEmailAddress(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Biography (at most 150 words – stating your current education, employment, recognition and awards, and any connection to UIC) <span className="text-red-500">*</span></label>
              <textarea data-field="biography" rows={3} value={biography} onChange={e => setBiography(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Key Experience / Expertise to share (List top 3-5 relevant skills, expertise, or hobbies) <span className="text-red-500">*</span></label>
              <textarea data-field="keyExperience" rows={2} value={keyExperience} onChange={e => setKeyExperience(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">I am broadly interested in the following sectors: (ex. robotics, electronics, agriculture, software) <span className="text-red-500">*</span></label>
              <input data-field="sectorInterest" type="text" value={sectorInterest} onChange={e => setSectorInterest(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
          </div>
        );

      // ── Step 2: Your Organization ──────────────
      case 2:
        return (
          <div className="space-y-4">
            <div className={sectionHeaderClass} style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
              Your Organization
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Proposed / Working Name of your Startup/Company <span className="text-red-500">*</span></label>
              <input data-field="startupName" type="text" value={startupName} onChange={e => setStartupName(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Organization Website (if any) <span className="text-red-500">*</span></label>
              <input data-field="orgWebsite" type="text" value={orgWebsite} onChange={e => setOrgWebsite(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Company Members (Other than the applicant) <span className="text-red-500">*</span></label>
              <p className="text-xs text-gray-500 mb-1">(Name, Email Address, Role/Description)</p>
              <textarea data-field="companyMembers" rows={4} value={companyMembers} onChange={e => setCompanyMembers(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
          </div>
        );

      // ── Step 3: Innovation Profile ─────────────
      case 3:
        return (
          <div className="space-y-4">
            <div className={sectionHeaderClass} style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
              Innovation Profile
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Title of your Innovation (one-sentence descriptive title) <span className="text-red-500">*</span></label>
              <input data-field="innovationTitle" type="text" value={innovationTitle} onChange={e => setInnovationTitle(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">First Relevant Sector <span className="text-red-500">*</span></label>
              <select data-field="firstSector" value={firstSector} onChange={e => setFirstSector(e.target.value)} className={selectClass}>
                <option value="">Choose</option>
                {SECTOR_OPTIONS.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Second Relevant Sector <span className="text-red-500">*</span></label>
              <select data-field="secondSector" value={secondSector} onChange={e => setSecondSector(e.target.value)} className={selectClass}>
                <option value="">Choose</option>
                {SECTOR_OPTIONS.map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">The Problem (at most 200 words – What problem are you solving? Focus on the specific problem in the local/regional context, if applicable.) <span className="text-red-500">*</span></label>
              <textarea data-field="theProblem" rows={4} value={theProblem} onChange={e => setTheProblem(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">The Solution (at most 200 words – Describe what your innovation/company and how it addresses the problem?) <span className="text-red-500">*</span></label>
              <textarea data-field="theSolution" rows={4} value={theSolution} onChange={e => setTheSolution(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Key benefits or competitive advantage (List 1-4 benefits to your users over your competitors) <span className="text-red-500">*</span></label>
              <textarea data-field="keyBenefits" rows={3} value={keyBenefits} onChange={e => setKeyBenefits(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Target Market (In 1-2 sentences, provide details of the target market(s) and ideal customers) <span className="text-red-500">*</span></label>
              <textarea data-field="targetMarket" rows={2} value={targetMarket} onChange={e => setTargetMarket(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
          </div>
        );

      // ── Step 4: Market and Development Status ──
      case 4:
        return (
          <div className="space-y-4">
            <div className={sectionHeaderClass} style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
              Market and Development Status
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Status (TRL) <span className="text-red-500">*</span></label>
              <select data-field="trlStatus" value={trlStatus} onChange={e => setTrlStatus(e.target.value)} className={selectClass}>
                <option value="">Choose</option>
                {TRL_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Commercialization Route <span className="text-red-500">*</span></label>
              <select data-field="commercializationRoute" value={commercializationRoute} onChange={e => setCommercializationRoute(e.target.value)} className={selectClass}>
                <option value="">Choose</option>
                {COMMERCIALIZATION_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Intellectual Property (IP) Status <span className="text-red-500">*</span></label>
              <select data-field="ipStatus" value={ipStatus} onChange={e => setIpStatus(e.target.value)} className={selectClass}>
                <option value="">Choose</option>
                {IP_STATUS_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Funding Status <span className="text-red-500">*</span></label>
              <select data-field="fundingStatus" value={fundingStatus} onChange={e => setFundingStatus(e.target.value)} className={selectClass}>
                <option value="">Choose</option>
                {FUNDING_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Revenue Model (In 1-2 sentences, how will your innovation generate revenue?) <span className="text-red-500">*</span></label>
              <textarea data-field="revenueModel" rows={2} value={revenueModel} onChange={e => setRevenueModel(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Search Keywords (At least 3 words that are related to your innovation) <span className="text-red-500">*</span></label>
              <input data-field="searchKeywords" type="text" value={searchKeywords} onChange={e => setSearchKeywords(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Innovation/Company Development (In 1-2 sentences, provide details of the current development and next milestone.) <span className="text-red-500">*</span></label>
              <textarea data-field="innovationDevelopment" rows={2} value={innovationDevelopment} onChange={e => setInnovationDevelopment(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
          </div>
        );

      // ── Step 5: TBI Goals and Alignment ────────
      case 5:
        return (
          <div className="space-y-4">
            <div className={sectionHeaderClass} style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
              TBI Goals and Alignment
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">What is the single most important goal you wish to achieve during the MARIAN TBI Incubation Program? (at 50 words) <span className="text-red-500">*</span></label>
              <textarea data-field="tbiGoal" rows={3} value={tbiGoal} onChange={e => setTbiGoal(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">What specific assistance are you looking for from MARIAN TBI? (e.g., technical mentoring, business planning, funding assistance, prototyping facilities, networking) <span className="text-red-500">*</span></label>
              <textarea data-field="tbiAssistance" rows={3} value={tbiAssistance} onChange={e => setTbiAssistance(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">UN Sustainable Development Goals (SDGs): Choose up to three that your innovation addresses (List of 17 SDGs) <span className="text-red-500">*</span></label>
              <textarea data-field="sdgs" rows={2} value={sdgs} onChange={e => setSdgs(e.target.value)} placeholder="Your answer" className={textareaClass} />
            </div>
          </div>
        );

      // ── Step 6: Media & Publications ───────────
      case 6:
        return (
          <div className="space-y-4">
            <div className={sectionHeaderClass} style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
              Media &amp; Publications about your innovation or company including awards and recognition
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Description and URL <span className="text-red-500">*</span></label>
              <input data-field="mediaUrl1" type="text" value={mediaUrl1} onChange={e => setMediaUrl1(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Description and URL <span className="text-red-500">*</span></label>
              <input data-field="mediaUrl2" type="text" value={mediaUrl2} onChange={e => setMediaUrl2(e.target.value)} placeholder="Your answer" className={inputClass} />
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  const stepLabels = [
    'Privacy Notice',
    'Applicant & Team',
    'Organization',
    'Innovation Profile',
    'Market & Development',
    'TBI Goals',
    'Media & Publications',
  ];

  useFormValues(2, () => ({
    dateOfApplication,
    applicantName,
    uicAffiliation,
    address,
    mobileNumber,
    emailAddress,
    biography,
    keyExperience,
    sectorInterest,
    startupName,
    orgWebsite,
    companyMembers,
    innovationTitle,
    firstSector,
    secondSector,
    theProblem,
    theSolution,
    keyBenefits,
    targetMarket,
    trlStatus,
    commercializationRoute,
    ipStatus,
    fundingStatus,
    revenueModel,
    searchKeywords,
    innovationDevelopment,
    tbiGoal,
    tbiAssistance,
    sdgs,
    mediaUrl1,
    mediaUrl2,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Form 2 - Application Form and Innovation Profile</h2>
            <p className="text-xs text-gray-500 mt-0.5">Step {step + 1} of {FORM2_TOTAL_STEPS}: {stepLabels[step]}</p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#E6275A] transition-colors"
            >
              <Printer className="w-4 h-4" />
              Print
            </button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Step indicator */}
        <div className="px-6 pt-4 pb-2 flex gap-1">
          {Array.from({ length: FORM2_TOTAL_STEPS }).map((_, i) => (
            <button
              key={i}
              onClick={() => setStep(i)}
              className={`flex-1 h-1.5 rounded-full transition-colors ${i <= step ? 'bg-[#FF2B5E]' : 'bg-gray-200'}`}
              title={stepLabels[i]}
            />
          ))}
        </div>

        {/* Scrollable body */}
        <div className="flex-1 overflow-y-auto p-6">
          {renderStep()}

          {/* Hidden print area containing all fields */}
          <div id="form2-print-area" className="hidden">
            <h1>Application Form and Innovation Profile</h1>
            <p className="subtitle">MARIAN Technology Business Incubator</p>
            <p className="privacy">
              MARIAN TBI is committed to protecting your privacy. We take all necessary steps to safeguard your personal information. This includes processing it manually or electronically, only with your consent, as required by law, or upon your request.
            </p>
            <h2>Applicant and Team Information</h2>
            <div className="field"><div className="field-label">Date of Application:</div><div className="field-value">{dateOfApplication || ' '}</div></div>
            <div className="field"><div className="field-label">Name of Applicant:</div><div className="field-value">{applicantName || ' '}</div></div>
            <div className="field"><div className="field-label">UIC Affiliation:</div><div className="field-value">{uicAffiliation || ' '}</div></div>
            <div className="field"><div className="field-label">Address:</div><div className="field-value">{address || ' '}</div></div>
            <div className="field"><div className="field-label">Mobile Number:</div><div className="field-value">{mobileNumber || ' '}</div></div>
            <div className="field"><div className="field-label">Email Address:</div><div className="field-value">{emailAddress || ' '}</div></div>
            <div className="field"><div className="field-label">Biography:</div><div className="field-value">{biography || ' '}</div></div>
            <div className="field"><div className="field-label">Key Experience / Expertise:</div><div className="field-value">{keyExperience || ' '}</div></div>
            <div className="field"><div className="field-label">Sectors of Interest:</div><div className="field-value">{sectorInterest || ' '}</div></div>
            <h2>Your Organization</h2>
            <div className="field"><div className="field-label">Startup/Company Name:</div><div className="field-value">{startupName || ' '}</div></div>
            <div className="field"><div className="field-label">Organization Website:</div><div className="field-value">{orgWebsite || ' '}</div></div>
            <div className="field"><div className="field-label">Company Members:</div><div className="field-value">{companyMembers || ' '}</div></div>
            <h2>Innovation Profile</h2>
            <div className="field"><div className="field-label">Innovation Title:</div><div className="field-value">{innovationTitle || ' '}</div></div>
            <div className="field"><div className="field-label">First Relevant Sector:</div><div className="field-value">{firstSector || ' '}</div></div>
            <div className="field"><div className="field-label">Second Relevant Sector:</div><div className="field-value">{secondSector || ' '}</div></div>
            <div className="field"><div className="field-label">The Problem:</div><div className="field-value">{theProblem || ' '}</div></div>
            <div className="field"><div className="field-label">The Solution:</div><div className="field-value">{theSolution || ' '}</div></div>
            <div className="field"><div className="field-label">Key Benefits:</div><div className="field-value">{keyBenefits || ' '}</div></div>
            <div className="field"><div className="field-label">Target Market:</div><div className="field-value">{targetMarket || ' '}</div></div>
            <h2>Market and Development Status</h2>
            <div className="field"><div className="field-label">Status (TRL):</div><div className="field-value">{trlStatus || ' '}</div></div>
            <div className="field"><div className="field-label">Commercialization Route:</div><div className="field-value">{commercializationRoute || ' '}</div></div>
            <div className="field"><div className="field-label">IP Status:</div><div className="field-value">{ipStatus || ' '}</div></div>
            <div className="field"><div className="field-label">Funding Status:</div><div className="field-value">{fundingStatus || ' '}</div></div>
            <div className="field"><div className="field-label">Revenue Model:</div><div className="field-value">{revenueModel || ' '}</div></div>
            <div className="field"><div className="field-label">Search Keywords:</div><div className="field-value">{searchKeywords || ' '}</div></div>
            <div className="field"><div className="field-label">Innovation Development:</div><div className="field-value">{innovationDevelopment || ' '}</div></div>
            <h2>TBI Goals and Alignment</h2>
            <div className="field"><div className="field-label">Most Important Goal:</div><div className="field-value">{tbiGoal || ' '}</div></div>
            <div className="field"><div className="field-label">Specific Assistance:</div><div className="field-value">{tbiAssistance || ' '}</div></div>
            <div className="field"><div className="field-label">SDGs:</div><div className="field-value">{sdgs || ' '}</div></div>
            <h2>Media &amp; Publications</h2>
            <div className="field"><div className="field-label">Description and URL (1):</div><div className="field-value">{mediaUrl1 || ' '}</div></div>
            <div className="field"><div className="field-label">Description and URL (2):</div><div className="field-value">{mediaUrl2 || ' '}</div></div>
          </div>
        </div>

        {/* Navigation footer */}
        <div className="p-4 border-t border-gray-200 flex items-center justify-between">
          <button
            onClick={() => setStep(Math.max(0, step - 1))}
            disabled={step === 0}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${
              step === 0
                ? 'text-gray-300 cursor-not-allowed'
                : 'text-[#FF2B5E] hover:bg-[#FF2B5E]/10'
            }`}
          >
            <ChevronLeft className="w-4 h-4" />
            Back
          </button>
          <span className="text-xs text-gray-400">
            {step + 1} / {FORM2_TOTAL_STEPS}
          </span>
          {step < FORM2_TOTAL_STEPS - 1 ? (
            <button
              onClick={() => setStep(step + 1)}
              className="flex items-center gap-1 px-4 py-2 text-sm text-white bg-[#FF2B5E] rounded-lg hover:bg-[#E6275A] transition-colors"
            >
              Next
              <ChevronRight className="w-4 h-4" />
            </button>
          ) : (
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
            >
              Close
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 3 – M-TSA Intent To Incubate Form
// ──────────────────────────────────────────────

function Form3Modal({ onClose }: { onClose: () => void }) {
  // 1. Team Info
  const [teamLeadName, setTeamLeadName] = useState('');
  const [courseYear, setCourseYear] = useState('');
  const [emailMobile, setEmailMobile] = useState('');
  const [coFounder1, setCoFounder1] = useState('');
  const [coFounder2, setCoFounder2] = useState('');
  const [coFounder3, setCoFounder3] = useState('');
  // 2. Project Snapshot
  const [thesisTitle, setThesisTitle] = useState('');
  const [thesisAdvisor, setThesisAdvisor] = useState('');
  const [projectStage, setProjectStage] = useState('');
  // 3. The Vision
  const [whoHasProblem, setWhoHasProblem] = useState('');
  const [whyBetter, setWhyBetter] = useState('');
  const [whyJoin, setWhyJoin] = useState<string[]>([]);
  // 4. Required Attachment
  const [hasThesisAbstract, setHasThesisAbstract] = useState(false);
  // 5. Commitment
  const [commitAgreed, setCommitAgreed] = useState(false);
  // Received
  const [receivedBy, setReceivedBy] = useState('');
  const [receivedDate, setReceivedDate] = useState('');

  const toggleWhyJoin = (val: string) => {
    setWhyJoin(prev => prev.includes(val) ? prev.filter(v => v !== val) : [...prev, val]);
  };

  const handlePrint = () => {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    const content = document.getElementById('form3-print-area')?.innerHTML || '';
    printWindow.document.write(`
      <html>
        <head>
          <title>Form 3 - M-TSA Intent To Incubate Form</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 24px; color: #1a1a1a; font-size: 12px; }
            h1 { font-size: 18px; text-align: center; margin-bottom: 16px; }
            h2 { font-size: 14px; margin-top: 18px; margin-bottom: 10px; }
            .field { margin-bottom: 10px; }
            .field-label { font-weight: 600; display: inline; }
            .field-value { border-bottom: 1px solid #999; min-width: 200px; display: inline-block; padding: 2px 4px; }
            .checkbox { display: inline-block; width: 12px; height: 12px; border: 1px solid #333; margin-right: 6px; vertical-align: middle; text-align: center; font-size: 10px; }
            .checked { background: #333; color: #fff; }
            .sig-line { border-bottom: 1px solid #333; width: 250px; display: inline-block; height: 1em; margin-top: 40px; }
            @media print { body { padding: 0; } }
          </style>
        </head>
        <body>${content}</body>
      </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
  };

  const inputClass =
    'w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent';
  const sectionHeaderClass =
    'text-sm font-bold text-gray-900 mb-3 pb-1 border-b-2 border-[#FF2B5E]';

  useFormValues(3, () => ({
    teamLeadName,
    courseYear,
    emailMobile,
    coFounder1,
    coFounder2,
    coFounder3,
    thesisTitle,
    thesisAdvisor,
    projectStage,
    whoHasProblem,
    whyBetter,
    whyJoin,
    hasThesisAbstract,
    commitAgreed,
    receivedBy,
    receivedDate,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">Form 3 - M-TSA Intent To Incubate Form</h2>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#E6275A] transition-colors"
            >
              <Printer className="w-4 h-4" />
              Print
            </button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Scrollable body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* 1. TEAM INFORMATION */}
          <div>
            <h3 className={sectionHeaderClass}>1. TEAM INFORMATION</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Team Lead Name <span className="text-red-500">*</span></label>
                <input data-field="teamLeadName" type="text" value={teamLeadName} onChange={e => setTeamLeadName(e.target.value)} placeholder="Enter team lead name" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Course / Year <span className="text-red-500">*</span></label>
                <input data-field="courseYear" type="text" value={courseYear} onChange={e => setCourseYear(e.target.value)} placeholder="e.g., BSIT / 4th Year" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Email &amp; Mobile <span className="text-red-500">*</span></label>
                <input data-field="emailMobile" type="text" value={emailMobile} onChange={e => setEmailMobile(e.target.value)} placeholder="email@example.com / 09XX-XXX-XXXX" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Co-Founders / Members</label>
                <div className="space-y-2">
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-400 w-4">1.</span>
                    <input data-field="coFounder1" type="text" value={coFounder1} onChange={e => setCoFounder1(e.target.value)} placeholder="Name" className={inputClass} />
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-400 w-4">2.</span>
                    <input data-field="coFounder2" type="text" value={coFounder2} onChange={e => setCoFounder2(e.target.value)} placeholder="Name" className={inputClass} />
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-gray-400 w-4">3.</span>
                    <input data-field="coFounder3" type="text" value={coFounder3} onChange={e => setCoFounder3(e.target.value)} placeholder="Name" className={inputClass} />
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* 2. PROJECT SNAPSHOT */}
          <div>
            <h3 className={sectionHeaderClass}>2. PROJECT SNAPSHOT</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Thesis Title <span className="text-red-500">*</span></label>
                <input data-field="thesisTitle" type="text" value={thesisTitle} onChange={e => setThesisTitle(e.target.value)} placeholder="Enter thesis title" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Thesis Advisor <span className="text-red-500">*</span></label>
                <input data-field="thesisAdvisor" type="text" value={thesisAdvisor} onChange={e => setThesisAdvisor(e.target.value)} placeholder="Enter thesis advisor name" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Where is the project right now? <span className="text-red-500">*</span></label>
                <div className="space-y-2">
                  {[
                    { value: 'concept', label: 'Concept Only: We have the math/theory, but no physical model yet.' },
                    { value: 'rough', label: 'Rough Prototype: We built it, but it\'s messy and needs fixing.' },
                    { value: 'lab', label: 'Lab Tested (TRL 4): It works in the lab under controlled conditions.' },
                  ].map(opt => (
                    <label key={opt.value} className="flex items-start gap-2 cursor-pointer group">
                      <input data-field="projectStage"
                        type="radio"
                        name="projectStage"
                        value={opt.value}
                        checked={projectStage === opt.value}
                        onChange={e => setProjectStage(e.target.value)}
                        className="mt-1 accent-[#FF2B5E]"
                      />
                      <span className="text-sm text-gray-700 group-hover:text-gray-900">{opt.label}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* 3. THE VISION */}
          <div>
            <h3 className={sectionHeaderClass}>3. THE VISION (Short answers only)</h3>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Who has the problem you are solving? <span className="text-red-500">*</span></label>
                <p className="text-xs text-gray-400 mb-1">e.g., Rice farmers, Call center agents, Medical students</p>
                <input data-field="whoHasProblem" type="text" value={whoHasProblem} onChange={e => setWhoHasProblem(e.target.value)} placeholder="Your answer" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Why is your solution better than what they use now? <span className="text-red-500">*</span></label>
                <p className="text-xs text-gray-400 mb-1">e.g., It's cheaper, faster, or eco-friendly</p>
                <input data-field="whyBetter" type="text" value={whyBetter} onChange={e => setWhyBetter(e.target.value)} placeholder="Your answer" className={inputClass} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Why do you want to join M-TSA? <span className="text-red-500">*</span></label>
                <div className="space-y-2">
                  {[
                    { value: 'commercialize', label: 'To commercialize/sell this product.' },
                    { value: 'patent', label: 'To get a patent/IP protection.' },
                    { value: 'improve', label: 'To improve the prototype.' },
                  ].map(opt => (
                    <label key={opt.value} className="flex items-center gap-2 cursor-pointer group">
                      <input data-field="whyJoin"
                        type="checkbox"
                        checked={whyJoin.includes(opt.value)}
                        onChange={() => toggleWhyJoin(opt.value)}
                        className="accent-[#FF2B5E]"
                      />
                      <span className="text-sm text-gray-700 group-hover:text-gray-900">{opt.label}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* 4. REQUIRED ATTACHMENT */}
          <div>
            <h3 className={sectionHeaderClass}>4. REQUIRED ATTACHMENT</h3>
            <label className="flex items-center gap-2 cursor-pointer group">
              <input data-field="hasThesisAbstract"
                type="checkbox"
                checked={hasThesisAbstract}
                onChange={e => setHasThesisAbstract(e.target.checked)}
                className="accent-[#FF2B5E]"
              />
              <span className="text-sm text-gray-700 group-hover:text-gray-900">Thesis Abstract (Print or Digital Copy)</span>
            </label>
          </div>

          {/* 5. COMMITMENT */}
          <div>
            <h3 className={sectionHeaderClass}>5. COMMITMENT</h3>
            <label className="flex items-start gap-2 cursor-pointer group">
              <input data-field="commitAgreed"
                type="checkbox"
                checked={commitAgreed}
                onChange={e => setCommitAgreed(e.target.checked)}
                className="mt-0.5 accent-[#FF2B5E]"
              />
              <span className="text-sm text-gray-700 group-hover:text-gray-900">
                I/We understand that building a product takes time and we are willing to attend coaching sessions.
              </span>
            </label>
          </div>

          {/* Received section */}
          <div className="border-t border-gray-200 pt-4 space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Received by</label>
              <input data-field="receivedBy" type="text" value={receivedBy} onChange={e => setReceivedBy(e.target.value)} placeholder="Name" className={inputClass} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
              <input data-field="receivedDate" type="date" value={receivedDate} onChange={e => setReceivedDate(e.target.value)} className={inputClass} />
            </div>
          </div>

          {/* Hidden print area */}
          <div id="form3-print-area" className="hidden">
            <h1>M-TSA INTENT TO INCUBATE FORM</h1>

            <h2>1. TEAM INFORMATION</h2>
            <div className="field"><span className="field-label">Team Lead Name: </span><span className="field-value">{teamLeadName || ' '}</span></div>
            <div className="field"><span className="field-label">Course / Year: </span><span className="field-value">{courseYear || ' '}</span></div>
            <div className="field"><span className="field-label">Email &amp; Mobile: </span><span className="field-value">{emailMobile || ' '}</span></div>
            <div className="field"><span className="field-label">Co-Founders / Members:</span></div>
            <div className="field">1. <span className="field-value">{coFounder1 || ' '}</span></div>
            <div className="field">2. <span className="field-value">{coFounder2 || ' '}</span></div>
            <div className="field">3. <span className="field-value">{coFounder3 || ' '}</span></div>

            <h2>2. PROJECT SNAPSHOT</h2>
            <div className="field"><span className="field-label">Thesis Title: </span><span className="field-value">{thesisTitle || ' '}</span></div>
            <div className="field"><span className="field-label">Thesis Advisor: </span><span className="field-value">{thesisAdvisor || ' '}</span></div>
            <div className="field">Where is the project right now?</div>
            <div className="field"><span className={`checkbox ${projectStage === 'concept' ? 'checked' : ''}`}>{projectStage === 'concept' ? '✓' : ' '}</span> Concept Only: We have the math/theory, but no physical model yet.</div>
            <div className="field"><span className={`checkbox ${projectStage === 'rough' ? 'checked' : ''}`}>{projectStage === 'rough' ? '✓' : ' '}</span> Rough Prototype: We built it, but it's messy and needs fixing.</div>
            <div className="field"><span className={`checkbox ${projectStage === 'lab' ? 'checked' : ''}`}>{projectStage === 'lab' ? '✓' : ' '}</span> Lab Tested (TRL 4): It works in the lab under controlled conditions.</div>

            <h2>3. THE VISION</h2>
            <div className="field"><span className="field-label">Who has the problem you are solving? </span><span className="field-value">{whoHasProblem || ' '}</span></div>
            <div className="field"><span className="field-label">Why is your solution better? </span><span className="field-value">{whyBetter || ' '}</span></div>
            <div className="field">Why do you want to join M-TSA?</div>
            <div className="field"><span className={`checkbox ${whyJoin.includes('commercialize') ? 'checked' : ''}`}>{whyJoin.includes('commercialize') ? '✓' : ' '}</span> To commercialize/sell this product.</div>
            <div className="field"><span className={`checkbox ${whyJoin.includes('patent') ? 'checked' : ''}`}>{whyJoin.includes('patent') ? '✓' : ' '}</span> To get a patent/IP protection.</div>
            <div className="field"><span className={`checkbox ${whyJoin.includes('improve') ? 'checked' : ''}`}>{whyJoin.includes('improve') ? '✓' : ' '}</span> To improve the prototype.</div>

            <h2>4. REQUIRED ATTACHMENT</h2>
            <div className="field"><span className={`checkbox ${hasThesisAbstract ? 'checked' : ''}`}>{hasThesisAbstract ? '✓' : ' '}</span> Thesis Abstract (Print or Digital Copy)</div>

            <h2>5. COMMITMENT</h2>
            <div className="field"><span className={`checkbox ${commitAgreed ? 'checked' : ''}`}>{commitAgreed ? '✓' : ' '}</span> I/We understand that building a product takes time and we are willing to attend coaching sessions.</div>

            <br /><br />
            <div style={{ textAlign: 'center' }}>
              <span className="sig-line">&nbsp;</span>
              <br />
              <span style={{ fontSize: '11px' }}>Signature of Team Lead</span>
            </div>
            <br />
            <div className="field"><span className="field-label">Received: </span><span className="field-value">{receivedBy || ' '}</span></div>
            <div className="field"><span className="field-label">Date: </span><span className="field-value">{receivedDate || ' '}</span></div>
          </div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-200 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 7 – Training Needs Assessment
// ──────────────────────────────────────────────
const TRAINING_AREAS = [
  {
    title: '1. Strategy & Business Model',
    items: [
      'Business Model Validation (e.g., Lean Startup, Pivoting)',
      'Business Planning (Comprehensive Strategy)',
      'Scaling and Growth Strategy',
      'Market Research and Analysis',
    ],
  },
  {
    title: '2. Product & Technology Development',
    items: [
      'Product Roadmapping & Strategy',
      'Agile/Scrum Project Management',
      'UI/UX Design and Testing',
      'Prototyping and Minimum Viable Product (MVP) Development',
    ],
  },
  {
    title: '3. Funding Financials',
    items: [
      'Financial Modeling & Projection (for Startups)',
      'Fundraising Strategy (Seed/Series A)',
      'Investor Relations and Due Diligence',
      'Grant Writing & Management',
    ],
  },
  {
    title: '4. Legal & Compliance',
    items: [
      'Intellectual Property Rights (IPR) Protection (Patents, Trademarks)',
      'Legal Entity Setup & Governance (Corporation, etc.)',
      'Data Privacy and Security Compliance',
    ],
  },
  {
    title: '5. Soft Skills Management',
    items: [
      'Team Building & Culture',
      'Effective Communication & Negotiation',
      'Mentorship Engagement & Utilization',
      'Technology Transfer & Licensing (From UIC Research)',
    ],
  },
  {
    title: '6. Marketing & Sales',
    items: [
      'Digital Marketing (SEO/SEM, Social Media)',
      'Value Proposition Design',
      'Sales Strategy and Customer Acquisition',
      'Pitching & Presentation Skills (Investor Deck)',
    ],
  },
];

function Form7Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0); // 0 = intro, 1 = training areas
  const [startupName, setStartupName] = useState('');
  const [members, setMembers] = useState('');
  // ratings: { "catIndex-itemIndex": number }
  const [ratings, setRatings] = useState<Record<string, number>>({});
  const [otherSkills, setOtherSkills] = useState('');

  const setRating = (catIdx: number, itemIdx: number, value: number) => {
    setRatings(prev => ({ ...prev, [`${catIdx}-${itemIdx}`]: value }));
  };

  const handlePrint = () => {
    const printArea = document.getElementById('form7-print-area');
    if (!printArea) return;
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><title>Form 7 - Training Needs Assessment</title><style>
      body { font-family: Arial, sans-serif; font-size: 12px; padding: 30px; color: #222; }
      h1 { font-size: 18px; margin-bottom: 4px; }
      h2 { font-size: 14px; margin-top: 18px; margin-bottom: 6px; background: #9b1b5a; color: #fff; padding: 4px 8px; border-radius: 4px; }
      .info { margin: 8px 0; }
      .info span { font-weight: bold; }
      .scale { margin: 8px 0 16px; font-size: 11px; line-height: 1.5; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
      th, td { border: 1px solid #bbb; padding: 5px 8px; text-align: center; font-size: 11px; }
      th { background: #f3f3f3; }
      td:first-child { text-align: left; width: 50%; }
      .other { margin-top: 12px; }
      .other-label { font-weight: bold; margin-bottom: 4px; }
      .other-value { border-bottom: 1px solid #333; min-height: 20px; padding: 2px 0; }
      @media print { body { padding: 15px; } }
    </style></head><body>` + printArea.innerHTML + '</body></html>');
    w.document.close();
    w.print();
  };

  useFormValues(7, () => ({
    startupName,
    members,
    ratings,
    otherSkills,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Form 7 - Training Needs Assessment</h2>
            <p className="text-xs text-gray-500 mt-0.5">Step {step + 1} of 2</p>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={handlePrint} className="text-gray-500 hover:text-[#FF2B5E] p-2" title="Print">
              <Printer className="w-5 h-5" />
            </button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1">
          <div className="bg-[#FF2B5E] h-1 transition-all duration-300" style={{ width: `${((step + 1) / 2) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {step === 0 && (
            <>
              {/* Instructions */}
              <div className="bg-gray-50 rounded-xl p-5 space-y-3">
                <p className="text-sm text-gray-700"><strong>Instructions:</strong> Describe your level of experience in the following areas using the provided scale.</p>
                <div className="text-sm text-gray-600 space-y-1">
                  <p className="font-semibold">Rating Scale:</p>
                  <p>5 - Excellent (Expert; can mentor others)</p>
                  <p>4 - Satisfactory (Proficient; can perform tasks independently)</p>
                  <p>3 - Good (Basic competence; needs occasional guidance)</p>
                  <p>2 - Moderate (Some exposure; needs significant guidance)</p>
                  <p>1 - Poor (No experience or knowledge)</p>
                </div>
              </div>
              <p className="text-xs text-[#FF2B5E]">* Indicates required question</p>

              {/* Startup name */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Name of Startup/Company: <span className="text-[#FF2B5E]">*</span></label>
                <input data-field="startupName"
                  type="text"
                  value={startupName}
                  onChange={e => setStartupName(e.target.value)}
                  placeholder="Your answer"
                  className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors"
                />
              </div>

              {/* Members */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Members (Last Name, First Name, Middle Initial): <span className="text-[#FF2B5E]">*</span></label>
                <input data-field="members"
                  type="text"
                  value={members}
                  onChange={e => setMembers(e.target.value)}
                  placeholder="Your answer"
                  className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors"
                />
              </div>
            </>
          )}

          {step === 1 && (
            <>
              {/* Section header */}
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  FUTURE TRAINING AREAS
                </div>
              </div>

              {TRAINING_AREAS.map((cat, catIdx) => (
                <div key={catIdx} className="space-y-2">
                  <h3 className="text-sm font-semibold text-gray-800">{cat.title} <span className="text-[#FF2B5E]">*</span></h3>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="bg-gray-50">
                          <th className="text-left px-3 py-2 font-medium text-gray-600 w-1/2"></th>
                          {[5, 4, 3, 2, 1].map(v => (
                            <th key={v} className="px-3 py-2 font-medium text-gray-600 text-center w-[10%]">{v}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-100">
                        {cat.items.map((item, itemIdx) => (
                          <tr key={itemIdx} className="hover:bg-gray-50">
                            <td className="px-3 py-2 text-gray-700">{item}</td>
                            {[5, 4, 3, 2, 1].map(v => (
                              <td key={v} className="text-center px-3 py-2">
                                <input data-field="ratings"
                                  type="radio"
                                  name={`form7-cat${catIdx}-item${itemIdx}`}
                                  checked={ratings[`${catIdx}-${itemIdx}`] === v}
                                  onChange={() => setRating(catIdx, itemIdx, v)}
                                  className="w-4 h-4 text-[#FF2B5E] accent-[#FF2B5E]"
                                />
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))}

              {/* Other skills */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  List down other skills/training not mentioned that you believe are critical for your venture: <span className="text-[#FF2B5E]">*</span>
                </label>
                <input data-field="otherSkills"
                  type="text"
                  value={otherSkills}
                  onChange={e => setOtherSkills(e.target.value)}
                  placeholder="Your answer"
                  className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors"
                />
              </div>
            </>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div id="form7-print-area">
            <h1>Training Needs Assessment</h1>
            <div className="scale">
              <strong>Rating Scale:</strong><br/>
              5 - Excellent (Expert; can mentor others)<br/>
              4 - Satisfactory (Proficient; can perform tasks independently)<br/>
              3 - Good (Basic competence; needs occasional guidance)<br/>
              2 - Moderate (Some exposure; needs significant guidance)<br/>
              1 - Poor (No experience or knowledge)
            </div>
            <div className="info"><span>Name of Startup/Company:</span> {startupName || '____________________'}</div>
            <div className="info"><span>Members:</span> {members || '____________________'}</div>

            <h2>FUTURE TRAINING AREAS</h2>
            {TRAINING_AREAS.map((cat, catIdx) => (
              <div key={catIdx}>
                <h2>{cat.title}</h2>
                <table>
                  <thead>
                    <tr><th style={{ textAlign: 'left' }}>Skill / Topic</th>{[5, 4, 3, 2, 1].map(v => <th key={v}>{v}</th>)}</tr>
                  </thead>
                  <tbody>
                    {cat.items.map((item, itemIdx) => (
                      <tr key={itemIdx}>
                        <td>{item}</td>
                        {[5, 4, 3, 2, 1].map(v => (
                          <td key={v}>{ratings[`${catIdx}-${itemIdx}`] === v ? '●' : '○'}</td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}
            <div className="other">
              <div className="other-label">Other skills/training critical for your venture:</div>
              <div className="other-value">{otherSkills || ' '}</div>
            </div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${
              step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'
            }`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
            >
              Close
            </button>
            {step < 1 ? (
              <button
                onClick={() => setStep(step + 1)}
                className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors"
              >
                Next <ChevronRight className="w-4 h-4" />
              </button>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 8 – Training/Event Evaluation Survey
// ──────────────────────────────────────────────
const PRE_TRAINING_ITEMS = [
  '1. Prior Knowledge: My current knowledge/experience in this training topic is:',
  '2. Relevance: This training topic is relevant to my startup\'s current stage of development:',
  '3. Urgency: Acquiring this knowledge/skill is critical to my immediate next steps:',
  '4. Expected Outcome: What is the single most important objective you hope to achieve by attending this training? (Open-ended)',
];

const POST_CONTENT_QUALITY_ITEMS = [
  '1. The training fulfilled the stated learning objectives.',
  '2. The content was practical and directly applicable to my technology venture.',
  '3. The materials/handouts were clear and useful.',
];

const POST_INSTRUCTOR_ITEMS = [
  '4. The instructor was knowledgeable and demonstrated expertise.',
  '5. The presentation style was engaging and easy to follow.',
];

const POST_IMPACT_ITEMS = [
  '6. I gained new skills or knowledge critical for my startup.',
  '7. I would recommend this training to other residents/mentees in MARIAN TBI.',
  '8. The time allocated for the training was sufficient.',
];

function Form8Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0); // 0 = privacy, 1 = pre-training, 2 = post-training
  const totalSteps = 3;

  // Pre-training ratings
  const [preRatings, setPreRatings] = useState<Record<number, number>>({});

  // Post-training ratings
  const [postContentRatings, setPostContentRatings] = useState<Record<number, number>>({});
  const [postInstructorRatings, setPostInstructorRatings] = useState<Record<number, number>>({});
  const [postImpactRatings, setPostImpactRatings] = useState<Record<number, number>>({});

  // Open feedback
  const [feedback9, setFeedback9] = useState('');
  const [feedback10, setFeedback10] = useState('');

  const handlePrint = () => {
    const printArea = document.getElementById('form8-print-area');
    if (!printArea) return;
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><title>Form 8 - Training/Event Evaluation Survey</title><style>
      body { font-family: Arial, sans-serif; font-size: 12px; padding: 30px; color: #222; }
      h1 { font-size: 18px; margin-bottom: 4px; }
      h2 { font-size: 14px; margin-top: 18px; margin-bottom: 6px; background: #9b1b5a; color: #fff; padding: 4px 8px; border-radius: 4px; }
      h3 { font-size: 13px; margin: 14px 0 6px; }
      .privacy { font-size: 11px; line-height: 1.5; margin-bottom: 16px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
      th, td { border: 1px solid #bbb; padding: 5px 8px; text-align: center; font-size: 11px; }
      th { background: #f3f3f3; }
      td:first-child { text-align: left; width: 55%; }
      .fb { margin-top: 10px; }
      .fb-label { font-weight: bold; margin-bottom: 4px; font-size: 12px; }
      .fb-value { border-bottom: 1px solid #333; min-height: 20px; padding: 2px 0; font-size: 12px; }
      @media print { body { padding: 15px; } }
    </style></head><body>` + printArea.innerHTML + '</body></html>');
    w.document.close();
    w.print();
  };

  const renderRatingTable = (
    items: string[],
    ratings: Record<number, number>,
    setRating: (idx: number, val: number) => void,
    namePrefix: string,
    scaleLabel: string,
    fieldKey: string
  ) => (
    <div className="overflow-x-auto">
      <p className="text-xs text-gray-500 mb-2">{scaleLabel}</p>
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-gray-50">
            <th className="text-left px-3 py-2 font-medium text-gray-600 w-1/2"></th>
            {[5, 4, 3, 2, 1].map(v => (
              <th key={v} className="px-3 py-2 font-medium text-gray-600 text-center w-[10%]">{v}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {items.map((item, idx) => (
            <tr key={idx} className="hover:bg-gray-50">
              <td className="px-3 py-2 text-gray-700 text-left">{item}</td>
              {[5, 4, 3, 2, 1].map(v => (
                <td key={v} className="text-center px-3 py-2">
                  <input
                    type="radio"
                    data-field={fieldKey}
                    name={`${namePrefix}-${idx}`}
                    checked={ratings[idx] === v}
                    onChange={() => setRating(idx, v)}
                    className="w-4 h-4 accent-[#FF2B5E]"
                  />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  const printRatingTable = (items: string[], ratings: Record<number, number>) => (
    <table>
      <thead>
        <tr><th style={{ textAlign: 'left' }}>Item</th>{[5, 4, 3, 2, 1].map(v => <th key={v}>{v}</th>)}</tr>
      </thead>
      <tbody>
        {items.map((item, idx) => (
          <tr key={idx}>
            <td>{item}</td>
            {[5, 4, 3, 2, 1].map(v => (
              <td key={v}>{ratings[idx] === v ? '●' : '○'}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );

  useFormValues(8, () => ({
    preRatings,
    postContentRatings,
    postInstructorRatings,
    postImpactRatings,
    feedback9,
    feedback10,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Form 8 - Training/Event Evaluation Survey</h2>
            <p className="text-xs text-gray-500 mt-0.5">Step {step + 1} of {totalSteps}</p>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={handlePrint} className="text-gray-500 hover:text-[#FF2B5E] p-2" title="Print">
              <Printer className="w-5 h-5" />
            </button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1">
          <div className="bg-[#FF2B5E] h-1 transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">

          {/* Step 0 – Privacy Notice */}
          {step === 0 && (
            <>
              <div className="bg-gray-50 rounded-xl p-5 space-y-4">
                <h3 className="text-sm font-bold text-gray-800">DATA PRIVACY NOTICE</h3>
                <p className="text-sm text-gray-600 leading-relaxed">
                  MARIAN TBI is taking all available measures to safeguard your personal information to uphold everyone's privacy. Your personal information may be processed manually or by automated means, upon your request or consent, or upon the lawful order of any competent authority. Personal information disclosure and sharing shall only be done within the community only when operational protocols and processes ultimately require the same.
                </p>
                <p className="text-sm text-gray-600 leading-relaxed">
                  Upon submitting this form, you consent to the processing of your personal information internally. Other forms of processing of your information, such as sharing it outside the community, shall further require your written consent.
                </p>
                <p className="text-sm text-gray-600 leading-relaxed">
                  As a data subject, you may avail of the guarantees provided by Section 16 of Republic Act No. 10173 (the "Data Privacy Act"), which includes the rights to be informed, to object, access, rectify, erasure or blocking, data portability, file a complaint, and to the payment of damages. Should you wish to make changes to your information stored in our database, please message us directly at Facebook through www.facebook.com/mariantbi or e-mail us at mariantbi@uic.edu.ph.
                </p>
              </div>
            </>
          )}

          {/* Step 1 – A. Pre-Training Survey */}
          {step === 1 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  A. Pre-Training Survey
                </div>
              </div>

              <h3 className="text-sm font-semibold text-gray-800">
                Topic (Rating Scale 1=Low, 5=High) <span className="text-[#FF2B5E]">*</span>
              </h3>

              {renderRatingTable(
                PRE_TRAINING_ITEMS,
                preRatings,
                (idx, val) => setPreRatings(prev => ({ ...prev, [idx]: val })),
                'f8-pre',
                'Rating Scale: 1 = Low, 5 = High',
                'preRatings'
              )}
            </>
          )}

          {/* Step 2 – B. Post-Training Survey */}
          {step === 2 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  B. Post-Training Survey
                </div>
              </div>
              <p className="text-xs text-gray-500">Section/Question (Rating Scale 1=Strongly Disagree, 5=Strongly Agree)</p>

              {/* I. Content Quality */}
              <h3 className="text-sm font-semibold text-gray-800">I. Content Quality <span className="text-[#FF2B5E]">*</span></h3>
              {renderRatingTable(
                POST_CONTENT_QUALITY_ITEMS,
                postContentRatings,
                (idx, val) => setPostContentRatings(prev => ({ ...prev, [idx]: val })),
                'f8-content',
                '',
                'postContentRatings'
              )}

              {/* II. Instructor/Facilitator */}
              <h3 className="text-sm font-semibold text-gray-800">II. Instructor/Facilitator <span className="text-[#FF2B5E]">*</span></h3>
              {renderRatingTable(
                POST_INSTRUCTOR_ITEMS,
                postInstructorRatings,
                (idx, val) => setPostInstructorRatings(prev => ({ ...prev, [idx]: val })),
                'f8-instructor',
                '',
                'postInstructorRatings'
              )}

              {/* III. Impact and Value */}
              <h3 className="text-sm font-semibold text-gray-800">III. Impact and Value <span className="text-[#FF2B5E]">*</span></h3>
              {renderRatingTable(
                POST_IMPACT_ITEMS,
                postImpactRatings,
                (idx, val) => setPostImpactRatings(prev => ({ ...prev, [idx]: val })),
                'f8-impact',
                '',
                'postImpactRatings'
              )}

              {/* IV. Open Feedback */}
              <div className="space-y-4">
                <h3 className="text-sm font-semibold text-gray-800">IV. Open Feedback <span className="text-[#FF2B5E]">*</span></h3>
                <div>
                  <label className="block text-sm text-gray-700 mb-1">
                    9. What was the <em>most</em> valuable part of the training?
                  </label>
                  <input data-field="feedback9"
                    type="text"
                    value={feedback9}
                    onChange={e => setFeedback9(e.target.value)}
                    placeholder="Your answer"
                    className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors"
                  />
                </div>
                <div>
                  <label className="block text-sm text-gray-700 mb-1">
                    10. What specific topic should be covered in more detail in the future? <span className="text-[#FF2B5E]">*</span>
                  </label>
                  <input data-field="feedback10"
                    type="text"
                    value={feedback10}
                    onChange={e => setFeedback10(e.target.value)}
                    placeholder="Your answer"
                    className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors"
                  />
                </div>
              </div>
            </>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div id="form8-print-area">
            <h1>Training/Event Evaluation Survey</h1>

            <div className="privacy">
              <strong>DATA PRIVACY NOTICE</strong><br/>
              MARIAN TBI is taking all available measures to safeguard your personal information to uphold everyone's privacy. Your personal information may be processed manually or by automated means, upon your request or consent, or upon the lawful order of any competent authority.
            </div>

            <h2>A. Pre-Training Survey</h2>
            <p style={{ fontSize: '11px', marginBottom: '6px' }}>Topic (Rating Scale 1=Low, 5=High)</p>
            {printRatingTable(PRE_TRAINING_ITEMS, preRatings)}

            <h2>B. Post-Training Survey</h2>
            <p style={{ fontSize: '11px', marginBottom: '6px' }}>Rating Scale 1=Strongly Disagree, 5=Strongly Agree</p>

            <h3>I. Content Quality</h3>
            {printRatingTable(POST_CONTENT_QUALITY_ITEMS, postContentRatings)}

            <h3>II. Instructor/Facilitator</h3>
            {printRatingTable(POST_INSTRUCTOR_ITEMS, postInstructorRatings)}

            <h3>III. Impact and Value</h3>
            {printRatingTable(POST_IMPACT_ITEMS, postImpactRatings)}

            <div className="fb">
              <div className="fb-label">9. What was the most valuable part of the training?</div>
              <div className="fb-value">{feedback9 || ' '}</div>
            </div>
            <div className="fb">
              <div className="fb-label">10. What specific topic should be covered in more detail in the future?</div>
              <div className="fb-value">{feedback10 || ' '}</div>
            </div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${
              step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'
            }`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
            >
              Close
            </button>
            {step < totalSteps - 1 ? (
              <button
                onClick={() => setStep(step + 1)}
                className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors"
              >
                Next <ChevronRight className="w-4 h-4" />
              </button>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 9 – Progress Monitoring Report
// ──────────────────────────────────────────────
const SECTION1_ROWS = [
  { label: 'Key Technical Milestones' },
  { label: 'IP / Patent Status' },
];
const SECTION2_ROWS = [
  { label: 'Problem Validation' },
  { label: 'Business Model Changes' },
  { label: 'Go-to-Market' },
  { label: 'Traction / Sales' },
];
const SECTION3_ROWS = [
  { label: 'Funding Raised' },
  { label: 'Burn Rate (Monthly)' },
  { label: 'Next Funding Goal' },
];
const SECTION4_ROWS = [
  { label: 'TBI Program Milestones' },
  { label: 'Mentorship Sessions' },
  { label: 'Team Status' },
  { label: 'Support Needed from MARIAN TBI' },
];
const SECTION5_ROWS = [
  { label: 'Technology' },
  { label: 'Business / Market' },
  { label: 'Financial / Funding' },
];
const EVAL_DIMENSIONS = [
  'Technology Development Pace',
  'Market Validation Effectiveness',
  'Financial Sustainability Path',
];

function Form9Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 4;

  // Step 0 – Header
  const [startupName, setStartupName] = useState('');
  const [members, setMembers] = useState(['', '', '', '', '']);
  const [reportDate, setReportDate] = useState('');
  const [periodFrom, setPeriodFrom] = useState('');
  const [periodTo, setPeriodTo] = useState('');

  // Step 1 – Sections 1 & 2  (key = "s{section}-{rowIdx}-{col}")
  const [trlStart, setTrlStart] = useState('');
  const [trlAchieved, setTrlAchieved] = useState('');
  const [trlTarget, setTrlTarget] = useState('');
  const [grid, setGrid] = useState<Record<string, string>>({});
  const setCell = (key: string, val: string) => setGrid(prev => ({ ...prev, [key]: val }));

  // Step 3 – Section 6 TBI Eval
  const [evalRatings, setEvalRatings] = useState<Record<number, string>>({});
  const [evalRemarks, setEvalRemarks] = useState<Record<number, string>>({});
  const [overallPerf, setOverallPerf] = useState('');
  const [evalName, setEvalName] = useState('');
  const [evalTitle, setEvalTitle] = useState('');
  const [evalDate, setEvalDate] = useState('');

  const setMember = (idx: number, val: string) => {
    setMembers(prev => { const n = [...prev]; n[idx] = val; return n; });
  };

  // Reusable 3-col table (Plan / Progress / Variance)
  const render3ColTable = (sectionKey: string, rows: { label: string }[]) => (
    <div className="overflow-x-auto">
      <table className="w-full text-sm border border-gray-200">
        <thead>
          <tr className="bg-gray-50">
            <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[22%]">Item</th>
            <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[26%]">Plan (for this period)</th>
            <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[26%]">Progress (Achieved)</th>
            <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[26%]">Variance / Deviation &amp; Rationale</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row, idx) => (
            <tr key={idx}>
              <td className="px-3 py-2 text-gray-700 border border-gray-200 font-medium text-xs">{row.label}</td>
              {['plan', 'progress', 'variance'].map(col => (
                <td key={col} className="px-1 py-1 border border-gray-200">
                  <input data-field="grid"
                    type="text"
                    value={grid[`${sectionKey}-${idx}-${col}`] || ''}
                    onChange={e => setCell(`${sectionKey}-${idx}-${col}`, e.target.value)}
                    className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent"
                  />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  const handlePrint = () => {
    const el = document.getElementById('form9-print-area');
    if (!el) return;
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><title>Form 9 - Progress Monitoring Report</title><style>
      body{font-family:Arial,sans-serif;font-size:11px;padding:25px;color:#222}
      h1{font-size:17px;margin-bottom:4px}
      h2{font-size:13px;margin-top:16px;margin-bottom:6px;background:#9b1b5a;color:#fff;padding:4px 8px;border-radius:4px}
      .info{margin:3px 0}.info span{font-weight:bold}
      table{width:100%;border-collapse:collapse;margin-bottom:10px}
      th,td{border:1px solid #bbb;padding:4px 6px;font-size:10px}
      th{background:#f3f3f3;text-align:left}
      .sig{margin-top:20px}.sig-line{display:inline-block;width:200px;border-bottom:1px solid #333}
      @media print{body{padding:12px}}
    </style></head><body>` + el.innerHTML + '</body></html>');
    w.document.close();
    w.print();
  };

  useFormValues(9, () => ({
    startupName,
    members,
    reportDate,
    periodFrom,
    periodTo,
    trlStart,
    trlAchieved,
    trlTarget,
    grid,
    evalRatings,
    evalRemarks,
    overallPerf,
    evalName,
    evalTitle,
    evalDate,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Form 9 - Progress Monitoring Report</h2>
            <p className="text-xs text-gray-500 mt-0.5">Step {step + 1} of {totalSteps}</p>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={handlePrint} className="text-gray-500 hover:text-[#FF2B5E] p-2" title="Print"><Printer className="w-5 h-5" /></button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2"><X className="w-5 h-5" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1">
          <div className="bg-[#FF2B5E] h-1 transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">

          {/* Step 0 – Header Info */}
          {step === 0 && (
            <>
              <div className="grid grid-cols-1 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Name of Startup / Company</label>
                  <input data-field="startupName" type="text" value={startupName} onChange={e => setStartupName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                {[0, 1, 2, 3, 4].map(i => (
                  <div key={i}>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Member {i + 1}</label>
                    <input data-field="members" type="text" value={members[i]} onChange={e => setMember(i, e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                ))}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Date of Report</label>
                  <input data-field="reportDate" type="date" value={reportDate} onChange={e => setReportDate(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Reporting Period – From</label>
                    <input data-field="periodFrom" type="date" value={periodFrom} onChange={e => setPeriodFrom(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Reporting Period – To</label>
                    <input data-field="periodTo" type="date" value={periodTo} onChange={e => setPeriodTo(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                </div>
              </div>
            </>
          )}

          {/* Step 1 – Sections 1 & 2 */}
          {step === 1 && (
            <>
              {/* Section 1 */}
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  1. Technology and Product Development Status
                </div>
              </div>

              {/* TRL row */}
              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-200">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200" colSpan={2}>Technology Readiness Level (TRL)</th>
                    </tr>
                  </thead>
                  <tbody>
                    {[
                      { label: 'TRL Start', field: 'trlStart', value: trlStart, set: setTrlStart },
                      { label: 'TRL Achieved', field: 'trlAchieved', value: trlAchieved, set: setTrlAchieved },
                      { label: 'TRL Target', field: 'trlTarget', value: trlTarget, set: setTrlTarget },
                    ].map((r, i) => (
                      <tr key={i}>
                        <td className="px-3 py-2 text-gray-700 border border-gray-200 font-medium text-xs w-[30%]">{r.label}</td>
                        <td className="px-1 py-1 border border-gray-200">
                          <input type="text" data-field={r.field} value={r.value} onChange={e => r.set(e.target.value)} className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {render3ColTable('s1', SECTION1_ROWS)}

              {/* Section 2 */}
              <div className="rounded-lg overflow-hidden mt-4">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  2. Business and Market Validation
                </div>
              </div>
              {render3ColTable('s2', SECTION2_ROWS)}
            </>
          )}

          {/* Step 2 – Sections 3 & 4 */}
          {step === 2 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  3. Financial and Resource Management
                </div>
              </div>
              {render3ColTable('s3', SECTION3_ROWS)}

              <div className="rounded-lg overflow-hidden mt-4">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  4. Incubation and Mentorship Engagement
                </div>
              </div>
              {render3ColTable('s4', SECTION4_ROWS)}
            </>
          )}

          {/* Step 3 – Sections 5 & 6 */}
          {step === 3 && (
            <>
              {/* Section 5 */}
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  5. Plan for Next Period (Summary of Next Steps)
                </div>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-200">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[25%]">Item</th>
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[50%]">Key Activities / Milestones</th>
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[25%]">Target Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {SECTION5_ROWS.map((row, idx) => (
                      <tr key={idx}>
                        <td className="px-3 py-2 text-gray-700 border border-gray-200 font-medium text-xs">{row.label}</td>
                        <td className="px-1 py-1 border border-gray-200">
                          <input data-field="grid" type="text" value={grid[`s5-${idx}-activities`] || ''} onChange={e => setCell(`s5-${idx}-activities`, e.target.value)} className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                        </td>
                        <td className="px-1 py-1 border border-gray-200">
                          <input data-field="grid" type="text" value={grid[`s5-${idx}-date`] || ''} onChange={e => setCell(`s5-${idx}-date`, e.target.value)} className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Section 6 */}
              <div className="rounded-lg overflow-hidden mt-4">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  6. TBI Evaluation
                </div>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-200">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[28%]">Dimension</th>
                      <th className="text-center px-3 py-2 font-medium text-gray-600 border border-gray-200" colSpan={3}>Rating (Check one)</th>
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[25%]">Remarks / Justification</th>
                    </tr>
                    <tr className="bg-gray-50">
                      <th className="border border-gray-200"></th>
                      <th className="text-center px-2 py-1 text-xs font-medium text-gray-600 border border-gray-200">Exceeding</th>
                      <th className="text-center px-2 py-1 text-xs font-medium text-gray-600 border border-gray-200">On Track</th>
                      <th className="text-center px-2 py-1 text-xs font-medium text-gray-600 border border-gray-200">Needs Attention</th>
                      <th className="border border-gray-200"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {EVAL_DIMENSIONS.map((dim, idx) => (
                      <tr key={idx}>
                        <td className="px-3 py-2 text-gray-700 border border-gray-200 font-medium text-xs">{dim}</td>
                        {['Exceeding', 'On Track', 'Needs Attention'].map(opt => (
                          <td key={opt} className="text-center px-2 py-2 border border-gray-200">
                            <input data-field="evalRatings"
                              type="radio"
                              name={`eval-dim-${idx}`}
                              checked={evalRatings[idx] === opt}
                              onChange={() => setEvalRatings(prev => ({ ...prev, [idx]: opt }))}
                              className="w-4 h-4 accent-[#FF2B5E]"
                            />
                          </td>
                        ))}
                        <td className="px-1 py-1 border border-gray-200">
                          <input data-field="evalRemarks" type="text" value={evalRemarks[idx] || ''} onChange={e => setEvalRemarks(prev => ({ ...prev, [idx]: e.target.value }))} className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Overall Performance */}
              <div>
                <h3 className="text-sm font-semibold text-gray-800 mb-2">Overall Performance</h3>
                <div className="flex flex-wrap gap-4">
                  {['Excellent', 'Good', 'Satisfactory', 'Needs Attention'].map(opt => (
                    <label key={opt} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                      <input data-field="overallPerf"
                        type="radio"
                        name="overall-perf"
                        checked={overallPerf === opt}
                        onChange={() => setOverallPerf(opt)}
                        className="w-4 h-4 accent-[#FF2B5E]"
                      />
                      {opt}
                    </label>
                  ))}
                </div>
              </div>

              {/* Evaluator */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Evaluator's Name</label>
                  <input data-field="evalName" type="text" value={evalName} onChange={e => setEvalName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Evaluator's Title</label>
                  <input data-field="evalTitle" type="text" value={evalTitle} onChange={e => setEvalTitle(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Date of Evaluation</label>
                  <input data-field="evalDate" type="date" value={evalDate} onChange={e => setEvalDate(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
              </div>
            </>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div id="form9-print-area">
            <h1>Progress Monitoring Report</h1>
            <div className="info"><span>Startup / Company:</span> {startupName}</div>
            {members.filter(m => m).map((m, i) => <div key={i} className="info"><span>Member {i + 1}:</span> {m}</div>)}
            <div className="info"><span>Date of Report:</span> {reportDate}</div>
            <div className="info"><span>Reporting Period:</span> {periodFrom} to {periodTo}</div>

            <h2>1. Technology and Product Development Status</h2>
            <table><tbody>
              <tr><td><strong>TRL Start</strong></td><td>{trlStart}</td></tr>
              <tr><td><strong>TRL Achieved</strong></td><td>{trlAchieved}</td></tr>
              <tr><td><strong>TRL Target</strong></td><td>{trlTarget}</td></tr>
            </tbody></table>
            <table><thead><tr><th>Item</th><th>Plan</th><th>Progress</th><th>Variance</th></tr></thead><tbody>
              {SECTION1_ROWS.map((r, i) => <tr key={i}><td>{r.label}</td><td>{grid[`s1-${i}-plan`]}</td><td>{grid[`s1-${i}-progress`]}</td><td>{grid[`s1-${i}-variance`]}</td></tr>)}
            </tbody></table>

            <h2>2. Business and Market Validation</h2>
            <table><thead><tr><th>Item</th><th>Plan</th><th>Progress</th><th>Variance</th></tr></thead><tbody>
              {SECTION2_ROWS.map((r, i) => <tr key={i}><td>{r.label}</td><td>{grid[`s2-${i}-plan`]}</td><td>{grid[`s2-${i}-progress`]}</td><td>{grid[`s2-${i}-variance`]}</td></tr>)}
            </tbody></table>

            <h2>3. Financial and Resource Management</h2>
            <table><thead><tr><th>Item</th><th>Plan</th><th>Progress</th><th>Variance</th></tr></thead><tbody>
              {SECTION3_ROWS.map((r, i) => <tr key={i}><td>{r.label}</td><td>{grid[`s3-${i}-plan`]}</td><td>{grid[`s3-${i}-progress`]}</td><td>{grid[`s3-${i}-variance`]}</td></tr>)}
            </tbody></table>

            <h2>4. Incubation and Mentorship Engagement</h2>
            <table><thead><tr><th>Item</th><th>Plan</th><th>Progress</th><th>Variance</th></tr></thead><tbody>
              {SECTION4_ROWS.map((r, i) => <tr key={i}><td>{r.label}</td><td>{grid[`s4-${i}-plan`]}</td><td>{grid[`s4-${i}-progress`]}</td><td>{grid[`s4-${i}-variance`]}</td></tr>)}
            </tbody></table>

            <h2>5. Plan for Next Period</h2>
            <table><thead><tr><th>Item</th><th>Key Activities / Milestones</th><th>Target Date</th></tr></thead><tbody>
              {SECTION5_ROWS.map((r, i) => <tr key={i}><td>{r.label}</td><td>{grid[`s5-${i}-activities`]}</td><td>{grid[`s5-${i}-date`]}</td></tr>)}
            </tbody></table>

            <h2>6. TBI Evaluation</h2>
            <table><thead><tr><th>Dimension</th><th>Exceeding</th><th>On Track</th><th>Needs Attention</th><th>Remarks</th></tr></thead><tbody>
              {EVAL_DIMENSIONS.map((d, i) => <tr key={i}><td>{d}</td><td>{evalRatings[i] === 'Exceeding' ? '●' : '○'}</td><td>{evalRatings[i] === 'On Track' ? '●' : '○'}</td><td>{evalRatings[i] === 'Needs Attention' ? '●' : '○'}</td><td>{evalRemarks[i]}</td></tr>)}
            </tbody></table>
            <div className="info"><span>Overall Performance:</span> {overallPerf}</div>
            <br/>
            <div className="info"><span>Evaluator's Name:</span> {evalName}</div>
            <div className="info"><span>Evaluator's Title:</span> {evalTitle}</div>
            <div className="info"><span>Date of Evaluation:</span> {evalDate}</div>
            <div className="sig"><span className="sig-line">&nbsp;</span><br/><span style={{ fontSize: '10px' }}>Evaluator's Signature</span></div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 10 – Quarterly Work Plan (QWP)
// ──────────────────────────────────────────────
const QWP_SECTION1_DEFAULTS = [
  {
    activity: 'Technical Design & Architecture',
    outcome: 'Finalized System Architecture Document and Code Repository Structure.',
    kpi: '100% completion of v1.0 design.',
  },
  {
    activity: 'Prototype / MVP Development',
    outcome: 'Completed functional Minimum Viable Product (MVP) with core features A,B,C.',
    kpi: 'TRL Advancement: From TRL __ to TRL __.',
  },
  {
    activity: 'Testing & Quality Assurance',
    outcome: 'Executed internal and closed-beta tests on __ number of users.',
    kpi: '<5 critical bugs reported; 90% uptime in testing environment.',
  },
  {
    activity: 'Health Tech Specific',
    outcome: 'Developed data flow architecture compliant with local health privacy standards.',
    kpi: 'Completed compliance checklist/audit.',
  },
  { activity: '', outcome: '', kpi: '' },
  { activity: '', outcome: '', kpi: '' },
];

const QWP_SECTION2_DEFAULTS = [
  {
    activity: 'Customer Validation',
    outcome: 'Conducted one-on-one interviews with __ potential B2B clients or end-users.',
    kpi: '80% validation rate of the core problem statement.',
  },
  {
    activity: 'Market Pilot Execution',
    outcome: 'Launched a controlled market pilot program with __ specific partners.',
    kpi: '50 registered users; 100% of pilot users provide feedback.',
  },
  {
    activity: 'Sales & Revenue',
    outcome: 'Secured first [X] paying customers or signed LOI with Partner Y.',
    kpi: 'Target Revenue: Php __ or __ Contracts / LOIs signed.',
  },
  {
    activity: 'Pricing Strategy',
    outcome: 'Finalized tiered pricing model based on pilot feedback.',
    kpi: '100% Sign-off on pricing by the management team.',
  },
];

const QWP_SECTION3_DEFAULTS = [
  {
    activity: 'Financial Projection',
    outcome: 'Updated 3-year financial model including burn rate and cash runway calculation.',
    kpi: 'Accuracy variance of actual vs. Projected monthly expenses < 10%',
  },
  {
    activity: 'Funding Readiness',
    outcome: 'Completed and polished Investment / Pitch Deck',
    kpi: 'Scheduled __ pitch meetings or submitted __ grant applications.',
  },
  {
    activity: 'Business Registration',
    outcome: 'Completed __ Legal registration / SEC filing milestones',
    kpi: '100% compliance with identified regulatory requirements.',
  },
];

const QWP_SECTION4_DEFAULTS = [
  {
    activity: 'Team Development',
    outcome: 'Hired / Onboarded __ critical personnel (Lead Developer, Marketing Intern).',
    kpi: '100% of team completes (Specific MARIAN TBI Training/Workshop)',
  },
  {
    activity: 'Mentorship',
    outcome: 'Engaged in formal sessions with TBI-assigned Mentors',
    kpi: 'Minimum of __ hours of recorded mentorship interaction.',
  },
  {
    activity: 'IP Protection',
    outcome: 'Submitted required documentation for IP protection.',
    kpi: "IP status moved to 'Pending Submission' or 'Submitted'.",
  },
];

function Form10Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 5; // 0=Header, 1=Sec1, 2=Sec2, 3=Sec3&4, 4=Sec5+Auth

  // Step 0 – Header
  const [startupName, setStartupName] = useState('');
  const [periodFrom, setPeriodFrom] = useState('');
  const [periodTo, setPeriodTo] = useState('');
  const [incubationStage, setIncubationStage] = useState('');
  const [founderName, setFounderName] = useState('');
  const [mentors, setMentors] = useState([{ name: '' }, { name: '' }, { name: '' }]);
  const [tbiManagerName, setTbiManagerName] = useState('');

  const setMentor = (idx: number, val: string) => {
    setMentors(prev => { const n = [...prev]; n[idx] = { name: val }; return n; });
  };

  // Section grids: key = "s{n}-{row}-{col}" where col = activity|outcome|kpi|date|notes
  const [grid, setGrid] = useState<Record<string, string>>(() => {
    const init: Record<string, string> = {};
    QWP_SECTION1_DEFAULTS.forEach((r, i) => { init[`s1-${i}-activity`] = r.activity; init[`s1-${i}-outcome`] = r.outcome; init[`s1-${i}-kpi`] = r.kpi; });
    QWP_SECTION2_DEFAULTS.forEach((r, i) => { init[`s2-${i}-activity`] = r.activity; init[`s2-${i}-outcome`] = r.outcome; init[`s2-${i}-kpi`] = r.kpi; });
    QWP_SECTION3_DEFAULTS.forEach((r, i) => { init[`s3-${i}-activity`] = r.activity; init[`s3-${i}-outcome`] = r.outcome; init[`s3-${i}-kpi`] = r.kpi; });
    QWP_SECTION4_DEFAULTS.forEach((r, i) => { init[`s4-${i}-activity`] = r.activity; init[`s4-${i}-outcome`] = r.outcome; init[`s4-${i}-kpi`] = r.kpi; });
    return init;
  });
  const setCell = (key: string, val: string) => setGrid(prev => ({ ...prev, [key]: val }));

  // Step 4 – Section 5 goals + authorization
  const [goalTech, setGoalTech] = useState('');
  const [goalMarket, setGoalMarket] = useState('');
  const [goalFunding, setGoalFunding] = useState('');
  const [authDate1, setAuthDate1] = useState('');
  const [authDate2, setAuthDate2] = useState('');

  const INCUBATION_STAGES = [
    'Pre-incubation (Ideation & Feasibility)',
    'Incubation – Phase 1: Development (Prototype/MVP/TRL Readiness Level)',
    'Incubation – Phase 2: Growth (Market Pilot & Traction)',
    'Graduation (Seed or Series A Funding)',
  ];

  // Render a 5-column QWP table
  const renderQwpTable = (sectionKey: string, rowCount: number) => (
    <div className="overflow-x-auto">
      <table className="w-full text-sm border border-gray-200">
        <thead>
          <tr className="bg-gray-50">
            <th className="text-left px-2 py-2 font-medium text-gray-600 border border-gray-200 w-[18%] text-xs">Activity / Task</th>
            <th className="text-left px-2 py-2 font-medium text-gray-600 border border-gray-200 w-[22%] text-xs">Measurable Outcome</th>
            <th className="text-left px-2 py-2 font-medium text-gray-600 border border-gray-200 w-[22%] text-xs">Key Metric (KPI)</th>
            <th className="text-left px-2 py-2 font-medium text-gray-600 border border-gray-200 w-[14%] text-xs">Target Date</th>
            <th className="text-left px-2 py-2 font-medium text-gray-600 border border-gray-200 w-[24%] text-xs">TBI Manager Notes</th>
          </tr>
        </thead>
        <tbody>
          {Array.from({ length: rowCount }).map((_, idx) => (
            <tr key={idx}>
              {['activity', 'outcome', 'kpi', 'date', 'notes'].map(col => (
                <td key={col} className="px-1 py-1 border border-gray-200">
                  <textarea data-field="grid"
                    value={grid[`${sectionKey}-${idx}-${col}`] || ''}
                    onChange={e => setCell(`${sectionKey}-${idx}-${col}`, e.target.value)}
                    rows={2}
                    className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent resize-none"
                  />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  const handlePrint = () => {
    const el = document.getElementById('form10-print-area');
    if (!el) return;
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><title>Form 10 - Quarterly Work Plan</title><style>
      body{font-family:Arial,sans-serif;font-size:10px;padding:20px;color:#222}
      h1{font-size:16px;margin-bottom:2px}
      h2{font-size:12px;margin-top:14px;margin-bottom:4px;background:#9b1b5a;color:#fff;padding:3px 8px;border-radius:4px}
      .info{margin:2px 0}.info span{font-weight:bold}
      .desc{font-size:10px;font-style:italic;margin-bottom:6px;color:#555}
      table{width:100%;border-collapse:collapse;margin-bottom:8px}
      th,td{border:1px solid #bbb;padding:3px 5px;font-size:9px;vertical-align:top}
      th{background:#f3f3f3;text-align:left}
      .goal{margin:4px 0}.goal-label{font-weight:bold}
      .sig{margin-top:16px;display:inline-block;margin-right:60px}
      .sig-line{display:inline-block;width:200px;border-bottom:1px solid #333}
      @media print{body{padding:10px}}
    </style></head><body>` + el.innerHTML + '</body></html>');
    w.document.close();
    w.print();
  };

  const printQwpTable = (sectionKey: string, rowCount: number) => (
    <table>
      <thead><tr><th>Activity / Task</th><th>Measurable Outcome</th><th>Key Metric (KPI)</th><th>Target Date</th><th>TBI Manager Notes</th></tr></thead>
      <tbody>
        {Array.from({ length: rowCount }).map((_, i) => (
          <tr key={i}>
            <td>{grid[`${sectionKey}-${i}-activity`] || ''}</td>
            <td>{grid[`${sectionKey}-${i}-outcome`] || ''}</td>
            <td>{grid[`${sectionKey}-${i}-kpi`] || ''}</td>
            <td>{grid[`${sectionKey}-${i}-date`] || ''}</td>
            <td>{grid[`${sectionKey}-${i}-notes`] || ''}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );

  useFormValues(10, () => ({
    startupName,
    periodFrom,
    periodTo,
    incubationStage,
    founderName,
    mentors,
    tbiManagerName,
    grid,
    goalTech,
    goalMarket,
    goalFunding,
    authDate1,
    authDate2,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Form 10 - Quarterly Work Plan (QWP)</h2>
            <p className="text-xs text-gray-500 mt-0.5">Step {step + 1} of {totalSteps}</p>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={handlePrint} className="text-gray-500 hover:text-[#FF2B5E] p-2" title="Print"><Printer className="w-5 h-5" /></button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2"><X className="w-5 h-5" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1">
          <div className="bg-[#FF2B5E] h-1 transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">

          {/* Step 0 – Header */}
          {step === 0 && (
            <>
              <div className="bg-gray-50 rounded-xl p-5">
                <p className="text-sm text-gray-600 leading-relaxed">
                  This Quarterly Work Plan (QWP) serves as the detailed roadmap for the incubatee to achieve specific technological, market, and business milestones within the current period. All reported progress will be measured against the outcomes defined below.
                </p>
              </div>

              <div className="grid grid-cols-1 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Name of Startup / Company</label>
                  <input data-field="startupName" type="text" value={startupName} onChange={e => setStartupName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Period Covered – From</label>
                    <input data-field="periodFrom" type="date" value={periodFrom} onChange={e => setPeriodFrom(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Period Covered – To</label>
                    <input data-field="periodTo" type="date" value={periodTo} onChange={e => setPeriodTo(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                </div>

                {/* Stage in Incubation */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Stage in Incubation</label>
                  <div className="space-y-2">
                    {INCUBATION_STAGES.map(s => (
                      <label key={s} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input data-field="incubationStage" type="radio" name="qwp-stage" checked={incubationStage === s} onChange={() => setIncubationStage(s)} className="w-4 h-4 accent-[#FF2B5E]" />
                        {s}
                      </label>
                    ))}
                  </div>
                </div>

                {/* Prepared by */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Prepared by (Founder/CEO) – Name</label>
                  <input data-field="founderName" type="text" value={founderName} onChange={e => setFounderName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>

                {/* Mentors */}
                {mentors.map((m, i) => (
                  <div key={i}>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Mentor/Adviser {i + 1} – Name</label>
                    <input data-field="mentors" type="text" value={m.name} onChange={e => setMentor(i, e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                ))}

                {/* TBI Manager */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">TBI Manager – Name</label>
                  <input data-field="tbiManagerName" type="text" value={tbiManagerName} onChange={e => setTbiManagerName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
              </div>
            </>
          )}

          {/* Step 1 – Section 1: Technology & Product Development */}
          {step === 1 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  SECTION 1: Technology &amp; Product Development (R&amp;D)
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">Objective: Define the specific technical deliverables and advancement of the Technology Readiness Level (TRL) for the core product/service.</p>
              {renderQwpTable('s1', QWP_SECTION1_DEFAULTS.length)}
            </>
          )}

          {/* Step 2 – Section 2: Market, Customer, and Revenue */}
          {step === 2 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  SECTION 2: Market, Customer, and Revenue Generation
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">Objective: Validate the target market, test pricing, and secure initial customer traction (sales or pilot partners).</p>
              {renderQwpTable('s2', QWP_SECTION2_DEFAULTS.length)}
            </>
          )}

          {/* Step 3 – Sections 3 & 4 */}
          {step === 3 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  SECTION 3: Business Model, Financials, and Funding
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">Objective: Solidify the business model, prepare financial projections, and actively pursue external funding opportunities.</p>
              {renderQwpTable('s3', QWP_SECTION3_DEFAULTS.length)}

              <div className="rounded-lg overflow-hidden mt-4">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  SECTION 4: Team, Mentorship, and Incubation Support
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">Objective: Leverage TBI resources for talent development, securing necessary operational support, and enhancing team skills.</p>
              {renderQwpTable('s4', QWP_SECTION4_DEFAULTS.length)}
            </>
          )}

          {/* Step 4 – Section 5 Goals + Authorization */}
          {step === 4 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  SECTION 5: Overall Quarterly Goals (Executive Summary)
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">To be filled out based on the key milestones defined above.</p>

              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">1. Top Priority Technical Goal</label>
                  <input data-field="goalTech" type="text" value={goalTech} onChange={e => setGoalTech(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">2. Top Priority Market Goal</label>
                  <input data-field="goalMarket" type="text" value={goalMarket} onChange={e => setGoalMarket(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">3. Top Priority Funding/Operational Goal</label>
                  <input data-field="goalFunding" type="text" value={goalFunding} onChange={e => setGoalFunding(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
              </div>

              {/* Authorization */}
              <div className="rounded-lg overflow-hidden mt-4">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  TBI and Startup Authorization
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <p className="text-sm font-medium text-gray-700">Startup CEO/Founder</p>
                  <p className="text-xs text-gray-500">Name: {founderName || '________________________'}</p>
                  <p className="text-xs text-gray-500">(Signature Over Printed Name)</p>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Date</label>
                    <input data-field="authDate1" type="date" value={authDate1} onChange={e => setAuthDate1(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-1 text-sm bg-transparent transition-colors" />
                  </div>
                </div>
                <div className="space-y-2">
                  <p className="text-sm font-medium text-gray-700">MARIAN TBI Manager</p>
                  <p className="text-xs text-gray-500">Name: {tbiManagerName || '________________________'}</p>
                  <p className="text-xs text-gray-500">(Signature Over Printed Name)</p>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Date</label>
                    <input data-field="authDate2" type="date" value={authDate2} onChange={e => setAuthDate2(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-1 text-sm bg-transparent transition-colors" />
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div id="form10-print-area">
            <h1>Quarterly Work Plan (QWP)</h1>
            <p style={{ fontSize: '10px', fontStyle: 'italic', marginBottom: '8px' }}>This Quarterly Work Plan serves as the detailed roadmap for the incubatee to achieve specific technological, market, and business milestones within the current period.</p>
            <div className="info"><span>Startup / Company:</span> {startupName}</div>
            <div className="info"><span>Period Covered:</span> {periodFrom} to {periodTo}</div>
            <div className="info"><span>Stage in Incubation:</span> {incubationStage}</div>
            <div className="info"><span>Prepared by (Founder/CEO):</span> {founderName}</div>
            {mentors.filter(m => m.name).map((m, i) => <div key={i} className="info"><span>Mentor/Adviser {i + 1}:</span> {m.name}</div>)}
            <div className="info"><span>TBI Manager:</span> {tbiManagerName}</div>

            <h2>SECTION 1: Technology &amp; Product Development (R&amp;D)</h2>
            <p className="desc">Define the specific technical deliverables and advancement of the TRL for the core product/service.</p>
            {printQwpTable('s1', QWP_SECTION1_DEFAULTS.length)}

            <h2>SECTION 2: Market, Customer, and Revenue Generation</h2>
            <p className="desc">Validate the target market, test pricing, and secure initial customer traction.</p>
            {printQwpTable('s2', QWP_SECTION2_DEFAULTS.length)}

            <h2>SECTION 3: Business Model, Financials, and Funding</h2>
            <p className="desc">Solidify the business model, prepare financial projections, and pursue external funding.</p>
            {printQwpTable('s3', QWP_SECTION3_DEFAULTS.length)}

            <h2>SECTION 4: Team, Mentorship, and Incubation Support</h2>
            <p className="desc">Leverage TBI resources for talent development and enhancing team skills.</p>
            {printQwpTable('s4', QWP_SECTION4_DEFAULTS.length)}

            <h2>SECTION 5: Overall Quarterly Goals</h2>
            <div className="goal"><span className="goal-label">1. Top Priority Technical Goal:</span> {goalTech}</div>
            <div className="goal"><span className="goal-label">2. Top Priority Market Goal:</span> {goalMarket}</div>
            <div className="goal"><span className="goal-label">3. Top Priority Funding/Operational Goal:</span> {goalFunding}</div>

            <br/>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <div className="sig">
                <br/><span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '9px' }}>Startup CEO/Founder</span><br/>
                <span style={{ fontSize: '9px' }}>(Signature Over Printed Name)</span><br/>
                <span style={{ fontSize: '9px' }}>Date: {authDate1 || '____________'}</span>
              </div>
              <div className="sig">
                <br/><span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '9px' }}>MARIAN TBI Manager</span><br/>
                <span style={{ fontSize: '9px' }}>(Signature Over Printed Name)</span><br/>
                <span style={{ fontSize: '9px' }}>Date: {authDate2 || '____________'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────
//  Form 11 – Incubation Evaluation Form
// ──────────────────────────────────────────────
const BASIC_INDICATORS = [
  {
    criteria: 'Relevance',
    outcome: "The extent to which their health-tech products/services align with MARIAN TBI's vision and enterprise development priorities (ICT-based solutions for healthcare).",
  },
  {
    criteria: 'Effectiveness',
    outcome: "Value for money of the incubatee's internal operations and the cost-effectiveness of TBI inputs (mentorship, facilities, and networks) to company growth.",
  },
  {
    criteria: 'Efficiency',
    outcome: 'The extent to which the incubatee achieves operational targets set initially, focusing on product/technology milestones (achieving MVP, pilot testing).',
  },
  {
    criteria: 'Sustainability',
    outcome: 'Clarity and viability of financial sustainability plans and goals beyond the incubation period (long-term business model).',
  },
  {
    criteria: 'Utility',
    outcome: 'The extent to which TBI support services (Mentorship, IP Assistance, Funding Linkages) are actively and effectively utilized to contribute to performance.',
  },
];

const KPI_CATEGORIES = [
  {
    category: 'Financial',
    items: [
      { indicator: 'Financial Standing', rationale: 'Current cash balance, burn rate, and financial stability.' },
      { indicator: 'Investment Secured', rationale: 'Total equity or grant funding / capital raised since joining the TBI. (Crucial TBI KPI)' },
      { indicator: 'Revenue Generation', rationale: 'Existing or projected sales / user-base growth (if applicable)' },
    ],
  },
  {
    category: 'Customer Focus',
    items: [
      { indicator: 'Target Market Validation', rationale: 'Evidence of Customer discovery / user acceptance (# of pilot users, feedback analysis, market size)' },
      { indicator: 'Products / Services', rationale: 'Value-added of the solution (health-tech) to the target beneficiaries / patients / healthcare sector.' },
    ],
  },
  {
    category: 'Innovation & Technology',
    items: [
      { indicator: 'Product Development', rationale: 'Progress on Technology Readiness Level (TRL) and achievement of Minimum Viable Product (MVP).' },
      { indicator: 'Intellectual Property (IP)', rationale: 'Status of IP protection (Patent, Utility Model, Copyright) applied for or granted.' },
    ],
  },
  {
    category: 'Ecosystem Impact',
    items: [
      { indicator: 'Job Creation', rationale: 'Number of full-time jobs created by the incubatee during the tenancy. (Standard TBI KPI)' },
      { indicator: 'Ecosystem Engagement', rationale: 'Successful linkages/partnerships established with healthcare institutions, industry, or government.' },
    ],
  },
];

const FORM11_ATTACHMENTS = [
  'Audited Financial Statements/Financial Projections (if applicable)',
  'Detailed Pricing Structure/Revenue Model',
  'Technology Roadmap/Product Development Plan',
  'Proof of Concept or Minimum Viable Product (MVP) documentation',
  'List of IP Filings and Status',
];

function Form11Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 4; // 0=Header, 1=Basic Indicators, 2=KPIs, 3=Recommendations+Auth

  // Step 0 – Header
  const [startupName, setStartupName] = useState('');
  const [techFocus, setTechFocus] = useState('');
  const [evalDate, setEvalDate] = useState('');
  const [tenancyLength, setTenancyLength] = useState('');
  const [incubationStage, setIncubationStage] = useState('');

  const INCUBATION_STAGES = [
    'Pre-incubation (Ideation & Feasibility)',
    'Incubation – Phase 1: Development (Prototype/MVP/TRL Readiness Level)',
    'Incubation – Phase 2: Growth (Market Pilot & Traction)',
    'Graduation (Seed or Series A Funding)',
  ];

  // Step 1 – Basic Indicators ratings (1-5)
  const [basicRatings, setBasicRatings] = useState<Record<number, number>>({});

  // Step 2 – KPI ratings & rationale comments
  const [kpiRatings, setKpiRatings] = useState<Record<string, number>>({});
  const [kpiComments, setKpiComments] = useState<Record<string, string>>({});

  // Step 3 – Recommendations & Auth
  const [recArea, setRecArea] = useState('');
  const [recSpecific, setRecSpecific] = useState('');
  const [recIncubationStatus, setRecIncubationStatus] = useState('');
  const [recNextSteps, setRecNextSteps] = useState('');
  const [evalByName, setEvalByName] = useState('');
  const [evalByDate, setEvalByDate] = useState('');
  const [mgrName, setMgrName] = useState('');
  const [mgrDate, setMgrDate] = useState('');

  const handlePrint = () => {
    const el = document.getElementById('form11-print-area');
    if (!el) return;
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<!DOCTYPE html><html><head><title>Form 11 - Incubation Evaluation Form</title><style>
      body{font-family:Arial,sans-serif;font-size:11px;padding:25px;color:#222}
      h1{font-size:17px;margin-bottom:4px}
      h2{font-size:13px;margin-top:16px;margin-bottom:6px;background:#9b1b5a;color:#fff;padding:4px 8px;border-radius:4px}
      h3{font-size:12px;margin:10px 0 4px;color:#555}
      .info{margin:3px 0}.info span{font-weight:bold}
      .note{font-size:10px;font-style:italic;color:#555;margin-bottom:6px}
      table{width:100%;border-collapse:collapse;margin-bottom:10px}
      th,td{border:1px solid #bbb;padding:4px 6px;font-size:10px;vertical-align:top}
      th{background:#f3f3f3;text-align:left}
      td.center{text-align:center}
      .att{font-size:10px;margin:2px 0}
      .sig{margin-top:16px;display:inline-block;margin-right:60px}
      .sig-line{display:inline-block;width:200px;border-bottom:1px solid #333}
      @media print{body{padding:12px}}
    </style></head><body>` + el.innerHTML + '</body></html>');
    w.document.close();
    w.print();
  };

  useFormValues(11, () => ({
    startupName,
    techFocus,
    evalDate,
    tenancyLength,
    incubationStage,
    basicRatings,
    kpiRatings,
    kpiComments,
    recArea,
    recSpecific,
    recIncubationStatus,
    recNextSteps,
    evalByName,
    evalByDate,
    mgrName,
    mgrDate,
  }));

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] p-4">
      <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">Form 11 - Incubation Evaluation Form</h2>
            <p className="text-xs text-gray-500 mt-0.5">Step {step + 1} of {totalSteps}</p>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={handlePrint} className="text-gray-500 hover:text-[#FF2B5E] p-2" title="Print"><Printer className="w-5 h-5" /></button>
            <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-2"><X className="w-5 h-5" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1">
          <div className="bg-[#FF2B5E] h-1 transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">

          {/* Step 0 – Header Info */}
          {step === 0 && (
            <>
              <div className="grid grid-cols-1 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Name of Startup / Company</label>
                  <input data-field="startupName" type="text" value={startupName} onChange={e => setStartupName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Technology / Focus Area</label>
                  <input data-field="techFocus" type="text" value={techFocus} onChange={e => setTechFocus(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date of Evaluation</label>
                    <input data-field="evalDate" type="date" value={evalDate} onChange={e => setEvalDate(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Length of Tenancy</label>
                    <input data-field="tenancyLength" type="text" value={tenancyLength} onChange={e => setTenancyLength(e.target.value)} placeholder="e.g., 12 months" className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-2 text-sm bg-transparent transition-colors" />
                  </div>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Stage in Incubation</label>
                  <div className="space-y-2">
                    {INCUBATION_STAGES.map(s => (
                      <label key={s} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input data-field="incubationStage" type="radio" name="f11-stage" checked={incubationStage === s} onChange={() => setIncubationStage(s)} className="w-4 h-4 accent-[#FF2B5E]" />
                        {s}
                      </label>
                    ))}
                  </div>
                </div>
              </div>
            </>
          )}

          {/* Step 1 – Basic Indicators */}
          {step === 1 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  Basic Indicators (TBI Service Utilization &amp; Alignment)
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">Evaluators should rate from 1-5. 1-Not met, 2-Below Average, 3-Average, 4-Above Average, 5-Excellent</p>

              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-200">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[15%]">Criteria</th>
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[65%]">Outcomes</th>
                      <th className="text-center px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[20%]">Rating</th>
                    </tr>
                  </thead>
                  <tbody>
                    {BASIC_INDICATORS.map((ind, idx) => (
                      <tr key={idx}>
                        <td className="px-3 py-2 text-gray-800 border border-gray-200 font-semibold text-xs">{ind.criteria}</td>
                        <td className="px-3 py-2 text-gray-600 border border-gray-200 text-xs">{ind.outcome}</td>
                        <td className="px-1 py-1 border border-gray-200 text-center">
                          <select data-field="basicRatings"
                            value={basicRatings[idx] ?? ''}
                            onChange={e => setBasicRatings(prev => ({ ...prev, [idx]: Number(e.target.value) }))}
                            className="w-full text-center text-sm border-0 outline-none bg-transparent py-1 cursor-pointer"
                          >
                            <option value="">--</option>
                            {[1, 2, 3, 4, 5].map(v => <option key={v} value={v}>{v}</option>)}
                          </select>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}

          {/* Step 2 – KPIs */}
          {step === 2 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  Key Performance Indicators (KPIs) (Measurable Results)
                </div>
              </div>
              <p className="text-xs text-gray-500 italic">Rate the company based on supporting documents (Financial Statements, Pitch Deck, IP Filings, Contracts, etc.).</p>

              {KPI_CATEGORIES.map((cat, catIdx) => (
                <div key={catIdx} className="space-y-1">
                  <h3 className="text-xs font-bold text-[#9b1b5a] uppercase tracking-wide mt-2">{cat.category}</h3>
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm border border-gray-200">
                      <thead>
                        <tr className="bg-gray-50">
                          <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[20%] text-xs">Indicators</th>
                          <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[55%] text-xs">Rationale / Comments</th>
                          <th className="text-center px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[12%] text-xs">Rating</th>
                        </tr>
                      </thead>
                      <tbody>
                        {cat.items.map((item, itemIdx) => {
                          const key = `${catIdx}-${itemIdx}`;
                          return (
                            <tr key={itemIdx}>
                              <td className="px-3 py-2 text-gray-800 border border-gray-200 font-medium text-xs">{item.indicator}</td>
                              <td className="px-1 py-1 border border-gray-200">
                                <textarea data-field="kpiComments"
                                  value={kpiComments[key] ?? item.rationale}
                                  onChange={e => setKpiComments(prev => ({ ...prev, [key]: e.target.value }))}
                                  rows={2}
                                  className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent resize-none"
                                />
                              </td>
                              <td className="px-1 py-1 border border-gray-200 text-center">
                                <select data-field="kpiRatings"
                                  value={kpiRatings[key] ?? ''}
                                  onChange={e => setKpiRatings(prev => ({ ...prev, [key]: Number(e.target.value) }))}
                                  className="w-full text-center text-sm border-0 outline-none bg-transparent py-1 cursor-pointer"
                                >
                                  <option value="">--</option>
                                  {[1, 2, 3, 4, 5].map(v => <option key={v} value={v}>{v}</option>)}
                                </select>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))}

              {/* Attachments checklist */}
              <div className="mt-4">
                <h3 className="text-sm font-semibold text-gray-800 mb-2">Attachments</h3>
                <ul className="space-y-1">
                  {FORM11_ATTACHMENTS.map((att, i) => (
                    <li key={i} className="text-xs text-gray-600 flex items-start gap-2">
                      <span className="text-gray-400 mt-0.5">•</span> {att}
                    </li>
                  ))}
                </ul>
              </div>
            </>
          )}

          {/* Step 3 – Recommendations & Authorization */}
          {step === 3 && (
            <>
              <div className="rounded-lg overflow-hidden">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  Recommendations
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-200">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[30%] text-xs">Area</th>
                      <th className="text-left px-3 py-2 font-medium text-gray-600 border border-gray-200 w-[70%] text-xs">Specific Recommendation</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td className="px-1 py-1 border border-gray-200">
                        <input data-field="recArea" type="text" value={recArea} onChange={e => setRecArea(e.target.value)} placeholder="e.g., Technology, Market" className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                      </td>
                      <td className="px-1 py-1 border border-gray-200">
                        <input data-field="recSpecific" type="text" value={recSpecific} onChange={e => setRecSpecific(e.target.value)} placeholder="e.g., Extend tenancy, Exit to commercialization, Need more R&D" className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                      </td>
                    </tr>
                    <tr>
                      <td className="px-3 py-2 text-gray-700 border border-gray-200 font-medium text-xs">Incubation Status</td>
                      <td className="px-1 py-1 border border-gray-200">
                        <input data-field="recIncubationStatus" type="text" value={recIncubationStatus} onChange={e => setRecIncubationStatus(e.target.value)} className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                      </td>
                    </tr>
                    <tr>
                      <td className="px-3 py-2 text-gray-700 border border-gray-200 font-medium text-xs">Next Steps / Action Plan</td>
                      <td className="px-1 py-1 border border-gray-200">
                        <input data-field="recNextSteps" type="text" value={recNextSteps} onChange={e => setRecNextSteps(e.target.value)} className="w-full px-2 py-1 text-xs border-0 outline-none bg-transparent" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              {/* Authorization */}
              <div className="rounded-lg overflow-hidden mt-4">
                <div className="px-4 py-2 text-white text-sm font-semibold" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
                  Authorization
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div className="space-y-3">
                  <p className="text-sm font-medium text-gray-700">Evaluated by:</p>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Name</label>
                    <input data-field="evalByName" type="text" value={evalByName} onChange={e => setEvalByName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-1 text-sm bg-transparent transition-colors" />
                  </div>
                  <p className="text-xs text-gray-400">(Signature Over Printed Name)</p>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Date</label>
                    <input data-field="evalByDate" type="date" value={evalByDate} onChange={e => setEvalByDate(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-1 text-sm bg-transparent transition-colors" />
                  </div>
                </div>
                <div className="space-y-3">
                  <p className="text-sm font-medium text-gray-700">MARIAN TBI Manager</p>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Name</label>
                    <input data-field="mgrName" type="text" value={mgrName} onChange={e => setMgrName(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-1 text-sm bg-transparent transition-colors" />
                  </div>
                  <p className="text-xs text-gray-400">(Signature Over Printed Name)</p>
                  <div>
                    <label className="block text-xs text-gray-500 mb-1">Date</label>
                    <input data-field="mgrDate" type="date" value={mgrDate} onChange={e => setMgrDate(e.target.value)} className="w-full border-b-2 border-gray-300 focus:border-[#FF2B5E] outline-none py-1 text-sm bg-transparent transition-colors" />
                  </div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div id="form11-print-area">
            <h1>Incubation Evaluation Form</h1>
            <div className="info"><span>Startup / Company:</span> {startupName}</div>
            <div className="info"><span>Technology / Focus Area:</span> {techFocus}</div>
            <div className="info"><span>Date of Evaluation:</span> {evalDate}</div>
            <div className="info"><span>Length of Tenancy:</span> {tenancyLength}</div>
            <div className="info"><span>Stage in Incubation:</span> {incubationStage}</div>

            <h2>Basic Indicators (TBI Service Utilization &amp; Alignment)</h2>
            <p className="note">Rating: 1-Not met, 2-Below Average, 3-Average, 4-Above Average, 5-Excellent</p>
            <table>
              <thead><tr><th>Criteria</th><th>Outcomes</th><th style={{ width: '10%' }}>Rating</th></tr></thead>
              <tbody>
                {BASIC_INDICATORS.map((ind, idx) => (
                  <tr key={idx}><td>{ind.criteria}</td><td>{ind.outcome}</td><td className="center">{basicRatings[idx] ?? ''}</td></tr>
                ))}
              </tbody>
            </table>

            <h2>Key Performance Indicators (KPIs)</h2>
            {KPI_CATEGORIES.map((cat, catIdx) => (
              <div key={catIdx}>
                <h3>{cat.category}</h3>
                <table>
                  <thead><tr><th>Indicators</th><th>Rationale / Comments</th><th style={{ width: '10%' }}>Rating</th></tr></thead>
                  <tbody>
                    {cat.items.map((item, itemIdx) => {
                      const key = `${catIdx}-${itemIdx}`;
                      return (
                        <tr key={itemIdx}><td>{item.indicator}</td><td>{kpiComments[key] || item.rationale}</td><td className="center">{kpiRatings[key] ?? ''}</td></tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ))}

            <h2>Attachments</h2>
            {FORM11_ATTACHMENTS.map((att, i) => <div key={i} className="att">• {att}</div>)}

            <h2>Recommendations</h2>
            <table>
              <thead><tr><th>Area</th><th>Specific Recommendation</th></tr></thead>
              <tbody>
                <tr><td>{recArea}</td><td>{recSpecific}</td></tr>
                <tr><td>Incubation Status</td><td>{recIncubationStatus}</td></tr>
                <tr><td>Next Steps / Action Plan</td><td>{recNextSteps}</td></tr>
              </tbody>
            </table>

            <br/>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <div className="sig">
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>Evaluated by:</span><br/>
                <br/><span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '9px' }}>(Signature Over Printed Name)</span><br/>
                <span style={{ fontSize: '9px' }}>Name: {evalByName}</span><br/>
                <span style={{ fontSize: '9px' }}>Date: {evalByDate || '____________'}</span>
              </div>
              <div className="sig">
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>MARIAN TBI Manager:</span><br/>
                <br/><span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '9px' }}>(Signature Over Printed Name)</span><br/>
                <span style={{ fontSize: '9px' }}>Name: {mgrName}</span><br/>
                <span style={{ fontSize: '9px' }}>Date: {mgrDate || '____________'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Form 12 Constants ─────────────────────────────────────
const EVAL_PARAMETERS = [
  { no: 1, name: 'Business Plan & Value Proposition', hint: 'Clarity of value, size of the Health-Tech market, potential social impact, and alignment with healthcare priorities.' },
  { no: 2, name: 'Market Research & Validation', hint: 'Depth of understanding of user needs (e.g., patients, hospitals, doctors), and evidence of problem/solution fit.' },
  { no: 3, name: 'Innovation / Competitive Advantage', hint: 'Distinctiveness of the solution; defense against competitors (local/global); clear unique selling proposition (USP) in the health sector.' },
  { no: 4, name: 'Technical Feasibility', hint: 'Realistic assessment of technical challenges, availability of required technology, and clear technical roadmap.' },
  { no: 5, name: 'Technology Readiness Level (TRL)', hint: 'Current TRL as per the scale below; verifiable proof of concept (PoC) or working components.' },
  { no: 6, name: 'Minimum Viable Product (MVP) Status', hint: 'Progress towards or existence of a functional prototype/MVP; ability to conduct pilot testing in a relevant environment.' },
  { no: 7, name: 'Scalability & Deployment Model', hint: 'Potential for mass-adoption; ease of replicating the ICT-based solution across multiple users/geographies; sustainability of the platform.' },
  { no: 8, name: 'Revenue & Sustainability Strategy', hint: 'Clear, viable monetization model (e.g., SaaS, subscription, service fee) and financial projections for self-sufficiency post-incubation.' },
  { no: 9, name: 'Investment Strategy/Status', hint: 'Realistic funding goals (grants, seed, angel) and current progress in securing investment.' },
  { no: 10, name: 'Skills & Commitment of Team', hint: 'Balance of technical and business skills (critical for a TBI); founder commitment; team\'s ability to execute the plan, particularly for Health-Tech/ICT development.' },
];

const RECOMMENDATION_OPTIONS = [
  { label: 'Pre-Incubation (Ideation/Validation)', note: 'Recommended for TRL 1-3. Needs further mentorship on business model and PoC.' },
  { label: 'Incubation (MVP/Growth)', note: 'Recommended for TRL 4-6. Ready to utilize TBI services for product development and market entry.' },
  { label: 'Acceleration (Market/Scale)', note: 'Recommended for TRL 7-9. Ready for investment and aggressive market scaling.' },
  { label: 'On Hold / Not Recommended', note: 'Clearly state the major gaps: e.g., Low TRL, Market size too small, Unviable business model.' },
];

const TRL_TABLE = [
  { trl: 1, desc: 'Basic principles observed', example: 'Scientific observations on a health problem; literature review of a potential ICT solution.' },
  { trl: 2, desc: 'Technology concept formulated', example: 'System architecture or algorithm drafted; envisioned application is speculative.' },
  { trl: 3, desc: 'Experimental proof of concept', example: 'Core algorithm or key software component validated with synthetic/lab data.' },
  { trl: 4, desc: 'Technology validated in lab', example: 'Prototype front-end/interface tested by a few developers; technology validated in a controlled environment.' },
  { trl: 5, desc: 'Technology validated in relevant environment', example: 'Beta-version of the platform (semi-integrated) tested with mock data in a simulated hospital setting.' },
  { trl: 6, desc: 'Technology demonstrated in relevant environment', example: 'Prototype system used by a small internal user group with real-world data; functionality verified.' },
  { trl: 7, desc: 'System model or prototype demonstration in operational environment', example: 'Fully integrated prototype used by a few genuine end-users (e.g., a single doctor/clinic) in a live operational setting.' },
  { trl: 8, desc: 'System complete and qualified', example: 'Final system (with necessary security/regulatory features) used in real conditions; ready for first commercial deployment.' },
  { trl: 9, desc: 'Actual system proven in operational environment', example: 'System successfully deployed and adopted by multiple end-users (hospitals/large clinics) for an extended period.' },
];

// ── Form 12 Modal ─────────────────────────────────────────
function Form12Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 3;
  const stepLabels = ['Header Info', 'Evaluation Parameters', 'Recommendation & TRL'];

  // Header
  const [startupName, setStartupName] = useState('');
  const [members, setMembers] = useState(['', '', '', '', '']);
  const [evalDate, setEvalDate] = useState('');
  const [incubationStage, setIncubationStage] = useState('');

  // Evaluation
  const [ratings, setRatings] = useState<Record<number, string>>({});
  const [observations, setObservations] = useState<Record<number, string>>({});

  // Recommendation
  const [selectedRec, setSelectedRec] = useState('');
  const [recReasons, setRecReasons] = useState<Record<string, string>>({});

  // Evaluator
  const [evaluatorName, setEvaluatorName] = useState('');
  const [evaluatorDate, setEvaluatorDate] = useState('');

  const printRef = useRef<HTMLDivElement>(null);

  const handlePrint = () => {
    if (!printRef.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 12 – Startup Incubation Evaluation</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; color: #222; }
      h2 { text-align: center; margin-bottom: 2px; font-size: 15px; }
      h3 { font-size: 12px; margin: 10px 0 4px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
      th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; font-size: 10px; }
      th { background: #f3f3f3; font-weight: bold; }
      .header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin-bottom: 12px; }
      .header-grid span { font-size: 10px; }
      .sig { display: inline-block; min-width: 200px; }
      .sig-line { display: inline-block; width: 180px; border-bottom: 1px solid #333; }
      .check { font-weight: bold; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  const setMember = (idx: number, val: string) => {
    const m = [...members];
    m[idx] = val;
    setMembers(m);
  };

  const stageOptions = [
    { value: '1', label: '1 - Application' },
    { value: '2', label: '2 - Pre-incubation' },
    { value: '3', label: '3 - Active Incubation' },
  ];

  useFormValues(12, () => ({
    startupName,
    members,
    evalDate,
    incubationStage,
    ratings,
    observations,
    selectedRec,
    recReasons,
    evaluatorName,
    evaluatorDate,
  }));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 12 – Startup Incubation Evaluation</h2>
            <p className="text-pink-100 text-xs mt-0.5">{stepLabels[step]} (Step {step + 1} of {totalSteps})</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1.5">
          <div className="h-1.5 rounded-r-full bg-[#FF2B5E] transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {/* ── Step 0: Header ── */}
          {step === 0 && (
            <div className="space-y-5">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Name of Startup / Company</label>
                <input data-field="startupName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={startupName} onChange={e => setStartupName(e.target.value)} />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {members.map((m, i) => (
                  <div key={i}>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Member {i + 1}</label>
                    <input data-field="members" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={m} onChange={e => setMember(i, e.target.value)} />
                  </div>
                ))}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Date of Evaluation</label>
                  <input data-field="evalDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={evalDate} onChange={e => setEvalDate(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Current Incubation Stage</label>
                  <select data-field="incubationStage" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={incubationStage} onChange={e => setIncubationStage(e.target.value)}>
                    <option value="">Select stage...</option>
                    {stageOptions.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                  </select>
                </div>
              </div>
            </div>
          )}

          {/* ── Step 1: Evaluation Parameters ── */}
          {step === 1 && (
            <div className="space-y-4">
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs font-semibold text-blue-800 mb-1">Rating Scale (1–5)</p>
                <ul className="text-xs text-blue-700 space-y-0.5">
                  <li><strong>1</strong> – Needs Major Work: Fails to meet basic requirements; critical gaps exist.</li>
                  <li><strong>2</strong> – Below Expectation: Key elements are present but poorly developed or unvalidated.</li>
                  <li><strong>3</strong> – Satisfactory: Meets basic TBI/market entry requirements; foundation is viable.</li>
                  <li><strong>4</strong> – Strong: Well-developed, validated, and shows high potential for growth.</li>
                  <li><strong>5</strong> – Exceptional: Market-ready/proven concept with a significant competitive edge.</li>
                </ul>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-300">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="border border-gray-300 px-2 py-2 text-left w-8">No.</th>
                      <th className="border border-gray-300 px-2 py-2 text-left">Parameters</th>
                      <th className="border border-gray-300 px-2 py-2 text-center w-24">Rating (1-5)</th>
                      <th className="border border-gray-300 px-2 py-2 text-left" style={{ minWidth: '220px' }}>Key Observations & Relevance to Health-Tech</th>
                    </tr>
                  </thead>
                  <tbody>
                    {EVAL_PARAMETERS.map(p => (
                      <tr key={p.no} className="hover:bg-gray-50">
                        <td className="border border-gray-300 px-2 py-2 text-center text-xs font-semibold">{p.no}</td>
                        <td className="border border-gray-300 px-2 py-2">
                          <span className="text-xs font-semibold">{p.name}</span>
                          <p className="text-[10px] text-gray-500 mt-0.5 italic">{p.hint}</p>
                        </td>
                        <td className="border border-gray-300 px-2 py-2 text-center">
                          <select data-field="ratings" className="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none w-16" value={ratings[p.no] || ''} onChange={e => setRatings({ ...ratings, [p.no]: e.target.value })}>
                            <option value="">—</option>
                            {[1, 2, 3, 4, 5].map(r => <option key={r} value={String(r)}>{r}</option>)}
                          </select>
                        </td>
                        <td className="border border-gray-300 px-2 py-2">
                          <textarea data-field="observations" className="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none resize-none" rows={2} value={observations[p.no] || ''} onChange={e => setObservations({ ...observations, [p.no]: e.target.value })} placeholder="Key observations..." />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* ── Step 2: Final Recommendation + Evaluator + TRL ── */}
          {step === 2 && (
            <div className="space-y-5">
              {/* Final Recommendation */}
              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-2">Final Recommendation</h3>
                <table className="w-full text-sm border border-gray-300">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="border border-gray-300 px-2 py-2 text-left">Final Recommendation</th>
                      <th className="border border-gray-300 px-2 py-2 text-center w-16">Check</th>
                      <th className="border border-gray-300 px-2 py-2 text-left">Reasons / Conditions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {RECOMMENDATION_OPTIONS.map(opt => (
                      <tr key={opt.label} className="hover:bg-gray-50">
                        <td className="border border-gray-300 px-2 py-2 text-xs font-semibold">{opt.label}</td>
                        <td className="border border-gray-300 px-2 py-2 text-center">
                          <input data-field="selectedRec" type="radio" name="form12rec" checked={selectedRec === opt.label} onChange={() => setSelectedRec(opt.label)} className="accent-[#FF2B5E]" />
                        </td>
                        <td className="border border-gray-300 px-2 py-2">
                          <textarea data-field="recReasons" className="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none resize-none" rows={2} value={recReasons[opt.label] || ''} onChange={e => setRecReasons({ ...recReasons, [opt.label]: e.target.value })} placeholder={opt.note} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Evaluator */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Evaluator's Name</label>
                  <input data-field="evaluatorName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={evaluatorName} onChange={e => setEvaluatorName(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Date</label>
                  <input data-field="evaluatorDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={evaluatorDate} onChange={e => setEvaluatorDate(e.target.value)} />
                </div>
              </div>

              {/* TRL Reference Table (read-only) */}
              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-2">Technology Readiness Level (TRL) – Standard Scale for Health-Tech</h3>
                <div className="overflow-x-auto">
                  <table className="w-full text-xs border border-gray-300">
                    <thead>
                      <tr className="bg-gray-50">
                        <th className="border border-gray-300 px-2 py-1 text-center w-12">TRL</th>
                        <th className="border border-gray-300 px-2 py-1 text-left">Description</th>
                        <th className="border border-gray-300 px-2 py-1 text-left">Example (Health-Tech)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {TRL_TABLE.map(t => (
                        <tr key={t.trl} className="hover:bg-gray-50">
                          <td className="border border-gray-300 px-2 py-1 text-center font-bold">{t.trl}</td>
                          <td className="border border-gray-300 px-2 py-1">{t.desc}</td>
                          <td className="border border-gray-300 px-2 py-1 text-gray-600 italic">{t.example}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef}>
            <h2>Form 12 – Startup Incubation Evaluation</h2>

            <div className="header-grid">
              <span><strong>Name of Startup / Company:</strong> {startupName || '____________'}</span>
              <span><strong>Date of Evaluation:</strong> {evalDate || '____________'}</span>
              {members.map((m, i) => (
                <span key={i}><strong>Member {i + 1}:</strong> {m || '____________'}</span>
              ))}
              <span><strong>Current Incubation Stage:</strong> {incubationStage ? stageOptions.find(s => s.value === incubationStage)?.label : '____________'}</span>
            </div>

            <h3>Evaluation Parameters</h3>
            <table>
              <thead>
                <tr><th style={{ width: '30px' }}>No.</th><th>Parameters</th><th style={{ width: '60px' }}>Rating</th><th>Key Observations & Relevance to Health-Tech</th></tr>
              </thead>
              <tbody>
                {EVAL_PARAMETERS.map(p => (
                  <tr key={p.no}>
                    <td style={{ textAlign: 'center' }}>{p.no}</td>
                    <td><strong>{p.name}</strong><br/><em style={{ fontSize: '9px', color: '#666' }}>{p.hint}</em></td>
                    <td style={{ textAlign: 'center' }}>{ratings[p.no] || ''}</td>
                    <td>{observations[p.no] || ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <h3>Final Recommendation</h3>
            <table>
              <thead>
                <tr><th>Final Recommendation</th><th style={{ width: '50px' }}>Check</th><th>Reasons / Conditions</th></tr>
              </thead>
              <tbody>
                {RECOMMENDATION_OPTIONS.map(opt => (
                  <tr key={opt.label}>
                    <td>{opt.label}</td>
                    <td style={{ textAlign: 'center' }} className="check">{selectedRec === opt.label ? '✓' : ''}</td>
                    <td>{recReasons[opt.label] || <em style={{ color: '#999' }}>{opt.note}</em>}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <br/>
            <div style={{ display: 'flex', justifyContent: 'flex-start', gap: '60px' }}>
              <div className="sig">
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>Evaluator's Name:</span> {evaluatorName || '____________'}<br/>
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>Signature:</span> <span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>Date:</span> {evaluatorDate || '____________'}
              </div>
            </div>

            <h3>Technology Readiness Level (TRL) – Standard Scale for Health-Tech</h3>
            <table>
              <thead>
                <tr><th style={{ width: '40px' }}>TRL</th><th>Description</th><th>Example (Specific to Health-Tech)</th></tr>
              </thead>
              <tbody>
                {TRL_TABLE.map(t => (
                  <tr key={t.trl}>
                    <td style={{ textAlign: 'center', fontWeight: 'bold' }}>{t.trl}</td>
                    <td>{t.desc}</td>
                    <td><em>{t.example}</em></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Form 13 Constants ─────────────────────────────────────────
const PART1_REQUIREMENTS = [
  { no: 1, name: 'Financial Dues (Settlement of all rent/fees/utilities)', statusOpts: ['Cleared', 'N/A'], signLabel: 'MARIAN TBI Finance/Accounting' },
  { no: 2, name: 'TBI Equipment & Facilities (Return of keys, badges, shared office/ICT equipment)', statusOpts: ['Cleared', 'N/A'], signLabel: 'TBI Manager' },
  { no: 3, name: 'Documentation & Reporting (Submission of final Incubation Evaluation Form & Exit Report)', statusOpts: ['Cleared'], signLabel: 'TBI Manager' },
  { no: 4, name: 'Legal / Contractual (Fulfillment of all terms in the Incubation Agreement)', statusOpts: ['Cleared'], signLabel: 'UIC Legal / Admin Office' },
];

const PART2_REQUIREMENTS = [
  { no: 1, name: 'Technology Transfer/IP Status (Final report on IP filings, ownership clarification, and royalty arrangements (if any) with UIC)', statusOpts: ['Cleared', 'N/A'], signLabel: 'UIC Technology Transfer Office (TTO) / IP Manager' },
  { no: 2, name: 'Use of UIC Lab/R&D Facilities (Settlement for use of specialized research or health-related labs)', statusOpts: ['Cleared', 'N/A'], signLabel: 'Relevant UIC Dept. Head/Lab Manager' },
  { no: 3, name: 'Final TRL Achievement (Indicate final verified TRL upon exit: TRL 7/8/9)', statusOpts: ['Verified'], signLabel: 'TBI Technical Expert/Mentor' },
  { no: 4, name: 'Exit Financial Summary (Final metrics on Investment Raised, Revenue, and Jobs Created during tenancy)', statusOpts: ['Submitted'], signLabel: 'TBI Manager' },
];

const EXIT_REASONS = [
  { value: 'graduation', label: 'Successful Graduation: Achieved market viability and TRL 7 or higher.' },
  { value: 'commercial', label: 'Full Commercial Deployment: Operating independently and generating sustained revenue.' },
  { value: 'other', label: 'Other (Specify):' },
];

// ── Form 13 Modal ─────────────────────────────────────────────
function Form13Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 3;
  const stepLabels = ['Header Info', 'Financial, Admin & IP Clearance', 'Certification of Clearance'];

  // Header
  const [startupName, setStartupName] = useState('');
  const [members, setMembers] = useState(['', '', '', '', '']);
  const [legalEntity, setLegalEntity] = useState('');
  const [programCohort, setProgramCohort] = useState('');
  const [dateEntry, setDateEntry] = useState('');
  const [dateExit, setDateExit] = useState('');

  // Part I
  const [p1Status, setP1Status] = useState<Record<number, string>>({});
  const [p1Date, setP1Date] = useState<Record<number, string>>({});
  const [p1Sign, setP1Sign] = useState<Record<number, string>>({});

  // Part II
  const [p2Status, setP2Status] = useState<Record<number, string>>({});
  const [p2Date, setP2Date] = useState<Record<number, string>>({});
  const [p2Sign, setP2Sign] = useState<Record<number, string>>({});

  // Certification
  const [exitReason, setExitReason] = useState('');
  const [exitOtherSpecify, setExitOtherSpecify] = useState('');
  const [tbiManagerName, setTbiManagerName] = useState('');
  const [tbiManagerDate, setTbiManagerDate] = useState('');
  const [uttoHeadName, setUttoHeadName] = useState('');
  const [uttoHeadDate, setUttoHeadDate] = useState('');

  const printRef13 = useRef<HTMLDivElement>(null);

  const setMember = (idx: number, val: string) => {
    const m = [...members];
    m[idx] = val;
    setMembers(m);
  };

  const handlePrint = () => {
    if (!printRef13.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 13 – Clearance Certificate</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; color: #222; }
      h2 { text-align: center; margin-bottom: 2px; font-size: 15px; }
      h3 { font-size: 12px; margin: 10px 0 4px; }
      p { font-size: 10px; margin: 4px 0; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
      th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; font-size: 10px; }
      th { background: #f3f3f3; font-weight: bold; }
      .header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin-bottom: 12px; }
      .header-grid span { font-size: 10px; }
      .sig { display: inline-block; min-width: 200px; }
      .sig-line { display: inline-block; width: 180px; border-bottom: 1px solid #333; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef13.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  const renderClearanceTable = (reqs: typeof PART1_REQUIREMENTS, statusMap: Record<number, string>, dateMap: Record<number, string>, signMap: Record<number, string>, setStatusMap: (v: Record<number, string>) => void, setDateMap: (v: Record<number, string>) => void, setSignMap: (v: Record<number, string>) => void, fieldKeys: { status: string; date: string; sign: string }) => (
    <div className="overflow-x-auto">
      <table className="w-full text-sm border border-gray-300">
        <thead>
          <tr className="bg-gray-50">
            <th className="border border-gray-300 px-2 py-2 text-left" style={{ minWidth: '200px' }}>Requirement</th>
            <th className="border border-gray-300 px-2 py-2 text-center w-32">Status</th>
            <th className="border border-gray-300 px-2 py-2 text-center w-28">Date Cleared</th>
            <th className="border border-gray-300 px-2 py-2 text-left" style={{ minWidth: '160px' }}>Authorized Signatory<br/><span className="text-[10px] font-normal text-gray-500">(Printed Name & Title)</span></th>
          </tr>
        </thead>
        <tbody>
          {reqs.map(r => (
            <tr key={r.no} className="hover:bg-gray-50">
              <td className="border border-gray-300 px-2 py-2">
                <span className="text-xs">{r.no}. {r.name}</span>
              </td>
              <td className="border border-gray-300 px-2 py-2 text-center">
                <div className="flex flex-wrap justify-center gap-2">
                  {r.statusOpts.map(opt => (
                    <label key={opt} className="flex items-center gap-1 text-xs cursor-pointer">
                      <input type="radio" data-field={fieldKeys.status} name={`clearance-${r.no}-${r.signLabel}`} checked={statusMap[r.no] === opt} onChange={() => setStatusMap({ ...statusMap, [r.no]: opt })} className="accent-[#FF2B5E]" />
                      {opt}
                    </label>
                  ))}
                </div>
              </td>
              <td className="border border-gray-300 px-2 py-2 text-center">
                <input type="date" data-field={fieldKeys.date} className="border border-gray-200 rounded px-1 py-0.5 text-xs w-full focus:ring-2 focus:ring-pink-400 focus:outline-none" value={dateMap[r.no] || ''} onChange={e => setDateMap({ ...dateMap, [r.no]: e.target.value })} />
              </td>
              <td className="border border-gray-300 px-2 py-2">
                <input type="text" data-field={fieldKeys.sign} className="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none" value={signMap[r.no] || ''} onChange={e => setSignMap({ ...signMap, [r.no]: e.target.value })} placeholder={r.signLabel} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );

  useFormValues(13, () => ({
    startupName,
    members,
    legalEntity,
    programCohort,
    dateEntry,
    dateExit,
    p1Status,
    p1Date,
    p1Sign,
    p2Status,
    p2Date,
    p2Sign,
    exitReason,
    exitOtherSpecify,
    tbiManagerName,
    tbiManagerDate,
    uttoHeadName,
    uttoHeadDate,
  }));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 13 – Clearance Certificate</h2>
            <p className="text-pink-100 text-xs mt-0.5">{stepLabels[step]} (Step {step + 1} of {totalSteps})</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1.5">
          <div className="h-1.5 rounded-r-full bg-[#FF2B5E] transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {/* ── Step 0: Header Info ── */}
          {step === 0 && (
            <div className="space-y-5">
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs text-blue-800 leading-relaxed">This Clearance Certificate is issued to the company listed below, verifying that all contractual and operational obligations with the MARIAN TBI and the University of the Immaculate Conception have been satisfactorily met for the purpose of <strong>Graduation / Exit</strong> from the Incubation Program.</p>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Name of Startup</label>
                <input data-field="startupName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={startupName} onChange={e => setStartupName(e.target.value)} />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {members.map((m, i) => (
                  <div key={i}>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Member {i + 1}</label>
                    <input data-field="members" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={m} onChange={e => setMember(i, e.target.value)} />
                  </div>
                ))}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Legal Entity</label>
                  <select data-field="legalEntity" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={legalEntity} onChange={e => setLegalEntity(e.target.value)}>
                    <option value="">Select...</option>
                    <option value="Corporation">Corporation</option>
                    <option value="Sole Proprietorship">Sole Proprietorship</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Program / Cohort</label>
                  <input data-field="programCohort" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={programCohort} onChange={e => setProgramCohort(e.target.value)} />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Date of Entry</label>
                  <input data-field="dateEntry" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={dateEntry} onChange={e => setDateEntry(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Date of Exit / Graduation</label>
                  <input data-field="dateExit" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={dateExit} onChange={e => setDateExit(e.target.value)} />
                </div>
              </div>
            </div>
          )}

          {/* ── Step 1: Part I & Part II ── */}
          {step === 1 && (
            <div className="space-y-6">
              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-1">Part I: Financial and Administrative Clearance</h3>
                <p className="text-xs text-gray-500 mb-3">The following departments/offices confirm that the Incubatee has settled all outstanding dues and fulfilled administrative requirements:</p>
                {renderClearanceTable(PART1_REQUIREMENTS, p1Status, p1Date, p1Sign, setP1Status, setP1Date, setP1Sign, { status: 'p1Status', date: 'p1Date', sign: 'p1Sign' })}
              </div>

              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-1">Part II: Technology and Intellectual Property (IP) Clearance</h3>
                <p className="text-xs text-gray-500 mb-3">This section confirms the status of the startup's intellectual output and its relationship with the University's resources:</p>
                {renderClearanceTable(PART2_REQUIREMENTS, p2Status, p2Date, p2Sign, setP2Status, setP2Date, setP2Sign, { status: 'p2Status', date: 'p2Date', sign: 'p2Sign' })}
              </div>
            </div>
          )}

          {/* ── Step 2: Certification of Clearance ── */}
          {step === 2 && (
            <div className="space-y-5">
              <div className="bg-green-50 border border-green-200 rounded-lg p-3">
                <h3 className="font-bold text-sm text-green-800 mb-1">Certification of Clearance</h3>
                <p className="text-xs text-green-700">This certifies that the above-named startup has successfully completed all necessary procedures and is formally <strong>CLEARED</strong> from the MARIAN TBI Incubation Program.</p>
              </div>

              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-2">Reason for Exit</h3>
                <div className="space-y-2">
                  {EXIT_REASONS.map(r => (
                    <label key={r.value} className="flex items-start gap-2 cursor-pointer">
                      <input data-field="exitReason" type="radio" name="exitReason" checked={exitReason === r.value} onChange={() => setExitReason(r.value)} className="accent-[#FF2B5E] mt-0.5" />
                      <span className="text-sm">{r.label}</span>
                    </label>
                  ))}
                  {exitReason === 'other' && (
                    <input data-field="exitOtherSpecify" type="text" className="ml-6 w-full max-w-md border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" placeholder="Specify reason..." value={exitOtherSpecify} onChange={e => setExitOtherSpecify(e.target.value)} />
                  )}
                </div>
              </div>

              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-3">Authorized Signatures</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="border border-gray-200 rounded-lg p-4 space-y-3">
                    <p className="text-xs font-bold text-gray-700 uppercase">TBI Manager</p>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Printed Name</label>
                      <input data-field="tbiManagerName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={tbiManagerName} onChange={e => setTbiManagerName(e.target.value)} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                      <input data-field="tbiManagerDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={tbiManagerDate} onChange={e => setTbiManagerDate(e.target.value)} />
                    </div>
                  </div>
                  <div className="border border-gray-200 rounded-lg p-4 space-y-3">
                    <p className="text-xs font-bold text-gray-700 uppercase">Head, UIC Technology Transfer Office (UTTO) / IP Manager</p>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Printed Name</label>
                      <input data-field="uttoHeadName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={uttoHeadName} onChange={e => setUttoHeadName(e.target.value)} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                      <input data-field="uttoHeadDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={uttoHeadDate} onChange={e => setUttoHeadDate(e.target.value)} />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef13}>
            <h2>Form 13 – Clearance Certificate</h2>
            <p style={{ textAlign: 'center', fontSize: '9px', marginBottom: '10px' }}>This Clearance Certificate is issued to the company listed below, verifying that all contractual and operational obligations with the MARIAN TBI and the University of the Immaculate Conception have been satisfactorily met for the purpose of Graduation / Exit from the Incubation Program.</p>

            <div className="header-grid">
              <span><strong>Name of Startup:</strong> {startupName || '____________'}</span>
              <span><strong>Legal Entity:</strong> {legalEntity || '____________'}</span>
              {members.map((m, i) => (
                <span key={i}><strong>Member {i + 1}:</strong> {m || '____________'}</span>
              ))}
              <span><strong>Program / Cohort:</strong> {programCohort || '____________'}</span>
              <span><strong>Date of Entry:</strong> {dateEntry || '____________'}</span>
              <span><strong>Date of Exit / Graduation:</strong> {dateExit || '____________'}</span>
            </div>

            <h3>Part I: Financial and Administrative Clearance</h3>
            <table>
              <thead>
                <tr><th>Requirement</th><th style={{ width: '80px' }}>Status</th><th style={{ width: '80px' }}>Date Cleared</th><th>Authorized Signatory</th></tr>
              </thead>
              <tbody>
                {PART1_REQUIREMENTS.map(r => (
                  <tr key={r.no}>
                    <td>{r.no}. {r.name}</td>
                    <td style={{ textAlign: 'center' }}>{p1Status[r.no] ? `☑ ${p1Status[r.no]}` : '☐'}</td>
                    <td style={{ textAlign: 'center' }}>{p1Date[r.no] || ''}</td>
                    <td>{p1Sign[r.no] || r.signLabel}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <h3>Part II: Technology and Intellectual Property (IP) Clearance</h3>
            <table>
              <thead>
                <tr><th>Requirement</th><th style={{ width: '80px' }}>Status</th><th style={{ width: '80px' }}>Date Cleared</th><th>Authorized Signatory</th></tr>
              </thead>
              <tbody>
                {PART2_REQUIREMENTS.map(r => (
                  <tr key={r.no}>
                    <td>{r.no}. {r.name}</td>
                    <td style={{ textAlign: 'center' }}>{p2Status[r.no] ? `☑ ${p2Status[r.no]}` : '☐'}</td>
                    <td style={{ textAlign: 'center' }}>{p2Date[r.no] || ''}</td>
                    <td>{p2Sign[r.no] || r.signLabel}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <h3>Certification of Clearance</h3>
            <p>This certifies that the above-named startup has successfully completed all necessary procedures and is formally <strong>CLEARED</strong> from the MARIAN TBI Incubation Program.</p>
            <p><strong>Reason for Exit:</strong></p>
            {EXIT_REASONS.map(r => (
              <p key={r.value}>{exitReason === r.value ? '☑' : '☐'} {r.label} {r.value === 'other' && exitReason === 'other' ? exitOtherSpecify : ''}</p>
            ))}

            <br/>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <div className="sig">
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>TBI MANAGER</span><br/>
                <br/><span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '9px' }}>(Signature Over Printed Name)</span><br/>
                <span style={{ fontSize: '9px' }}>Name: {tbiManagerName || '____________'}</span><br/>
                <span style={{ fontSize: '9px' }}>Date: {tbiManagerDate || '____________'}</span>
              </div>
              <div className="sig">
                <span style={{ fontSize: '10px', fontWeight: 'bold' }}>HEAD, UIC Technology Transfer Office (UTTO) / IP Manager</span><br/>
                <br/><span className="sig-line">&nbsp;</span><br/>
                <span style={{ fontSize: '9px' }}>(Signature Over Printed Name)</span><br/>
                <span style={{ fontSize: '9px' }}>Name: {uttoHeadName || '____________'}</span><br/>
                <span style={{ fontSize: '9px' }}>Date: {uttoHeadDate || '____________'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Form 14 Constants ─────────────────────────────────────────
const BP_SECTIONS = [
  { key: 'overview', num: 'I', title: 'Overview', maxWords: 200, prompt: 'Details of the company/team\u2019s name, company\u2019s/team\u2019s vision, and mission. Include the why, the problem you are trying to address, and the purpose of the initiative.' },
  { key: 'products', num: 'II', title: 'Products/Services', maxWords: 200, prompt: 'Brief description of the product/service and the price. Also include your business model/concept, value proposition, and how your model works.' },
  { key: 'marketing', num: 'III', title: 'Marketing', maxWords: 300, prompt: 'Discuss marketing goals and strategies. How will you get the word out about your product/service? What are the major channels that you will focus on the varied advertising methods and when to implement them?' },
  { key: 'financial', num: 'IV', title: 'Financial Information', maxWords: 300, prompt: 'Describe your financial goals. How much money would you need as working capital? How will you fund your business? And how much money you would need to make it sustainable.' },
  { key: 'team', num: 'V', title: 'Team', maxWords: 200, prompt: 'Who are the individuals that make up your team? What are their job descriptions and responsibilities for your start-up and how it can contribute towards the success of your business?' },
];

// ── Form 14 Modal ─────────────────────────────────────────────
function Form14Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 2;
  const stepLabels = ['Sections I–III', 'Sections IV–V'];

  const [sectionTexts, setSectionTexts] = useState<Record<string, string>>({});

  const printRef14 = useRef<HTMLDivElement>(null);

  const wordCount = (text: string) => text.trim() ? text.trim().split(/\s+/).length : 0;

  const handlePrint = () => {
    if (!printRef14.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 14 – Business Plan Format</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 30px; color: #222; }
      h2 { text-align: center; margin-bottom: 2px; font-size: 15px; }
      h3 { font-size: 12px; margin: 14px 0 4px; }
      p { font-size: 10px; margin: 4px 0; line-height: 1.5; }
      .subtitle { text-align: center; font-size: 9px; color: #555; margin-bottom: 16px; }
      .section { margin-bottom: 14px; }
      .prompt { font-size: 9px; color: #666; font-style: italic; margin-bottom: 4px; }
      .content { font-size: 10px; white-space: pre-wrap; line-height: 1.5; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef14.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  const renderSection = (sec: typeof BP_SECTIONS[number]) => {
    const text = sectionTexts[sec.key] || '';
    const wc = wordCount(text);
    const overLimit = wc > sec.maxWords;
    return (
      <div key={sec.key} className="space-y-2">
        <h3 className="font-bold text-sm text-gray-800">{sec.num}. {sec.title} <span className="text-xs font-normal text-gray-500">(max {sec.maxWords} words)</span></h3>
        <p className="text-xs text-gray-500 italic">{sec.prompt}</p>
        <textarea data-field="sectionTexts"
          className={`w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none resize-y ${overLimit ? 'border-red-400 bg-red-50' : 'border-gray-300'}`}
          rows={6}
          value={text}
          onChange={e => setSectionTexts({ ...sectionTexts, [sec.key]: e.target.value })}
          placeholder={`Enter ${sec.title.toLowerCase()} here...`}
        />
        <div className="flex justify-end">
          <span className={`text-xs ${overLimit ? 'text-red-500 font-semibold' : 'text-gray-400'}`}>{wc} / {sec.maxWords} words</span>
        </div>
      </div>
    );
  };

  useFormValues(14, () => ({
    sectionTexts,
  }));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 14 – Business Plan Format</h2>
            <p className="text-pink-100 text-xs mt-0.5">{stepLabels[step]} (Step {step + 1} of {totalSteps})</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1.5">
          <div className="h-1.5 rounded-r-full bg-[#FF2B5E] transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {step === 0 && (
            <div className="space-y-6">
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs text-blue-800 leading-relaxed">This provides a general overview of you, your business, and what you are trying to develop or incubate. <strong>Maximum of 5 pages</strong> excluding the cover page. Only include the essentials of your business idea. Be direct, straightforward, and stick to the facts.</p>
              </div>
              {BP_SECTIONS.slice(0, 3).map(sec => renderSection(sec))}
            </div>
          )}

          {step === 1 && (
            <div className="space-y-6">
              {BP_SECTIONS.slice(3).map(sec => renderSection(sec))}
            </div>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef14}>
            <h2>Business Plan Format (for Incubation)</h2>
            <p className="subtitle">Maximum of 5 pages excluding the cover page. Only include the essentials of your business idea. Be direct, straightforward, and stick to the facts.</p>

            {BP_SECTIONS.map(sec => (
              <div key={sec.key} className="section">
                <h3>{sec.num}. {sec.title}</h3>
                <p className="prompt">{sec.prompt}</p>
                <p className="content">{sectionTexts[sec.key] || '(No content entered)'}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Form 15 Constants ─────────────────────────────────────────
const EXIT_CRITERIA_A: { no: number; text: string; hasFill?: string }[] = [
  { no: 1, text: 'Completion of stipulated incubation period and achievement of all agreed-upon Key Performance Indicators (KPIs) in the Incubation Agreement.' },
  { no: 2, text: 'Successful Fundraising from Angel Investors, Venture Capital Funds, or other accredited investors (exceeding ₱___ Million or equivalent).', hasFill: 'amount' },
  { no: 3, text: 'Achieved High Technology Readiness Level (TRL) and transitioned into the commercial phase (TRL 7 or higher and revenue generating). (Specific to Technology TBI)' },
  { no: 4, text: 'Annual revenue exceeding (_____) or achieving a pre-determined valuation threshold (≥ ₱___ Million).', hasFill: 'revenueThreshold' },
  { no: 5, text: 'Significant corporate action (acquisition, merger, amalgamation, reorganization, public issue) that substantially alters the company\'s profile.' },
  { no: 6, text: 'Incubatee\'s decision to voluntarily exit the program and all TBI service utilization has ceased.' },
];

const EXIT_CRITERIA_B: { no: number; text: string }[] = [
  { no: 7, text: 'Underperformance or unviability of the business proposition (ex. failure to meet critical milestones over two consecutive quarterly reviews).' },
  { no: 8, text: 'Failure to maintain a Health-Tech/ICT focus as agreed upon in the initial proposal.' },
  { no: 9, text: 'Change in the promoter/founder team or substantial management overhaul without MARIAN TBI\'s consent.' },
  { no: 10, text: 'Non-compliance with the terms and conditions of the University of the Immaculate Conception (UIC) or MARIAN TBI (including financial arrears, misuse of facilities, or ethical breaches).' },
  { no: 11, text: 'Any other critical reasons deemed necessary by the MARIAN TBI Management for the company to leave.' },
];

// ── Form 15 Modal ─────────────────────────────────────────────
function Form15Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 3;
  const stepLabels = ['Header Info', 'Exit Criteria', 'Remarks & Authorization'];

  // Header
  const [startupName, setStartupName] = useState('');
  const [members, setMembers] = useState(['', '', '', '', '']);
  const [email, setEmail] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [allotmentNumber, setAllotmentNumber] = useState('');
  const [evalDate, setEvalDate] = useState('');

  // Exit criteria
  const [checkedCriteria, setCheckedCriteria] = useState<Record<number, boolean>>({});
  const [fillAmount, setFillAmount] = useState('');
  const [fillRevenue, setFillRevenue] = useState('');

  // Remarks
  const [primaryTrigger, setPrimaryTrigger] = useState('');
  const [remarksPerformance, setRemarksPerformance] = useState('');
  const [postIncubationPlan, setPostIncubationPlan] = useState('');

  // Authorization
  const [tbiManagerName, setTbiManagerName] = useState('');
  const [tbiManagerDate, setTbiManagerDate] = useState('');
  const [rpicDirectorName, setRpicDirectorName] = useState('');
  const [rpicDirectorDate, setRpicDirectorDate] = useState('');

  const printRef15 = useRef<HTMLDivElement>(null);

  const setMember = (idx: number, val: string) => {
    const m = [...members];
    m[idx] = val;
    setMembers(m);
  };

  const toggleCriteria = (no: number) => setCheckedCriteria({ ...checkedCriteria, [no]: !checkedCriteria[no] });

  const handlePrint = () => {
    if (!printRef15.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 15 – Incubation Exit Form</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; color: #222; }
      h2 { text-align: center; margin-bottom: 2px; font-size: 15px; }
      h3 { font-size: 12px; margin: 10px 0 4px; }
      h4 { font-size: 11px; margin: 8px 0 4px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
      th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; font-size: 10px; }
      th { background: #f3f3f3; font-weight: bold; }
      .header-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin-bottom: 12px; }
      .header-grid span { font-size: 10px; }
      .sig { display: inline-block; min-width: 200px; }
      .sig-line { display: inline-block; width: 180px; border-bottom: 1px solid #333; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef15.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  useFormValues(15, () => ({
    startupName,
    members,
    email,
    contactNumber,
    allotmentNumber,
    evalDate,
    checkedCriteria,
    fillAmount,
    fillRevenue,
    primaryTrigger,
    remarksPerformance,
    postIncubationPlan,
    tbiManagerName,
    tbiManagerDate,
    rpicDirectorName,
    rpicDirectorDate,
  }));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 15 – Incubation Exit Form</h2>
            <p className="text-pink-100 text-xs mt-0.5">{stepLabels[step]} (Step {step + 1} of {totalSteps})</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1.5">
          <div className="h-1.5 rounded-r-full bg-[#FF2B5E] transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {/* ── Step 0: Header Info ── */}
          {step === 0 && (
            <div className="space-y-5">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Name of Startup / Company</label>
                <input data-field="startupName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={startupName} onChange={e => setStartupName(e.target.value)} />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {members.map((m, i) => (
                  <div key={i}>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Member {i + 1}</label>
                    <input data-field="members" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={m} onChange={e => setMember(i, e.target.value)} />
                  </div>
                ))}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Email Address</label>
                  <input data-field="email" type="email" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={email} onChange={e => setEmail(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Contact Number</label>
                  <input data-field="contactNumber" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={contactNumber} onChange={e => setContactNumber(e.target.value)} />
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Incubation Support Allotment Number</label>
                  <input data-field="allotmentNumber" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={allotmentNumber} onChange={e => setAllotmentNumber(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Date of Final Evaluation</label>
                  <input data-field="evalDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={evalDate} onChange={e => setEvalDate(e.target.value)} />
                </div>
              </div>
            </div>
          )}

          {/* ── Step 1: Exit Criteria ── */}
          {step === 1 && (
            <div className="space-y-5">
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs text-blue-800">This section identifies the primary trigger for the company's departure. Multiple criteria may be ticked, but the main one should be noted in the remarks.</p>
              </div>

              {/* Section A */}
              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2">A. Successful Graduation / Voluntary Exit</h3>
                <div className="space-y-3">
                  {EXIT_CRITERIA_A.map(c => (
                    <div key={c.no} className="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50">
                      <input data-field="checkedCriteria" type="checkbox" checked={!!checkedCriteria[c.no]} onChange={() => toggleCriteria(c.no)} className="accent-[#FF2B5E] mt-1" />
                      <div className="flex-1">
                        <span className="text-xs"><strong>{c.no}.</strong> {c.text}</span>
                        {c.hasFill === 'amount' && checkedCriteria[c.no] && (
                          <div className="mt-1">
                            <input data-field="fillAmount" type="text" className="border border-gray-300 rounded px-2 py-1 text-xs w-48 focus:ring-2 focus:ring-pink-400 focus:outline-none" placeholder="Amount in ₱ Million" value={fillAmount} onChange={e => setFillAmount(e.target.value)} />
                          </div>
                        )}
                        {c.hasFill === 'revenueThreshold' && checkedCriteria[c.no] && (
                          <div className="mt-1 flex gap-2">
                            <input data-field="fillRevenue" type="text" className="border border-gray-300 rounded px-2 py-1 text-xs w-48 focus:ring-2 focus:ring-pink-400 focus:outline-none" placeholder="Revenue / Valuation threshold" value={fillRevenue} onChange={e => setFillRevenue(e.target.value)} />
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Section B */}
              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2">B. Forced Exit / Termination / Non-Compliance</h3>
                <div className="space-y-3">
                  {EXIT_CRITERIA_B.map(c => (
                    <div key={c.no} className="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50">
                      <input data-field="checkedCriteria" type="checkbox" checked={!!checkedCriteria[c.no]} onChange={() => toggleCriteria(c.no)} className="accent-[#FF2B5E] mt-1" />
                      <span className="text-xs flex-1"><strong>{c.no}.</strong> {c.text}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* ── Step 2: Remarks & Authorization ── */}
          {step === 2 && (
            <div className="space-y-5">
              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-3">Remarks and Post-Incubation Plan</h3>
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Primary Exit Trigger (Summary)</label>
                    <textarea data-field="primaryTrigger" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none resize-none" rows={2} value={primaryTrigger} onChange={e => setPrimaryTrigger(e.target.value)} placeholder="State the main exit criteria number and brief summary..." />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Remarks on Performance</label>
                    <p className="text-[10px] text-gray-500 italic mb-1">Briefly summarize the company's performance and impact, e.g., "Graduated with 8 new jobs created and TRL 8."</p>
                    <textarea data-field="remarksPerformance" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none resize-none" rows={3} value={remarksPerformance} onChange={e => setRemarksPerformance(e.target.value)} />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-700 mb-1">Post-Incubation Plan (Future Tracking)</label>
                    <p className="text-[10px] text-gray-500 italic mb-1">Note the company's new location, post-program monitoring commitment, and if they will join the TBI Alumni Network.</p>
                    <textarea data-field="postIncubationPlan" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none resize-none" rows={3} value={postIncubationPlan} onChange={e => setPostIncubationPlan(e.target.value)} />
                  </div>
                </div>
              </div>

              <div>
                <h3 className="font-bold text-sm text-gray-800 mb-3">Authorization and Verification</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="border border-gray-200 rounded-lg p-4 space-y-3">
                    <p className="text-xs font-bold text-gray-700 uppercase">Verified by: TBI Manager</p>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Printed Name</label>
                      <input data-field="tbiManagerName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={tbiManagerName} onChange={e => setTbiManagerName(e.target.value)} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                      <input data-field="tbiManagerDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={tbiManagerDate} onChange={e => setTbiManagerDate(e.target.value)} />
                    </div>
                  </div>
                  <div className="border border-gray-200 rounded-lg p-4 space-y-3">
                    <p className="text-xs font-bold text-gray-700 uppercase">Approved by: RPIC Director</p>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Printed Name</label>
                      <input data-field="rpicDirectorName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={rpicDirectorName} onChange={e => setRpicDirectorName(e.target.value)} />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                      <input data-field="rpicDirectorDate" type="date" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={rpicDirectorDate} onChange={e => setRpicDirectorDate(e.target.value)} />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef15}>
            <h2>Incubation Exit (Graduation/Termination) Form</h2>
            <p style={{ textAlign: 'center', fontSize: '9px', marginBottom: '8px' }}>MARIAN TBI - UIC</p>

            <div className="header-grid">
              <span><strong>Name of Startup / Company:</strong> {startupName || '____________'}</span>
              <span><strong>Email Address:</strong> {email || '____________'}</span>
              {members.map((m, i) => (
                <span key={i}><strong>Member {i + 1}:</strong> {m || '____________'}</span>
              ))}
              <span><strong>Contact Number:</strong> {contactNumber || '____________'}</span>
              <span><strong>Incubation Support Allotment Number:</strong> {allotmentNumber || '____________'}</span>
              <span><strong>Date of Final Evaluation:</strong> {evalDate || '____________'}</span>
            </div>

            <h3>Details of Criteria for Exit of Startup from Incubation Support</h3>
            <h4>A. Successful Graduation / Voluntary Exit</h4>
            <table>
              <thead>
                <tr><th style={{ width: '30px' }}>No.</th><th>Parameters</th><th style={{ width: '50px' }}>Tick</th></tr>
              </thead>
              <tbody>
                {EXIT_CRITERIA_A.map(c => (
                  <tr key={c.no}>
                    <td style={{ textAlign: 'center' }}>{c.no}</td>
                    <td>{c.text}{c.hasFill === 'amount' && checkedCriteria[c.no] ? ` [₱${fillAmount} Million]` : ''}{c.hasFill === 'revenueThreshold' && checkedCriteria[c.no] ? ` [${fillRevenue}]` : ''}</td>
                    <td style={{ textAlign: 'center', fontWeight: 'bold' }}>{checkedCriteria[c.no] ? '✓' : ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <h4>B. Forced Exit / Termination / Non-Compliance</h4>
            <table>
              <thead>
                <tr><th style={{ width: '30px' }}>No.</th><th>Parameters</th><th style={{ width: '50px' }}>Tick</th></tr>
              </thead>
              <tbody>
                {EXIT_CRITERIA_B.map(c => (
                  <tr key={c.no}>
                    <td style={{ textAlign: 'center' }}>{c.no}</td>
                    <td>{c.text}</td>
                    <td style={{ textAlign: 'center', fontWeight: 'bold' }}>{checkedCriteria[c.no] ? '✓' : ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <h3>Remarks and Post-Incubation Plan</h3>
            <table>
              <thead>
                <tr><th>Section</th><th>Details</th></tr>
              </thead>
              <tbody>
                <tr><td>Primary Exit Trigger (Summary)</td><td>{primaryTrigger || ''}</td></tr>
                <tr><td>Remarks on Performance</td><td>{remarksPerformance || ''}</td></tr>
                <tr><td>Post-Incubation Plan (Future Tracking)</td><td>{postIncubationPlan || ''}</td></tr>
              </tbody>
            </table>

            <h3>Authorization and Verification</h3>
            <table>
              <thead>
                <tr><th>Role</th><th>Name (Printed)</th><th>Signature</th><th>Date</th></tr>
              </thead>
              <tbody>
                <tr>
                  <td>Verified by: TBI Manager</td>
                  <td>{tbiManagerName || '____________'}</td>
                  <td>(Signature Over Printed Name)</td>
                  <td>{tbiManagerDate || '____________'}</td>
                </tr>
                <tr>
                  <td>Approved by: RPIC Director</td>
                  <td>{rpicDirectorName || '____________'}</td>
                  <td>(Signature Over Printed Name)</td>
                  <td>{rpicDirectorDate || '____________'}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Form 16 Modal ─────────────────────────────────────────────
function Form16Modal({ onClose }: { onClose: () => void }) {
  const printRef16 = useRef<HTMLDivElement>(null);

  const handlePrint = () => {
    if (!printRef16.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 16 – TBI Code of Conduct</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 30px; color: #222; }
      h2 { text-align: center; margin-bottom: 4px; font-size: 15px; }
      h3 { font-size: 12px; margin: 16px 0 6px; }
      p { font-size: 10px; margin: 6px 0; line-height: 1.6; text-align: justify; }
      .subtitle { text-align: center; font-size: 10px; color: #555; margin-bottom: 14px; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef16.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  useFormValues(16, () => ({}));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 16 – TBI Code of Conduct</h2>
            <p className="text-pink-100 text-xs mt-0.5">MARIAN TBI</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Section I */}
          <div>
            <h3 className="font-bold text-sm text-gray-800 mb-2 flex items-center gap-2">
              <span className="bg-[#FF2B5E] text-white text-xs font-bold px-2 py-0.5 rounded">I</span>
              Principles of Engagement and Integrity
            </h3>
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <p className="text-sm text-gray-700 leading-relaxed text-justify">
                This Code of Conduct outlines the ethical and professional standards required of all individuals participating in the MARIAN TBI ecosystem, including Incubatees, Mentors, Advisors, and TBI Staff. All parties must act with integrity, honesty, and good faith in all dealings, ensuring that statements regarding intellectual property (IP), financial status, or project progress are truthful and accurate.
              </p>
              <p className="text-sm text-gray-700 leading-relaxed text-justify mt-3">
                The TBI maintains a strict policy against discrimination, harassment, and retaliation, requiring that all interactions be conducted with respect and inclusivity, irrespective of personal background. Confidentiality is paramount, and all engagements are governed by the Non-Disclosure Agreement found in Annex 16; proprietary, financial, and technical information shared within the TBI environment must not be used for personal gain or to benefit third parties.
              </p>
            </div>
          </div>

          {/* Section II */}
          <div>
            <h3 className="font-bold text-sm text-gray-800 mb-2 flex items-center gap-2">
              <span className="bg-[#FF2B5E] text-white text-xs font-bold px-2 py-0.5 rounded">II</span>
              Conflict of Interest (COI) Policy
            </h3>
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <p className="text-sm text-gray-700 leading-relaxed text-justify">
                To maintain transparency and trust, any potential Conflict of Interest (COI) must be disclosed immediately and in writing to the TBI Manager and the Advisory Board for review and written approval. This policy applies specifically to:
              </p>
              <ul className="list-disc list-inside text-sm text-gray-700 mt-2 space-y-1 ml-2">
                <li>Direct financial investments by TBI personnel in an Incubatee company</li>
                <li>Engagement in or advising a venture that is a direct competitor to a current Incubatee</li>
                <li>Use of privileged TBI information to recruit key personnel away from an active team</li>
              </ul>
              <p className="text-sm text-gray-700 leading-relaxed text-justify mt-3">
                The TBI prioritizes its mission and the success of its Incubatees, and no individual shall leverage their position to exploit TBI-related IP or resources for unauthorized personal or competitive gain.
              </p>
            </div>
          </div>
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef16}>
            <h2>TBI Code of Conduct</h2>
            <p className="subtitle">MARIAN TBI</p>

            <h3>I. Principles of Engagement and Integrity</h3>
            <p>This Code of Conduct outlines the ethical and professional standards required of all individuals participating in the MARIAN TBI ecosystem, including Incubatees, Mentors, Advisors, and TBI Staff. All parties must act with integrity, honesty, and good faith in all dealings, ensuring that statements regarding intellectual property (IP), financial status, or project progress are truthful and accurate. The TBI maintains a strict policy against discrimination, harassment, and retaliation, requiring that all interactions be conducted with respect and inclusivity, irrespective of personal background. Confidentiality is paramount, and all engagements are governed by the Non-Disclosure Agreement found in Annex 16; proprietary, financial, and technical information shared within the TBI environment must not be used for personal gain or to benefit third parties.</p>

            <h3>II. Conflict of Interest (COI) Policy</h3>
            <p>To maintain transparency and trust, any potential Conflict of Interest (COI) must be disclosed immediately and in writing to the TBI Manager and the Advisory Board for review and written approval. This policy applies specifically to direct financial investments by TBI personnel in an Incubatee company, engagement in or advising a venture that is a direct competitor to a current Incubatee, and the use of privileged TBI information to recruit key personnel away from an active team. The TBI prioritizes its mission and the success of its Incubatees, and no individual shall leverage their position to exploit TBI-related IP or resources for unauthorized personal or competitive gain.</p>
          </div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-200 flex justify-end">
          <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
        </div>
      </div>
    </div>
  );
}

// ── Form 17 Modal ─────────────────────────────────────────────
function Form17Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 2;
  const stepLabels = ['Code of Conduct', 'Agreement & Signatures'];

  // Agreement signatories (up to 5 rows)
  const [signatories, setSignatories] = useState<{ name: string; date: string }[]>([
    { name: '', date: '' },
    { name: '', date: '' },
    { name: '', date: '' },
    { name: '', date: '' },
    { name: '', date: '' },
  ]);
  const [witnessName, setWitnessName] = useState('');

  const printRef17 = useRef<HTMLDivElement>(null);

  const setSig = (idx: number, field: 'name' | 'date', val: string) => {
    const s = [...signatories];
    s[idx] = { ...s[idx], [field]: val };
    setSignatories(s);
  };

  const handlePrint = () => {
    if (!printRef17.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 17 – Incubatee Code of Conduct</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; color: #222; }
      h2 { text-align: center; margin-bottom: 2px; font-size: 15px; }
      h3 { font-size: 12px; margin: 12px 0 4px; }
      p, li { font-size: 10px; line-height: 1.6; text-align: justify; }
      ul { margin: 4px 0 4px 14px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
      th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; font-size: 10px; }
      th { background: #f3f3f3; font-weight: bold; }
      .subtitle { text-align: center; font-size: 10px; color: #555; margin-bottom: 14px; }
      .sig { display: inline-block; min-width: 200px; }
      .sig-line { display: inline-block; width: 200px; border-bottom: 1px solid #333; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef17.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  const sections = [
    {
      num: 1,
      title: 'Professionalism and Respect',
      items: [
        'Treat all individuals (TBI staff, UIC faculty, students, mentors, and fellow incubatees) with courtesy, respect, and professionalism. Discourteous behavior, harassment, or discrimination based on race, gender, religion, or background will result in immediate disciplinary action.',
        'Maintain confidentiality of sensitive information disclosed by the TBI, other startups, mentors, or the University.',
        'Uphold the Intellectual Property (IP) rights of all parties involved in the program, including respecting the IP of other startups and the University\'s licensed technology.',
      ],
    },
    {
      num: 2,
      title: 'Commitment and Participation',
      items: [
        'Demonstrate a strong, full-time commitment to your venture and the program.',
        'Actively participate in mandatory program activities, including specialized workshops, pitch events, and mentorship sessions.',
        'Regularly attend scheduled meetings and honestly report on progress towards established technical and business milestones (KPIs). Failure to meet reporting requirements is grounds for review.',
        'Utilize feedback received from mentors and evaluators constructively to advance the venture.',
      ],
    },
    {
      num: 3,
      title: 'Collaboration and Resource Sharing',
      items: [
        'Foster a collaborative spirit by sharing general knowledge, experiences, and market insights with fellow startups within the program ecosystem.',
        'Respect the shared workspace and UIC co-working facilities. Maintain a clean, quiet, and organized environment.',
        'Adhere to all security and safety protocols when utilizing specialized UIC laboratories or equipment.',
      ],
    },
    {
      num: 4,
      title: 'Ethical Conduct and Health-Tech Integrity',
      items: [
        'Conduct all business activities with honesty and integrity.',
        'Ensure data integrity and patient confidentiality when developing, testing, or deploying Health-Tech solutions.',
        'Avoid any actions that could bring disrepute to the MARIAN TBI, the University of the Immaculate Conception (UIC), or the DOST. This includes misrepresenting the TBI\'s role or the startup\'s achievements.',
        'Disclose any conflict of interest that may arise between the startup and the TBI, the University, or other incubatees.',
      ],
    },
    {
      num: 5,
      title: 'Consequences and Reporting',
      items: [
        'Violations of this Code of Conduct may result in formal warnings, restrictions on access to TBI/UIC facilities, mandatory training, or immediate termination from the incubation program as determined by the TBI Management.',
        'If you experience or witness any violation of this Code of Conduct, please report it immediately to the TBI Program Manager. All reports will be handled confidentially and addressed appropriately by the TBI/UIC Administration.',
      ],
    },
  ];

  useFormValues(17, () => ({
    signatories,
    witnessName,
  }));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 17 – Incubatee Code of Conduct</h2>
            <p className="text-pink-100 text-xs mt-0.5">{stepLabels[step]} (Step {step + 1} of {totalSteps})</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1.5">
          <div className="h-1.5 rounded-r-full bg-[#FF2B5E] transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-5">
          {/* Step 0: Code of Conduct */}
          {step === 0 && (
            <div className="space-y-5">
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs text-blue-800 leading-relaxed">The MARIAN TBI, an initiative of the University of the Immaculate Conception (UIC) supported by the DOST, fosters a collaborative and respectful environment for all startups, mentors, and staff. To ensure a productive and positive experience for everyone, all incubatees must strictly adhere to the following Code of Conduct:</p>
              </div>

              {sections.map(sec => (
                <div key={sec.num}>
                  <h3 className="font-bold text-sm text-gray-800 mb-2 flex items-center gap-2">
                    <span className="bg-[#FF2B5E] text-white text-xs font-bold px-2 py-0.5 rounded">{sec.num}</span>
                    {sec.title}
                  </h3>
                  <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <ul className="space-y-2">
                      {sec.items.map((item, idx) => (
                        <li key={idx} className="flex items-start gap-2 text-sm text-gray-700">
                          <span className="text-[#FF2B5E] mt-0.5">•</span>
                          <span className="leading-relaxed">{item}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Step 1: Agreement & Signatures */}
          {step === 1 && (
            <div className="space-y-5">
              <div className="bg-green-50 border border-green-200 rounded-lg p-3">
                <h3 className="font-bold text-sm text-green-800 mb-1">Agreement</h3>
                <p className="text-xs text-green-700">By participating in the MARIAN TBI program, the Founders/Incubatees below agree to abide by this Code of Conduct.</p>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-300">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="border border-gray-300 px-3 py-2 text-left w-8">#</th>
                      <th className="border border-gray-300 px-3 py-2 text-left">Name</th>
                      <th className="border border-gray-300 px-3 py-2 text-center w-36">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {signatories.map((s, i) => (
                      <tr key={i} className="hover:bg-gray-50">
                        <td className="border border-gray-300 px-3 py-2 text-center text-xs font-semibold">{i + 1}</td>
                        <td className="border border-gray-300 px-3 py-2">
                          <input data-field="signatories" type="text" className="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={s.name} onChange={e => setSig(i, 'name', e.target.value)} placeholder="Full name" />
                        </td>
                        <td className="border border-gray-300 px-3 py-2">
                          <input data-field="signatories" type="date" className="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={s.date} onChange={e => setSig(i, 'date', e.target.value)} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="border border-gray-200 rounded-lg p-4 max-w-sm">
                <p className="text-xs font-bold text-gray-700 uppercase mb-2">Witnessed by: TBI Program Manager</p>
                <input data-field="witnessName" type="text" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-pink-400 focus:outline-none" value={witnessName} onChange={e => setWitnessName(e.target.value)} placeholder="Name of TBI Program Manager" />
              </div>
            </div>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef17}>
            <h2>Incubatee Code of Conduct</h2>
            <p className="subtitle">MARIAN TBI, UIC</p>

            <p>The MARIAN TBI, an initiative of the University of the Immaculate Conception (UIC) supported by the DOST, fosters a collaborative and respectful environment for all startups, mentors, and staff. To ensure a productive and positive experience for everyone, all incubatees must strictly adhere to the following Code of Conduct:</p>

            {sections.map(sec => (
              <div key={sec.num}>
                <h3>{sec.num}. {sec.title}</h3>
                <ul>
                  {sec.items.map((item, idx) => <li key={idx}>{item}</li>)}
                </ul>
              </div>
            ))}

            <h3>Agreement</h3>
            <p>By participating in the MARIAN TBI program, the Founders/Incubatees below agree to abide by this Code of Conduct.</p>
            <table>
              <thead>
                <tr><th>Name</th><th>Signature</th><th>Date</th></tr>
              </thead>
              <tbody>
                {signatories.map((s, i) => (
                  <tr key={i}>
                    <td>{s.name || '____________'}</td>
                    <td></td>
                    <td>{s.date || '____________'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <br/>
            <div>
              <span style={{ fontSize: '10px', fontWeight: 'bold' }}>Witnessed by:</span><br/>
              <br/><span className="sig-line">&nbsp;</span><br/>
              <span style={{ fontSize: '9px' }}>TBI Program Manager: {witnessName || '____________'}</span>
            </div>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Form 19 Modal ─────────────────────────────────────────────
function Form19Modal({ onClose }: { onClose: () => void }) {
  const printRef19 = useRef<HTMLDivElement>(null);

  const handlePrint = () => {
    if (!printRef19.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 19 – MARIAN TBI Steering Committee Charter</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 30px; color: #222; }
      h2 { text-align: center; margin-bottom: 4px; font-size: 15px; }
      h3 { font-size: 12px; margin: 14px 0 4px; font-weight: bold; }
      h4 { font-size: 11px; margin: 8px 0 3px; font-weight: bold; }
      p, li { font-size: 10px; line-height: 1.6; text-align: justify; }
      ul, ol { margin: 4px 0 4px 18px; }
      .subtitle { text-align: center; font-size: 10px; color: #555; margin-bottom: 14px; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef19.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  const charterSections = [
    {
      letter: 'A',
      title: 'Name and Purpose',
      subs: [
        { label: 'Name', text: 'The Committee shall be known as the Mobilizing Advanced Research Innovation to Advocate Nation-building Technology Business Incubator (MARIAN TBI) Steering Committee.' },
        { label: 'Purpose', text: 'The Marian TBI Steering Committee is established to provide strategic oversight, governance, and guidance for the effective operation, growth, and long-term sustainability of the MARIAN TBI. It will ensure the TBI\'s activities align with its institutional mission, funding mandates, and strategic objectives, particularly during its critical initial transition phase.' },
      ],
    },
    {
      letter: 'B',
      title: 'Authority and Responsibilities',
      intro: 'The Committee\'s core responsibilities include:',
      groups: [
        { label: 'Strategic Direction', items: [
          'Reviewing and approving the TBI\'s strategic plan, mission, and long-term objectives.',
          'Monitoring the TBI\'s performance against key indicators, including financial, incubation, and impact metrics.',
        ]},
        { label: 'Transition Oversight & Governance', items: [
          'Providing guidance and approval for critical operational policies and procedures (tenant selection, intellectual property management).',
          'Overseeing the smooth transition from the TBI\'s initial development phase to full operational capacity.',
          'Ensuring compliance with relevant legal, regulatory, and funding body requirements.',
        ]},
        { label: 'Financial Health & Sustainability', items: [
          'Reviewing and recommending approval of the annual budget and resource allocation.',
          'Critically reviewing the Sustainability Plan to ensure diversified funding streams and long-term financial viability beyond initial grants.',
        ]},
        { label: 'Stakeholder Relations', items: [
          'Acting as a liaison and advocate for the TBI among key stakeholders (university, government, industry).',
        ]},
      ],
    },
    {
      letter: 'C',
      title: 'Membership',
      subs: [
        { label: 'Composition', text: 'The Committee shall be composed of individuals representing key internal and external stakeholders.' },
        { label: 'Chair', text: 'The Committee will be chaired by VP Academics.' },
        { label: 'Term', text: 'Members shall serve for a term of three years, renewable upon mutual agreement.' },
      ],
    },
    {
      letter: 'D',
      title: 'Meetings and Administration',
      subs: [
        { label: 'Frequency', text: 'The Committee shall meet quarterly and as deemed necessary by the Chair.' },
        { label: 'Quorum', text: 'A quorum shall consist of a simple majority (50% + 1) of the total membership.' },
        { label: 'Reporting', text: 'The Committee will report its recommendations and decisions to the University President.' },
      ],
    },
  ];

  useFormValues(19, () => ({}));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 19 – Steering Committee Charter</h2>
            <p className="text-pink-100 text-xs mt-0.5">MARIAN TBI</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {charterSections.map(sec => (
            <div key={sec.letter}>
              <h3 className="font-bold text-sm text-gray-800 mb-2 flex items-center gap-2">
                <span className="bg-[#FF2B5E] text-white text-xs font-bold px-2 py-0.5 rounded">{sec.letter}</span>
                {sec.title}
              </h3>

              {/* Simple sub-items (A, C, D) */}
              {'subs' in sec && sec.subs && (
                <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-3">
                  {sec.subs.map((s, idx) => (
                    <div key={idx}>
                      <span className="text-xs font-bold text-gray-700">{String.fromCharCode(97 + idx)}. {s.label}</span>
                      <p className="text-sm text-gray-700 leading-relaxed mt-0.5">{s.text}</p>
                    </div>
                  ))}
                </div>
              )}

              {/* Section B: grouped responsibilities */}
              {'groups' in sec && sec.groups && (
                <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-4">
                  {'intro' in sec && sec.intro && <p className="text-sm text-gray-700 italic">{sec.intro}</p>}
                  {sec.groups.map((g, gIdx) => (
                    <div key={gIdx}>
                      <span className="text-xs font-bold text-gray-700">{String.fromCharCode(97 + gIdx)}. {g.label}</span>
                      <ul className="mt-1 space-y-1 ml-4">
                        {g.items.map((item, iIdx) => (
                          <li key={iIdx} className="flex items-start gap-2 text-sm text-gray-700">
                            <span className="text-[#FF2B5E] mt-0.5">{String.fromCharCode(97 + iIdx)})</span>
                            <span className="leading-relaxed">{item}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef19}>
            <h2>MARIAN TBI Steering Committee Charter</h2>

            <h3>A. Name and Purpose</h3>
            <p><strong>a. Name</strong> – The Committee shall be known as the Mobilizing Advanced Research Innovation to Advocate Nation-building Technology Business Incubator (MARIAN TBI) Steering Committee.</p>
            <p><strong>b. Purpose</strong> – The Marian TBI Steering Committee is established to provide strategic oversight, governance, and guidance for the effective operation, growth, and long-term sustainability of the MARIAN TBI. It will ensure the TBI's activities align with its institutional mission, funding mandates, and strategic objectives, particularly during its critical initial transition phase.</p>

            <h3>B. Authority and Responsibilities</h3>
            <p>The Committee's core responsibilities include:</p>
            <h4>a. Strategic Direction</h4>
            <ol type="a"><li>Reviewing and approving the TBI's strategic plan, mission, and long-term objectives.</li><li>Monitoring the TBI's performance against key indicators, including financial, incubation, and impact metrics.</li></ol>
            <h4>b. Transition Oversight &amp; Governance</h4>
            <ol type="a"><li>Providing guidance and approval for critical operational policies and procedures (tenant selection, intellectual property management).</li><li>Overseeing the smooth transition from the TBI's initial development phase to full operational capacity.</li><li>Ensuring compliance with relevant legal, regulatory, and funding body requirements.</li></ol>
            <h4>c. Financial Health &amp; Sustainability</h4>
            <ol type="a"><li>Reviewing and recommending approval of the annual budget and resource allocation.</li><li>Critically reviewing the Sustainability Plan to ensure diversified funding streams and long-term financial viability beyond initial grants.</li></ol>
            <h4>d. Stakeholder Relations</h4>
            <ol type="a"><li>Acting as a liaison and advocate for the TBI among key stakeholders (university, government, industry).</li></ol>

            <h3>C. Membership</h3>
            <p><strong>a. Composition</strong> – The Committee shall be composed of individuals representing key internal and external stakeholders.</p>
            <p><strong>b. Chair</strong> – The Committee will be chaired by VP Academics.</p>
            <p><strong>c. Term</strong> – Members shall serve for a term of three years, renewable upon mutual agreement.</p>

            <h3>D. Meetings and Administration</h3>
            <p><strong>a. Frequency</strong> – The Committee shall meet quarterly and as deemed necessary by the Chair.</p>
            <p><strong>b. Quorum</strong> – A quorum shall consist of a simple majority (50% + 1) of the total membership.</p>
            <p><strong>c. Reporting</strong> – The Committee will report its recommendations and decisions to the University President.</p>
          </div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-200 flex justify-end">
          <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
        </div>
      </div>
    </div>
  );
}

// ── Form 20 Constants ─────────────────────────────────────────
const ETHICS_CATEGORIES: {
  catNum: number;
  title: string;
  items: { sub: string; question: string; action: string }[];
}[] = [
  {
    catNum: 1,
    title: 'Data Privacy & Security (Philippine Data Privacy Act of 2012)',
    items: [
      { sub: 'a', question: 'Does the project collect, store, transmit, or process any Personal Information (PI) (e.g., names, addresses, emails) of users/patients?', action: 'If Yes: Identify the TBI team member designated as the de facto Data Protection Officer (DPO) and submit a draft Privacy Notice.' },
      { sub: 'b', question: 'Does the project collect, store, transmit, or process any Sensitive Personal Information (SPI) or Personal Health Information (PHI) (e.g., medical records, diagnoses, blood type, religious beliefs)?', action: 'If Yes: Critical Milestone: Must develop and submit a formal Consent Form template and a Data Sharing Agreement (if applicable) for TBI review before Incubation Phase.' },
      { sub: 'c', question: 'Are measures in place for secure data storage (e.g., encryption, pseudonymization, or anonymization) to prevent unauthorized access or breaches?', action: 'If Yes: Must detail security standards and protocols in the Business Plan (Annex 3).' },
      { sub: 'd', question: 'Does the project involve transferring PI/PHI outside of the Philippines?', action: 'If Yes: Must document and justify the transfer mechanism to ensure equivalent data protection, as required by the NPC (National Privacy Commission).' },
    ],
  },
  {
    catNum: 2,
    title: 'Health Regulatory Compliance (FDA & DOH)',
    items: [
      { sub: 'a', question: 'Is the product/solution classified by the team as a Medical Device (e.g., diagnostic software, wearable health sensor, surgical planning tool)?', action: 'If Yes: Must identify the specific FDA classification risk class (I, II, or III) in the Business Plan.' },
      { sub: 'b', question: 'Does the product require a License to Operate (LTO) or a Certificate of Product Registration (CPR) from the Philippine FDA prior to commercial launch?', action: 'If Yes: Critical Milestone: Must provide a regulatory roadmap and estimated timeline for LTO/CPR application before the Acceleration Phase.' },
      { sub: 'c', question: 'Does the solution meet recognized DOH (Department of Health) interoperability standards or guidelines for Electronic Health Records (EHRs) or Health Information Exchanges (HIEs)?', action: 'If Yes: Must provide proof of adherence to relevant national standards (e.g., PhilHealth requirements, DOH Circulars).' },
    ],
  },
  {
    catNum: 3,
    title: 'Research & Clinical Ethics',
    items: [
      { sub: 'a', question: 'Does the project involve direct intervention with human subjects (e.g., clinical trials, controlled testing of a treatment or diagnostic)?', action: 'If Yes: Critical Milestone: Must submit the full research protocol to the UIC Institutional Ethics Review Board (ERB) and obtain Written Approval before any testing or data collection begins.' },
      { sub: 'b', question: 'Does the project rely on the analysis of pre-existing or historical clinical data obtained from a hospital, clinic, or third-party data custodian?', action: 'If Yes: Must provide a Data Use Agreement (DUA) or proof of authorization from the data custodian to the TBI.' },
      { sub: 'c', question: 'Are the project\'s developers, mentors, or advisors involved in any professional practice that could create a conflict of interest (e.g., a mentor whose clinic would be a direct beneficiary of the product)?', action: 'If Yes: Must submit a formal Conflict of Interest Disclosure Form to the TBI Manager (Refer to Annex 10).' },
    ],
  },
  {
    catNum: 4,
    title: 'Operational and Safety Liability',
    items: [
      { sub: 'a', question: 'Is the software or service intended to directly inform, guide, or replace a clinical decision made by a licensed healthcare professional?', action: 'If Yes: Critical Milestone: Must obtain Professional Liability / Malpractice Insurance before the Post-Incubation Phase and clearly define the limitations of liability in the End User License Agreement (EULA).' },
      { sub: 'b', question: 'Has the team conducted a preliminary risk assessment to identify potential harms (patient injury, misdiagnosis, data breach) that could arise from the product\'s failure or misuse?', action: 'If Yes: Must include a section in the Business Plan detailing mitigation strategies.' },
      { sub: 'c', question: 'If applicable, is the hardware component (e.g., sensor, device) designed to meet internationally recognized safety standards (e.g., IEC 60601 for electrical medical equipment)?', action: 'If Yes: Must identify the specific standard and the plan for achieving certification.' },
    ],
  },
];

// ── Form 20 Modal ─────────────────────────────────────────────
function Form20Modal({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);
  const totalSteps = 3;
  const stepLabels = ['Categories 1–2', 'Categories 3–4', 'TBI Management Review'];

  // Response state: key = "catNum-sub", value = "Yes" | "No" | "N/A"
  const [responses, setResponses] = useState<Record<string, string>>({});

  // Review notes (up to 3 rows)
  const [reviewRows, setReviewRows] = useState<{ date: string; reviewer: string; outcome: string; notes: string }[]>([
    { date: '', reviewer: '', outcome: '', notes: '' },
    { date: '', reviewer: '', outcome: '', notes: '' },
    { date: '', reviewer: '', outcome: '', notes: '' },
  ]);

  const printRef20 = useRef<HTMLDivElement>(null);

  const setResponse = (key: string, val: string) => setResponses({ ...responses, [key]: val });

  const setReviewRow = (idx: number, field: keyof typeof reviewRows[number], val: string) => {
    const r = [...reviewRows];
    r[idx] = { ...r[idx], [field]: val };
    setReviewRows(r);
  };

  const handlePrint = () => {
    if (!printRef20.current) return;
    const w = window.open('', '_blank', 'width=900,height=700');
    if (!w) return;
    w.document.write(`<html><head><title>Form 20 – ICT for Health Ethics & Compliance Checklist</title><style>
      body { font-family: Arial, sans-serif; font-size: 11px; padding: 20px; color: #222; }
      h2 { text-align: center; margin-bottom: 2px; font-size: 14px; }
      h3 { font-size: 11px; margin: 10px 0 4px; }
      p { font-size: 9px; margin: 3px 0; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
      th, td { border: 1px solid #333; padding: 3px 5px; text-align: left; font-size: 9px; vertical-align: top; }
      th { background: #f3f3f3; font-weight: bold; }
      .cat-header { background: #e8e8e8; font-weight: bold; }
      @media print { body { padding: 0; } }
    </style></head><body>`);
    w.document.write(printRef20.current.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    w.print();
  };

  const renderCategory = (cat: typeof ETHICS_CATEGORIES[number]) => (
    <div key={cat.catNum} className="mb-5">
      <h3 className="font-bold text-sm text-gray-800 mb-2 flex items-center gap-2">
        <span className="bg-[#FF2B5E] text-white text-xs font-bold px-2 py-0.5 rounded">{cat.catNum}</span>
        {cat.title}
      </h3>
      <div className="overflow-x-auto">
        <table className="w-full text-sm border border-gray-300">
          <thead>
            <tr className="bg-gray-50">
              <th className="border border-gray-300 px-2 py-2 text-left" style={{ minWidth: '250px' }}>Compliance Statement / Question</th>
              <th className="border border-gray-300 px-2 py-2 text-center w-28">Response</th>
              <th className="border border-gray-300 px-2 py-2 text-left" style={{ minWidth: '200px' }}>Required Action / Milestone</th>
            </tr>
          </thead>
          <tbody>
            {cat.items.map(item => {
              const key = `${cat.catNum}-${item.sub}`;
              return (
                <tr key={key} className="hover:bg-gray-50">
                  <td className="border border-gray-300 px-2 py-2">
                    <span className="text-xs"><strong>{item.sub}.</strong> {item.question}</span>
                  </td>
                  <td className="border border-gray-300 px-2 py-2 text-center">
                    <select data-field="responses" className="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none w-16" value={responses[key] || ''} onChange={e => setResponse(key, e.target.value)}>
                      <option value="">—</option>
                      <option value="Yes">Yes</option>
                      <option value="No">No</option>
                      <option value="N/A">N/A</option>
                    </select>
                  </td>
                  <td className="border border-gray-300 px-2 py-2">
                    <span className="text-[10px] text-gray-600 italic">{item.action}</span>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );

  useFormValues(20, () => ({
    responses,
    reviewRows,
  }));

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="p-4 rounded-t-2xl flex items-center justify-between" style={{ background: 'linear-gradient(90deg, #9b1b5a, #c0392b)' }}>
          <div>
            <h2 className="text-lg font-bold text-white flex items-center gap-2"><FileText className="w-5 h-5" /> Form 20 – ICT for Health Ethics & Compliance</h2>
            <p className="text-pink-100 text-xs mt-0.5">{stepLabels[step]} (Step {step + 1} of {totalSteps})</p>
          </div>
          <div className="flex gap-2">
            <button onClick={handlePrint} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors" title="Print"><Printer className="w-4 h-4" /></button>
            <button onClick={onClose} className="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-colors"><X className="w-4 h-4" /></button>
          </div>
        </div>

        {/* Progress bar */}
        <div className="w-full bg-gray-100 h-1.5">
          <div className="h-1.5 rounded-r-full bg-[#FF2B5E] transition-all duration-300" style={{ width: `${((step + 1) / totalSteps) * 100}%` }} />
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          {/* Step 0: Categories 1-2 */}
          {step === 0 && (
            <div>
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                <p className="text-xs text-blue-800 leading-relaxed"><strong>Phase:</strong> Pre-Incubation (Mandatory Submission for Gate Review)</p>
                <p className="text-xs text-blue-800 leading-relaxed mt-1"><strong>Purpose:</strong> To assess project alignment with Philippine Data Privacy Law, Health Regulation, and research ethics.</p>
                <p className="text-xs text-blue-800 leading-relaxed mt-1"><strong>Instructions:</strong> Incubatee teams must complete all sections. TBI Management will review the responses to determine required compliance milestones before the Acceleration Phase.</p>
              </div>
              {ETHICS_CATEGORIES.slice(0, 2).map(cat => renderCategory(cat))}
            </div>
          )}

          {/* Step 1: Categories 3-4 */}
          {step === 1 && (
            <div>
              {ETHICS_CATEGORIES.slice(2, 4).map(cat => renderCategory(cat))}
            </div>
          )}

          {/* Step 2: TBI Management Review */}
          {step === 2 && (
            <div className="space-y-4">
              <h3 className="font-bold text-sm text-gray-800">TBI Management Review Notes</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm border border-gray-300">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="border border-gray-300 px-2 py-2 text-center w-28">Date</th>
                      <th className="border border-gray-300 px-2 py-2 text-left">Reviewer</th>
                      <th className="border border-gray-300 px-2 py-2 text-center w-44">Outcome</th>
                      <th className="border border-gray-300 px-2 py-2 text-left">Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    {reviewRows.map((r, i) => (
                      <tr key={i} className="hover:bg-gray-50">
                        <td className="border border-gray-300 px-2 py-2">
                          <input data-field="reviewRows" type="date" className="w-full border border-gray-200 rounded px-1 py-0.5 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none" value={r.date} onChange={e => setReviewRow(i, 'date', e.target.value)} />
                        </td>
                        <td className="border border-gray-300 px-2 py-2">
                          <input data-field="reviewRows" type="text" className="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none" value={r.reviewer} onChange={e => setReviewRow(i, 'reviewer', e.target.value)} placeholder="Reviewer name" />
                        </td>
                        <td className="border border-gray-300 px-2 py-2">
                          <select data-field="reviewRows" className="w-full border border-gray-300 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none" value={r.outcome} onChange={e => setReviewRow(i, 'outcome', e.target.value)}>
                            <option value="">Select...</option>
                            <option value="Pass">Pass</option>
                            <option value="Milestones Required">Milestones Required</option>
                            <option value="Fail">Fail</option>
                          </select>
                        </td>
                        <td className="border border-gray-300 px-2 py-2">
                          <textarea data-field="reviewRows" className="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-2 focus:ring-pink-400 focus:outline-none resize-none" rows={2} value={r.notes} onChange={e => setReviewRow(i, 'notes', e.target.value)} placeholder="Notes..." />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>

        {/* Hidden print area */}
        <div className="hidden">
          <div ref={printRef20}>
            <h2>ICT for Health Ethics & Compliance Checklist</h2>
            <p style={{ textAlign: 'center', fontSize: '9px' }}>Phase: Pre-Incubation (Mandatory Submission for Gate Review)</p>
            <p style={{ fontSize: '9px' }}>Purpose: To assess project alignment with Philippine Data Privacy Law, Health Regulation, and research ethics.</p>
            <p style={{ fontSize: '9px', marginBottom: '8px' }}>Instructions: Incubatee teams must complete all sections. TBI Management will review the responses to determine required compliance milestones before the Acceleration Phase.</p>

            {ETHICS_CATEGORIES.map(cat => (
              <div key={cat.catNum}>
                <h3>{cat.catNum}. {cat.title}</h3>
                <table>
                  <thead>
                    <tr><th>Compliance Statement / Question</th><th style={{ width: '60px' }}>Response</th><th>Required Action / Milestone</th></tr>
                  </thead>
                  <tbody>
                    {cat.items.map(item => (
                      <tr key={`${cat.catNum}-${item.sub}`}>
                        <td><strong>{item.sub}.</strong> {item.question}</td>
                        <td style={{ textAlign: 'center' }}>{responses[`${cat.catNum}-${item.sub}`] || ''}</td>
                        <td><em>{item.action}</em></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))}

            <h3>TBI Management Review Notes</h3>
            <table>
              <thead>
                <tr><th>Date</th><th>Reviewer</th><th>Outcome</th><th>Notes</th></tr>
              </thead>
              <tbody>
                {reviewRows.map((r, i) => (
                  <tr key={i}>
                    <td>{r.date || ''}</td>
                    <td>{r.reviewer || ''}</td>
                    <td>{r.outcome || ''}</td>
                    <td>{r.notes || ''}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Footer navigation */}
        <div className="p-4 border-t border-gray-200 flex justify-between">
          <button
            onClick={() => step > 0 && setStep(step - 1)}
            className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${step === 0 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-gray-100'}`}
            disabled={step === 0}
          >
            <ChevronLeft className="w-4 h-4" /> Back
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Close</button>
            {step < totalSteps - 1 && (
              <button onClick={() => setStep(step + 1)} className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors">
                Next <ChevronRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ===================== FORM 21: Personnel Transition, Document Turnover, and Asset Endorsement Form ===================== */

function Form21Modal({ onClose }: { onClose: () => void }) {
  const printRef21 = useRef<HTMLDivElement>(null);
  const [step, setStep] = useState(0);
  const totalSteps = 3;

  // Header
  const [employeeName, setEmployeeName] = useState('');
  const [position, setPosition] = useState('');
  const [effectivityDate, setEffectivityDate] = useState('');

  // Knowledge Transfer Status Report rows
  const [ktRows, setKtRows] = useState([
    { category: 'Startup Mentees', description: '', status: '', pending: '', remarks: '' },
    { category: 'Funding/Grants', description: '', status: '', pending: '', remarks: '' },
    { category: 'Partnerships', description: '', status: '', pending: '', remarks: '' },
    { category: 'Events/Programs', description: '', status: '', pending: '', remarks: '' },
  ]);
  const [digitalRepoPath, setDigitalRepoPath] = useState('');

  // Section I checkboxes
  const [sec1Files, setSec1Files] = useState(false);
  const [sec1Credentials, setSec1Credentials] = useState(false);
  const [sec1StatusReport, setSec1StatusReport] = useState(false);
  const [sec1TechAsst, setSec1TechAsst] = useState('');
  const [sec1TbiManager, setSec1TbiManager] = useState('');

  // Section II
  const [laptopChecked, setLaptopChecked] = useState(false);
  const [laptopSerial, setLaptopSerial] = useState('');
  const [laptopCondition, setLaptopCondition] = useState('');
  const [tabletChecked, setTabletChecked] = useState(false);
  const [tabletSerial, setTabletSerial] = useState('');
  const [tabletCondition, setTabletCondition] = useState('');
  const [keysChecked, setKeysChecked] = useState(false);
  const [equipChecked, setEquipChecked] = useState(false);
  const [equipSpecify, setEquipSpecify] = useState('');
  const [sec2Liaison, setSec2Liaison] = useState('');
  const [sec2Custodian, setSec2Custodian] = useState('');

  // Section III
  const [mgrName, setMgrName] = useState('');
  const [mgrDate, setMgrDate] = useState('');
  const [hrName, setHrName] = useState('');
  const [hrDate, setHrDate] = useState('');

  const handlePrint = () => {
    if (!printRef21.current) return;
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<html><head><title>Form 21 - Personnel Transition</title><style>
      body{font-family:Arial,sans-serif;padding:30px;font-size:12px;color:#222;}
      h2{text-align:center;margin-bottom:2px;} h3{margin-top:18px;margin-bottom:6px;}
      table{width:100%;border-collapse:collapse;margin:8px 0;} th,td{border:1px solid #333;padding:5px 7px;text-align:left;}
      th{background:#e0e0e0;} .label{font-weight:bold;} .sig-line{border-bottom:1px solid #333;min-width:180px;display:inline-block;margin:0 8px;}
      .check{margin-right:6px;} .note{font-size:11px;margin-top:16px;padding:8px;background:#f9f9f9;border:1px solid #ccc;}
    </style></head><body>` + printRef21.current.innerHTML + '</body></html>');
    w.document.close(); w.focus(); w.print();
  };

  const updateKtRow = (idx: number, field: string, value: string) => {
    setKtRows(prev => prev.map((r, i) => i === idx ? { ...r, [field]: value } : r));
  };

  useFormValues(21, () => ({
    employeeName,
    position,
    department: 'MARIAN TBI',
    effectivityDate,
    ktRows,
    digitalRepoPath,
    sec1Files,
    sec1Credentials,
    sec1StatusReport,
    sec1TechAsst,
    sec1TbiManager,
    laptopChecked,
    laptopSerial,
    laptopCondition,
    tabletChecked,
    tabletSerial,
    tabletCondition,
    keysChecked,
    equipChecked,
    equipSpecify,
    sec2Liaison,
    sec2Custodian,
    mgrName,
    mgrDate,
    hrName,
    hrDate,
  }));

  return (
  <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40">
    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
      {/* Header */}
      <div
        className="p-5 rounded-t-2xl text-white flex items-center justify-between"
        style={{ background: "linear-gradient(90deg, #9b1b5a, #c0392b)" }}
      >
        <div className="flex items-center gap-3">
          <FileText className="w-6 h-6" />
          <div>
            <h2 className="text-lg font-bold leading-tight">
              Personnel Transition, Document Turnover,
              <br />
              and Asset Endorsement Form
            </h2>
            <p className="text-xs text-white/80 mt-1">
              Step {step + 1} of {totalSteps}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handlePrint}
            className="p-2 hover:bg-white/20 rounded-lg transition-colors"
            title="Print"
          >
            <Printer className="w-5 h-5" />
          </button>
          <button
            onClick={onClose}
            className="p-2 hover:bg-white/20 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Step dots */}
      <div className="flex items-center justify-center gap-2 py-3 bg-gray-50 border-b">
        {Array.from({ length: totalSteps }).map((_, i) => (
          <button
            key={i}
            onClick={() => setStep(i)}
            className={`w-3 h-3 rounded-full transition-colors ${
              i === step
                ? "bg-[#FF2B5E]"
                : i < step
                ? "bg-[#FF2B5E]/40"
                : "bg-gray-300"
            }`}
          />
        ))}
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto p-6 space-y-5">
        {step === 0 && (
          <>
            {/* Header Fields */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">
                  Employee Name
                </label>
                <input data-field="employeeName"
                  value={employeeName}
                  onChange={(e) => setEmployeeName(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">
                  Position
                </label>
                <input data-field="position"
                  value={position}
                  onChange={(e) => setPosition(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">
                  Department/Unit
                </label>
                <input
                  value="MARIAN TBI"
                  data-field="department"
                  name="department"
                  disabled
                  className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-500"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-600 mb-1">
                  Effectivity Date
                </label>
                <input data-field="effectivityDate"
                  type="date"
                  value={effectivityDate}
                  onChange={(e) => setEffectivityDate(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                />
              </div>
            </div>

            {/* Knowledge Transfer Status Report */}
            <div>
              <h3 className="text-sm font-bold text-[#FF2B5E] mb-1">
                KNOWLEDGE TRANSFER STATUS REPORT
              </h3>
              <p className="text-xs text-gray-500 italic mb-3">
                Goal: To capture Intellectual Capital before personnel
                departure.
              </p>
              <div className="overflow-x-auto">
                <table className="w-full text-xs border border-gray-300">
                  <thead>
                    <tr className="bg-gray-100">
                      <th className="border border-gray-300 px-2 py-2 text-left w-[120px]">
                        Category
                      </th>
                      <th className="border border-gray-300 px-2 py-2 text-left">
                        Description / Project Name
                      </th>
                      <th className="border border-gray-300 px-2 py-2 text-left">
                        Current Status / Phase
                      </th>
                      <th className="border border-gray-300 px-2 py-2 text-left">
                        Pending Actions & Deadlines
                      </th>
                      <th className="border border-gray-300 px-2 py-2 text-left w-[100px]">
                        Remarks
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {ktRows.map((row, i) => (
                      <tr key={i}>
                        <td className="border border-gray-300 px-2 py-1 font-medium text-gray-700 bg-gray-50">
                          {row.category}
                        </td>
                        <td className="border border-gray-300 px-1 py-1">
                          <input data-field="ktRows"
                            value={row.description}
                            onChange={(e) =>
                              updateKtRow(i, "description", e.target.value)
                            }
                            className="w-full border-0 text-xs px-1 py-1 focus:ring-1 focus:ring-[#FF2B5E] rounded"
                            placeholder={
                              i === 0
                                ? "List assigned startups/founders & Contact"
                                : i === 1
                                ? "Ex. DOST-PCIEERD reports"
                                : i === 2
                                ? "List active MOUs/MOAs"
                                : "Workshops, Demo Days, etc."
                            }
                          />
                        </td>
                        <td className="border border-gray-300 px-1 py-1">
                          <input data-field="ktRows"
                            value={row.status}
                            onChange={(e) =>
                              updateKtRow(i, "status", e.target.value)
                            }
                            className="w-full border-0 text-xs px-1 py-1 focus:ring-1 focus:ring-[#FF2B5E] rounded"
                            placeholder={
                              i === 0
                                ? "e.g., Incubation, Acceleration"
                                : i === 1
                                ? "% of completion"
                                : i === 2
                                ? "Active/Pending renewal"
                                : "Planning/Execution/Liquidation"
                            }
                          />
                        </td>
                        <td className="border border-gray-300 px-1 py-1">
                          <input data-field="ktRows"
                            value={row.pending}
                            onChange={(e) =>
                              updateKtRow(i, "pending", e.target.value)
                            }
                            className="w-full border-0 text-xs px-1 py-1 focus:ring-1 focus:ring-[#FF2B5E] rounded"
                            placeholder={
                              i === 0
                                ? "List upcoming milestones"
                                : i === 1
                                ? "Next report due date"
                                : i === 2
                                ? "Key contact & email"
                                : "Unfinished logistics/invites"
                            }
                          />
                        </td>
                        <td className="border border-gray-300 px-1 py-1">
                          <input data-field="ktRows"
                            value={row.remarks}
                            onChange={(e) =>
                              updateKtRow(i, "remarks", e.target.value)
                            }
                            className="w-full border-0 text-xs px-1 py-1 focus:ring-1 focus:ring-[#FF2B5E] rounded"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="mt-3">
                <label className="block text-xs font-semibold text-gray-600 mb-1">
                  Digital Repository Path
                </label>
                <input data-field="digitalRepoPath"
                  value={digitalRepoPath}
                  onChange={(e) => setDigitalRepoPath(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                  placeholder="Link to Google Drive/SharePoint folder where all files were migrated"
                />
              </div>
            </div>
          </>
        )}

        <div className="space-y-6">
          {/* STEP 1 */}
          {step === 1 && (
            <div key="step-1">
              <h3 className="text-sm font-bold text-[#FF2B5E] mb-1">
                PERSONNEL CLEARANCE & ENDORSEMENT FORM
              </h3>
              <p className="text-xs text-gray-500 italic mb-4">
                Goal: Formal sign-off for administrative and HR records.
              </p>

              {/* Section I */}
              <div className="border border-gray-200 rounded-xl p-4 mb-4">
                <h4 className="text-xs font-bold text-gray-700 mb-3">
                  Section I: Document & Data Turnover (ISO 21001)
                </h4>
                <div className="space-y-3">
                  <label className="flex items-start gap-2 text-xs">
                    <input data-field="sec1Files"
                      type="checkbox"
                      checked={sec1Files}
                      onChange={(e) => setSec1Files(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <span>
                      All project files migrated to TBI Official Cloud Drive.{" "}
                      <span className="text-gray-400">(Attach List)</span>
                    </span>
                  </label>
                  <label className="flex items-start gap-2 text-xs">
                    <input data-field="sec1Credentials"
                      type="checkbox"
                      checked={sec1Credentials}
                      onChange={(e) => setSec1Credentials(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <span>
                      Login credentials for official social media/portals
                      surrendered.
                    </span>
                  </label>
                  <label className="flex items-start gap-2 text-xs">
                    <input data-field="sec1StatusReport"
                      type="checkbox"
                      checked={sec1StatusReport}
                      onChange={(e) => setSec1StatusReport(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <span>
                      Status Report{" "}
                      <span className="text-gray-400">(Attach list)</span>{" "}
                      submitted and discussed with successor.
                    </span>
                  </label>
                </div>
                <div className="grid grid-cols-2 gap-4 mt-4 pt-3 border-t border-gray-200">
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">
                      Verified by (Technical Asst.)
                    </label>
                    <input data-field="sec1TechAsst"
                      value={sec1TechAsst}
                      onChange={(e) => setSec1TechAsst(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">
                      Verified by (TBI Manager)
                    </label>
                    <input data-field="sec1TbiManager"
                      value={sec1TbiManager}
                      onChange={(e) => setSec1TbiManager(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                    />
                  </div>
                </div>
              </div>

              {/* Section II */}
              <div className="border border-gray-200 rounded-xl p-4">
                <h4 className="text-xs font-bold text-gray-700 mb-3">
                  Section II: Physical Asset Return
                </h4>
                <div className="space-y-3">
                  {/* Laptop */}
                  <div className="flex items-start gap-2 text-xs">
                    <input data-field="laptopChecked"
                      type="checkbox"
                      checked={laptopChecked}
                      onChange={(e) => setLaptopChecked(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <div className="flex-1">
                      <span>Laptop</span>
                      {laptopChecked && (
                        <div className="flex gap-3 mt-1">
                          <input data-field="laptopSerial"
                            value={laptopSerial}
                            onChange={(e) => setLaptopSerial(e.target.value)}
                            className="border border-gray-300 rounded px-2 py-1 text-xs flex-1 focus:ring-1 focus:ring-[#FF2B5E]"
                            placeholder="Serial No."
                          />
                          <select data-field="laptopCondition"
                            value={laptopCondition}
                            onChange={(e) =>
                              setLaptopCondition(e.target.value)
                            }
                            className="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-[#FF2B5E]"
                          >
                            <option value="">Condition...</option>
                            <option value="Good">Good</option>
                            <option value="Damaged">Damaged</option>
                          </select>
                        </div>
                      )}
                    </div>
                  </div>
                  {/* Tablet */}
                  <div className="flex items-start gap-2 text-xs">
                    <input data-field="tabletChecked"
                      type="checkbox"
                      checked={tabletChecked}
                      onChange={(e) => setTabletChecked(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <div className="flex-1">
                      <span>Tablet</span>
                      {tabletChecked && (
                        <div className="flex gap-3 mt-1">
                          <input data-field="tabletSerial"
                            value={tabletSerial}
                            onChange={(e) => setTabletSerial(e.target.value)}
                            className="border border-gray-300 rounded px-2 py-1 text-xs flex-1 focus:ring-1 focus:ring-[#FF2B5E]"
                            placeholder="Serial No."
                          />
                          <select data-field="tabletCondition"
                            value={tabletCondition}
                            onChange={(e) =>
                              setTabletCondition(e.target.value)
                            }
                            className="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-[#FF2B5E]"
                          >
                            <option value="">Condition...</option>
                            <option value="Good">Good</option>
                            <option value="Damaged">Damaged</option>
                          </select>
                        </div>
                      )}
                    </div>
                  </div>
                  {/* Keys */}
                  <label className="flex items-start gap-2 text-xs">
                    <input data-field="keysChecked"
                      type="checkbox"
                      checked={keysChecked}
                      onChange={(e) => setKeysChecked(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <span>Office Keys / ID Badge / Access Cards</span>
                  </label>
                  {/* Equipment */}
                  <div className="flex items-start gap-2 text-xs">
                    <input data-field="equipChecked"
                      type="checkbox"
                      checked={equipChecked}
                      onChange={(e) => setEquipChecked(e.target.checked)}
                      className="mt-0.5 accent-[#FF2B5E]"
                    />
                    <div className="flex-1">
                      <span>Specialized TBI Equipment</span>
                      {equipChecked && (
                        <input data-field="equipSpecify"
                          value={equipSpecify}
                          onChange={(e) => setEquipSpecify(e.target.value)}
                          className="w-full border border-gray-300 rounded px-2 py-1 text-xs mt-1 focus:ring-1 focus:ring-[#FF2B5E]"
                          placeholder="Specify equipment..."
                        />
                      )}
                    </div>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-4 mt-4 pt-3 border-t border-gray-200">
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">
                      Verified by (Liaison Staff)
                    </label>
                    <input data-field="sec2Liaison"
                      value={sec2Liaison}
                      onChange={(e) => setSec2Liaison(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">
                      Verified by (Property Custodian)
                    </label>
                    <input data-field="sec2Custodian"
                      value={sec2Custodian}
                      onChange={(e) => setSec2Custodian(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                    />
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* STEP 2 */}
          {step === 2 && (
            <div key="step-2">
              <h3 className="text-sm font-bold text-[#FF2B5E] mb-2">
                Section III: Final Management Approval
              </h3>
              <div className="border border-gray-200 rounded-xl p-5">
                <p className="text-xs text-gray-700 leading-relaxed mb-5">
                  I hereby certify that the above-named personnel has
                  successfully completed all turnover requirements in accordance
                  with the <strong>MARIAN TBI Personnel Transition Policy</strong>.
                </p>
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">
                        MARIAN TBI Manager
                      </label>
                      <input data-field="mgrName"
                        value={mgrName}
                        onChange={(e) => setMgrName(e.target.value)}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">
                        Date
                      </label>
                      <input data-field="mgrDate"
                        type="date"
                        value={mgrDate}
                        onChange={(e) => setMgrDate(e.target.value)}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">
                        HR/Admin Officer
                      </label>
                      <input data-field="hrName"
                        value={hrName}
                        onChange={(e) => setHrName(e.target.value)}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">
                        Date
                      </label>
                      <input data-field="hrDate"
                        type="date"
                        value={hrDate}
                        onChange={(e) => setHrDate(e.target.value)}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#FF2B5E] focus:border-transparent"
                      />
                    </div>
                  </div>
                </div>
                <div className="mt-5 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                  <p className="text-[11px] text-amber-800 leading-relaxed">
                    <strong>Administration Note:</strong> In compliance with ISO
                    21001:2018, these forms must be filed in the employee's 201
                    File and digitized to ensure that the "Educational
                    Organization" maintains its history of mentorship and
                    operational intelligence.
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Hidden print area */}
      <div className="hidden">
        <div ref={printRef21}>
          <h2>
            PERSONNEL TRANSITION, DOCUMENT TURNOVER,
            <br />
            AND ASSET ENDORSEMENT FORM
          </h2>
          <table>
            <tbody>
              <tr>
                <td className="label">Employee Name:</td>
                <td>{employeeName}</td>
                <td className="label">Position:</td>
                <td>{position}</td>
              </tr>
              <tr>
                <td className="label">Department/Unit:</td>
                <td>MARIAN TBI</td>
                <td className="label">Effectivity Date:</td>
                <td>{effectivityDate}</td>
              </tr>
            </tbody>
          </table>

          <h3>KNOWLEDGE TRANSFER STATUS REPORT</h3>
          <p>
            <em>Goal: To capture Intellectual Capital before personnel departure.</em>
          </p>
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th>Description / Project Name</th>
                <th>Current Status / Phase</th>
                <th>Pending Actions & Deadlines</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              {ktRows.map((r, i) => (
                <tr key={i}>
                  <td>{r.category}</td>
                  <td>{r.description || ""}</td>
                  <td>{r.status || ""}</td>
                  <td>{r.pending || ""}</td>
                  <td>{r.remarks || ""}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <p>
            <strong>Digital Repository Path:</strong>{" "}
            {digitalRepoPath || "___________________________"}
          </p>

          <h3>PERSONNEL CLEARANCE & ENDORSEMENT FORM</h3>
          <p>
            <em>Goal: Formal sign-off for administrative and HR records.</em>
          </p>

          <h3>Section I: Document & Data Turnover (ISO 21001)</h3>
          <p>
            <span className="check">[{sec1Files ? "X" : " "}]</span> All
            project files migrated to TBI Official Cloud Drive. (Attach List)
          </p>
          <p>
            <span className="check">[{sec1Credentials ? "X" : " "}]</span> Login
            credentials for official social media/portals surrendered.
          </p>
          <p>
            <span className="check">[{sec1StatusReport ? "X" : " "}]</span>{" "}
            Status Report (Attach list) submitted and discussed with successor.
          </p>
          <p>
            Verified by: <span className="sig-line">{sec1TechAsst}</span>{" "}
            (Technical Asst.) &nbsp;&nbsp;{" "}
            <span className="sig-line">{sec1TbiManager}</span> (TBI Manager)
          </p>

          <h3>Section II: Physical Asset Return</h3>
          <p>
            <span className="check">[{laptopChecked ? "X" : " "}]</span> Laptop
            (Serial No: {laptopSerial || "_____"}) - Condition:{" "}
            {laptopCondition || "______"}
          </p>
          <p>
            <span className="check">[{tabletChecked ? "X" : " "}]</span> Tablet
            (Serial No: {tabletSerial || "_____"}) - Condition:{" "}
            {tabletCondition || "______"}
          </p>
          <p>
            <span className="check">[{keysChecked ? "X" : " "}]</span> Office
            Keys / ID Badge / Access Cards
          </p>
          <p>
            <span className="check">[{equipChecked ? "X" : " "}]</span>{" "}
            Specialized TBI Equipment (specify: {equipSpecify || "______"})
          </p>
          <p>
            Verified by: <span className="sig-line">{sec2Liaison}</span>{" "}
            (Liaison Staff) &nbsp;&nbsp;{" "}
            <span className="sig-line">{sec2Custodian}</span> (Property
            Custodian)
          </p>

          <h3>Section III: Final Management Approval</h3>
          <p>
            I hereby certify that the above-named personnel has successfully
            completed all turnover requirements in accordance with the MARIAN
            TBI Personnel Transition Policy.
          </p>
          <p>
            MARIAN TBI Manager: <span className="sig-line">{mgrName}</span>{" "}
            Date: <span className="sig-line">{mgrDate}</span>
          </p>
          <p>
            HR/Admin Officer: <span className="sig-line">{hrName}</span> Date:{" "}
            <span className="sig-line">{hrDate}</span>
          </p>
          <div className="note">
            <strong>Administration Note:</strong> In compliance with ISO
            21001:2018, these forms must be filed in the employee's 201 File and
            digitized to ensure that the "Educational Organization" maintains
            its history of mentorship and operational intelligence.
          </div>
        </div>
      </div>

      {/* Footer navigation */}
      <div className="p-4 border-t border-gray-200 flex justify-between">
        <button
          onClick={() => step > 0 && setStep(step - 1)}
          className={`flex items-center gap-1 px-4 py-2 text-sm rounded-lg transition-colors ${
            step === 0
              ? "text-gray-300 cursor-not-allowed"
              : "text-gray-600 hover:bg-gray-100"
          }`}
          disabled={step === 0}
        >
          <ChevronLeft className="w-4 h-4" /> Back
        </button>
        <div className="flex gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          >
            Close
          </button>
          {step < totalSteps - 1 && (
            <button
              onClick={() => setStep(step + 1)}
              className="flex items-center gap-1 px-5 py-2 text-sm bg-[#FF2B5E] text-white rounded-lg hover:bg-[#e0224f] transition-colors"
            >
              Next <ChevronRight className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>
    </div>
  </div>
);
} 