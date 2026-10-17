import { useEffect, useState } from 'react';
import { useRef } from 'react';
import { X } from 'lucide-react';
import { StartupFormsList } from './StartupForms';

interface StartupFormsSession {
    startup: string;
    track: 'internal' | 'external';
}

interface StartupFormsAppProps {
    submitUrl: string | undefined;
}

type SourceField = { label: string; type: string; value: string | boolean | null };
type SourceControl = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

function sourceModalFor(control: SourceControl): HTMLElement | null {
    let modal: HTMLElement | null = control.parentElement;
    while (modal && !(modal.classList.contains('fixed') && modal.classList.contains('inset-0'))) {
        modal = modal.parentElement;
    }

    return modal && Number.parseInt(window.getComputedStyle(modal).zIndex, 10) >= 60
        ? modal
        : null;
}

function fieldLabel(control: SourceControl): string {
    const explicitLabel = control.labels?.[0]?.innerText.trim()
        || control.closest('label')?.textContent?.trim();
    if (explicitLabel) {
        return explicitLabel;
    }

    let container = control.parentElement;
    while (container && !container.classList.contains('fixed')) {
        const directLabel = Array.from(container.children)
            .find((child) => child.tagName === 'LABEL' && !child.contains(control));
        if (directLabel?.textContent?.trim()) {
            return directLabel.textContent.trim().replace(/\s*\*\s*$/, '');
        }

        container = container.parentElement;
    }

    const cell = control.closest('td');
    const row = cell?.closest('tr');
    if (cell && row) {
        const cells = Array.from(row.cells);
        const cellIndex = cells.indexOf(cell);
        const context = cells
            .slice(0, cellIndex)
            .map((previousCell) => previousCell.textContent?.trim())
            .filter((text): text is string => Boolean(text));
        if (context.length > 0) {
            return context.join(' — ');
        }
    }

    return control.getAttribute('aria-label')?.trim()
        || control.getAttribute('placeholder')?.trim()
        || control.getAttribute('name')?.trim()
        || 'Unlabelled field';
}

function controlValue(control: SourceControl): string | boolean | null {
    if (control instanceof HTMLInputElement && control.type === 'checkbox') {
        return control.checked;
    }
    if (control instanceof HTMLSelectElement && control.multiple) {
        return Array.from(control.selectedOptions, (option) => option.value).join(', ');
    }
    if (control instanceof HTMLInputElement && control.type === 'radio') {
        return control.checked ? control.value : null;
    }

    return control.value || null;
}

function collectVisibleSourceFormFields(): Array<SourceField & { key: string }> {
    const controls = Array.from(
        document.querySelectorAll<SourceControl>('input, select, textarea'),
    ).filter((control) => {
        const excludedTypes = ['hidden', 'file', 'button', 'submit', 'reset'];
        return !excludedTypes.includes(control.type)
            && !control.disabled
            && sourceModalFor(control) !== null
            && control.getClientRects().length > 0;
    });

    return controls.flatMap((control) => {
        const value = controlValue(control);
        if (value === null) {
            return [];
        }

        const label = fieldLabel(control);
        const type = control.type || control.tagName.toLowerCase();
        const sameLabelIndex = controls
            .filter((candidate) => fieldLabel(candidate) === label && candidate.type === control.type)
            .indexOf(control);
        const key = control instanceof HTMLInputElement && control.type === 'radio' && control.name
            ? `radio:${control.name}`
            : `${label}:${type}:${sameLabelIndex}`;

        return [{ key, label, type, value }];
    });
}

export default function StartupFormsApp({ submitUrl }: StartupFormsAppProps) {
    const [session, setSession] = useState<StartupFormsSession | null>(null);
    const [selectedFormId, setSelectedFormId] = useState<number | null>(null);
    const [signerName, setSignerName] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const selectedFormIdRef = useRef<number | null>(null);
    const capturedFields = useRef<Map<string, SourceField>>(new Map());

    useEffect(() => {
        const openForms = (event: Event) => {
            const detail = (event as CustomEvent<StartupFormsSession>).detail;
            if (!detail || !['internal', 'external'].includes(detail.track) || !detail.startup) {
                return;
            }

            setSession(detail);
            selectedFormIdRef.current = null;
            setSelectedFormId(null);
            setSignerName('');
            setError('');
            capturedFields.current.clear();
        };
        const captureField = (event: Event) => {
            if (selectedFormIdRef.current === null
                || !(event.target instanceof HTMLInputElement
                    || event.target instanceof HTMLSelectElement
                    || event.target instanceof HTMLTextAreaElement)) {
                return;
            }

            const control = event.target;
            const modal = sourceModalFor(control);
            if (!modal || control.disabled || ['hidden', 'file', 'button', 'submit', 'reset'].includes(control.type)) {
                return;
            }

            if (control instanceof HTMLInputElement && control.type === 'radio' && !control.checked) {
                return;
            }

            const label = fieldLabel(control);
            const type = control.type || control.tagName.toLowerCase();
            const sameLabelControls = Array.from(
                modal.querySelectorAll<SourceControl>('input, select, textarea'),
            ).filter((candidate) => fieldLabel(candidate) === label && candidate.type === control.type);
            const sameLabelIndex = sameLabelControls.indexOf(control);
            const key = control instanceof HTMLInputElement && control.type === 'radio' && control.name
                ? `radio:${control.name}`
                : `${label}:${type}:${sameLabelIndex}`;
            const value = controlValue(control);

            if (value !== null) {
                capturedFields.current.set(key, { label, type, value });
            }
        };

        window.addEventListener('open-source-startup-forms', openForms);
        document.addEventListener('input', captureField, true);
        document.addEventListener('change', captureField, true);
        return () => {
            window.removeEventListener('open-source-startup-forms', openForms);
            document.removeEventListener('input', captureField, true);
            document.removeEventListener('change', captureField, true);
        };
    }, []);

    const close = () => {
        setSession(null);
        selectedFormIdRef.current = null;
        setSelectedFormId(null);
        setError('');
    };

    const openAttachmentUpload = (formId: number) => {
        if (!session) {
            return;
        }

        const detail = { id: formId, startup: session.startup, track: session.track };
        close();
        window.dispatchEvent(new CustomEvent('open-form-filler', { detail }));
    };

    const submit = async () => {
        setError('');

        if (!session || !selectedFormId || !submitUrl) {
            setError('The selected form could not be submitted. Please close it and try again.');
            return;
        }

        if (!signerName.trim()) {
            setError('Enter the name of the person submitting this form.');
            return;
        }

        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            setError('The security token is missing. Refresh the page and try again.');
            return;
        }

        const payload = new FormData();
        payload.append('_token', csrfToken);
        payload.append('form_id', String(selectedFormId));
        payload.append('startup', session.startup);
        payload.append('track', session.track);
        payload.append('signer_name', signerName.trim());
        const fields = new Map(capturedFields.current);
        collectVisibleSourceFormFields().forEach(({ key, ...field }) => fields.set(key, field));
        payload.append('data', JSON.stringify({
            source: 'marian_TBI',
            fields: Array.from(fields.values()),
        }));

        setSubmitting(true);
        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload,
            });

            if (!response.ok) {
                const result = await response.json();
                setError(result.message || 'The form could not be submitted. Check the fields and try again.');
                return;
            }

            if (!response.redirected) {
                setError('The response was saved, but the File Manager destination was not returned.');
                return;
            }

            window.location.assign(response.url);
        } catch (submissionError) {
            setError(
                submissionError instanceof Error
                    ? submissionError.message
                    : 'A network error prevented the form from being submitted.',
            );
        } finally {
            setSubmitting(false);
        }
    };

    if (!session) {
        return null;
    }

    const internal = session.track === 'internal';
    const accent = internal ? '#b92369' : '#1a8a8e';

    return (
        <>
            <div
                className={selectedFormId === null
                    ? 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm'
                    : 'hidden'}
                onMouseDown={(event) => {
                    if (event.target === event.currentTarget) {
                        close();
                    }
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Escape' && selectedFormId === null) {
                        close();
                    }
                }}
            >
                <section className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <header className="flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#1a8a8e]">Incubatee workspace</p>
                            <h2 className="mt-1 text-xl font-bold tracking-tight text-slate-900">Startup forms</h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {session.startup} · {internal ? 'Internal Academic' : 'External Industry'}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={close}
                            aria-label="Close startup forms"
                            className="rounded-xl border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800"
                        >
                            <X className="h-5 w-5" />
                        </button>
                    </header>
                    <div className="overflow-y-auto bg-slate-50/70 p-6">
                        {error && (
                            <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                {error}
                            </div>
                        )}
                        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-semibold text-slate-800">Available forms</h3>
                                <p className="mt-1 text-xs leading-5 text-slate-500">
                                    Select a form to open and complete it. PDF-only forms use the required attachment flow.
                                </p>
                            </div>
                            <span
                                className="rounded-full px-3 py-1 text-xs font-semibold"
                                style={{ color: accent, backgroundColor: internal ? '#fcf0f5' : '#eaf6f6' }}
                            >
                                {internal ? 'Internal Academic' : 'External Industry'}
                            </span>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <StartupFormsList
                                onSelectForm={(formId) => {
                                    if (selectedFormIdRef.current !== formId) {
                                        capturedFields.current.clear();
                                    }
                                    selectedFormIdRef.current = formId;
                                    setSelectedFormId(formId);
                                    setError('');
                                }}
                                onAttachmentOnly={openAttachmentUpload}
                            />
                        </div>
                    </div>
                </section>
            </div>
            {selectedFormId !== null && (
                <div className="fixed inset-x-0 bottom-0 z-[80] mx-auto flex w-full max-w-7xl flex-wrap items-center gap-4 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-10px_30px_rgba(15,23,42,0.08)] backdrop-blur sm:px-6 lg:bottom-4 lg:rounded-2xl lg:border">
                    <div className="hidden min-w-0 flex-1 sm:block">
                        <p className="text-xs font-semibold text-slate-800">Save this form response</p>
                        <p className="mt-0.5 truncate text-xs text-slate-500">
                            {session.startup} · {internal ? 'Internal Academic' : 'External Industry'}
                        </p>
                    </div>
                    <label className="min-w-40 flex-1 text-xs font-semibold text-slate-600 sm:max-w-xs">
                        Submitted by
                        <input
                            value={signerName}
                            onChange={(event) => setSignerName(event.target.value)}
                            autoComplete="name"
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-normal text-slate-900 shadow-sm focus:border-[#1a8a8e] focus:outline-none focus:ring-2 focus:ring-[#1a8a8e]/20"
                            placeholder="Full name"
                        />
                    </label>
                    <button
                        type="button"
                        disabled={submitting}
                        onClick={submit}
                        className="inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-wait disabled:opacity-60"
                        style={{ backgroundColor: accent }}
                    >
                        {submitting ? 'Submitting…' : 'Save response'}
                    </button>
                </div>
            )}
        </>
    );
}
