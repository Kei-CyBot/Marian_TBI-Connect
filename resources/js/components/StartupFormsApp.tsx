import { useEffect, useRef, useState } from 'react';
import { X } from 'lucide-react';
import { StartupFormsList, FormRegistryProvider, useFormRegistry, findMissingRequiredFields } from './StartupForms';

interface StartupFormsSession {
    startup: string;
    track: 'internal' | 'external';
}

interface StartupFormsAppProps {
    submitUrl: string | undefined;
}

function StartupFormsAppInner({ submitUrl }: StartupFormsAppProps) {
    const [session, setSession] = useState<StartupFormsSession | null>(null);
    const [selectedFormId, setSelectedFormId] = useState<number | null>(null);
    const [signerName, setSignerName] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const selectedFormIdRef = useRef<number | null>(null);
    const registry = useFormRegistry();

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
        };

        window.addEventListener('open-source-startup-forms', openForms);
        return () => {
            window.removeEventListener('open-source-startup-forms', openForms);
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

        const values = registry?.read(selectedFormId) ?? null;
        if (values === null) {
            setError('The form has not finished loading. Close it and open it again.');
            return;
        }

        const missing = findMissingRequiredFields(selectedFormId, values);
        if (missing.length > 0) {
            const labels = missing.map((field) => field.label);
            const summary = labels.length <= 6
                ? labels.join(', ')
                : `${labels.slice(0, 6).join(', ')} and ${labels.length - 6} more`;
            setError(`Complete the required fields before saving: ${summary}.`);
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
        payload.append('data', JSON.stringify({
            source: 'marian_TBI',
            ...values,
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
                            data-field="signerName"
                            name="signer_name"
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

export default function StartupFormsApp(props: StartupFormsAppProps) {
    return (
        <FormRegistryProvider>
            <StartupFormsAppInner {...props} />
        </FormRegistryProvider>
    );
}
