<section x-show="form.attachmentOnly" class="mb-5 rounded-2xl border border-[#1a8a8e]/20 bg-[#eaf6f6] p-4">
    <h3 class="text-sm font-semibold text-[#146e72]" x-text="form.prerequisite ? form.prerequisite.label : form.title"></h3>
    <p class="mt-1 text-xs text-gray-600">Attach the completed document required by this form as a PDF, up to 10 MB.</p>
    <label class="mt-3 block text-xs font-semibold text-slate-600" for="form-document-upload">Required document for this form (PDF)</label>
    <input id="form-document-upload" name="prerequisite" type="file" accept=".pdf,application/pdf"
           :required="form.attachmentOnly"
           @change="prerequisiteSelected=$event.target.files.length > 0"
           class="mt-1 block w-full rounded-xl border border-slate-line bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#eaf6f6] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#146e72]">
</section>
