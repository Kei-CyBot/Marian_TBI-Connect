@props(['data' => []])

@php
    $blocks = \App\Helpers\FormFormatHelper::formatData(is_array($data) ? $data : []);
@endphp

<div class="space-y-3">
    @forelse ($blocks as $block)
        @if ($block['type'] === 'field')
            <div class="border-b border-gray-100 pb-2 last:border-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $block['label'] }}</p>
                <p class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-900">{{ $block['value'] }}</p>
            </div>
        @elseif ($block['type'] === 'grid')
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $block['label'] }}</p>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($block['items'] as $item)
                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                            <dt class="text-xs font-semibold text-gray-600">{{ $item['label'] }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @elseif ($block['type'] === 'table')
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $block['label'] }}</p>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-gray-200 text-gray-500">
                            <tr>
                                @foreach ($block['columns'] as $columnLabel)
                                    <th class="px-2 py-1.5 font-semibold">{{ $columnLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($block['rows'] as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td class="px-2 py-1.5 text-gray-900">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($block['type'] === 'badges')
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $block['label'] }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($block['items'] as $item)
                        <span class="rounded-full bg-[#eaf6f6] px-2.5 py-1 text-xs font-medium text-[#14595c]">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    @empty
        <p class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-xs text-gray-500">
            No response fields were recorded for this form.
        </p>
    @endforelse
</div>
