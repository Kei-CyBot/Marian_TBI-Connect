import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.technicalNeedsPage = (requirements, startups, selectedStartupId, selectedStartupName) => ({
    requirements,
    startups,
    selectedStartupId,
    selectedStartupName,
    modal: false,
    editing: false,
    draft: {},
    reviewModal: false,
    reviewItem: {},
    reviewAction: 'approved',
    approvalFormModal: false,
    approvalDraft: {},
    get filteredRequirements() {
        return this.requirements;
    },
    openCreate() {
        this.editing = false;
        this.draft = {
            id: null,
            startupId: this.selectedStartupId,
            requirement: '',
            description: '',
            price: '',
            months: '',
            responsible: '',
            dateEntry: new Date().toISOString().slice(0, 10),
            dateNeeded: '',
            resource: '',
            prospect: '',
            priority: 'Moderate',
            remarks: '',
        };
        this.modal = true;
    },
    openEdit(item) {
        this.editing = true;
        this.draft = { ...item };
        this.modal = true;
    },
    openReview(item) {
        this.reviewItem = { ...item };
        this.reviewAction = item.status === 'pending' ? 'approved' : item.status;
        this.reviewModal = true;
    },
    openApprovalForm() {
        this.approvalDraft = {
            incubatee: this.selectedStartupName || '',
            requirement: '',
            description: '',
            price: '',
            dateNeeded: '',
            priority: 'Moderate',
            remarks: '',
        };
        this.approvalFormModal = true;
    },
    printApprovalForm() {
        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[character]);
        const rows = [
            ['Technical need / subscription', this.approvalDraft.requirement],
            ['Specifications / purpose', this.approvalDraft.description],
            ['Estimated cost (PHP)', this.approvalDraft.price
                ? `₱${Number(this.approvalDraft.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                : ''],
            ['Date needed', this.approvalDraft.dateNeeded],
            ['Priority', this.approvalDraft.priority],
            ['Remarks', this.approvalDraft.remarks],
        ];
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) {
            window.alert('Allow pop-ups for this site to preview the approval form.');
            return;
        }

        const detailRows = rows.map(([label, value]) => (
            `<tr><th>${escapeHtml(label)}</th><td>${escapeHtml(value) || '&nbsp;'}</td></tr>`
        )).join('');
        printWindow.document.open();
        printWindow.document.write(`<!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Technical Need Approval Form</title>
                <style>
                    @page { size: A4; margin: 18mm; }
                    body { font: 12px Arial, sans-serif; color: #17212b; }
                    .actions { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 20px; }
                    button { border: 0; border-radius: 6px; padding: 9px 14px; font-weight: 600; cursor: pointer; }
                    .print { background: #146e72; color: white; }
                    .close { background: #edf2f4; color: #263640; }
                    h1 { font-size: 20px; margin: 0 0 4px; }
                    h2 { font-size: 14px; margin: 22px 0 8px; }
                    .brand { color: #146e72; font-size: 11px; font-weight: bold; letter-spacing: .12em; text-transform: uppercase; }
                    .sub { color: #52616b; margin-bottom: 20px; }
                    .meta { padding: 10px 12px; background: #edf7f6; border: 1px solid #b7dedd; border-radius: 6px; margin: 15px 0; }
                    table { border-collapse: collapse; width: 100%; }
                    th, td { border: 1px solid #bcc7ce; padding: 9px; text-align: left; vertical-align: top; }
                    th { width: 34%; background: #f4f7f8; }
                    .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 30px 36px; margin-top: 34px; }
                    .signature { padding-top: 35px; border-bottom: 1px solid #394650; min-height: 28px; }
                    .caption, .notice { font-size: 10px; color: #52616b; margin-top: 6px; }
                    @media print { .actions { display: none; } }
                </style>
            </head>
            <body>
                <div class="actions">
                    <button class="close" onclick="window.close()">Close</button>
                    <button class="print" onclick="window.print()">Print this form</button>
                </div>
                <div class="brand">Marian TBI Connect</div>
                <h1>Technical Need / Subscription Request</h1>
                <div class="sub">Review the request, print this form, complete signatures, and upload the signed copy.</div>
                <div class="meta"><strong>Incubatee:</strong> ${escapeHtml(this.approvalDraft.incubatee)}</div>
                <table>${detailRows}</table>
                <h2>Review and approval</h2>
                <p>By signing below, the parties confirm that this technical need has been reviewed and approved for processing, subject to applicable program and procurement policies.</p>
                <div class="signatures">
                    <div><div class="signature"></div><div class="caption">Requested by — Incubatee Founder / Authorized Representative · Date</div></div>
                    <div><div class="signature"></div><div class="caption">Reviewed by — Marian TBI Technical Staff · Date</div></div>
                    <div><div class="signature"></div><div class="caption">Approved by — Authorized Marian TBI Approver · Date</div></div>
                    <div><div class="signature"></div><div class="caption">Noted by — Finance / Procurement, if applicable · Date</div></div>
                </div>
                <p class="notice">Attach the signed and approved document through the Attach &amp; Review workflow.</p>
            </body>
            </html>`);
        printWindow.document.close();
    },
    statusClass(status) {
        return status === 'approved' ? 'bg-emerald-50/80 text-emerald-800 ring-emerald-200/70'
            : status === 'rejected' ? 'bg-rose-50/80 text-rose-800 ring-rose-200/70'
                : 'bg-amber-50/80 text-amber-800 ring-amber-200/70';
    },
    statusLabel(status) {
        return status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Pending';
    },
    priorityClass(priority) {
        return priority === 'High' ? 'bg-rose-50/80 text-rose-800 ring-rose-200/70'
            : priority === 'Moderate' ? 'bg-amber-50/80 text-amber-800 ring-amber-200/70'
                : 'bg-slate-100 text-slate-700 ring-slate-200/80';
    },
});

const sidebarStorage = {
    readRaw(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    readJson(key) {
        try {
            const parsed = JSON.parse(this.readRaw(key) ?? 'null');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch {
            return {};
        }
    },
    write(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // Storage may be unavailable in private browsing; state stays in memory.
        }
    },
};

window.appSidebar = (options = {}) => ({
    collapsed: sidebarStorage.readRaw('marian:sidebar:v2:collapsed') === 'true',
    sections: {
        operations: true,
        network: true,
        administration: true,
        ...sidebarStorage.readJson('marian:sidebar:v2:sections'),
    },
    activeSection: options.activeSection || null,
    shortcuts: options.shortcuts || {},
    altHeld: false,
    init() {
        if (this.activeSection) {
            this.sections = { ...this.sections, [this.activeSection]: true };
            this.persistSections();
        }
    },
    isOpen(section) {
        return this.sections[section] !== false;
    },
    toggle(section) {
        this.sections = { ...this.sections, [section]: !this.isOpen(section) };
        this.persistSections();
    },
    persistSections() {
        sidebarStorage.write('marian:sidebar:v2:sections', this.sections);
    },
    toggleCollapsed() {
        this.collapsed = !this.collapsed;
        sidebarStorage.write('marian:sidebar:v2:collapsed', this.collapsed);
    },
    handleShortcut(event) {
        if (event.key === 'Alt') {
            this.altHeld = true;
            return;
        }

        if (!event.altKey || this.isTyping(event)) {
            return;
        }

        if (event.code === 'BracketLeft' || event.key === '[') {
            event.preventDefault();
            this.toggleCollapsed();
            return;
        }

        const url = this.shortcuts[String(event.key).toLowerCase()];
        if (url) {
            event.preventDefault();
            window.location.href = url;
        }
    },
    isTyping(event) {
        const target = event.target;
        return target instanceof HTMLElement
            && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
    },
});

Alpine.start();

const startupFormsRoot = document.getElementById('startup-forms-root');

if (startupFormsRoot) {
    Promise.all([
        import('react'),
        import('react-dom/client'),
        import('./components/StartupFormsApp'),
    ]).then(([React, { createRoot }, { default: StartupFormsApp }]) => {
        createRoot(startupFormsRoot).render(
            React.createElement(StartupFormsApp, {
                submitUrl: startupFormsRoot.dataset.submitUrl,
            }),
        );
    });
}
