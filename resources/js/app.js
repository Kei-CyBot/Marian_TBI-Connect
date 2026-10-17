import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.technicalNeedsPage = (requirements, startups, selectedStartupId, selectedStartupName) => ({
    requirements,
    startups,
    selectedStartupId,
    selectedStartupName,
    statusFilter: '',
    priorityFilter: '',
    modal: false,
    editing: false,
    draft: {},
    get filteredRequirements() {
        return this.requirements.filter((item) =>
            (!this.statusFilter || item.status === this.statusFilter)
            && (!this.priorityFilter || item.priority === this.priorityFilter));
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
            status: 'Ongoing',
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
    printApprovalForm() {
        if (!this.draft.requirement || !this.draft.dateEntry) {
            window.alert('Enter the technical need and date entered before previewing the approval form.');
            return;
        }

        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[character]);
        const rows = [
            ['Technical need / subscription', this.draft.requirement],
            ['Description / purpose', this.draft.description],
            ['Estimated cost (PHP)', this.draft.price
                ? `₱${Number(this.draft.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                : ''],
            ['Duration (months)', this.draft.months],
            ['Resource / tool requested', this.draft.resource],
            ['Prospective resource person / provider', this.draft.prospect],
            ['Responsible team member', this.draft.responsible],
            ['Date entered', this.draft.dateEntry],
            ['Date needed', this.draft.dateNeeded],
            ['Remarks', this.draft.remarks],
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
                <div class="meta"><strong>Incubatee:</strong> ${escapeHtml(this.selectedStartupName)}</div>
                <table>${detailRows}</table>
                <h2>Review and approval</h2>
                <p>By signing below, the parties confirm that this technical need has been reviewed and approved for processing, subject to applicable program and procurement policies.</p>
                <div class="signatures">
                    <div><div class="signature"></div><div class="caption">Requested by — Incubatee Founder / Authorized Representative · Date</div></div>
                    <div><div class="signature"></div><div class="caption">Reviewed by — Marian TBI Technical Staff · Date</div></div>
                    <div><div class="signature"></div><div class="caption">Approved by — Authorized Marian TBI Approver · Date</div></div>
                    <div><div class="signature"></div><div class="caption">Noted by — Finance / Procurement, if applicable · Date</div></div>
                </div>
                <p class="notice">Attach the signed and approved document as a PDF. The technical need will not be saved until the signed approval is uploaded.</p>
            </body>
            </html>`);
        printWindow.document.close();
    },
    priorityClass(priority) {
        return priority === 'High' ? 'bg-red-50 text-red-700 ring-red-200'
            : priority === 'Moderate' ? 'bg-amber-50 text-amber-700 ring-amber-200'
                : 'bg-gray-100 text-gray-700 ring-gray-200';
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
